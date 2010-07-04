<?php
/**
 * Should output all Wiki articles and their information from the Ganon page
 * at Google Code (http://code.google.com/p/ganon/w/list)
 *
 * Demonstrates (advanced) selectors and nested queries
 *
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

include_once('../ganon.php');
$html = file_get_dom('http://code.google.com/p/ganon/w/list');

foreach($html('#resultstable tr[! id=headingrow]') as $row) {
	foreach($row('td[class ^= "vt "]') as $col) {
		echo $col->getPlainText(), ' [', $col, "] <br>\n";
	}
	echo "<br>\n";
}
 
echo 'done';


?>