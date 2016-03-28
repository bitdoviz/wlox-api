<?php 
class APIKeys {
	public static function get() {
		global $CFG;
		
		if (!$CFG->session_active || $CFG->session_locked)
			return false;
		
		$sql = "SELECT id, `key`, `view`, orders, withdraw, affiliate, test, auto_conversion, merchant, merchant_currencies, merchant_url, merchant_name FROM api_keys WHERE site_user = ".User::$info['id'];
		return db_query_array($sql);
	}
	
	public static function edit($ids_array) {
		global $CFG;
		
		if (!$CFG->session_active || $CFG->session_locked || !is_array($ids_array) || !$CFG->token_verified)
			return false;
		
		foreach ($ids_array as $id => $permissions) {
			$id = preg_replace("/[^0-9]/", "",$id);
			$permissions['merchant_url'] = preg_replace("/[^a-zA-Z0-9\/\-\_\:\.]/", "",$permissions['merchant_url']);
			$permissions['merchant_currencies'] = preg_replace("/[^a-zA-Z0-9\,]/", "",$permissions['merchant_currencies']);
			if (!($id > 0))
				continue;
			
			$existing = DB::getRecord('api_keys',$id,0,1);
			if (!$existing || $existing['site_user'] != User::$info['id'])
				continue;
			
			db_update('api_keys',$id,array('view'=>($permissions['view'] == 'Y' ? 'Y' : 'N'),'orders'=>($permissions['orders'] == 'Y' ? 'Y' : 'N'),'withdraw'=>($permissions['withdraw'] == 'Y' ? 'Y' : 'N'),'affiliate'=>($permissions['affiliate'] == 'Y' ? 'Y' : 'N'),'merchant'=>($permissions['merchant'] == 'Y' ? 'Y' : 'N'),'auto_conversion'=>($permissions['auto_conversion'] == 'Y' ? 'Y' : 'N'),'test'=>($permissions['test'] == 'Y' ? 'Y' : 'N'),'merchant_url'=>$permissions['merchant_url'],'merchant_currencies'=>$permissions['merchant_currencies'],'merchant_name'=>$permissions['merchant_name']));
		}
	}
	
	public static function add() {
		global $CFG;
		
		if (!$CFG->session_active || $CFG->session_locked || !$CFG->token_verified)
			return false;
		
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$key = substr(str_shuffle($chars),0,16);
		$secret = substr(str_shuffle($chars),0,32);
		
		$sql = 'SELECT id FROM api_keys WHERE api_keys.key = \''.$key.'\'';
		$exists = db_query_array($sql);
		if ($exists)
			return false;
		
		db_insert('api_keys',array('key'=>$key,'secret'=>$secret,'site_user'=>User::$info['id'],'view'=>'Y','orders'=>'Y','withdraw'=>'Y','affiliate'=>'N','merchant'=>'N','auto_conversion'=>'N'));
		return $secret;
	}
	
	public static function delete($remove_id) {
		global $CFG;
	
		$remove_id = preg_replace("/[^0-9]/", "",$remove_id);
		if (!$CFG->session_active || $CFG->session_locked || !($remove_id > 0) || !$CFG->token_verified)
			return false;
		
		$existing = DB::getRecord('api_keys',$remove_id,0,1);
		if (!$existing || $existing['site_user'] != User::$info['id'])
			continue;
		
		return db_delete('api_keys',$remove_id);
	}
	
	public static function hasPermission($api_key) {
		global $CFG;
		
		if ($CFG->memcached) {
			$cached = $CFG->m->get('api_'.$api_key.'_p');
			if ($cached)
				return $cached;
		}
		
		$sql = 'SELECT api_keys.view AS p_view, api_keys.orders AS p_orders, api_keys.withdraw AS p_withdraw, api_keys.affiliate AS p_affiliate, api_keys.merchant AS p_merchant FROM api_keys WHERE api_keys.key = "'.$api_key.'"';
		$result = db_query_array($sql);

		if ($result) {
			if ($CFG->memcached)
				$CFG->m->set('api_'.$api_key.'_p',$result[0],300);
			
			return $result[0];
		}
		else
			return array('p_view'=>'Y','p_orders'=>'Y','p_withdraw'=>'Y','p_affiliate'=>'Y','p_merchant'=>'Y');
	}
	
	public static function getMerchantInfo($api_key=false) {
		global $CFG;
	
		$api_key = preg_replace("/[^0-9a-zA-Z]/","",$api_key);
		if (!$api_key || strlen($api_key) != 16)
			return false;
	
		$sql = 'SELECT api_keys.merchant_currencies, api_keys.merchant_url, api_keys.merchant_name, api_keys.test, api_keys.auto_conversion
				FROM api_keys 
				JOIN site_users ON (site_users.id = api_keys.site_user)
				WHERE api_keys.key = "'.$api_key.'" AND site_users.is_merchant = "Y" ';
		$result = db_query_array($sql);
		if ($result)
			return $result[0];
		else
			return false;
	}
	
	public static function newInvoice($invoice_id,$api_key,$external_email=false,$currency=false,$amount_billed=false) {
		global $CFG;
		
		$invoice_id = preg_replace("/[^0-9a-zA-Z]/","",$invoice_id);
		$api_key = preg_replace("/[^0-9a-zA-Z]/","",$api_key);
		$external_email = preg_replace("/[^0-9a-zA-Z@\.\!#\$%\&\*+_\~\?\-]/", "",$external_email);
		$currency = preg_replace("/[^0-9]/","",$currency);
		$amount_billed = preg_replace("/[^0-9\.]/","",$amount_billed);
		
		if (!$api_key || strlen($api_key) != 16 || !$invoice_id)
			return false;
		
		$user_id = User::getIdFromAPIKey($api_key);
		$merchant_info = self::getMerchantInfo($api_key);
		if (!$merchant_info)
			return false;
		
		db_insert('invoices',array('invoice_id'=>$invoice_id,'date'=>date('Y-m-d H:i:s'),'merchant'=>$user_id,'currency'=>$currency,'amount_billed'=>$amount_billed,'email_external'=>$external_email));
		return true;
	}
	
	public static function getInvoiceId($invoice_id) {
		if (!$invoice_id)
			return false;
		
		$result = DB::getRecord('invoices',0,$invoice_id,false,'invoice_id');
		if ($result)
			return $result['id'];
		else
			return false;
	}
}
?>
