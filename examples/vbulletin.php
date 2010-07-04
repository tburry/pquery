<?php
/**
 * Should output all sections from the SRL forums (http://villavu.com/forum/)
 *
 * Demonstrates selectors
 *
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

include_once('../ganon.php');
$html = file_get_dom('http://villavu.com/forum/');

foreach($html('a[href ^= forumdisplay] > strong') as $element) {
	echo $element->getPlainText(), "<br>\n";
}


?>