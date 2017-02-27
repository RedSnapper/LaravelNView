<?php

namespace RS\NView;

class NViewController {

	protected $parent;


	public function render(NView $view,array $data): NView {
		return $view;
	}

	public function renderChild(NView $view, NView $child, array $data): NView {
		return $view;
	}

	/**
	 * Bind data to the view before render.
	 *
	 * @param  NViewCompiler $view
	 * @return void
	 */
	public function compose(NViewCompiler $view) {

	}

	/**
	 * Bind data to the view after render
	 *
	 * @param  NViewCompiler $view
	 * @return void
	 */
	public function creator(NViewCompiler $view) {

	}


	/**
	 * @return bool
	 */
	public function hasParent(): bool {
		return !is_null($this->parent);
	}

	/**
	 * @return null|string
	 */
	public function getParent() {
		return $this->parent;
	}

}