<?php

namespace RS\NView;

use Exception;

mb_internal_encoding('UTF-8');



class Document {
	const GAP_NONE = 1;
	const GAP_FOLLOWING = 2;
	const GAP_PRECEDING = 3;
	const GAP_CHILD = 4;
	const GAP_DATA = 5;
	private $errs = '';
	private $fname = null;
	/**
	 * @var \DOMXPath
	 */
	private $xp = null;
	private $doc = null;

	/**
	 * NView constructor.
	 *
	 * @param mixed $value
	 */
	public function __construct($value = '') {

		set_error_handler(array($this, 'doMsg'), E_ALL | E_STRICT);
		try {
			switch (gettype($value)) {
				case 'NULL':
				case 'string': {
					$this->conString($value);
				}
				break;
				case 'object': {
					$this->conObject($value);
				}
				break;
				case 'resource': {
					$contents = '';
					while (!feof($value)) {
						$contents .= fread($value, 1024);
					}
					$this->conString($contents);
				}
				break;
				default: {
					$this->doMsg("NView:: __construct does not (yet) support " . gettype($value));
				}
			}
		} catch (Exception $e) {
			$this->doMsg($e->getCode(), "NView: " . $e->getMessage(), $e->getFile(), $e->getLine());
		}
		restore_error_handler();
	}

	/**
	 * 'doc'
	 */
	public function doc() {
		return $this->doc->documentElement;
	}


	/**
	 * 'show'
	 */
	public function show($whole_doc = false) {
		$retval = "";
		if (!is_null($this->doc) && !is_null($this->xp)) {
			$this->tidyView();
			ob_start();
			$this->doc->save('php://output');
			$retval = ob_get_clean();
			if (!$whole_doc) {
				$retval = static::asFragment($retval);
			}
		}
		return $retval;
	}

	/**
	 * 'text'
	 */
	public function text() {
		$retval = "";
		if (!is_null($this->doc) && !is_null($this->xp)) {
			$this->tidyView();
			$retval = $this->doc->textContent;
		}
		return $retval;
	}

	/**
	 * 'asFragment'
	 */
	public static function asFragment($docstr) {
		$xmlDeclaration = '/<\?xml[^?]+\?>/';
		$docTypeDeclaration = '/<!DOCTYPE \w+>/';
		$namespaceValue = '/\sxmlns="http:\/\/www.w3.org\/1999\/xhtml"/';
		$ksub = array($xmlDeclaration, $docTypeDeclaration, $namespaceValue);
		return trim(preg_replace($ksub, '', $docstr));
	}

	/**
	 * 'addNamespace'
	 */
	public function addNamespace($prefix, $namespace) {
		if (!is_null($this->xp)) {
			$this->xp->registerNamespace($prefix, $namespace);
		}
	}

	/**
	 * 'strToNode'
	 */
	public function strToNode($value = null) {
		$fnode = null;
		$fnode = $this->doc->createDocumentFragment();
		$this->errs = '';
		set_error_handler(array($this, 'doMsg'), E_ALL | E_STRICT);
		try {
			// One should always xml-encode ampersands in URLs in XML.

			$fragstr = $this->xmlenc($value);
			$fnode->appendXML($fragstr);
		} catch (Exception $ex) {
			$this->doMsg('Attempted fragment:', htmlspecialchars(print_r($fragstr, true)));
			restore_error_handler();
			throw $ex;
		}
		restore_error_handler();
		if (strpos($this->errs, 'parser error') !== false) {
			$fnode = $this->doc->createTextNode($value);
		}
		$node = $this->doc->importNode($fnode, true);
		return $node;
	}

	/**
	 * 'count'
	 */
	public function count($xpath, $ref = null) {
		$retval = 0;
		if (!is_null($this->doc) && !is_null($this->xp)) {
			if (is_null($ref)) {
				$entries = $this->xp->query($xpath);
			} else {
				$entries = $this->xp->query($xpath, $ref);
			}
			if ($entries) {
				$retval = $entries->length;
			} else {
				$this->doMsg('NView: count() ' . $xpath . ' failed.');
			}
		} else {
			$this->doMsg('NView: count() ' . $xpath . ' attempted on a non-document.');
		}
		return $retval;
	}

