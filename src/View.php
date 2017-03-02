<?php

namespace RS\NView;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Contracts\Support\Arrayable;
use \Illuminate\Contracts\Container\Container;

use Illuminate\Contracts\Support\Renderable;

class View implements ViewContract {

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
	 * @var Document
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
	 * The prefix for all xPaths.
	 *
	 * @var string
	 */
	protected $prefix = "data-v.";

	/**
	 * @var null|ViewController
	 */
	protected $controller;

	/**
	 * An array of tokens and associatedCompilers
	 * Compilers will be run in this order
	 *
	 * @var array
	 */
	protected $compilers = [
	  ['token' => 'can', 'function' => 'Can'],
	  ['token' => 'cannot', 'function' => 'Cannot'],
	  ['token' => 'include', 'function' => 'Include'],
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
		$this->view = new Document($path);
		$this->factory = $factory;
		$this->container = $this->factory->getContainer();
		$this->viewName = $viewName;
		$this->path = $path;
		$this->data = $data instanceof Arrayable ? $data->toArray() : (array)$data;
	}

	/**
	 * Renders the view to a string
	 *
	 * @return string
	 */
	public function render(): string {

		$this->data = $this->gatherData();

		$view = $this->compile();

		$this->tidy();

		return $view->show(true);
	}

	/**
	 * Run all compilers on the view
	 *
	 * @return Document
	 */
	public function compile() {

		$this->loadViewController($this->viewName);

		$this->runCompilers();

		$this->renderViewController();
		$this->renderParent();

		return $this->view;
	}

	/**
	 * Get the name of the view.
	 *
	 * @return string
	 */
	public function name() {
		return $this->viewName;
	}

	/**
	 * Get the controller
	 *
	 * @return null|ViewController
	 */
	public function getController() {
		return $this->controller;
	}

	/**
	 * Does the view have an associated controller
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

	/**
	 * Run call the compilers
	 *
	 * @return void
	 */
	protected function runCompilers() {

		$tokens = $this->getTokensFromView();

		foreach ($this->compilers as $compiler) {

			if (in_array($compiler['token'], $tokens)) {
				$compiler = "compile{$compiler['function']}";
				$this->$compiler();
			}
		}
	}

	/**
	 * Compiles translations
	 *
	 * @return void
	 */
	protected function compileTranslations() {

		$translator = $this->container->make('translator');

		$this->compileNodes('tr', function (\DOMElement $node, $attribute) use ($translator) {

			$translation = $translator->trans($attribute);

			$this->view->set('.', $translation, $node);
		});
	}

	/**
	 * Compiles child-gap
	 *
	 * @return void
	 */
	protected function compileChildGap() {

		$this->compileNodes('child', function (\DOMElement $node, $attribute) {

			$value = $this->getValue($attribute, $this->data);

			$this->view->set('./child-gap()', e($value), $node);
		});
	}

	/**
	 * Compiles text
	 *
	 * @return void
	 */
	protected function compileText() {

		$this->compileNodes('text', function (\DOMElement $node, $attribute) {

			$value = $this->getValue($attribute, $this->data);

			$this->view->set('.', e($value), $node);
		});
	}

	/**
	 * Security using gates
	 *
	 * @return void
	 */
	protected function compileCan() {

		$gate = $this->container->make('Gate');

		$this->compileNodes('can', function (\DOMElement $node, $attribute) use ($gate) {

			if ($gate::denies($attribute)) {
				$this->view->set('.', null, $node);
			};
		});
	}

	/**
	 * Security using gates
	 *
	 * @return void
	 */
	protected function compileCannot() {

		$gate = $this->container->make('Gate');

		$this->compileNodes('cannot', function (\DOMElement $node, $attribute) use ($gate) {

			if ($gate::allows($attribute)) {
				$this->view->set('.', null, $node);
			};
		});
	}

	/**
	 * Includes
	 *
	 * @return void
	 */
	protected function compileInclude() {

		$this->compileNodes('include', function (\DOMElement $node, $attribute) {
			$include = $this->factory->make($attribute, $this->data);
			$this->view->set('.', $include->compile(), $node);
		});
	}

	/**
	 * Iterates through nodes which match the given token
	 *
	 * @param          $token
	 * @param \Closure $closure
	 */
	protected function compileNodes($token, \Closure $closure) {
		$nodes = $this->getNodesByToken($token);

		$closure = $closure->bindTo($this);

		foreach ($nodes as $node) {

			$attribute = $this->getNodeAttribute($node, $token);
			$closure($node, $attribute);
		}
	}

	/**
	 * Get node attribute using the specified prefix
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return string
	 */
	protected function getNodeAttribute(\DOMElement $node, $attribute) {
		return $node->getAttribute("{$this->prefix}{$attribute}");
	}

	/**
	 * Get value from the data based on dot notation
	 *
	 * @param string $attribute
	 * @param array  $data
	 * @return mixed|string
	 */
	protected function getValue(string $attribute, array $data) {
		return data_get($data, $attribute);
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
	 * Loads an associated controller given a view name
	 *
	 * @param string $viewName
	 * @return void
	 */
	protected function loadViewController(string $viewName) {

		// First check if we have a controller declared in our view
		// Or try and load an associated controller by view name

		if($controller = $this->view->get("/*/@{$this->prefix}controller")){
			$this->loadViewControllerClass($controller);
		}else{
			$this->loadViewControllerClass($viewName);
		}

	}

	protected function loadViewControllerClass($name):bool{
		$class = $this->getViewControllerClassName($name);
		if ($exists = class_exists($class)) {
			$this->controller = $this->container->make($class);
		}
		return $exists;
	}

	protected function getViewControllerClassName(string $name) {

		$parts = array_map(function ($word) {
			return studly_case($word);
		}
		  , explode('.', $name)
		);

		return $this->container->getNamespace() . "View\\" . implode('\\', $parts);
	}

	/**
	 * Render the parent of the given view
	 *
	 * @return Document
	 */
	protected function renderParent() {
		if ($this->hasController() && $this->controller->hasParent()) {
			return $this->view = $this->renderParentView($this->controller->getParent());
		}

		if ($parent = $this->view->get("/*/@{$this->prefix}parent")) {
			return $this->view = $this->renderParentView($parent);
		}
	}

	/**
	 * Renders the parent given a view name
	 * Calls renderChild on the controller
	 *
	 * @param string $viewName
	 * @return Document
	 */
	protected function renderParentView(string $viewName): Document {
		$parent = $this->factory->make($viewName, $this->data);
		$parentView = $parent->compile();
		if ($parent->hasController()) {
			$parentView = $parent->getController()->renderChild($parentView, $this->view, $this->data);
		}
		return $parentView;
	}

	/**
	 * Calls the render method on the associated controller
	 */
	protected function renderViewController() {
		if ($this->hasController()) {
			$this->controller->compose($this);
			$this->view = $this->controller->render($this->view, $this->data);
			$this->controller->creator($this);
		}
	}

	/**
	 * Remove all prefixed attributes from the view

	 */
	protected function tidy() {
		$this->view->set("//*/@*[starts-with(name(),'$this->prefix')]");
	}

	/**
	 * Returns a unique list of all the tokens found in the view
	 *
	 * @return array
	 */
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
				$data[$key] = $value->compile();
			}
		}

		return $data;
	}

}