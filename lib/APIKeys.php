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
		
		db_insert('invoices',array('invoice_id'=>$invoice_id,'date'=>date('Y-m-d H:i:s'),'merchant'=>$user_id,'currency'=>$currency,'amount_billed'=>$amount_billed,'email_external'=>$external_email,'auto_conversion'=>$merchant_info['auto_conversion']));
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
	
	public static function getInvoice($invoice_id) {
		global $CFG;
		
		if (!$invoice_id)
			return false;
	
		$result = DB::getRecord('invoices',0,$invoice_id,false,'invoice_id');
		if (!$result)
			return false;
		
		$result['user_email'] = $result['email_external'];
		$result['conversions_pending'] = 'N';
		$result['crypto_deposits_pending'] = 'N';
		
		$sql = 'SELECT COUNT(id) AS total FROM orders WHERE invoice_id = '.$result['id'];
		$result1 = db_query_array($sql);
		if ($result1 && $result1[0]['total'] > 0)
			$result['conversions_pending'] = 'Y';
		
		$sql = 'SELECT COUNT(id) AS total FROM requests WHERE invoice_id = '.$result['id'].' AND requests.request_status = '.$CFG->request_completed_id;
		$result1 = db_query_array($sql);
		if ($result1 && $result1[0]['total'] > 0)
			$result['crypto_deposits_pending'] = 'Y';
		
		
		unset($result['id']);
		unset($result['merchant']);
		unset($result['site_user']);
		unset($result['email_external']);
		
		return $result;
	}
	
	public static function updateInvoice($invoice_id,$api_key=false,$type=false,$amount_payed=false,$currency_payed=false) {
		global $CFG;
		
		$invoice_id = preg_replace("/[^0-9a-zA-Z]/","",$invoice_id);
		$amount_payed = preg_replace("/[^0-9\.]/", "",$amount_payed);
		$currency_payed = preg_replace("/[^0-9]/", "",$currency_payed);
		$api_key = preg_replace("/[^0-9a-zA-Z]/","",$api_key);
		$currency_credited = $currency_payed;
		$received = 0;
		$customer_balances = array();
		$fees = 0;
		$fee_currency = $currency_payed;
		
		if (!$invoice_id || !$type || strlen($api_key) != 16)
			return false;
		
		$invoice_info = DB::getRecord('invoices',0,$invoice_id,false,'invoice_id');
		if (!$invoice_info)
			return false;
		
		$merchant_id = $invoice_info['merchant'];
		$merchant_info = DB::getRecord('site_users',$merchant_id,false,true);
		if (!$merchant_info)
			return false;
		
		$api_key_info = self::getMerchantInfo($api_key);
		if (!$api_key_info)
			return false;
		
		$customer_id = false;
		if (!empty(User::$info['id'])) {
			$customer_id = User::$info['id'];
		}
		
		if ($type == 'crypto') {
			$amount_payed1 = 0;
			$deposits = Requests::get(false,false,false,false,$currency_payed,false,false,false,$invoice_id);
			
			if ($deposits) {
				foreach ($deposits as $deposit) {
					$amount_payed += $deposit['amount'];
					if ($deposit['request_status'] != $CFG->request_completed_id)
						continue;
					
					$amount_payed1 += $deposit['amount'];
				}
			}
						
			if ($amount_payed1 > 0 && $invoice_info['currency'] && $currency_payed != $invoice_info['currency'] && $invoice_info['auto_conversion'] == 'Y') {
				$currency_credited = $invoice_info['currency'];
				$order = Orders::executeOrder(false,false,$amount_payed1,$currency_payed,$currency_credited,false,true,false,$merchant_id,false,false,false,true,false,$invoice_info['id']);

				/*
				if (!empty($order['order_info']) && $order['order_info']['amount_remaining'] == 0)
					$received = $amount_payed1 * $order['order_info']['avg_price_executed'];
					*/
			}
			else 
				$received = $amount_payed1;
			
			$fees = $received * $CFG->merchant_commision;
			$fee_currency = $currency_payed;
			$received = $received - ($received * $CFG->merchant_commision);
			
			db_start_transaction();
			$merchant_balances = User::getBalances($merchant_id,array($currency_payed),true);
			User::updateBalances($merchant_id,array($currency_payed=>number_format((($merchant_balances[strtolower($CFG->currencies[$currency_payed]['currency'])]) - $fees),8,'.','')));
			db_commit();
		}
		else if ($type == 'account') {
			db_start_transaction();
			$customer_balances = User::getBalances($customer_id,array($currency_payed),true);
			$generate_order = false;
			
			if (!$currency_payed || !$amount_payed || empty($customer_balances[strtolower($CFG->currencies[$currency_payed]['currency'])]) || ($customer_balances[strtolower($CFG->currencies[$currency_payed]['currency'])] - $amount_payed <= 0)) {
				db_commit();
				return false;
			}
			
			User::updateBalances($customer_id,array($currency_payed=>number_format(($customer_balances[strtolower($CFG->currencies[$currency_payed]['currency'])] - $amount_payed),8,'.','')));
			db_commit();
			
			db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>$customer_id,'currency'=>$currency_payed,'amount'=>$amount_payed,'description'=>(($CFG->currencies[$currency_payed]['is_crypto'] == 'Y') ? $CFG->withdraw_btc_desc : $CFG->withdraw_fiat_desc),'request_status'=>$CFG->request_completed_id,'request_type'=>$CFG->request_withdrawal_id,'invoice_id'=>$invoice_info['id']));
			
			if ($invoice_info['currency'] && $currency_payed != $invoice_info['currency'] && $invoice_info['auto_conversion'] == 'Y') {
				$currency_credited = $invoice_info['currency'];
				
				if ($CFG->currencies[$currency_payed]['is_crypto'] == 'Y') {
					$generate_order = true;
					db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>$merchant_id,'currency'=>$currency_payed,'amount'=>$amount_payed,'description'=>(($CFG->currencies[$currency_payed]['is_crypto'] == 'Y') ? $CFG->deposit_bitcoin_desc : $CFG->deposit_fiat_desc),'request_status'=>$CFG->request_completed_id,'request_type'=>$CFG->request_deposit_id,'invoice_id'=>$invoice_info['id']));
				
					$fees = $amount_payed * $CFG->merchant_commision;
					$fee_currency = $currency_payed;
					$amount_payed = $amount_payed - ($amount_payed * $CFG->merchant_commision);
					
					db_start_transaction();
					$merchant_balances = User::getBalances($merchant_id,array($currency_payed),true);
					User::updateBalances($merchant_id,array($currency_payed=>number_format((($merchant_balances[strtolower($CFG->currencies[$currency_payed]['currency'])]) + $amount_payed),8,'.','')));
					db_commit();
				}
				else {
					$fees = $amount_payed * $CFG->merchant_commision;
					$fee_currency = $currency_payed;
					$amount_payed = $amount_payed - ($amount_payed * $CFG->merchant_commision);
					
					$ledger = array();
					$sql = 'SELECT * FROM conversions WHERE is_active != "Y" AND currency IN ('.$currency_payed.','.$currency_credited.')';
					$result = db_query_array($sql);
					if ($result) {
						foreach ($result as $row) {
							$ledger[$row['currency']] = $row;
						}
					}
					
					if (!empty($ledger[$currency_payed]))
						db_update('conversions',$ledger[$currency_payed]['id'],array('amount'=>number_format(($ledger[$currency_payed]['amount'] + $amount_payed),8,'.',''),'date1'=>date('Y-m-d H:i:s')));
					else
						db_insert('conversions',array('amount'=>number_format($amount_payed,8,'.',''),'date'=>date('Y-m-d H:i:s'),'date1'=>date('Y-m-d H:i:s'),'currency'=>$currency_payed,'is_active'=>'N','factored'=>'N'));
					
					if (!empty($ledger[$currency_credited]))
						db_update('conversions',$ledger[$currency_credited]['id'],array('amount'=>number_format(($ledger[$currency_credited]['amount'] + Currencies::convertTo($amount_payed,$currency_payed,$currency_credited,'down')),8,'.',''),'date1'=>date('Y-m-d H:i:s')));
					else
						db_insert('conversions',array('amount'=>number_format(Currencies::convertTo($amount_payed,$currency_payed,$currency_credited,'down'),8,'.',''),'date'=>date('Y-m-d H:i:s'),'date1'=>date('Y-m-d H:i:s'),'currency'=>$currency_credited,'is_active'=>'N','factored'=>'N'));
				
					$received = Currencies::convertTo($amount_payed,$currency_payed,$currency_credited,'down');
					db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>$merchant_id,'currency'=>$currency_credited,'amount'=>number_format($received,8,'.',''),'description'=>(($CFG->currencies[$currency_credited]['is_crypto'] == 'Y') ? $CFG->deposit_bitcoin_desc : $CFG->deposit_fiat_desc),'request_status'=>$CFG->request_completed_id,'request_type'=>$CFG->request_deposit_id,'invoice_id'=>$invoice_info['id']));
					
					db_start_transaction();
					$merchant_balances = User::getBalances($merchant_id,array($currency_credited),true);
					User::updateBalances($merchant_id,array($currency_credited=>number_format((($merchant_balances[strtolower($CFG->currencies[$currency_credited]['currency'])]) + $received),8,'.','')));
					db_commit();
				}
			}
			else {
				db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>$merchant_id,'currency'=>$currency_payed,'amount'=>number_format($amount_payed,8,'.',''),'description'=>(($CFG->currencies[$currency_payed]['is_crypto'] == 'Y') ? $CFG->deposit_bitcoin_desc : $CFG->deposit_fiat_desc),'request_status'=>$CFG->request_completed_id,'request_type'=>$CFG->request_deposit_id,'invoice_id'=>$invoice_info['id']));
				$received = $amount_payed;
				
				$fees = $received * $CFG->merchant_commision;
				$fee_currency = $currency_payed;
				$received = $received - ($received * $CFG->merchant_commision);
				
				db_start_transaction();
				$merchant_balances = User::getBalances($merchant_id,array($currency_payed),true);
				User::updateBalances($merchant_id,array($currency_payed=>number_format((($merchant_balances[strtolower($CFG->currencies[$currency_payed]['currency'])]) + $received),8,'.','')));
				db_commit();
			}
			
			if ($generate_order) {
				$order = Orders::executeOrder(false,false,$amount_payed,$currency_payed,$currency_credited,false,true,false,$merchant_id,false,false,false,true,false,$invoice_info['id']);
				/*
				if (!empty($order['order_info']) && $order['order_info']['amount_remaining'] == 0)
					$received = $amount_payed * $order['order_info']['avg_price_executed'];
					*/
			}
		}
		
		$customer_email = (($customer_id) ? User::$info['email'] : $invoice_info['email_external']);
		$info = array('invoice_id'=>$invoice_id,'amount'=>$amount_payed,'currency'=>$CFG->currencies[$currency_payed]['currency'],'user_email'=>$customer_email,'merchant_name'=>$api_key_info['merchant_name'],'merchant_email'=>$merchant_info['email']);
		$email = SiteEmail::getRecord('merchant-user-receipt');
		Email::send($CFG->form_email,$customer_email,str_replace('[amount]',$amount_payed,str_replace('[merchant_name]',$api_key_info['merchant_name'],$email['title'])),$CFG->form_email_from,false,$email['content'],$info);
		
		$info = array('invoice_id'=>$invoice_id,'amount'=>$amount_payed,'currency'=>$CFG->currencies[$currency_payed]['currency'],'user_email'=>$customer_email);
		$email = SiteEmail::getRecord('merchant-transaction-notif');
		Email::send($CFG->form_email,(($customer_id) ? User::$info['email'] : $invoice_info['email_external']),$email['title'],$CFG->form_email_from,false,$email['content'],$info);
		
		db_update('invoices',$invoice_info['id'],array('site_user'=>$customer_id,'amount_received'=>number_format($received,8,'.',''),'currency_received'=>$currency_credited,'completed'=>'Y','fee'=>number_format($fees,8,'.',''),'fee_rate'=>($CFG->merchant_commision * 100)));
		Status::updateEscrows(array($fee_currency=>number_format($fees,8,'.','')));
		return true;
	}
}
?>
