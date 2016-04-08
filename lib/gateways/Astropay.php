<?php

include('gateways/AstroPayCard/AstroPayCard.class.php');


class Astropay extends GatewayBase {

    public function __construct(){
        parent::__construct();
    }

    // validations from Okpay gatewoy to perform a request
    public function validate($params){
        $needed = array (
             //Cardholder data
            'x_card_num' ,
            'x_card_code',
            'x_exp_date' ,

            //Transaction data
            'x_amount'      ,
            'x_unique_id'   ,
            'x_invoice_num' ,
        );
        return $this->checkParams($params,$needed);
    }

    public function test(){
        //<form action="https://www.moneybookers.com/app/payment.pl" method="post",
        $p = array (
             //Cardholder data
            'x_card_num'  => "1175000010737129",
            'x_card_code' => "0679",
            'x_exp_date'  => "12/2013",

            //Transaction data
            'x_amount'       => "1.06",
            'x_unique_id'    => "1234-987",
            'x_invoice_num'  => "pepito-097018813",
        );

        return $this->pay($p);
    }

    function pay($p=false,$timeout=false){

            $result = false;
            $errors = false;
            $r = false;

            $timeout = empty($timeout) ? $this->post_timeout : $timeout;

            try {
                $this->validate($p);

                //AstroPayCard class instance
                $ap = new AstroPayCard($p['x_login'],$p['x_trans_key']);
                unset($p['x_login']);
                unset($p['x_trans_key']);

                //Making an AUTH_CAPTURE transaction, this method response has the result
                $raw_response = $ap->auth_capture_transaction($p['x_card_num'], $p['x_card_code'], $p['x_exp_date'], $p['x_amount'], $p['x_unique_id'], $p['x_invoice_num']);

                if($this->isJson($raw_response)){
                    $r = json_decode($raw_response,1);
                } else {
                    //Use only in "string" format
                    $response = explode("|", $raw_response);

                    $r['response_code'] = $response[0];
                    $r['response_subcode'] = $response[1];
                    $r['response_reason_code'] = $response[2];
                    $r['response_reason_text'] = $response[3];
                    $r['response_authorization_code'] = $response[4];
                    $r['response_transaction_id'] = $response[6];
                    $r['response_amount'] = $response[10];
                }

                //Evaluate if the transaction was succesfull or not
                if ($r['response_code'] == 1) {
                    if ($p['x_amount'] == $r['response_amount']) {
                        $result = "Transaction OK!";
                        //TODO!: Save $response_transaction_id and $response_authorization code for future use
                    } else {
                        $errors[] = "Error: Invalid amount check.";
                    }
                } else {
                    //If there are an error, it will be printed here.
                   $errors[] = "An exception has occurred in transaction process: {$r['response_reason_text']} ".
                        "(code: {$r['response_code']}, subcode: {$r['response_subcode']}, reason_code: {$r['response_reason_code']})";
                }

            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        
            if(is_array($errors) && (is_array($r)) )
                $result = $r;

            return array (
                'result'   => $result,
                'errors'   => $errors,
                'warnings' => false,
            );  
    }

}

