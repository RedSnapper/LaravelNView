<?php

use PHPUnit\Framework\TestCase;

use RS\NView\Document;

class DocumentTest extends TestCase {


	function testShow(){
		$v = new Document('<!DOCTYPE div><div xmlns="http://www.w3.org/1999/xhtml"/>');
		$this->assertEquals('<div></div>',$v->show());

		$expected = <<<EOT
<?xml version="1.0"?>
<!DOCTYPE div>
<div xmlns="http://www.w3.org/1999/xhtml"></div>

EOT;

		$this->assertEquals($expected,$v->show(true),"show(true) should output full document including DOCTYPE");

	}

	public function testSetAttribute() {
		
	}

}