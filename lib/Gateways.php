<?php 
use Omnipay\Omnipay;
class Gateways {
	public static $gateways;
	
	public static function get($type=false) {
		global $CFG;
		
		$sql = 'SELECT gateways.*, gateway_types.key AS type_key, gateway_types.name_'.$CFG->language.' AS type_name FROM gateways LEFT JOIN gateway_types ON (gateways.gateway_type = gateway_types.id) WHERE gateways.is_active = "Y" ';
		if ($type && is_string($type))
			$sql .= ' AND gateway_types.key = "'.$type.'" ';
		if ($type && is_numeric($type))
			$sql .= ' AND gateways.gateway_type = '.$type.' ';
		
		$result = db_query_array($sql);
		$return = array();
		if ($result) {
			foreach ($result as $row) {
				$return[$row['id']] = $row;
			}
		}
		
		return $return;
	}
	
	public static function getTypes() {
		global $CFG;
		
		$sql = 'SELECT * FROM gateway_types';
		$result = db_query_array($sql);
		
		return $result;
	}
	
	public static function getCards() {
		global $CFG;
	
		$sql = 'SELECT * FROM gateway_card_types ORDER BY id ASC';
		$result = db_query_array($sql);
		$return = array();
		if ($result) {
			foreach ($result as $row) {
				$return[$row['id']] = $row;
			}
		}
	
		return $return;
	}
	
