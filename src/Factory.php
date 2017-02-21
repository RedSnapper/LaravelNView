<?php

namespace RS\NView;

use Illuminate\View\ViewFinderInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\View\ViewName;
use InvalidArgumentException;
use Illuminate\Contracts\Translation\Translator;

class Factory {

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
	 * Create a new view factory instance.
	 * @param  \Illuminate\Contracts\Translation\Translator $translator
	 * @param  \Illuminate\View\ViewFinderInterface    $finder
	 * @param  \Illuminate\Contracts\Events\Dispatcher $events
	 */
	public function __construct(Translator $translator,ViewFinderInterface $finder, Dispatcher $events) {
		$this->translator = $translator;
		$this->finder = $finder;
		$this->events = $events;
		//$this->share('__env', $this);
	}

	/**
	 * Get the evaluated view contents for the given view.
	 *
	 * @param  string $view
	 * @return NView
	 */
	public function make($view) {

		$view = $this->normalizeName($view);
		try {
			$view = $this->finder->find($view);
		} catch (InvalidArgumentException $e) {
		}

		return tap($this->viewInstance($view), function ($view) {
			//$this->callCreator($view);
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
	 * Create a new view instance from the given arguments.
	 *
	 * @param  string $view
	 * @return NView
	 */
	protected function viewInstance($view) {
		return new NView($this->translator,$view);
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
	 * Get the view finder instance.
	 *
	 * @return \Illuminate\View\ViewFinderInterface
	 */
	public function getFinder()
	{
		return $this->finder;
	}


}