	/**
	 * 'consume'
	 */
	public function consume($xpath, $ref = null) {
		$retval = null;
		$retval = $this->get($xpath, $ref);
		if (!is_null($retval)) {
			$this->set($xpath, null, $ref);
		}
		return $retval;
	}

	/**
	 * @param string $xpath
	 * @param null   $ref
	 * @return \DOMNodeList
	 */
	public function getList(string $xpath, $ref = null): \DOMNodeList {
		if (!is_null($this->doc) && !is_null($this->xp)) {
			return $this->xp->query($xpath, $ref);
		}
		return new \DOMNodeList();
	}

	/**
	 * 'get'
	 */
	public function get($xpath, $ref = null) {
		$retval = null;
		if (!is_null($this->doc) && !is_null($this->xp)) {
			set_error_handler(array($this, 'doMsg'), E_ALL | E_STRICT);

			$entries = $this->xp->query($xpath, $ref);

			if ($entries) {
				switch ($entries->length) {
					case 1: {
						$entry = $entries->item(0);
						if ($entry->nodeType == XML_TEXT_NODE || $entry->nodeType == XML_CDATA_SECTION_NODE) {
							$retval = $entry->nodeValue;
						} elseif ($entry->nodeType == XML_ATTRIBUTE_NODE) {
							$retval = $entry->value;
						} elseif ($entry->nodeType == XML_ELEMENT_NODE) {
							//convert this to a domdocument so that we can maintain the namespace.
							$retval = new \DOMDocument("1.0", "utf-8");
							$retval->preserveWhiteSpace = true;
							$retval->formatOutput = false;
							$node = $retval->importNode($entry, true);
							$retval->appendChild($node);
							$olde = $this->doc->documentElement;
							if ($olde->hasAttributes()) {
								$myde = $retval->documentElement;
								foreach ($olde->attributes as $attr) {
									if (substr($attr->nodeName, 0, 6) == "xmlns:") {
										$myde->removeAttribute($attr->nodeName);
										$natr = $retval->importNode($attr, true); //can return false.
										if ($natr) {
											$myde->setAttributeNode($natr);
										}
									}
								}
							}
							$retval->normalizeDocument();
						} else {
							$retval = $entry;
						}
					}
					break;
					case 0:
					break;
					default: {
						$retval = $entries;
					}
					break;
				}
			} else {
				$this->doMsg('NView::get() ' . $xpath . ' failed.');
			}
			restore_error_handler();
		} else {
			$this->doMsg('NView::get() ' . $xpath . ' attempted on a non-document.');
		}
		return $retval;
	}

