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

if(!defined('AUTOMATED_TESTING')){
    define('AUTOMATED_TESTING', false);
}

class WC_Fortnox_Controller {

    const WCF_API_KEY_ERROR = 1;
    const WCF_FORTNOX_KEY_ERROR = 2;
    const WCF_ORDER_ERROR = 3;
    const WCF_INVOICE_ERROR = 4;
    const WCF_BOOKKEEPING_ERROR = 5;
    const WCF_CONTACT_ERROR = 6;
    const WCF_PRODUCT_ERROR = 7;
    const WCF_ORDER_SUCCESS = 8;
    const WCF_PRODUCT_SUCCESS = 9;

    const FORTNOX_ERROR_CODE_ORDER_PRODUCT_NOT_EXIST = 2001302;
    const FORTNOX_ERROR_CODE_ORDER_EXISTS = 2000861;
    const FORTNOX_ERROR_CODE_PRODUCT_NOT_EXIST = 2000513;
    const FORTNOX_ERROR_CODE_PRODUCT_PRICE_EXIST = 2000762;
    const FORTNOX_ERROR_CODE_ARTICLE_NUMBER_MISSING = 2001846;
    const FORTNOX_ERROR_CODE_ARTICLE_PRICELIST_ERROR = 2000342;
    const FORTNOX_ERROR_CODE_ARTICLE_ALREADY_TAKEN = 2000013;
    const FORTNOX_ERROR_CODE_ACCESS_TOKEN = 2000311;
    const FORTNOX_ERROR_CODE_VALID_IDENTIFIER = 2000729;
    const FORTNOX_ERROR_XML = 200;
    const FORTNOX_ERROR_CONNECTION = 201;
    const FORTNOX_ERROR_CURL = 202;

    public $error_notice = '';
    public $response_code = '';
    public $error_code = '';


    public function add_fortnox_error_notice( $location ) {
        remove_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
        return add_query_arg( array( 'fortnox_error_code' => $this->error_code, 'fortnox_code' => $this->response_code, 'fortnox_message' => urlencode($this->error_notice )), $location );
    }

    public function add_fortnox_success_notice( $location ) {
        remove_filter( 'redirect_post_location', array( $this, 'add_fortnox_success_notice' ), 99 );
        return add_query_arg( array( 'fortnox_code' => $this->response_code ), $location );
    }

