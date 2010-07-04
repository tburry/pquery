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
$html = file_get_dom('http://www.youtube.com/videos');

echo $html('a[href ^= "/watch"]:has(img)', 0)->toString();


?>