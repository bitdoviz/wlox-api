<?

class Perfectmoney extends GatewayBase {

    public function __construct(){
        parent::__construct();
    }

    // validations from Okpay gatewoy to perform a request
    public function validate($params){

        $needed = array(
            'AccountID'    ,
            'PassPhrase'   ,
            'Payer_Account',
            'Amount'       ,
            'PAYMENT_ID'   ,
        );

        return $this->checkParams($params,$needed);
    }

    public function test(){
        //  https://perfectmoney.is/acct/confirm.asp?AccountID=myaccount&PassPhrase=mypassword&
        //  Payer_Account=U987654&Payee_Account=U1234567&Amount=1&PAY_IN=1&PAYMENT_ID=1223', 'rb');
        $p = array(
            'AccountID'    => 'myaccount',
            'PassPhrase'   => 'mypassword',
            'Payer_Account'=> 'U987654',
            'Amount'       => '1',
            'PAYMENT_ID'   => '1223',
        );
        return $this->pay($p);
    }

    function pay($params=false,$timeout=false){

           // based on https://perfectmoney.is/acct/samples/parse_spend.txt
 
           $result = false;
           $errors = false;

           $timeout = empty($timeout) ? $this->post_timeout : $timeout;

           try {
                $this->validate($params);

                /*
                $opts = array(
                   'http'=>array(
                       'method'     => 'GET',
                       'timeout'    => $timeout,  //in seconds
                   )
                );
                $context = stream_context_create($opts);
                */

                $url = 'confirm.asp?';

                foreach($params as $k=>$v)
                    $url.="{$k}={$v}&";

                //$params = "AccountID=myaccount&PassPhrase=mypassword&Payer_Account=U987654&Payee_Account=U1234567&Amount=1&PAY_IN=1&PAYMENT_ID=1223";

                $url = 'https://perfectmoney.is/acct/'.urlencode($url);

                $f=fopen($url, 'rb');

                if($f===false) {
                   $errors[] = 'unable to get url '.$url;
                } else {
                   // getting data
                   $out=array(); $out="";
                   while(!feof($f)) $out.=fgets($f);
                   fclose($f);

                   // searching for hidden fields
                   if(!preg_match_all("/<input name='(.*)' type='hidden' value='(.*)'>/", $out, $res, PREG_SET_ORDER)){
                       $errors[] = 'Invalid output';
                   } else {
                       $ar="";
                       foreach($res as $item){
                           $key=$item[1];
                           $ar[$key]=$item[2];
                       }
                       $result = $ar;
                   }
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }

            return array (
                'result'   => $result,
                'errors'   => $errors,
                'warnings' => false,
            );  
    }

}

