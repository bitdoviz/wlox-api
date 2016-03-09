<?php


if (!function_exists('log_str')) {
    // send variable output to error log
    function log_str($var){
        $date = date('Y-m-d H:i:s');
        $str = "\n {$date} > ".print_r( $var,1)."\n";
        $type = ini_get('error_log');
        error_log($str,3,$type);
    }
}

class Requests{

    public static function getUserFromAPIKey($api_key){
        $api_key = preg_replace("/[^0-9a-zA-Z]/","",$api_key) ;
        $sql = "
            SELECT 
                site_users.user
            FROM
                site_users,
                api_keys
            WHERE
                api_keys.key='{$api_key}' AND                    
                api_keys.site_user = site_users.id
            LIMIT 1
        ";
        $row = db_query_array($sql);
        if(!$row) return false;
        $user_id = $row['0']['user'];

        if($user_id) 
            return $user_id;

        log_str("WARNING Requests::getUserFromAPIKey({$api_key}) INVALID USER");
        return false;
    }

    public static function get($count=false,$page=false,$per_page=false,$withdrawals=false,$currency=false,$status=false,$public_api=false,$id=false,$btc_address1=false,$api_key=false,$age=false,$exclude_canceled=false) {
		global $CFG;
		
        if($api_key){
            $user_id = self::getUserFromAPIKey($api_key);
        } else {
		    if (!$CFG->session_active)
			    return false;

            $user_id = User::$info['id'];;
        }

        if(!$user_id){
            log_str('Requests.php::get user_id is empty.');
            return false;
        }		

		$page = preg_replace("/[^0-9]/", "",$page);
		$per_page = preg_replace("/[^0-9]/", "",$per_page);
		$currency = preg_replace("/[^0-9]/", "",$currency);
		$currency_info = (!empty($CFG->currencies[$currency])) ? $CFG->currencies[$currency] : false;
		$type = ($withdrawals) ? $CFG->request_withdrawal_id : $CFG->request_deposit_id;
		$id = preg_replace("/[^0-9]/", "",$id);
		
		$page = ($page > 0) ? $page - 1 : 0;
		$r1 = $page * $per_page;
		
		if ($CFG->memcached && !$count && $public_api) {
			$cached = $CFG->m->get('requests_u'.$user_id.(($type) ? '_t'.$type : '').(($currency) ? '_c'.$currency_info['id'] : '').(($status) ? '_s'.$status : '').(($id) ? '_i'.$id : '').(($per_page) ? '_l'.$per_page : ''));
			if (is_array($cached)) {
				if (!empty($cached))
					return $cached;
				else 
					return false;
			}
		}
		
		$currency_abbr = '(CASE requests.currency';
		$currency_abbr1 = '(CASE requests.currency';
		foreach ($CFG->currencies as $curr_id => $currency1) {
			if (is_numeric($curr_id))
				continue;
		
			$currency_abbr .= ' WHEN '.$currency1['id'].' THEN "'.$currency1['fa_symbol'].'" ';
			$currency_abbr1 .= ' WHEN '.$currency1['id'].' THEN "'.$currency1['currency'].'" ';
		}
		$currency_abbr .= ' END)';
		$currency_abbr1 .= ' END)';
		
		if (!$count && !$public_api)
			$sql = "SELECT requests.*, request_descriptions.name_{$CFG->language} AS description, request_status.name_{$CFG->language} AS status, $currency_abbr AS fa_symbol ";
		elseif (!$count && $public_api)
			$sql = "SELECT requests.id AS id, requests.date AS date, $currency_abbr1 AS currency, IF(requests.currency = {$CFG->btc_currency_id},requests.amount,ROUND(requests.amount,2)) AS amount, (IF(requests.request_status = {$CFG->request_pending_id} OR requests.request_status = {$CFG->request_awaiting_id},'PENDING',IF(requests.request_status = {$CFG->request_completed_id},'COMPLETED','CANCELED'))) AS status, requests.account AS account_number, requests.send_address AS address";
		else
			$sql = "SELECT COUNT(requests.id) AS total ";
		
		$sql .= " 
		FROM requests 
		LEFT JOIN request_descriptions ON (request_descriptions.id = requests.description) 
		LEFT JOIN request_status ON (request_status.id = requests.request_status)
		WHERE 1 AND requests.site_user = ".$user_id;
		
		if ($type > 0 && !($id > 0))
			$sql .= " AND requests.request_type = $type ";
		
		if ($currency)
			$sql .= " AND requests.currency = {$currency_info['id']} ";
		
		if ($status == 'pending')
			$sql .= " AND (requests.request_status = {$CFG->request_pending_id} OR requests.request_status = {$CFG->request_awaiting_id}) ";
		else if ($status == 'completed')
			$sql .= " AND requests.request_status = {$CFG->request_completed_id} ";
		else if ($status == 'cancelled')
			$sql .= " AND requests.request_status = {$CFG->request_cancelled_id} ";
		else
			$status = false;
			
		if ($id > 0)
			$sql .= " AND requests.id = $id ";

        if($btc_address1)
			$sql .= "\n AND requests.send_address = '{$btc_address1}' ";

        // age in minutes
        if($age)
			$sql .= "\n AND requests.date >= (NOW() - INTERVAL {$age} MINUTE ) ";

        if($exclude_canceled) // status] => CANCELED
			$sql .= "\n AND requests.request_status !='CANCELED' ";

		if ($per_page > 0 && !$count)
			$sql .= " ORDER BY requests.id DESC LIMIT $r1,$per_page ";


        log_str("\n\n 123 Request query: ".$sql);

		$result = db_query_array($sql);
		
		if ($CFG->memcached && !$count && $public_api) {
			$result = ($result) ? $result : array();
			$key = $user_id.(($type) ? '_t'.$type : '').(($currency) ? '_c'.$currency_info['id'] : '').(($status) ? '_s'.$status : '').(($id) ? '_i'.$id : '').(($per_page) ? '_l'.$per_page : '');
			$CFG->m->set('requests_u'.$key,$result,60);
			$cached = $CFG->m->get('requests_cache_'.$user_id);
			$cached[$key] = true;
			$CFG->m->set('requests_cache_'.$user_id,$cached,60);
		}
		
		if (!$count)
			return $result;
		else
			return $result[0]['total'];
	}
	
