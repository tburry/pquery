<?php
/**
 * Should output a string with parsed unicode characters
 *
 * Demonstrates UTF8
 *
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

include_once('../ganon.php');
//PHP4 users, make sure this path is correct!

header( 'Content-Type: text/html; charset=UTF-8' );
//Make sure the header is set for UTF8 output

$html = file_get_dom('_html5_utf.html');

if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
	//PHP 5.3.0 and higher

	foreach($html('(title, h1)') as $element) {
		echo $element->getPlainText(), "<br>\n";
	}

} else {
	//PHP 4 and 5.3.0 and lower

	foreach($html->select('(title, h1)') as $element) {
		echo $element->getPlainText(), "<br>\n";
	}
	
}


?>