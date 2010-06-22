<?php
/**
 * @author Niels A.D.
 * @package Ganon
 */

/**
 * Converts a document into tokens
 *
 * Can convert any string into tokens. The base class only supports
 * identifier/whitespace tokens. For more tokens, the class can be
 * easily extended.
 *
 * Use like:
 * <code>
 * <?php
 *  $a = new Tokenizer_Base('hello word');
 *  while ($a->next() !== $a::TOK_NULL) {
 *    echo $a->token, ': ',$a->getTokenString(), "<br>\n";
 *  }
 * ?>
 * </code>
 *
 * @internal The tokenizer works with a character map that connects a certain
 * character to a certain function/token. This class is build with speed in mind.
 */
class Tokenizer_Base {

	/**
	 * NULL Token, used at end of document (parsing should stop after this token)
	 */
	const TOK_NULL = 0;
	/**
	 * Unknown token, used at unidentified character
	 */
	const TOK_UNKNOWN = 1;
	/**
	 * Whitespace token, used with whitespace
	 */
	const TOK_WHITESPACE= 2;
	/**
	 * Identifier token, used with identifiers
	 */
	const TOK_IDENTIFIER = 3;

	/**
	 * The document that is being tokenized
	 * @var string
	 * @internal Public for faster access!
	 * @see setDoc()
	 * @see getDoc()
	 * @access private
	 */
	var $doc = '';

	/**
	 * The size of the document (length of string)
	 * @var int
	 * @internal Public for faster access!
	 * @see $doc
	 * @access private
	 */
	var $size = 0;

	/**
	 * Current (character) position in the document
	 * @var int
	 * @internal Public for faster access!
	 * @see setPos()
	 * @see getPos()
	 * @access private
	 */
	var $pos = 0;

	/**
	 * Current (Line/Column) position in document
	 * @var array (Current_Line, Line_Starting_Pos)
	 * @internal Public for faster access!
	 * @see getLinePos()
	 * @access private
	 */
	var $line_pos = array(0, 0);

	/**
	 * Current token
	 * @var int
	 * @internal Public for faster access!
	 * @see getToken()
	 * @access private
	 */
	var $token = self::TOK_NULL;

	/**
	 * Startposition of token. If NULL, then current position is used.
	 * @var int
	 * @internal Public for faster access!
	 * @see getTokenString()
	 * @access private
	 */
	var $token_start = null;

	/**
	 * List with all the character that can be considered as whitespace
	 * @var array|string
	 * @internal Variable is public + asscociated array for faster access!
	 * @internal array(' ' => true) will recognize space (' ') as whitespace
	 * @internal String will be converted to array in constructor
	 * @internal Result token will be {@link self::TOK_WHITESPACE};
	 * @see setWhitespace()
	 * @see getWhitespace()
	 * @access private
	 */
	var $whitespace = " \t\n\r\0\x0B";

	/**
	 * List with all the character that can be considered as identifier
	 * @var array|string
	 * @internal Variable is public + asscociated array for faster access!
	 * @internal array('a' => true) will recognize 'a' as identifer
	 * @internal String will be converted to array in constructor
	 * @internal Result token will be {@link self::TOK_IDENTIFIER};
	 * @see setIdentifiers()
	 * @see getIdentifiers()
	 * @access private
	 */
	var $identifiers = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890_';

	/**
	 * All characters that should be mapped to a token/function that cannot be considered as whitespace or identifier
	 * @var array
	 * @internal Variable is public + asscociated array for faster access!
	 * @internal array('a' => 'parse_a') will call $this->parse_a() if it matches the character 'a'
	 * @internal array('a' => self::TOK_A) will set token to TOK_A if it matches the character 'a'
	 * @see mapChar()
	 * @see unmapChar()
	 * @access private
	 */
	var $custom_char_map = array();

	/**
	 * Automaticly built character map. Built using {@link $identifiers}, {@link $whitespace} and {@link $custom_char_map}
	 * @var array
	 * @internal Public for faster access!
	 * @access private
	 */
	var $char_map = array();
	
	/**
	 * All errors found while parsing the document
	 * @var array
	 * @see addError()
	 */
	var $errors = array();

	/**
	 * Class constructor
	 * @param string $doc Document to be tokenized
	 * @param int $pos Position to start parsing
	 * @see setDoc()
	 * @see setPos()
	 */
	function __construct($doc = '', $pos = 0) {
		$this->setWhitespace($this->whitespace);
		$this->setIdentifiers($this->identifiers);

		$this->setDoc($doc, $pos);
	}

	/**
	 * Sets target document
	 * @param string $doc Document to be tokenized
	 * @param int $pos Position to start parsing
	 * @see getDoc()
	 * @see setPos()
	 */
	function setDoc($doc, $pos = 0) {
		$this->doc = $doc;
		$this->size = strlen($doc);
		$this->setPos($pos);
	}

	/**
	 * Returns target document
	 * @return string
	 * @see setDoc()
	 */
	function getDoc() {
		return $this->doc;
	}

	/**
	 * Sets position in document
	 * @param int $pos
	 * @see getPos()
	 */
	function setPos($pos = 0) {
		$this->pos = $pos - 1;
		$this->line_pos = array(0, 0);
		$this->next();
	}

	/**
	 * Returns current position in document (Index)
	 * @return int
	 * @see setPos()
	 */
	function getPos() {
		return $this->pos;
	}

	/**
	 * Returns current position in document (Line/Char)
	 * @return array array(Line, Column)
	 */
	function getLinePos() {
		return array($this->line_pos[0], $this->pos - $this->line_pos[1]);
	}

	/**
	 * Returns current token
	 * @return int
	 * @see $token
	 */
	function getToken() {
		return $this->token;
	}

	/**
	 * Returns current token as string
	 * @param int $start_offset Offset from token start
	 * @param int $end_offset Offset from token end
	 * @return string
	 */
	function getTokenString($start_offset = 0, $end_offset = 0) {
		$token_start = ((is_int($this->token_start)) ? $this->token_start : $this->pos) + $start_offset;
		$len = $this->pos - $token_start + 1 + $end_offset;
		return (($len > 0) ? substr($this->doc, $token_start, $len) : '');
	}

	/**
	 * Sets characters to be recognized as whitespace
	 *
	 * Used like: setWhitespace('ab') or setWhitespace(array('a' => true, 'b', 'c'));
	 * @param string|array $ws
	 * @see getWhitespace();
	 */
	function setWhitespace($ws) {
		if (is_array($ws)) {
			$this->whitespace = array_fill_keys(array_values($ws), true);
			$this->buildCharMap();
		} else {
			$this->setWhiteSpace(str_split($ws));
		}
	}

	/**
	 * Returns whitespace characters as string/array
	 * @param bool $as_string Should the result be a string or an array?
	 * @return string|array
	 * @see setWhitespace()
	 */
	function getWhitespace($as_string = true) {
		$ws = array_keys($this->whitespace);
		return (($as_string) ? implode('', $ws) : $ws);
	}

	/**
	 * Sets characters to be recognized as identifier
	 *
	 * Used like: setIdentifiers('ab') or setIdentifiers(array('a' => true, 'b', 'c'));
	 * @param string|array $ident
	 * @see getIdentifiers();
	 */
	function setIdentifiers($ident) {
		if (is_array($ident)) {
			$this->identifiers = array_fill_keys(array_values($ident), true);
			$this->buildCharMap();
		} else {
			$this->setIdentifiers(str_split($ident));
		}
	}

	/**
	 * Returns identifier characters as string/array
	 * @param bool $as_string Should the result be a string or an array?
	 * @return string|array
	 * @see setIdentifiers()
	 */
	function getIdentifiers($as_string = true) {
		$ident = array_keys($this->identifiers);
		return (($as_string) ? implode('', $ident) : $ident);
	}

	/**
	 * Maps a custom character to a token/function
	 *
	 * Used like: mapChar('a', self::{@link TOK_IDENTIFIER}) or mapChar('a', 'parse_identifier');
	 * @param string $char Character that should be mapped. If set, it will be overriden
	 * @param int|string $map If function name, then $this->function will be called, otherwise token is set to $map
	 * @see unmapChar()
	 */
	function mapChar($char, $map) {
		$this->custom_char_map[$char] = $map;
		$this->buildCharMap();
	}

	/**
	 * Removes a char mapped with {@link mapChar()}
	 * @param string $char Character that should be unmapped
	 * @see mapChar()
	 */
	function unmapChar($char) {
		unset($this->custom_char_map[$char]);
		$this->buildCharMap();
	}

	/**
	 * Builds the {@link $map_char} array
	 * @internal Builds single array that maps all characters. Gets called if {@link $whitespace}, {@link $identifiers} or {@link $custom_char_map} get modified
	 */
	protected function buildCharMap() {
		$this->char_map = $this->custom_char_map;
		if (is_array($this->whitespace)) {
			foreach($this->whitespace as $w => $v) {
				$this->char_map[$w] = 'parse_whitespace';
			}
		}
		if (is_array($this->identifiers)) {
			foreach($this->identifiers as $i => $v) {
				$this->char_map[$i] = 'parse_identifier';
			}
		}
	}
	
	/**
	 * Add error to the array and appends current position
	 * @param string $error
	 */
	function addError($error) {
		$this->errors[] = htmlentities($error.' at '.($this->line_pos[0] + 1).', '.($this->pos - $this->line_pos[1] + 1).'!');
	}

	/**
	 * Parse whitespace
	 * @return int Token
	 * @internal Gets called with {@link $whitespace} characters
	 */
	protected function parse_whitespace() {
		$this->token_start = $this->pos;

		while(++$this->pos < $this->size) {
			if (!isset($this->whitespace[$this->doc[$this->pos]])) {
				break;
			} elseif($this->doc[$this->pos] === "\r") {
				++$this->line_pos[0];
				if ($this->doc[$this->pos + 1] === "\n") {
					++$this->pos;
				}
				$this->line_pos[1] = $this->pos;
			} elseif($this->doc[$this->pos] === "\n") {
				++$this->line_pos[0];
				$this->line_pos[1] = $this->pos;
			}
		}

		--$this->pos;
		return self::TOK_WHITESPACE;
	}

	/**
	 * Parse identifiers
	 * @return int Token
	 * @internal Gets called with {@link $identifiers} characters
	 */
	protected function parse_identifier() {
		$this->token_start = $this->pos;

		while((++$this->pos < $this->size) && isset($this->identifiers[$this->doc[$this->pos]])) {}

		--$this->pos;
		return self::TOK_IDENTIFIER;
	}

	/**
	 * Continues to the next token
	 * @return int Next token ({@link TOK_NULL} if none)
	 */
	function next() {
		$this->token_start = null;

		if (++$this->pos < $this->size) {
			if (isset($this->char_map[$this->doc[$this->pos]])) {
				if (is_string($this->char_map[$this->doc[$this->pos]])) {
					return ($this->token = $this->{$this->char_map[$this->doc[$this->pos]]}());
				} else {
					return ($this->token = $this->char_map[$this->doc[$this->pos]]);
				}
			} else {
				return ($this->token = self::TOK_UNKNOWN);
			}
		} else {
			return ($this->token = self::TOK_NULL);
		}
	}

	/**
	 * Finds the next token, but skips whitespace
	 * @return int Next token ({@link TOK_NULL} if none)
	 */
	function next_no_whitespace() {
		$this->token_start = null;

		while (++$this->pos < $this->size) {
			if (!isset($this->whitespace[$this->doc[$this->pos]])) {
				if (isset($this->char_map[$this->doc[$this->pos]])) {
					if (is_string($this->char_map[$this->doc[$this->pos]])) {
						return ($this->token = $this->{$this->char_map[$this->doc[$this->pos]]}());
					} else {
						return ($this->token = $this->char_map[$this->doc[$this->pos]]);
					}
				} else {
					return ($this->token = self::TOK_UNKNOWN);
				}
			} elseif($this->doc[$this->pos] === "\r") {
				++$this->line_pos[0];
				if ($this->doc[$this->pos + 1] === "\n") {
					++$this->pos;
				}
				$this->line_pos[1] = $this->pos;
			} elseif($this->doc[$this->pos] === "\n") {
				++$this->line_pos[0];
				$this->line_pos[1] = $this->pos;
			}
		}

		return ($this->token = self::TOK_NULL);
	}

