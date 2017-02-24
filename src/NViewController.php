<?php

namespace RS\NView;

use Illuminate\Support\Collection;

class NViewController {

	protected $parent;

	/**
	 * @var Collection
	 */
	protected $data;

	public function render(NView $view):NView{
		return $view;
	}

	public function renderChild(NView $view,NView $child):NView{
		return $view;
	}

	/**
	 * @return Collection
	 */
	public function getData(): Collection {
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