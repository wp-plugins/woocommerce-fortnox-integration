<?php
/**
 * Created by PhpStorm.
 * User: tomas
 * Date: 11/19/14
 * Time: 10:47 AM
 */
if(!defined('TESTING')){
    define('TESTING', false);
}

class WC_Fortnox_Controller {

    const FORTNOX_ERROR_LOGIN = 1;
    const FORTNOX_ERROR_CODE_ORDER_PRODUCT_NOT_EXIST = 2001302;
    const FORTNOX_ERROR_CODE_ORDER_EXISTS = 2000861;
    const FORTNOX_ERROR_CODE_PRODUCT_NOT_EXIST = 2000513;
    const FORTNOX_ERROR_CODE_PRODUCT_PRICE_EXIST = 2000762;
    const FORTNOX_ERROR_CODE_ARTICLE_NUMBER_MISSING = 2001846;
    const FORTNOX_ERROR_CODE_ARTICLE_PRICELIST_ERROR = 2000342;
    const FORTNOX_ERROR_CODE_ARTICLE_PRICE_ERROR = 2000517;
    const FORTNOX_ERROR_CODE_ARTICLE_ALREADY_TAKEN = 2000013;
    const FORTNOX_ERROR_CODE_UPDATE_ARTICLE_DOES_NOT_EXIST = 2000762;
    const FORTNOX_ERROR_CODE_ACCESS_TOKEN = 2000311;
    const FORTNOX_ERROR_CODE_VALID_IDENTIFIER = 2000729;
    const FORTNOX_ERROR_XML = 200;
    const FORTNOX_ERROR_CONNECTION = 201;
    const FORTNOX_ERROR_CURL = 202;

    private $ERROR_API_KEY = array(
        'success'=> false,
        'message'=> 'API Nyckeln är ej giltig'
    );

    private $UPDATE_ARTICLE_DOES_NOT_EXIST = array(
        'error_id' => WC_Fortnox_Controller::FORTNOX_ERROR_CODE_UPDATE_ARTICLE_DOES_NOT_EXIST,
        'success'=> false,
        'message'=> 'Produkt med detta artikelnummer finns ej.',
        'link'=> 'produkt-med-detta-artikelnummer-finns-ej'
    );

    private $ERROR_CONTACT = array(
        'error_id' => WC_Fortnox_Controller::FORTNOX_ERROR_CODE_VALID_IDENTIFIER,
        'success'=> false,
        'message'=> 'Produkt med detta artikelnummer finns ej.',
        'link'=> 'produkt-med-detta-artikelnummer-finns-ej'
    );

    private $ERROR_ORDER_NOT_ACTIVATED = array(
        'success'=> false,
        'message'=> 'Ordersynkronisering ej aktiverad'
    );

    private $ERROR_PRODUCT_NOT_ACTIVATED = array(
        'success'=> false,
        'message'=> 'Produktsynkronisering ej aktiverad'
    );

    private $ERROR_XML = array(
        'success'=> false,
        'message'=> 'Ett fel uppstod generering av XML'
    );

    private $SUCCESS_ORDER = array(
        'success'=> true,
        'message'=> 'Ordern synkroniserad'
    );

    private $SUCCESS_PRODUCT = array(
        'success'=> true,
        'message'=> 'Produkt synkroniserad'
    );

    private $UNVALID_ACCESSTOKEN = array(
        'error_id' => WC_Fortnox_Controller::FORTNOX_ERROR_CODE_ACCESS_TOKEN,
        'success'=> false,
        'message'=> 'Det uppstod ett fel med er Fortnox Accesstoken',
        'link'=> 'fortnox-accesstoken-fel'
    );

    private $ERROR_LOGIN = array(
        'error_id' => WC_Fortnox_Controller::FORTNOX_ERROR_LOGIN,
        'success'=> false,
        'message'=> 'Det uppstod ett fel med login till Fortnox. Har du angett Fortnox API-kod under inställningar?',
        'link'=> 'fortnox-accesstoken-fel'
    );