	/**
	 * Finds the next token using stopcharacters
	 *
	 * Used like: next_search('abc') or next_seach(array('a' => true, 'b' => true, 'c' => true));
	 * @param string|array $characters Characters to search for
	 * @param bool $callback Should the function check the charmap after finding a character?
	 * @return int Next token ({@link TOK_NULL} if none)
	 */
	function next_search($characters, $callback = true) {
		$this->token_start = $this->pos;
		if (!is_array($characters)) {
			$characters = array_fill_keys(str_split($characters), true);
		}

		while(++$this->pos < $this->size) {
			if (isset($characters[$this->doc[$this->pos]])) {
				if ($callback && isset($this->char_map[$this->doc[$this->pos]])) {
					if (is_string($this->char_map[$this->doc[$this->pos]])) {
						return ($this->token = $this->{$this->char_map[$this->doc[$this->pos]]}());
					} else {
						return ($this->token = $this->char_map[$this->doc[$this->pos]]);
					}
				} else {
					return ($this->token = self::TOK_UNKNOWN);
				}
			} elseif($this->doc[$this->pos] === "\r") {
				++$this->line_pos[0];
				if ($this->doc[$this->pos + 1] === "\n") {
					++$this->pos;
				}
				$this->line_pos[1] = $this->pos;
			} elseif($this->doc[$this->pos] === "\n") {
				++$this->line_pos[0];
				$this->line_pos[1] = $this->pos;
			}
		}

		return ($this->token = self::TOK_NULL);
	}

	/**
	 * Finds the next token by searching for a string
	 * @param string $needle The needle that's being searched for
	 * @param bool $callback Should the function check the charmap after finding the needle?
	 * @return int Next token ({@link TOK_NULL} if none)
	 */
	function next_pos($needle, $callback = true) {
		$this->token_start = $this->pos;
		if (($this->pos < $this->size) && (($p = strpos($this->doc, $needle, $this->pos + 1)) !== false)) {

			$len = $p - $this->pos - 1;
			if ($len > 0) {
				$str = substr($this->doc, $this->pos + 1, $len);

				if (($l = strrpos($str, "\n")) !== false) {
					++$this->line_pos[0];
					$this->line_pos[1] = $l + $this->pos + 1;

					$len -= $l;
					if ($len > 0) {
						$str = substr($str, 0, -$len);
						$this->line_pos[0] += substr_count($str, "\n");
					}
				}
			}

			$this->pos = $p;
			if ($callback && isset($this->char_map[$this->doc[$this->pos]])) {
				if (is_string($this->char_map[$this->doc[$this->pos]])) {
					return ($this->token = $this->{$this->char_map[$this->doc[$this->pos]]}());
				} else {
					return ($this->token = $this->char_map[$this->doc[$this->pos]]);
				}
			} else {
				return ($this->token = self::TOK_UNKNOWN);
			}
		} else {
			$this->pos = $this->size;
			return ($this->token = self::TOK_NULL);
		}
	}
	
