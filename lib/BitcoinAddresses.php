<?php
class BitcoinAddresses{
	static $bitcoin;
	
	public static function get($count=false,$c_currency=false,$page=false,$per_page=false,$user=false,$unassigned=false,$system=false,$public_api=false,$api_key=false) {
		global $CFG;

		if ((!$CFG->session_active || !(User::$info['id'] > 0)) && (!$api_key))
			return false;
		
		$page = preg_replace("/[^0-9]/", "",$page);
		$per_page = preg_replace("/[^0-9]/", "",$per_page);
		$c_currency = preg_replace("/[^0-9]/", "",$c_currency);
		$api_key = preg_replace("/[^0-9a-zA-Z]/",'',$api_key);
		
		if (empty($CFG->currencies[strtoupper($c_currency)]))
			$c_currency = $CFG->currencies[$main['crypto']]['id'];
		else
			$c_currency = $CFG->currencies[strtoupper($c_currency)]['id'];
		
		$page = ($page > 0) ? $page - 1 : 0;
		$r1 = $page * $per_page;

        if(!$api_key)
		    $user = User::$info['id'];
		else {
            $row = DB::getRecord('api_keys',false,$api_key,false,'key');
            $user = $row['id'];
            if (!$user)
            	return false;
        }

		if (!$count && !$public_api)
			$sql = "SELECT * FROM bitcoin_addresses WHERE 1 ";
		elseif (!$count && $public_api)
			$sql = "SELECT address,`date` FROM bitcoin_addresses WHERE 1 ";
		else
			$sql = "SELECT COUNT(id) AS total FROM bitcoin_addresses WHERE 1  ";
		
		if ($user > 0)
			$sql .= " AND site_user = $user ";
		
		if ($unassigned)
			$sql .= " AND site_user = 0 ";
		
		if ($system)
			$sql .= " AND system_address = 'Y' ";
		else
			$sql .= " AND system_address != 'Y' ";
		
		if ($c_currency)
			$sql .= ' AND c_currency = '.$c_currency.' ';
		
		if ($per_page > 0 && !$count)
			$sql .= " ORDER BY bitcoin_addresses.date DESC LIMIT $r1,$per_page ";
		
		$result = db_query_array($sql);
		if (!$count)
			return $result;
		else
			return $result[0]['total'];
	}
	
	public static function getNew($c_currency=false,$return_address=false,$api_key=false,$invoice_id=false) {
		global $CFG;

        $api_key = preg_replace("/[^0-9a-zA-Z]/","",$api_key);
        $c_currency = preg_replace("/[^0-9]/", "",$c_currency);
        $invoice_id = preg_replace("/[^0-9]/","",$invoice_id);
        
        if (!array_key_exists($c_currency,$CFG->currencies))
        	return false;
        
        if (!$CFG->session_active && strlen($api_key) != 16)
        	return false;

        if (!$api_key)
        	$user_id = User::$info['id'];
        else
        	$user_id = User::getIdFromAPIKey($api_key);
        
        if (!$user_id)
        	return false;

        if ($invoice_id) {
        	if ($invoice_id)
        		$invoice_id = APIKeys::getInvoiceId($invoice_id);
        }
        
		$wallet = Wallets::getWallet($c_currency);		
		
		require_once('../lib/easybitcoin.php');
		$bitcoin = new Bitcoin($wallet['bitcoin_username'],$wallet['bitcoin_passphrase'],$wallet['bitcoin_host'],$wallet['bitcoin_port'],$wallet['bitcoin_protocol']);
		$new_address = $bitcoin->getnewaddress($wallet['bitcoin_accountname']);

		$new_id = db_insert('bitcoin_addresses',array('c_currency'=>$c_currency,'address'=>$new_address,'site_user'=>$user_id,'date'=>date('Y-m-d H:i:s'),'invoice_id'=>$invoice_id));
		return ($return_address) ? $new_address : $new_id;
	}
	
	public static function validateAddress($c_currency=false,$btc_address) {
		global $CFG;
		
		$btc_address = preg_replace("/[^0-9a-zA-Z]/",'',$btc_address);
		$c_currency = preg_replace("/[^0-9]/", "",$c_currency);
		$wallet = Wallets::getWallet($c_currency);
		
		if (!$btc_address || !$c_currency)
			return false;
	
		require_once('../lib/easybitcoin.php');
		$bitcoin = new Bitcoin($wallet['bitcoin_username'],$wallet['bitcoin_passphrase'],$wallet['bitcoin_host'],$wallet['bitcoin_port'],$wallet['bitcoin_protocol']);
		
		$response = $bitcoin->validateaddress($btc_address);
	
		if (!$response['isvalid'] || !is_array($response))
			return false;
		else
			return true;
	}
}
