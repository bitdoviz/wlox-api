<?
/* https://gist.github.com/tianlim/5887436
https://www.ecopayz.com/ar-SA/Resources
https://github.com/dercoder/omnipay-ecopayz/blob/master/src/Omnipay/Ecopayz/Gateway.php
*/

class Ecopayz extends GatewayBase {

    public function __construct(){
        parent::__construct();
    }

    public function validate($params){
        return $params;
    }
 
    public function test(){
        //<form action="https://www.moneybookers.com/app/payment.pl" method="post",

        $p = array (
            "pay_to_email" => "merchant@skrill.com",
            "transaction_id" => "A10005",
            "return_url" => "http://www.moneybookers.com/payment_made.html",
            "cancel_url" => "http://www. moneybookers.com/payment_cancelled.html",
            "status_url" => "https://www. moneybookers.com/process_payment.cgi",
            "language" => "EN",
            "merchant_fields" => "customer_number, session_id",
            "customer_number" => "C1234",
            "session_ID" => "A3DFA2234",
            "pay_from_email" => "payer@skrill.com",
            "amount2_description" => "Product Price:",
            "amount2" => "29.90",
            "amount3_description" => "Handling Fees & Charges:",
            "amount3" => "3.10",
            "amount4_description" => "VAT (20%):",
            "amount4" => "6.60",
            "amount" => "39.60",
            "currency" => "GBP",
            "firstname" => "John",
            "lastname" => "Payer",
            "address" => "Payerstreet",
            "postal_code" => "EC45MQ",
            "city" => "Payertown",
            "country" => "GBR",
            "detail1_description" => "Product ID:",
            "detail1_text" => "4509334",
            "detail2_description" => "Description:",
            "detail2_text" => "Romeo and Juliet (W. Shakespeare)",
            "detail3_description" => "Special Conditions:",
            "detail3_text" => "5-6 days for delivery",
            "confirmation_note" => "Sample merchant wishes you pleasure reading your new book!",
            "submit" => "Pay!",
        );
        return $this->pay($p);
    }
    

    public function pay($params){
        $url = 'https://www.moneybookers.com/app/payment.pl';
        return $this->postArray($url,$params);

        /*
        // https://test.resurs.com/docs/display/ecom/Test+Data
        <form action="https://www.moneybookers.com/app/payment.pl" method="post" target="_blank">
        <input type="hidden" name="pay_to_email" value="merchant@skrill.com">
        <input type="hidden" name="transaction_id" value="A10005">
        <input type="hidden" name="return_url" value="http://www.moneybookers.com/payment_made.html">
        <input type="hidden" name="cancel_url" value="http://www. moneybookers.com/payment_cancelled.html">
        <input type="hidden" name="status_url" value="https://www. moneybookers.com/process_payment.cgi">
        <input type="hidden" name="language" value="EN">
        <input type="hidden" name="merchant_fields" value="customer_number, session_id">
        <input type="hidden" name="customer_number" value="C1234">
        <input type="hidden" name="session_ID" value="A3DFA2234">
        <input type="hidden" name="pay_from_email" value="payer@skrill.com">
        <input type="hidden" name="amount2_description" value="Product Price:">
        <input type="hidden" name="amount2" value="29.90">
        <input type="hidden" name="amount3_description" value="Handling Fees & Charges:">
        <input type="hidden" name="amount3" value="3.10">
        <input type="hidden" name="amount4_description" value="VAT (20%):">
        <input type="hidden" name="amount4" value="6.60">
        <input type="hidden" name="amount" value="39.60">
        <input type="hidden" name="currency" value="GBP">
        <input type="hidden" name="firstname" value="John">
        <input type="hidden" name="lastname" value="Payer">
        <input type="hidden" name="address" value="Payerstreet">
        <input type="hidden" name="postal_code" value="EC45MQ">
        <input type="hidden" name="city" value="Payertown">
        <input type="hidden" name="country" value="GBR">
        <input type="hidden" name="detail1_description" value="Product ID:">
        <input type="hidden" name="detail1_text" value="4509334">
        <input type="hidden" name="detail2_description" value="Description:">
        <input type="hidden" name="detail2_text" value="Romeo and Juliet (W. Shakespeare)">
        <input type="hidden" name="detail3_description" value="Special Conditions:">
        <input type="hidden" name="detail3_text" value="5-6 days for delivery">
        <input type="hidden" name="confirmation_note" value="Sample merchant wishes you pleasure reading your new book!">
        <input type="submit" value="Pay!">
        </form>
        */
    }

}