	public static function depositPreconditions($info) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$info['gateway_type'] = preg_replace("/[^a-z_]/","",$info['gateway_type']);
		$info['gateway_currency'] = preg_replace("/[^0-9]/","",$info['gateway_currency']);
		$info['gateway_amount'] = String::currencyInput($info['gateway_amount']);
		$info['card_type'] = preg_replace("/[^0-9]/","",$info['card_type']);
		$info['card_name'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u", "",$info['card_name']);
		$info['card_number'] = preg_replace("/[^0-9]/", "",$info['card_number']);
		$info['card_expiration_month'] = preg_replace("/[^0-9]/","",$info['card_expiration_month']);
		$info['card_expiration_year'] = preg_replace("/[^0-9]/","",$info['card_expiration_year']);
		$info['card_cvv'] = preg_replace("/[^0-9]/", "",$info['card_cvv']);
		$info['card_email'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_email']);
		$info['card_phone'] = preg_replace("/[^0-9]/","",$info['card_phone']);
		$info['card_address1'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_address1']);
		$info['card_address2'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_address2']);
		$info['card_city'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_city']);
		$info['card_state'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_state']);
		$info['card_country'] = preg_replace("/[^0-9]/","",$info['card_country']);
		$info['card_zip'] = preg_replace("/[^0-9]/","",$info['card_zip']);
		$info['gateway_id'] = preg_replace("/[^0-9]/","",$info['gateway_id']);
		$info['gateway_user'] = preg_replace($CFG->pass_regex,"",$info['gateway_user']);
		$info['gateway_pass'] = preg_replace($CFG->pass_regex,"",$info['gateway_pass']);
		$info['gateway_bank_account'] = preg_replace("/[^0-9]/","",$info['gateway_bank_account']);
		$info['gateway_bank_iban'] = preg_replace("/[^0-9]/","",$info['gateway_bank_iban']);
		$info['gateway_bank_swift'] = preg_replace("/[^0-9]/","",$info['gateway_bank_swift']);
		$info['gateway_bank_name'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['gateway_bank_name']);
		$info['gateway_bank_city'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['gateway_bank_city']);
		$info['gateway_bank_country'] = preg_replace("/[^0-9]/","",$info['gateway_bank_country']);
		
		if (!$info['gateway_type'])
			return array('error'=>array('message'=>Lang::string('gateway-invalid-type'),'code'=>'GATEWAY_INVALID_TYPE'));
		
		$result = self::get($info['gateway_type']);
		if (!$result)
			return array('error'=>array('message'=>Lang::string('gateway-invalid-type'),'code'=>'GATEWAY_INVALID_TYPE'));
		
		if (empty($CFG->currencies[$info['gateway_currency']]))
			return array('error'=>array('message'=>Lang::string('gateway-invalid-currency'),'code'=>'GATEWAY_INVALID_CURRENCY'));
		
		if ($info['gateway_amount'] < 1)
			return array('error'=>array('message'=>Lang::string('gateway-invalid-amount'),'code'=>'GATEWAY_INVALID_AMOUNT'));
		
		if ($info['gateway_type'] == 'credit_card') {
			if (!$info['card_type'])
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-type'),'code'=>'GATEWAY_INVALID_CARD_TYPE'));
			
			$card_type = DB::getRecord('gateway_card_types',$info['card_type'],0,1);
			if (!$card_type || $card_type['is_active'] != 'Y')
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-type'),'code'=>'GATEWAY_INVALID_CARD_TYPE'));
			
			$gateway = DB::getRecord('gateways',$card_type['gateway'],0,1);
			if (!$gateway)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-gateway'),'code'=>'GATEWAY_INVALID_GATEWAY'));
			
			if ($gateway['offsite'] == 'Y')
				return array('offsite'=>$gateway['offsite_url'],'offsite_vars'=>self::redirectVars($info));
			
			if (!$info['card_name'] || strlen($info['card_name']) < 5)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-name'),'code'=>'GATEWAY_INVALID_CARD_NAME'));
			
			if (!$info['card_number'] || strlen($info['card_number']) < 13)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-number'),'code'=>'GATEWAY_INVALID_CARD_NUMBER'));
		
			if (!$info['card_expiration_month'] || !$info['card_expiration_year'] || $info['card_expiration_month'] < 1 || $info['card_expiration_month'] > 12 || strtotime($info['card_expiration_year'].'-'.$info['card_expiration_month'].'-01 00:00:00') < time())
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-expiration'),'code'=>'GATEWAY_INVALID_CARD_EXPIRATION'));
			
			if (!$info['card_email'] || strlen($info['card_email']) < 5)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-email'),'code'=>'GATEWAY_INVALID_CARD_EMAIL'));

			if (!$info['card_phone'] || strlen($info['card_phone']) < 5)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-phone'),'code'=>'GATEWAY_INVALID_CARD_PHONE'));
			
			if (!$info['card_address1'] || strlen($info['card_address1']) < 5)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-address'),'code'=>'GATEWAY_INVALID_CARD_ADDRESS'));
			
			if (!$info['card_city'] || strlen($info['card_city']) < 3)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-city'),'code'=>'GATEWAY_INVALID_CARD_CITY'));

			if (!$info['card_state'] || strlen($info['card_state']) < 1)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-state'),'code'=>'GATEWAY_INVALID_CARD_STATE'));
			
			if (!$info['card_country'] || !($info['card_country'] > 0))
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-country'),'code'=>'GATEWAY_INVALID_CARD_COUNTRY'));
			
			if (!$info['card_zip'] || strlen($info['card_zip']) < 1)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-card-zip'),'code'=>'GATEWAY_INVALID_CARD_ZIP'));
		}
		else if ($gateway_type1 == 'gateway') {
			$gateway = DB::getRecord('gateways',$info['gateway_id'],0,1);
			if (!$gateway)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-gateway'),'code'=>'GATEWAY_INVALID_GATEWAY'));
			
			if ($gateway['offsite'] == 'Y')
				return array('offsite'=>$gateway['offsite_url'],'offsite_vars'=>self::redirectVars($info));
			
			if (!$info['gateway_user'] || strlen($info['gateway_user']) < 5)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-user'),'code'=>'GATEWAY_INVALID_USER'));
			
			if (!$info['gateway_pass'] || strlen($info['gateway_pass']) < 3)
				return array('error'=>array('message'=>Lang::string('gateway-invalid-pass'),'code'=>'GATEWAY_INVALID_PASSWORD'));
		}
		return false;
	}
	
	public static function processDeposit($info) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$error = self::depositPreconditions($info);
		if ($error)
			return $error;
		
		$invoice_id = md5(uniqid(mt_rand(),true));
		$info['gateway_type'] = preg_replace("/[^a-z_]/","",$info['gateway_type']);
		$info['gateway_currency'] = preg_replace("/[^0-9]/","",$info['gateway_currency']);
		$info['gateway_amount'] = String::currencyInput($info['gateway_amount']);
		$info['card_type'] = preg_replace("/[^0-9]/","",$info['card_type']);
		$info['card_name'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u", "",$info['card_name']);
		$info['card_number'] = preg_replace("/[^0-9]/", "",$info['card_number']);
		$info['card_expiration_month'] = preg_replace("/[^0-9]/","",$info['card_expiration_month']);
		$info['card_expiration_year'] = preg_replace("/[^0-9]/","",$info['card_expiration_year']);
		$info['card_cvv'] = preg_replace("/[^0-9]/", "",$info['card_cvv']);
		$info['card_email'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_email']);
		$info['card_phone'] = preg_replace("/[^0-9]/","",$info['card_phone']);
		$info['card_address1'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_address1']);
		$info['card_address2'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_address2']);
		$info['card_city'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_city']);
		$info['card_state'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['card_state']);
		$info['card_country'] = preg_replace("/[^0-9]/","",$info['card_country']);
		$info['card_zip'] = preg_replace("/[^0-9]/","",$info['card_zip']);
		$info['gateway_id'] = preg_replace("/[^0-9]/","",$info['gateway_id']);
		$info['gateway_user'] = preg_replace($CFG->pass_regex,"",$info['gateway_user']);
		$info['gateway_pass'] = preg_replace($CFG->pass_regex,"",$info['gateway_pass']);
		$info['gateway_bank_account'] = preg_replace("/[^0-9]/","",$info['gateway_bank_account']);
		$info['gateway_bank_iban'] = preg_replace("/[^0-9]/","",$info['gateway_bank_iban']);
		$info['gateway_bank_swift'] = preg_replace("/[^0-9]/","",$info['gateway_bank_swift']);
		$info['gateway_bank_name'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['gateway_bank_name']);
		$info['gateway_bank_city'] = preg_replace("/[^\pL a-zA-Z0-9@\s\._-]/u","",$info['gateway_bank_city']);
		$info['gateway_bank_country'] = preg_replace("/[^0-9]/","",$info['gateway_bank_country']);
		
		if ($info['gateway_type'] == 'credit_card') {
			$card_type_info = DB::getRecord('gateway_card_types',$info['card_type'],0,1);
			$gateway_info = DB::getRecord('gateways',$card_type_info['gateway'],0,1);
			$exp_date = str_pad($info['card_expiration_month'],2,'0',STR_PAD_LEFT).'/'.$info['card_expiration_year'];
			
			$processor = new PaymentsProcessor();
			$processor->setGateway($gateway_info['key']);
			$request_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>User::$info['id'],'currency'=>$info['gateway_currency'],'amount'=>$info['gateway_amount'],'description'=>$CFG->deposit_fiat_desc,'request_status'=>$CFG->request_pending_id,'request_type'=>$CFG->request_deposit_id,'invoice_id'=>$invoice_id,'gateway_id'=>$gateway_info['id']));
			
			if ($gateway_info['key'] == 'astropay')
				$processor->pay(array('x_login'=>$gateway_info['api_key'],'x_trans_key'=>$gateway_info['api_secret'],'x_card_num'=>$card_number1,'x_card_code'=>$info['card_cvv'],'x_exp_date'=>$exp_date,'x_amount'=>$info['gateway_amount'],'x_unique_id'=>$invoice_id,'x_invoice_num'=>$invoice_id,'x_currency'=>$CFG->currencies[$info['gateway_currency']]['currency']));
		}
		else if ($info['gateway_type'] == 'gateway') {
			$gateway_info = DB::getRecord('gateways',$info['gateway_id'],0,1);
			
			$processor = new PaymentsProcessor();
			$processor->setGateway($gateway_info['key']);
			$request_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>User::$info['id'],'currency'=>$info['gateway_currency'],'amount'=>$info['gateway_amount'],'description'=>$CFG->deposit_fiat_desc,'request_status'=>$CFG->request_pending_id,'request_type'=>$CFG->request_deposit_id,'invoice_id'=>$invoice_id,'gateway_id'=>$gateway_info['id']));
			
			if ($gateway_info['key'] == 'skrill')
				$processor->pay(array('email'=>$gateway_info['api_key'],'password'=>$gateway_info['api_secret'],'amount'=>$info['gateway_amount'],'currency'=>$CFG->currencies[$info['gateway_currency']]['currency'],'bnf_email'=>$info['gateway_user'],'subject'=>Lang::string('deposit-fiat-instructions'),'note'=>'','frn_trn_id'=>$invoice_id));
			else if ($gateway_info['key'] == 'webmoney')
				$processor->pay(array('signer_wmid'=>$gateway_info['api_key'],'password'=>$gateway_info['api_secret'],'amount'=>$info['gateway_amount'],'currency'=>$CFG->currencies[$info['gateway_currency']]['currency'],'bnf_email'=>$info['gateway_user'],'subject'=>Lang::string('deposit-fiat-instructions'),'note'=>'','frn_trn_id'=>$invoice_id));
			
		}
		else if ($gateway_type1 == 'bank_account') {
				
		}
	}
	
	public static function processOffsiteResponse($info) {
		
	}
	
	public static function redirectVars($info) {
		global $CFG;
		
		$invoice_id = md5(uniqid(mt_rand(),true));
		$url_ok = $CFG->baseurl.'deposit.php?action=complete&invoice_id='.$invoice_id;
		$url_notify = $CFG->baseurl.'deposit.php?action=notify&invoice_id='.$invoice_id;
		$url_cancel = $CFG->baseurl.'deposit.php?action=cancel&invoice_id'.$invoice_id;
		
		if ($gateway_type1 == 'credit_card') {
			$card_type_info = DB::getRecord('gateway_card_types',$info['card_type'],0,1);
			$gateway_info = DB::getRecord('gateways',$card_type_info['gateway'],0,1);
			$exp_date = str_pad($info['card_expiration_month'],2,'0',STR_PAD_LEFT).'/'.$info['card_expiration_year'];
			
			$processor = new PaymentsProcessor();
			$processor->setGateway($gateway_info['key']);
			$request_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>User::$info['id'],'currency'=>$info['gateway_currency'],'amount'=>$info['gateway_amount'],'description'=>$CFG->deposit_fiat_desc,'request_status'=>$CFG->request_pending_id,'request_type'=>$CFG->request_deposit_id,'invoice_id'=>$invoice_id,'gateway_id'=>$gateway_info['id']));
			
			if ($gateway_info['key'] == 'paysafecard')
				return array('username'=>$gateway_info['api_key'],'password'=>$gateway_info['api_secret'],'amount'=>$info['gateway_amount'],'mtid'=>$invoice_id,'currency'=>$CFG->currencies[$info['gateway_currency']]['currency'],'MerchantClientId'=>User::$info['email'],'mCId'=>User::$info['email'],'okUrl'=>$url_ok,'nokUrl'=>$url_cancel,'pnUrl'=>$url_notify,'subId'=>'','clientIp'=>$CFG->client_ip,'dispositionrestrictions'=>'','shopId'=>$CFG->exchange_name,'shoplabel'=>$CFG->exchange_name);
			else if ($gateway_info['key'] == 'skrill')
				return $processor->prepare(array('pay_to_email'=>$gateway_info['api_key'],'amount'=>$info['gateway_amount'],'currency'=>$CFG->currencies[$info['gateway_currency']]['currency'],"language"=>"EN",'transaction_id'=>$invoice_id,'return_url'=>$url_ok,'cancel_url'=>$url_cancel,'status_url'=>$url_notify,'return_url_target'=>3,'cancel_url_target'=>3,'logo_url'=>$CFG->baseurl.'images/bitdoviz.png','dynamic_descriptor'=>$CFG->exchange_name,'rid'=>User::$info['user']));
		}
		else if ($gateway_type1 == 'gateway') {
			$gateway_info = DB::getRecord('gateways',$info['gateway_id'],0,1);
			
			$processor = new PaymentsProcessor();
			$processor->setGateway($gateway_info['key']);
			$request_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>User::$info['id'],'currency'=>$info['gateway_currency'],'amount'=>$info['gateway_amount'],'description'=>$CFG->deposit_fiat_desc,'request_status'=>$CFG->request_pending_id,'request_type'=>$CFG->request_deposit_id,'invoice_id'=>$invoice_id,'gateway_id'=>$gateway_info['id']));
			
			if ($gateway_info['key'] == 'skrill')
				$processor->pay(array('username'=>$gateway_info['api_key'],'password'=>$gateway_info['api_secret'],'amount'=>$info['gateway_amount'],'mtid'=>$invoice_id,'currency'=>$CFG->currencies[$info['gateway_currency']]['currency'],'MerchantClientId'=>User::$info['email'],'mCId'=>User::$info['email']));
				
		}
		else if ($gateway_type1 == 'bank_account') {
		
		}
	}
}