    private $ERROR_ORDER_PRODUCT_NOT_EXIST = array(
        'error_id' => WC_Fortnox_Controller::FORTNOX_ERROR_CODE_ORDER_PRODUCT_NOT_EXIST,
        'success'=> false,
        'message'=> 'Ordern innehåller produkter som ej synkroniserats.',
        'link'=> 'kunde-inte-hitta-artikel'
    );


    private function errors(){
        return array(
            $this->UPDATE_ARTICLE_DOES_NOT_EXIST,
            $this->UNVALID_ACCESSTOKEN,
            $this->ERROR_LOGIN,
            $this->ERROR_ORDER_PRODUCT_NOT_EXIST,
            $this->ERROR_CONTACT,
        );
    }

    /**
     * Sends contact to Fortnox API
     *
     * @access public
     * @param int $orderId
     * @return mixed
     */
    public function send_contact_to_fortnox($orderId) {
        global $wcdn, $woocommerce;
        $options = get_option('woocommerce_fortnox_general_settings');
        if($this->is_api_key_valid()){

            include_once("class-fortnox3-contact-xml.php");
            include_once("class-fortnox3-database-interface.php");
            include_once("class-fortnox3-api.php");
            //fetch Order
            $order = new WC_Order($orderId);
            logthis('send_contact_to_fortnox');
            $customerNumber = $this->get_or_create_customer($order);

            if(is_array($customerNumber)){
                return $customerNumber;
            }
            return $this->send_order_to_fortnox($orderId, $customerNumber);
        }
        else{
            return $this->ERROR_API_KEY;
        }
    }

    /**
     * Sends order to Fortnox API
     *
     * @access public
     * @param int $orderId
     * @param $customerNumber
     * @return mixed
     */
    public function send_order_to_fortnox($orderId, $customerNumber) {
        global $wcdn;
        $options = get_option('woocommerce_fortnox_general_settings');

        include_once("class-fortnox3-order-xml.php");
        include_once("class-fortnox3-database-interface.php");
        include_once("class-fortnox3-api.php");

        //fetch Order
        $order = new WC_Order($orderId);

        //Init API
        $apiInterface = new WCF_API();
        if($apiInterface->has_error){
            return $this->ERROR_LOGIN;
        }
        //create Order XML
        $orderDoc = new WCF_Order_XML_Document();
        $orderXml = $orderDoc->create($order, $customerNumber);
        if(!$orderXml){
            return $this->ERROR_XML;
        }

        //send Order XML
        $orderResponse = $apiInterface->create_order_request($orderXml);
        $this->check_order_difference($order, $orderResponse);

        //Error handling
        if(array_key_exists('Error', $orderResponse)){
            logthis(print_r($orderResponse, true));
            // if order exists
            if((int)$orderResponse['Code'] == WC_Fortnox_Controller::FORTNOX_ERROR_CODE_ORDER_EXISTS){
                logthis("ORDER EXISTS");
                $orderXml = $orderDoc->create($order, $customerNumber);
                $orderResponse = $apiInterface->update_order_request($orderXml, $orderId);
                $this->check_order_difference($order, $orderResponse);
                //Handle error
                if(array_key_exists('Error', $orderResponse)){
                    return $this->handle_error($orderResponse);
                }
                else{
                    $this->set_order_as_synced($orderId);
                }
            }
            // if products dont exist
            elseif((int)$orderResponse['Code'] == WC_Fortnox_Controller::FORTNOX_ERROR_CODE_ORDER_PRODUCT_NOT_EXIST){
                logthis("PRODUCT DOES NOT EXIST");

                foreach($order->get_items() as $item){
                    //if variable product there might be a different SKU
                    if(empty($item['variation_id'])){
                        $productId = $item['product_id'];
                    }
                    else{
                        $productId = $item['variation_id'];
                    }
                    $this->send_product_to_fortnox($productId);
                }
                $orderResponse = $apiInterface->create_order_request($orderXml);
                $this->check_order_difference($order, $orderResponse);

                if(array_key_exists('Error', $orderResponse)){
                    return $this->handle_error($orderResponse);
                }
                else{
                    $this->set_order_as_synced($orderId);
                }
            }
            else{
                logthis("CREATE UNSYNCED ORDER");
                //Init DB 2000861
                $database = new WCF_Database_Interface();
                //Save
                $database->create_unsynced_order($orderId);
                return $this->handle_error($orderResponse);
            }
        }
        else{
            $this->set_order_as_synced($orderId);
        }
        if(!isset($options['activate-invoices'])){
            return $this->SUCCESS_ORDER;
        }
        return $this->handle_invoice($options, $apiInterface, $orderResponse);

    }

