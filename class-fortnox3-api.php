<?php
class WCF_API{

    /** @public String base URL */
    public $api_url;

    /** @public Client Secret token */
    public $client_secret = 'AQV9TbDU1k';

    /** @public String Authorization code */
    public $authorization_code;

    /** @public String Access token */
    public $access_token;

    /** @public String api key */
    public $api_key;

    /** @public String api key */
    public $has_error;
    /**
     *
     */
    function __construct() {

        $options = get_option('woocommerce_fortnox_general_settings');
        $this->api_url = "https://api.fortnox.se/3/";
        $this->authorization_code = $options['authorization_code'];
        $this->api_key = $options['api-key'];
        $this->access_token = get_option('fortnox_access_token');
        if(!$this->access_token){
            $this->login();
        }
    }

    /**
     * Builds url
     *
     * @access public
     * @param mixed $urlAppendix
     * @return string
     */
    private function build_url($urlAppendix){
        return $this->api_url.$urlAppendix;
    }

    /**
     * Creates a HttpRequest and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @return bool
     */
    public function create_api_validation_request(){
        logthis("API VALIDATION");
        if(!isset($this->api_key)){
            return false;
        }

//        $ch = curl_init();
//        $url = "http://plugapi.consuasor.se/api.php?api_key=" . $this->api_key;
//        curl_setopt ($ch, CURLOPT_URL, $url);
//        curl_setopt ($ch, CURLOPT_TIMEOUT,60);
//        curl_setopt ($ch, CURLOPT_VERBOSE,0);
//        curl_setopt ($ch, CURLOPT_POST, 0);
//        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
//        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
//        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
//        curl_setopt($ch,CURLOPT_TIMEOUT,3);
//        $data = curl_exec($ch);
//        curl_close($ch);
//        logthis($url);
//        logthis($data);
//        if($data == 'NOT OK'){
//            return false;
//        }
//
//        $options = get_option('woocommerce_fortnox_general_settings');
//        $options['api-key'] = $data;
//        //update_option('woocommerce_fortnox_general_settings', $options);
//        return true;
        logthis("BEGIN VALIDATION");
        $ret = $this->create_license_validation_request();

        if(!$ret){
            return $this->create_api_validation_request_new_key();
        }
        else{
            return $ret;
        }

    }

