<?

// based on examples from:
// https://www.paysafecard.com/en/business/support/downloads/
// link:Classic payment-> PHP API example

include('gateways/Paysafecard/class.php');


class Paysafecard extends GatewayBase {

    public function __construct(){
        parent::__construct();
        $e = $this->systemTest();

        if(is_array($e)) 
               return array(
                'result'   => false,
                'errors'   => $e,
                'warnings' => false,
               );
    }

    // validations from Okpay gatewoy to perform a request
    public function validate($params){

        $needed = array(
            'mtid' , // //must be unique for each transaction (createDisposition).
            'currency',
            'amount',
            'MerchantClientId' ,
            'mCId',
            'username',
            'password',
            'current_url', // http://something.com/processor.php
        );

        return $this->checkParams($params,$needed);
    }

    public function test(){
        $p = array (
            'mtid' => 'Paysafecard-test_'.time().'_'.rand('1','9999999'),
            'currency' => 'EUR',
            'amount' => '0.02',
            'MerchantClientId' => 'somebuddy@somewhere.com', // - e.g. email address>
            'mCId' => 'myClientI2d',
            'username' => 'test_user',
            'password' => '1kj2h3k1j23h12',
            'current_url' => 'mail.google.com',
        );
        return $this->pay($p);
    }

    function systemTest(){
        $errors = false;

        if(phpversion() < 5)
             $errors[] = 'PHP version is lower than 5, system version is '.phpversion();

        $extensions = get_loaded_extensions();
        if(!in_array('soap',$extensions))
            $errors[] = 'Soap extension is not loaded';

        return $errors;
    }
    
    function pay($params=false){
    	
    }

    function prepare($params=false){

           $result = false;
           $errors = false;

           $timeout = empty($timeout) ? $this->post_timeout : $timeout;

           try {
                $this->validate($params);
                $this->setSystemVars($params);

                $p = $this->params;

                $obj = new SOPGClassicMerchantClient( $p['debug'], $p['sys_lang'], $p['auto_correct'], $p['mode'] );
              
                $obj->merchant( $p['username'], $p['password'] );

                $obj->setCustomer( $p['amount'], $p['currency'], $p['mtid'], $p['mCId'] );

                $obj->setUrl( $p['okUrl'], $p['nokUrl'], $p['pnUrl'] );
 
                $result = $obj->createDisposition();

                if ( $result == false ) 
                    $errors [] = $test->getLog();

           } catch (Exception $e) {
                $error =  $e->getMessage();
                if(stristr($error, "SOAP-ERROR: Parsing WSDL: Couldn't load from 'https://soa.paysafecard.com/psc/services/PscService?wsdl"))
                    $errors [] = 'please contact paysafe integration@paysafecard.com [server ip is not added in paysafe white lists]';

                $errors[] = $error;
           }

           if(is_object($result))
                $result = get_object_vars($result);

           return array (
                'result'   => $result,
                'errors'   => $errors,
                'warnings' => false,
           );  
    }


    // set system vars AND params recieved.
    function setSystemVars($p){

        $p['ok_url']  = rawurlencode( $p['current_url'].'?ok=true&mtid='.$p['mtid'].'&cur='.$p['currency'].'&amo='.$p['amount'] );
        //NOK-URL - nok=true

        //http://www.your-domain.com/psc/index.php?nok=true
        $p['nok_url'] = rawurlencode( $p['current_url'].'?nok=true' );

        //PN-URL - pn=true
        //http://www.your-domain.com/psc/index.php?pn=true&mtid='.$mtid
        $p['pn_url'] = rawurlencode( $p['current_url'].'?pn=true&mtid='.$p['mtid'].'&cur='.$p['currency'].'&amo='.$p['amount'] );

        // force responses to english (not deutch!)
        $p['sys_lang'] = 'en'; // empty($p['sys_lang']) ? 'en' : $p['sys_lang'] ;

        //Debug true/false
        $p['debug'] = isset($p['debug']) ? $p['debug'] : false;

        $p['auto_correct'] = isset($p['auto_correct']) ? $p['auto_correct'] : false;

        //test or live SYSTEM
        $p['mode'] = isset($p['mode']) ? $p['mode'] : 'live';
 
        return $this->params = $p;
    }
 

}

 
