<?php

namespace RS\NView;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\FileViewFinder;
use RS\NView\Console\NViewMakeCommand;
use RS\NView\Console\NViewControllerMakeCommand;

class NViewServiceProvider extends ServiceProvider {

	/**
	 * Register the application services.
	 *
	 * @return void
	 */
	public function register() {

		$this->registerFactory();

		$this->registerViewFinder();
	}

	public function boot(){
		if ($this->app->runningInConsole()) {
			$this->commands([
			  NViewMakeCommand::class,
			  NViewControllerMakeCommand::class
			]);
		}
	}

	/**
	 * Register the view environment.
	 *
	 * @return void
	 */
	public function registerFactory()
	{

		$this->app->alias('view', Factory::class);

		$this->app->singleton('view', function ($app) {

			$finder = $app['view.finder'];

			$env = new Factory($finder, $app['events']);

			// We will also set the container instance on this view environment since the
			// view composers may be classes registered in the container, which allows
			// for great testable, flexible composers for the application developer.
			$env->setContainer($app);

			$env->share('app', $app);

			return $env;
		});


	}

	/**
	 * Register the view finder implementation.
	 *
	 * @return void
	 */
	public function registerViewFinder() {
		$this->app->bind('view.finder', function ($app) {
			return new FileViewFinder($app['files'], $app['config']['view.paths'],['xml']);
		});
	}

	public function registerHelpers(){

	}

}
