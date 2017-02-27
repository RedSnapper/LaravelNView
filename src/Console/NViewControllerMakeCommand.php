<?php

namespace RS\NView\Console;

use Illuminate\Console\GeneratorCommand;

class NViewControllerMakeCommand extends GeneratorCommand {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'make:viewcontroller';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new view controller';

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
}