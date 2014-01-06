<?php
/**
 * Converts the BBC news feed to an array
 *
 * Demonstrates the XML2ArrayParser class
 *
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

require_once __DIR__.'/../vendor/autoload.php';

$html = new pQuery\XML2ArrayParser(file_get_contents('http://newsrss.bbc.co.uk/rss/newsonline_world_edition/front_page/rss.xml'));
$html = $html->root;
var_dump($html);


?>