    /**
     * Sends product to Fortnox API
     *
     * @access public
     * @param $productId
     * @internal param int $orderId
     * @return mixed
     */
    public function send_product_to_fortnox($productId) {
        global $wcdn;
        $options = get_option('woocommerce_fortnox_general_settings');

        if( $this->is_api_key_valid()){
            $post = get_post($productId);
            logthis($post->post_status);
            if(($post->post_type == 'product' || $post->post_type == 'product_variation') && $post->post_status == 'publish'){

                logthis("PRODUCT");
                include_once("class-fortnox3-product-xml.php");
                include_once("class-fortnox3-database-interface.php");
                include_once("class-fortnox3-api.php");
                //fetch Product
                $pf = new WC_Product_Factory();
                $product = $pf->get_product($productId);

                if($product->has_child()){
                    if($options['do-not-sync-children'] == 'off' || !isset($options['do-not-sync-children'])){
                        //sync children
                        foreach($product->get_children() as $childId){
                            logthis("PRODUCT CHILD " . $childId );
                            $message = $this->send_product_to_fortnox($childId);
                            if(!$message['success']){
                                return $message;
                            }
                        }

                        //if not sync master product return
                        if(!isset($options['sync-master'])){
                            return $this->SUCCESS_PRODUCT;
                        }
                        if($options['sync-master'] == 'off'){
                            return $this->SUCCESS_PRODUCT;
                        }
                    }
                }
                //Init API
                $apiInterface = new WCF_API();

                if($apiInterface->has_error){
                    return $this->ERROR_LOGIN;
                }

                //create Product XMLDOC
                $productDoc = new WCF_Product_XML_Document();
                $sku = $product->get_sku();

                $isSynced = get_post_meta( $productId, '_is_synced_to_fortnox' );

                //check if already synced
                if (!empty($isSynced)) {

                    logthis("UPDATE PRODUCT");
                    //update product
                    $productXml = $productDoc->update($product);
                    $updateResponse = $apiInterface->update_product_request($productXml, $sku);

                    //update price
                    $productPriceXml = $productDoc->update_price($product);
                    $priceResponse = $apiInterface->update_product_price_request($productPriceXml, $sku);

                    if(!$this->handle_pricelist_error($priceResponse, $product, $productDoc, $apiInterface)){
                        return $this->handle_error($priceResponse);
                    }

                    //Error handling
                    if(array_key_exists('Code', $updateResponse)){
                        //Product does not exist
                        if((int)$updateResponse['Code'] == WC_Fortnox_Controller::FORTNOX_ERROR_CODE_PRODUCT_NOT_EXIST){

                            //Create product
                            $productXml = $productDoc->create($product);
                            $productResponse = $apiInterface->create_product_request($productXml);

                            if(array_key_exists('Error', $productResponse)){
                                return $this->handle_error($productResponse);
                            }

                            $fortnoxId = $productResponse['ArticleNumber'];

                            //set sku;
                            update_post_meta($productId, '_sku', $fortnoxId);
                            update_post_meta($productId, '_is_synced_to_fortnox', 1);

                            //update price
                            $productPriceXml = $productDoc->update_price($product);
                            $priceResponse = $apiInterface->update_product_price_request($productPriceXml, $fortnoxId);

                            if(!$this->handle_pricelist_error($priceResponse, $product, $productDoc, $apiInterface)){
                                return $this->handle_error($priceResponse);
                            }
                        }
                    }
                }
                else{

                    logthis("CREATE PRODUCT");

                    //Create product
                    $productXml = $productDoc->create($product);
                    $productResponse = $apiInterface->create_product_request($productXml);

                    if(array_key_exists('Error', $productResponse)){
                        if((int)$productResponse['Code'] == WC_Fortnox_Controller::FORTNOX_ERROR_CODE_ARTICLE_ALREADY_TAKEN){
                            $productXml = $productDoc->update($product);
                            $productResponse = $apiInterface->update_product_request($productXml, $sku);

                            //update price
                            $productPriceXml = $productDoc->update_price($product);
                            $priceResponse = $apiInterface->update_product_price_request($productPriceXml, $sku);

                            if(array_key_exists('Error', $productResponse)){
                                return $this->handle_error($productResponse);
                            }
                        }
                        else{
                            return $this->handle_error($productResponse);
                        }
                    }

                    $fortnoxId = $productResponse['ArticleNumber'];

                    //set sku;
                    update_post_meta($productId, '_sku', $fortnoxId);
                    update_post_meta($productId, '_is_synced_to_fortnox', 1);

                    //update price
                    $productPriceXml = $productDoc->update_price($product);
                    $priceResponse = $apiInterface->update_product_price_request($productPriceXml, $fortnoxId);

                    if(!$this->handle_pricelist_error($priceResponse, $product, $productDoc, $apiInterface)){
                        return $this->handle_error($priceResponse);
                    }
                }
                return $this->SUCCESS_PRODUCT;
            }
        }
        else{
            return $this->ERROR_API_KEY;
        }
    }

