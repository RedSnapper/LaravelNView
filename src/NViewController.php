<?php

namespace RS\NView;

class NViewController {

	protected $parent;

	/**
	 * @var array
	 */
	protected $data;

	public function render(NView $view):NView{
		return $view;
	}

	public function renderChild(NView $view,NView $child):NView{
		return $view;
	}

	/**
	 * @return array
	 */
	public function getData():array {
		return $this->data;
	}

	/**
	 * @param array $data
	 */
	public function setData(array $data) {
		$this->data = $data;
	}

	/**
	 * @return bool
	 */
	public function hasParent():bool {
		return !is_null($this->parent);
	}

	/**
	 * @return null|string
	 */
	public function getParent() {
		return $this->parent;
	}

}