<?php

namespace RS\NView\Facades;

use Illuminate\Support\Facades\Facade;

class NView extends Facade {
	protected static function getFacadeAccessor() {
		return 'nview';
	}
}

