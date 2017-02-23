<?php

namespace RS\NView;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\FileViewFinder;

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

	/**
	 * Register the view environment.
	 *
	 * @return void
	 */
	public function registerFactory()
	{

		$this->app->alias('nview', Factory::class);

		$this->app->singleton('nview', function ($app) {

			$finder = $app['nview.finder'];

			$env = new Factory($app['translator'], $finder, $app['events']);

			// We will also set the container instance on this view environment since the
			// view composers may be classes registered in the container, which allows
			// for great testable, flexible composers for the application developer.
			$env->setContainer($app);

			//$env->share('app', $app);

			return $env;
		});


	}

	/**
	 * Register the view finder implementation.
	 *
	 * @return void
	 */
	public function registerViewFinder() {
		$this->app->bind('nview.finder', function ($app) {
			return new FileViewFinder($app['files'], $app['config']['view.paths'],['xml']);
		});
	}

	public function registerHelpers(){

	}

}
