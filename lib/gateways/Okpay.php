<?

class Okpay extends GatewayBase {

    public function __construct(){
        parent::__construct();
    }

    // validations from Okpay gatewoy to perform a request
    public function validate($params){
        $needed = array('secWord','WalletID','Currency');
        return $this->checkParams($params,$needed){
    }

    public function test(){
        //<form action="https://www.moneybookers.com/app/payment.pl" method="post",
        $p = array (
           'secWord'    => "YourWalletAPIPassword", // wallet API password
           'WalletID'   => "OKxxxxxxxxx", // wallet ID
           'Currency'   => 'EUR',
        );
        return $this->pay($p);
    }

    function pay($params=false,$timeout=false){
 
           $result = false;
           $errors = false;

           $timeout = empty($timeout) ? $this->post_timeout : $timeout;

           try {
                $this->validate($params);

                // Connecting to SOAP
                $opts = array(
                   'http'=>array(
                       'user_agent' => 'PHPSoapClient',
                       'timeout'    => $timeout,  //in seconds
                   )
                );
                $context = stream_context_create($opts);
                $client  = new SoapClient("https://api.okpay.com/OkPayAPI?wsdl",
                                           array(
                                               'stream_context' => $context,
                                               'cache_wsdl' => WSDL_CACHE_NONE,
                                               // 'trace' => true,
                                               // 'cache_wsdl' => WSDL_CACHE_MEMORY,
                                           )
                                         );

                $secWord  = $params['secWord']; // wallet API password
                $authString = $secWord.':'.gmdate("Ymd:H");
                $secToken = hash('sha256', $authString);
                $secToken = strtoupper($secToken);
            
                $obj->WalletID = $params['WalletID'];
                $obj->SecurityToken = $secToken;
                $obj->Currency = $params['Currency'];

                $webService = $client->Wallet_Get_Currency_Balance($obj);
                $result = $webService->Wallet_Get_Currency_BalanceResult;
  
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
