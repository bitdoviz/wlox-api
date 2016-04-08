<?
use baibaratsky\WebMoney\WebMoney;
use baibaratsky\WebMoney\Signer;
use baibaratsky\WebMoney\Request\Requester\CurlRequester;
use baibaratsky\WebMoney\Api\X\X2;

class WebmoneyController extends GatewayBase {

    public function __construct(){
        parent::__construct();
    }

    public function validate($params){
    	/*
        $req_fields = explode(' ','signer_wmid requester_id signer key_file_path key_file_password name');

        foreach($req_fields as $f){
            if(empty($params[$f]))
                throw new Exception("param {$f} is empty.Params required:".print_r($req_fields,1)); 
        }
		*/
        return $params;
    }

    function test(){
        $params = array (
            "signer_wmid" => "444465751111",
            "requester_id" => "23423423424323",
            "signer" => "234234234234",
            "key_file_path" => "/dfsdf/ds/fs/dfs/df",
            "key_file_password" => "as476asdASAZ123123a!",
            "name" => "Z000000000000",
        );
        return $this->pay($params);
    }

    // the 
    function pay($p){

        $errors = false;
        $result = false;
        
        try {
            $this->validate($p);
            $data_string = json_encode($p);
             
            $ch = curl_init('https://merchant.webmoney.ru/conf/xml/XMLTransRequest.asp');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
            );
             
            $result = curl_exec($ch);
            
            
            

            // If you don’t want to use the WM root certificate to protect against DNS spoofing, pass false to the CurlRequester constructor
            $webMoney = new WebMoney\WebMoney(new CurlRequester);

            $request = new Request;
            $request->setSignerWmid($p['signer_wmid']); // yout wmid
            $request->setRequestedWmid($p['requester_id']); // requested wmid

            $request->sign(new Signer($p['signer'], $p['key_file_path'] , $p['key_file_password'] ));

            // You can access the request XML: $request->getData()

            if ($request->validate()) {
                /** @var Response $response */
                $response = $webMoney->request($request);

                // The response from WebMoney is here: $response->getRawData()

                if ($response->getReturnCode() === 0) {
                    $result = $response->getPurseByName($p['name'])->getAmount();
                } else {
                    $errors[] = $response->getReturnDescription();
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
