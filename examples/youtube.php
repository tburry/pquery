<?php
/**
 * Should output the most viewed video of the day on Youtube
 *
 * Demonstrates selectors
 *
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

include_once('../ganon.php');
//PHP4 users, make sure this path is correct!

$html = file_get_dom('http://www.youtube.com/videos');


if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
	//PHP 5.3.0 and higher

	echo $html('a[href ^= "/watch"]:has(img)', 0)->toString();

} else {
	//PHP 4 and 5.3.0 and lower

	echo $html->select('a[href ^= "/watch"]:has(img)', 0)->toString();
	
}


?>