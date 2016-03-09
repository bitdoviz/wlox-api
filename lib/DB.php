<?php

// send variable output to error log
if (!function_exists('log_str')) {
    // send variable output to error log
    function log_str($var){
        $date = date('Y-m-d H:i:s');
        $str = "\n {$date} > ".print_r( $var,1)."\n";
        $type = ini_get('error_log');
        error_log($str,3,$type);
    }
}

class DB {
	public static $errors, $random_ids;
	
	public static function getRecord($table,$id = 0,$f_id=0,$id_required=false,$f_id_field=false,$order_by=false,$order_asc=false,$for_update=false) {
		if ($id_required && !($id > 0))
			return false;
			
		if (!$table)
			return false;
		
		$f_id_field = ($f_id_field) ? $f_id_field : 'f_id';
			
		$sql = "SELECT {$table}.* FROM {$table} WHERE 1 ";
		if ($id > 0) {
			$sql .= " AND  {$table}.id = $id ";
		}
		if ($f_id) {
			$sql .= " AND  {$table}.{$f_id_field} = '$f_id' ";
		}
		if ($order_by) {
			$order_asc = ($order_asc) ? 'ASC' : 'DESC';
			$sql .= " ORDER BY $order_by $order_asc ";
		}
		$sql .= " LIMIT 0,1 ";
		
		if ($for_update)
			$sql .= ' FOR UPDATE';
		
        log_str( " 30 DB.php $sql \n \n " );

		$result = db_query_array($sql);
		return $result[0];
	}
}
?>
