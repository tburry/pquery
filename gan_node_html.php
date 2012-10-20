<?php
/**
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

#!! <- Ignore when converting to single file
if (!defined('GANON_NO_INCLUDES')) {
	include_once('gan_parser_html.php');
	include_once('gan_selector_html.php');
}
#!

/**
 * Holds (x)html/xml tag information like tag name, attributes,
 * parent, children, self close, etc.
 */
class HTML_Node {

	/**
	 * Element Node, used for regular elements
	 */
	const NODE_ELEMENT = 0;
	/**
	 * Text Node
	 */
	const NODE_TEXT = 1;
	/**
	 * Comment Node
	 */
	const NODE_COMMENT = 2;
	/**
	 * Conditional Node (<![if]> <![endif])
	 */
	const NODE_CONDITIONAL = 3;
	/**
	 * CDATA Node (<![CDATA[]]>
	 */
	const NODE_CDATA = 4;
	/**
	 * Doctype Node
	 */
	const NODE_DOCTYPE = 5;
	/**
	 * XML Node, used for tags that start with ?, like <?xml and <?php
	 */
	const NODE_XML = 6;
	/**
	 * ASP Node
	 */
	const NODE_ASP = 7;
	
	#php4 Compatibility with PHP4, this gets changed to a regular var in release tool
	#static $NODE_TYPE = self::NODE_ELEMENT;
	#php4e
	#php5
	/**
	 * Node type of class
	 */
	const NODE_TYPE = self::NODE_ELEMENT;
	#php5e


	/**
	 * Name of the selector class
	 * @var string
	 * @see select()
	 */
	var $selectClass = 'HTML_Selector';
	/**
	 * Name of the parser class
	 * @var string
	 * @see setOuterText()
	 * @see setInnerText()
	 */
	var $parserClass = 'HTML_Parser_HTML5';

	/**
	 * Name of the class used for {@link addChild()}
	 * @var string
	 */
	var $childClass = __CLASS__;
	/**
	 * Name of the class used for {@link addText()}
	 * @var string
	 */
	var $childClass_Text = 'HTML_Node_TEXT';
	/**
	 * Name of the class used for {@link addComment()}
	 * @var string
	 */
	var $childClass_Comment = 'HTML_Node_COMMENT';
	/**
	 * Name of the class used for {@link addContional()}
	 * @var string
	 */
	var $childClass_Conditional = 'HTML_Node_CONDITIONAL';
	/**
	 * Name of the class used for {@link addCDATA()}
	 * @var string
	 */
	var $childClass_CDATA = 'HTML_Node_CDATA';
	/**
	 * Name of the class used for {@link addDoctype()}
	 * @var string
	 */
	var $childClass_Doctype = 'HTML_Node_DOCTYPE';
	/**
	 * Name of the class used for {@link addXML()}
	 * @var string
	 */
	var $childClass_XML = 'HTML_Node_XML';
	/**
	 * Name of the class used for {@link addASP()}
	 * @var string
	 */
	var $childClass_ASP = 'HTML_Node_ASP';

	/**
	 * Parent node, null if none
	 * @var HTML_Node
	 * @see changeParent()
	 */
	var $parent = null;

	/**
	 * Attributes of node
	 * @var array
	 * @internal array('attribute' => 'value')
	 * @internal Public for faster access!
	 * @see getAttribute()
	 * @see setAttribute()
	 * @access private
	 */
	var $attributes = array();

	/**
	 * Namespace info for attributes
	 * @var array
	 * @internal array('tag' => array(array('ns', 'tag', 'ns:tag', index)))
	 * @internal Public for easy outside modifications!
	 * @see findAttribute()
	 * @access private
	 */
	var $attributes_ns = null;

	/**
	 * Array of childnodes
	 * @var array
	 * @internal Public for faster access!
	 * @see childCount()
	 * @see getChild()
	 * @see addChild()
	 * @see deleteChild()
	 * @access private
	 */
	var $children = array();

	/**
	 * Full tag name (including namespace)
	 * @var string
	 * @see getTagName()
	 * @see getNamespace()
	 */
	var $tag = '';

	/**
	 * Namespace info for tag
	 * @var array
	 * @internal array('namespace', 'tag')
	 * @internal Public for easy outside modifications!
	 * @access private
	 */
	var $tag_ns = null;

	/**
	 * Is node a self closing node? No closing tag if true.
	 * @var bool
	 */
	var $self_close = false;

	/**
	 * If self close, then this will be used to close the tag
	 * @var string
	 * @see $self_close
	 */
	var $self_close_str = ' /';

	/**
	 * Use shorttags for attributes? If true, then attributes
	 * with values equal to the attribute name will not output
	 * the value, e.g. selected="selected" will be selected.
	 * @var bool
	 */
	var $attribute_shorttag = true;

	/**
	 * Function map used for the selector filter
	 * @var array
	 * @internal array('root' => 'filter_root') will cause the
	 * selector to call $this->filter_root at :root
	 * @access private
	 */
	var $filter_map = array(
		'root' => 'filter_root',
		'nth-child' => 'filter_nchild',
		'eq' => 'filter_nchild', //jquery (naming) compatibility
		'gt' => 'filter_gt',
		'lt' => 'filter_lt',
		'nth-last-child' => 'filter_nlastchild',
		'nth-of-type' => 'filter_ntype',
		'nth-last-of-type' => 'filter_nlastype',
		'odd' => 'filter_odd',
		'even' => 'filter_even',
		'every' => 'filter_every',
		'first-child' => 'filter_first',
		'last-child' => 'filter_last',
		'first-of-type' => 'filter_firsttype',
		'last-of-type' => 'filter_lasttype',
		'only-child' => 'filter_onlychild',
		'only-of-type' => 'filter_onlytype',
		'empty' => 'filter_empty',
		'not-empty' => 'filter_notempty',
		'has-text' => 'filter_hastext',
		'no-text' => 'filter_notext',
		'lang' => 'filter_lang',
		'contains' => 'filter_contains',
		'has' => 'filter_has',
		'not' => 'filter_not',
		'element' => 'filter_element',
		'text' => 'filter_text',
		'comment' => 'filter_comment'
	);

	/**
	 * Class constructor
	 * @param string|array $tag Name of the tag, or array with taginfo (array(
	 *	'tag_name' => 'tag',
	 *	'self_close' => false,
	 *	'attributes' => array('attribute' => 'value')))
	 * @param HTML_Node $parent Parent of node, null if none
	 */
	function __construct($tag, $parent) {
		$this->parent = $parent;

		if (is_string($tag)) {
			$this->tag = $tag;
		} else {
			$this->tag = $tag['tag_name'];
			$this->self_close = $tag['self_close'];
			$this->attributes = $tag['attributes'];
		}
	}
	
	#php4 PHP4 class constructor compatibility
	#function HTML_Node($tag, $parent) {return $this->__construct($tag, $parent);}
	#php4e

	/**
	 * Class destructor
	 * @access private
	 */
	function __destruct() {
		$this->delete();
	}

	/**
	 * Class toString, outputs {@link $tag}
	 * @return string
	 * @access private
	 */
	function __toString() {
		return (($this->tag === '~root~') ? $this->toString(true, true, 1) : $this->tag);
	}

	/**
	 * Class magic get method, outputs {@link getAttribute()}
	 * @return string
	 * @access private
	 */
	function __get($attribute) {
		return $this->getAttribute($attribute);
	}

	/**
	 * Class magic set method, performs {@link setAttribute()}
	 * @access private
	 */
	function __set($attribute, $value) {
		$this->setAttribute($attribute, $value);
	}

	/**
	 * Class magic isset method, returns {@link hasAttribute()}
	 * @return bool
	 * @access private
	 */
	function __isset($attribute) {
		return $this->hasAttribute($attribute);
	}

	/**
	 * Class magic unset method, performs {@link deleteAttribute()}
	 * @access private
	 */
	function __unset($attribute) {
		return $this->deleteAttribute($attribute);
	}

	/**
	 * Class magic invoke method, performs {@link select()}
	 * @return array
	 * @access private
	 */
	function __invoke($query = '*', $index = false, $recursive = true, $check_self = false) {
		return $this->select($query, $index, $recursive, $check_self);
	}

