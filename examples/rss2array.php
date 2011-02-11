<?php
/**
 * Converts the BBC news feed to an array
 *
 * Demonstrates the XML_Parser_Array class
 *
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

include_once('../gan_xml2array.php');
$html = new XML_Parser_Array(file_get_contents('http://newsrss.bbc.co.uk/rss/newsonline_world_edition/front_page/rss.xml'));
$html = $html->root;
var_dump($html);


?>