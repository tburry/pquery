<?php
/**
 * Small script to help convert Ganon to a single file and adjust for PHP4
 *
 * Strips comments, converts class constants to global constants (php4), converts static variables
 * to regular variables (php4) and supports conditional comments:
 * #php4 ... #php4e  ->  For php 4 only
 * #php5 ... #php5e  ->  For php 5 only
 * #!!   ... #!      ->  Ignore
 *
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 * @license http://dev.perl.org/licenses/artistic.html Artistic License
 */

if (!function_exists('file_put_contents')) {
    function file_put_contents($filename, $data) {
        $f = @fopen($filename, 'w');
        if (!$f) {
            return false;
        } else {
            $bytes = fwrite($f, $data);
            fclose($f);
            return $bytes;
        }
    }
}
 
if (isset($_GET['v'])) {

	$php4 = ($_GET['v'] == 4);
	if ($php4) {
		$file_tpl = 'ganon_tpl.php4';
		$file_out = '../../tags/php4/ganon.php4';
	} else {
		$file_tpl = 'ganon_tpl.php5';
		$file_out = '../../tags/php5/ganon.php';
	}

	$constants = array();
	$statics = array();

	function parse_file($file) {
		global $constants, $statics, $php4, $file_tpl, $file_out;
		
		$source = file_get_contents($file) or die("Can't open file: $file");
		$tokens = token_get_all($source);
		$output = '//START '.basename($file)."\n";
		$do_output = true;
		
		for($i = 0; $count = count($tokens), $i < $count; $i++) {
			$token = $tokens[$i];
			if (is_string($token)) {
				if ($do_output) {
					$output .= $token;
				}
			} else {
				switch ($token[0]) {
					case T_OPEN_TAG:
					case T_OPEN_TAG_WITH_ECHO:
					case T_CLOSE_TAG:
						break;
						
					case T_COMMENT:
					case T_DOC_COMMENT:
						if ($token[1][0] === '#') {
							if (($token[1][1] === 'p') && ($token[1][2] === 'h') && ($token[1][3] === 'p')) {
								if (($token[1][4] === '4') === $php4) {
									$do_output = true;//($token[1][5] !== 'e');
								} else {
									$do_output = ($token[1][5] === 'e');
								}
							} elseif ($token[1][1] === '!') {
								$do_output = ($token[1][2] !== '!');
							} elseif($do_output) {
								$new_tokens = token_get_all('<?php '.substr($token[1], 1));
								array_splice($tokens, $i, 1, $new_tokens);
								$i--;
								break;
							}
						}
						$output .= "\n";
						break;

					case T_PRIVATE:
					case T_PROTECTED:
					case T_PUBLIC:
						if (!$do_output) break;

						if ($php4) {
							$tmp_i = $i;
							$i += 2;
							if ($tokens[$i][0] !== T_FUNCTION) {
								$output .= 'var';
							} else {
								$tmp_i++;
							}
							$i = $tmp_i;
							break;
						}
					case T_STATIC:
						if (!$do_output) break;

						if ($php4) {
							$tmp_i = $i;
							$i += 2;

							if ($tokens[$i][0] !== T_FUNCTION) {
								$output .= 'var';
							} else {
								$i += 2;
								$tmp_i++;
							}
							if (is_string($tokens[$i])) {
								$statics[$tokens[$i]] = true;
							} else {
								$statics[$tokens[$i][1]] = true;
							}

							$i = $tmp_i;
							break;
						}
					case T_CONST:
						if (!$do_output) break;

						if ($php4) {
							$i += 2;
							if (is_string($tokens[$i])) {
								$const = $tokens[$i];
							} else {
								$const = $tokens[$i][1];
							}
							$i += 4;
							if (is_string($tokens[$i]) || (($tokens[$i][0] === T_DNUMBER) || ($tokens[$i][0] === T_LNUMBER) || ($tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING))) {
								$constants[$const] = (is_string($tokens[$i])) ? $tokens[$i] : $tokens[$i][1];
							} else {
								die("parsing error 1 in '$file'");
							}

							$i += 2;
							break;
						}
					default:
						if ($do_output) {
							$output .= $token[1];
						}
						break;
				}
			}
		}

		//Remove empty lines and return
		return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $output."\n//END ".basename($file));
	}

	$tpl = file_get_contents($file_tpl);
	$tpl = preg_replace_callback('`\{\{\{(.*)\}\}\}`sU', create_function('$matches', 'return parse_file($matches[1])."\n";'), $tpl);
	$tpl = str_replace('##date##', date('j M Y'), $tpl);
	if ($php4) {
		foreach($statics as $c => $v) {
			if ($c[0] === '$') {
				$c = substr($c, 1);
			}
			$tpl = preg_replace('`\:\:(\$)?'.preg_quote($c).'\b`', '->'.$c, $tpl);
		}

		$s = '';
		foreach($constants as $c => $v) {
			$tpl = preg_replace('`(\$)?\w+'.preg_quote("::$c").'\b`', $c, $tpl);
			$s .= "define('$c', $v);\n";
		}
		$tpl = preg_replace('`\bself\-\>`i', '$this->', $tpl);
		$tpl = str_replace('##constants##', $s, $tpl);
	}

	file_put_contents($file_out, $tpl) or die("Can't write file: $file_out"); 
	echo "Saved in $file_out.";
}

?>

<p>
	Create single file for: &nbsp; <a href="<?php echo basename(__FILE__); ?>?v=4">PHP4</a> &nbsp; or &nbsp; <a href="<?php echo basename(__FILE__); ?>?v=5">PHP5</a>
</p>