	/**
	 * Returns place in document
	 * @return string
	 */
	 function dumpLocation() {
		return (($this->parent) ? (($p = $this->parent->dumpLocation()) ? $p.' > ' : '').$this->tag.'('.$this->typeIndex().')' : '');
	 }

	/**
	 * Returns all the attributes and their values
	 * @return string
	 * @access private
	 */
	protected function toString_attributes() {
		$s = '';
		foreach($this->attributes as $a => $v) {
			$s .= ' '.$a.(((!$this->attribute_shorttag) || ($this->attributes[$a] !== $a)) ? '="'.htmlspecialchars($this->attributes[$a], ENT_QUOTES, '', false).'"' : '');
		}
		return $s;
	}

	/**
	 * Returns the content of the node (child tags and text)
	 * @param bool $attributes Print attributes of child tags
	 * @param bool|int $recursive How many sublevels of childtags to print. True for all.
	 * @param bool $content_only Only print text, false will print tags too.
	 * @return string
	 * @access private
	 */
	protected function toString_content($attributes = true, $recursive = true, $content_only = false) {
		$s = '';
		foreach($this->children as $c) {
			$s .= $c->toString($attributes, $recursive, $content_only);
		}
		return $s;
	}

	/**
	 * Returns the node as string
	 * @param bool $attributes Print attributes (of child tags)
	 * @param bool|int $recursive How many sublevels of childtags to print. True for all.
	 * @param bool $content_only Only print text, false will print tags too.
	 * @return string
	 */
	function toString($attributes = true, $recursive = true, $content_only = false) {
		if ($content_only) {
			if (is_int($content_only)) {
				--$content_only;
			}
			return $this->toString_content($attributes, $recursive, $content_only);
		}

		$s = '<'.$this->tag;
		if ($attributes) {
			$s .= $this->toString_attributes();
		}
		if ($this->self_close) {
			$s .= $this->self_close_str.'>';
		} else {
			$s .= '>';
			if($recursive) {
				$s .= $this->toString_content($attributes);
			}
			$s .= '</'.$this->tag.'>';
		}
		return $s;
	}

	/**
	 * Similar to JavaScript outerText, will return full (html formatted) node
	 * @return string
	 */
	function getOuterText() {
		return html_entity_decode($this->toString(), ENT_QUOTES);
	}

	/**
	 * Similar to JavaScript outerText, will replace node (and childnodes) with new text
	 * @param string $text
	 * @param HTML_Parser_Base $parser Null to auto create instance
	 * @return bool|array True on succeed, array with errors on failure
	 */
	function setOuterText($text, $parser = null) {
		if (trim($text)) {
			$index = $this->index();
			if ($parser === null) {
				$parser = new $this->parserClass();
			}
			$parser->setDoc($text);
			$parser->parse_all();
			$parser->root->moveChildren($this->parent, $index);
		}
		$this->delete();
		return (($parser && $parser->errors) ? $parser->errors : true);
	}

	/**
	 * Return html code of node
	 * @internal jquery (naming) compatibility
	 * @see toString()
	 * @return string
	 */
	function html() {
		return $this->toString();
	}

	/**
	 * Similar to JavaScript innerText, will return (html formatted) content
	 * @return string
	 */
	function getInnerText() {
		return html_entity_decode($this->toString(true, true, 1), ENT_QUOTES);
	}

	/**
	 * Similar to JavaScript innerText, will replace childnodes with new text
	 * @param string $text
	 * @param HTML_Parser_Base $parser Null to auto create instance
	 * @return bool|array True on succeed, array with errors on failure
	 */
	function setInnerText($text, $parser = null) {
		$this->clear();
		if (trim($text)) {
			if ($parser === null) {
				$parser = new $this->parserClass();
			}
			$parser->root =& $this;
			$parser->setDoc($text);
			$parser->parse_all();
		}
		return (($parser && $parser->errors) ? $parser->errors : true);
	}

	/**
	 * Similar to JavaScript plainText, will return text in node (and subnodes)
	 * @return string
	 */
	function getPlainText() {
		return preg_replace('`\s+`', ' ', html_entity_decode($this->toString(true, true, true), ENT_QUOTES));
	}
	
	/**
	 * Return plaintext taking document encoding into account
	 * @return string
	 */
	function getPlainTextUTF8() {
		$txt = $this->getPlainText();
		$enc = $this->getEncoding();
		if ($enc !== false) {
			$txt = mb_convert_encoding($txt, "UTF-8", $enc);
		}
		return $txt;
	}

	/**
	 * Similar to JavaScript plainText, will replace childnodes with new text (literal)
	 * @param string $text
	 */
	function setPlainText($text) {
		$this->clear();
		if (trim($text)) {
			$this->addText(htmlentities($text, ENT_QUOTES));
		}
	}

	/**
	 * Delete node from parent and clear node
	 */
	function delete() {
		if (($p = $this->parent) !== null) {
			$this->parent = null;
			$p->deleteChild($this);
		} else {
			$this->clear();
		}
	}

	/**
	 * Detach node from parent
	 * @param bool $move_children_up Only detach current node and replace it with childnodes
	 * @internal jquery (naming) compatibility
	 * @see delete()
	 */
	function detach($move_children_up = false) {
		if (($p = $this->parent) !== null) {
			$index = $this->index();
			$this->parent = null;
			
			if ($move_children_up) {
				$this->moveChildren($p, $index);
			}
			$p->deleteChild($this, true);
		}
	}

	/**
	 * Deletes all child nodes from node
	 */
	function clear() {
		foreach($this->children as $c) {
			$c->parent = null;
			$c->delete();
		}
		$this->children = array();
	}

	/**
	 * Get top parent
	 * @return HTML_Node Root, null if node has no parent
	 */
	function getRoot() {
		$r = $this->parent;
		$n = ($r === null) ? null : $r->parent;
		while ($n !== null) {
			$r = $n;
			$n = $r->parent;
		}
		
		return $r;
	}

	/**
	 * Change parent
	 * @param HTML_Node $to New parent, null if none
	 * @param int $index Add child to parent if not present at index, false to not add, negative to cound from end, null to append
	 */
	#php4
	#function changeParent($to, &$index) {
	#php4e
	#php5
	function changeParent($to, &$index = null) {
	#php5e
		if ($this->parent !== null) {
			$this->parent->deleteChild($this, true);
		}
		$this->parent = $to;
		if ($index !== false) {
			$new_index = $this->index();
			if (!(is_int($new_index) && ($new_index >= 0))) {
				$this->parent->addChild($this, $index);
			}
		}
	}

	/**
	 * Find out if node has (a certain) parent
	 * @param HTML_Node|string $tag Match against parent, string to match tag, object to fully match node, null to return if node has parent
	 * @param bool $recursive
	 * @return bool
	 */
	function hasParent($tag = null, $recursive = false) {
		if ($this->parent !== null) {
			if ($tag === null) {
				return true;
			} elseif (is_string($tag)) {
				return (($this->parent->tag === $tag) || ($recursive && $this->parent->hasParent($tag)));
			} elseif (is_object($tag)) {
				return (($this->parent === $tag) || ($recursive && $this->parent->hasParent($tag)));
			}
		}

		return false;
	}

	/**
	 * Find out if node is parent of a certain tag
	 * @param HTML_Node|string $tag Match against parent, string to match tag, object to fully match node
	 * @param bool $recursive
	 * @return bool
	 * @see hasParent()
	 */
	function isParent($tag, $recursive = false) {
		return ($this->hasParent($tag, $recursive) === ($tag !== null));
	}
	
	/**
	 * Find out if node is text
	 * @return bool
	 */
	function isText() {
		return false;
	}
	
	/**
	 * Find out if node is comment
	 * @return bool
	 */
	function isComment() {
		return false;
	}
	
	/**
	 * Find out if node is text or comment node
	 * @return bool
	 */
	function isTextOrComment() {
		return false;
	}

	/**
	 * Move node to other node
	 * @param HTML_Node $to New parent, null if none
	 * @param int $new_index Add child to parent at index if not present, null to not add, negative to cound from end
	 * @internal Performs {@link changeParent()}
	 */
	#php4
	#function move($to, &$new_index) {
	#php4e
	#php5
	function move($to, &$new_index = -1) {
	#php5e
		$this->changeParent($to, $new_index);
	}