    /***********************************************************************************************************
     * PRIVATE FUNCTIONS
     ***********************************************************************************************************/
    private function clean_str($sku){
        $sku = preg_replace(array('/å/','/ä/','/ö/','/Å/','/Ä/','/Ö/', '/\s+/', '.'), array('a','a','o','A','A','O', '_', ''), $sku);
        return $sku;
    }

    private function handle_invoice($options, $apiInterface, $orderResponse){
        //Create invoice
        $invoiceResponse = $apiInterface->create_order_invoice_request($orderResponse['DocumentNumber']);

        if(array_key_exists('Error', $invoiceResponse)){
            return $this->handle_error($invoiceResponse);
        }

        if($options['activate-bookkeeping'] == 'on'){
            //Set invoice as bookkept
            $bookkeptResponse = $apiInterface->create_invoice_bookkept_request($invoiceResponse['InvoiceReference']);

            if(array_key_exists('Error', $bookkeptResponse)){
                return $this->handle_error($bookkeptResponse);
            }
        }
        return $this->SUCCESS_ORDER;
    }

    private function handle_error($response){

        $errors = $this->errors();

        foreach($errors as $error){
            if($response['Code'] == $error['error_id']){
                return array(
                    'success'=> false,
                    'message'=> 'Felkod: ' .$response['Code'] . ' Meddelande: ' . $error['message'],
                    'link'=> $error['link'],
                );
            }
        }

        return $arr = array(
                'success'=> false,
                'message'=> 'Felkod: ' .$response['Code'] . ' Meddelande: ' . $response['Message']
        );
    }

    private function set_order_as_synced($order_id){
        logthis("SET AS SYNCED");
        $r = update_post_meta($order_id, '_fortnox_order_synced', 1);
        logthis($r);
    }

    private function handle_pricelist_error($priceResponse, $product, $productDoc, $apiInterface){
        if(array_key_exists('Code', $priceResponse)){
            if((int)$priceResponse['Code'] == WC_Fortnox_Controller::FORTNOX_ERROR_CODE_ARTICLE_PRICELIST_ERROR || (int)$priceResponse['Code'] == WC_Fortnox_Controller::FORTNOX_ERROR_CODE_ARTICLE_PRICE_ERROR){
                $productPriceXml = $productDoc->create_price($product);
                $apiInterface->create_product_price_request($productPriceXml);
                return true;
            }
            else{
                return false;
            }
        }
        return true;
    }

    /***********************************************************************************************************
     * MANUAL FUNCTIONS
     ***********************************************************************************************************/

    /**
     * Fetches contacts from Fortnox and writes them local db
     *
     * @access public
     * @return bool
     */
    public function fetch_fortnox_contacts() {
        include_once("class-fortnox3-order-xml.php");
        include_once("class-fortnox3-database-interface.php");
        include_once("class-fortnox3-api.php");

        //Init API
        $apiInterface = new WCF_API();

        if(!$apiInterface->create_api_validation_request()){
            return $this->ERROR_API_KEY;
        }

        if($apiInterface->has_error){
            return $this->ERROR_LOGIN;
        }

        $customers = $apiInterface->get_customers();
        $databaseInterface = new WCF_Database_Interface();

        foreach($customers as $customer){
            $databaseInterface->create_existing_customer($customer);
        }
        return "Kontakter synkroniserade.";
    }

