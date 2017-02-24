<?php

namespace RS\NView;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use \Illuminate\Contracts\Container\Container;

class NViewCompiler implements ViewContract {

	/**
	 * The factory
	 *
	 * @var Factory
	 */
	protected $factory;

	/**
	 * The container
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * The name of the view.
	 *
	 * @var NView
	 */
	protected $view;

	/**
	 * The name of the view.
	 *
	 * @var string
	 */
	protected $viewName;

	/**
	 * The array of view data.
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * The path to the view file.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * The prefix for all xpaths.
	 *
	 * @var string
	 */
	protected $prefix = "data-v.";

	/**
	 * @var null|NViewController
	 */
	protected $controller;

	protected $compilers = [
	  ['token' => 'can', 'function' => 'Can'],
	  ['token' => 'cannot', 'function' => 'Cannot'],
	  ['token' => 'child', 'function' => 'ChildGap'],
	  ['token' => 'text', 'function' => 'Text'],
	  ['token' => 'tr', 'function' => 'Translations'],
	];

	/**
	 * Create a new view instance.
	 *
	 * @param Factory $factory
	 * @param  string $viewName
	 * @param  string $path
	 * @param  mixed  $data
	 */
	public function __construct(Factory $factory, $viewName, string $path, $data = []) {
		$this->view = new NView($path);
		$this->factory = $factory;
		$this->container = $this->factory->getContainer();
		$this->viewName = $viewName;
		$this->path = $path;
		$this->data = $data instanceof Arrayable ? $data->toArray() : (array)$data;
	}

	public function render() {

		$this->data = $this->gatherData();

		$view = $this->compile();

		$this->tidy();

		return $view->show(true);
	}


	public function compile() {

		$this->loadViewController($this->viewName);

		$this->runCompilers();

		$this->renderViewController();
		$this->renderParent();

		return $this->view;
	}

	public function name() {
		return $this->viewName;
	}

	/**
	 * Get the controller
	 *
	 * @return null|NViewController
	 */
	public function getController() {
		return $this->controller;
	}

	/**
	 * Has a controller
	 *
	 * @return bool
	 */
	public function hasController(): bool {
		return !is_null($this->controller);
	}

	/**
	 * Add a piece of data to the view.
	 *
	 * @param  string|array $key
	 * @param  mixed        $value
	 * @return $this
	 */
	public function with($key, $value = null) {
		if (is_array($key)) {
			$this->data = array_merge($this->data, $key);
		} else {
			$this->data[$key] = $value;
		}

		return $this;
	}

	protected function runCompilers() {

		$collection = collect(array_dot($this->data));

		$tokens = $this->getTokensFromView();

		foreach ($this->compilers as $compiler) {

			if (in_array($compiler['token'], $tokens)) {
				$compiler = "compile{$compiler['function']}";
				$this->$compiler($collection);
			}
		}
	}

	protected function compileTranslations(Collection $collection) {

		$translator = $this->container->make('translator');

		$this->compileNodes('tr', function (\DOMElement $node, $attribute) use ($translator) {

			$translation = $translator->trans($attribute);

			$this->view->set('.', $translation, $node);
		});
	}

	protected function compileChildGap(Collection $data) {

		$this->compileNodes('child', function (\DOMElement $node, $attribute) use ($data) {

			$value = $this->getValue($attribute, $data);

			$this->view->set('./child-gap()', $value, $node);
		});
	}

	protected function compileText(Collection $data) {

		$this->compileNodes('text', function (\DOMElement $node, $attribute) use ($data) {

			$value = $this->getValue($attribute, $data);

			$this->view->set('.', $value, $node);
		});
	}

	protected function compileCan(Collection $data) {

		$gate = $this->container->make('Gate');

		$this->compileNodes('can', function (\DOMElement $node, $attribute) use ($gate) {

			if ($gate::denies($attribute)) {
				$this->view->set('.', null, $node);
			};
		});
	}

	protected function compileCannot(Collection $data) {

		$gate = $this->container->make('Gate');

		$this->compileNodes('cannot', function (\DOMElement $node, $attribute) use ($gate) {

			if ($gate::allows($attribute)) {
				$this->view->set('.', null, $node);
			};
		});
	}

