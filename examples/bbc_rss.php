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
//PHP4 users, make sure this path is correct!

$html = file_get_dom('http://newsrss.bbc.co.uk/rss/newsonline_world_edition/front_page/rss.xml');


if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
	//PHP 5.3.0 and higher

	echo 'Last updated: ', $html('lastBuildDate', 0)->getPlainText(), "<br><br>\n";

	foreach($html('item') as $item) {
		echo 'Title: ', $item('title', 0)->getPlainText(), "<br>\n";
		echo 'Date: ', $item('pubDate', 0)->getPlainText(), "<br>\n";
		echo 'Link: ', $item('link', 0)->getPlainText(), "<br><br>\n";
	}

} else {
	//PHP 4 and 5.3.0 and lower

	echo 'Last updated: ', $html->select('lastBuildDate', 0)->getPlainText(), "<br><br>\n";

	foreach($html->select('item') as $item) {
		echo 'Title: ', $item->select('title', 0)->getPlainText(), "<br>\n";
		echo 'Date: ', $item->select('pubDate', 0)->getPlainText(), "<br>\n";
		echo 'Link: ', $item->select('link', 0)->getPlainText(), "<br><br>\n";
	}
	
}

echo 'done';

?>