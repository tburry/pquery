<?php
/**
 * @author Niels A.D.
 * @package Ganon
 */

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

if (!defined('GANON_NO_INCLUDES')) {
	include_once('gan_tokenizer.php');
	include_once('gan_parser_html.php');
	include_once('gan_node_html.php');
	include_once('gan_selector_html.php');
	include_once('gan_formatter.php');
}

?>