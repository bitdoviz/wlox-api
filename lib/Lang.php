<?php
class Lang {
	public static function getTable() {
		global $CFG;
		
		if ($CFG->memcached) {
			$cached = $CFG->m->get('lang');
			if ($cached) {
				return $cached;
			}
		}
		
		$sql = "SELECT * FROM lang";
		$result = db_query_array($sql);
		
		if ($result) {
			foreach ($result as $row) {
				$key = $row['key'];
				$lang_table[$key]['tr'] = str_replace('[exchange_name]',$CFG->exchange_name,str_replace('[baseurl]',$CFG->frontend_baseurl,$row['tr']));
				$lang_table[$key]['en'] = str_replace('[exchange_name]',$CFG->exchange_name,str_replace('[baseurl]',$CFG->frontend_baseurl,$row['eng']));
			}

			if ($CFG->memcached)
				$CFG->m->set('lang',$lang_table,300);
		}
		return $lang_table;
	}
	
	public static function string($key=false) {
		global $CFG;
	
		if (empty($key))
			return false;
		
		$lang = (empty($CFG->language)) ? 'eng' : $CFG->language;
		if ($lang == 'en')
			$lang = 'eng';
		else if ($lang == 'es')
			$lang = 'esp';
		
		if ($CFG->memcached) {
			$cached = $CFG->m->get('lang_'.$key.'_'.$lang);
			if ($cached) {
				return $cached;
			}
		}
			
		$sql = 'SELECT '.$lang.' AS line FROM lang WHERE `key` = "'.$key.'" LIMIT 0,1';
		$result = db_query_array($sql);
		if ($result) {
			if ($CFG->memcached)
				$CFG->m->set('lang_'.$key.'_'.$lang,$result[0]['line'],300);
			
			return $result[0]['line'];
		}
		else
			return false;
	}
}
?>