	/**
	 * Move childnodes to other node
	 * @param HTML_Node $to New parent, null if none
	 * @param int $new_index Add child to new node at index if not present, null to not add, negative to cound from end
	 * @param int $start Index from child node where to start wrapping, 0 for first element
	 * @param int $end Index from child node where to end wrapping, -1 for last element
	 */
	#php4
	#function moveChildren($to, &$new_index, $start = 0, $end = -1) {
	#php4e
	#php5
	function moveChildren($to, &$new_index = -1, $start = 0, $end = -1) {
	#php5e
		if ($end < 0) {
			$end += count($this->children);
		}
		for ($i = $start; $i <= $end; $i++) {
			$this->children[$start]->changeParent($to, $new_index);
		}
	}

	/**
	 * Index of node in parent
	 * @param bool $count_all True to count all tags, false to ignore text and comments
	 * @return int -1 if not found
	 */
	function index($count_all = true) {
		if (!$this->parent) {
			return -1;
		} elseif ($count_all) {
			return $this->parent->findChild($this);
		} else{
			$index = -1;
			//foreach($this->parent->children as &$c) {
			//	if (!$c->isTextOrComment()) {
			//		++$index;
			//	}
			//	if ($c === $this) {
			//		return $index;
			//	}
			//}
			
			foreach(array_keys($this->parent->children) as $k) {
				if (!$this->parent->children[$k]->isTextOrComment()) {
					++$index;
				}
				if ($this->parent->children[$k] === $this) {
					return $index;
				}
			}
			return -1;
		}
	}

	/**
	 * Change index of node in parent
	 * @param int $index New index
	 */
	function setIndex($index) {
		if ($this->parent) {
			if ($index > $this->index()) {
				--$index;
			}
			$this->delete();
			$this->parent->addChild($this, $index);
		}
	}

	/**
	 * Index of all similar nodes in parent
	 * @return int -1 if not found
	 */
	function typeIndex() {
		if (!$this->parent) {
			return -1;
		} else {
			$index = -1;
			//foreach($this->parent->children as &$c) {
			//	if (strcasecmp($this->tag, $c->tag) === 0) {
			//		++$index;
			//	}
			//	if ($c === $this) {
			//		return $index;
			//	}
			//}
			
			foreach(array_keys($this->parent->children) as $k) {
				if (strcasecmp($this->tag, $this->parent->children[$k]->tag) === 0) {
					++$index;
				}
				if ($this->parent->children[$k] === $this) {
					return $index;
				}
			}
			return -1;
		}
	}

	/**
	 * Calculate indent of node (number of parent tags - 1)
	 * @return int
	 */
	function indent() {
		return (($this->parent) ? $this->parent->indent() + 1 : -1);
	}

	/**
	 * Get sibling node
	 * @param int $offset Offset from current node
	 * @return HTML_Node Null if not found
	 */
	function getSibling($offset = 1) {
		$index = $this->index() + $offset;
		if (($index >= 0) && ($index < $this->parent->childCount())) {
			return $this->parent->getChild($index);
		} else {
			return null;
		}
	}

	/**
	 * Get node next to current
	 * @param bool $skip_text_comments
	 * @return HTML_Node Null if not found
	 * @see getSibling()
	 * @see getPreviousSibling()
	 */
	function getNextSibling($skip_text_comments = true) {
		$offset = 1;
		while (($n = $this->getSibling($offset)) !== null) {
			if ($skip_text_comments && ($n->tag[0] === '~')) {
				++$offset;
			} else {
				break;
			}
		}

		return $n;
	}

	/**
	 * Get node previous to current
	 * @param bool $skip_text_comments
	 * @return HTML_Node Null if not found
	 * @see getSibling()
	 * @see getNextSibling()
	 */
	function getPreviousSibling($skip_text_comments = true) {
		$offset = -1;
		while (($n = $this->getSibling($offset)) !== null) {
			if ($skip_text_comments && ($n->tag[0] === '~')) {
				--$offset;
			} else {
				break;
			}
		}

		return $n;
	}

	/**
	 * Get namespace of node
	 * @return string
	 * @see setNamespace()
	 */
	function getNamespace() {
		if ($tag_ns === null) {
			$a = explode(':', $this->tag, 2);
			if (empty($a[1])) {
				$this->tag_ns = array('', $a[0]);
			} else {
				$this->tag_ns = array($a[0], $a[1]);
			}
		}

		return $this->tag_ns[0];
	}

	/**
	 * Set namespace of node
	 * @param string $ns
	 * @see getNamespace()
	 */
	function setNamespace($ns) {
		if ($this->getNamespace() !== $ns) {
			$this->tag_ns[0] = $ns;
			$this->tag = $ns.':'.$this->tag_ns[1];
		}
	}

	/**
	 * Get tagname of node (without namespace)
	 * @return string
	 * @see setTag()
	 */
	function getTag() {
		if ($tag_ns === null) {
			$this->getNamespace();
		}

		return $this->tag_ns[1];
	}

	/**
	 * Set tag (with or without namespace)
	 * @param string $tag
	 * @param bool $with_ns Does $tag include namespace?
	 * @see getTag()
	 */
	function setTag($tag, $with_ns = false) {
		$with_ns = $with_ns || (strpos($tag, ':') !== false);
		if ($with_ns) {
			$this->tag = $tag;
			$this->tag_ns = null;
		} elseif ($this->getTag() !== $tag) {
			$this->tag_ns[1] = $tag;
			$this->tag = (($this->tag_ns[0]) ? $this->tag_ns[0].':' : '').$tag;
		}
	}
	
	/**
	 * Try to determine the encoding of the current tag
	 * @return string|bool False if encoding could not be found
	 */
	function getEncoding() {
		$root = $this->getRoot();
		if ($root !== null) {
			if ($enc = $root->select('meta[charset]', 0, true, true)) {
				return $enc->getAttribute("charset");
			} elseif ($enc = $root->select('"?xml"[encoding]', 0, true, true)) {
				return $enc->getAttribute("encoding");
			} elseif ($enc = $root->select('meta[content*="charset="]', 0, true, true)) {
				$enc = $enc->getAttribute("content");
				return substr($enc, strpos($enc, "charset=")+8);
			}
		}
		
		return false;
	}	

	/**
	 * Number of children in node
	 * @param bool $ignore_text_comments Ignore text/comments with calculation
	 * @return int
	 */
	function childCount($ignore_text_comments = false) {
		if (!$ignore_text_comments) {
			return count($this->children);
		} else{
			$count = 0;
			//foreach($this->children as &$c) {
			//	if (!$c->isTextOrComment()) {
			//		++$count;
			//	}
			//}
			
			foreach(array_keys($this->children) as $k) {
				if (!$this->children[$k]->isTextOrComment()) {
					++$count;
				}
			}
			return $count;
		}
	}

	/**
	 * Find node in children
	 * @param HTML_Node $child
	 * @return int False if not found
	 */
	function findChild($child) {
		return array_search($child, $this->children, true);
	}

	/**
	 * Checks if node has another node as child
	 * @param HTML_Node $child
	 * @return bool
	 */
	function hasChild($child) {
		return ((bool) findChild($child));
	}

	/**
	 * Get childnode
	 * @param int|HTML_Node $child Index, negative to count from end
	 * @param bool $ignore_text_comments Ignore text/comments with index calculation
	 * @return HTML_Node
	 */
	function &getChild($child, $ignore_text_comments = false) {
		if (!is_int($child)) {
			$child = $this->findChild($child);
		} elseif ($child < 0) {
			$child += $this->childCount($ignore_text_comments);
		}

		if ($ignore_text_comments) {
			$count = 0;
			$last = null;
			//foreach($this->children as &$c) {
			//	if (!$c->isTextOrComment()) {
			//		if ($count++ === $child) {
			//			return $c;
			//		}
			//		$last = $c;
			//	}
			//}
			
			foreach(array_keys($this->children) as $k) {
				if (!$this->children[$k]->isTextOrComment()) {
					if ($count++ === $child) {
						return $this->children[$k];
					}
					$last = $this->children[$k];
				}
			}
			return (($child > $count) ? $last : null);
		} else {
			return $this->children[$child];
		}
	}