	public static function insert($currency=false,$amount=false,$btc_address=false,$account_number=false) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;

		if ($CFG->withdrawals_status == 'suspended')
			return false;
		
		$currency = preg_replace("/[^0-9]/", "",$currency);
		$amount = preg_replace("/[^0-9\.]/", "",$amount);
		$account_number = preg_replace("/[^0-9]/", "",$account_number);
		$btc_address = preg_replace("/[^0-9a-zA-Z]/",'',$btc_address);
		
		if (!array_key_exists($currency,$CFG->currencies))
			return false;
		
		$currency_info = $CFG->currencies[$currency];
		$available = User::getAvailable();
		$is_crypto = ($currency_info['is_crypto'] == 'Y');
		$amount = round($amount,($is_crypto ? 8 : 2),PHP_ROUND_HALF_UP);
		if ($amount > $available[$currency_info['currency']])
			return false;
		
		if ($is_crypto) {
			if (((User::$info['verified_authy'] == 'Y'|| User::$info['verified_google'] == 'Y')) && User::$info['confirm_withdrawal_2fa_btc'] == 'Y' && !($CFG->token_verified || $CFG->session_api))
				return false;
			if (((User::$info['verified_authy'] == 'Y'|| User::$info['verified_google'] == 'Y') && User::$info['confirm_withdrawal_2fa_bank'] == 'Y') && !($CFG->token_verified || $CFG->session_api))
				return false;

			$wallet = Wallets::getWallet($currency);
			$status = (User::$info['confirm_withdrawal_email_btc'] == 'Y' && !($CFG->token_verified || $CFG->session_api)) ? $CFG->request_awaiting_id : $CFG->request_pending_id;
			$request_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>User::$info['id'],'currency'=>$currency,'amount'=>$amount,'description'=>$CFG->withdraw_btc_desc,'request_status'=>$status,'request_type'=>$CFG->request_withdrawal_id,'send_address'=>$btc_address,'fee'=>$wallet['bitcoin_sending_fee'],'net_amount'=>($amount - $wallet['bitcoin_sending_fee'])));
			db_insert('history',array('date'=>date('Y-m-d H:i:s'),'ip'=>$CFG->client_ip,'history_action'=>$CFG->history_withdraw_id,'site_user'=>User::$info['id'],'request_id'=>$request_id,'bitcoin_address'=>$btc_address,'balance_before'=>User::$info[strtolower($currency_info['currency'])],'balance_after'=>(User::$info[strtolower($currency_info['currency'])] - $amount)));
			
			if (User::$info['confirm_withdrawal_email_btc'] == 'Y' && !($CFG->token_verified || $CFG->session_api) && $request_id > 0) {
				Wallets::sumFields($wallet['id'],array('pending_withdrawals'=>$amount));
				$email_token = User::randomPassword(12);
				$vars = User::$info;
				$vars['authcode'] = urlencode(Encryption::encrypt($email_token));
				$vars['baseurl'] = $CFG->frontend_baseurl;
				db_update('requests',$request_id,array('email_token'=>$email_token));
				
				$email = SiteEmail::getRecord('request-auth');
				Email::send($CFG->form_email,User::$info['email'],$email['title'],$CFG->form_email_from,false,$email['content'],$vars);
			}
			elseif (User::$info['notify_withdraw_btc'] == 'Y') {
		        $info['amount'] = $amount;
		        $info['currency'] = $currency_info['currency'];
		        $info['first_name'] = User::$info['first_name'];
		        $info['last_name'] = User::$info['last_name'];
		        $info['id'] = $request_id;
		        $email = SiteEmail::getRecord('new-withdrawal');
		        Email::send($CFG->form_email,User::$info['email'],str_replace('[amount]',$amount,str_replace('[currency]',$currency_info['currency'],$email['title'])),$CFG->form_email_from,false,$email['content'],$info);
			}
		}
		else {
			if (((User::$info['verified_authy'] == 'Y'|| User::$info['verified_google'] == 'Y') && User::$info['confirm_withdrawal_2fa_bank'] == 'Y') && !($CFG->token_verified || $CFG->session_api))
				return false;

			$status = (User::$info['confirm_withdrawal_email_bank'] == 'Y' && !($CFG->token_verified || $CFG->session_api)) ? $CFG->request_awaiting_id : $CFG->request_pending_id;
			$request_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>User::$info['id'],'currency'=>$currency,'amount'=>$amount,'description'=>$CFG->withdraw_fiat_desc,'request_status'=>$status,'request_type'=>$CFG->request_withdrawal_id,'account'=>$account_number,'fee'=>$CFG->fiat_withdraw_fee,'net_amount'=>($amount - $CFG->fiat_withdraw_fee)));
			db_insert('history',array('date'=>date('Y-m-d H:i:s'),'ip'=>$CFG->client_ip,'history_action'=>$CFG->history_withdraw_id,'site_user'=>User::$info['id'],'request_id'=>$request_id,'balance_before'=>User::$info[strtolower($currency_info['currency'])],'balance_after'=>(User::$info[strtolower($currency_info['currency'])] - $amount)));
			
			if (User::$info['confirm_withdrawal_email_bank'] == 'Y' && !($CFG->token_verified || $CFG->session_api) && $request_id > 0) {
				$vars = User::$info;
				$email_token = User::randomPassword(12);
				$vars['authcode'] = urlencode(Encryption::encrypt($email_token));
				$vars['baseurl'] = $CFG->frontend_baseurl;
				db_update('requests',$request_id,array('email_token'=>$email_token));
			
				$email = SiteEmail::getRecord('request-auth');
				Email::send($CFG->form_email,User::$info['email'],$email['title'],$CFG->form_email_from,false,$email['content'],$vars);
			}
			elseif (User::$info['notify_withdraw_bank'] == 'Y') {
			    $info['amount'] = number_format($amount,2);
			    $info['currency'] = $currency_info['currency'];
			    $info['first_name'] = User::$info['first_name'];
			    $info['last_name'] = User::$info['last_name'];
			    $info['id'] = $request_id;
			    $email = SiteEmail::getRecord('new-withdrawal');
			    Email::send($CFG->form_email,User::$info['email'],str_replace('[amount]',number_format($amount,2),str_replace('[currency]',$currency_info['currency'],$email['title'])),$CFG->form_email_from,false,$email['content'],$info);
			}
		}
		
		if ($request_id && $CFG->memcached) {
			$CFG->unset_cache['balances'][User::$info['id']] = 2;
			self::unsetCache(User::$info['id']);
		}
		
		if ($CFG->session_api && $request_id > 0) {
			$result = self::get(false,false,false,false,false,false,1,$request_id);
			return $result[0];
		}
		else
			return $request_id;
	}
	
	public static function emailValidate($authcode) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$authcode = Encryption::decrypt(urldecode($authcode));
		if (!$authcode)
			return false;

		$authcode = preg_replace("/[^0-9a-zA-Z]/", "",$authcode);
		if (!$authcode)
			return false;
		
		$sql = 'SELECT * FROM requests WHERE email_token = "'.$authcode.'"';
		$result = db_query_array($sql);
		if (!$result)
			return false;
		
		$request = $result[0];
		if ($request['request_status'] != $CFG->request_awaiting_id)
			return false;
		
	    if (User::$info['notify_withdraw_bank'] == 'Y') {
	        $currency_info = DB::getRecord('currencies',$request['currency'],0,1);
	        $info['amount'] = $request['amount'];
	        $info['currency'] = $currency_info['currency'];
	        $info['first_name'] = User::$info['first_name'];
	        $info['last_name'] = User::$info['last_name'];
	        $info['id'] = $request['id'];
	        $email = SiteEmail::getRecord('new-withdrawal');
	        Email::send($CFG->form_email,User::$info['email'],str_replace('[amount]',number_format($request['amount'],2),str_replace('[currency]',$currency_info['currency'],$email['title'])),$CFG->form_email_from,false,$email['content'],$info);
	    }
		return db_update('requests',$request['id'],array('request_status'=>$CFG->request_pending_id));
	}
	
	public static function unsetCache($user_id) {
		global $CFG;
		
		if (!$user_id || !$CFG->memcached)
			return false;
		
		$cached = $CFG->m->get('requests_cache_'.User::$info['id']);
		if ($cached) {
			$delete_keys = array();
			foreach ($cached as $key) {
				$delete_keys[] = 'requests_u'.$key;
			}
			$CFG->m->deleteMulti($delete_keys);
		}
	}
}
