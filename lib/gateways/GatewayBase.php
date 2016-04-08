<?
 
class GatewayBase {

    public $check_request = 1;
    public $post_timeout = 30;
    public $validate = false;

    public function __construct($params=false){
       // $params = empty($params) ? $this->setDefaultParams() : $params;
       // $this->setParams($defaultParams);
    }

    public function defaultParams(){
        return array(
            'check_request' => 1,
            'timeout'=>5,
        );
    }

    public function setParams($params){
        foreach($params as $k=>$v)
            $this->$k = $v;
    }

    public function validate($params){
        throw new Exception('validation method is not defined.'); 
    }

    // check for multiple fields in array, if one of those is empty, throw exception.
    function checkParams($params,$needed){
        foreach($needed as $n)
            if(empty($params[$n]))
                throw new Exception("parameter [{$n}] is empty.\nparameters needed (".implode(', ',$needed).')');

        return $params;
    }

    function getTestParams(){
        throw new Exception('getTestParams method is not defined.'); 
    }

    public function test(){
        throw new Exception('test method is not defined.'); 
    }
    /*
    public function pay($params){
        if(empty($this->validate))
            $this->validate = new Validations();
        
        throw new Exception('pay method is not defined.'); 
    }
    */

    // post array to some url
    function postArray($url,$fields){
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,$this->post_timeout); 
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->post_timeout); //timeout in seconds

        $out['result'] = $request = curl_exec($ch);
        curl_close($ch);

        if(empty($this->check_request))
            return $out;

        $status = $this->checkRequest($request);            
        $out = array_merge($out,$status);

        unset($out['result']);

        return $out;
    }

    function cleanHTML($html){
        $bad  = array('<',"\n");
        $good = array(' <','');
        $html = str_replace($bad,$good,$html);
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/i', "", $html);    // remove javascript
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/i', "", $html);      // remove css
        $txt = strip_tags($html);
        $temp = explode("\n",$txt);
        $out = false;

        foreach($temp as $k=>$v){ // trim rows and delete empty ones.
            $v = trim($v);
            if($v=='') continue;
            $out[]=$v;
        }

        return implode("\n",$out);
    }
    
    function isXML($xmlstr) {
    	libxml_use_internal_errors(true);
    	$doc = simplexml_load_string($xmlstr);
 
    	if (!$doc) {
    		$errors_detected = libxml_get_errors();
    		$errors = array();
    		
    		//foreach ($errors_detected as $error) {
    			//$errors[] = display_xml_error($error, $xml);
    		//}
    		
    		//if (count($errors) > 0)
    			//trigger_error(print_r($errors,1));
    		
    		libxml_clear_errors();
    		return false;
    	}
    	else
    		return @json_decode(@json_encode($doc),1);
    }


    // @param txt(string) request
    // check if got warnings or errors
    // return array
    public function checkRequest($txt,$clean=1){
        $errors   = array('error','404','not allowed','fatal','critical');
        $warnings = array('warning','unable','undefined','not allowed','invalid','unable','wrong');

        $status = array(
            'errors'   => false,
            'warnings' => false,
        );
        
        $txt_original = $txt;
        $xml = self::isXML($txt);
        if ($xml)
        	$txt = $xml;
      	else if($clean)
            $txt = $this->cleanHTML($txt);

        foreach($errors as $e)
            if(stristr($txt_original,$e)){
                $status['errors']=true;
                break;
            }

        foreach($warnings as $w)
            if(stristr($txt_original,$w)){
                $status['warnings']=true;
                break;
            }

        $status['response'] = $txt;
        return $status;
    }

    public function addError($name='',$details=false){
         $this->errors[$name] = $details;
    }

    public function getErrors(){
        return $this->errors;
    }

    function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

}