    /**
     * Sends ALL unsynced orders to Fortnox API
     *
     * @access public
     * @return bool
     */
    public function sync_orders_to_fortnox() {
        include_once("class-fortnox3-order-xml.php");
        include_once("class-fortnox3-database-interface.php");
        include_once("class-fortnox3-api.php");

        $options = get_option('woocommerce_fortnox_general_settings');

        //Init API
        $apiInterface = new WCF_API();

        if(!$apiInterface->create_api_validation_request()){
            return $this->ERROR_API_KEY;
        }

        if($apiInterface->has_error){
            return $this->ERROR_LOGIN;
        }

        $databaseInterface = new WCF_Database_Interface();
        $unsyncedOrders = $databaseInterface->read_unsynced_orders();

        foreach($unsyncedOrders as $order){

            $orderId = $order->order_id;
            $order = new WC_Order($orderId);

            $customerNumber = $this->get_or_create_customer($order);

            //create Order XML
            $orderDoc = new WCF_Order_XML_Document();
            $orderXml = $orderDoc->create($order, $customerNumber);

            //send Order XML
            $orderResponse = $apiInterface->create_order_request($orderXml);
            if(!array_key_exists("Error", $orderResponse)){
                $databaseInterface->set_as_synced($orderId);
            }
            else{
                continue;
            }
            if(!isset($options['activate-invoices'])){
                continue;
            }
            if($options['activate-invoices'] == 'on'){
                //Create invoice
                $invoiceResponse = $apiInterface->create_order_invoice_request($orderResponse['DocumentNumber']);
                if($options['activate-bookkeeping'] == 'on'){
                    //Set invoice as bookkept
                    $apiInterface->create_invoice_bookkept_request($invoiceResponse['InvoiceReference']);
                }
            }
        }
        return "Ordrar synkroniserade.";
    }


    /**
     * Fetches inventory from fortnox and updates WooCommerce inventory
     *
     * @return string
     */
    public function run_inventory_cron_job(){
        include_once("class-fortnox3-api.php");
        logthis("RUNNING CRON");
        $options = get_option('woocommerce_fortnox_general_settings');
        if(!isset($options['activate-fortnox-products-sync'])){
            return;
        }

        //Init API
        $apiInterface = new WCF_API();

        if(!$apiInterface->create_api_validation_request()){
            return $this->ERROR_API_KEY;
        }

        if($apiInterface->has_error){
            return $this->ERROR_LOGIN;
        }

        //fetch all articles
        $articles = $apiInterface->get_inventory();

        $pf = new WC_Product_Factory();
        $product = null;
        foreach($articles as $article){
            //Query DB for id by SKU

            $args = array(
                'post_type' => array('product', 'product_variation'),
                'orderby' => 'id',
                'meta_key' => '_sku',
                'meta_value' => $article['ArticleNumber'],
            );
            $query = new WP_Query( $args );
            if($query->post_count == 1){
                $product = $pf->get_product($query->posts[0]->ID);
            }
            else{
                continue;
            }

            if(!$product || null === $product){
                continue;
            }

            if($product instanceof WC_Product_Variation){
                if($product->parent == ''){
                    continue;
                }
            }

            if($article['QuantityInStock'] > 0){
                logthis('IN STOCK');
                $product->set_stock($article['QuantityInStock']);
                $product->set_stock_status('instock');
            }
            else{
                logthis('OUT OF STOCK');
                $product->set_stock(0);
                $product->set_stock_status('outofstock');
            }
        }
        return "Lager uppdaterat";
    }