	/**
	 * Expect a specific token or character. Adds error if token doesn't match.
	 * @param string|int $token Character or token to expect
	 * @param bool|int $do_next Go to next character before evaluating. 1 for next char, true to ignore whitespace
	 * @param bool|int $try_next Try next character if current doesn't match. 1 for next char, true to ignore whitespace
	 * @param bool|int $next_on_match Go to next character after evaluating. 1 for next char, true to ignore whitespace
	 * @return bool
	 */
	protected function expect($token, $do_next = true, $try_next = false, $next_on_match = 1) {
		if ($do_next) {
			if ($do_next === 1) {
				$this->next();
			} else {
				$this->next_no_whitespace();
			}
		}
		
		if (is_int($token)) {
			if (($this->token !== $token) && ((!$try_next) || ((($try_next === 1) && ($this->next() !== $token)) || (($try_next === true) && ($this->next_no_whitespace() !== $token))))) {
				$this->addError('Unexpected "'.$this->getTokenString().'"');
				return false;
			}
		} else {
			if (($this->doc[$this->pos] !== $token) && ((!$try_next) || (((($try_next === 1) && ($this->next() !== self::TOK_NULL)) || (($try_next === true) && ($this->next_no_whitespace() !== self::TOK_NULL))) && ($this->doc[$this->pos] !== $token)))) {
				$this->addError('Expected "'.$token.'", but found "'.$this->getTokenString().'"');
				return false;
			}
		}

		if ($next_on_match) {
			if ($next_on_match === 1) {
				$this->next();
			} else {
				$this->next_no_whitespace();
			}
		}
		return true;
	}
}


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
	/**
	 * Node type of class
	 */
	const NODE_TYPE = self::NODE_ELEMENT;


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
		'eg' => 'filter_nchild', //jquery (naming) compatibility
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

	/**
	 * Class destructor
	 * @access private
	 */
	function __destruct() {
		$this->delete();
	}

	/**
	 * Class toString, outputs (@link $tag)
	 * @return string
	 * @access private
	 */
	function __toString() {
		return $this->tag;
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
			foreach($parser->root->children as &$c) {
				$this->parent->addChild($c, $index);
			}
		}
		$this->delete();
		return (($parser && $parser->errors) ? $parser->errors : true);
	}

	/**
	 * Return html code of node
	 * @internal jquery (naming) compatibility
	 * @see getOuterText()
	 * @return string
	 */
	function html() {
		return $this->getOuterText();
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
		return html_entity_decode($this->toString(true, true, true), ENT_QUOTES);
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
			$this->parent = null;
			if ($move_children_up) {
				foreach($this->children as &$c) {
					$c->changeParent($p);
				}
			}
			$p->deleteChild($this, true);
		}
	}

	/**
	 * Deletes all child nodes from node
	 */
	function clear() {
		foreach($this->children as $c) {
			$c->delete();
		}
		$this->children = array();
	}

	/**
	 * Change parent
	 * @param HTML_Node $to New parent, null if none
	 * @param int $index Add child to parent if not present at index, null to not add, negative to cound from end
	 */
	function changeParent($to, &$index = -1) {
		if ($this->parent !== null) {
			$this->parent->deleteChild($this, true);
		}
		$this->parent = $to;
		if ($index !== null) {
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
	 * Find out if node has a certain parent
	 * @param HTML_Node|string $tag Match against parent, string to match tag, object to fully match node
	 * @param bool $recursive
	 * @return bool
	 * @see hasParent()
	 */
	function isParent($tag, $recursive = false) {
		return ($this->hasParent($tag, $recursive) === ($tag !== null));
	}

	/**
	 * Move node to other node
	 * @param HTML_Node $to New parent, null if none
	 * @param int $new_index Add child to parent at index if not present, null to not add, negative to cound from end
	 * @internal Performs {@link changeParent()}
	 */
	function move($to, &$new_index = -1) {
		$this->changeParent($to, $new_index);
	}

	/**
	 * Move childnodes to other node
	 * @param HTML_Node $to New parent, null if none
	 * @param int $new_index Add child to new node at index if not present, null to not add, negative to cound from end
	 * @param int $start Index from child node where to start wrapping, 0 for first element
	 * @param int $end Index from child node where to end wrapping, -1 for last element
	 */
	function moveChildren($to, &$new_index = -1, $start = 0, $end = -1) {
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
			foreach($this->parent->children as &$c) {
				if (($c::NODE_TYPE !== self::NODE_TEXT) && ($c::NODE_TYPE !== self::NODE_COMMENT)) {
					++$index;
				}
				if ($c === $this) {
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
			foreach($this->parent->children as &$c) {
				if (strcasecmp($this->tag, $c->tag) === 0) {
					++$index;
				}
				if ($c === $this) {
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
		$offset = 1;
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
		if ($with_ns) {
			$this->tag = $tag;
			$this->tag_ns = null;
		} elseif ($this->getTag() !== $tag) {
			$this->tag_ns[1] = $tag;
			$this->tag = $this->tag_ns[0].':'.$tag;
		}
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
			foreach($this->children as &$c) {
				if (($c::NODE_TYPE !== self::NODE_TEXT) && ($c::NODE_TYPE !== self::NODE_COMMENT)) {
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
			foreach($this->children as $i => &$c) {
				if (($c::NODE_TYPE !== self::NODE_TEXT) && ($c::NODE_TYPE !== self::NODE_COMMENT)) {
					if (++$count === $child) {
						return $c;
					}
					$last = $c;
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
	function &addChild($tag, &$offset = null) {
		if (!is_object($tag)) {
			$tag = new $this->childClass($tag, $this);
		} elseif ($tag->parent !== $this) {
			$tag->changeParent($this, false);
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
	function &addText($text, &$offset = null) {
		return $this->addChild(new $this->childClass_Text($this, $text), $offset);
	}

	/**
	 * Add comment node
	 * @param string $text
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	function &addComment($text, &$offset = null) {
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
	function &addConditional($condition, $hidden = true, &$offset = null) {
		return $this->addChild(new $this->childClass_Conditional($this, $condition, $hidden), $offset);
	}

	/**
	 * Add CDATA node
	 * @param string $text
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	function &addCDATA($text, &$offset = null) {
		return $this->addChild(new $this->childClass_CDATA($this, $text), $offset);
	}

	/**
	 * Add doctype node
	 * @param string $dtd
	 * @param int $offset Position to insert node, negative to count from end, null to append
	 * @return HTML_Node Added node
	 * @see addChild();
	 */
	function &addDoctype($dtd, &$offset = null) {
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
	function &addXML($tag = 'xml', $text = '', $attributes = array(), &$offset = null) {
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
	function &addASP($tag = '', $text = '', $attributes = array(), &$offset = null) {
		return $this->addChild(new $this->childClass_ASP($this, $tag, $text, $attributes), $offset);
	}

	/**
	 * Delete a childnode
	 * @param int|HTML_Node $child Child(index) to delete, negative to count from end
	 * @param bool $soft_delete False to call {@link delete()) from child
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
		foreach($this->children as &$c) {
			$tmp[] =& $c;
		}
		$this->children = $tmp;
	}

	/**
	 * Wrap node
	 * @param string|HTML_Node $node Wrapping node, string to create new element node
	 * @param int $index Index to insert wrapping node, -1 to append
	 * @param int $child_index Index to insert current node in wrapping node, -1 to append
	 * @return HTML_Node Wrapping node
	 */
	function wrap($node, $index = -1, $child_index = -1) {
		if (!is_object($node)) {
			$node = $this->parent->addChild($node, $index);
		} elseif ($node->parent !== $this->parent) {
			$node->changeParent($this->parent, $index);
		}

		$this->changeParent($node, $child_index);
		return $node;
	}

	/**
	 * Wrap childnodes
	 * @param string|HTML_Node $node Wrapping node, string to create new element node
	 * @param int $start Index from child node where to start wrapping, 0 for first element
	 * @param int $end Index from child node where to end wrapping, -1 for last element
	 * @param int $index Index to insert wrapping node, -1 to append
	 * @param int $child_index Index to insert current node in wrapping node, -1 to append
	 * @return HTML_Node Wrapping node
	 */
	function wrapInner($node, $start = 0, $end = -1, $index = -1, $child_index = -1) {
		if ($end < 0) {
			$end += count($this->children);
		}

		if (!is_object($node)) {
			$node = $this->addChild($node, $index);
		} elseif ($node->parent !== $this) {
			$node->changeParent($this->parent, $index);
		}

		$this->moveChildren($node, $child_index, $start, $end);
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
				$this->attributes[$a[2]] = $val;
			}
		} else {
			$this->attributes[$attr] = $val;
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
				$res = ($match['value'] === $has);

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
								break ((int) $res) + 1;

							case '|=':
							case 'contains_prefix':
								$res = ((preg_match('`\b'.preg_quote($match['value']).'[\-\s]?`s', $val) > 0) === $match['match']);
								break ((int) $res) + 1;

							case '~=':
							case 'contains_word':
								$res = ((preg_match('`\b'.preg_quote($match['value']).'\b`s', $val) > 0) === $match['match']);
								break ((int) $res) + 1;

							case '*=':
							case 'contains':
								$res = ((strpos($val, $match['value']) !== false) === $match['match']);
								break ((int) $res) + 1;

							case '$=':
							case 'ends_with':
								$res = ((substr($val, -strlen($match['value'])) === $match['value']) === $match['match']);
								break ((int) $res) + 1;

							case '^=':
							case 'starts_with':
								$res = ((substr($val, 0, strlen($match['value'])) === $match['value']) === $match['match']);
								break ((int) $res) + 1;

							case '!=':
							case 'not_equal':
								$res = (($val !== $match['value']) === $match['match']);
								break ((int) $res) + 1;

							case '=':
							case 'equals':
								$res = (($val === $match['value']) === $match['match']);
								break ((int) $res) + 1;

							case '>=':
							case 'bigger_than':
								$res = (($val >= $match['value']) === $match['match']);
								break ((int) $res) + 1;

							case '<=':
							case 'smaller_than':
								$res = (($val >= $match['value']) === $match['match']);
								break ((int) $res) + 1;

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
	 * @param array $custom_filters Custom map next to (@link $filter_map)
	 * @return bool
	 * @internal Used by selector class
	 * @see match()
	 * @access private
	 */
	protected function match_filters($conditions, $custom_filters = array()) {
		foreach($conditions as &$c) {
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
	 * @param array $custom_filters Custom map next to (@link $filter_map)
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

			if (($f && (!$this->match_filters($conditions['filters']))) === $match) {
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
		$func =
<<<CALLBACK
	return (%s->match(array(
		'attributes' => array(
			'%s' => array(
				'operator_value' => '%s',
				'value' => %s,
				'match' => %s,
				'compare' => '%s'
			)
		)
	)));
CALLBACK;

		return $this->getChildrenByCallback(
			create_function('$e', sprintf($func, '$e',
				$attribute,
				$mode[0],
				((is_string($value)) ? "'$value'" : (($value) ? 'true' : 'false')),
				$match,
				$compare
			)),
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

		$func =
<<<CALLBACK
	return (%s->match(array(
		'tags' => array(
			'%s' => array(
				'match' => %s,
				'compare' => '%s'
			)
		)
	)));
CALLBACK;

		return $this->getChildrenByCallback(
			create_function('$e', sprintf($func, '$e',
				$tag[0],
				$match,
				$compare
			)),
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
		return getChildrenByAttribute('id', $id, 'equals', 'total', $recursive);
	}

	/**
	 * Finds all children using class attribute
	 * @param string $class
	 * @param bool|int $recursive
	 * @return array
	 */
	function getChildrenByClass($class, $recursive = true) {
		return getChildrenByAttribute('class', $id, 'equals', 'total', $recursive);
	}

	/**
	 * Finds all children using name attribute
	 * @param string $name
	 * @param bool|int $recursive
	 * @return array
	 */
	function getChildrenByName($name, $recursive = true) {
		return getChildrenByAttribute('name', $name, 'equals', 'total', $recursive);
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
			return $res[$index];
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
		return ($this->parent === null) || ($this->parent->parent === null);
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
	protected function filter_containts($text) {
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
		return ($this::NODE_TYPE === self::NODE_ELEMENT);
	}

	/**
	 * Checks if node matches css query filter ":text"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_text() {
		return ($this::NODE_TYPE === self::NODE_TEXT);
	}

	/**
	 * Checks if node matches css query filter ":comment"
	 * @return bool
	 * @see match()
	 * @access private
	 */
	protected function filter_comment() {
		return ($this::NODE_TYPE === self::NODE_COMMENT);
	}
}

/**
 * Node subclass for text
 */
class HTML_NODE_TEXT extends HTML_NODE {
	const NODE_TYPE = self::NODE_TEXT;
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

	function toString_attributes() {return '';}
	function toString_content() {return $this->text;}
	function toString() {return $this->text;}
}

/**
 * Node subclass for comments
 */
class HTML_NODE_COMMENT extends HTML_NODE {
	const NODE_TYPE = self::NODE_COMMENT;
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

	function toString_attributes() {return '';}
	function toString_content() {return $this->text;}
	function toString() {return '<!--'.$this->text.'-->';}
}

/**
 * Node subclass for conditional tags
 */
class HTML_NODE_CONDITIONAL extends HTML_NODE {
	const NODE_TYPE = self::NODE_CONDITIONAL;
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
class HTML_NODE_CDATA extends HTML_NODE {
	const NODE_TYPE = self::NODE_CDATA;
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

	function toString_attributes() {return '';}
	function toString_content() {return $this->text;}
	function toString() {return '<![CDATA['.$this->text.']]>';}
}

/**
 * Node subclass for doctype tags
 */
class HTML_NODE_DOCTYPE extends HTML_NODE {
	const NODE_TYPE = self::NODE_DOCTYPE;
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

	function toString_attributes() {return '';}
	function toString_content() {return $this->text;}
	function toString() {return '<'.$this->tag.' '.$this->dtd.'>';}
}

/**
 * Node subclass for embedded tags like xml, php and asp
 */
class HTML_NODE_EMBEDDED extends HTML_NODE {

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
	const NODE_TYPE = self::NODE_XML;

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
}

/**
 * Node subclass for asp tags
 */
class HTML_NODE_ASP extends HTML_NODE_EMBEDDED {
	const NODE_TYPE = self::NODE_ASP;

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
}

/**
 * Parses a HTML document
 *
 * Functionality can be extended by overriding functions or adjusting the tag map.
 * Document may contain small errors, the parser will try to recover and resume parsing.
 */
class HTML_Parser_Base extends Tokenizer_Base {

	/**
	 * Tag open token, used for "<"
	 */
	const TOK_TAG_OPEN = 100;
	/**
	 * Tag close token, used for ">"
	 */
	const TOK_TAG_CLOSE = 101;
	/**
	 * Forward slash token, used for "/"
	 */
	const TOK_SLASH_FORWARD = 103;
	/**
	 * Backslash token, used for "\"
	 */
	const TOK_SLASH_BACKWARD = 104;
	/**
	 * String token, used for attribute values (" and ')
	 */
	const TOK_STRING = 104;
	/**
	 * Equals token, used for "="
	 */
	const TOK_EQUALS = 105;

	/**
	 * Sets HTML identifiers, tags/attributes are considered identifiers
	 * @see Tokenizer_Base::$identifiers
	 * @access private
	 */
	var $identifiers = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890:-_!?%';

	/**
	 * Status of the parser (tagname, closing tag, etc)
	 * @var array
	 */
	var $status = array();

	/**
	 * Map characters to match their tokens
	 * @see Tokenizer_Base::$custom_char_map
	 * @access private
	 */
	var $custom_char_map = array(
		'<' => self::TOK_TAG_OPEN,
		'>' => self::TOK_TAG_CLOSE,
		"'" => 'parse_string',
		'"' => 'parse_string',
		'/' => self::TOK_SLASH_FORWARD,
		'\\' => self::TOK_SLASH_BACKWARD,
		'=' => self::TOK_EQUALS
	);

	function __construct($doc = '', $pos = 0) {
		parent::__construct($doc, $pos);
		$this->parse_all();
	}

	/**
	 Callback functions for certain tags
	 @var array (TAG_NAME => FUNCTION_NAME)
	 @internal Function should be a method in the class
	 @internal Tagname should be lowercase and is everything after <, e.g. "?php" or "!doctype"
	 @access private
	 */
	var $tag_map = array(
		'!doctype' => 'parse_doctype',
		'?' => 'parse_php',
		'?php' => 'parse_php',
		'%' => 'parse_asp',
		'style' => 'parse_style',
		'script' => 'parse_script'
	);

	/**
	 * Parse a HTML string (attributes)
	 * @internal Gets called with ' and "
	 * @return int
	 */
	protected function parse_string() {
		if ($this->next_pos($this->doc[$this->pos], false) !== self::TOK_UNKNOWN) {
			--$this->pos;
		}
		return self::TOK_STRING;
	}

	/**
	 * Parse text between tags
	 * @internal Gets called between tags, uses {@link $status}[last_pos]
	 * @internal Stores text in {@link $status}[text]
	 */
	function parse_text() {
		$len = $this->pos - 1 - $this->status['last_pos'];
		$this->status['text'] = (($len > 0) ? substr($this->doc, $this->status['last_pos'] + 1, $len) : '');
	}

	/**
	 * Parse comment tags
	 * @internal Gets called with HTML comments ("<!--")
	 * @internal Stores text in {@link $status}[comment]
	 * @return bool
	 */
	function parse_comment() {
		$this->pos += 3;
		if ($this->next_pos('-->', false) !== self::TOK_UNKNOWN) {
			$this->status['comment'] = $this->getTokenString(1, -1);
			--$this->pos;
		} else {
			$this->status['comment'] = $this->getTokenString(1, -1);
			$this->pos += 2;
		}
		$this->status['last_pos'] = $this->pos;

		return true;
	}

	/**
	 * Parse doctype tag
	 * @internal Gets called with doctype ("<!doctype")
	 * @internal Stores text in {@link $status}[dtd]
	 * @return bool
	 */
	function parse_doctype() {
		$start = $this->pos;
		if ($this->next_search('[>', false) === self::TOK_UNKNOWN)  {
			if ($this->doc[$this->pos] === '[') {
				if (($this->next_pos(']', false) !== self::TOK_UNKNOWN) || ($this->next_pos('>', false) !== self::TOK_UNKNOWN)) {
					$this->addError('Invalid doctype');
					return false;
				}
			}

			$this->token_start = $start;
			$this->status['dtd'] = $this->getTokenString(2, -1);
			$this->status['last_pos'] = $this->pos;
			return true;
		} else {
			$this->addError('Invalid doctype');
			return false;
		}
	}

	/**
	 * Parse cdata tag
	 * @internal Gets called with cdata ("<![cdata")
	 * @internal Stores text in {@link $status}[cdata]
	 * @return bool
	 */
	function parse_cdata() {
		if ($this->next_pos(']]>', false) === self::TOK_UNKNOWN) {
			$this->status['cdata'] = $this->getTokenString(9, -1);
			$this->status['last_pos'] = $this->pos + 2;
			return true;
		} else {
			$this->addError('Invalid cdata tag');
			return false;
		}
	}

	/**
	 * Parse php tags
	 * @internal Gets called with php tags ("<?php")
	 * @return bool
	 */
	function parse_php() {
		$start = $this->pos;
		if ($this->next_pos('?>', false) !== self::TOK_UNKNOWN) {
			$this->pos -= 2; //End of file
		}

		$len = $this->pos - 1 - $start;
		$this->status['text'] = (($len > 0) ? substr($this->doc, $start + 1, $len) : '');
		$this->status['last_pos'] = ++$this->pos;
		return true;
	}

	/**
	 * Parse asp tags
	 * @internal Gets called with asp tags ("<%")
	 * @return bool
	 */
	function parse_asp() {
		$start = $this->pos;
		if ($this->next_pos('%>', false) !== self::TOK_UNKNOWN) {
			$this->pos -= 2; //End of file
		}

		$len = $this->pos - 1 - $start;
		$this->status['text'] = (($len > 0) ? substr($this->doc, $start + 1, $len) : '');
		$this->status['last_pos'] = ++$this->pos;
		return true;
	}

	/**
	 * Parse style tags
	 * @internal Gets called with php tags ("<style>")
	 * @return bool
	 */
	function parse_style() {
		if ($this->parse_attributes() && ($this->token === self::TOK_TAG_CLOSE) && ($start = $this->pos) && ($this->next_pos('</style>', false) === self::TOK_UNKNOWN)) {
			$len = $this->pos - 1 - $start;
			$this->status['text'] = (($len > 0) ? substr($this->doc, $start + 1, $len) : '');

			$this->pos += 7;
			$this->status['last_pos'] = $this->pos;
			return true;
		} else {
			$this->addError('No end for style tag found');
			return false;
		}
	}

	/**
	 * Parse script tags
	 * @internal Gets called with php tags ("<script>")
	 * @return bool
	 */
	function parse_script() {
		if ($this->parse_attributes() && ($this->token === self::TOK_TAG_CLOSE) && ($start = $this->pos) && ($this->next_pos('</script>', false) === self::TOK_UNKNOWN)) {
			$len = $this->pos - 1 - $start;
			$this->status['text'] = (($len > 0) ? substr($this->doc, $start + 1, $len) : '');

			$this->pos += 8;
			$this->status['last_pos'] = $this->pos;
			return true;
		} else {
			$this->addError('No end for script tag found');
			return false;
		}
	}

	/**
	 * Parse conditional tags (+ all conditional tags inside)
	 * @internal Gets called with IE conditionals ("<![if]" and "<!--[if]")
	 * @internal Stores condition in {@link $status}[tag_condition]
	 * @return bool
	 */
	function parse_conditional() {
		if ($this->status['closing_tag']) {
			$this->pos += 8;
		} else {
			$this->pos += (($this->status['comment']) ? 5 : 3);
			if ($this->next_pos(']', false) !== self::TOK_UNKNOWN) {
				$this->addError('"]" not found in conditional tag');
				return false;
			}
			$this->status['tag_condition'] = $this->getTokenString(0, -1);
		}

		if ($this->next_no_whitespace() !== self::TOK_TAG_CLOSE) {
			$this->addError('No ">" tag found 2 for conditional tag');
			return false;
		}

		if ($this->status['comment']) {
			$this->status['last_pos'] = $this->pos;
			if ($this->next_pos('-->', false) !== self::TOK_UNKNOWN) {
				$this->addError('No ending tag found for conditional tag');
				$this->pos = $this->size - 1;

				$len = $this->pos - 1 - $this->status['last_pos'];
				$this->status['text'] = (($len > 0) ? substr($this->doc, $this->status['last_pos'] + 1, $len) : '');
			} else {
				$len = $this->pos - 10 - $this->status['last_pos'];
				$this->status['text'] = (($len > 0) ? substr($this->doc, $this->status['last_pos'] + 1, $len) : '');
				$this->pos += 2;
			}
		}

		$this->status['last_pos'] = $this->pos;
		return true;
	}

	/**
	 * Parse attributes (names + value)
	 * @internal Stores attributes in {@link $status}[attributes] (array(ATTR => VAL))
	 * @return bool
	 */
	function parse_attributes() {
		$this->status['attributes'] = array();

		while ($this->next_no_whitespace() === self::TOK_IDENTIFIER) {
			$attr = $this->getTokenString();
			if (($attr === '?') || ($attr === '%')) {
				//Probably closing tags
				break;
			}

			if ($this->next_no_whitespace() === self::TOK_EQUALS) {
				if ($this->next_no_whitespace() === self::TOK_STRING) {
					$val = $this->getTokenString(1, -1);
				} else {
					if (!isset($stop)) {
						$stop = $this->whitespace;
						$stop['<'] = true;
						$stop['>'] = true;
					}

					while ((++$this->pos < $this->size) && (!isset($stop[$this->doc[$this->pos]]))) {}
					--$this->pos;
					$val = $this->getTokenString();

					if (trim($val) === '') {
						$this->addError('Invalid attribute value');
						return false;
					}
				}
			} else {
				$val = $attr;
				$this->pos = (($this->token_start) ? $this->token_start : $this->pos) - 1;
			}

			$this->status['attributes'][$attr] = $val;
		}

		return true;
	}

	/**
	 * Default callback for tags
	 * @internal Gets called after the tagname (<html*ENTERS_HERE* attribute="value">)
	 * @return bool
	 */
	function parse_tag_default() {
		if ($this->status['closing_tag']) {
			$this->status['attributes'] = array();
			$this->next_no_whitespace();
		} else {
			if (!$this->parse_attributes()) {
				return false;
			}
		}

		if ($this->token !== self::TOK_TAG_CLOSE) {
			if ($this->token === self::TOK_SLASH_FORWARD) {
				$this->status['self_close'] = true;
				$this->next();
			} elseif ((($this->status['tag_name'][0] === '?') && ($this->doc[$this->pos] === '?')) || (($this->status['tag_name'][0] === '%') && ($this->doc[$this->pos] === '%'))) {
				$this->status['self_close'] = true;
				$this->pos++;

				if (isset($this->char_map[$this->doc[$this->pos]]) && (!is_string($this->char_map[$this->doc[$this->pos]]))) {
					$this->token = $this->char_map[$this->doc[$this->pos]];
				} else {
					$this->token = self::TOK_UNKNOWN;
				}
			}/* else {
				$this->status['self_close'] = false;
			}*/
		}

		if ($this->token !== self::TOK_TAG_CLOSE) {
			$this->addError('Expected ">", but found "'.$this->getTokenString().'"');
			if ($this->next_pos('>', false) !== self::TOK_UNKNOWN) {
				$this->addError('No ">" tag found for "'.$this->status['tag_name'].'" tag');
				return false;
			}
		}

		return true;
	}

	/**
	 * Parse tag
	 * @internal Gets called after opening tag (<*ENTERS_HERE*html attribute="value">)
	 * @internal Stores information about the tag in {@link $status} (comment, closing_tag, tag_name)
	 * @return bool
	 */
	function parse_tag() {
		$start = $this->pos;
		$this->status['self_close'] = false;
		$this->parse_text();

		if ($this->doc[$this->pos + 1] === '!') {
			$this->status['closing_tag'] = false;

			if (substr($this->doc, $this->pos + 2, 2) === '--') {
				$this->status['comment'] = true;

				if (($this->doc[$this->pos + 4] === '[') && (strcasecmp(substr($this->doc, $this->pos + 5, 2), 'if') === 0)) {
					return $this->parse_conditional();
				} else {
					return $this->parse_comment();
				}
			} else {
				$this->status['comment'] = false;

				if ($this->doc[$this->pos + 2] === '[') {
					if (strcasecmp(substr($this->doc, $this->pos + 3, 2), 'if') === 0) {
						return $this->parse_conditional();
					} elseif (strcasecmp(substr($this->doc, $this->pos + 3, 5), 'endif') === 0) {
						$this->status['closing_tag'] = true;
						return $this->parse_conditional();
					} elseif (strcasecmp(substr($this->doc, $this->pos + 3, 5), 'cdata') === 0) {
						return $this->parse_cdata();
					}
				}
			}
		} elseif ($this->doc[$this->pos + 1] === '/') {
			$this->status['closing_tag'] = true;
			++$this->pos;
		} else {
			$this->status['closing_tag'] = false;
		}

		if ($this->next() !== self::TOK_IDENTIFIER) {
			$this->addError('Tagname expected');
			//if ($this->next_pos('>', false) === self::TOK_UNKNOWN) {
				$this->status['last_pos'] = $start - 1;
				return true;
			//} else {
			//	return false;
			//}
		}

		$tag = $this->getTokenString();
		$this->status['tag_name'] = $tag;
		$tag = strtolower($tag);

		if (isset($this->tag_map[$tag])) {
			$res = $this->{$this->tag_map[$tag]}();
		} else {
			$res = $this->parse_tag_default();
		}

		$this->status['last_pos'] = $this->pos;
		return $res;
	}

	/**
	 * Parse full document
	 * @return bool
	 */
	function parse_all() {
		$this->errors = array();
		$this->status['last_pos'] = $this->pos;
		if (($this->token === self::TOK_TAG_OPEN) || ($this->next_pos('<', false) === self::TOK_UNKNOWN)) {
			do {
				if (!$this->parse_tag()) {
					return false;
				}
			} while ($this->next_pos('<') !== self::TOK_NULL);
		}

		$this->pos = $this->size - 1;
		$this->parse_text();

		return true;
	}
}

/**
 * Parses a HTML document into a HTML DOM
 */
class HTML_Parser extends HTML_Parser_Base {

	/**
	 * Root object
	 * @internal If string, then it will create a new instance as root
	 * @var HTML_Node
	 */
	var $root = 'HTML_Node';

	/**
	 * Current parsing hierarchy
	 * @internal Root is always at index 0, current tag is at the end of the array
	 * @var array
	 * @access private
	 */
	var $hierarchy = array();

	/**
	 * Tags that don't need closing tags
	 * @var array
	 * @access private
	 */
	var	$tags_selfclose = array(
		'area'		=> true,
		'base'		=> true,
		'basefont'	=> true,
		'br'		=> true,
		'col'		=> true,
		'command'	=> true,
		'embed'		=> true,
		'frame'		=> true,
		'hr'		=> true,
		'img'		=> true,
		'input'		=> true,
		'ins'		=> true,
		'keygen'	=> true,
		'link'		=> true,
		'meta'		=> true,
		'param'		=> true,
		'source'	=> true,
		'track'		=> true,
		'wbr'		=> true
	);

	/**
	 * Class constructor
	 * @param string $doc Document to be tokenized
	 * @param int $pos Position to start parsing
	 * @param HTML_Node $root Root node, null to auto create
	 */
	function __construct($doc = '', $pos = 0, $root = null) {
		if ($root === null) {
			$root = new $this->root('~root~', null);
		}
		$this->root =& $root;

		parent::__construct($doc, $pos);
	}

	/**
	 * Class destructor
	 * @access private
	 */
	function __destruct() {
		$this->root = null;
	}

	/**
	 * Class magic invoke method, performs {@link select()}
	 * @return array
	 * @access private
	 */
	function __invoke($query = '*') {
		return $this->select($query);
	}

	/**
	 * Class magic toString method, performs {@link HTML_Node::toString()}
	 * @return string
	 * @access private
	 */
	function __toString() {
		return $this->root->getInnerText();
	}

	/**
	 * Performs a css select query on the root node
	 * @see HTML_Node::select()
	 * @return array
	 */
	function select($query = '*', $index = false, $recursive = true, $check_self = false) {
		return $this->root->select($query, $index, $recursive, $check_self);
	}

	/**
	 * Updates the current hierarchy status and checks for
	 * correct opening/closing of tags
	 * @param bool $self_close Is current tag self closing? Null to use {@link tags_selfclose}
	 * @internal This is were most of the nodes get added
	 * @access private
	 */
	protected function parse_hierarchy($self_close = null) {
		if ($self_close === null) {
			$this->status['self_close'] = ($self_close = isset($this->tags_selfclose[strtolower($this->status['tag_name'])]));
		}

		if ($self_close) {
			if ($this->status['tag_name'][0] === '?') {
				end($this->hierarchy)->addXML($this->status['tag_name'], '', $this->status['attributes']);
			} elseif ($this->status['tag_name'][0] === '%') {
				end($this->hierarchy)->addASP($this->status['tag_name'], '', $this->status['attributes']);
			} else {
				end($this->hierarchy)->addChild($this->status);
			}
		} elseif ($this->status['closing_tag']) {
			$found = false;
			for ($count = count($this->hierarchy), $i = $count - 1; $i >= 0; $i--) {
				if (strcasecmp($this->hierarchy[$i]->tag, $this->status['tag_name']) === 0) {

					for($ii = ($count - $i - 1); $ii >= 0; $ii--) {
						$e = array_pop($this->hierarchy);
						if ($ii > 0) {
							$this->addError('Closing tag "'.$this->status['tag_name'].'" while "'.$e->tag.'" is not closed yet');
						}
					}

					$found = true;
					break;
				}
			}

			if (!$found) {
				$this->addError('Closing tag "'.$this->status['tag_name'].'" which is not open');
			}

		} else {
			$this->hierarchy[] = end($this->hierarchy)->addChild($this->status);
		}
	}

	function parse_cdata() {
		if (!parent::parse_cdata()) {return false;}

		end($this->hierarchy)->addCDATA($this->status['cdata']);
		return true;
	}

	function parse_comment() {
		if (!parent::parse_comment()) {return false;}

		end($this->hierarchy)->addComment($this->status['comment']);
		return true;
	}

	function parse_conditional() {
		if (!parent::parse_conditional()) {return false;}

		if ($this->status['comment']) {
			$e = end($this->hierarchy)->addConditional($this->status['tag_condition'], true);
			if ($this->status['text']) {
				$e->addText($this->status['text']);
			}
		} else {
			if ($this->status['closing_tag']) {
				$this->parse_hierarchy(false);
			} else {
				$this->hierarchy[] = end($this->hierarchy)->addConditional($this->status['tag_condition'], false);
			}
		}

		return true;
	}

	function parse_doctype() {
		if (!parent::parse_doctype()) {return false;}

		end($this->hierarchy)->addDoctype($this->status['dtd']);
		return true;
	}

	function parse_php() {
		if (!parent::parse_php()) {return false;}

		end($this->hierarchy)->addXML('php', $this->status['text']);
		return true;
	}

	function parse_asp() {
		if (!parent::parse_asp()) {return false;}

		end($this->hierarchy)->addASP('', $this->status['text']);
		return true;
	}

	function parse_script() {
		if (!parent::parse_script()) {return false;}

		$e = end($this->hierarchy)->addChild($this->status);
		if ($this->status['text']) {
			$e->addText($this->status['text']);
		}
		return true;
	}

	function parse_style() {
		if (!parent::parse_style()) {return false;}

		$e = end($this->hierarchy)->addChild($this->status);
		if ($this->status['text']) {
			$e->addText($this->status['text']);
		}
		return true;
	}

	function parse_tag_default() {
		if (!parent::parse_tag_default()) {return false;}

		$this->parse_hierarchy(($this->status['self_close']) ? true : null);
		return true;
	}

	function parse_text() {
		parent::parse_text();
		if ($this->status['text']) {
			end($this->hierarchy)->addText($this->status['text']);
		}
	}

	function parse_all() {
		$this->hierarchy = array($this->root);
		return ((parent::parse_all()) ? $this->root : false);
	}
}

/**
 * HTML5 specific parser (adds support for omittable closing tags)
 */
class HTML_Parser_HTML5 extends HTML_Parser {

	/**
	 * Tags with ommitable closing tags
	 * @var array array('tag2' => 'tag1') will close tag1 if following (not child) tag is tag2
	 * @access private
	 */
	var $tags_optional_close = array(
		//Current tag	=> Previous tag
		'li' 			=> array('li' => true),
		'dt' 			=> array('dt' => true, 'dd' => true),
		'dd' 			=> array('dt' => true, 'dd' => true),
		'address' 		=> array('p' => true),
		'article' 		=> array('p' => true),
		'aside' 		=> array('p' => true),
		'blockquote' 	=> array('p' => true),
		'dir' 			=> array('p' => true),
		'div' 			=> array('p' => true),
		'dl' 			=> array('p' => true),
		'fieldset' 		=> array('p' => true),
		'footer' 		=> array('p' => true),
		'form' 			=> array('p' => true),
		'h1' 			=> array('p' => true),
		'h2' 			=> array('p' => true),
		'h3' 			=> array('p' => true),
		'h4' 			=> array('p' => true),
		'h5' 			=> array('p' => true),
		'h6' 			=> array('p' => true),
		'header' 		=> array('p' => true),
		'hgroup' 		=> array('p' => true),
		'hr' 			=> array('p' => true),
		'menu' 			=> array('p' => true),
		'nav' 			=> array('p' => true),
		'ol' 			=> array('p' => true),
		'p' 			=> array('p' => true),
		'pre' 			=> array('p' => true),
		'section' 		=> array('p' => true),
		'table' 		=> array('p' => true),
		'ul' 			=> array('p' => true),
		'rt'			=> array('rt' => true, 'rp' => true),
		'rp'			=> array('rt' => true, 'rp' => true),
		'optgroup'		=> array('optgroup' => true, 'option' => true),
		'option'		=> array('option'),
		'tbody'			=> array('thread' => true, 'tbody' => true, 'tfoot' => true),
		'tfoot'			=> array('thread' => true, 'tbody' => true),
		'tr'			=> array('tr' => true),
		'td'			=> array('td' => true, 'th' => true),
		'th'			=> array('td' => true, 'th' => true),
		'body'			=> array('head' => true)
	);

	protected function parse_hierarchy($self_close = null) {
		$tag_curr = strtolower($this->status['tag_name']);
		if ($self_close === null) {
			$this->status['self_close'] = ($self_close = isset($this->tags_selfclose[$tag_curr]));
		}

		if (! ($self_close || $this->status['closing_tag'])) {
			$tag_prev = strtolower(end($this->hierarchy)->tag);
			if (isset($this->tags_optional_close[$tag_curr]) && isset($this->tags_optional_close[$tag_curr][$tag_prev])) {
				array_pop($this->hierarchy);
			}
		}

		return parent::parse_hierarchy($self_close);
	}
}

/**
 * Converts a XML document to an array
 */
class XML_Parser_Array extends HTML_Parser_Base {

	/**
	 * Holds the document structure
	 * @var array array('name' => 'tag', 'attrs' => array('attr' => 'val'), 'childen' => array())
	 */
	var $root = array(
		'name' => '',
		'attrs' => array(),
		'children' => array()
	);

	/**
	 * Current parsing hierarchy
	 * @var array
	 * @access private
	 */
	var $hierarchy = array();

	protected function parse_hierarchy($self_close) {
		if ($this->status['closing_tag']) {
			$found = false;
			for ($count = count($this->hierarchy), $i = $count; $i >= 0; $i--) {
				if (strcasecmp($this->hierarchy[$i]['name'], $this->status['tag_name']) === 0) {

					for($ii = ($count - $i - 1); $ii >= 0; $ii--) {
						$e = array_pop($this->hierarchy);
						if ($ii > 0) {
							$this->addError('Closing tag "'.$this->status['tag_name'].'" while "'.$e['name'].'" is not closed yet');
						}
					}

					$found = true;
					break;
				}
			}

			if (!$found) {
				$this->addError('Closing tag "'.$this->status['tag_name'].'" which is not open');
			}
		} else {
			$tag = array(
				'name' => $this->status['tag_name'],
				'attrs' => $this->status['attributes']
			);
			if ($this->hierarchy) {
				$current =& $this->hierarchy[count($this->hierarchy) - 1];
				$current['children'][] = $tag;
				$tag =& $current['children'][count($current['children']) - 1];
				unset($current['tagData']);
			} else {
				$this->root = $tag;
				$tag =& $this->root;
				$tag['children'] = array();
				$self_close = false;
			}
			if (!$self_close) {
				$this->hierarchy[] =& $tag;
			}
		}
	}

	function parse_tag_default() {
		if (!parent::parse_tag_default()) {return false;}

		if ($this->status['tag_name'][0] !== '?') {
			$this->parse_hierarchy(($this->status['self_close']) ? true : null);
		}
		return true;
	}

	function parse_text() {
		parent::parse_text();
		if ($this->status['text'] && ($this->hierarchy)) {
			$current =& $this->hierarchy[count($this->hierarchy) - 1];
			if (!$current['children']) {
				$current['tagData'] = $this->status['text'];
			}
		}
	}

	function parse_all() {
		return ((parent::parse_all()) ? $this->root : false);
	}
}

/**
 * Tokenizes a css selector query
 */
class Tokenizer_CSSQuery extends Tokenizer_Base {

	/**
	 * Opening bracket token, used for "["
	 */
	const TOK_BRACKET_OPEN = 100;
	/**
	 * Closing bracket token, used for "]"
	 */
	const TOK_BRACKET_CLOSE = 101;
	/**
	 * Opening brace token, used for "("
	 */
	const TOK_BRACE_OPEN = 102;
	/**
	 * Closing brace token, used for ")"
	 */
	const TOK_BRACE_CLOSE = 103;
	/**
	 * String token
	 */
	const TOK_STRING = 104;
	/**
	 * Colon token, used for ":"
	 */
	const TOK_COLON = 105;
	/**
	 * Comma token, used for ","
	 */
	const TOK_COMMA = 106;
	/**
	 * "Not" token, used for "!"
	 */
	const TOK_NOT = 107;

	/**
	 * "All" token, used for "*" in query
	 */
	const TOK_ALL = 108;
	/**
	 * Pipe token, used for "|"
	 */
	const TOK_PIPE = 109;
	/**
	 * Plus token, used for "+"
	 */
	const TOK_PLUS = 110;
	/**
	 * "Sibling" token, used for "~" in query
	 */
	const TOK_SIBLING = 111;
	/**
	 * Class token, used for "." in query
	 */
	const TOK_CLASS = 112;
	/**
	 * ID token, used for "#" in query
	 */
	const TOK_ID = 113;
	/**
	 * Child token, used for ">" in query
	 */
	const TOK_CHILD = 114;

	/**
	 * Attribute compare prefix token, used for "|="
	 */
	const TOK_COMPARE_PREFIX = 115;
	/**
	 * Attribute contains token, used for "*="
	 */
	const TOK_COMPARE_CONTAINS = 116;
	/**
	 * Attribute contains word token, used for "~="
	 */
	const TOK_COMPARE_CONTAINS_WORD = 117;
	/**
	 * Attribute compare end token, used for "$="
	 */
	const TOK_COMPARE_ENDS = 118;
	/**
	 * Attribute equals token, used for "="
	 */
	const TOK_COMPARE_EQUALS = 119;
	/**
	 * Attribute not equal token, used for "!="
	 */
	const TOK_COMPARE_NOT_EQUAL = 120;
	/**
	 * Attribute compare bigger than token, used for ">="
	 */
	const TOK_COMPARE_BIGGER_THAN = 121;
	/**
	 * Attribute compare smaller than token, used for "<="
	 */
	const TOK_COMPARE_SMALLER_THAN = 122;
	/**
	 * Attribute compare with regex, used for "%="
	 */
	const TOK_COMPARE_REGEX = 123;
	/**
	 * Attribute compare start token, used for "^="
	 */
	const TOK_COMPARE_STARTS = 124;

	/**
	 * Sets query identifiers
	 * @see Tokenizer_Base::$identifiers
	 * @access private
	 */
	var $identifiers = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890_-?';

	/**
	 * Map characters to match their tokens
	 * @see Tokenizer_Base::$custom_char_map
	 * @access private
	 */
	var $custom_char_map = array(
		'.' => self::TOK_CLASS,
		'#' => self::TOK_ID,
		',' => self::TOK_COMMA,
		'>' => 'parse_gt',//self::TOK_CHILD,

		'+' => self::TOK_PLUS,
		'~' => 'parse_sibling',

		'|' => 'parse_pipe',
		'*' => 'parse_star',
		'$' => 'parse_compare',
		'=' => self::TOK_COMPARE_EQUALS,
		'!' => 'parse_not',
		'%' => 'parse_compare',
		'^' => 'parse_compare',
		'<' => 'parse_compare',

		'"' => 'parse_string',
		"'" => 'parse_string',
		'(' => self::TOK_BRACE_OPEN,
		')' => self::TOK_BRACE_CLOSE,
		'[' => self::TOK_BRACKET_OPEN,
		']' => self::TOK_BRACKET_CLOSE,
		':' => self::TOK_COLON
	);

	/**
	 * Parse ">" character
	 * @internal Could be {@link TOK_CHILD} or {@link TOK_COMPARE_BIGGER_THAN}
	 * @return int
	 */
	protected function parse_gt() {
		if ($this->doc[$this->pos + 1] === '=') {
			++$this->pos;
			return ($this->token = self::TOK_COMPARE_BIGGER_THAN);
		} else {
			return ($this->token = self::TOK_CHILD);
		}
	}

	/**
	 * Parse "~" character
	 * @internal Could be {@link TOK_SIBLING} or {@link TOK_COMPARE_CONTAINS_WORD}
	 * @return int
	 */
	protected function parse_sibling() {
		if ($this->doc[$this->pos + 1] === '=') {
			++$this->pos;
			return ($this->token = self::TOK_COMPARE_CONTAINS_WORD);
		} else {
			return ($this->token = self::TOK_SIBLING);
		}
	}

	/**
	 * Parse "|" character
	 * @internal Could be {@link TOK_PIPE} or {@link TOK_COMPARE_PREFIX}
	 * @return int
	 */
	protected function parse_pipe() {
		if ($this->doc[$this->pos + 1] === '=') {
			++$this->pos;
			return ($this->token = self::TOK_COMPARE_PREFIX);
		} else {
			return ($this->token = self::TOK_PIPE);
		}
	}

	/**
	 * Parse "*" character
	 * @internal Could be {@link TOK_ALL} or {@link TOK_COMPARE_CONTAINS}
	 * @return int
	 */
	protected function parse_star() {
		if ($this->doc[$this->pos + 1] === '=') {
			++$this->pos;
			return ($this->token = self::TOK_COMPARE_CONTAINS);
		} else {
			return ($this->token = self::TOK_ALL);
		}
	}

	/**
	 * Parse "!" character
	 * @internal Could be {@link TOK_NOT} or {@link TOK_COMPARE_NOT_EQUAL}
	 * @return int
	 */
	protected function parse_not() {
		if ($this->doc[$this->pos + 1] === '=') {
			++$this->pos;
			return ($this->token = self::TOK_COMPARE_NOT_EQUAL);
		} else {
			return ($this->token = self::TOK_NOT);
		}
	}

	/**
	 * Parse several compare characters
	 * @return int
	 */
	protected function parse_compare() {
		if ($this->doc[$this->pos + 1] === '=') {
			switch($this->doc[$this->pos++]) {
				case '$':
					return ($this->token = self::TOK_COMPARE_ENDS);
				case '%':
					return ($this->token = self::TOK_COMPARE_REGEX);
				case '^':
					return ($this->token = self::TOK_COMPARE_STARTS);
				case '<':
					return ($this->token = self::TOK_COMPARE_SMALLER_THAN);
			}
		}
		return false;
	}

	/**
	 * Parse strings (" and ')
	 * @return int
	 */
	protected function parse_string() {
		$char = $this->doc[$this->pos];

		while (true) {
			if ($this->next_search($char.'\\', false) !== self::TOK_NULL) {
				if($this->doc[$this->pos] === $char) {
					break;
				} else {
					++$this->pos;
				}
			} else {
				$this->pos = $this->size - 1;
				break;
			}
		}

		return ($this->token = self::TOK_STRING);
	}

}

/**
 * Performs a css select query on HTML nodes
 */
class HTML_Selector {

	/**
	 * Parser object
	 * @internal If string, then it will create a new instance as parser
	 * @var Tokenizer_CSSQuery
	 */
	var $parser = 'Tokenizer_CSSQuery';

	/**
	 * Target of queries
	 * @var HTML_Node
	 */
	var $root = null;

	/**
	 * Last performed query, result in (@link $result)
	 * @var string
	 */
	var $query = '';

	/**
	 * Array of matching nodes
	 * @var array
	 */
	var $result = array();

	/**
	 * Include root in search, if false the only child nodes are evaluated
	 * @var bool
	 */
	var $search_root = false;

	/**
	 * Search recursively
	 * @var bool
	 */
	var $search_recursive = true;

	/**
	 * Extra function map for custom filters
	 * @var array
	 * @internal array('root' => 'filter_root') will cause the
	 * selector to call $this->filter_root at :root
	 * @see HTML_Node::$filter_map
	 */
	var $custom_filter_map = array();

	/**
	 * Class constructor
	 * @param HTML_Node $root (@link $root)
	 * @param string $query
	 * @param bool $search_root (@link $search_root)
	 * @param bool $search_recursive (@link $search_recursive)
	 * @param Tokenizer_CSSQuery $parser If null, then default class will be used
	 */
	function __construct($root, $query = '*', $search_root = false, $search_recursive = true, $parser = null) {
		if ($parser === null) {
			$parser = new $this->parser();
		}
		$this->parser = $parser;
		$this->root =& $root;

		$this->search_root = $search_root;
		$this->search_recursive = $search_recursive;

		$this->select($query);
	}

	/**
	 * toString method, returns (@link $query)
	 * @return string
	 * @access private
	 */
	function __toString() {
		return $this->query;
	}

	/**
	 * Class magic invoke method, performs {@link select()}
	 * @return array
	 * @access private
	 */
	function __invoke($query = '*') {
		return $this->select($query);
	}

	/**
	 * Perform query
	 * @param string $query
	 * @return array False on failure
	 */
	function select($query = '*') {
		$this->parser->setDoc($query);
		$this->query = $query;
		return (($this->parse()) ? $this->result : false);
	}

	/**
	 * Trigger error
	 * @param string $error
	 * @internal %pos% and %tok% will be replace in string with position and token(string)
	 * @access private
	 */
	protected function error($error) {
		$error = htmlentities(str_replace(
			array('%tok%', '%pos%'),
			array($this->parser->getTokenString(), (int) $this->parser->getPos()),
			$error
		));

		trigger_error($error);
	}

	/**
	 * Get identifier (parse identifier or string)
	 * @param bool $do_error Error on failure
	 * @return string False on failure
	 * @access private
	 */
	protected function parse_getIdentifier($do_error = true) {
		$p =& $this->parser;
		$tok = $p->token;

		if ($tok === $p::TOK_IDENTIFIER) {
			return $p->getTokenString();
		} elseif($tok === $p::TOK_STRING) {
			return str_replace(array('\\\'', '\\"', '\\\\'), array('\'', '"', '\\'), $p->getTokenString(1, -1));
		} elseif ($do_error) {
			$this->error('Expected identifier at %pos%!');
		}
		return false;
	}

	/**
	 * Get query conditions (tag, attribute and filter conditions)
	 * @return array False on failure
	 * @see HTML_Node::match()
	 * @access private
	 */
	protected function parse_conditions() {
		$p =& $this->parser;
		$tok = $p->token;

		if ($tok === $p::TOK_NULL) {
			$this->error('Invalid search pattern(1): Empty string!');
			return false;
		}
		$conditions_all = array();

		//Tags
		while ($tok !== $p::TOK_NULL) {
			$conditions = array('tags' => array(), 'attributes' => array());

			if ($tok === $p::TOK_ALL) {
				$tok = $p->next();
				if (($tok === $p::TOK_PIPE) && ($tok = $p->next()) && ($tok !== $p::TOK_ALL)) {
					if (($tag = $this->parse_getIdentifier()) === false) {
						return false;
					}
					$conditions['tags'][] = array(
						'tag' => $tag,
						'compare' => 'name'
					);
					$tok = $p->next_no_whitespace();
				} else {
					$conditions['tags'][''] = array(
						'tag' => '',
						'match' => false
					);
					if ($tok === $p::TOK_ALL) {
						$tok = $p->next_no_whitespace();
					}
				}
			} elseif ($tok === $p::TOK_PIPE) {
				$tok = $p->next();
				if ($tok === $p::TOK_ALL) {
					$conditions['tags'][] = array(
						'tag' => '',
						'compare' => 'namespace',
					);
				} elseif (($tag = $this->parse_getIdentifier()) !== false) {
					$conditions['tags'][] = array(
						'tag' => $tag,
						'compare' => 'total',
					);
				} else {
					return false;
				}
				$tok = $p->next_no_whitespace();
			} elseif ($tok === $p::TOK_BRACE_OPEN) {
				$tok = $p->next_no_whitespace();
				$last_mode = 'or';

				while (true) {
					$match = true;
					$compare = 'total';

					if ($tok === $p::TOK_NOT) {
						$match = false;
						$tok = $p->next_no_whitespace();
					}

					if ($tok === $p::TOK_ALL) {
						$tok = $p->next();
						if ($tok === $p::TOK_PIPE) {
							$this->next();
							$compare = 'name';
							if (($tag = $this->parse_getIdentifier()) === false) {
								return false;
							}
						}
					} elseif ($tok === $p::TOK_PIPE) {
						$tok = $p->next();
						if ($tok === $p::TOK_ALL) {
							$tag = '';
							$compare = 'namespace';
						} elseif (($tag = $this->parse_getIdentifier()) === false) {
							return false;
						}
						$tok = $p->next_no_whitespace();
					} else {
						if (($tag = $this->parse_getIdentifier()) === false) {
							return false;
						}
						$tok = $p->next();
						if ($tok === $p::TOK_PIPE) {
							$tok = $p->next();

							if ($tok === $p::TOK_ALL) {
								$compare = 'namespace';
							} elseif (($tag_name = $this->parse_getIdentifier()) !== false) {
								$tag = $tag.':'.$tag_name;
							} else {
								return false;
							}

							$tok = $p->next_no_whitespace();
						}
					}
					if ($tok === $p::TOK_WHITESPACE) {
						$tok = $p->next_no_whitespace();
					}

					$conditions['tags'][] = array(
						'tag' => $tag,
						'match' => $match,
						'operator' => $last_mode,
						'compare' => $compare
					);
					switch($tok) {
						case $p::TOK_COMMA:
							$tok = $p->next_no_whitespace();
							$last_mode = 'or';
							continue 2;
						case $p::TOK_PLUS:
							$tok = $p->next_no_whitespace();
							$last_mode = 'and';
							continue 2;
						case $p::TOK_BRACE_CLOSE:
							$tok = $p->next();
							break 2;
						default:
							$this->error('Expected closing brace or comma at pos %pos%!');
							return false;
					}
				}
			} elseif (($tag = $this->parse_getIdentifier(false)) !== false) {
				$tok = $p->next();
				if ($tok === $p::TOK_PIPE) {
					$tok = $p->next();

					if ($tok === $p::TOK_ALL) {
						$conditions['tags'][] = array(
							'tag' => $tag,
							'compare' => 'namespace'
						);
					} elseif (($tag_name = $this->parse_getIdentifier()) !== false) {
						$tag = $tag.':'.$tag_name;
						$conditions['tags'][] = array(
							'tag' => $tag,
							'match' => true
						);
					} else {
						return false;
					}

					$tok = $p->next();
				} else {
					$conditions['tags'][] = array(
						'tag' => $tag,
						'match' => true
					);
				}
			} else {
				unset($conditions['tags']);
			}

			//Class
			$last_mode = 'or';
			if ($tok === $p::TOK_CLASS) {
				$p->next();
				if (($class = $this->parse_getIdentifier()) === false) {
					return false;
				}

				$conditions['attributes'][] = array(
					'attribute' => 'class',
					'operator_value' => 'equals',
					'value' => $class,
					'operator_result' => $last_mode
				);
				$last_mode = 'and';
				$tok = $p->next();
			}

			//ID
			if ($tok === $p::TOK_ID) {
				$p->next();
				if (($id = $this->parse_getIdentifier()) === false) {
					return false;
				}

				$conditions['attributes'][] = array(
					'attribute' => 'id',
					'operator_value' => 'equals',
					'value' => $id,
					'operator_result' => $last_mode
				);
				$last_mode = 'and';
				$tok = $p->next();
			}

			//Attributes
			if ($tok === $p::TOK_BRACKET_OPEN) {
				$tok = $p->next_no_whitespace();

				while (true) {
					$match = true;
					$compare = 'total';
					if ($tok === $p::TOK_NOT) {
						$match = false;
						$tok = $p->next_no_whitespace();
					}

					if ($tok === $p::TOK_ALL) {
						$tok = $p->next();
						if ($tok === $p::TOK_PIPE) {
							$tok = $p->next();
							if (($attribute = $this->parse_getIdentifier()) === false) {
								return false;
							}
							$compare = 'name';
							$tok = $p->next();
						} else {
							$this->error('Expected pipe at pos %pos%!');
							return false;
						}
					} elseif ($tok === $p::TOK_PIPE) {
						$tok = $p->next();
						if (($tag = $this->parse_getIdentifier()) === false) {
							return false;
						}
						$tok = $p->next_no_whitespace();
					} elseif (($attribute = $this->parse_getIdentifier()) !== false) {
						$tok = $p->next();
						if ($tok === $p::TOK_PIPE) {
							$tok = $p->next();

							if (($attribute_name = $this->parse_getIdentifier()) !== false) {
								$attribute = $attribute.':'.$attribute_name;
							} else {
								return false;
							}

							$tok = $p->next();
						}
					} else {
						return false;
					}
					if ($tok === $p::TOK_WHITESPACE) {
						$tok = $p->next_no_whitespace();
					}

					$operator_value = '';
					$val = '';
					switch($tok) {
						case $p::TOK_COMPARE_PREFIX:
						case $p::TOK_COMPARE_CONTAINS:
						case $p::TOK_COMPARE_CONTAINS_WORD:
						case $p::TOK_COMPARE_ENDS:
						case $p::TOK_COMPARE_EQUALS:
						case $p::TOK_COMPARE_NOT_EQUAL:
						case $p::TOK_COMPARE_REGEX:
						case $p::TOK_COMPARE_STARTS:
						case $p::TOK_COMPARE_BIGGER_THAN:
						case $p::TOK_COMPARE_SMALLER_THAN:
							$operator_value = $p->getTokenString(($tok === $p::TOK_COMPARE_EQUALS) ? 0 : -1);
							$p->next_no_whitespace();

							if (($val = $this->parse_getIdentifier()) === false) {
								return false;
							}

							$tok = $p->next_no_whitespace();
							break;
					}

					if ($operator_value && $val) {
						$conditions['attributes'][] = array(
							'attribute' => $attribute,
							'operator_value' => $operator_value,
							'value' => $val,
							'match' => $match,
							'operator_result' => $last_mode,
							'compare' => $compare
						);
					} else {
						$conditions['attributes'][] = array(
							'attribute' => $attribute,
							'value' => $match,
							'operator_result' => $last_mode,
							'compare' => $compare
						);
					}

					switch($tok) {
						case $p::TOK_COMMA:
							$tok = $p->next_no_whitespace();
							$last_mode = 'or';
							continue 2;
						case $p::TOK_PLUS:
							$tok = $p->next_no_whitespace();
							$last_mode = 'and';
							continue 2;
						case $p::TOK_BRACKET_CLOSE:
							$tok = $p->next();
							break 2;
						default:
							$this->error('Expected closing bracket or comma at pos %pos%!');
							return false;
					}
				}
			}

			if (count($conditions['attributes']) < 1) {
				unset($conditions['attributes']);
			}

			while($tok === $p::TOK_COLON) {
				if (count($conditions) < 1) {
					$conditions['tags'] = array(array(
						'tag' => '',
						'match' => false
					));
				}

				$tok = $p->next();
				if (($filter = $this->parse_getIdentifier()) === false) {
					return false;
				}

				if (($tok = $p->next()) === $p::TOK_BRACE_OPEN) {
					$start = $p->pos;
					$count = 1;
					while ((($tok = $p->next()) !== $p::TOK_NULL) && !(($tok === $p::TOK_BRACE_CLOSE) && (--$count === 0))) {
						if ($tok === $p::TOK_BRACE_OPEN) {
							++$count;
						}
					}


					if ($tok !== $p::TOK_BRACE_CLOSE) {
						$this->error('Expected closing brace at pos %pos%!');
						return false;
					}
					$len = $p->pos - 1 - $start;
					$params = (($len > 0) ? substr($p->doc, $start + 1, $len) : '');
					$tok = $p->next();
				} else {
					$params = '';
				}

				$conditions['filters'][] = array('filter' => $filter, 'params' => $params);
			}
			if (count($conditions) < 1) {
				$this->error('Invalid search pattern(2): No conditions found!');
				return false;
			}
			$conditions_all[] = $conditions;

			if ($tok === $p::TOK_WHITESPACE) {
				$tok = $p->next_no_whitespace();
			}

			if ($tok === $p::TOK_COMMA) {
				$tok = $p->next_no_whitespace();
				continue;
			} else {
				break;
			}
		}

		return $conditions_all;
	}


	/**
	 * Evaluate root node using custom callback
	 * @param array $conditions {@link parse_conditions()}
	 * @param bool|int $recursive
	 * @param bool $check_root
	 * @return array
	 * @access private
	 */
	protected function parse_callback($conditions, $recursive = true, $check_root = false) {
		$c = var_export($conditions, true);
		$f = var_export($this->custom_filter_map, true);
		$func =
<<<func
	static \$conditions = $c;
	static \$filter_map = $f;
	return (\$e->match(\$conditions, true, \$filter_map));
func;
//'return ($e->match(unserialize(\''.serialize($conditions).'\'), true, unserialize(\''.serialize($this->custom_filter_map).'\')));'),
		return ($this->result = $this->root->getChildrenByCallback(
			create_function('$e', $func),
			$recursive,
			$check_root
		));
	}

	/**
	 * Parse first bit of query, only root node has to be evaluated now
	 * @param bool|int $recursive
	 * @return bool
	 * @internal Result of query is set in (@link $result)
	 * @access private
	 */
	protected function parse_single($recursive = true) {
		if (($c = $this->parse_conditions()) === false) {
			return false;
		}

		$this->parse_callback($c, $recursive, $this->search_root);
		return true;
	}

	/**
	 * Evaluate sibling nodes
	 * @return bool
	 * @internal Result of query is set in (@link $result)
	 * @access private
	 */
	protected function parse_adjacent() {
		$tmp = $this->result;
		$this->result = array();
		if (($c = $this->parse_conditions()) === false) {
			return false;
		}

		foreach($tmp as $t) {
			if (($sibling = $t->getNextSibling()) !== false) {
				if ($sibling->match($c, true, $this->custom_filter_map)) {
					$this->result[] = $sibling;
				}
			}
		}

		return true;
	}

	/**
	 * Evaluate (@link $result)
	 * @param bool $parent Evaluate parent nodes
	 * @param bool|int $recursive
	 * @return bool
	 * @internal Result of query is set in (@link $result)
	 * @access private
	 */
	protected function parse_result($parent = false, $recursive = true) {
		$tmp = $this->result;
		$tmp_res = array();
		if (($c = $this->parse_conditions()) === false) {
			return false;
		}

		foreach($tmp as &$t) {
			$this->root = (($parent) ? $t->parent : $t);
			$this->parse_callback($c, $recursive);
			foreach($this->result as &$r) {
				if (!in_array($r, $tmp_res, true)) {
					$tmp_res[] = $r;
				}
			}
		}
		$this->result = $tmp_res;
		return true;
	}

	/**
	 * Parse full query
	 * @return bool
	 * @internal Result of query is set in (@link $result)
	 * @access private
	 */
	protected function parse() {
		$p =& $this->parser;
		$p->setPos(0);
		$this->result = array();

		if (!$this->parse_single()) {
			return false;
		}

		while (count($this->result) > 0) {
			switch($p->token) {
				case $p::TOK_CHILD:
					$this->parser->next_no_whitespace();
					if (!$this->parse_result(false, 1)) {
						return false;
					}
					break;

				case $p::TOK_SIBLING:
					$this->parser->next_no_whitespace();
					if (!$this->parse_result(true, 1)) {
						return false;
					}
					break;

				case $p::TOK_PLUS:
					$this->parser->next_no_whitespace();
					if (!$this->parse_adjacent()) {
						return false;
					}
					break;

				case $p::TOK_ALL:
				case $p::TOK_IDENTIFIER:
				case $p::TOK_STRING:
				case $p::TOK_BRACE_OPEN:
				case $p::TOK_BRACKET_OPEN:
				case $p::TOK_ID:
				case $p::TOK_CLASS:
				case $p::TOK_COLON:
					if (!$this->parse_result()) {
						return false;
					}
					break;

				case $p::TOK_NULL:
					break 2;

				default:
					$this->error('Invalid search pattern(3): No result modifier found!');
					return false;
			}
		}

		return true;
	}
}

/**
 * Compress all whitespace in string (to a single space)
 * @param string $text
 * @return string
 */
function compress_whitespace($text) {
	return preg_replace('`\s+`', ' ', $text);
}

/**
 * Indents text
 * @param string $text
 * @param int $indent
 * @param string $indent_string
 * @return string
 */
function indent_text($text, $indent, $indent_string = '  ') {
	if ($indent && $indent_string) {
		return str_replace("\n", "\n".str_repeat($indent_string, $indent), $text);
	} else {
		return $text;
	}
}

/**
 * Class used to format/minify HTML nodes
 *
 * Used like:
 * <code>
 * <?php
 *   $formatter = new HTML_Formatter();
 *   $formatter->format($root);
 * ?>
 * </code>
 */
class HTML_Formatter {

	/**
	 * Determines which elements start on a new line and which function as block
	 * @var array('element' => array('new_line' => true, 'as_block' => true, 'format_inside' => true))
	 */
	var $block_elements = array(
		'p' =>			array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'h1' => 		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'h2' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'h3' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'h4' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'h5' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'h6' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),

		'form' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'fieldset' =>  	array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'legend' =>  	array('new_line' => true,  'as_block' => false, 'format_inside' => true),
		'dl' =>  		array('new_line' => true,  'as_block' => false, 'format_inside' => true),
		'dt' =>  		array('new_line' => true,  'as_block' => false, 'format_inside' => true),
		'dd' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'ol' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'ul' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'li' =>  		array('new_line' => true,  'as_block' => false, 'format_inside' => true),

		'table' =>  	array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'tr' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),

		'dir' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'menu' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'address' =>  	array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'blockquote' => array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'center' =>  	array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'del' =>  		array('new_line' => true,  'as_block' => false, 'format_inside' => true),
		//'div' =>  	array('new_line' => false, 'as_block' => true,  'format_inside' => true),
		'hr' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'ins' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'noscript' =>  	array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'pre' =>  		array('new_line' => true,  'as_block' => true,  'format_inside' => false),
		'script' =>  	array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'style' =>  	array('new_line' => true,  'as_block' => true,  'format_inside' => true),

		'html' => 		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'head' => 		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'body' => 		array('new_line' => true,  'as_block' => true,  'format_inside' => true),
		'title' => 		array('new_line' => true,  'as_block' => false, 'format_inside' => false)
	);

	/**
	 * Determines which characters are considered whitespace
	 * @var array("\t" => true) True to recognize as new line
	 */
	var $whitespace = array(
		' ' => false,
		"\t" => false,
		"\x0B" => false,
		"\0" => false,
		"\n" => true,
		"\r" => true
	);

	/**
	 * String that is used to generate correct indenting
	 * @var string
	 */
	var $indent_string = ' ';

	/**
	 * String that is used to break lines
	 * @var string
	 */
	var $linebreak_string = "\n";

	/**
	 * Other formatting options
	 * @var array
	 */
	var $options = array(
		'img_alt' => '',
		'self_close_str' => null,
		'attribute_shorttag' => false,
		'sort_attributes' => false,
		'attributes_case' => CASE_LOWER,
		'minify_script' => true
	);

	/**
	 * Errors found during formatting
	 * @var array
	 */
	var $errors = array();


	/**
	 * Class constructor
	 * @param array $options {@link $options}
	 */
	function __construct($options = array()) {
		$this->options = array_merge($this->options, $options);
	}

	/**
	 * Class magic invoke method, performs {@link format()}
	 * @access private
	 */
	function __invoke(&$node) {
		return $this->format($node);
	}

	/**
	 * Minifies HTML / removes unneeded whitespace
	 * @param HTML_Node $root
	 * @param bool $strip_comments
	 * @param bool $recursive
	 */
	static function minify_html(&$root, $strip_comments = true, $recursive = true) {
		if ($strip_comments) {
			foreach($root->select(':comment', false, $recursive, true) as $c) {
				$prev = $c->getSibling(-1);
				$next = $c->getSibling(1);
				$c->delete();
				if ($prev && $next && ($prev::NODE_TYPE === $root::NODE_TEXT) && ($next::NODE_TYPE === $root::NODE_TEXT)) {
					$prev->text .= $next->text;
					$next->delete();
				}
			}
		}
		foreach($root->select('(!pre + !xmp + !style + !script + !"?php" + !"~text~" + !"~comment~"):not-empty > "~text~"', false, $recursive, true) as $c) {
			$c->text = compress_whitespace($c->text);
		}
	}

	/**
	 * Minifies javascript using JSMin+
	 * @param HTML_Node $root
	 * @param string $indent_string
	 * @param bool $wrap_comment Wrap javascript in HTML comments (<!-- ~text~ //-->)
	 * @param bool $recursive
	 * @return bool|array Array of errors on failure, true on succes
	 */
	static function minify_javascript(&$root, $indent_string = ' ', $wrap_comment = true, $recursive = true) {
		include_once('jsminplus.php');

		$errors = array();
		foreach($root->select('script:not-empty > "~text~"', false, $recursive, true) as $c) {
			try {
				$text = $c->text;
				while ($text) {
					$text = trim($text);
					//Remove comment/CDATA tags at begin and end
					if (substr($text, 0, 4) === '<!--') {
						$text = substr($text, 5);
						continue;
					} elseif (strtolower(substr($text, 0, 9)) === '<![cdata[') {
						$text = substr($text, 10);
						continue;
					}

					if (($end = substr($text, -3)) && (($end === '-->') || ($end === ']]>'))) {
						$text = substr($text, 0, -3);
						continue;
					}

					break;
				}

				if (trim($text)) {
					$text = JSMinPlus::minify($text);
					if ($wrap_comment) {
						$text = "<!--\n".$text."\n//-->";
					}
					if ($indent_string && ($wrap_comment || (strpos($text, "\n") !== false))) {
						$text = indent_text("\n".$text, $c->indent(), $indent_string);
					}
				}
				$c->text = $text;
			} catch (Exception $e) {
				$errors[] = array($e, $c->parent->dumpLocation());
			}
		}

		return (($errors) ? $errors : true);
	}

	/**
	 * Formats HTML
	 * @param HTML_Node $root
	 * @param bool $recursive
	 * @access private
	 */
	function format_html(&$root, $recursive = null) {
		if ($recursive === null) {
			$recursive = true;
			self::minify_html($root);
		} elseif (is_int($recursive)) {
			$recursive = (($recursive > 1) ? $recursive - 1 : false);
		}

		$root_tag = strtolower($root->tag);
		$in_block = isset($this->block_elements[$root_tag]) && $this->block_elements[$root_tag]['as_block'];
		$child_count = count($root->children);

		if (isset($this->options['sort_attributes']) && $this->options['sort_attributes']) {
			if ($this->options['sort_attributes'] === 'reverse') {
				krsort($root->attributes);
			} else {
				ksort($root->attributes);
			}
		}
		if (isset($this->options['attributes_case']) && $this->options['attributes_case']) {
			$root->attributes = array_change_key_case($root->attributes, $this->options['attributes_case']);
			$root->attributes_ns = null;
		}

		if ($root::NODE_TYPE === $root::NODE_ELEMENT) {
			$root->setTag(strtolower($root->tag), true);
			if (($this->options['img_alt'] !== null) && ($root_tag === 'img') && (!isset($root->alt))) {
				$root->alt = $this->options['img_alt'];
			}
		}
		if ($this->options['self_close_str'] !== null) {
			$root->self_close_str = $this->options['self_close_str'];
		}
		if ($this->options['attribute_shorttag'] !== null) {
			$root->attribute_shorttag = $this->options['attribute_shorttag'];
		}

		$prev = null;
		$n_tag = '';
		$prev_tag = '';
		$as_block = false;
		$prev_asblock = false;
		for($i = 0; $i < $child_count; $i++) {
			$n =& $root->children[$i];
			$indent = $n->indent();

			if ($n::NODE_TYPE !== $root::NODE_TEXT) {
				$n_tag = strtolower($n->tag);
				$new_line = isset($this->block_elements[$n_tag]) && $this->block_elements[$n_tag]['new_line'];
				$as_block = isset($this->block_elements[$n_tag]) && $this->block_elements[$n_tag]['as_block'];
				$format_inside = ((!isset($this->block_elements[$n_tag])) || $this->block_elements[$n_tag]['format_inside']);

				if ($prev && ($prev::NODE_TYPE === $root::NODE_TEXT) && $prev->text && ($char = $prev->text[strlen($prev->text) - 1]) && isset($this->whitespace[$char])) {
					if ($this->whitespace[$char]) {
						$prev->text .= str_repeat($this->indent_string, $indent);
					} else {
						$prev->text = substr_replace($prev->text, $this->linebreak_string.str_repeat($this->indent_string, $indent), -1, 1);
					}
				} elseif (($new_line || $prev_asblock || ($in_block && ($i === 0)))){
					if ($prev && ($prev::NODE_TYPE === $root::NODE_TEXT)) {
						$prev->text .= $this->linebreak_string.str_repeat($this->indent_string, $indent);
					} else {
						$root->addText($this->linebreak_string.str_repeat($this->indent_string, $indent), $i);
						++$child_count;
					}
				}

				if ($format_inside) {
					$last = end($n->children);
					$last_tag = ($last) ? strtolower($last->tag) : '';
					$last_asblock = ($last_tag && isset($this->block_elements[$last_tag]) && $this->block_elements[$last_tag]['as_block']);

					if (($n->childCount(true) > 0) || (trim($n->getPlainText()))) {
						if ($last && ($last::NODE_TYPE === $root::NODE_TEXT) && $last->text && ($char = $last->text[strlen($last->text) - 1]) && isset($this->whitespace[$char])) {
							if ($as_block || ($last->index() > 0) || isset($this->whitespace[$last->text[0]])) {
								if ($this->whitespace[$char]) {
									$last->text .= str_repeat($this->indent_string, $indent);
								} else {
									$last->text = substr_replace($last->text, $this->linebreak_string.str_repeat($this->indent_string, $indent), -1, 1);
								}
							}
						} elseif (($as_block || $last_asblock || ($in_block && ($i === 0))) && $last) {
							if ($last && ($last::NODE_TYPE === $root::NODE_TEXT)) {
								$last->text .= $this->linebreak_string.str_repeat($this->indent_string, $indent);
							} else {
								$n->addText($this->linebreak_string.str_repeat($this->indent_string, $indent));
							}
						}
					} elseif (!trim($n->getInnerText())) {
						$n->clear();
					}

					if ($recursive) {
						$this->format_html($n, $recursive);
					}
				}

			} elseif (trim($n->text) && ((($i - 1 < $child_count) && ($char = $n->text[0]) && isset($this->whitespace[$char])) || ($in_block && ($i === 0)))) {
				if (isset($this->whitespace[$char])) {
					if ($this->whitespace[$char]) {
						$n->text = str_repeat($this->indent_string, $indent).$n->text;
					} else {
						$n->text = substr_replace($n->text, $this->linebreak_string.str_repeat($this->indent_string, $indent), 0, 1);
					}
				} else {
					$n->text = $this->linebreak_string.str_repeat($this->indent_string, $indent).$n->text;
				}
			}

			$prev = $n;
			$prev_tag = $n_tag;
			$prev_asblock = $as_block;
		}
	}

	/**
	 * Formats HTML/Javascript
	 * @param HTML_Node $root
	 * @see format_html()
	 */
	function format(&$node) {
		$this->errors = array();
		if ($this->options['minify_script']) {
			$a = self::minify_javascript($node, $this->indent_string, true, true);
			if (is_array($a)) {
				foreach($a as $error) {
					$this->errors[] = $error[0]->getMessage().' >>> '.$error[1];
				}
			}
		}
		return $this->format_html($node);
	}
}

/**
 * Turns template commands into tokens
 */
class Tokenizer_tpl extends Tokenizer_Base {

	/**
	 * Opening bracket token, used for "["
	 */
	const TOK_BRACKET_OPEN = 100;
	/**
	 * Closing bracket token, used for "]"
	 */
	const TOK_BRACKET_CLOSE = 101;
	/**
	 * Opening brace token, used for "("
	 */
	const TOK_BRACE_OPEN = 102;
	/**
	 * Closing brace token, used for ")"
	 */
	const TOK_BRACE_CLOSE = 103;
	/**
	 * Opening brace token, used for "{"
	 */
	const TOK_CURLY_OPEN = 104;
	/**
	 * Closing brace token, used for "}"
	 */
	const TOK_CURLY_CLOSE = 105;	
	/**
	 * String token
	 */
	const TOK_STRING = 106;
	/**
	 * Comma token, used for ","
	 */
	const TOK_COMMA = 107;
	/**
	 * "Not" token, used for "!"
	 */
	const TOK_NOT = 108;
	/**
	 * Question mark token, used for "?"
	 */
	const TOK_QUESTIONMARK = 109;
	/**
	 * Colon token, used for ":"
	 */
	const TOK_COLON = 110;
	/**
	 * Equals token, used for "="
	 */
	const TOK_EQUALS = 111;
	/**
	 * Greater than token, used for ">"
	 */
	const TOK_GREATERTHAN = 112;
	/**
	 * Less than token, used for "<"
	 */
	const TOK_LESSTHAN = 113;
	/**
	 * Plus token, used for "+"
	 */
	const TOK_PLUS = 114;
	/**
	 * Minus token, used for "-"
	 */
	const TOK_MINUS = 115;
	/**
	 * Multiply token, used for "*"
	 */
	const TOK_MULTIPLY = 116;
	/**
	 * Divide token, used for "/"
	 */
	const TOK_DIVIDE = 117;
	/**
	 * "Mod" token, used for "%"
	 */
	const TOK_MOD = 118;
	/**
	 * "And" token, used for "&"
	 */
	const TOK_AND = 119;
	/**
	 * "Or" token, used for "|"
	 */
	const TOK_OR = 120;

	/**
	 * Sets query identifiers
	 * @see Tokenizer_Base::$identifiers
	 * @access private
	 */
	var $identifiers = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890_';

	/**
	 * Map characters to match their tokens
	 * @see Tokenizer_Base::$custom_char_map
	 * @access private
	 */
	var $custom_char_map = array(
		',' => self::TOK_COMMA,
		'!' => self::TOK_NOT,	
		'?' => self::TOK_QUESTIONMARK,
		':' => self::TOK_COLON,
		'=' => self::TOK_EQUALS,
		'>' => self::TOK_GREATERTHAN,
		'<' => self::TOK_LESSTHAN,
		'+' => self::TOK_PLUS,
		'-' => self::TOK_MINUS,
		'*' => self::TOK_MULTIPLY,
		'/' => self::TOK_DIVIDE,
		'%' => self::TOK_MOD,
		'&' => self::TOK_AND,
		'|' => self::TOK_OR,

		'"' => 'parse_string',
		"'" => 'parse_string',
		'(' => self::TOK_BRACE_OPEN,
		')' => self::TOK_BRACE_CLOSE,
		'[' => self::TOK_BRACKET_OPEN,
		']' => self::TOK_BRACKET_CLOSE,
		'{' => self::TOK_CURLY_OPEN,
		'}' => self::TOK_CURLY_CLOSE,
	);

	/**
	 * Parse ">" character
	 * @internal Could be {@link TOK_CHILD} or {@link TOK_COMPARE_BIGGER_THAN}
	 * @return int
	 */
	protected function parse_gt() {
		if ($this->doc[$this->pos + 1] === '=') {
			++$this->pos;
			return ($this->token = self::TOK_COMPARE_BIGGER_THAN);
		} else {
			return ($this->token = self::TOK_CHILD);
		}
	}

	/**
	 * Parse strings (" and ')
	 * @return int
	 */
	protected function parse_string() {
		$char = $this->doc[$this->pos];

		while (true) {
			if ($this->next_search($char.'\\', false) !== self::TOK_NULL) {
				if($this->doc[$this->pos] === $char) {
					break;
				} else {
					++$this->pos;
				}
			} else {
				$this->pos = $this->size - 1;
				break;
			}
		}

		return ($this->token = self::TOK_STRING);
	}

}

/**
 * Extends HTML parser to parse template specific tags
 */
class Template_Parser extends HTML_Parser_HTML5 {

	var $tpl_char_open = '{';
	var $tpl_char_close = '}';
	var $tpl_namespace = 'tpl';
	var $tpl_function_map = array(
		'block' => 'parse_tpl_block',
		'define_macro' => 'parse_tpl_definemacro',
		'else' => 'parse_tpl_else',
		'elseif' => 'parse_tpl_elseif',
		'if' => 'parse_tpl_if',
		'loop' => 'parse_tpl_loop',
		'macro' => 'parse_tpl_macro',
		'query' => 'parse_tpl_query',
		'setting' => 'parse_tpl_setting'
	);

	function expect_end($do_next = false) {
		if ($this->tpl_char_close !== null) {
			$this->expect($this->tpl_char_close, $do_next);
			$do_next = false;
		}
		return $this->expect(self::TOK_TAG_CLOSE, $do_next);
	}

	function parse_tpl_block() {
		if (!$this->status['closing_tag']) {
			if (!$this->expect(self::TOK_IDENTIFIER, true, false, false)) {
				return false;
			}
			$name = $this->getTokenString();
		}

		return $this->expect_end(true);
	}
	
	function parse_tpl_setting() {
		if (!($this->parse_attributes() && $this->status['attributes'])) {
			return false;
		}

		return $this->expect_end();
	}

	function parse_tag() {
		$start = $this->pos;
		if (($this->tpl_char_open === null) || ($this->doc[$this->pos + 1] === $this->tpl_char_open)) {
			++$this->pos;
			if ($this->doc[$this->pos + 1] === '/') {
				$this->status['closing_tag'] = true;
				++$this->pos;
			} else {
				$this->status['closing_tag'] = false;
			}

			if ($this->next() !== self::TOK_IDENTIFIER) {
				$this->addError('Tagname expected');
				$this->status['last_pos'] = $start - 1;
				return ($this->tpl_char_open === null);
			}

			$tag = $this->getTokenString();
			$this->status['tag_name'] = (($this->tpl_namespace) ? $this->tpl_namespace.':' : '').$tag;
			$tag = strtolower($tag);

			if (isset($this->tpl_function_map[$tag])) {
				return $this->{$this->tpl_function_map[$tag]}();
			} elseif ($this->tpl_char_open !== null) {
				$this->addError('Unkown template tag "'.$tag.'"');
				return false;
			} else {
				$this->status['last_pos'] = $start - 1;
			}
		}

		return parent::parse_tag();
	}

}

/**
 * Returns HTML DOM from string
 * @param string $str
 * @param bool $return_root Return root node or return parser object
 * @return HTML_Parser_HTML5|HTML_Node
 */
function str_get_html($str, $return_root = true) {
	$a = new HTML_Parser_HTML5($str);
	return (($return_root) ? $a->root : $a);
}

/**
 * Returns HTML DOM from file/website
 * @param string $str
 * @param bool $return_root Return root node or return parser object
 * @return HTML_Parser_HTML5|HTML_Node
 */
function file_get_html($file, $return_root = true) {
	return str_get_html(file_get_contents($file), $return_root);
}

?>