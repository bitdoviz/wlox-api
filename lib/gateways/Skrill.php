<?
class Skrill extends GatewayBase {

    public function __construct(){
        parent::__construct();
    }

    public function validate($params){
        return $params;
    }
 
    public function test(){
    	global $CFG;
    	
        $p = array (
            "pay_to_email" => "merchant@skrill.com",
            "transaction_id" => "A10005",
            "return_url" => $CFG->baseurl.'deposit.php?action=complete',
            "cancel_url" => $CFG->baseurl.'deposit.php?action=cancel',
            "status_url" => $CFG->baseurl.'deposit.php?action=payment_details',
            'return_url_target' => 3,
            'cancel_url_target' => 3,
            'logo_url' => $CFG->baseurl.'images/bitdoviz.png',
            'dynamic_descriptor'=> 'CME Habob Inc.',
            'rid' => 'cutomer_email@site.com',
            'amount' => 55,
            'currency' => 'USD',
            "language" => "EN",
            "merchant_fields" => "transaction_id,amount,currency"
        );
        return $this->pay($p);
    }
    
	// this can receive (1) params from off-site payment for or (2) direct transfer params between accounts
    public function pay($params){
    	$type = $params['type'];
    	unset($params['type']);
    	
    	if ($type == 'transfer') {
    		$params['action'] = 'prepare';
    		$url = 'https://www.skrill.com/app/pay.pl';
    		$result = $this->postArray($url,$params);
    		
    		if (!$result)
    			return array('error'=>'Gateway connection problem. Please try again later.');
    		if (!empty($result['warnings']) || !empty($result['errors']))
    			return array('error'=>$result['response']);
    		if (empty($result['response']['response']['sid']))
    			return array('error'=>'Gateway connection problem. Please try again later.');
    		
    		$url = 'https://www.skrill.com/app/pay.pl';
    		$result = $this->postArray($url,array('action'=>'transfer','sid'=>$result['response']['sid']));
    		if (!$result)
    			return array('error'=>'Gateway connection problem. Please try again later.');
    		if (!empty($result['warnings']) || !empty($result['errors']))
    			return array('error'=>$result['response']);
    		
    		return ($result['response']['response']['Status']['processed'] == 'Y');
    	}
    	else if ($type == 'external') {
    		if ($params['status'] == 2)
    			return $params['transaction_id'];
    		else	
    			return false;
    	}
    	
    	return false;
    }

}
