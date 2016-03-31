<?
include('gateways/GatewayBase.php');

class PaymentProcessor {

    // astropayOK ecopayz paysafecardOK
    // webmoneyOK skrillOK okpayOK perfectmoneyOK

    private $allowed_gateways = array('skrill','okpay','webmoney','perfectmoney','paysafecard','astropay','ecopayz','berraco');

    public function __construct(){
    	
    }

    public function returnWs($data){
        echo json_encode($data);
    }

    // set this->gateway or thrown exception
    public function setGateway($gateway){
        $g = trim(strtolower($gateway));

        if( !in_array($g, $this->allowed_gateways) )
            throw new Exception('GATEWAY NOT ALLOWED.Please one of this: '.print_r( $this->allowed_gateways ,1));

        try {
            $className = ucwords($g);
            // start payments gateway factory

            $path = "gateways/{$className}.php";

            // try to load controller class or just load class directly from folder
            if(file_exists($path)){

                include($path);
                return $this->gateway = new $className();

            } else { // have a folder with multiple files

                include("gateways/{$className}/{$className}Controller.php");
                $className = "{$className}Controller";
                return $this->gateway = new $className();

            }
        } catch (Exception $e) {
            throw new Exception(  $e->getMessage() );
        }

    }

    // @param 'gateway': string
    // @param 'payment': array
    public function pay($params){
        try {
            return $this->gateway->pay($params);

        } catch (Exception $e) {
            $data = array(
                'status'  => 'ERROR',
                'details' => $e->getMessage());
            return $this->returnWs($data);
        }
    }

}
