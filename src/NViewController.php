<?php

namespace RS\NView;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Collection;

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
	 * The prefix for all xpaths.
	 *
	 * @var string
	 */
	protected $prefix = "data-v.";


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

		$this->compileChildGap();
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
		$nodes = $this->getNodesByToken('tr');

		foreach ($nodes as $node){
			$translation = $this->translator->trans(
			  $node->getAttribute("{$this->prefix}tr")
			);
			$this->view->set('.',$translation,$node);
		}
	}

	protected function compileChildGap(){
		$nodes = $this->getNodesByToken('child');

		$data = $this->data;

		$data = collect(array_dot($data));

		foreach ($nodes as $node){
			$attribute = $node->getAttribute("{$this->prefix}child");

			if($data->has($attribute)){
				$value = $data->get($attribute);
			}else{
				$composite = array_reverse(explode('.',$attribute));
				$accessor = array_pop($composite);

				if($data->has($accessor)){
					$value = $this->getValue($composite,$data->get($accessor));
				}else{
					$value = $attribute;
				}

			}

			$this->view->set('./child-gap()',$value,$node);
		}
	}

	/**
	 * Gets all the nodes for the given token
	 *
	 * @param string $token
	 * @return \DOMNodeList
	 */
	protected function getNodesByToken(string $token): \DOMNodeList{
		return $this->view->getList("//*[@{$this->prefix}{$token}]");
	}

	protected function getValue($composite,$data){

		// There are no more pieces left to access so return the result
		if(count($composite) == 0){
			return $data;
		}

		// Get the next accessor
		$accessor = array_pop($composite);

		// If the current data is an object then get the property of the object
		if(gettype($data) == "object"){
			return $this->getValue($composite,$data->$accessor);
		}

		// If the current data is an array then get the key of the array
		if(gettype($data) == "array"){
			$collection = collect(array_dot($data));
			return $this->getValue($composite,$collection->get($accessor));
		}


	}

}