    /**
     * Fetches product stock for every product in Woo from Fortnox
     * DEPRECATED
     * @return bool
     */
    public function run_manual_inventory_cron_job(){
        include_once("class-fortnox3-api.php");

        logthis('MANUAL INVENTORY');
        //Init API
        $apiInterface = new WCF_API();

        if(!$apiInterface->create_api_validation_request()){
            return $this->ERROR_API_KEY;
        }

        if($apiInterface->has_error){
            return $this->ERROR_LOGIN;
        }

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'orderby' => 'id',
            'posts_per_page' => -1                                                                                                                                                                                                                                         ,
        );
        $the_query = new WP_Query( $args );

        $index = 0;
        foreach($the_query->get_posts() as $fetched_product){

            $pf = new WC_Product_Factory();
            $product = $pf->get_product($fetched_product->ID);

            //Init API
            $apiInterface = new WCF_API();
            if($apiInterface->has_error){
                return $this->ERROR_LOGIN;
            }

            if($product->has_child()){

                $totalAmount = 0;

                foreach($product->get_children() as $childId){

                    $child = $pf->get_product($childId);
                    $sku = $child->get_sku();
                    $article = $apiInterface->get_article($sku);

                    if($article['QuantityInStock'] > 0){

                        $totalAmount += (int)$article['QuantityInStock'];
                        $child->set_stock($article['QuantityInStock']);
                        $child->set_stock_status('instock');
                    }
                    else{

                        $child->set_stock(0);
                        $child->set_stock_status('outofstock');
                    }
                }

                if($totalAmount > 0){

                    update_post_meta( $fetched_product->ID, '_manage_stock', 'no' );
                    $product->set_stock_status('instock');

                }
                else{

                    $product->set_stock(0);
                    $product->set_stock_status('outofstock' );
                }
            }
            else{
                $sku = $product->get_sku();
                $article = $apiInterface->get_article($sku);
                if(array_key_exists('QuantityInStock', $article)){

                    if($article['QuantityInStock'] > 0){

                        $product->set_stock($article['QuantityInStock']);
                        $product->set_stock_status('instock');
                    }
                    else{

                        $product->set_stock(0);
                        $product->set_stock_status('outofstock');
                    }
                }
            }
        }
        return "Lager uppdaterat";
    }

    /**
     * Fetches product stock for every product in Woo from Fortnox
     *
     * @return bool
     */
    public function diff_woo_fortnox_inventory(){
        include_once("class-fortnox3-api.php");

        logthis('DIFF');
        //Init API
        $apiInterface = new WCF_API();

        if(!$apiInterface->create_api_validation_request()){
            return $this->ERROR_API_KEY;
        }

        if($apiInterface->has_error){
            return $this->ERROR_LOGIN;
        }

        $args = array(
            'post_type' => 'product',
            'orderby' => 'id',
            'posts_per_page' => -1                                                                                                                                                                                                                                         ,
        );
        $the_query = new WP_Query( $args );

        $missing = array();
        $missing_sku = array();
        foreach($the_query->get_posts() as $fetched_product){

            $pf = new WC_Product_Factory();
            $product = $pf->get_product($fetched_product->ID);

            //Init API
            $apiInterface = new WCF_API();
            if($apiInterface->has_error){
                return $this->ERROR_LOGIN;
            }

            if($product->has_child()){

                $totalAmount = 0;

                foreach($product->get_children() as $child_id){

                    $child = $pf->get_product($child_id);
                    $sku = $child->get_sku();
                    $article = $apiInterface->get_article($sku);
                    if($sku === NULL){
                        array_push($missing_sku, "VARIANT ID:" .$child_id);
                    }
                    if(array_key_exists('Error', $article)){
                        array_push($missing, "VARIANT ID:" .$child_id . " SKU:" .$sku);
                    }
                }
            }
            else{
                $sku = $product->get_sku();
                $article = $apiInterface->get_article($sku);
                if($sku === NULL){
                    array_push($missing_sku, "VARIANT ID:" .$fetched_product->ID);
                }
                if(array_key_exists('Error', $article)){
                    array_push($missing, "PRODUKT ID:" .$fetched_product->ID . " SKU:" .$sku);
                }
            }
        }
        logthis(print_r($missing, true));
        return print_r($missing, true);
    }


    /**
     * Fetches product stock for every product in Woo from Fortnox
     *
     * @return bool
     */
    public function SKU_clean(){
        $args = array(
            'post_type' => array('product', 'product_variation'),
            'orderby' => 'id',
            'posts_per_page' => -1                                                                                                                                                                                                                                         ,
        );
        $the_query = new WP_Query( $args );

        logthis("BEGINNING");
        $index = 0;
        foreach($the_query->get_posts() as $fetched_product){

            $pf = new WC_Product_Factory();
            $product = $pf->get_product($fetched_product->ID);

            if($product->has_child()){

                foreach($product->get_children() as $child_id){
                    logthis($child_id);
                    $child = $pf->get_product($child_id);
                    $sku = $child->get_sku();
                    $sku = $this->clean_str($sku);
                    update_post_meta($child_id, '_sku', $sku);
                    $index++;
                }
            }
            else{
                logthis($fetched_product->ID);
                $sku = $product->get_sku();
                $sku = $this->clean_str($sku);
                update_post_meta($fetched_product->ID, '_sku', $sku);
                $index++;
            }
        }
        return "SKUs cleaned: ". $index;
    }

    /***********************************************************************************************************
     * WP-PLUGS API FUNCTIONS
     ***********************************************************************************************************/

    /**
     * Checks if API-key is valid
     *
     * @access public
     * @return bool
     */
    public function is_api_key_valid() {
        include_once("class-fortnox3-api.php");
        logthis("VALIDATION");
        $apiInterface = new WCF_API();
        return $apiInterface->create_api_validation_request();
    }

    /**
     * Fetches customer from DB or creates it at Fortnox
     *
     * @access public
     * @param $order
     * @return int
     */
    private function get_or_create_customer($order){
        include_once("class-fortnox3-contact-xml.php");
        $databaseInterface = new WCF_Database_Interface();
        $customer = $databaseInterface->get_customer_by_email($order->billing_email);

        //Init API
        $apiInterface = new WCF_API();
        if($apiInterface->has_error){
            return $this->ERROR_LOGIN;
        }

        //create Contact XML
        $contactDoc = new WCF_Contact_XML_Document();
        $contactXml = $contactDoc->create($order);

        if(empty($customer) || $customer[0]->customer_number == 0){
            logthis("CREATING CUSTOMER");
            $customerId = $databaseInterface->create_customer($order->billing_email);

            //send Contact XML
            $contactResponse = $apiInterface->create_customer_request($contactXml);

            if(array_key_exists('Error', $contactResponse)){
                return $this->handle_error($contactResponse);
            }

            $customerNumber = $contactResponse['CustomerNumber'];
            $databaseInterface->update_customer($customerId, $customerNumber);

        }
        else{
            logthis("UPDATING CUSTOMER");
            $customerNumber = $customer[0]->customer_number;
            $contactResponse = $apiInterface->update_customer_request($contactXml, $customerNumber);
            if(array_key_exists('Error', $contactResponse)){
                return $this->handle_error($contactResponse, $link='fel-i-kunddatabastabellen');
            }
        }
        return $customerNumber;
    }

    /**
     * Creates meta if order in Woo differences from Fortnox
     *
     * @access public
     * @param $order
     * @param $orderResponse
     * @return int
     */
    private function check_order_difference($order, $orderResponse){
        $total = $order->get_total();

        if(array_key_exists('TotalToPay', $orderResponse)){
            if($total != floatval($orderResponse['TotalToPay'])){
                update_post_meta($order->id, "_fortnox_difference_order", $total - floatval($orderResponse['TotalToPay']));
            }
            else{
                delete_post_meta($order->id, "_fortnox_difference_order");
            }
        }
    }

    /**
     * Creates meta if order in Woo differences from Fortnox
     *
     * @access public
     * @return int
     */
    public function get_synced_products(){
        global $wpdb;
        logthis("INNE");
        $rows = $wpdb->get_results("SELECT meta2.post_id, meta2.meta_value from wp_postmeta meta1
        JOIN wp_postmeta meta2 ON meta1.post_id = meta2.post_id  WHERE meta1.meta_key = '_is_synced_to_fortnox' AND meta1.meta_value = '1' AND meta2.meta_key = '_sku'");
        foreach($rows as $key => $row){
            logthis($row->meta_value);
        }
    }
}