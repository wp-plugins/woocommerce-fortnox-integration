<?php
/**
 * Plugin Name: WooCommerce Fortnox Integration
 * Plugin URI: http://plugins.svn.wordpress.org/woocommerce-fortnox-integration/
 * Description: A Fortnox 3 API Interface. Synchronizes products, orders and more to fortnox.
 * Also fetches inventory from fortnox and updates WooCommerce
 * Version: 2.02
 * Author: Advanced WP-Plugs
 * Author URI: http://wp-plugs.com
 * License: GPL2
 */

if(!defined('TESTING')){
    define('TESTING', false);
}

if(!defined('AUTOMATED_TESTING')){
    define('AUTOMATED_TESTING', false);
}

if ( ! function_exists( 'logthis' ) ) {
    function logthis($msg) {
        if(TESTING){
            if(!file_exists('/tmp/testlog.log')){
                $fileobject = fopen('/tmp/testlog.log', 'a');
                chmod('/tmp/testlog.log', 0666);
            }
            else{
                $fileobject = fopen('/tmp/testlog.log', 'a');
            }

            if(is_array($msg) || is_object($msg)){
                fwrite($fileobject,print_r($msg, true));
            }
            else{
                fwrite($fileobject,date("Y-m-d H:i:s"). "\n" . $msg . "\n");
            }
        }
        else{
            error_log($msg);
        }
    }
}

if(!defined('WORDPRESS_FOLDER')){
    define('WORDPRESS_FOLDER',$_SERVER['DOCUMENT_ROOT']);
}

if(!defined('PLUGIN_FOLDER')){
    define('PLUGIN_FOLDER',str_replace("\\",'/',dirname(__FILE__)));
}

