<?php

namespace RS\NView\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class NViewMakeCommand extends Command {

	/**
	 * The filesystem instance.
	 *
	 * @var \Illuminate\Filesystem\Filesystem
	 */
	protected $files;

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
	 * Create a new controller creator command instance.
	 *
	 * @param  \Illuminate\Filesystem\Filesystem $files
	 * @return void
	 */
	public function __construct(Filesystem $files) {
		parent::__construct();

		$this->files = $files;
	}

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub() {
		return __DIR__ . '/stubs/view.stub';
	}


	public function fire() : bool {

		$name = $this->getNameInput();

		$path = $this->getViewPath($name);

		if ($this->files->exists($path)) {
			$this->error('View file already exists!');
			return false;
		}

		// Next, we will generate the path to the location where this view file should get
		// written. Then, we will build the view.
		$this->makeDirectory($path);

		$this->files->put($path, $this->buildView());

		$this->info('View file created successfully.');

		if($this->option('controller')){
			$this->generateViewController($name);
		}
		return true;
	}

	protected function getViewPath(string $name) {

		$path = str_replace('.', '/', $name) . ".xml";

		return $this->laravel['config']['view']['paths'][0] . "/" . $path;
	}

	/**
	 * Generate a view controller
	 *
	 * @param string $name
	 */
	protected function generateViewController(string $name){
		$this->call('make:viewcontroller',['name'=> $this->getViewControllerName($name)]);
	}

	protected function getViewControllerName(string $name){

		$parts = array_map(function($word) { return studly_case($word); }
			,explode('.',$name)
		);

		return implode('\\',$parts);

	}

	/**
	 * Build the view.
	 *
	 * @return string
	 */
	protected function buildView() {
		return $stub = $this->files->get($this->getStub());
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments() {
		return [
		  ['name', InputArgument::REQUIRED, 'The name of the view'],
		];
	}

	/**
	 * Get the desired class name from the input.
	 *
	 * @return string
	 */
	protected function getNameInput() {
		return trim($this->argument('name'));
	}

	/**
	 * Build the directory for the view file if necessary.
	 *
	 * @param  string $path
	 * @return string
	 */
	protected function makeDirectory($path) {
		if (!$this->files->isDirectory(dirname($path))) {
			$this->files->makeDirectory(dirname($path), 0777, true, true);
		}

		return $path;
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions() {
		return [
		  ['controller', 'c', InputOption::VALUE_NONE, 'Generate a view controller for the given view.'],
		];
	}

}