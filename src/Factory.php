<?php

namespace RS\NView;

use Illuminate\View\ViewFinderInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\View\ViewName;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\Container\Container;
use Illuminate\View\Concerns;

use Illuminate\Contracts\View\Factory as FactoryContract;

class Factory implements FactoryContract {

	use Concerns\ManagesEvents;

	/**
	 * The Translator implementation.
	 *
	 * @var \Illuminate\Contracts\Translation\Translator
	 */
	protected $translator;

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
	 * Create a new view factory instance.
	 *
	 * @param  \Illuminate\Contracts\Translation\Translator $translator
	 * @param  \Illuminate\View\ViewFinderInterface         $finder
	 * @param  \Illuminate\Contracts\Events\Dispatcher      $events
	 */
	public function __construct(Translator $translator, ViewFinderInterface $finder, Dispatcher $events) {
		$this->translator = $translator;
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
	 * @param  string $view
	 * @return NView
	 */
	public function make($view, $data = [], $mergeData = []) {

		$path = $this->finder->find(
		  $view = $this->normalizeName($view)
		);

		// Next, we will create the view instance and call the view creator for the view
		// which can set any data, etc. Then we will return the view instance back to
		// the caller for rendering or performing other view manipulations on this.
		$data = array_merge($mergeData, $this->parseData($data));

		return tap($this->viewInstance($view, $path, $data), function ($view) {
			$this->callCreator($view);
		});
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
	 * Create a new view instance from the given arguments.
	 *
	 * @param  string $view
	 * @param  string $path
	 * @param  array  $data
	 * @return \Illuminate\Contracts\View\View
	 */
	protected function viewInstance($view, $path, $data) {
		return new NViewCompiler($this,$view, $path, $data);
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

}