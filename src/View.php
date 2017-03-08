<?php

namespace RS\NView;

use Illuminate\Contracts\Support\MessageProvider;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Contracts\Support\Arrayable;
use \Illuminate\Contracts\Container\Container;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\MessageBag;

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
	  'container'=>'Container',
	  'can'        => 'Can',
	  'cannot'     => 'Cannot',
	  'include'    => 'Include',
	  'pagination' => 'Pagination',
	  'foreach'    => 'ForEach',
	  'url'        => 'URL',
	  'child'      => 'ChildGap',
	  'text'       => 'Text',
	  'tr'         => 'Translations'
	];

	/**
	 * Create a new view instance.
	 *
	 * @param Factory $factory
	 * @param  string $viewName
	 * @param  mixed  $document
	 * @param  mixed  $data
	 */
	public function __construct(Factory $factory, $viewName, $document, $data = []) {
		$this->view = new Document($document);
		$this->factory = $factory;
		$this->container = $this->factory->getContainer();
		$this->viewName = $viewName;
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

		$this->factory->callComposer($this);

		$this->loadViewController($this->viewName);

		$this->renderViewController();

		$this->runCompilers();

		$this->renderParent();

		return $this->view;
	}

	/**
	 * Get the name of the view.
	 *
	 * @return string
	 */
	public function name() {
		return $this->viewName ?? "(dynamic)";
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
	 * Add validation errors to the view.
	 *
	 * @param  \Illuminate\Contracts\Support\MessageProvider|array $provider
	 * @return $this
	 */
	public function withErrors($provider) {
		$this->with('errors', $this->formatErrors($provider));
		return $this;
	}

	/**
	 * Set a document at the specified xpath.
	 *
	 * @param string        $xpath
	 * @param null|mixed    $document
	 * @param null|\DOMNode $ref
	 */
	public function set(string $xpath, $document = null, $ref = null) {
		$this->view->set($xpath, $this->formatDocument($document), $ref);
	}

	/**
	 * Compile the document if it is an instance of View
	 *
	 * @param $value
	 * @return mixed
	 */
	protected function formatDocument($value) {
		return $value instanceof self ? $value->compile() : $value;
	}

	/**
	 * Format the given message provider into a MessageBag.
	 *
	 * @param  \Illuminate\Contracts\Support\MessageProvider|array $provider
	 * @return \Illuminate\Support\MessageBag
	 */
	protected function formatErrors($provider) {
		return $provider instanceof MessageProvider
		  ? $provider->getMessageBag() : new MessageBag((array)$provider);
	}

	/**
	 * Run call the compilers
	 *
	 * @return void
	 */
	protected function runCompilers() {

		$nodes = $this->getAllTokenNodes();

		foreach ($nodes as $node) {

			$tokens = [];

			if ($this->nodeIsRemoved($node)) {
				continue;
			}

			foreach ($node->attributes as $attrNode) {

				if ($token = $this->getCompilerTokenFromAttribute($attrNode)) {

					$compiler = "compile{$this->compilers[$token]}";

					$value = $this->getNodeAttribute($node, $token);

					$this->$compiler($node, $value);

					$tokens[] = $token;
				}
			}

			if (count($tokens)) {
				$this->removeAttributesFromNode($node);
			}

		}

	}

	/**
	 * Returns the a compiler token for a given dom attribute
	 * If there is no compiler then false is returned
	 *
	 * @param \DOMAttr $attribute
	 * @return bool|string
	 */
	protected function getCompilerTokenFromAttribute(\DOMAttr $attribute) {

		$name = $attribute->name;

		if (!starts_with($name, $this->prefix)) {
			return false;
		}

		$token = substr($name, mb_strlen($this->prefix));

		if ($this->isCompiler($token)) {
			return $token;
		}

		return false;
	}

	/**
	 * For a given token is there a compiler
	 *
	 * @param string $token
	 * @return bool
	 */
	protected function isCompiler(string $token): bool {
		return isset($this->compilers[$token]);
	}

	/**
	 * Compiles translations
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compileTranslations(\DOMElement $node, $attribute) {

		$translator = $this->container->make('translator');

		$translation = $translator->trans($attribute);

		$this->view->set('.', $translation, $node);
	}

	/**
	 * Compiles child-gap
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compileChildGap(\DOMElement $node, $attribute) {

		$value = $this->getValue($attribute, $this->data);

		$this->view->set('./child-gap()', $value, $node);
	}

	/**
	 * Compiles text
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compileText(\DOMElement $node, $attribute) {

		$value = $this->getValue($attribute, $this->data);

		$this->view->set('.', $value, $node);
	}

	/**
	 * Security using gates
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compileCan(\DOMElement $node, $attribute) {

		$gate = $this->container->make('Gate');

		if ($gate::denies($attribute)) {
			$this->view->set('.', null, $node);
		};
	}

	/**
	 * Security using gates
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compileCannot(\DOMElement $node, $attribute) {

		$gate = $this->container->make('Gate');

		if ($gate::allows($attribute)) {
			$this->view->set('.', null, $node);
		};
	}

	/**
	 * Includes
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compileInclude(\DOMElement $node, $attribute) {

		$include = $this->factory->make($attribute, $this->data);
		$this->view->set('.', $include->compile(), $node);
	}

	/**
	 * URL
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compileURL(\DOMElement $node, $attribute) {

		$url = preg_replace_callback('/{([\d\w\.]+)}/', function ($matches) {
			return $this->getValue($matches[1], $this->data);
		}, $attribute);

		$this->view->set('./@href', $url, $node);
	}

	/**
	 * Container
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compileContainer(\DOMElement $node, $attribute) {


		$container = $this->factory->make($attribute, $this->data);

		$this->removeAttributeFromNode('container',$node);

		$child = $this->factory->make($this->view->get('.',$node),$this->data);

		$containerView = $container->compile();

		if ($container->hasController()) {
			$view = $container->getController()->renderChild($containerView, $child->compile(), $this->data);
			$this->view->set('.',$view,$node);
		}


	}


	/**
	 * Pagination
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compilePagination(\DOMElement $node, $attribute) {

		$paginator = $this->getValue($this->getNodeAttribute($node, 'name'), $this->data);

		if($paginator->hasPages()){
			$include = $this->factory->make($attribute, $this->data, compact('paginator'));
			$this->view->set('.', $include->compile(), $node);
		}

	}

	/**
	 * Compiles Foreach
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return void
	 */
	protected function compileForEach(\DOMElement $node, $attribute) {

		$array = $this->getValue($attribute, $this->data);
		count($array) ? $this->renderForEach($array, $node) : $this->removeNode($node);
	}

	/**
	 * Render using an array
	 *
	 * @param             $array
	 * @param \DOMElement $node
	 */
	protected function renderForEach($array, \DOMElement $node) {

		$name = $this->getNodeAttribute($node, 'name');

		$template = $this->view->consume("./*[1]", $node);

		foreach ($array as $value) {
			$item = $this->factory->make($template, array_merge($this->data, [$name => $value]));
			$this->view->set("./child-gap()", $item, $node);
		}
	}

	/**
	 * Remove node from the document
	 *
	 * @param \DOMElement $node
	 */
	protected function removeNode(\DOMElement $node) {
		$this->view->set(".", null, $node);
	}

	/**
	 * Remove all prefixed attributes from a node
	 *
	 * @param \DOMElement $node
	 */
	protected function removeAttributesFromNode(\DOMElement $node) {
		$this->view->set("./@*[starts-with(name(),'$this->prefix')]", null, $node);
	}

	/**
	 * Remove a prefixed attribute given a node
	 *
	 * @param string      $token
	 * @param \DOMElement $node
	 */
	protected function removeAttributeFromNode(string $token, \DOMElement $node){
		$this->view->set("./@{$this->prefix}$token", null, $node);
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
	 * Loads an associated controller given a view name
	 *
	 * @param string|null $viewName
	 * @return void
	 */
	protected function loadViewController($viewName) {

		// First check if we have a controller declared in our view
		// Or try and load an associated controller by view name

		if ($controller = $this->view->get("/*/@{$this->prefix}controller")) {
			$this->loadViewControllerClass($controller);
		} else {
			$this->loadViewControllerClass($viewName);
		}
	}

	/**
	 * Load controller class based on view name
	 *
	 * @param string|null $name
	 * @return bool
	 */
	protected function loadViewControllerClass(string $name = null): bool {

		if (is_null($name)) {
			return false;
		}

		$class = $this->getViewControllerClassName($name);
		if ($exists = class_exists($class)) {
			$this->controller = $this->container->make($class);
		}
		return $exists;
	}

	/**
	 * Get controller class name based of view name
	 *
	 * @param string $name
	 * @return string
	 */
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
	 * @return array
	 */
	protected function getAllTokenNodes(): array {
		return iterator_to_array($this->view->getList("//*[@*[starts-with(name(),'$this->prefix')]]"));
	}

	protected function nodeIsRemoved(\DOMNode $node): bool {
		return !isset($node->nodeType);
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