if(!defined('PLUGIN_PATH')){
    define('PLUGIN_PATH','/' . substr(PLUGIN_FOLDER, stripos(PLUGIN_FOLDER,'wp-content')));
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    load_plugin_textdomain( 'wc_fortnox_extended', false, dirname( plugin_basename( __FILE__ ) ) . '/' );
    if ( ! class_exists( 'WCFortnoxExtended' ) ) {

        include_once("fortnox3-ajax-callbacks.php");

        add_action( 'wp_ajax_test_connection', 'test_connection_callback' );

        function test_connection_callback() {
            include_once("class-fortnox3-api.php");
            $temp = new WCF_API();
            $temp->create_license_validation_request();

            die(); // this is required to return a proper result
        }

        // in javascript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
        function fortnox_enqueue(){
            wp_enqueue_script('jquery');
            wp_register_script( 'fortnox-script', plugins_url( '/woocommerce-fortnox-integration/js/fortnox.js' ) );
            wp_enqueue_script( 'fortnox-script' );
        }

        add_action( 'admin_enqueue_scripts', 'fortnox_enqueue' );

        add_action( 'admin_enqueue_scripts', 'load_fortnox_admin_style' );
        function load_fortnox_admin_style() {
            wp_register_style( 'admin_css', plugins_url( '/woocommerce-fortnox-integration/css/admin-style.css'), false, '1.0.0' );
            wp_enqueue_style( 'admin_css', plugins_url( '/woocommerce-fortnox-integration/css/admin-style.css'), false, '1.0.0' );
        }

        /**
         * Localisation
         **/
        load_plugin_textdomain( 'wc_fortnox', false, dirname( plugin_basename( __FILE__ ) ) . '/' );

        class WC_Fortnox_Extended {

            private $general_settings_key = 'woocommerce_fortnox_general_settings';
            private $accounting_settings_key = 'woocommerce_fortnox_accounting_settings';
            private $order_settings_key = 'woocommerce_fortnox_order_settings';
            private $support_key = 'woocommerce_fortnox_support';
            private $manual_action_key = 'woocommerce_fortnox_manual_action';
            private $start_action_key = 'woocommerce_fortnox_start_action';
            private $differences_key = 'woocommerce_fortnox_differences';
            private $general_settings;
            private $accounting_settings;
            private $plugin_options_key = 'woocommerce_fortnox_options';
            private $plugin_settings_tabs = array();

            public function __construct() {

                //call register settings function
                add_action( 'init', array( &$this, 'load_settings' ) );
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_start_action' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_general_settings' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_order_settings' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_manual_action' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_support' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_order_differences' ));
                add_action( 'admin_menu', array( &$this, 'add_admin_menus' ) );
                add_action( 'add_meta_boxes', array( $this, 'order_meta_box_add'));
                add_action( 'add_meta_boxes', array( $this, 'product_meta_box_add'));
                //Order
                add_filter( 'manage_edit-shop_order_columns',  array( &$this, 'fortnox_order_columns_head'), 20, 1);
                add_action( 'manage_shop_order_posts_custom_column',  array( &$this, 'fortnox_order_columns_content'), 10, 2);
                add_action( 'admin_footer-edit.php',  array( &$this, 'synchronize_bulk_admin_footer'));
                add_action( 'load-edit.php', array( &$this, 'custom_bulk_action'), 10, 1 );
                //Product
                add_filter( 'manage_edit-product_columns',  array( &$this, 'fortnox_product_columns_head'), 20, 1);
                add_action( 'manage_product_posts_custom_column',  array( &$this, 'fortnox_product_columns_content'), 10, 2);

                add_action( 'admin_notices', array( &$this, 'display_admin_notice' ) );

                add_action( 'woocommerce_order_status_completed', array(&$this, 'synchronize_order_on_complete'), 10, 1 );

                // install necessary tables
                register_activation_hook( __FILE__, array(&$this, 'install'));
                register_deactivation_hook( __FILE__, array(&$this, 'uninstall'));
            }

            /***********************************************************************************************************
             * ADMIN SETUP
             ***********************************************************************************************************/

            /**
             * Adds Fortnox Column to listing
             *
             * @access public
             * @return mixed
             */
            public function display_admin_notice() {

                $html = '<div id="ajax-fortnox-notification" class="updated" style="display: none">';
                $html .= '<p id="ajax-fortnox-message">';
                $html .= '</p>';
                $html .= '</div><!-- /.updated -->';

                echo $html;

            } // end display_admin_notice

            /**
             * Performs Fortnox Sync For bulk
             *
             * @access public
             * @return mixed
             */
            function custom_bulk_action() {

                // ...
                logthis("BULK");
                // 1. get the action
                $wp_list_table = _get_list_table('WP_Posts_List_Table');
                $action = $wp_list_table->current_action();

                logthis($action);
                if(!($action == 'bulk_order_fortnox_synchronize' || $action == 'bulk_product_fortnox_synchronize')){
                    return;
                }

                include_once("class-woo-fortnox-controller.php");
                $controller = new WC_Fortnox_Controller();
                $post_ids = array_map( 'absint', (array) $_REQUEST['post'] );
                $changed = 0;

                switch($action){
                    case 'bulk_order_fortnox_synchronize':
                        foreach( $post_ids as $post_id ) {
                            $ret = $controller->send_contact_to_fortnox($post_id);
                            logthis(print_r($ret, true));
                            if(!$ret['success']){
                                wp_die( __('Fel vid synkronisering av order ' . $post_id . ' ' . $ret['message']) );
                            }
                            $changed++;
                        }
                        $report_action = 'bulk_order_fortnox_synchronize';
                        $post_type = 'shop_order';
                        break;
                    case 'bulk_product_fortnox_synchronize':
                        logthis("PRDO");
                        foreach( $post_ids as $post_id ) {
                            $ret = $controller->send_product_to_fortnox($post_id);
                            logthis(print_r($ret, true));
                            if(!$ret['success']){
                                wp_die( __('Fel vid synkronisering av produkt ' . $post_id . ' ' . $ret['message']) );
                            }
                            $changed++;
                        }
                        $report_action = 'bulk_product_fortnox_synchronize';
                        $post_type = 'product';
                        break;

                }
                
                $sendback = add_query_arg( array( 'post_type' => $post_type, $report_action => true, 'changed' => $changed, 'ids' => join( ',', $post_ids ) ), '' );
                wp_redirect( $sendback );

                exit();
            }

            /**
             * Adds Fortnox Column to listing
             *
             * @access public
             * @param $columns
             * @return mixed
             */
            function synchronize_bulk_admin_footer() {

                global $post_type;

                if($post_type == 'shop_order') {
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function() {
                            jQuery('<option>').val('bulk_order_fortnox_synchronize').text('<?php _e('Synkronisera till Fortnox')?>').appendTo("select[name='action']");
                        });
                    </script>
                <?php
                }
                else if($post_type == 'product') {
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function() {
                            jQuery('<option>').val('bulk_product_fortnox_synchronize').text('<?php _e('Synkronisera till Fortnox')?>').appendTo("select[name='action']");
                        });
                    </script>
                <?php
                }
            }

            /**
             * Adds Fortnox Column to listing
             *
             * @access public
             * @param $columns
             * @return mixed
             */
            public function fortnox_order_columns_head($columns){
                $new_columns = (is_array($columns)) ? $columns : array();
                //all of your columns will be added before the actions column
                $new_columns['fortnox_order_synchronized'] = '<span class="center">Fortnox</span>';
                $new_columns['fortnox_synchronize'] = '<span class="center">Synkronisera</span>';
                //stop editing

                $new_columns['order_actions'] = $columns['order_actions'];
                return $new_columns;
            }

            /**
             * Renders image for Fortnox status
             *
             * @access public
             * @param $column_name
             * @param $post_id
             * @return void
             */
            public function fortnox_order_columns_content($column_name, $post_id) {
                $ajax_nonce = wp_create_nonce( "fortnox_woocommerce" );
                if ($column_name == 'fortnox_order_synchronized') {
                    $synced = get_post_meta($post_id, '_fortnox_order_synced', true);
                    if($synced == 1){ ?>
                        <mark class="fortnox-status completed" title="Order har synkroniserats"></mark>
                    <?php }
                    else { ?>
                        <mark class="fortnox-status not-completed" title="Order har EJ synkroniserats" ></mark>
                    <?php }
                }
                elseif($column_name == 'fortnox_synchronize'){?>
                    <button type="button" class="button" title="Exportera" style="margin:5px" onclick="sync_order(<?php echo $post_id;?>, '<?php echo $ajax_nonce;?>')">></button>
                <?php }

            }

            /**
             * Adds metabox content to product
             *
             * @access public
             * @param void
             * @return void
             */
            public function fortnox_order_meta_box_cb(){
                global $post;

                // Get the location data if its already been entered
                $synced = get_post_meta($post->ID, '_fortnox_order_synced', true);
                if($synced == 1){
                    echo "Order har synkroniserats";
                }
                else{
                    echo "Order har EJ synkroniserats";
                }
            }

            /**
             * Adds meta box to product
             *
             * @access public
             * @param void
             * @return void
             */
            public function order_meta_box_add(){
                add_meta_box( 'fortnox-order-meta-box-id', 'Fortnox', array( $this, 'fortnox_order_meta_box_cb'), 'shop_order', 'normal', 'high' );
            }

            /**
             * Adds Fortnox Column to listing
             *
             * @access public
             * @param $columns
             * @return void
             */
            public function fortnox_product_columns_head($columns){
                $new_columns = (is_array($columns)) ? $columns : array();
                //unset( $new_columns['order_actions'] );
                logthis(print_r($columns,true));

                $new_columns['fortnox_product_synchronized'] = '<span class="center">Fortnox</span>';
                $new_columns['fortnox_synchronize'] = '<span class="center">Synkronisera</span>';
                //stop editing

                return $new_columns;
            }

            /**
             * Renders image for Fortnox status
             *
             * @access public
             * @param $column_name
             * @param $post_id
             * @return void
             */
            public function fortnox_product_columns_content($column_name, $post_id) {
                $ajax_nonce = wp_create_nonce( "fortnox_woocommerce" );
                if ($column_name == 'fortnox_product_synchronized') {
                    $synced = $synced = $this->is_product_synced($post_id);
                    if($synced == 1){ ?>
                        <mark class="fortnox-status completed" title="Produkt har synkroniserats" onclick="set_product_as_unsynced(<?php echo $post_id;?>, '<?php echo $ajax_nonce;?>')"></mark>
                        <?php }
                         else { ?>
                        <mark class="fortnox-status not-completed" title="Produkt har EJ synkroniserats"></mark>
                    <?php }
                }
                elseif($column_name == 'fortnox_synchronize'){?>
                    <button type="button" class="button" title="Exportera" style="margin:5px" onclick="sync_product(<?php echo $post_id;?>, '<?php echo $ajax_nonce;?>')">></button>

                <?php }
            }

            /**
             * Adds metabox content to product
             *
             * @access public
             * @param void
             * @return void
             */
            public function fortnox_product_meta_box_cb(){
                global $post;

                // Get the location data if its already been entered
                $synced = $this->is_product_synced($post->ID);
                if($synced){
                    echo "Produkt har synkroniserats";
                }
                else{
                    echo "Produkt har EJ synkroniserats";
                }
            }

            /**
             * Adds meta box to product
             *
             * @access private
             * @param $product_id
             * @return bool
             */

            private function is_product_synced($product_id){
                $synced = get_post_meta($product_id, '_is_synced_to_fortnox', true);

                $pf = new WC_Product_Factory();
                $product = $pf->get_product($product_id);
                if($product->has_child()){
                    //sync children
                    $num = count($product->get_children());
                    $counter = 0;
                    foreach($product->get_children() as $child_id){
                        $synced = get_post_meta($child_id, '_is_synced_to_fortnox', true);
                        if($synced == 1){
                            $counter++;
                        }
                    }
                    if($counter == $num){
                        return true;
                    }
                    else {
                        return false;
                    }
                }
                else if($synced == 1){
                    return true;
                }
                else {
                    return false;
                }
            }

            /**
             * Adds meta box to product
             *
             * @access public
             * @param void
             * @return void
             */
            public function product_meta_box_add(){
                add_meta_box( 'fortnox-product-meta-box-id', 'Fortnox', array( $this, 'fortnox_product_meta_box_cb'), 'product', 'normal', 'high' );
            }

            /**
             * Adds admin menu
             *
             * @access public
             * @param void
             * @return void
             */
            public function add_admin_menus() {
                add_options_page( 'WooCommerce Fortnox Integration', 'WooCommerce Fortnox Integration', 'manage_options', $this->plugin_options_key, array( &$this, 'woocommerce_fortnox_options_page' ) );
            }

            /**
             * Generates html for textfield for given settings params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_gateway($args) {
                $options = get_option($args['tab_key']);?>

                <input type="hidden" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]" value="<?php echo $args['key']; ?>" />

                <select name="<?php echo $args['tab_key']; ?>[<?php echo $args['key'] . "_payment_method"; ?>]" >';
                    <option value=""<?php if(isset($options[$args['key'] . "_payment_method"]) && $options[$args['key'] . "_payment_method"] == ''){echo 'selected="selected"';}?>>Välj nedan</option>
                    <option value="CARD"<?php if(isset($options[$args['key'] . "_payment_method"]) && $options[$args['key'] . "_payment_method"] == 'CARD'){echo 'selected="selected"';}?>>Kortbetalning</option>
                    <option value="BANK"<?php if(isset($options[$args['key'] . "_payment_method"]) && $options[$args['key'] . "_payment_method"] == 'BANK'){echo 'selected="selected"';}?>>Bankgiro/Postgiro</option>
                </select>
                <?php
                $str = '';
                if(isset($options[$args['key'] . "_book_keep"])){
                    if($options[$args['key'] . "_book_keep"] == 'on'){
                        $str = 'checked = checked';
                    }
                }
                ?>
                <span>Bokför automatiskt:  </span>
                <input type="checkbox" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key'] . "_book_keep"; ?>]" <?php echo $str; ?> />

            <?php
            }

            /**
             * Generates html for textfield for given settings params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_option_text($args) {
                $options = get_option($args['tab_key']);
                $val = '';
                if(isset($options[$args['key']] )){
                    $val = esc_attr( $options[$args['key']] );
                }
                ?>
                <input type="text" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]" value="<?php echo $val; ?>" />
                <span><i><?php echo $args['desc']; ?></i></span>
            <?php
            }

            /**
             * Generates html for textfield for given settings params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_hidden_option_text($args) {
                $options = get_option($args['tab_key']);
                $val = '';
                if(isset($options[$args['key']] )){
                    $val = esc_attr( $options[$args['key']] );
                }
                ?>
                <input type="text" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]" value="<?php echo $val; ?>" />
                <span><i><?php echo $args['desc']; ?></i></span>
            <?php
            }

            /**
             * Generates html for checkbox for given settings params
             *
             * @access public
             * @param void
             * @return void
             */
            function field_option_checkbox($args) {
                $options = get_option($args['tab_key']);
                $str = '';
                if(isset($options[$args['key']])){
                    if($options[$args['key']] == 'on'){
                        $str = 'checked = checked';
                    }
                }

                ?>
                <input type="checkbox" name="<?php echo $args['tab_key']; ?>[<?php echo $args['key']; ?>]" <?php echo $str; ?> />
                <span><i><?php echo $args['desc']; ?></i></span>
            <?php
            }

            /**
             * WooCommerce Loads settigns
             *
             * @access public
             * @param void
             * @return void
             */
            function load_settings() {
                $this->general_settings = (array) get_option( $this->general_settings_key );
                $this->accounting_settings = (array) get_option( $this->accounting_settings_key );
                $this->order_settings = (array) get_option( $this->order_settings_key );
            }

            /**
             * Tabs and plugin page setup
             *
             * @access public
             * @param void
             * @return void
             */
            function plugin_options_tabs() {
                $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->start_action_key;
                $options = get_option('woocommerce_fortnox_general_settings');
                echo '<div class="wrap"><h2>WooCommerce Fortnox Integration</h2><div id="icon-edit" class="icon32"></div></div>';
                if(!isset($options['api-key']) || $options['api-key'] == ''){
                    echo "<button type=\"button button-primary\" class=\"button button-primary\" title=\"\" style=\"margin:5px\" onclick=\"window.open('http://whmcs.onlineforce.net/cart.php?a=add&pid=49&carttpl=flex-web20cart','_blank');\">Hämta API-Nyckel</button>";
                }

                if (!function_exists('curl_version')){
                    echo '<div class="error"><p>PHP cURL saknas. Pluginet kommer EJ att fungera utan det. Kontakta din serveradmin.<a href="http://wp-plugs.com/php-curl-saknas">Se mer info</a></p></div>';
                }
                echo '<h2 class="nav-tab-wrapper">';

                foreach ( $this->plugin_settings_tabs as $tab_key => $tab_caption ) {
                    $active = $current_tab == $tab_key ? 'nav-tab-active' : '';
                    echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->plugin_options_key . '&tab=' . $tab_key . '">' . $tab_caption . '</a>';
                }
                echo '</h2>';

            }

            /**
             * WooCommerce Fortnox General Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_fortnox_general_settings() {

                $this->plugin_settings_tabs[$this->general_settings_key] = 'Allmänna inställningar';

                register_setting( $this->general_settings_key, $this->general_settings_key );
                add_settings_section( 'section_general', 'Allmänna inställningar', array( &$this, 'section_general_desc' ), $this->general_settings_key );
                add_settings_field( 'woocommerce-fortnox-api-key', 'API Nyckel', array( &$this, 'field_hidden_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'api-key', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-authorization-code', 'Fortnox API-kod', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'authorization_code', 'desc' => 'Här anges din API kod från Fortnox. <a target="_blank" href="http://vimeo.com/107836260#t=1m20s">Videoinstruktion</a>') );
                add_settings_field( 'woocommerce-fortnox-activate-automatic-orders', 'Aktivera automatisk synkning av ordrar', array( &$this, 'field_option_checkbox'), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-automatic-orders', 'desc' => ''));
                add_settings_field( 'woocommerce-fortnox-activate-invoices', 'Skapa faktura för varje order', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-invoices', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-activate-bookkeeping', 'Aktivera automatisk bokföring för faktura', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-bookkeeping', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-activate-fortnox-products-sync', 'Aktivera lagersaldosynkning från fortnox', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-fortnox-products-sync', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-product-price-including-vat', 'Synkronisera produktpriser inklusive moms', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'product-price-including-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-sync-master', 'Synkronisera Master-produkten', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'sync-master', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-do-not-sync-children', 'Synkronisera EJ variationer', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'do-not-sync-children', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-default-pricelist', 'Prislista', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'default-pricelist', 'desc' => 'Standard är prislita A om inget anges') );
            }

            /**
             * WooCommerce Manual Actions Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_fortnox_manual_action() {

                $this->plugin_settings_tabs[$this->manual_action_key] = 'Manuella funktioner';
                register_setting( $this->manual_action_key, $this->manual_action_key );
            }

            /**
             * WooCommerce Start Actions
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_fortnox_start_action() {

                $this->plugin_settings_tabs[$this->start_action_key] = 'Välkommen!';
                register_setting( $this->start_action_key, $this->start_action_key );
            }

            /**
             * WooCommerce Fortnox Order Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_fortnox_order_settings() {

                $this->plugin_settings_tabs[$this->order_settings_key] = 'Orderinställningar';

                register_setting( $this->order_settings_key, $this->order_settings_key );
                add_settings_section( 'section_order', 'Orderinställningar', array( &$this, 'section_order_desc' ), $this->order_settings_key );
                add_settings_field( 'woocommerce-fortnox-admin-fee', 'Administrationsavgift', array( &$this, 'field_option_text'), $this->order_settings_key, 'section_order', array ( 'tab_key' => $this->order_settings_key, 'key' => 'admin-fee', 'desc' => 'Här anges fakturaavgiften/administrationsavgiften') );
                add_settings_field( 'woocommerce-fortnox-payment-options', 'Betalningsvillkor för order', array( &$this, 'field_option_text'), $this->order_settings_key, 'section_order', array ( 'tab_key' => $this->order_settings_key, 'key' => 'payment-options', 'desc' => 'Här anges Fortnox-koden för betalningsalternativ för ordern. Koder finns under INSTÄLLNINGAR->BOKFÖRING->BETALNINGSALTERNATIV i Fortnox.') );
                add_settings_field( 'woocommerce-fortnox-add-payment-type', 'Lägg till betaltyp på order', array( &$this, 'field_option_checkbox'), $this->order_settings_key, 'section_order', array ( 'tab_key' => $this->order_settings_key, 'key' => 'add-payment-type', 'desc' => '') );
            }

            /**
             * WooCommerce Fortnox Accounting Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_fortnox_support() {

                $this->plugin_settings_tabs[$this->support_key] = 'Support';
                register_setting( $this->support_key, $this->support_key );
            }

            /**
             * WooCommerce Fortnox Order Differences
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_fortnox_order_differences() {

                $this->plugin_settings_tabs[$this->differences_key] = 'Order diff';
                register_setting( $this->support_key, $this->differences_key );
            }

            /**
             * The description for the general section
             *
             * @access public
             * @param void
             * @return void
             */
            function section_general_desc() { echo 'Här anges grundinställningar för Fortnoxkopplingen och här kan man styra vilka delar som ska synkas till Fortnox'; }

            /**
             * The description for the accounting section
             *
             * @access public
             * @param void
             * @return void
             */
            function section_accounting_desc() { echo 'Beskrivning bokföringsinställningar.'; }

            /**
             * The description for the shipping section
             *
             * @access public
             * @param void
             * @return void
             */
            function section_order_desc() { echo ''; }

            /**
             * Options page
             *
             * @access public
             * @param void
             * @return void
             */
            function woocommerce_fortnox_options_page() {
                $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->start_action_key;?>

                <!-- CSS -->
                <style>
                    li.logo,  {
                        float: left;
                        width: 100%;
                        padding: 20px;
                    }
                    li.full {
	                    padding: 10px 0;
                    }
                    li.col-two {
                        float: left;
                        width: 380px;
                        margin-left: 1%;
                    }
                    li.col-onethird, li.col-twothird {
	                    float: left;
                    }
                    li.col-twothird {
	                    max-width: 772px;
	                    margin-right: 20px;
                    }
                    li.col-onethird {
	                    width: 300px;
                    }
                    .mailsupport {
	                	background: #dadada;
	                	border-radius: 4px;
	                	-moz-border-radius: 4px;
	                	-webkit-border-radius: 4px;
	                	max-width: 230px;
	                	padding: 0 0 20px 20px;
	                }
	                .mailsupport > h2 {
		                font-size: 20px;
		            }
	                form#support table.form-table tbody tr td {
		                padding: 4px 0 !important;
		            }
		            form#support input, form#support textarea {
			                border: 1px solid #b7b7b7;
			                border-radius: 3px;
			                -moz-border-radius: 3px;
			                -webkit-border-radius: 3px;
			                box-shadow: none;
			                width: 210px;
			        }
			        form#support textarea {
				        height: 60px;
			        }
			        form#support button {
				        float: left;
				        margin: 0 !important;
				        min-width: 100px;
				    }
				    ul.manuella li.full button.button {
					       clear: left;
					       float: left;
					       min-width: 250px;
				    }
				    ul.manuella li.full > p {
					        clear: right;
					        float: left;
					        margin: 2px 0 20px 11px;
					        max-width: 440px;
					        padding: 5px 10px;
					}
                </style>
                <?php
                if($tab == $this->support_key){ ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <ul>
                            <li class="logo"><?php echo '<img src="' . plugins_url( 'img/logo_landscape.png', __FILE__ ) . '" > '; ?></li>
                            <li class="col-two"><a href="http://wp-plugs.com/woocommerce-fortnox/#faq"><?php echo '<img src="' . plugins_url( 'img/awp_faq.png', __FILE__ ) . '" > '; ?></a></li>
                            <li class="col-two"><a href="http://wp-plugs.com/support"><?php echo '<img src="' . plugins_url( 'img/awp_support.png', __FILE__ ) . '" > '; ?></a></li>
                    </div>
                <?php
                }
                else if($tab == $this->general_settings_key){ ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <form method="post" action="options.php">
                            <?php wp_nonce_field( 'update-options' ); ?>
                            <?php settings_fields( $tab ); ?>
                            <?php do_settings_sections( $tab ); ?>
                            <?php submit_button(); ?>
                        </form>
                    </div>
                <?php }
                else if($tab == $this->manual_action_key){
                    $ajax_nonce = wp_create_nonce( "fortnox_woocommerce" );?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <ul class="manuella">
                            <li class="full">
                                <button type="button" class="button" title="Manuell Synkning" style="margin:5px" onclick="fetch_contacts('<?php echo $ajax_nonce;?>')">Manuell synkning kontakter</button>
                                <p>Hämtar alla kunder från er Fortnox. Detta görs för att undvika dubbletter.</p>
                            </li>
                            <li class="full" style="display: none;">
                                <button type="button" class="button" title="Manuell Synkning Orders" style="margin:5px" onclick="sync_all_orders('<?php echo $ajax_nonce;?>')">Manuell synkning ordrar</button>
                                <p>Synkroniserar alla ordrar som misslyckats att synkronisera.</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Manuell Synkning Produkter" style="margin:5px" onclick="manual_sync_products('<?php echo $ajax_nonce;?>')">Manuell synkning produkter</button>
                                <p>Skicka alla produkter till er Fortnox. Om ni har många produkter kan det ta ett tag.</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Uppdatera lagersaldo från Fortnox" style="margin:5px" onclick="update_fortnox_inventory('<?php echo $ajax_nonce;?>')">Uppdatera lagersaldo från Fortnox</button>
                                <p>Uppdatera lagersaldo från Fortnox. Om ni har många produkter kan det ta ett tag.</p>
                            </li>
                            <li class="full" style="display: none;">
                                <button type="button" class="button" title="Visa diff lista" style="margin:5px" onclick="missing_list('<?php echo $ajax_nonce;?>')">DiffLista</button>
                                <p>Visa diff-lista</p>
                            </li>
                            <li class="full" style="display: none;">
                                <button type="button" class="button" title="Visa diff lista" style="margin:5px" onclick="clean_sku('<?php echo $ajax_nonce;?>')">Rensa SKU-nummer</button>
                                <p>Rensa SKU-nummer</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Synkronisera differensordrar " style="margin:5px" onclick="manual_diff_sync_orders('<?php echo $ajax_nonce;?>')">Synkronisera differensordrar</button>
                                <p>Synkronisera ordrar, vars total har en differens mot FortNox</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Synkronisera alla ordrar" style="margin:5px" onclick="sync_all_orders('<?php echo $ajax_nonce;?>')">Synkronisera alla ordrar</button>
                                <p>Synkronisera alla godkända ordrar</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Radera accesstoken" style="margin:5px" onclick="clear_accesstoken('<?php echo $ajax_nonce;?>')">Radera accesstoken</button>
                                <p>Radera accesstoken</p>
                            </li>
                        </ul>
                    </div>
                <?php }
                else if($tab == $this->start_action_key){
                    $options = get_option('woocommerce_fortnox_general_settings');
                    ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <ul>
                        	<li class="full">
                        		<?php echo '<img src="' . plugins_url( 'img/banner-772x250.png', __FILE__ ) . '" > '; ?>
                        	</li>
                            <li class="col-twothird">
                                <iframe src="//player.vimeo.com/video/107836260" width="500" height="281" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
                            </li>
                            <?php if(!isset($options['api-key']) || $options['api-key'] == ''){ ?>
                            <li class="col-onethird">
                            	<div class="mailsupport">
                            		<h2>Installationssupport</h2>
                            	    <form method="post" id="support">
                            	        <input type="hidden" value="send_support_mail" name="action">
                            	        <table class="form-table">
								
                            	            <tbody>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Företag" name="company">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Namn" name="name">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Telefon" name="telephone">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <input type="text" value="" placeholder="Email" name="email">
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <textarea placeholder="Ärende" name="subject"></textarea>
                            	                </td>
                            	            </tr>
                            	            <tr valign="top">
                            	                <td>
                            	                    <button type="button" class="button button-primary" title="send_support_mail" style="margin:5px" onclick="send_support_mail()">Skicka</button>
                            	                </td>
                            	            </tr>
                            	            </tbody>
                            	        </table>
                            	        <!-- p class="submit">
                            	           <button type="button" class="button button-primary" title="send_support_mail" style="margin:5px" onclick="send_support_mail()">Skicka</button> 
                            	        </p -->
                            	    </form>
                            	</div>
                            </li>
                        <?php } ?>
                        </ul>
                    </div>
                <?php }
                else if($tab == $this->differences_key){
                    global $wpdb;
                    $differences = $wpdb->get_results("SELECT * FROM wp_postmeta where meta_key = '_fortnox_difference_order'");?>
                    <div class="wrap">
                    <?php $this->plugin_options_tabs(); ?>
                        <table class="manuella">
                    <?php
                    if ( $differences ){
                        foreach ( $differences as $difference ){
                            if(abs(floatval($difference->meta_value)) > 1){
                                $order = get_post($difference->post_id);
                                if($order->post_status != 'wc-failed' || $order->post_status != 'wc-cancelled' ){?>
                                <tr>
                                    <td>Order ID: <?php echo $difference->post_id; ?></td>
                                    <td>Differens: <?php echo $difference->meta_value; ?></td>
                                </tr>
                                <?php }
                            }
                        }
                    }
                    ?>
                        </table>
                    </div>
                <?php }
                else{ ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <form method="post" action="options.php">
                            <?php wp_nonce_field( 'update-options' ); ?>
                            <?php settings_fields( $tab ); ?>
                            <?php do_settings_sections( $tab ); ?>
                            <?php submit_button(); ?>
                        </form>
                    </div>
                <?php }
            }

            /***********************************************************************************************************
             * DATABASE FUNCTIONS
             ***********************************************************************************************************/

            /**
             * Creates tables for WooCommerce Fortnox
             *
             * @access public
             * @param void
             * @return bool
             */
            public function install(){
                global $wpdb;
                $table_name = "wcf_orders";
                $sql = "CREATE TABLE IF NOT EXISTS ".$table_name."( id mediumint(9) NOT NULL AUTO_INCREMENT,
                        order_id mediumint(9) NOT NULL,
                        synced tinyint(1) DEFAULT FALSE NOT NULL,
                        UNIQUE KEY id (id)
                );";

                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql );

                $table_name = "wcf_customers";
                $sql = "CREATE TABLE IF NOT EXISTS ".$table_name."( id mediumint(9) NOT NULL AUTO_INCREMENT,
                        customer_number VARCHAR(50) NULL,
                        email VARCHAR(100) NOT NULL,
                        UNIQUE KEY id (id),
                        UNIQUE (email)
                );";
                dbDelta( $sql );
                return true;
            }

            /**
             * Drops tables for WooCommerce Fortnox
             *
             * @access public
             * @param void
             * @return bool
             */
            public function uninstall(){
                global $wpdb;
                $table_name = "wcf_orders";
                $sql = "DROP TABLE ".$table_name.";";

                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
                dbDelta( $sql );
                return true;
            }

            /***********************************************************************************************************
             * FORTNOX FUNCTIONS
             ***********************************************************************************************************/

            /**
             * Sends contact of given order to Fortnox API
             *
             * @access public
             * @param int $orderId
             */
            public function synchronize_order_on_complete($orderId) {
                include_once("class-woo-fortnox-controller.php");

                $options = get_option('woocommerce_fortnox_general_settings');
                if(!isset($options['activate-automatic-orders'])){
                    return;
                }
                $controller = new WC_Fortnox_Controller();
                $controller->send_contact_to_fortnox($orderId);
            }

            /**
             * Sends product to Fortnox API
             *
             * @access public
             * @param $productId
             * @internal param int $productId
             */
            public function send_product_to_fortnox($productId) {
                include_once("class-woo-fortnox-controller.php");
                $controller = new WC_Fortnox_Controller();
                $controller->send_product_to_fortnox($productId);

            }
        }
        $GLOBALS['wc_consuasor'] = new WC_Fortnox_Extended();
    }
}