	/**
	 * 'set'
	 */
	public function set($xpath, $value = null, $ref = null, $unused = true) {
		//replace node at string xpath with node 'value'.
		set_error_handler(array($this, 'doMsg'), E_ALL | E_STRICT);
		if (!is_null($this->doc) && !is_null($this->xp)) {
			$gap = self::GAP_NONE;
			if (substr($xpath, -6) == "-gap()") {
				$xpath = mb_substr($xpath, 0, -6); //remove the -gap();
				if (substr($xpath, -6) == "/child") {
					$xpath = mb_substr($xpath, 0, -6); //remove the child;
					$gap = self::GAP_CHILD;
				} elseif (substr($xpath, -10) == "/preceding") {
					$xpath = mb_substr($xpath, 0, -10); //remove the child;
					$gap = self::GAP_PRECEDING;
				} elseif (substr($xpath, -10) == "/following") {
					$xpath = mb_substr($xpath, 0, -10); //remove the child;
					$gap = self::GAP_FOLLOWING;
				}
			} elseif (substr($xpath, -7) == "/data()") {
				$xpath = mb_substr($xpath, 0, -7); //remove the func.;
				$gap = self::GAP_DATA;
			}
			//now act according to value type.
			switch (gettype($value)) {
				case "NULL": {
					if ($gap == self::GAP_NONE) {
						if (is_null($ref)) {
							$entries = $this->xp->query($xpath);
						} else {
							$entries = $this->xp->query($xpath, $ref);
						}
						if ($entries) {
							foreach ($entries as $entry) {
								if ($entry instanceof \DOMAttr) {
									$entry->parentNode->removeAttributeNode($entry);
								} else {
									if(is_null($entry->parentNode)) {
										$this->initDoc();
										$entry = null;
									} else {
										$n = $entry->parentNode->removeChild($entry);
										unset($n); //not sure if this is needed..
									}
								}
							}
						} else {
							$this->doMsg('NView::set() ' . $xpath . ' failed.');
						}
					}
				}
				break;
				case "boolean":
				case "integer":
				case "string":
				case "double":
				case "object" : { //probably a node.
					if (gettype($value) != "object" || is_subclass_of($value, \DOMNode::class) || $value instanceof \DOMNodeList
						|| $value instanceof Document || $value instanceof View
					) {
						$aName = null;
						$atPoint = mb_strrpos($xpath, "/@");
						if ($atPoint !== false) {
							$aName = mb_substr($xpath, $atPoint + 2); //grab the attribute name.
							if ($this->validName($aName)) {
								$xpath = mb_substr($xpath, 0, $atPoint);
							}
						}
						if (!is_null($ref) && $xpath == ".") { //not worth evaluating an xpath for this.
							$entries = [$ref]; //it's hard to create a DOMNodeList
						} else {
							$entries = $this->xp->query($xpath, $ref);
						}

						if ($entries) {
							if (is_array($entries) || $entries->length !== 0) {

								if ($value instanceof View) {
									$value = $value->compile();
								}

								if ($value instanceof Document) {
									if ($gap !== self::GAP_DATA) {
										$value = $value->doc;
									} else {
										$value = $value->show(false);
									}
								}
								if ($value instanceof \DOMDocument) {
									$value = $value->documentElement;
								}
								if (isset($aName) && (gettype($value) != "object")) { //prepare attribute.
									$value = $this->xmlenc(strval($value));
								}
								//Now we have the value set.
								foreach ($entries as $entry) {
									if (($entry->nodeType == XML_ATTRIBUTE_NODE) && (gettype($value) != "object")) {
										switch ($gap) {
											case self::GAP_DATA:
											case self::GAP_NONE: {
												$entry->value = $this->xmlenc(strval($value));
											}
											break;
											case self::GAP_PRECEDING: {
												$entry->value = $this->xmlenc(strval($value)) . $entry->value;
											}
											break;
											case self::GAP_FOLLOWING:
											case self::GAP_CHILD: {
												$entry->value .= $this->xmlenc(strval($value));
											}
											break;
										}
									} elseif (($entry->nodeType == XML_CDATA_SECTION_NODE) && (gettype($value) != "object")) {
										switch ($gap) {
											case self::GAP_NONE: {
												$entry->data = strval($value);
											}
											break;
											case self::GAP_PRECEDING: {
												$entry->insertData(0, strval($value));
											}
											break;
											case self::GAP_FOLLOWING:
											case self::GAP_CHILD: {
												$entry->appendData(strval($value));
											}
											break;
										}
									} elseif (($entry->nodeType == XML_COMMENT_NODE) && ($gap == self::GAP_DATA)) {
										if (gettype($value) == "object") {
											$fvalue = "";
											if ($value instanceof \DOMNodeList) {
												foreach ($value as $nodi) {
													$doc = new \DOMDocument("1.0", "utf-8");
													$node = $doc->importNode($nodi, true);
													$doc->appendChild($node);
													$txt = $doc->saveXML();
													$fvalue .= static::asFragment($txt);
												}
											} else {
												if ($value instanceof \DOMNode) {
													$doc = new \DOMDocument("1.0", "utf-8");
													$node = $doc->importNode($value, true);
													$doc->appendChild($node);
													$txt = $doc->saveXML();
													$fvalue = static::asFragment($txt);
												} else {
													$this->doMsg("NView:  " . gettype($value) . " not yet implemented for comment insertion.");
												}
											}
											$fvalue = str_replace(array("<!--", "-->"), "", $value);
											$entry->replaceData(0, $entry->length, $fvalue);
										} else {
											$fvalue = str_replace(array("<!--", "-->"), "", $value);
											$entry->replaceData(0, $entry->length, $fvalue);
										}
									} else {
										if ($value instanceof \DOMNodeList) {
											foreach ($value as $nodi) {
												$nodc = $nodi->cloneNode(true);
												$node = $this->doc->importNode($nodc, true);
												switch ($gap) {
													case self::GAP_DATA:
													case self::GAP_NONE: {
														$entry->parentNode->replaceChild($node, $entry);
													}
													break;
													case self::GAP_PRECEDING: {
														$entry->parentNode->insertBefore($node, $entry);
													}
													break;
													case self::GAP_FOLLOWING: {
														if (is_null($entry->nextSibling)) {
															$entry->parentNode->appendChild($node);
														} else {
															$entry->parentNode->insertBefore($node, $entry->nextSibling);
														}
													}
													break;
													case self::GAP_CHILD: {
														$entry->appendChild($node);
													}
													break;
												}
											}
										} else {
											if (gettype($value) != "object") {
												$node = $this->strToNode(strval($value));
											} else {
												$nodc = $value->cloneNode(true);
												$node = $this->doc->importNode($nodc, true);
											}
											if (isset($aName)) {
												if ($entry->nodeType == XML_ELEMENT_NODE) {
													switch ($gap) {
														case self::GAP_DATA:
														case self::GAP_NONE: {
															if (!$this->isNullOrEmpty($value)) {
																$entry->setAttribute($aName, $value);
															} else {
																$entry->removeAttribute($aName);
															}
														}
														break;
														case self::GAP_PRECEDING: {
															$original = $entry->getAttribute($aName);
															$entry->setAttribute($aName, $value . $original);
														}
														break;
														case self::GAP_CHILD:
														case self::GAP_FOLLOWING: {
															$original = $entry->getAttribute($aName);
															$entry->setAttribute($aName, $original . $value);
														}
														break;
													}
												}
											} else {
												switch ($gap) {
													case self::GAP_DATA:
													case self::GAP_NONE: {
														$entry->parentNode->replaceChild($node, $entry);
													}
													break;
													case self::GAP_PRECEDING: {
														$entry->parentNode->insertBefore($node, $entry);
													}
													break;
													case self::GAP_FOLLOWING: {
														if (is_null($entry->nextSibling)) {
															$entry->parentNode->appendChild($node);
														} else {
															$entry->parentNode->insertBefore($node, $entry->nextSibling);
														}
													}
													break;
													case self::GAP_CHILD: {
														$entry->appendChild($node);
													}
													break;
												}
											}
										}
									}
								}
								if (gettype($value) != "object" || $value->nodeType == XML_TEXT_NODE && $gap != self::GAP_NONE) {
									$this->doc->normalizeDocument();
								}
							} else {
//								$this->debug('NView::set() ' . $xpath . ' failed to find the xpath in the document.');
							}
						} else {
							$this->doMsg('NView::set() ' . $xpath . ' failed.');
						}
					} else {
						$this->doMsg("NView: Unknown value type of object " . gettype($value) . " found");
					}
				}
				break;
				default: { //treat as text.
					$this->doMsg("NView: Unknown value type of object " . gettype($value) . " found");
				}
			}
		} else {
			$this->doMsg('set() ' . $xpath . ' attempted on a non-document.');
		}
		restore_error_handler();
		return $this;
	}

