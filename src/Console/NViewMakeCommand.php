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
	protected $type = 'View';

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
	 * @param  string  $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace)
	{
		return $rootNamespace.'\View';
	}

	/**
	 * Build the class with the given name.
	 *
	 * Remove the base controller import if we are already in base namespace.
	 *
	 * @param  string  $name
	 * @return string
	 */
	protected function buildClass($name)
	{
		$controllerNamespace = $this->getNamespace($name);

		$replace = [];

		$replace["use {$controllerNamespace}\Controller;\n"] = '';

		return str_replace(
		  array_keys($replace), array_values($replace), parent::buildClass($name)
		);
	}

}