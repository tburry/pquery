<?php
/**
 * Should output all CNN Headlines
 *
 * Demonstrates advanced selectors
 *
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

include_once('../ganon.php');
//PHP4 users, make sure this path is correct!

$html = file_get_dom('http://www.cnn.com/');

if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
	//PHP 5.3.0 and higher

	foreach($html('div:has(h4) (li, h4)') as $element) {

		if ($element->tag === 'h4') {
			echo '<b>', $element->getPlainText(), '</b>';
		} else {
			echo $element->getPlainText();
		}
		echo "<br>\n";
	}

} else {
	//PHP 4 and 5.3.0 and lower

	foreach($html->select('div:has(h4) (li, h4)') as $element) {

		if ($element->tag === 'h4') {
			echo '<b>', $element->getPlainText(), '</b>';
		} else {
			echo $element->getPlainText();
		}
		echo "<br>\n";
	}
	
}


?>