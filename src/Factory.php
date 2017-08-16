<?php

namespace RS\NView;

use Illuminate\Support\Str;
use Illuminate\View\ViewFinderInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\View\ViewName;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\Contracts\View\Factory as FactoryContract;
use Illuminate\Contracts\View\View as ViewContract;

class Factory implements FactoryContract {

	/**
	 * The engine implementation.
	 *
	 * @var \Illuminate\View\Engines\EngineResolver
	 */
	protected $engines;

	/**
	 * The view finder implementation.
	 *
	 * @var \Illuminate\View\ViewFinderInterface
	 */
	protected $finder;

	/**
	 * The event dispatcher instance.
	 *
	 * @var \Illuminate\Contracts\Events\Dispatcher
	 */
	protected $events;

	/**
	 * The IoC container instance.
	 *
	 * @var \Illuminate\Contracts\Container\Container
	 */
	protected $container;

	/**
	 * Data that should be available to all templates.
	 *
	 * @var array
	 */
	protected $shared = [];

	/**
	 * Cached documents which have been initialised
	 * @var array
	 */
	protected $cache = [];

	/**
	 * Blade Factory
	 * @var FactoryContract|null
	 */
	protected $bladeFactory;

	/**
	 * The extension to engine bindings.
	 *
	 * @var array
	 */
	protected $extensions = [
	  'blade.php' => 'blade',
	  'php' => 'blade',
	  'css' => 'blade',
	  'xml' => 'nview',
	  'ixml'=> 'nview'
	];

	/**
	 * Create a new view factory instance.
	 *
	 * @param  \Illuminate\View\ViewFinderInterface    $finder
	 * @param  \Illuminate\Contracts\Events\Dispatcher $events
	 */
	public function __construct(ViewFinderInterface $finder, Dispatcher $events) {
		$this->engines = new EngineResolver();
		$this->finder = $finder;
		$this->events = $events;
		$this->share('__env', $this);
	}

	/**
	 * Get the evaluated view contents for the given view.
	 *
	 * @param  string $path
	 * @param  array  $data
	 * @param  array  $mergeData
	 * @return \Illuminate\Contracts\View\View
	 */
	public function file($path, $data = [], $mergeData = []) {
		$data = array_merge($mergeData, $this->parseData($data));

		return tap($this->viewInstance($path, $path, $data), function ($view) {
			$this->callCreator($view);
		});
	}

	/**
	 * Get the evaluated view contents for the given view.
	 *
	 * @param  array  $data
	 * @param  array  $mergeData
	 * @param  string $viewName
	 * @return View
	 */
	public function make($viewName, $data = [], $mergeData = []) {

		// If the viewName is a string and is not a xml string
		// Try and file the file
		if (is_string($viewName) && strpos($viewName, '<') == false) {
			$document = $this->finder->find(
			  $viewName = $this->normalizeName($viewName)
			);

			// Default laravel engine or nview engine
			$engine = $this->getEngineFromPath($document);

			if($engine =="nview"){
				return $this->makeView($viewName,$document,$data,$mergeData);
			}else{
				return $this->makeBlade($viewName,$data,$mergeData);
			}

		}

		return $this->makeView(null,$viewName,$data,$mergeData);

	}

	protected function makeView($viewName,$document,$data = [], $mergeData = []){

		// Next, we will create the view instance and call the view creator for the view
		// which can set any data, etc. Then we will return the view instance back to
		// the caller for rendering or performing other view manipulations on this.
		$data = array_merge($mergeData, $this->parseData($data));

		return tap($this->viewInstance($viewName, $document, $data), function ($view) {
			$this->callCreator($view);
		});
	}

	protected function makeBlade($viewName,$data = [], $mergeData = []){

		$factory = $this->getBladeFactory();

		return $factory->make($viewName,$data,$mergeData);
	}

	protected function getBladeFactory():FactoryContract{

		if(!isset($this->bladeFactory)){
			$this->bladeFactory = new \Illuminate\View\Factory(
			  $this->container->make('view.engine.resolver'),
			  $this->finder,
			  $this->getDispatcher()
			);
		}

		return $this->bladeFactory;
	}

	/**
	 * Get the appropriate view engine for the given path.
	 *
	 * @param  string  $path
	 * @return \Illuminate\View\Engines\EngineInterface
	 *
	 * @throws \InvalidArgumentException
	 */
	public function getEngineFromPath($path)
	{
		if (! $extension = $this->getExtension($path)) {
			throw new InvalidArgumentException("Unrecognized extension in file: $path");
		}

		$engine = $this->extensions[$extension];

		return $engine;
	}

	/**
	 * Normalize a view name.
	 *
	 * @param  string $name
	 * @return string
	 */
	protected function normalizeName($name) {
		return ViewName::normalize($name);
	}

	/**
	 * Parse the given data into a raw array.
	 *
	 * @param  mixed $data
	 * @return array
	 */
	protected function parseData($data) {
		return $data instanceof Arrayable ? $data->toArray() : $data;
	}