    /**
     * Creates a HttpRequest and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @return bool
     */
    public function create_api_validation_request_new_key(){
        logthis("API VALIDATION NEW KEY");

        $ch = curl_init();
        $url = "http://plugapi.consuasor.se/api_new_key.php?api_key=" . $this->api_key;
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_TIMEOUT,60);
        curl_setopt ($ch, CURLOPT_VERBOSE,0);
        curl_setopt ($ch, CURLOPT_POST, 0);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch,CURLOPT_TIMEOUT,3);
        $data = curl_exec($ch);
        curl_close($ch);
        logthis($data);
        if($data == 'NOT OK'){
            logthis("API VALIDATION NOT OK");
            return false;
        }

        $options = get_option('woocommerce_fortnox_general_settings');

        $new_options = array();
        foreach ($options as $key => $value) {
            logthis( $key);

            if($key != 'api-key'){
                $new_options[$key] = $value;
            }
            else{
                $new_options[$key] = $data;
            }

        };
        update_option('woocommerce_fortnox_general_settings', $new_options);
        logthis("FIRST KEY " . print_r($options, true));
        $options = get_option('woocommerce_fortnox_general_settings');
        $this->api_key = $options['api-key'];
        logthis("UPDATING KEY " . print_r($options, true));
        return true;
    }

    /**
     * Creates a HttpRequest and appends the given XML to the request and sends it For license key
     *
     * @access public
     * @param string $localkey
     * @return bool
     */
    public function create_license_validation_request($localkey=''){
        logthis("LICENSE VALIDATION " . $this->api_key);
        if(!isset($this->api_key)){
            return false;
        }
        $licensekey = $this->api_key;
        // -----------------------------------
        //  -- Configuration Values --
        // -----------------------------------
        // Enter the url to your WHMCS installation here
        //$whmcsurl = 'http://176.10.250.47/whmcs/';
        $whmcsurl = 'http://whmcs.onlineforce.net/';
        // Must match what is specified in the MD5 Hash Verification field
        // of the licensing product that will be used with this check.
        $licensing_secret_key = 'ak4763';
        //$licensing_secret_key = 'itservice';
        // The number of days to wait between performing remote license checks
        $localkeydays = 15;
        // The number of days to allow failover for after local key expiry
        $allowcheckfaildays = 5;

        // -----------------------------------
        //  -- Do not edit below this line --
        // -----------------------------------

        $check_token = time() . md5(mt_rand(1000000000, 9999999999) . $licensekey);
        $checkdate = date("Ymd");
        $domain = $_SERVER['SERVER_NAME'];
        logthis('SERVER:' . print_r($_SERVER, true));
        logthis('DOMÄM:' . $domain);
        $usersip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : $_SERVER['LOCAL_ADDR'];

        logthis('DOMÄM:' . $usersip);
        $dirpath = dirname(__FILE__);
        $verifyfilepath = 'modules/servers/licensing/verify.php';
        $localkeyvalid = false;
        if ($localkey) {
            $localkey = str_replace("\n", '', $localkey); # Remove the line breaks
            $localdata = substr($localkey, 0, strlen($localkey) - 32); # Extract License Data
            $md5hash = substr($localkey, strlen($localkey) - 32); # Extract MD5 Hash
            if ($md5hash == md5($localdata . $licensing_secret_key)) {
                $localdata = strrev($localdata); # Reverse the string
                $md5hash = substr($localdata, 0, 32); # Extract MD5 Hash
                $localdata = substr($localdata, 32); # Extract License Data
                $localdata = base64_decode($localdata);
                $localkeyresults = unserialize($localdata);
                $originalcheckdate = $localkeyresults['checkdate'];
                if ($md5hash == md5($originalcheckdate . $licensing_secret_key)) {
                    $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - $localkeydays, date("Y")));
                    if ($originalcheckdate > $localexpiry) {
                        $localkeyvalid = true;
                        $results = $localkeyresults;
                        $validdomains = explode(',', $results['validdomain']);
                        if (!in_array($_SERVER['SERVER_NAME'], $validdomains)) {
                            $localkeyvalid = false;
                            $localkeyresults['status'] = "Invalid";
                            $results = array();
                        }
                        $validips = explode(',', $results['validip']);
                        if (!in_array($usersip, $validips)) {
                            $localkeyvalid = false;
                            $localkeyresults['status'] = "Invalid";
                            $results = array();
                        }
                        $validdirs = explode(',', $results['validdirectory']);
                        if (!in_array($dirpath, $validdirs)) {
                            $localkeyvalid = false;
                            $localkeyresults['status'] = "Invalid";
                            $results = array();
                        }
                    }
                }
            }
        }
        if (!$localkeyvalid) {
            $postfields = array(
                'licensekey' => $licensekey,
                'domain' => $domain,
                'ip' => $usersip,
                'dir' => $dirpath,
            );
            if ($check_token) $postfields['check_token'] = $check_token;
            $query_string = '';
            foreach ($postfields AS $k=>$v) {
                $query_string .= $k.'='.urlencode($v).'&';
            }
            if (function_exists('curl_exec')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $whmcsurl . $verifyfilepath);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $data = curl_exec($ch);
                curl_close($ch);
            } else {
                $fp = fsockopen($whmcsurl, 80, $errno, $errstr, 5);
                if ($fp) {
                    $newlinefeed = "\r\n";
                    $header = "POST ".$whmcsurl . $verifyfilepath . " HTTP/1.0" . $newlinefeed;
                    $header .= "Host: ".$whmcsurl . $newlinefeed;
                    $header .= "Content-type: application/x-www-form-urlencoded" . $newlinefeed;
                    $header .= "Content-length: ".@strlen($query_string) . $newlinefeed;
                    $header .= "Connection: close" . $newlinefeed . $newlinefeed;
                    $header .= $query_string;
                    $data = '';
                    @stream_set_timeout($fp, 20);
                    @fputs($fp, $header);
                    $status = @socket_get_status($fp);
                    while (!@feof($fp)&&$status) {
                        $data .= @fgets($fp, 1024);
                        $status = @socket_get_status($fp);
                    }
                    @fclose ($fp);
                }
            }
            if (!$data) {
                $localexpiry = date("Ymd", mktime(0, 0, 0, date("m"), date("d") - ($localkeydays + $allowcheckfaildays), date("Y")));
                if ($originalcheckdate > $localexpiry) {
                    $results = $localkeyresults;
                } else {
                    $results = array();
                    $results['status'] = "Invalid";
                    $results['description'] = "Remote Check Failed";
                    return $results;
                }
            } else {
                preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $matches);
                $results = array();
                foreach ($matches[1] AS $k=>$v) {
                    $results[$v] = $matches[2][$k];
                }
            }

            logthis(print_r($results, true));
            if (!is_array($results)) {
                die("Invalid License Server Response");
            }

            if ($results['md5hash']) {
                if ($results['md5hash'] != md5($licensing_secret_key . $check_token)) {
                    $results['status'] = "Invalid";
                    $results['description'] = "MD5 Checksum Verification Failed";
                    return $results;
                }
            }
            if ($results['status'] == "Active") {
                $results['checkdate'] = $checkdate;
                $data_encoded = serialize($results);
                $data_encoded = base64_encode($data_encoded);
                $data_encoded = md5($checkdate . $licensing_secret_key) . $data_encoded;
                $data_encoded = strrev($data_encoded);
                $data_encoded = $data_encoded . md5($data_encoded . $licensing_secret_key);
                $data_encoded = wordwrap($data_encoded, 80, "\n", true);
                $results['localkey'] = $data_encoded;
            }
            $results['remotecheck'] = true;
        }

        logthis(print_r($results, true));

        unset($postfields,$data,$matches,$whmcsurl,$licensing_secret_key,$checkdate,$usersip,$localkeydays,$allowcheckfaildays,$md5hash);
        logthis("API LICENSE" . print_r($results, true));
        return $results['status'] == 'Active' ? true : false;
    }


    /**
     * Creates a HttpRequest for creation of an invoice and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $xml
     * @return bool
     */
    public function create_invoice_request($xml){

        return $this->make_post_request($this->build_url("invoices"), $xml);
    }

    /**
     * Creates a HttpRequest for setting an invoice with given id as bookkeot and sends it to Fortnox
     *
     * @access public
     * @param mixed $id
     * @return bool
     */
    public function create_invoice_bookkept_request($id){
        logthis("SET INVOICE AS BOOKKEPT REQUEST");
        return $this->make_put_request($this->build_url("invoices/". $id . "/bookkeep"));
    }

    /**
     * Creates a HttpRequest creation of an order and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $xml
     * @return bool
     */
    public function create_order_request($xml){
        logthis("CREATE ORDER REQUEST");
        return $this->make_post_request($this->build_url("orders"), $xml);
    }

    /**
     * Creates a HttpRequest updating an order and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $xml
     * @param int $orderId
     * @return bool
     */
    public function update_order_request($xml, $orderId){
        logthis("UPDATE ORDER REQUEST");
        return $this->make_put_request($this->build_url("orders/". $orderId . "/"), $xml);
    }

    /**
     * Creates a HttpRequest creation of an orderinvoice and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $documentNumber
     * @return bool
     */
    public function create_order_invoice_request($documentNumber){
        logthis("CREATE INVOICE REQUEST");
        return $this->make_put_request($this->build_url("orders/" . $documentNumber . "/createinvoice"));
    }

    /**
     * Creates the HttpRequest creation of a contact/customer and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $xml
     * @return bool
     */
    public function create_customer_request($xml){
        logthis("CREATE CONTACT PRICE REQUEST");
        return $this->make_post_request($this->build_url("customers"), $xml);
    }

    /**
     * Creates a HttpRequest for creation of a product and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $xml
     * @return bool
     */
    public function create_product_request($xml){
        logthis("CREATE PRODUCT REQUEST");
        return $this->make_post_request($this->build_url("articles"), $xml);
    }

    /**
     * Creates a HttpRequest for creation of product(for given sku)
     * price and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $xml
     * @return bool
     */
    public function create_product_price_request($xml){
        logthis("CREATE PRODUCT PRICE REQUEST");
        return $this->make_post_request($this->build_url("prices/"), $xml);
    }

    /**
     * Creates a HttpRequest for an article for given SKU
     *
     * @access public
     * @return mixed
     */
    public function get_article($sku){
        logthis("GET ARTICLE REQUEST " . $sku);
        return $this->make_get_request($this->build_url("articles/" . $sku));
    }

    /**
     * Creates a HttpRequest for fetching all customer and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @return array
     */
    public function get_customers(){
        logthis("GET CUSTOMER REQUEST");
        $response = $this->make_get_request($this->build_url("customers/?limit=500"));
        $customers = $response['CustomerSubset'];
        if($response['@attributes']['TotalPages'] > 1){

            $currentPage = $response['@attributes']['CurrentPage'];
            $totalPages = $response['@attributes']['TotalPages'];

            for($i = $currentPage + 1; $i <= $totalPages; $i++){
                $response = $this->make_get_request($this->build_url("customers/?limit=500&page=" . $i));
                $customers = array_merge($customers, $response['CustomerSubset']);
            }
        }
        logthis(print_r($customers, true));
        return $customers;
    }

    /**
     * Creates a HttpRequest for fetching all customerand appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @return bool
     */
    public function get_inventory(){
        logthis("GET INVENTORY REQUEST");
        return $this->make_get_request($this->build_url("articles/?limit=500"));
    }

    /**
     * Creates a HttpRequest and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @return bool
     */
    public function login(){

        if(!isset($this->api_key)){
            return false;
        }

        logthis("LOGIN");
        logthis($this->authorization_code);
        logthis($this->client_secret);
        $headers = array(
            'Accept: application/xml',
            'Authorization-Code: '.$this->authorization_code,
            'Client-Secret: '.$this->client_secret,
            'Content-Type: application/xml',
        );
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
        curl_setopt ($ch, CURLOPT_URL, $this->api_url);
        curl_setopt ($ch, CURLOPT_TIMEOUT,60);
        curl_setopt ($ch, CURLOPT_VERBOSE,0);
        curl_setopt ($ch, CURLOPT_POST, 0);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $data = curl_exec($ch);
        $arrayData = json_decode(json_encode(simplexml_load_string($data)), true);
        $this->access_token = $arrayData['AccessToken'];
        if($this->access_token){
            logthis("ACCESSTOKEN EXISTS");
            update_option( 'fortnox_access_token', $this->access_token, '', 'yes' );
        }
        else{
            $this->has_error = true;
        }
        logthis(print_r($arrayData, true));
        curl_close($ch);
        return false;
    }

    /**
     * Makes GET request
     *
     * @access private
     * @param mixed $url
     * @return string
     */
    private function make_get_request($url){
        $headers = array(
            'Accept: application/xml',
            'Access-Token: '.$this->access_token,
            'Client-Secret: '.$this->client_secret,
            'Content-Type: application/xml',
        );
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);

        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_TIMEOUT,60);
        curl_setopt ($ch, CURLOPT_VERBOSE,0);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $data = curl_exec($ch);
        curl_close($ch);

        //convert the XML result into array
        $arrayData = json_decode(json_encode(simplexml_load_string($data)), true);
        logthis(print_r($arrayData, true));

        //Send error to plugapi
        if (array_key_exists("Error", $arrayData)){
            logthis("FORTNOX ERROR");
            $this->post_error($url . " " . $arrayData['Message']);
        }

        return $arrayData;
    }

    /**
    * Makes POST request
    *
    * @access private
    * @param mixed $url
    * @param mixed $xml
    * @return string
    */
    private function make_post_request($url,$xml){
        $headers = array(
            'Accept: application/xml',
            'Access-Token: '.$this->access_token,
            'Client-Secret: '.$this->client_secret,
            'Content-Type: application/xml',
        );
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);

        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_TIMEOUT,60);
        curl_setopt ($ch, CURLOPT_VERBOSE,0);
        curl_setopt ($ch, CURLOPT_POST, 1);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $data = curl_exec($ch);
        curl_close($ch);

        //convert the XML result into array
        $arrayData = json_decode(json_encode(simplexml_load_string($data)), true);
        logthis(print_r($arrayData, true));

        //Send error to plugapi
        if (array_key_exists("Error", $arrayData)){
            logthis("FORTNOX ERROR");
            $this->post_error($url . " " . $arrayData['Message']);
        }

        return $arrayData;
    }

    /**
     * Makes PUT request
     *
     * @access private
     * @param mixed $url
     * @param mixed $xml
     * @return string
     */
    private function make_put_request($url,$xml=null){
        $headers = array(
            'Accept: application/xml',
            'Access-Token: '.$this->access_token,
            'Client-Secret: '.$this->client_secret,
            'Content-Type: application/xml',
        );
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);

        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_TIMEOUT,60);
        curl_setopt ($ch, CURLOPT_VERBOSE,0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

        if($xml!=null){
            curl_setopt ($ch, CURLOPT_POSTFIELDS, $xml);
        }
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $data = curl_exec($ch);
        curl_close($ch);

        //convert the XML result into array

        $array_data = json_decode(json_encode(simplexml_load_string($data)), true);
        logthis(print_r($array_data, true));

        //Send error to plugapi
        if (array_key_exists("Error", $array_data)){
            logthis("FORTNOX ERROR");
            $this->post_error($url . " " . $array_data['Message']);
        }

        return $array_data;
    }

    /**
     * Creates a HttpRequest for an update of a customer and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $xml
     * @param mixed $customerNumber
     * @return bool
     */
    public function update_customer_request($xml, $customerNumber){
        logthis("UPDATE CUSTOMER REQUEST");
        return $this->make_put_request($this->build_url("customers/" . $customerNumber), $xml);
    }

    /**
     * Creates a HttpRequest for an update of a product and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $xml
     * @param mixed $sku
     * @return bool
     */
    public function update_product_request($xml, $sku){
        logthis("UPDATE PRODUCT REQUEST");
        return $this->make_put_request($this->build_url("articles/" . $sku), $xml);
    }

    /**
     * Creates a HttpRequest for an update of product(for given sku)
     * price and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @param mixed $xml
     * @param mixed $sku
     * @return bool
     */
    public function update_product_price_request($xml, $sku){
        logthis("UPDATE PRICE REQUEST");
        return $this->make_put_request($this->build_url("prices/A/" . $sku . "/0"), $xml);
    }

    /**
     * Creates a HttpRequest for an update of a product and appends the given XML to the request and sends it to Fortnox
     *
     * @access private
     * @param mixed $message
     * @return bool
     */
    private function post_error($message){
        if(!isset($this->api_key)){
            return false;
        }

        $fields = array(
            'api_key' => $this->api_key,
            'message' => $message,
        );

        $ch = curl_init();
        $url = "http://plugapi.consuasor.se/api_post.php";
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_TIMEOUT,60);
        curl_setopt ($ch, CURLOPT_VERBOSE,0);
        curl_setopt ($ch, CURLOPT_POST, 1);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $data = curl_exec($ch);
        curl_close($ch);
        logthis($data);
    }
}