	/**
	 * Add childnode
	 * @param string|HTML_Node $tag Tagname or object
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 */
	#php4
	#function &addChild($tag, &$offset) {
	#php4e
	#php5
	function &addChild($tag, &$offset = null) {
	#php5e
		if (!is_object($tag)) {
			$tag = new $this->childClass($tag, $this);
		} elseif ($tag->parent !== $this) {
			$index = false; //Needs to be passed by ref
			$tag->changeParent($this, $index);
		}

		if (is_int($offset) && ($offset < count($this->children)) && ($offset !== -1)) {
			if ($offset < 0) {
				$offset += count($this->children);
			}
			array_splice($this->children, $offset++, 0, array(&$tag));
		} else {
			$this->children[] =& $tag;
		}

		return $tag;
	}

	/**
	 * First child node
	 * @param bool $ignore_text_comments Ignore text/comments with index calculation
	 * @return HTML_Node
	 */
	function &firstChild($ignore_text_comments = false) {
		return $this->getChild(0, $ignore_text_comments);
	}

	/**
	 * Last child node
	 * @param bool $ignore_text_comments Ignore text/comments with index calculation
	 * @return HTML_Node
	 */
	function &lastChild($ignore_text_comments = false) {
		return $this->getChild(-1, $ignore_text_comments);
	}

	/**
	 * Insert childnode
	 * @param string|HTML_Node $tag Tagname or object
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	function &insertChild($tag, $index) {
		return $this->addChild($tag, $index);
	}

	/**
	 * Add textnode
	 * @param string $text
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	#php4
	#function &addText($text, &$offset) {
	#php4e
	#php5
	function &addText($text, &$offset = null) {
	#php5e
		return $this->addChild(new $this->childClass_Text($this, $text), $offset);
	}

	/**
	 * Add comment node
	 * @param string $text
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	#php4
	#function &addComment($text, &$offset) {
	#php4e
	#php5
	function &addComment($text, &$offset = null) {
	#php5e
		return $this->addChild(new $this->childClass_Comment($this, $text), $offset);
	}

	/**
	 * Add conditional node
	 * @param string $condition
	 * @param bool True for <!--[if, false for <![if
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	#php4
	#function &addConditional($condition, $hidden = true, &$offset) {
	#php4e
	#php5
	function &addConditional($condition, $hidden = true, &$offset = null) {
	#php5e
		return $this->addChild(new $this->childClass_Conditional($this, $condition, $hidden), $offset);
	}

	/**
	 * Add CDATA node
	 * @param string $text
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	#php4
	#function &addCDATA($text, &$offset) {
	#php4e
	#php5
	function &addCDATA($text, &$offset = null) {
	#php5e
		return $this->addChild(new $this->childClass_CDATA($this, $text), $offset);
	}

	/**
	 * Add doctype node
	 * @param string $dtd
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	#php4
	#function &addDoctype($dtd, &$offset) {
	#php4e
	#php5
	function &addDoctype($dtd, &$offset = null) {
	#php5e
		return $this->addChild(new $this->childClass_Doctype($this, $dtd), $offset);
	}

	/**
	 * Add xml node
	 * @param string $tag Tagname after "?", e.g. "php" or "xml"
	 * @param string $text
	 * @param array $attributes Array of attributes (array('attribute' => 'value'))
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	#php4
	#function &addXML($tag = 'xml', $text = '', $attributes = array(), &$offset) {
	#php4e
	#php5
	function &addXML($tag = 'xml', $text = '', $attributes = array(), &$offset = null) {
	#php5e
		return $this->addChild(new $this->childClass_XML($this, $tag, $text, $attributes), $offset);
	}

	/**
	 * Add ASP node
	 * @param string $tag Tagname after "%"
	 * @param string $text
	 * @param array $attributes Array of attributes (array('attribute' => 'value'))
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	#php4
	#function &addASP($tag = '', $text = '', $attributes = array(), &$offset) {
	#php4e
	#php5
	function &addASP($tag = '', $text = '', $attributes = array(), &$offset = null) {
	#php5e
		return $this->addChild(new $this->childClass_ASP($this, $tag, $text, $attributes), $offset);
	}

	/**
	 * Delete a childnode
	 * @param int|HTML_Node $child Child(index) to delete, negative to count from end
	 * @param bool $soft_delete False to call {@link delete()} from child
	 */
	function deleteChild($child, $soft_delete = false) {
		if (is_object($child)) {
			$child = $this->findChild($child);
		} elseif ($child < 0) {
			$child += count($this->children);
		}

		if (!$soft_delete) {
			$this->children[$child]->delete();
		}
		unset($this->children[$child]);

		//Rebuild indices
		$tmp = array();
		
		//foreach($this->children as &$c) {
		//	$tmp[] =& $c;
		//}
		foreach(array_keys($this->children) as $k) {
			$tmp[] =& $this->children[$k];
		}
		$this->children = $tmp;
	}

	/**
	 * Wrap node
	 * @param string|HTML_Node $node Wrapping node, string to create new element node
	 * @param int $wrap_index Index to insert current node in wrapping node, -1 to append
	 * @param int $node_index Index to insert wrapping node, null to keep at same position
	 * @return HTML_Node Wrapping node
	 */
	function wrap($node, $wrap_index = -1, $node_index = null) {
		if ($node_index === null) {
			$node_index = $this->index();
		}
		
		if (!is_object($node)) {
			$node = $this->parent->addChild($node, $node_index);
		} elseif ($node->parent !== $this->parent) {
			$node->changeParent($this->parent, $node_index);
		}

		$this->changeParent($node, $wrap_index);
		return $node;
	}

	/**
	 * Wrap childnodes
	 * @param string|HTML_Node $node Wrapping node, string to create new element node
	 * @param int $start Index from child node where to start wrapping, 0 for first element
	 * @param int $end Index from child node where to end wrapping, -1 for last element
	 * @param int $wrap_index Index to insert in wrapping node, -1 to append
	 * @param int $node_index Index to insert current node, null to keep at same position
	 * @return HTML_Node Wrapping node
	 */
	function wrapInner($node, $start = 0, $end = -1, $wrap_index = -1, $node_index = null) {
		if ($end < 0) {
			$end += count($this->children);
		}
		if ($node_index === null) {
			$node_index = $end + 1;
		}

		if (!is_object($node)) {
			$node = $this->addChild($node, $node_index);
		} elseif ($node->parent !== $this) {
			$node->changeParent($this->parent, $node_index);
		}

		$this->moveChildren($node, $wrap_index, $start, $end);
		return $node;
	}

	/**
	 * Number of attributes
	 * @return int
	 */
	function attributeCount() {
		return count($this->attributes);
	}

	/**
	 * Find attribute using namespace, name or both
	 * @param string|int $attr Negative int to count from end
	 * @param string $compare "namespace", "name" or "total"
	 * @param bool $case_sensitive Compare with case sensitivity
	 * @return array array('ns', 'attr', 'ns:attr', index)
	 * @access private
	 */
	protected function findAttribute($attr, $compare = 'total', $case_sensitive = false) {
		if (is_int($attr)) {
			if ($attr < 0) {
				$attr += count($this->attributes);
			}
			$keys = array_keys($this->attributes);
			return $this->findAttribute($keys[$attr], 'total', true);
		} else if ($compare === 'total') {
			$b = explode(':', $attr, 2);
			if ($case_sensitive) {
				$t =& $this->attributes;
			} else {
				$t = array_change_key_case($this->attributes);
				$attr = strtolower($attr);
			}

			if (isset($t[$attr])) {
				$index = 0;
				foreach($this->attributes as $a => $v) {
					if (($v === $t[$attr]) && (strcasecmp($a, $attr) === 0)) {
						$attr = $a;
						$b = explode(':', $attr, 2);
						break;
					}
					++$index;
				}

				if (empty($b[1])) {
					return array(array('', $b[0], $attr, $index));
				} else {
					return array(array($b[0], $b[1], $attr, $index));
				}
			} else {
				return false;
			}
		} else {
			if ($this->attributes_ns === null) {
				$index = 0;
				foreach($this->attributes as $a => $v) {
					$b = explode(':', $a, 2);
					if (empty($b[1])) {
						$this->attributes_ns[$b[0]][] = array('', $b[0], $a, $index);
					} else {
						$this->attributes_ns[$b[1]][] = array($b[0], $b[1], $a, $index);
					}
					++$index;
				}
			}

			if ($case_sensitive) {
				$t =& $this->attributes_ns;
			} else {
				$t = array_change_key_case($this->attributes_ns);
				$attr = strtolower($attr);
			}

			if ($compare === 'namespace') {
				$res = array();
				foreach($t as $ar) {
					foreach($ar as $a) {
						if ($a[0] === $attr) {
							$res[] = $a;
						}
					}
				}
				return $res;
			} elseif ($compare === 'name') {
				return ((isset($t[$attr])) ? $t[$attr] : false);
			} else {
				trigger_error('Unknown comparison mode');
			}
		}
	}

