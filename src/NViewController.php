<?php

namespace RS\NView;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Translation\Translator;

class NViewController implements ViewContract {

	/**
	 * The translator
	 *
	 * @var Translator
	 */
	private $translator;

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
	 * @param Translator $translator
	 * @param  string    $viewName
	 * @param  string    $path
	 * @param  mixed     $data
	 */
	public function __construct(Translator $translator,$viewName, string $path, $data = []) {
		$this->view = new NView($path);
		$this->viewName = $viewName;
		$this->path = $path;
		$this->data = $data instanceof Arrayable ? $data->toArray() : (array)$data;
		$this->translator = $translator;
	}

	public function render() {
		
		$this->compileTranslations();

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

	protected function compileTranslations(){
		$nodes = $this->view->getList('//*[@data-v.tr]');

		foreach ($nodes as $node){
			$translation = $this->translator->trans(
			  $node->getAttribute('data-v.tr')
			);
			$this->view->set('.',$translation,$node);
		}
	}

}