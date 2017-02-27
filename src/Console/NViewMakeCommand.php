<?php

namespace RS\NView\Console;

use Illuminate\Console\GeneratorCommand;

class NViewMakeCommand extends GeneratorCommand {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'make:view';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new view file';

	/**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'ViewController';

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub() {

		return __DIR__ . '/stubs/controller.stub';
	}

	/**
	 * Get the default namespace for the class.
	 *
	 * @param  string $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace) {
		return $rootNamespace . '\View';
	}

	public function fire() {
		parent::fire();

		$name = $this->getNameInput();

		$path = $this->getViewPath($name);

		if ($this->files->exists($path)) {
			$this->warn('View file already exists!');
		}

		// Next, we will generate the path to the location where this view file should get
		// written. Then, we will build the view.
		$this->makeDirectory($path);

		$this->files->put($path, $this->buildView());


		$this->info('View file created successfully.');
	}

	protected function getViewPath(string $name) {

		// Remove app namespace if provided
		$name = str_replace_first($this->rootNamespace(), '', $name);

		$name = str_replace('\\', '/', $name);

		$path = $this->files->dirname($name) . "/" . snake_case($this->files->name($name)) . ".xml";

		return $this->laravel['config']['view']['paths'][0] . "/" . $path;
	}

	/**
	 * Build the view.
	 *
	 * @return string
	 */
	protected function buildView() {
		return $stub = $this->files->get(__DIR__ . '/stubs/view.stub');;
	}

}