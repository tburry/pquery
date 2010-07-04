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
$html = file_get_dom('http://www.cnn.com/');

foreach($html('div:has(h4) (li, h4)') as $element) {

	if ($element->tag === 'h4') {
		echo '<b>', $element->getPlainText(), '</b>';
	} else {
		echo $element->getPlainText();
	}
	
	echo "<br>\n";
}


?>