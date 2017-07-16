<?php

use PHPUnit\Framework\TestCase;

use RS\NView\Document;

class DocumentTest extends TestCase {


	function testShow(){
		$v = $this->getView();
		$this->assertEquals('<div></div>',$v->show());

		$expected = <<<EOT
<?xml version="1.0"?>
<!DOCTYPE div>
<div xmlns="http://www.w3.org/1999/xhtml"></div>

EOT;

		$this->assertEquals($expected,$v->show(true),"show(true) should output full document including DOCTYPE");

	}

	public function testSetAttribute() {

		$v = $this->getView();
		$v->set('./@id',"foo");
		$this->assertEquals('<div id="foo"></div>',$v->show(),"Should be able to add attribute to element");

		$v = $this->getView();
		$v->set('./@id',"");
		$this->assertEquals('<div id=""></div>',$v->show(),"Should be able to add attribute with an empty string value");

		$v = $this->getView(['id'=>'foo']);
		$v->set('./@id',"bar");
		$this->assertEquals('<div id="bar"></div>',$v->show(),"Should be able to change attribute value");

		$v = $this->getView(['id'=>'foo']);
		$v->set('./@id');
		$this->assertEquals('<div></div>',$v->show(),"Should be able to delete attribute");

		$v = $this->getView(['id'=>'foo']);
		$v->set('./@id/child-gap()',"bar");
		$this->assertEquals('<div id="foobar"></div>',$v->show(),"Should be able to append to the attribute value");

		$v = $this->getView(['id'=>'foo']);
		$v->set('./@id/preceding-gap()',"bar");
		$this->assertEquals('<div id="barfoo"></div>',$v->show(),"Should be able to prepend to the attribute value");

		$v = $this->getView(['id'=>'foo']);
		$v->set('./@id/following-gap()',"bar");
		$this->assertEquals('<div id="foobar"></div>',$v->show(),"Should be able to append to the attribute value");

		$v = $this->getView();
		$v->set('./@id',"foo&bar");
		$this->assertEquals('<div id="foo&amp;bar"></div>',$v->show(),"Should xml encode attributes");
	}

	private function getView(array $attributes=[]){

		$attributes = $this->attributes($attributes);

		return new Document('<!DOCTYPE div><div ' . $attributes . ' xmlns="http://www.w3.org/1999/xhtml"/>');
	}

	private function attributes(array $attributes=[]){

		return array_reduce(array_keys($attributes),function($carry,$key) use($attributes){
			return $carry . ' ' . $key . '="' . $attributes[$key] . '"';
		},"");
	}

}