    /**
     * Sends contact to Fortnox API
     *
     * @access public
     * @param int $orderId
     * @return boolean
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

            if(!$customerNumber){
                return false;
            }

            if(!isset($options['activate-orders'])){
                return false;
            }

            if($options['activate-orders'] == 'on'){
                $orderNumber = $this->send_order_to_fortnox($orderId, $customerNumber);
                if(!$orderNumber){
                    return false;
                }
            }
            return true;
        }
        else{
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice'), 99 );
            return false;
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
        if(!isset($options['activate-orders'])){
            return;
        }
        if($options['activate-orders'] == 'on'){

            include_once("class-fortnox3-order-xml.php");
            include_once("class-fortnox3-database-interface.php");
            include_once("class-fortnox3-api.php");

            //fetch Order
            $order = new WC_Order($orderId);
            logthis("ORDER");
            logthis(print_r($order, true));

            //Init API
            $apiInterface = new WCF_API();
            if($apiInterface->has_error){
                $this->handle_error(array('Message'=> 'NO KEY', 'Code'=>WC_Fortnox_Controller::WCF_API_KEY_ERROR), WC_Fortnox_Controller::WCF_API_KEY_ERROR);
                return false;
            }
            //create Order XML
            $orderDoc = new WCF_Order_XML_Document();
            $orderXml = $orderDoc->create($order, $customerNumber, false);
            if(!$orderXml){
                $this->handle_error(array('Message'=> 'Fel på XML', 'Code'=>WC_Fortnox_Controller::FORTNOX_ERROR_XML), WC_Fortnox_Controller::WCF_ORDER_ERROR);
                if(!AUTOMATED_TESTING){
                    return false;
                }
            }
            //send Order XML
            $orderResponse = $apiInterface->create_order_request($orderXml);
            $this->check_order_difference($order, $orderResponse);
            if(AUTOMATED_TESTING){
                $action = 'create';
            }
            //Error handling
            if(array_key_exists('Error', $orderResponse)){
                logthis(print_r($orderResponse, true));
                // if order exists
                if((int)$orderResponse['Code'] == WC_Fortnox_Controller::FORTNOX_ERROR_CODE_ORDER_EXISTS){
                    logthis("ORDER EXISTS");
                    $orderXml = $orderDoc->create($order, $customerNumber, true);
                    $orderResponse = $apiInterface->update_order_request($orderXml, $orderId);
                    $this->check_order_difference($order, $orderResponse);
                    //Handle error
                    if(array_key_exists('Error', $orderResponse)){
                        $this->handle_error($orderResponse, WC_Fortnox_Controller::WCF_ORDER_ERROR);
                        if(!AUTOMATED_TESTING){
                            return false;
                        }
                    }
                    else{
                        $this->set_order_as_synced($orderId);
                    }

                    if(AUTOMATED_TESTING){
                        $action = 'update';
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
                        $this->handle_error($orderResponse, WC_Fortnox_Controller::WCF_ORDER_ERROR);
                        if(!AUTOMATED_TESTING){
                            return false;
                        }
                    }
                    else{
                        $this->set_order_as_synced($orderId);
                    }

                    if(AUTOMATED_TESTING){
                        $action = 'create_product_dont_exist';
                    }
                }
                else{
                    logthis("CREATE UNSYNCED ORDER");
                    //Init DB 2000861
                    $database = new WCF_Database_Interface();
                    //Save
                    $database->create_unsynced_order($orderId);
                    $this->handle_error($orderResponse, WC_Fortnox_Controller::WCF_ORDER_ERROR);
                    if(!AUTOMATED_TESTING){
                        return false;
                    }
                }
            }
            else{
                $this->set_order_as_synced($orderId);
            }
            if(!isset($options['activate-invoices'])){
                if(AUTOMATED_TESTING){
                    return array(
                        'action' => $action,
                        'order_response' => $orderResponse,
                    );
                };

                $this->response_code = WC_Fortnox_Controller::WCF_ORDER_SUCCESS;
                add_filter( 'redirect_post_location', array( $this, 'add_fortnox_success_notice' ), 99 );
                return true;
            }
            if($options['activate-invoices'] == 'on'){
                //Create invoice
                $invoiceResponse = $apiInterface->create_order_invoice_request($orderResponse['DocumentNumber']);

                if(array_key_exists('Error', $invoiceResponse)){
                    $this->handle_error($invoiceResponse, WC_Fortnox_Controller::WCF_INVOICE_ERROR);
                    if(!AUTOMATED_TESTING){
                        return false;
                    }
                }

                if($options['activate-bookkeeping'] == 'on'){

                    //Set invoice as bookkept
                    $bookkeptResponse = $apiInterface->create_invoice_bookkept_request($invoiceResponse['InvoiceReference']);

                    if(array_key_exists('Error', $bookkeptResponse)){
                        $this->handle_error($bookkeptResponse, WC_Fortnox_Controller::WCF_BOOKKEEPING_ERROR);
                        if(!AUTOMATED_TESTING){
                            return false;
                        }
                    }
                }
            }
            $this->response_code = WC_Fortnox_Controller::WCF_ORDER_SUCCESS;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_success_notice' ), 99 );
        }
        if(AUTOMATED_TESTING){
            return array(
                'action' => $action,
                'order_response' => $orderResponse,
            );
        };
        $this->set_order_as_synced($orderId);
        return true;
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
        if(!isset($options['activate-prices'])){
            return;
        }
        if( $this->is_api_key_valid()){
            if($options['activate-prices'] == 'on'){
                $post = get_post($productId);
                logthis($post->post_status);
                if(($post->post_type == 'product' || $post->post_type == 'product_variation') && $post->post_status == 'publish'){

                    logthis("PRODUCT");
                    include_once("class-fortnox3-product-xml.php");
                    include_once("class-fortnox3-database-interface.php");
                    include_once("class-fortnox3-api.php");
                    $has_children = false;
                    //fetch Product
                    $pf = new WC_Product_Factory();
                    $product = $pf->get_product($productId);

                    if($product->has_child()){
                        logthis("HAS CHILD");

                        if(AUTOMATED_TESTING){
                            $has_children = true;
                        };

                        //sync children
                        foreach($product->get_children() as $childId){
                            logthis("PRODUCT CHILD " . $childId );
                            $this->send_product_to_fortnox($childId);
                        }

                        //if not sync master product return
                        if(!isset($options['sync-master'])){
                            return;
                        }
                        if($options['sync-master'] == 'off'){
                            return;
                        }
                    }
                    //Init API
                    $apiInterface = new WCF_API();

                    if($apiInterface->has_error){
                        $this->handle_error(array( 'Message' => 'API-Nyckeln är fel/fattas', 'Code' => 0 ), WC_Fortnox_Controller::WCF_API_KEY_ERROR);
                        return false;
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
                            return false;
                        }

                        //Error handling
                        if(array_key_exists('Code', $updateResponse)){
                            //Product does not exist
                            if((int)$updateResponse['Code'] == WC_Fortnox_Controller::FORTNOX_ERROR_CODE_PRODUCT_NOT_EXIST){

                                //Create product
                                $productXml = $productDoc->create($product);
                                $productResponse = $apiInterface->create_product_request($productXml);

                                if(array_key_exists('Error', $productResponse)){
                                    $this->handle_error($productResponse, WC_Fortnox_Controller::WCF_PRODUCT_ERROR);
                                    if(!AUTOMATED_TESTING){
                                        return 0;
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
                                    return false;
                                }

                                if(AUTOMATED_TESTING){
                                    return array(
                                        'action' => 'update',
                                        'product_response' => $productResponse,
                                        'price_response' => $priceResponse,
                                    );
                                }
                            }
                        }

                        if(AUTOMATED_TESTING){
                            return array(
                                'action' => 'update',
                                'has_children' => $has_children,
                                'product_response' => $updateResponse,
                                'price_response' => $priceResponse,
                            );
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
                                $updateResponse = $apiInterface->update_product_request($productXml, $sku);

                                //update price
                                $productPriceXml = $productDoc->update_price($product);
                                $priceResponse = $apiInterface->update_product_price_request($productPriceXml, $sku);
                            }
                            else{
                                $this->handle_error($productResponse, WC_Fortnox_Controller::WCF_PRODUCT_ERROR);
                                if(!AUTOMATED_TESTING){
                                    return 0;
                                }
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
                            return false;
                        }

                        if(AUTOMATED_TESTING){
                            return array(
                                'action' => 'create',
                                'product_response' => $productResponse,
                                'price_response' => $priceResponse,
                            );
                        }
                    }
                    $this->response_code = WC_Fortnox_Controller::WCF_PRODUCT_SUCCESS;
                    add_filter( 'redirect_post_location', array( $this, 'add_fortnox_success_notice' ), 99 );
                }
            }
        }
        else{
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice'), 99 );
        }
    }

    private function handle_error($response, $code){
        $this->error_notice = $response['Message'];
        $this->error_code = $response['Code'];
        $this->response_code = $code;
        add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice'), 99 );
    }

    private function set_order_as_synced($order_id){
        logthis("SET AS SYNCED");
        $r = update_post_meta($order_id, '_fortnox_order_synced', 1);
        logthis($r);
    }

    private function handle_pricelist_error($priceResponse, $product, $productDoc, $apiInterface){
        if(array_key_exists('Code', $priceResponse)){
            if((int)$priceResponse['Code'] == $this->FORTNOX_ERROR_CODE_ARTICLE_PRICELIST_ERROR){
                $productPriceXml = $productDoc->create_price($product);
                $apiInterface->create_product_price_request($productPriceXml);
                return false;
            }
            else{
                $this->handle_error($priceResponse, WC_Fortnox_Controller::WCF_PRODUCT_ERROR);
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
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Er API-Nyckel är ej giltig.";
        }

        if($apiInterface->has_error){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Inloggning till Fortnox misslyckades";
        }

        $customers = $apiInterface->get_customers();
        $databaseInterface = new WCF_Database_Interface();

        foreach($customers as $customer){
            $databaseInterface->create_existing_customer($customer);

        }
        return "Kontakter synkroniserade.";
    }

    /**
     * Sends ALL orders to Fortnox API
     *
     * @access public
     * @return bool
     */
    public function sync_all_orders_to_fortnox() {
        include_once("class-fortnox3-api.php");
        logthis("POST");
        //Init API
        $apiInterface = new WCF_API();

        if(!$apiInterface->create_api_validation_request()){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Er API-Nyckel är ej giltig.";
        }

        if($apiInterface->has_error){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Inloggning till Fortnox misslyckades";
        }
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'orderby' => 'id',
            'posts_per_page' => -1                                                                                                                                                                                                                                         ,
        );
        logthis("POST");
        $the_query = new WP_Query( $args );
        foreach($the_query->get_posts() as $db_order){
            $order = new WC_Order($db_order->ID);
            if($order->has_status( 'completed' )){
                $check = $this->send_contact_to_fortnox($db_order->ID);
                if(!$check){
                    logthis("ORDER FAILED: " . $db_order->ID);
                }
            }
        }

