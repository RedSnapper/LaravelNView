<?php

namespace RS\NView;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\MessageProvider;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\MessageBag;

class View implements ViewContract {
	/**
	 * Identifier for container to render the whole container
	 * in contents
	 *
	 * @var string
	 */
	const DEFAULT_SECTION = "#document";
	/**
	 * Child content
	 *
	 * @var null|Document
	 */
	protected $child;
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
	 * The Document.
	 *
	 * @var Document
	 */
	public $document;
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
	 * Has the view been compiled
	 *
	 * @var bool
	 */
	protected $compiled = false;
	/**
	 * Containers for the view
	 *
	 * @var array
	 */
	protected $containers = [];
	/**
	 * Accessor to the config for this.
	 *
	 * @var array
	 */
	protected $config = [];
//	protected $nodeRemoved = false;
	/**
	 * An array of tokens and associatedCompilers
	 * Compilers will be run in this order
	 *
	 * @var array
	 */
	protected $compilers = [
		'attr'       => 'Attribute',
		'container'  => 'Container',
		'errors'     => 'Errors',
		'auth'       => 'Auth',
		'can'        => 'Can',
		'cannot'     => 'Cannot',
		'exists'     => 'Exists',
		'empty'			 => 'NotExists',
		'match'			 => 'Match',
		'nomatch'		 => 'NoMatch',
		'include'    => 'Include',
		'pagination' => 'Pagination',
		'foreach'    => 'ForEach',
		'url'        => 'URL',
		'route'      => 'Route',
		'asset'      => 'Asset',
		'child'      => 'ChildGap',
		'replace'    => 'Replace',
		'tr'         => 'Translations',
		'null'       => 'Null'
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
		$this->factory = $factory;
		$this->document = $this->initialiseDocument($document);
		$this->container = $this->factory->getContainer();
		$this->config = $this->container['config']['view'];
		$this->viewName = $viewName;
		$this->data = $data instanceof Arrayable ? $data->toArray() : (array)$data;

		$this->loadViewController($this->viewName);
	}

	protected function initialiseDocument($name): Document {
		if (is_string($name)) {
			if (!$this->factory->hasDocument($name)) {
				$this->factory->addDocument($name, new Document($name));
			}
			return $this->factory->getDocument($name);
		}
		return new Document($name);
	}

	/**
	 * Renders the view to a string
	 *
	 * @return string
	 */
	public function render(): string {

		$this->data = $this->gatherData();

		$document = $this->compile();

		$this->tidy();

		return $document->show(true);
	}

	/**
	 * Run all compilers on the view
	 *
	 * @return Document
	 */
	public function compile(): Document {

		if ($this->isCompiled()) {
			return $this->document;
		}

		$this->factory->callComposer($this);

		// Need to render children first
		$this->renderChildren();

		$this->renderViewController();

		$this->runCompilers();

		return $this->document;
	}

