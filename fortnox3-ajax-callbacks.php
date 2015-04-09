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
    $message = $controller->run_manual_inventory_cron_job();
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