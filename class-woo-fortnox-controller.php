<?php
/**
 * Created by PhpStorm.
 * User: tomas
 * Date: 11/19/14
 * Time: 10:47 AM
 */
if(!defined('TESTING')){
    define('TESTING', true);
}

if(!defined('AUTOMATED_TESTING')){
    define('AUTOMATED_TESTING', true);
}

class WC_Fortnox_Controller {

    private $FORTNOX_ERROR_CODE_ORDER_PRODUCT_NOT_EXIST = 2001302;
    private $FORTNOX_ERROR_CODE_ORDER_EXISTS = 2000861;
    private $FORTNOX_ERROR_CODE_PRODUCT_NOT_EXIST = 2000513;
    private $FORTNOX_ERROR_CODE_PRODUCT_PRICE_EXIST = 2000762;
    private $FORTNOX_ERROR_CODE_ARTICLE_NUMBER_MISSING = 2001846;

    /**
     * Fetches contacts from Fortnox and writes them local db
     *
     * @access public
     * @return void
     */
    public function fetch_fortnox_contacts() {
        include_once("class-fortnox3-order-xml.php");
        include_once("class-fortnox3-database-interface.php");
        include_once("class-fortnox3-api.php");

        $apiInterface = new WCF_API();
        $customers = $apiInterface->get_customers();
        $databaseInterface = new WCF_Database_Interface();

        foreach($customers as $customer){
            foreach($customer as $c){
                $databaseInterface->create_existing_customer($c);
            }
        }
        return true;
    }

    /**
     * Sends contact to Fortnox API
     *
     * @access public
     * @param int $orderId
     * @return void
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
            if(!isset($options['activate-orders'])){
                return;
            }
            if($options['activate-orders'] == 'on'){
                $orderNumber = $this->send_order_to_fortnox($orderId, $customerNumber);
                if($orderNumber == 0){
                    return;
                }
            }
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

            //create Order XML
            $orderDoc = new WCF_Order_XML_Document();
            $orderXml = $orderDoc->create($order, $customerNumber);

            //send Order XML
            $orderResponse = $apiInterface->create_order_request($orderXml);

            if(AUTOMATED_TESTING){
                $action = 'create';
            }
            //Error handling
            if(array_key_exists('Error', $orderResponse)){
                logthis(print_r($orderResponse, true));
                // if order exists
                if((int)$orderResponse['Code'] == $this->FORTNOX_ERROR_CODE_ORDER_EXISTS){
                    logthis("ORDER EXISTS");
                    $orderResponse = $apiInterface->update_order_request($orderXml, $orderId);
                    if(AUTOMATED_TESTING){
                        $action = 'update';
                    }
                }
                // if products dont exist
                elseif((int)$orderResponse['Code'] == $this->FORTNOX_ERROR_CODE_PRODUCT_NOT_EXIST){
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
                    return 0;
                }
            }
            if(!isset($options['activate-invoices'])){
                if(AUTOMATED_TESTING){
                    return array(
                        'action' => $action,
                        'order_response' => $orderResponse,
                    );
                };
                return;
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
        if(AUTOMATED_TESTING){
            return array(
                'action' => $action,
                'order_response' => $orderResponse,
            );
        };
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

        $apiInterface = new WCF_API();
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
        return true;
    }


    /**
     * Syncs ALL products to Fortnox API
     *
     * @access public
     * @return bool
     */
    public function initial_products_sync() {
        $args = array(
            'post_type' => 'product',
            'orderby' => 'id',
            'posts_per_page' => -1,
        );
        $the_query = new WP_Query( $args );
        foreach($the_query->get_posts() as $product){
            $this->send_product_to_fortnox($product->ID);
        }
        wp_reset_postdata();
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
        if($options['activate-prices'] == 'on' && $this->is_api_key_valid()){
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

                //child logic
                if(AUTOMATED_TESTING){
                    $has_children = false;
                };

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

                    //Error handling
                    if(array_key_exists('Code', $updateResponse)){
                        //Product does not exist
                        if((int)$updateResponse['Code'] == $this->FORTNOX_ERROR_CODE_PRODUCT_NOT_EXIST){

                            //Create product
                            $productXml = $productDoc->create($product);
                            $productResponse = $apiInterface->create_product_request($productXml);
                            $fortnoxId = $productResponse['ArticleNumber'];

                            //set sku;
                            update_post_meta($productId, '_sku', $fortnoxId);
                            update_post_meta($productId, '_is_synced_to_fortnox', 1);

                            //update price
                            $productPriceXml = $productDoc->update_price($product);
                            $priceResponse = $apiInterface->update_product_price_request($productPriceXml, $fortnoxId);

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
                    $fortnoxId = $productResponse['ArticleNumber'];

                    //set sku;
                    update_post_meta($productId, '_sku', $fortnoxId);
                    update_post_meta($productId, '_is_synced_to_fortnox', 1);

                    //update price
                    $productPriceXml = $productDoc->update_price($product);
                    $priceResponse = $apiInterface->update_product_price_request($productPriceXml, $fortnoxId);

                    if(AUTOMATED_TESTING){
                        return array(
                            'action' => 'create',
                            'product_response' => $productResponse,
                            'price_response' => $priceResponse,
                        );
                    }
                }
            }
        }
    }

    /**
     * Fetches inventory from fortnox and updates WooCommerce inventory
     *
     * @return void
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
    }

    /**
     * Fetches product stock for every product in Woo from Fortnox
     *
     * @return bool
     */
    public function run_manual_inventory_cron_job(){
        include_once("class-fortnox3-api.php");

        logthis('MANUAL INVENTORY');

        $args = array(
            'post_type' => 'product',
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
        return true;
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
        $databaseInterface = new WCF_Database_Interface();
        $customer = $databaseInterface->get_customer_by_email($order->billing_email);

        //Init API
        $apiInterface = new WCF_API();
        //create Contact XML
        $contactDoc = new WCF_Contact_XML_Document();
        $contactXml = $contactDoc->create($order);

        if(empty($customer)){

            $customerId = $databaseInterface->create_customer($order->billing_email);

            //send Contact XML
            $contactResponseCode = $apiInterface->create_customer_request($contactXml);
            $customerNumber = $contactResponseCode['CustomerNumber'];
            $databaseInterface->update_customer($customerId, $customerNumber);

        }
        else{
            $customerNumber = $customer[0]->customer_number;
            $apiInterface->update_customer_request($contactXml, $customerNumber);
        }
        return $customerNumber;
    }

}