	protected function compileNodes($token, \Closure $closure) {
		$nodes = $this->getNodesByToken($token);

		$closure = $closure->bindTo($this);

		foreach ($nodes as $node) {

			$attribute = $this->getNodeAttribute($node, $token);
			$closure($node, $attribute);
		}
	}

	protected function getNodeAttribute(\DOMElement $node, $attribute) {

		return $node->getAttribute("{$this->prefix}{$attribute}");
	}

	/**
	 * Get value from the data based on dot notation
	 *
	 * @param string     $attribute
	 * @param Collection $data
	 * @return mixed|string
	 */
	protected function getValue(string $attribute, Collection $data): string {

		if ($data->has($attribute)) {
			return $data->get($attribute);
		} else {
			$composite = array_reverse(explode('.', $attribute));
			$accessor = array_pop($composite);

			if ($data->has($accessor)) {
				return $this->getValueFromData($composite, $data->get($accessor));
			} else {
				return $attribute;
			}
		}
	}

	/**
	 * Gets all the nodes for the given token
	 *
	 * @param string $token
	 * @return \DOMNodeList
	 */
	protected function getNodesByToken(string $token): \DOMNodeList {

		return $this->view->getList("//*[@{$this->prefix}{$token}]");
	}

	/**
	 * Returns the value from the data based on dot notation
	 *
	 * @param array $composite
	 * @param mixed $data
	 * @return mixed
	 */
	protected function getValueFromData($composite, $data) {

		// There are no more pieces left to access so return the result
		if (count($composite) == 0) {
			return $data;
		}

		// Get the next accessor
		$accessor = array_pop($composite);

		// If the current data is an object then get the property of the object
		if (gettype($data) == "object") {
			return $this->getValueFromData($composite, $data->$accessor);
		}

		// If the current data is an array then get the key of the array
		if (gettype($data) == "array") {
			$collection = collect(array_dot($data));
			return $this->getValueFromData($composite, $collection->get($accessor));
		}
	}

	protected function loadViewController(string $viewName) {

		$viewName = preg_replace_callback("/\.([a-z])/",
		  function ($matches) {
			  return "\\" . strtoupper($matches[1]);
		  },
		  $viewName);

		$class = "App\\View\\" . studly_case($viewName);

		if (class_exists($class)) {
			$this->controller = $this->container->make($class);
			$this->controller->setData($this->data);
		}
	}

	protected function renderParent() {
		if ($this->hasController() && $this->controller->hasParent()) {
			return $this->view = $this->renderParentView($this->controller->getParent());
		}

		if ($parent = $this->view->get("/*/@{$this->prefix}container")) {
			return $this->view = $this->renderParentView($parent);
		}
	}

	protected function renderParentView(string $viewName): NView {
		$parent = $this->factory->make($viewName, $this->data);
		$view = $parent->compile();
		if ($parent->hasController()) {
			$view = $parent->getController()->renderChild($view, $this->view);
		}
		return $view;
	}

	protected function renderViewController() {
		if ($this->hasController()) {
			return $this->view = $this->controller->render($this->view);
		}
	}

	protected function tidy() {
		$this->view->set("//*/@*[starts-with(name(),'$this->prefix')]");
	}

	protected function getTokensFromView(): array {

		$attributes = $this->view->getList("//*/@*[starts-with(name(),'$this->prefix')]");

		$tokens = [];

		foreach ($attributes as $attribute) {
			if (starts_with($attribute->name, $this->prefix)) {
				$tokens[] = substr($attribute->name, mb_strlen($this->prefix));
			}
		}

		return array_unique($tokens);
	}

	/**
	 * Get the data bound to the view instance.
	 *
	 * @return array
	 */
	protected function gatherData() {
		$data = array_merge($this->factory->getShared(), $this->data);

		foreach ($data as $key => $value) {
			if ($value instanceof Renderable) {
				$data[$key] = $value->render();
			}
		}

		return $data;
	}

}