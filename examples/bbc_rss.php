<?php
/**
 * Parses the BBC news feed
 *
 * Demonstrates selectors
 *
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

include_once('../ganon.php');
$html = file_get_dom('http://newsrss.bbc.co.uk/rss/newsonline_world_edition/front_page/rss.xml');

echo 'Last updated: ', $html('lastBuildDate', 0)->getPlainText(), "<br><br>\n";

foreach($html('item') as $item) {
	echo 'Title: ', $item('title', 0)->getPlainText(), "<br>\n";
	echo 'Date: ', $item('pubDate', 0)->getPlainText(), "<br>\n";
	echo 'Link: ', $item('link', 0)->getPlainText(), "<br><br>\n";
}

echo 'done';

?>