	/**
	 * Checks if node has attribute
	 * @param string|int$attr Negative int to count from end
	 * @param string $compare Find node using "namespace", "name" or "total"
	 * @param bool $case_sensitive Compare with case sensitivity
	 * @return bool
	 */
	function hasAttribute($attr, $compare = 'total', $case_sensitive = false) {
		return ((bool) $this->findAttribute($attr, $compare, $case_sensitive));
	}

	/**
	 * Gets namespace of attribute(s)
	 * @param string|int $attr Negative int to count from end
	 * @param string $compare Find node using "namespace", "name" or "total"
	 * @param bool $case_sensitive Compare with case sensitivity
	 * @return string|array False if not found
	 */
	function getAttributeNS($attr, $compare = 'name', $case_sensitive = false) {
		$f = $this->findAttribute($attr, $compare, $case_sensitive);
		if (is_array($f) && $f) {
			if (count($f) === 1) {
				return $this->attributes[$f[0][0]];
			} else {
				$res = array();
				foreach($f as $a) {
					$res[] = $a[0];
				}
				return $res;
			}
		} else {
			return false;
		}
	}

	/**
	 * Sets namespace of attribute(s)
	 * @param string|int $attr Negative int to count from end
	 * @param string $namespace
	 * @param string $compare Find node using "namespace", "name" or "total"
	 * @param bool $case_sensitive Compare with case sensitivity
	 * @return bool
	 */
	function setAttributeNS($attr, $namespace, $compare = 'name', $case_sensitive = false) {
		$f = $this->findAttribute($attr, $compare, $case_sensitive);
		if (is_array($f) && $f) {
			if ($namespace) {
				$namespace .= ':';
			}
			foreach($f as $a) {
				$val = $this->attributes[$a[2]];
				unset($this->attributes[$a[2]]);
				$this->attributes[$namespace.$a[1]] = $val;
			}
			$this->attributes_ns = null;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Gets value(s) of attribute(s)
	 * @param string|int $attr Negative int to count from end
	 * @param string $compare Find node using "namespace", "name" or "total"
	 * @param bool $case_sensitive Compare with case sensitivity
	 * @return string|array
	 */
	function getAttribute($attr, $compare = 'total', $case_sensitive = false) {
		$f = $this->findAttribute($attr, $compare, $case_sensitive);
		if (is_array($f) && $f){
			if (count($f) === 1) {
				return $this->attributes[$f[0][2]];
			} else {
				$res = array();
				foreach($f as $a) {
					$res[] = $this->attributes[$a[2]];
				}
				return $res;
			}
		} else {
			return null;
		}
	}

	/**
	 * Sets value(s) of attribute(s)
	 * @param string|int $attr Negative int to count from end
	 * @param string $compare Find node using "namespace", "name" or "total"
	 * @param bool $case_sensitive Compare with case sensitivity
	 */
	function setAttribute($attr, $val, $compare = 'total', $case_sensitive = false) {
		if ($val === null) {
			return $this->deleteAttribute($attr, $compare, $case_sensitive);
		}

		$f = $this->findAttribute($attr, $compare, $case_sensitive);
		if (is_array($f) && $f) {
			foreach($f as $a) {
				$this->attributes[$a[2]] = (string) $val;
			}
		} else {
			$this->attributes[$attr] = (string) $val;
		}
	}

	/**
	 * Add new attribute
	 * @param string $attr
	 * @param string $val
	 */
	function addAttribute($attr, $val) {
		$this->setAttribute($attr, $val, 'total', true);
	}

	/**
	 * Delete attribute(s)
	 * @param string|int $attr Negative int to count from end
	 * @param string $compare Find node using "namespace", "name" or "total"
	 * @param bool $case_sensitive Compare with case sensitivity
	 */
	function deleteAttribute($attr, $compare = 'total', $case_sensitive = false) {
		$f = $this->findAttribute($attr, $compare, $case_sensitive);
		if (is_array($f) && $f) {
			foreach($f as $a) {
				unset($this->attributes[$a[2]]);
				if ($this->attributes_ns !== null) {
					unset($this->attributes_ns[$a[1]]);
				}
			}
		}
	}

	/**
	 * Determine if node has a certain class
	 * @param string $className
	 * @return bool
	 */
	function hasClass($className) {
		return ($className && preg_match('`\b'.preg_quote($className).'\b`si', $class = $this->class));
	}

	/**
	 * Add new class(es)
	 * @param string|array $className
	 */
	function addClass($className) {
		if (!is_array($className)) {
			$className = array($className);
		}
		$class = $this->class;
		foreach ($className as $c) {
			if (!(preg_match('`\b'.preg_quote($c).'\b`si', $class) > 0)) {
				$class .= ' '.$c;
			}
		}
		 $this->class = $class;
	}

	/**
	 * Remove clas(ses)
	 * @param string|array $className
	 */
	function removeClass($className) {
		if (!is_array($className)) {
			$className = array($className);
		}
		$class = $this->class;
		foreach ($className as $c) {
			$class = reg_replace('`\b'.preg_quote($c).'\b`si', '', $class);
		}
		if ($class) {
			$this->class = $class;
		} else {
			unset($this->class);
		}
	}

	/**
	 * Finds children using a callback function
	 * @param function $callback Function($node) that returns a bool
	 * @param bool|int $recursive Check recursively
	 * @param bool $check_self Include this node in search?
	 * @return array
	 */
	function getChildrenByCallback($callback, $recursive = true, $check_self = false) {
		$count = $this->childCount();
		if ($check_self && $callback($this)) {
			$res = array($this);
		} else {
			$res = array();
		}

		if ($count > 0) {
			if (is_int($recursive)) {
				$recursive = (($recursive > 1) ? $recursive - 1 : false);
			}

			for ($i = 0; $i < $count; $i++) {
				if ($callback($this->children[$i])) {
					$res[] = $this->children[$i];
				}
				if ($recursive) {
					$res = array_merge($res, $this->children[$i]->getChildrenByCallback($callback, $recursive));
				}
			}
		}

		return $res;
	}
	
	/**
	 * Finds children using the {$link match()} function
	 * @param $conditions See {$link match()}
	 * @param $custom_filters See {$link match()}
	 * @param bool|int $recursive Check recursively
	 * @param bool $check_self Include this node in search?
	 * @return array
	 */
	function getChildrenByMatch($conditions, $recursive = true, $check_self = false, $custom_filters = array()) {
		$count = $this->childCount();
		if ($check_self && $this->match($conditions, true, $custom_filters)) {
			$res = array($this);
		} else {
			$res = array();
		}

		if ($count > 0) {
			if (is_int($recursive)) {
				$recursive = (($recursive > 1) ? $recursive - 1 : false);
			}

			for ($i = 0; $i < $count; $i++) {
				if ($this->children[$i]->match($conditions, true, $custom_filters)) {
					$res[] = $this->children[$i];
				}
				if ($recursive) {
					$res = array_merge($res, $this->children[$i]->getChildrenByMatch($conditions, $recursive, false, $custom_filters));
				}
			}
		}

		return $res;
	}

	/**
	 * Checks if tag matches certain conditions
	 * @param array $tags array('tag1', 'tag2') or array(array(
	 *	'tag' => 'tag1',
	 *	'operator' => 'or'/'and',
	 *	'compare' => 'total'/'namespace'/'name',
	 * 	'case_sensitive' => true))
	 * @return bool
	 * @internal Used by selector class
	 * @see match()
	 * @access private
	 */
	protected function match_tags($tags) {
		$res = false;

		foreach($tags as $tag => $match) {
			if (!is_array($match)) {
				$match = array(
					'match' => $match,
					'operator' => 'or',
					'compare' => 'total',
					'case_sensitive' => false
				);
			} else {
				if (is_int($tag)) {
					$tag = $match['tag'];
				}
				if (!isset($match['match'])) {
					$match['match'] = true;
				}
				if (!isset($match['operator'])) {
					$match['operator'] = 'or';
				}
				if (!isset($match['compare'])) {
					$match['compare'] = 'total';
				}
				if (!isset($match['case_sensitive'])) {
					$match['case_sensitive'] = false;
				}
			}

			if (($match['operator'] === 'and') && (!$res)) {
				return false;
			} elseif (!($res && ($match['operator'] === 'or'))) {
				if ($match['compare'] === 'total') {
					$a = $this->tag;
				} elseif ($match['compare'] === 'namespace') {
					$a = $this->getNamespace();
				} elseif ($match['compare'] === 'name') {
					$a = $this->getTag();
				}

				if ($match['case_sensitive']) {
					$res = (($a === $tag) === $match['match']);
				} else {
					$res = ((strcasecmp($a, $tag) === 0) === $match['match']);
				}
			}
		}

		return $res;
	}

	/**
	 * Checks if attributes match certain conditions
	 * @param array $attributes array('attr' => 'val') or array(array(
	 *	'operator_value' => 'equals'/'='/'contains_regex'/etc
	 *	'attribute' => 'attr',
	 *	'value' => 'val',
	 *	'match' => true,
	 *	'operator_result' => 'or'/'and',
	 *	'compare' => 'total'/'namespace'/'name',
	 *	'case_sensitive' => true))
	 * @return bool
	 * @internal Used by selector class
	 * @see match()
	 * @access private
	 */
	protected function match_attributes($attributes) {
		$res = false;

		foreach($attributes as $attribute => $match) {
			if (!is_array($match)) {
				$match = array(
					'operator_value' => 'equals',
					'value' => $match,
					'match' => true,
					'operator_result' => 'or',
					'compare' => 'total',
					'case_sensitive' => false
				);
			} else {
				if (is_int($attribute)) {
					$attribute = $match['attribute'];
				}
				if (!isset($match['match'])) {
					$match['match'] = true;
				}
				if (!isset($match['operator_result'])) {
					$match['operator_result'] = 'or';
				}
				if (!isset($match['compare'])) {
					$match['compare'] = 'total';
				}
				if (!isset($match['case_sensitive'])) {
					$match['case_sensitive'] = false;
				}
			}
			
			if (is_string($match['value']) && (!$match['case_sensitive'])) {
				$match['value'] = strtolower($match['value']);
			}

			if (($match['operator_result'] === 'and') && (!$res)) {
				return false;
			} elseif (!($res && ($match['operator_result'] === 'or'))) {
				$possibles = $this->findAttribute($attribute, $match['compare'], $match['case_sensitive']);

				$has = (is_array($possibles) && $possibles);
				$res = (($match['value'] === $has) || (($match['match'] === false) && ($has === $match['match'])));

				if ((!$res) && $has && is_string($match['value'])) {
					foreach($possibles as $a) {
						$val = $this->attributes[$a[2]];
						if (is_string($val) && (!$match['case_sensitive'])) {
							$val = strtolower($val);
						}

						switch($match['operator_value']) {
							case '%=':
							case 'contains_regex':
								$res = ((preg_match('`'.$match['value'].'`s', $val) > 0) === $match['match']);
								if ($res) break 1; else break 2;

							case '|=':
							case 'contains_prefix':
								$res = ((preg_match('`\b'.preg_quote($match['value']).'[\-\s]?`s', $val) > 0) === $match['match']);
								if ($res) break 1; else break 2;

							case '~=':
							case 'contains_word':
								$res = ((preg_match('`\b'.preg_quote($match['value']).'\b`s', $val) > 0) === $match['match']);
								if ($res) break 1; else break 2;

							case '*=':
							case 'contains':
								$res = ((strpos($val, $match['value']) !== false) === $match['match']);
								if ($res) break 1; else break 2;

							case '$=':
							case 'ends_with':
								$res = ((substr($val, -strlen($match['value'])) === $match['value']) === $match['match']);
								if ($res) break 1; else break 2;

							case '^=':
							case 'starts_with':
								$res = ((substr($val, 0, strlen($match['value'])) === $match['value']) === $match['match']);
								if ($res) break 1; else break 2;

							case '!=':
							case 'not_equal':
								$res = (($val !== $match['value']) === $match['match']);
								if ($res) break 1; else break 2;

							case '=':
							case 'equals':
								$res = (($val === $match['value']) === $match['match']);
								if ($res) break 1; else break 2;

							case '>=':
							case 'bigger_than':
								$res = (($val >= $match['value']) === $match['match']);
								if ($res) break 1; else break 2;

							case '<=':
							case 'smaller_than':
								$res = (($val >= $match['value']) === $match['match']);
								if ($res) break 1; else break 2;

							default:
								trigger_error('Unknown operator "'.$match['operator_value'].'" to match attributes!');
								return false;
						}
					}
				}
			}
		}

		return $res;
	}

	/**
	 * Checks if node matches certain filters
	 * @param array $tags array(array(
	 *	'filter' => 'last-child',
	 *	'params' => '123'))
	 * @param array $custom_filters Custom map next to {@link $filter_map}
	 * @return bool
	 * @internal Used by selector class
	 * @see match()
	 * @access private
	 */
	protected function match_filters($conditions, $custom_filters = array()) {
		foreach($conditions as $c) {
			$c['filter'] = strtolower($c['filter']);
			if (isset($this->filter_map[$c['filter']])) {
				if (!$this->{$this->filter_map[$c['filter']]}($c['params'])) {
					return false;
				}
			} elseif (isset($custom_filters[$c['filter']])) {
				if (!call_user_func($custom_filters[$c['filter']], $this, $c['params'])) {
					return false;
				}
			} else {
				trigger_error('Unknown filter "'.$c['filter'].'"!');
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks if node matches certain conditions
	 * @param array $tags array('tags' => array(tag_conditions), 'attributes' => array(attr_conditions), 'filters' => array(filter_conditions))
	 * @param array $match Should conditions evaluate to true?
	 * @param array $custom_filters Custom map next to {@link $filter_map}
	 * @return bool
	 * @internal Used by selector class
	 * @see match_tags();
	 * @see match_attributes();
	 * @see match_filters();
	 * @access private
	 */
	function match($conditions, $match = true, $custom_filters = array()) {
		$t = isset($conditions['tags']);
		$a = isset($conditions['attributes']);
		$f = isset($conditions['filters']);

		if (!($t || $a || $f)) {
			if (is_array($conditions) && $conditions) {
				foreach($conditions as $c) {
					if ($this->match($c, $match)) {
						return true;
					}
				}
			}

			return false;
		} else {
			if (($t && (!$this->match_tags($conditions['tags']))) === $match) {
				return false;
			}

			if (($a && (!$this->match_attributes($conditions['attributes']))) === $match) {
				return false;
			}

			if (($f && (!$this->match_filters($conditions['filters'], $custom_filters))) === $match) {
				return false;
			}

			return true;
		}
	}

	/**
	 * Finds children that match a certain attribute
	 * @param string $attribute
	 * @param string $value
	 * @param string $mode Compare mode, "equals", "|=", "contains_regex", etc.
	 * @param string $compare "total"/"namespace"/"name"
	 * @param bool|int $recursive
	 * @return array
	 */
	function getChildrenByAttribute($attribute, $value, $mode = 'equals', $compare = 'total', $recursive = true) {
		if ($this->childCount() < 1) {
			return array();
		}

		$mode = explode(' ', strtolower($mode));
		$match = ((isset($mode[1]) && ($mode[1] === 'not')) ? 'false' : 'true');

		return $this->getChildrenByMatch(
			array(
				'attributes' => array(
					$attribute => array(
						'operator_value' => $mode[0],
						'value' => $value,
						'match' => $match,
						'compare' => $compare
					)
				)
			),
			$recursive
		);
	}

	/**
	 * Finds children that match a certain tag
	 * @param string $tag
	 * @param string $compare "total"/"namespace"/"name"
	 * @param bool|int $recursive
	 * @return array
	 */
	function getChildrenByTag($tag, $compare = 'total', $recursive = true) {
		if ($this->childCount() < 1) {
			return array();
		}

		$tag = explode(' ', strtolower($tag));
		$match = ((isset($tag[1]) && ($tag[1] === 'not')) ? 'false' : 'true');

		return $this->getChildrenByMatch(
			array(
				'tags' => array(
					$tag[0] => array(
						'match' => $match,
						'compare' => $compare
					)
				)
			),
			$recursive
		);
	}

	/**
	 * Finds all children using ID attribute
	 * @param string $id
	 * @param bool|int $recursive
	 * @return array
	 */
	function getChildrenByID($id, $recursive = true) {
		return $this->getChildrenByAttribute('id', $id, 'equals', 'total', $recursive);
	}

	/**
	 * Finds all children using class attribute
	 * @param string $class
	 * @param bool|int $recursive
	 * @return array
	 */
	function getChildrenByClass($class, $recursive = true) {
		return $this->getChildrenByAttribute('class', $id, 'equals', 'total', $recursive);
	}

	/**
	 * Finds all children using name attribute
	 * @param string $name
	 * @param bool|int $recursive
	 * @return array
	 */
	function getChildrenByName($name, $recursive = true) {
		return $this->getChildrenByAttribute('name', $name, 'equals', 'total', $recursive);
	}

	/**
	 * Performs css query on node
	 * @param string $query
	 * @param int|bool $index True to return node instead of array if only 1 match,
	 * false to return array, int to return match at index, negative int to count from end
	 * @param bool|int $recursive
	 * @param bool $check_self Include this node in search or only search childnodes
	 * @return array|HTML_Node
	 */
	function select($query = '*', $index = false, $recursive = true, $check_self = false) {
		$s = new $this->selectClass($this, $query, $check_self, $recursive);
		$res = $s->result;
		unset($s);
		if (is_array($res) && ($index === true) && (count($res) === 1)) {
			return $res[0];
		} elseif (is_int($index) && is_array($res)) {
			if ($index < 0) {
				$index += count($res);
			}
			return ($index < count($res)) ? $res[$index] : null;
		} else {
			return $res;
		}
	}

	/**
	 * Checks if node matches css query filter ":root"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_root() {
		return (strtolower($this->tag) === 'html');
	}

	/**
	 * Checks if node matches css query filter ":nth-child(n)"
	 * @param string $n
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_nchild($n) {
		return ($this->index(false) === (int) $n);
	}

	/**
	 * Checks if node matches css query filter ":gt(n)"
	 * @param string $n
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_gt($n) {
		return ($this->index(false) > (int) $n);
	}

	/**
	 * Checks if node matches css query filter ":lt(n)"
	 * @param string $n
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_lt($n) {
		return ($this->index(false) < (int) $n);
	}

	/**
	 * Checks if node matches css query filter ":nth-last-child(n)"
	 * @param string $n
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_nlastchild($n) {
		if ($this->parent === null) {
			return false;
		} else {
			return ($this->parent->childCount(true) - 1 - $this->index(false) === (int) $n);
		}
	}

	/**
	 * Checks if node matches css query filter ":nth-of-type(n)"
	 * @param string $n
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_ntype($n) {
		return ($this->typeIndex() === (int) $n);
	}

	/**
	 * Checks if node matches css query filter ":nth-;ast-of-type(n)"
	 * @param string $n
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_nlastype($n) {
		if ($this->parent === null) {
			return false;
		} else {
			return (count($this->parent->getChildrenByTag($this->tag, 'total', false)) - 1 - $this->typeIndex() === (int) $n);
		}
	}

	/**
	 * Checks if node matches css query filter ":odd"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_odd() {
		return (($this->index(false) & 1) === 1);
	}

	/**
	 * Checks if node matches css query filter ":even"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_even() {
		return (($this->index(false) & 1) === 0);
	}

	/**
	 * Checks if node matches css query filter ":every(n)"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_every($n) {
		return (($this->index(false) % (int) $n) === 0);
	}

	/**
	 * Checks if node matches css query filter ":first"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_first() {
		return ($this->index(false) === 0);
	}

	/**
	 * Checks if node matches css query filter ":last"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_last() {
		if ($this->parent === null) {
			return false;
		} else {
			return ($this->parent->childCount(true) - 1 === $this->index(false));
		}
	}

	/**
	 * Checks if node matches css query filter ":first-of-type"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_firsttype() {
		return ($this->typeIndex() === 0);
	}

	/**
	 * Checks if node matches css query filter ":last-of-type"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_lasttype() {
		if ($this->parent === null) {
			return false;
		} else {
			return (count($this->parent->getChildrenByTag($this->tag, 'total', false)) - 1 === $this->typeIndex());
		}
	}

	/**
	 * Checks if node matches css query filter ":only-child"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_onlychild() {
		if ($this->parent === null) {
			return false;
		} else {
			return ($this->parent->childCount(true) === 1);
		}
	}

	/**
	 * Checks if node matches css query filter ":only-of-type"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_onlytype() {
		if ($this->parent === null) {
			return false;
		} else {
			return (count($this->parent->getChildrenByTag($this->tag, 'total', false)) === 1);
		}
	}

	/**
	 * Checks if node matches css query filter ":empty"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_empty() {
		return ($this->childCount() === 0);
	}

	/**
	 * Checks if node matches css query filter ":not-empty"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_notempty() {
		return ($this->childCount() !== 0);
	}

	/**
	 * Checks if node matches css query filter ":has-text"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_hastext() {
		return ($this->getPlainText() !== '');
	}

	/**
	 * Checks if node matches css query filter ":no-text"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_notext() {
		return ($this->getPlainText() === '');
	}

	/**
	 * Checks if node matches css query filter ":lang(s)"
	 * @param string $lang
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_lang($lang) {
		return ($this->lang === $lang);
	}

	/**
	 * Checks if node matches css query filter ":contains(s)"
	 * @param string $text
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_contains($text) {
		return (strpos($this->getPlainText(), $text) !== false);
	}

	/**
	 * Checks if node matches css query filter ":has(s)"
	 * @param string $selector
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_has($selector) {
		$s = $this->select((string) $selector, false);
		return (is_array($s) && (count($s) > 0));
	}

	/**
	 * Checks if node matches css query filter ":not(s)"
	 * @param string $selector
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_not($selector) {
		$s = $this->select((string) $selector, false, true, true);
		return ((!is_array($s)) || (array_search($this, $s, true) === false));
	}

	/**
	 * Checks if node matches css query filter ":element"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_element() {
		return true;
	}

	/**
	 * Checks if node matches css query filter ":text"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_text() {
		return false;
	}

	/**
	 * Checks if node matches css query filter ":comment"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_comment() {
		return false;
	}
}

/**
 * Node subclass for text
 */
class HTML_NODE_TEXT extends HTML_Node {
	#php4 Compatibility with PHP4, this gets changed to a regular var in release tool
	#static $NODE_TYPE = self::NODE_TEXT;
	#php4e
	#php5
	const NODE_TYPE = self::NODE_TEXT;
	#php5e
	var $tag = '~text~';

	/**
	 * @var string
	 */
	var $text = '';

	/**
	 * Class constructor
	 * @param HTML_Node $parent
	 * @param string $text
	 */
	function __construct($parent, $text = '') {
		$this->parent = $parent;
		$this->text = $text;
	}
	
	#php4 PHP4 class constructor compatibility
	#function HTML_NODE_TEXT($parent, $text = '') {return $this->__construct($parent, $text);}
	#php4e
	
	function isText() {return true;}
	function isTextOrComment() {return true;}
	protected function filter_element() {return false;}
	protected function filter_text() {return true;}
	function toString_attributes() {return '';}
	function toString_content($attributes = true, $recursive = true, $content_only = false) {return $this->text;}
	function toString($attributes = true, $recursive = true, $content_only = false) {return $this->text;}
}

/**
 * Node subclass for comments
 */
class HTML_NODE_COMMENT extends HTML_Node {
	#php4 Compatibility with PHP4, this gets changed to a regular var in release tool
	#static $NODE_TYPE = self::NODE_COMMENT;
	#php4e
	#php5
	const NODE_TYPE = self::NODE_COMMENT;
	#php5e
	var $tag = '~comment~';

	/**
	 * @var string
	 */
	var $text = '';

	/**
	 * Class constructor
	 * @param HTML_Node $parent
	 * @param string $text
	 */
	function __construct($parent, $text = '') {
		$this->parent = $parent;
		$this->text = $text;
	}
	
	#php4 PHP4 class constructor compatibility
	#function HTML_NODE_COMMENT($parent, $text = '') {return $this->__construct($parent, $text);}
	#php4e
	
	function isComment() {return true;}
	function isTextOrComment() {return true;}	
	protected function filter_element() {return false;}
	protected function filter_comment() {return true;}
	function toString_attributes() {return '';}
	function toString_content($attributes = true, $recursive = true, $content_only = false) {return $this->text;}
	function toString($attributes = true, $recursive = true, $content_only = false) {return '<!--'.$this->text.'-->';}
}

/**
 * Node subclass for conditional tags
 */
class HTML_NODE_CONDITIONAL extends HTML_Node {
	#php4 Compatibility with PHP4, this gets changed to a regular var in release tool
	#static $NODE_TYPE = self::NODE_CONDITIONAL;
	#php4e
	#php5
	const NODE_TYPE = self::NODE_CONDITIONAL;
	#php5e
	var $tag = '~conditional~';

	/**
	 * @var string
	 */
	var $condition = '';

	/**
	 * Class constructor
	 * @param HTML_Node $parent
	 * @param string $condition e.g. "if IE"
	 * @param bool $hidden <!--[if if true, <![if if false
	 */
	function __construct($parent, $condition = '', $hidden = true) {
		$this->parent = $parent;
		$this->hidden = $hidden;
		$this->condition = $condition;
	}
	
	#php4 PHP4 class constructor compatibility
	#function HTML_NODE_CONDITIONAL($parent, $condition = '', $hidden = true) {return $this->__construct($parent, $condition, $hidden);}
	#php4e
	
	protected function filter_element() {return false;}
	function toString_attributes() {return '';}
	function toString($attributes = true, $recursive = true, $content_only = false) {
		if ($content_only) {
			if (is_int($content_only)) {
				--$content_only;
			}
			return $this->toString_content($attributes, $recursive, $content_only);
		}

		$s = '<!'.(($this->hidden) ? '--' : '').'['.$this->condition.']>';
		if($recursive) {
			$s .= $this->toString_content($attributes);
		}
		$s .= '<![endif]'.(($this->hidden) ? '--' : '').'>';
		return $s;
	}
}

/**
 * Node subclass for CDATA tags
 */
class HTML_NODE_CDATA extends HTML_Node {
	#php4 Compatibility with PHP4, this gets changed to a regular var in release tool
	#static $NODE_TYPE = self::NODE_CDATA;
	#php4e
	#php5
	const NODE_TYPE = self::NODE_CDATA;
	#php5e
	var $tag = '~cdata~';

	/**
	 * @var string
	 */
	var $text = '';

	/**
	 * Class constructor
	 * @param HTML_Node $parent
	 * @param string $text
	 */
	function __construct($parent, $text = '') {
		$this->parent = $parent;
		$this->text = $text;
	}
	
	#php4 PHP4 class constructor compatibility
	#function HTML_NODE_CDATA($parent, $text = '') {return $this->__construct($parent, $text);}
	#php4e
	
	protected function filter_element() {return false;}
	function toString_attributes() {return '';}
	function toString_content($attributes = true, $recursive = true, $content_only = false) {return $this->text;}
	function toString($attributes = true, $recursive = true, $content_only = false) {return '<![CDATA['.$this->text.']]>';}
}

/**
 * Node subclass for doctype tags
 */
class HTML_NODE_DOCTYPE extends HTML_Node {
	#php4 Compatibility with PHP4, this gets changed to a regular var in release tool
	#static $NODE_TYPE = self::NODE_DOCTYPE;
	#php4e
	#php5
	const NODE_TYPE = self::NODE_DOCTYPE;
	#php5e
	var $tag = '!DOCTYPE';

	/**
	 * @var string
	 */
	var $dtd = '';

	/**
	 * Class constructor
	 * @param HTML_Node $parent
	 * @param string $dtd
	 */
	function __construct($parent, $dtd = '') {
		$this->parent = $parent;
		$this->dtd = $dtd;
	}
	
	#php4 PHP4 class constructor compatibility
	#function HTML_NODE_DOCTYPE($parent, $dtd = '') {return $this->__construct($parent, $dtd);}
	#php4e

	protected function filter_element() {return false;}
	function toString_attributes() {return '';}
	function toString_content($attributes = true, $recursive = true, $content_only = false) {return $this->text;}
	function toString($attributes = true, $recursive = true, $content_only = false) {return '<'.$this->tag.' '.$this->dtd.'>';}
}

/**
 * Node subclass for embedded tags like xml, php and asp
 */
class HTML_NODE_EMBEDDED extends HTML_Node {

	/**
	 * @var string
	 * @internal specific char for tags, like ? for php and % for asp
	 * @access private
	 */
	var $tag_char = '';

	/**
	 * @var string
	 */
	var $text = '';

	/**
	 * Class constructor
	 * @param HTML_Node $parent
	 * @param string $tag_char {@link $tag_char}
	 * @param string $tag {@link $tag}
	 * @param string $text
	 * @param array $attributes array('attr' => 'val')
	 */
	function __construct($parent, $tag_char = '', $tag = '', $text = '', $attributes = array()) {
		$this->parent = $parent;
		$this->tag_char = $tag_char;
		if ($tag[0] !== $this->tag_char) {
			$tag = $this->tag_char.$tag;
		}
		$this->tag = $tag;
		$this->text = $text;
		$this->attributes = $attributes;
		$this->self_close_str = $tag_char;
	}
	
	#php4 PHP4 class constructor compatibility
	#function HTML_NODE_EMBEDDED($parent, $tag_char = '', $tag = '', $text = '', $attributes = array()) {return $this->__construct($parent, $tag_char, $tag, $text, $attributes);}
	#php4e

	protected function filter_element() {return false;}
	function toString($attributes = true, $recursive = true, $content_only = false) {
		$s = '<'.$this->tag;
		if ($attributes) {
			$s .= $this->toString_attributes();
		}
		$s .= $this->text.$this->self_close_str.'>';
		return $s;
	}
}

/**
 * Node subclass for "?" tags, like php and xml
 */
class HTML_NODE_XML extends HTML_NODE_EMBEDDED {
	#php4 Compatibility with PHP4, this gets changed to a regular var in release tool
	#static $NODE_TYPE = self::NODE_XML;
	#php4e
	#php5
	const NODE_TYPE = self::NODE_XML;
	#php5e

	/**
	 * Class constructor
	 * @param HTML_Node $parent
	 * @param string $tag {@link $tag}
	 * @param string $text
	 * @param array $attributes array('attr' => 'val')
	 */
	function __construct($parent, $tag = 'xml', $text = '', $attributes = array()) {
		return parent::__construct($parent, '?', $tag, $text, $attributes);
	}
	
	#php4 PHP4 class constructor compatibility
	#function HTML_NODE_XML($parent, $tag = 'xml', $text = '', $attributes = array()) {return $this->__construct($parent, $tag, $text, $attributes);}
	#php4e
}

/**
 * Node subclass for asp tags
 */
class HTML_NODE_ASP extends HTML_NODE_EMBEDDED {
	#php4 Compatibility with PHP4, this gets changed to a regular var in release tool
	#static $NODE_TYPE = self::NODE_ASP;
	#php4e
	#php5
	const NODE_TYPE = self::NODE_ASP;
	#php5e

	/**
	 * Class constructor
	 * @param HTML_Node $parent
	 * @param string $tag {@link $tag}
	 * @param string $text
	 * @param array $attributes array('attr' => 'val')
	 */
	function __construct($parent, $tag = '', $text = '', $attributes = array()) {
		return parent::__construct($parent, '%', $tag, $text, $attributes);
	}
	
	#php4 PHP4 class constructor compatibility
	#function HTML_NODE_ASP($parent, $tag = '', $text = '', $attributes = array()) {return $this->__construct($parent, $tag, $text, $attributes);}
	#php4e
}

?>