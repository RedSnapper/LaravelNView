<?php

namespace RS\NView;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Contracts\Support\Arrayable;

class NViewController implements ViewContract {

	/**
	 * The name of the view.
	 *
	 * @var NView
	 */
	protected $view;

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
	 * The path to the view file.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Create a new view instance.
	 *
	 * @param  string $viewName
	 * @param  string $path
	 * @param  mixed  $data
	 */
	public function __construct(string $viewName, string $path, $data = []) {
		$this->view = new NView($path);
		$this->viewName = $viewName;
		$this->path = $path;
		$this->data = $data instanceof Arrayable ? $data->toArray() : (array)$data;
	}

	public function render() {
		return $this->view->show(true);
	}

	public function name() {
		return $this->viewName;
	}

	/**
	 * Add a piece of data to the view.
	 *
	 * @param  string|array $key
	 * @param  mixed        $value
	 * @return $this
	 */
	public function with($key, $value = null) {
		if (is_array($key)) {
			$this->data = array_merge($this->data, $key);
		} else {
			$this->data[$key] = $value;
		}

		return $this;
	}

}