	/**
	 * Create a new view instance from the given arguments
	 *
	 * @param  string|null $viewName
	 * @param  mixed       $document
	 * @param  array       $data
	 * @return \Illuminate\Contracts\View\View
	 */
	protected function viewInstance($viewName, $document, $data) {
		return new View($this, $viewName, $document, $data);
	}

	/**
	 * Determine if a given view exists.
	 *
	 * @param  string $view
	 * @return bool
	 */
	public function exists($view) {
		try {
			$this->finder->find($view);
		} catch (InvalidArgumentException $e) {
			return false;
		}

		return true;
	}

	/**
	 * Add a piece of shared data to the environment.
	 *
	 * @param  array|string $key
	 * @param  mixed        $value
	 * @return mixed
	 */
	public function share($key, $value = null) {
		$keys = is_array($key) ? $key : [$key => $value];

		foreach ($keys as $key => $value) {
			$this->shared[$key] = $value;
		}

		return $value;
	}

	/**
	 * Add a location to the array of view locations.
	 *
	 * @param  string $location
	 * @return void
	 */
	public function addLocation($location) {
		$this->finder->addLocation($location);
	}

	/**
	 * Register a valid view extension and its engine.
	 *
	 * @param  string $extension
	 * @return void
	 */
	public function addExtension($extension) {
		$this->finder->addExtension($extension);
	}

	/**
	 * Add a new namespace to the loader.
	 *
	 * @param  string       $namespace
	 * @param  string|array $hints
	 * @return $this
	 */
	public function addNamespace($namespace, $hints) {
		$this->finder->addNamespace($namespace, $hints);

		return $this;
	}

	/**
	 * Replace the namespace hints for the given namespace.
	 *
	 * @param  string       $namespace
	 * @param  string|array $hints
	 * @return $this
	 */
	public function replaceNamespace($namespace, $hints) {
		$this->finder->replaceNamespace($namespace, $hints);
		return $this;
	}

	/**
	 * Get the event dispatcher instance.
	 *
	 * @return \Illuminate\Contracts\Events\Dispatcher
	 */
	public function getDispatcher() {
		return $this->events;
	}

	/**
	 * Set the event dispatcher instance.
	 *
	 * @param  \Illuminate\Contracts\Events\Dispatcher $events
	 * @return void
	 */
	public function setDispatcher(Dispatcher $events) {
		$this->events = $events;
	}

	/**
	 * Get the IoC container instance.
	 *
	 * @return \Illuminate\Contracts\Container\Container
	 */
	public function getContainer() {
		return $this->container;
	}

	/**
	 * Set the IoC container instance.
	 *
	 * @param  \Illuminate\Contracts\Container\Container $container
	 * @return void
	 */
	public function setContainer(Container $container) {
		$this->container = $container;
	}

	/**
	 * Get an item from the shared data.
	 *
	 * @param  string $key
	 * @param  mixed  $default
	 * @return mixed
	 */
	public function shared($key, $default = null) {
		return Arr::get($this->shared, $key, $default);
	}

	/**
	 * Get all of the shared data for the environment.
	 *
	 * @return array
	 */
	public function getShared() {
		return $this->shared;
	}

	public function composer($views, $callback) {
		// TODO: Implement composer() method.
	}

	public function creator($views, $callback) {
		// TODO: Implement creator() method.
	}

	/**
	 * Get the engine resolver instance.
	 *
	 * @return \Illuminate\View\Engines\EngineResolver
	 */
	public function getEngineResolver()
	{
		return $this->engines;
	}


	/**
	 * Call the creator for a given view.
	 *
	 * @param  \Illuminate\Contracts\View\View $view
	 * @return void
	 */
	public function callCreator(ViewContract $view) {
		$this->events->fire('creating: ' . $view->name(), [$view]);
	}

	/**
	 * Call the composer for a given view.
	 *
	 * @param  \Illuminate\Contracts\View\View $view
	 * @return void
	 */
	public function callComposer(ViewContract $view) {
		$this->events->fire('composing: ' . $view->name(), [$view]);
	}


	/**
	 * Add a document to the cache
	 *
	 * @param string $name
	 * @param Document $document
	 */
	public function addDocument(string $name , Document $document) {
		$this->cache[$name] = $document;
	}

	/**
	 * Is document in the cache
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasDocument(string $name):bool{
		return isset($this->cache[$name]);
	}

	/**
	 * Get document from the cache
	 *
	 * @param string $name
	 * @return Document|null
	 */
	public function getDocument(string $name){
		return new Document($this->cache[$name]);
	}

	/**
	 * Get the extension used by the view file.
	 *
	 * @param  string  $path
	 * @return string
	 */
	protected function getExtension($path)
	{
		$extensions = array_keys($this->extensions);

		return Arr::first($extensions, function ($value) use ($path) {
			return Str::endsWith($path, '.'.$value);
		});
	}

	/**
	 * Needed by laravel.
	 *
	 */
	public function flushFinderCache(){
		$factory = $this->getBladeFactory();
		$factory->flushFinderCache();
	}

}