	/**
	 * Find out if anything was put into this.
	 */
	public function errors() {
		return $this->errs;
	}
	/**
	 * 'initDoc'
	 */
	private function initDoc() {
		$this->doc = new \DOMDocument("1.0", "utf-8");
		$this->doc->preserveWhiteSpace = true;
		$this->doc->formatOutput = false;
	}

	/**
	 * 'initXpath'
	 */
	private function initXpath() {
		$this->xp = new \DOMXPath($this->doc);
		$this->xp->registerNamespace("h", "http://www.w3.org/1999/xhtml");
	}

	/**
	 * 'conClass'
	 */
	private function conClass($value) {
		if (!is_null($value->doc)) {
			$this->initDoc();
			$this->doc = $value->doc->cloneNode(true);
			$this->initXpath();
		}
	}

	/**
	 * 'conNode'
	 */
	private function conNode($value) {
		if ($value->nodeType == XML_DOCUMENT_NODE) {
			$this->doc = $value->cloneNode(true);
			$this->initXpath();
		} elseif ($value->nodeType == XML_ELEMENT_NODE) {
			$this->initDoc();
			if ($this->isNullOrEmpty($value->prefix)) {
				$value->setAttribute("xmlns", $value->namespaceURI);
			} else {
				$value->setAttribute("xmlns:" . $value->prefix, $value->namespaceURI);
			}
			$node = $this->doc->importNode($value, true);
			$this->doc->appendChild($node);
			$olde = $value->ownerDocument->documentElement;
			if ($olde->hasAttributes()) {
				$myde = $this->doc->documentElement;
				foreach ($olde->attributes as $attr) {
					if (substr($attr->nodeName, 0, 6) == "xmlns:") {
						$myde->removeAttribute($attr->nodeName);
						$natr = $this->doc->importNode($attr, true);
						$myde->setAttributeNode($natr);
					}
				}
			}
			$this->initXpath();
		} else {
			$this->doMsg("NView:: __construct does not (yet) support construction from nodes of type " . $value->nodeType);
		}
	}

