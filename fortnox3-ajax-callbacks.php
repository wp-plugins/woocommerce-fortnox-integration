<?php
/**
 * Created by PhpStorm.
 * User: tomas
 * Date: 3/24/15
 * Time: 4:02 PM
 */

add_action( 'wp_ajax_manual_sync_products', 'manual_sync_products_callback' );

function manual_sync_products_callback() {
    global $wpdb; // this is how you get access to the database

    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    ob_start();
    $args = array(
        'post_type' => 'product', 'product_variation',
        'orderby' => 'id',
        'posts_per_page' => -1,
    );
    $the_query = new WP_Query( $args );
    $post_ids = wp_list_pluck( $the_query->posts, 'ID' );
    ob_end_clean();
    echo json_encode($post_ids);
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_manual_sync_products', 'manual_sync_products_callback' );

function check_products_diff_callback() {
    global $wpdb; // this is how you get access to the database

    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    ob_start();
    $args = array(
        'post_type' => 'product', 'product_variation',
        'orderby' => 'id',
        'posts_per_page' => -1,
    );
    $the_query = new WP_Query( $args );
    $post_ids = wp_list_pluck( $the_query->posts, 'ID' );
    ob_end_clean();
    echo json_encode($post_ids);
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_check_products_diff', 'check_products_diff_callback' );

function wp_ajax_manual_diff_sync_orders_callback() {
    global $wpdb; // this is how you get access to the database

    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    ob_start();
    $args = array(
        'post_type' => 'shop_order',
        'orderby' => 'id',
        'post_status' => 'wc-completed',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key'     => '_fortnox_difference_order',
                'value' => '1',
                'type' => 'numeric',
                'compare' => '>='
            ),
            array(
                'key'     => '_fortnox_difference_order',
                'value' => '-1',
                'type' => 'numeric',
                'compare' => '<='
            ),
        ),
    );

    $the_query = new WP_Query( $args );
    $post_ids = wp_list_pluck( $the_query->posts, 'ID' );
    ob_end_clean();
    echo json_encode($post_ids);
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_fetch_contacts', 'fetch_contacts_callback' );

function fetch_contacts_callback() {
    global $wpdb; // this is how you get access to the database
    include_once("class-woo-fortnox-controller.php");
    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    ob_start();
    $controller = new WC_Fortnox_Controller();
    $message = $controller->fetch_fortnox_contacts();
    ob_end_clean();
    echo $message;

    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_send_support_mail', 'send_support_mail_callback' );

function send_support_mail_callback() {

    $message = 'Kontakta ' . $_POST['name'] . ' på ' . $_POST['company'] . ' antingen på ' .$_POST['telephone'] .
        ' eller ' . $_POST['email'] . ' gällande: \n' . $_POST['subject'];
    $sent = wp_mail( 'support@wp-plugs.com', 'Fortnox Support', $message);
    echo $sent;
    //die(); // this is required to return a proper result
}

add_action( 'wp_ajax_sync_orders', 'sync_orders_callback' );

function sync_orders_callback() {
    global $wpdb; // this is how you get access to the database
    include_once("class-woo-fortnox-controller.php");
    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    $controller = new WC_Fortnox_Controller();
    $message = $controller->sync_orders_to_fortnox();
    echo $message;
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_update_fortnox_inventory', 'update_fortnox_inventory_callback' );

function update_fortnox_inventory_callback() {
    global $wpdb; // this is how you get access to the database
    include_once("class-woo-fortnox-controller.php");
    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    ob_start();
    $controller = new WC_Fortnox_Controller();
    $message = $controller->run_inventory_cron_job();
    ob_end_clean();
    echo $message;
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_missing_list', 'missing_list_callback' );

function missing_list_callback() {
    global $wpdb; // this is how you get access to the database
    include_once("class-woo-fortnox-controller.php");
    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    ob_start();
    $controller = new WC_Fortnox_Controller();
    $message = $controller->diff_woo_fortnox_inventory();
    ob_end_clean();
    echo $message;
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_clean_sku', 'clean_sku_callback' );

function clean_sku_callback() {
    global $wpdb; // this is how you get access to the database
    include_once("class-woo-fortnox-controller.php");
    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    //ob_start();
    $controller = new WC_Fortnox_Controller();
    $message = $controller->SKU_clean();
    //ob_end_clean();
    echo $message;
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_sync_all_orders', 'sync_all_orders_callback' );

function sync_all_orders_callback() {

    global $wpdb; // this is how you get access to the database
    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    ob_start();

    $args = array(
        'post_type' => 'shop_order',
        'post_status' => 'wc-completed',
        'orderby' => 'id',
        'posts_per_page' => -1                                                                                                                                                                                                                                         ,
    );
    $the_query = new WP_Query( $args );
    $post_ids = wp_list_pluck( $the_query->posts, 'ID' );
    ob_end_clean();
    echo json_encode($post_ids);
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_sync_order', 'sync_order_callback' );

function sync_order_callback() {

    global $wpdb; // this is how you get access to the database
    include_once("class-woo-fortnox-controller.php");
    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    $controller = new WC_Fortnox_Controller();
    $message = $controller->send_contact_to_fortnox($_POST['order_id']);
    echo json_encode($message);
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_sync_product', 'sync_product_callback' );

function sync_product_callback() {

    global $wpdb; // this is how you get access to the database
    include_once("class-woo-fortnox-controller.php");
    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    $controller = new WC_Fortnox_Controller();
    $message = $controller->send_product_to_fortnox($_POST['product_id']);
    echo json_encode($message);
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_set_product_as_unsynced', 'set_product_as_unsynced_callback' );

function set_product_as_unsynced_callback() {

    global $wpdb; // this is how you get access to the database
    delete_post_meta($_POST['product_id'], '_is_synced_to_fortnox');
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_clear_accesstoken', 'clear_accesstoken_callback' );

function clear_accesstoken_callback() {

    global $wpdb; // this is how you get access to the database
    delete_option('fortnox_access_token');
    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_check_diff', 'check_diff_callback' );

function check_diff_callback() {
    global $wpdb; // this is how you get access to the database
    include_once("class-fortnox3-api.php");

    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    $pf = new WC_Product_Factory();
    $child = $pf->get_product($_POST['product_id']);
    $sku = $child->get_sku();
    $apiInterface = new WCF_API();
    $article = $apiInterface->get_article($sku);

    if($sku === NULL){
        echo json_encode(array(
            'success'=> false,
            'product_id'=> $_POST['product_id'],
            'sku'=> 'Inget artikelnummer',
            'title' => $child->get_title()
        ));
    }
    else if(array_key_exists('Error', $article)){
        echo json_encode(array(
            'success'=> false,
            'product_id'=> $_POST['product_id'],
            'sku'=> $sku,
            'title' => $child->get_title()
        ));
    }
    else{
        echo json_encode(array(
            'success'=> true,
            'product_id'=> $_POST['product_id'],
            'sku'=> $sku,
            'title' => $child->get_title()
        ));
    }

    die(); // this is required to return a proper result
}

add_action( 'wp_ajax_clean_customer_table', 'clean_customer_table_callback' );

function clean_customer_table_callback() {
    global $wpdb; // this is how you get access to the database
    include_once("class-fortnox3-database-interface.php");
    logthis('clean_customer_table_callback');
    check_ajax_referer( 'fortnox_woocommerce', 'security' );
    $databaseInterface = new WCF_Database_Interface();
    $customer_emails = $databaseInterface->clean_customer_table();

    if($customer_emails){
        logthis($customer_emails);
        $message = 'Tabell rensad.';
        if(is_array($customer_emails)){
            $message .= 'För att undvika dubbletter, ta bort dessa kunder i er Fortnox: ';
            foreach($customer_emails as $email){
                logthis($email);
                $message .= $email->email . ', ';
            }
            $message = substr($message, 0, strlen($message) - 2);
        }
        echo json_encode(array(
            'success'=> true,
            'message'=> $message,
        ));
    }
    else{
        echo json_encode(array(
            'success'=> false,
            'message'=> 'Tabell rensad.',
        ));
    }

    die(); // this is required to return a proper result
}

