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

        $ch = curl_init();
        $url = "http://plugapi.consuasor.se/api.php?api_key=" . $this->api_key;
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_TIMEOUT,60);
        curl_setopt ($ch, CURLOPT_VERBOSE,0);
        curl_setopt ($ch, CURLOPT_POST, 0);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $data = curl_exec($ch);
        curl_close($ch);
        logthis($data);
        if($data == 'OK'){
            return true;
        }
        return false;
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
        logthis("GET ARTICLE REQUEST");
        return $this->make_get_request($this->build_url("articles/" . $sku));
    }

    /**
     * Creates a HttpRequest for fetching all customer and appends the given XML to the request and sends it to Fortnox
     *
     * @access public
     * @return bool
     */
    public function get_customers(){
        logthis("GET CUSTOMER REQUEST");
        return $this->make_get_request($this->build_url("customers/?limit=500"));
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
            update_option( 'fortnox_access_token', $this->access_token, '', 'yes' );
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
        if (array_key_exists("Error",$arrayData)){
            logthis("FORTNOX ERROR");
            $this->post_error($arrayData['Message']);
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
        if (array_key_exists("Error",$arrayData)){
            logthis("FORTNOX ERROR");
            $this->post_error($arrayData['Message']);
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
        if (array_key_exists("Error",$array_data)){
            logthis("FORTNOX ERROR");
            $this->post_error($array_data['Message']);
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