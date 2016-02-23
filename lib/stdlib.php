<?
function print_ar($arr, $html_entities = false, $no_interpolation = false, $in_recursion = false) {
	if ($in_recursion)
		$width = " width='100%'";
	else
		$width = " width='30'";
	echo "<table border=0 cellspacing=0 cellpadding=0 $width>";
	if (is_object ( $arr ))
		$arr = ( array ) $arr;
	if (! is_array ( $arr )) {
		if ($arr === false)
			$arr = '<I>false</I>';
		if ($arr === true)
			$arr = '<I>true</I>';
		if (! $in_recursion)
			echo '<tr bgcolor="#eeeeee"><td align=right valign=top><b>(scalar):</b>&nbsp;</td><td>';
		else
			echo '<tr><td>';
		if ($html_entities)
			$arr = 'stripped-HTML:' . htmlentities ( $arr );
		
		if (! $no_interpolation)
			echo "$arr";
		else
			echo $arr;
		echo '</td></tr>';
	} else {
		foreach ( $arr as $key => $val ) {
			$bgcolor = (@$bgcolor == 'eeeeee') ? 'ffffff' : 'eeeeee';
			echo "<tr bgcolor=\"#$bgcolor\"><td align=right valign=top><b>$key:</b>&nbsp;</td><td align=left>";
			print_ar ( $val, $html_entities, $no_interpolation, true );
		}
	}
	echo ("</table>");
	if (! $in_recursion) {
		//		echo("</table>");
	}
}

if (!function_exists('mb_strlen')) {
	function mb_strlen($utf8string=false) {
		if (empty($utf8string))
			return false;

		return preg_match_all("/.{1}/us",$utf8string,$dummy);

	}
}

if (!function_exists('hex2bin')) {
	function hex2bin( $str ) {
		$sbin = "";
		$len = strlen( $str );
		for ( $i = 0; $i < $len; $i += 2 ) {
			$sbin .= pack( "H*", substr( $str, $i, 2 ) );
		}

		return $sbin;
	}
}

if (class_exists('Memcached')) {
	if (!method_exists('Memcached','deleteMulti')) {
		class MemcachedFallback extends Memcached {
			public function deleteMulti($keys,$time=0) {
				if (!is_array($keys))
					return false;
				
				foreach ($keys as $k => $v) {
					$this->delete($v,$time);
				}
			}
		}
	}
}


if (!function_exists('mb_strlen')) {
    // send variable output to error log
    function log_str($var){
        $date = date('Y-m-d H:i:s');
        $str = "\n {$date} > ".print_r( $var,1)."\n";
        $type = ini_get('error_log');
        error_log($str,3,$type);
    }
}


if (!function_exists('getItemsByKey')) {
    // return array from a multiple array with deeps
    function getItemsByKey($array, $key)
    {
        $founded = false;

        if(isset($array[$key]))
            return $array[$key];

        foreach ($array as $sub_array){
            $founded = getItemsByKey($sub_array,$key);
            if($founded) return $founded;
        }
        return False;
    }
}


if (!function_exists('getItemsByKey')) {
    // send variable output to error log
    function log_str($var){
        $date = date('Y-m-d H:i:s');
        $str = "\n {$date} > ".print_r( $var,1)."\n";
        $type = ini_get('error_log');
        error_log($str,3,$type);
    }
}

 

?>
