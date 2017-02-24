<?php

namespace RS\NView;

class NViewController {

	protected $parent = null;

	public function render(NView $view):NView{
		return $view;
	}

	public function renderChild(NView $view,NView $child):NView{
		return $view;
	}

	/**
	 * @return null|string
	 */
	public function getParent() {
		return $this->parent;
	}

}