	/**
	 * 'conFile'
	 */
	private function conFile($value) {
		$this->fname = $value;
		if ($this->fname !== false) {
			$this->initDoc();
			$data = file_get_contents($this->fname);
			try {
				$this->doc->loadXML($data);
			} catch (\Exception $e) {
				$this->doMsg("NView: File '" . $this->fname . "' was found but didn't parse " . $data);
				$this->doMsg($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
			}
			$this->initXpath();
		} else {
			$this->doMsg("NView: File '" . $value . "' wasn't found. ");
		}
	}

	/**
	 * 'conString'
	 */
	private function conString($value) {

		if ($this->isNullOrEmpty($value)) {
			$this->conFile($value); //handle implicit in file..
		} elseif (strpos($value, '<') === false) {
			$this->conFile($value);
		} else {
			// Treat value as xml to be parsed.
			if (mb_check_encoding($value)) {
				$wss = array("\r\n", "\n", "\r", "\t"); //
				$value = str_replace($wss, "", $value); //str_replace should be mb safe.
				$this->initDoc();
				$this->doc->loadXML($value);
				$this->initXpath();
			} else {
				$this->doc = null;
			}
		}
	}

	/**
	 * 'conObject'
	 */
	private function conObject($value) {
		if ($value instanceof Document) {
			$this->conClass($value);
		} elseif (is_subclass_of($value, 'DOMNode')) {
			$this->conNode($value);
		} else {
			$this->doMsg("NView: object constructor only uses instances of NView or subclasses of DOMNode.");
		}
	}

	/**
	 * 'xmlenc'
	 */
	private function xmlenc($value) {
		return preg_replace('/&(?![\w#]{1,7};)/i', '&amp;', $value);
	}

	/**
	 * 'tidyView'
	 */
	private function tidyView() {
		$this->doc->normalizeDocument();
		$xq = "//*[not(node())][not(contains('[area|base|br|col|hr|img|input|link|meta|param|command|keygen|source]',local-name()))]";
		$entries = $this->xp->query($xq);
		if ($entries) {
			foreach ($entries as $entry) {
				$entry->appendChild($this->doc->createTextNode(''));
			}
		}
	}

// Function for basic field validation (present and neither empty nor only white space
	private function isNullOrEmpty($value) {
		return (!isset($value) || trim($value) === '');
	}

	/**
	 * Test that a name is a legal xml name suitable for attributes and elements.
	 * Colon has been removed.
	 */
	private function validName($name): bool {
		$pattern = '~
# XML 1.0 Name symbol PHP PCRE regex <http://www.w3.org/TR/REC-xml/#NT-Name>
(?(DEFINE)
    (?<NameStartChar> [A-Z_a-z\\xC0-\\xD6\\xD8-\\xF6\\xF8-\\x{2FF}\\x{370}-\\x{37D}\\x{37F}-\\x{1FFF}\\x{200C}-\\x{200D}\\x{2070}-\\x{218F}\\x{2C00}-\\x{2FEF}\\x{3001}-\\x{D7FF}\\x{F900}-\\x{FDCF}\\x{FDF0}-\\x{FFFD}\\x{10000}-\\x{EFFFF}])
    (?<NameChar>      (?&NameStartChar) | [.\\-0-9\\xB7\\x{0300}-\\x{036F}\\x{203F}-\\x{2040}])
    (?<Name>          (?&NameStartChar) (?&NameChar)*)
)
^(?&Name)$
~ux';
		return (1 === preg_match($pattern, $name));
	}

	/**
	 * 'doMsg'
	 * parser message handler..
	 * This needs to be public because we are calling render from outside of ourselves.
	 */
	public function doMsg($errno, $errstr = '', $errfile = '', $errline = 0) {
		$this->errs .= "$errstr; "; //error was made.
	}
}