	/**
	 * Get the name of the view.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->viewName ?? "(dynamic)";
	}

	/**
	 * Get the controller
	 *
	 * @return null|ViewController
	 */
	public function getController(): ?ViewController {
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
	 * Has this view already be compiled
	 *
	 * @return bool
	 */
	public function isCompiled(): bool {
		return $this->compiled;
	}

	/**
	 * @return null|Document
	 */
	public function getChild(): ?Document {
		return $this->child;
	}

	/**
	 * @param null|Document $child
	 */
	public function setChild(Document $child): void {
		$this->child = $child;
	}

	/**
	 * Does this view have a child
	 *
	 * @return bool
	 */
	public function hasChild(): bool {
		return isset($this->child);
	}

	/**
	 * Add a piece of data to the view.
	 *
	 * @param  string|array $key
	 * @param  mixed        $value
	 * @return ViewContract
	 */
	public function with($key, $value = null): ViewContract {
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
	 * @return ViewContract
	 */
	public function withErrors($provider): ViewContract {
		$this->with('errors', $this->formatErrors($provider));
		return $this;
	}

	/**
	 * @param MessageBag $provider
	 * @return ViewContract
	 */
	public function withErrorBag(MessageBag $provider) : ViewContract
	{
		$this->with('errors',$provider);
		return $this;
	}


	/**
	 * Set a document at the specified xpath.
	 *
	 * @param string        $xpath
	 * @param null|mixed    $document
	 * @param null|\DOMNode $ref
	 */
	public function set(string $xpath, $document = null, $ref = null): void {
		$this->document->set($xpath, $this->formatDocument($document), $ref);
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
	protected function runCompilers(): void {
		$errs = error_reporting();
		error_reporting(0);
		$nodes = $this->getAllTokenNodes();

		foreach ($nodes as $node) {

			if ($this->nodeIsRemoved($node)) {
				continue;
			}

			$compilers = $this->getCompilers($node);

			foreach ($compilers as $compiler) {
				list($fn, $node, $attribute) = $compiler;
				if (!(is_null($node->parentNode))) {
					$this->$fn($node, $attribute);
				}
			}

			if (count($compilers)) {
				$this->removeAttributesFromNode($node);
			}
		}
		error_reporting($errs);
	}

	/**
	 * Get all the compilers for a given node
	 *
	 * @param \DOMNode $node
	 * @return array
	 */
	protected function getCompilers(\DOMNode $node): array {
		$attr_array = $this->nodeAttrs($node);
		$result = array_reduce($attr_array, function ($carry, \DOMAttr $attr) use ($node) {
			if ($token = $this->getCompilerTokenFromAttribute($attr)) {
				$compiler = "compile{$this->compilers[$token]}";
				$carry[] = [$compiler, $node, $attr];
			}
			return $carry;
		}, []);
		return $result;
	}

	/**
	 * Returns the a compiler token for a given dom attribute
	 * If there is no compiler then false is returned
	 *
	 * @param \DOMAttr $attribute
	 * @return string
	 */
	protected function getCompilerTokenFromAttribute(\DOMAttr $attribute): ?string {

		$name = $attribute->name;

		if (!starts_with($name, $this->prefix)) {
			return null;
		}

		$token = $this->getArrayFromAttribute($name)[0];

		if ($this->isCompiler($token)) {
			return $token;
		}

		return null;
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
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileTranslations(\DOMElement $node, \DOMAttr $attr): void {

		$translator = $this->container->make('translator');

		$translation = $translator->trans($this->attValue($attr));

		$this->document->set('.', $translation, $node);
	}

	/**
	 * Compiles errors
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileErrors(\DOMElement $node, \DOMAttr $attr): void {
		if (count($this->data['errors']) > 0) {
			$errorView = $this->factory->make($this->attValue($attr), $this->data);
			$this->document->set('.', $errorView->compile(), $node);
		} else {
			$this->document->set('.', null, $node);
		}
	}

	/**
	 * Compiles child-gap
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileChildGap(\DOMElement $node, \DOMAttr $attr): void {

		$value = $this->getValue($this->attValue($attr), $this->data);
		$this->document->set('./child-gap()', $value, $node);
	}

	/**
	 * Replaces the node with data found
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileReplace(\DOMElement $node, \DOMAttr $attr): void {
		$key = $this->attValue($attr);
		$value = $this->getValue($key, $this->data);
		$this->document->set('.', $value, $node);
	}

	/**
	 * do nothing. just can use for stop.
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileNull(\DOMElement $node, \DOMAttr $attr): void {
		$key = $this->attValue($attr);
		$value = $this->getValue($key, $this->data);
	}

	/**
	 * handle existence in data.
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileExists(\DOMElement $node, \DOMAttr $attr): void {
		if (!$this->hasValue($this->attValue($attr), $this->data)) {
			$this->document->set('.', null, $node);
			$this->deleteDescendants($node);
		}
	}

	/**
	 * handle non-existence in data.
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileNotExists(\DOMElement $node, \DOMAttr $attr): void {
		if ($this->hasValue($this->attValue($attr), $this->data)) {
			$this->document->set('.', null, $node);
			$this->deleteDescendants($node);
		}
	}

	private function matching(\DOMElement $node, \DOMAttr $attr) : bool {
		$valueToMatch = $this->getCompilerParameter($node);
		$source = $this->attValue($attr);
		$sourceValue = $this->getValue($source, $this->data);
		return ($valueToMatch == $sourceValue);
	}

	/**
	 * match a value.
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileMatch(\DOMElement $node, \DOMAttr $attr): void {
		if(!$this->matching($node,$attr)) {
			$this->document->set('.', null, $node);
			$this->deleteDescendants($node);
		}
	}
	/**
	 * don't match a value.
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileNoMatch(\DOMElement $node, \DOMAttr $attr): void {
		if( $this->matching($node,$attr)) {
			$this->document->set('.', null, $node);
			$this->deleteDescendants($node);
		}
	}


	/**
	 * Security using gates
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileCan(\DOMElement $node, \DOMAttr $attr): void {

		$gate = $this->container->make('Gate');
		$activity = $this->attValue($attr); //because of a php debugging bug.
		$value = $this->getCompilerParameter($node);

		if ($gate::denies($activity, $value)) {
			$this->document->set('.', null, $node);
			$this->deleteDescendants($node);
		};
	}

	/**
	 * Security using gates
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileCannot(\DOMElement $node, \DOMAttr $attr): void {

		$gate = $this->container->make('Gate');
		$activity = $this->attValue($attr); //because of a php debugging bug.
		$value = $this->getCompilerParameter($node);

		if ($gate::allows($activity, $value)) {
			$this->document->set('.', null, $node);
			$this->deleteDescendants($node);
		};
	}

	/**
	 * Is the user logged in
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileAuth(\DOMElement $node, \DOMAttr $attr): void {

		$auth = $this->container->make('Auth');

		if ($auth::check() != filter_var($this->attValue($attr), FILTER_VALIDATE_BOOLEAN)) {
			$this->document->set('.', null, $node);
			$this->deleteDescendants($node);
		}
	}

	/**
	 * Includes
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileInclude(\DOMElement $node, \DOMAttr $attr): void {
			$params = $this->getCompilerParameter($node);
			$data = $params == "" ? $this->data : $params;
			$include = $this->factory->make($this->attValue($attr), $data);
			$this->document->set('.', $include->compile(), $node);
			$this->deleteDescendants($node);
	}

	/**
	 * URL
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileUrl(\DOMElement $node, \DOMAttr $attr): void {

		$url = preg_replace_callback('/{([\d\w\.]+)}/', function ($matches) {
			return $this->getValue($matches[1], $this->data);
		}, $this->attValue($attr));
		$this->document->set('./@href', $url, $node);
	}

	/**
	 * URL
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileRoute(\DOMElement $node, \DOMAttr $attr): void {
		$params = $this->getCompilerParameter($node);
		$route = $this->attValue($attr);
		$value = preg_replace_callback('/{([\d\w\.]+)}/', function ($matches) {
			$value = $this->getValue($matches[1],$this->data);
			return $value instanceof Model ? strtolower(class_basename($value)) : $value;
		}, $route);

		$url = URL::route($value, $params);

		$this->document->set('./@href', $url, $node);
	}

	/**
	 * Asset
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileAsset(\DOMElement $node, \DOMAttr $attr): void {

		$url = $this->container->make('url');

		$property = $node->tagName == "link" ? "href" : "src";

		$this->document->set("./@{$property}", $url->asset($this->attValue($attr)), $node);
	}

	/**
	 * Pagination
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compilePagination(\DOMElement $node, \DOMAttr $attr): void {

		$paginator = $this->getValue($this->getNodeAttribute($node, 'name'), $this->data);

		if ($paginator && $paginator->hasPages()) {
			$include = $this->factory->make($this->attValue($attr), $this->data, compact('paginator'));
			$this->document->set('.', $include->compile(), $node);
		}
	}

	/**
	 * Compiles Attribute
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileAttribute(\DOMElement $node, \DOMAttr $attr): void {

		$name = $this->getArrayFromAttribute($attr->nodeName)[1];

		$value = preg_replace_callback('/{([\d\w\.]+)}/', function ($matches) {
			return $this->getValue($matches[1], $this->data);
		}, $this->attValue($attr));

		$this->document->set("./@{$name}", $value, $node);
	}

	/**
	 * Compiles Foreach
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileForEach(\DOMElement $node, \DOMAttr $attr): void {

		$array = $this->getValue($this->attValue($attr), $this->data);
		count($array) ? $this->renderForEach($array, $node) : $this->removeNode($node);
	}

	/**
	 * Render using an array
	 *
	 * @param             $array
	 * @param \DOMElement $node
	 */
	protected function renderForEach($array, \DOMElement $node): void {

		$name = $this->getNodeAttribute($node, 'name');
		$aKey = $this->getNodeAttribute($node, 'key') ?? "#key";

		$template = $this->document->consume("./*[1]", $node);

		foreach ($array as $key => $value) {
			$item = $this->factory->make($template, array_merge($this->data, [$aKey => $key, $name => $value]));
			$this->document->set("./child-gap()", $item, $node);
		}
	}

	/**
	 * Container
	 *
	 * @param \DOMElement $node
	 * @param \DOMAttr    $attr
	 * @return void
	 */
	protected function compileContainer(\DOMElement $node, \DOMAttr $attr): void {

		// Load up our container view
		$container = $this->factory->make($this->attValue($attr), $this->data);

		//Remove container attribute
		$this->removeAttributeFromNode('container', $node);

		// Pass this node to the container
		$container->setChild($this->factory->make($node, $this->data)->compile());

		// Replace the current node with the container
		$this->document->set('.', $container, $node);

		$this->deleteDescendants($node);

		// Let the compilers know that part of the document has been removed
		// So need to check again for tokens in the view
		//$this->nodeRemoved = true;
	}

	private function deleteDescendants(\DOMNode $node = null): void {
		while (!is_null($node) && $node->firstChild) {
			$this->deleteDescendants($node->firstChild);
			$node->removeChild($node->firstChild);
		}
	}

	/**
	 * If we have a child set then we need to first insert child into
	 * the document in the appropriate content sections before parsing the rest of the document
	 */
	protected function renderChildren() : void {

		if (!$this->hasChild()) {
			return;
		}

		// Get all the contents nodes as a DOMNodeList.
		$nodes = $this->document->getList("//*[@{$this->prefix}contents]");

		foreach ($nodes as $node) {

			$attribute = $node->getAttribute("{$this->prefix}contents");

			$section = $this->getSectionFromDocument($attribute);

			// Replace the current node with content from the child
			$this->document->set('.', $section, $node);
		}
	}

	/**
	 * @param string $attribute
	 * @return mixed
	 */
	protected function getSectionFromDocument(string $attribute): Document {
		$child = $this->getChild();

		// Default section so just return the whole document
		if ($attribute === static::DEFAULT_SECTION) {
			return $child;
		}

		//Get the corresponding section from the child
		$section = $child->get("//*[@{$this->prefix}section='$attribute']");

		// If found in the child remove the section attribute
		if (!is_null($section)) {
			$section->documentElement->removeAttribute("{$this->prefix}section");
		}

		return $section;
	}

	/**
	 * Remove node from the document
	 *
	 * @param \DOMElement $node
	 */
	protected function removeNode(\DOMElement $node): void {
		$this->document->set(".", null, $node);
	}

	/**
	 * Remove all prefixed attributes from a node
	 *
	 * @param \DOMElement $node
	 */
	protected function removeAttributesFromNode(\DOMElement $node): void {
		$this->document->set("./@*[starts-with(name(),'$this->prefix') and name() != '{$this->prefix}section' ]", null, $node);
	}

	/**
	 * Remove a prefixed attribute given a node
	 *
	 * @param string      $token
	 * @param \DOMElement $node
	 */
	protected function removeAttributeFromNode(string $token, \DOMElement $node): void {
		$this->document->set("./@{$this->prefix}$token", null, $node);
	}

	/**
	 * Get node attribute using the specified prefix
	 *
	 * @param \DOMElement $node
	 * @param             $attribute
	 * @return string
	 */
	protected function getNodeAttribute(\DOMElement $node, $attribute): string {
		return $node->getAttribute("{$this->prefix}{$attribute}");
	}

	/**
	 * @return Factory
	 */
	public function getFactory(): Factory {
		return $this->factory;
	}

	/**
	 * Get value(s) from the data based on dot notation.
	 * This allows for a semi-colon delimited [name:]value array.
	 *
	 * @param string $attribute semicolon delimited
	 * @param array  $data
	 * @return mixed
	 */
	public function getValue(string $attribute, array $data) {
		/**
		 * initialise the response.
		 */
		$result = [];
		/**
		 * Get the array of parameters, which are semi-colon delimited.
		 * eg data-v.param="foo;bar;bim"
		 */
		$parameters = explode(';', $attribute);
		/**
		 * for each parameter found (to be passed)...
		 */
		foreach ($parameters as $parameter) {
			/**
			 * find out if it has been explicitly named, by using a colon
			 * eg data-v.param="foo;bar;bim:1121" the final parameter bim will hold the value 1121.
			 */
			$nameValue = explode(':', $parameter);
			/**
			 * named parameters are not mandatory, so we need to see if this parameter is named.
			 * Normally they are not.
			 */
			if (count($nameValue) < 2) { //no name/value division.
				$result[] = data_get($data, $parameter);
			} else {
				$result[$nameValue[0]] = data_get($data, $nameValue[1]);
			}
		}
		//because we don't always want multi-values array, we may just want to respond with the first value.
		return count($result) == 1 ? $result[0] : $result;
	}

	/**
	 * Test value from the data based on dot notation
	 *
	 * @param string $attribute
	 * @param array  $data
	 * @return bool
	 */
	public function hasValue(string $attribute, array $data): bool {
		return data_get($data, $attribute) !== null;
	}

	/**
	 * Loads an associated controller given a view name
	 *
	 * @param string|null $viewName
	 * @return void
	 */
	protected function loadViewController($viewName): void {
		/**
		 * First check if we have a controller declared in our view
		 * Or try and load an associated controller by view name
		 */
		if ($controller = $this->document->get("/*/@{$this->prefix}controller")) {
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
	 * Get controller class name based of view name.
	 * This now uses config settings to identify controller paths.
	 * See the view config file 'controllers' section.
	 *
	 * @param string $name
	 * @return string
	 */
	protected function getViewControllerClassName(string $name): string {

		$parts = array_map(function ($word) {
			return studly_case($word);
		}
			, explode('.', $name)
		);
		$paths = $this->config['controllers'];
		$signature = array_shift($parts);          //get $paths[0]
		if (array_key_exists($signature, $paths)) { //do we know about it?
			return $paths[$signature] . '\\' . implode('\\', $parts);
		}
		array_unshift($parts, $signature);
		return $paths["__default"] . '\\' . implode('\\', $parts);
	}

	/**
	 * Calls the render method on the associated controller
	 */
	protected function renderViewController(): void {
		if ($this->hasController()) {
			$this->controller->compose($this);
			$this->document = $this->controller->render($this->document, $this->data);
			$this->controller->creator($this);
		}
	}

	/**
	 * Remove all prefixed attributes from the view
	 */
	protected function tidy(): void {
		$this->document->set("//*/@*[starts-with(name(),'$this->prefix')]");
	}

	/**
	 * @return array
	 */
	protected function getAllTokenNodes(): array {
		return iterator_to_array($this->document->getList("//*[@*[starts-with(name(),'$this->prefix')]]"));
	}

	protected function nodeIsRemoved(\DOMNode $node): bool {
		return !isset($node->parentNode); //!isset($node->nodeType) ||
	}

	/**
	 * Get the data bound to the view instance.
	 *
	 * @return array
	 */
	protected function gatherData(): array {
		$data = array_merge($this->factory->getShared(), $this->data);

		foreach ($data as $key => $value) {
			if ($value instanceof Renderable) {
				$data[$key] = $value->compile();
			}
		}

		return $data;
	}

	/**
	 * @param $name
	 * @return array
	 */
	private function getArrayFromAttribute($name): array {

		$token = substr($name, mb_strlen($this->prefix));
		return explode('.', $token);
	}

	/**
	 * @param \DOMElement $node
	 * @return mixed|null|string
	 */
	private function getCompilerParameter(\DOMElement $node) {
		$param = @$this->getNodeAttribute($node, 'param');
		if($param=="") {
			$value = @$this->getNodeAttribute($node, 'literal');
		} else {
			$value = $this->getValue($param, $this->data);
		}
		return $value;
	}

	private function nodeAttrs(\DOMNode $node): array {
		return iterator_to_array($node->attributes);
	}

	private function attValue(\DOMAttr $attr): string {
		return $attr->nodeValue;
	}
}