        return "Ordrar synkroniserade.";
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
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Er API-Nyckel är ej giltig.";
        }

        if($apiInterface->has_error){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Inloggning till Fortnox misslyckades";
        }

        $databaseInterface = new WCF_Database_Interface();
        $unsyncedOrders = $databaseInterface->read_unsynced_orders();

        foreach($unsyncedOrders as $order){

            $orderId = $order->order_id;
            $order = new WC_Order($orderId);

            $customerNumber = $this->get_or_create_customer($order);

            //create Order XML
            $orderDoc = new WCF_Order_XML_Document();
            $orderXml = $orderDoc->create($order, $customerNumber, false);

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
     * Syncs ALL products to Fortnox API
     *
     * @access public
     * @return bool
     */
    public function initial_products_sync() {

        include_once("class-fortnox3-api.php");

        //Init API
        $apiInterface = new WCF_API();

        if(!$apiInterface->create_api_validation_request()){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Er API-Nyckel är ej giltig.";
        }

        if($apiInterface->has_error){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Inloggning till Fortnox misslyckades";
        }

        $args = array(
            'post_type' => 'product', 'product_variation',
            'orderby' => 'id',
            'posts_per_page' => -1,
        );
        $the_query = new WP_Query( $args );
        foreach($the_query->get_posts() as $product){
            $this->send_product_to_fortnox($product->ID);
        }
        wp_reset_postdata();
        return "Produkter synkade";
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
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Er API-Nyckel är ej giltig.";
        }

        if($apiInterface->has_error){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return "Inloggning till Fortnox misslyckades";
        }

        //fetch all articles
        $inventory = $apiInterface->get_inventory();
        $articles = $inventory['ArticleSubset'];

        $pf = new WC_Product_Factory();
        $product = null;
        foreach($articles as $article){
            //Query DB for id by SKU
            $query = new WP_Query( "post_type=product&meta_key=_sku&meta_value=" . $article['ArticleNumber'] );
            if($query->post_count == 1){
                $product = $pf->get_product($query->posts[0]->ID);
            }
            else{
                continue;
            }

            if(!$product)
                continue;

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
     *
     * @return bool
     */
    public function run_manual_inventory_cron_job(){
        include_once("class-fortnox3-api.php");

        logthis('MANUAL INVENTORY');
        //Init API
        $apiInterface = new WCF_API();

        if(!$apiInterface->create_api_validation_request()){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return false;
        }

        if($apiInterface->has_error){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return false;
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
                add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
                return false;
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
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
        }

        if($apiInterface->has_error){
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
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
                $this->error_notice = 'NO KEY';
                $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
                add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
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

    private function clean_str($sku){
        $sku = preg_replace('/\s+/', '', $sku);
        $sku = str_replace('.', '', $sku);
        return $sku;
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
            $this->error_notice = 'NO KEY';
            $this->response_code = WC_Fortnox_Controller::WCF_API_KEY_ERROR;
            add_filter( 'redirect_post_location', array( $this, 'add_fortnox_error_notice' ), 99 );
            return false;
        }

        //create Contact XML
        $contactDoc = new WCF_Contact_XML_Document();
        $contactXml = $contactDoc->create($order);

        if(empty($customer)){

            $customerId = $databaseInterface->create_customer($order->billing_email);

            //send Contact XML
            $contactResponse = $apiInterface->create_customer_request($contactXml);

            if(array_key_exists('Error', $contactResponse)){
                $this->handle_error($contactResponse, WC_Fortnox_Controller::WCF_CONTACT_ERROR);
                return null;
            }

            $customerNumber = $contactResponse['CustomerNumber'];
            $databaseInterface->update_customer($customerId, $customerNumber);

        }
        else{
            $customerNumber = $customer[0]->customer_number;
            $contactResponse = $apiInterface->update_customer_request($contactXml, $customerNumber);
            if(array_key_exists('Error', $contactResponse)){
                $this->handle_error($contactResponse, WC_Fortnox_Controller::WCF_CONTACT_ERROR);
                return null;
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
}