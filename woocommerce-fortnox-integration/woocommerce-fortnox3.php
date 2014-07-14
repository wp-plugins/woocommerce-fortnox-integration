<?php
/**
 * Plugin Name: WooCommerce Fortnox Integration
 * Plugin URI: http://plugins.svn.wordpress.org/woocommerce-fortnox-integration/
 * Description: A Fortnox 3 API Interface. Synchronizes products, orders and more to fortnox
 * Version: 1.2
 * Author: Tomas Kircher
 * Author URI: http://kircherrodewiberg.se
 * License: GPL2
 */

if ( ! function_exists( 'logthis' ) ) {
    function logthis($msg) {
        $fileobject = fopen('/tmp/testlog.log', 'a');
        if(is_array($msg || is_object($msg))){
            fwrite($fileobject,print_r($msg, true));
        }
        else{
            fwrite($fileobject,date("Y-m-d H:i:s"). "\n" . $msg . "\n");
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

    load_plugin_textdomain( 'wc_fortnox', false, dirname( plugin_basename( __FILE__ ) ) . '/' );
    if ( ! class_exists( 'WC_Fortnox' ) ) {

        // in javascript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
        function fortnox_enqueue(){
            wp_enqueue_script('jquery');
            wp_register_script( 'fortnox-script', plugins_url( '/woocommerce-fortnox3/js/fortnox.js' ) );
            wp_enqueue_script( 'fortnox-script' );
        }

        add_action( 'admin_enqueue_scripts', 'fortnox_enqueue' );
        add_action( 'wp_ajax_initial_sync_products', 'initial_sync_products_callback' );

        function initial_sync_products_callback() {
            global $wpdb; // this is how you get access to the database

            $fnox = new WC_Fortnox();
            if($fnox->initial_products_sync()){
                echo "Produkter är synkroniserade utan problem.";
            }
            else{
                echo "Något gick fel";
            }
            die(); // this is required to return a proper result
        }

        add_action( 'wp_ajax_sync_orders', 'sync_orders_callback' );

        function sync_orders_callback() {
            global $wpdb; // this is how you get access to the database
            $fnox = new WC_Fortnox();

            if($fnox->sync_orders_to_fortnox()){
                echo "Ordrar är synkroniserade utan problem.";
            }
            else{
                echo "Något gick fel";
            }
            die(); // this is required to return a proper result
        }

        add_action( 'wp_ajax_fetch_contacts', 'fetch_contacts_callback' );

        function fetch_contacts_callback() {
            global $wpdb; // this is how you get access to the database
            $fnox = new WC_Fortnox();
            $fnox->fetch_fortnox_contacts();
            echo "Kontakter är synkroniserade utan problem.";
            die(); // this is required to return a proper result
        }

        /**
         * Localisation
         **/
        load_plugin_textdomain( 'wc_fortnox', false, dirname( plugin_basename( __FILE__ ) ) . '/' );

        class WC_Fortnox {

            private $general_settings_key = 'woocommerce_fortnox_general_settings';
            private $accounting_settings_key = 'woocommerce_fortnox_accounting_settings';
            private $order_settings_key = 'woocommerce_fortnox_order_settings';
            private $support_key = 'woocommerce_fortnox_support';
            private $general_settings;
            private $accounting_settings;
            private $plugin_options_key = 'woocommerce_fortnox_options';
            private $plugin_settings_tabs = array();

            public function __construct() {

                //call register settings function
                add_action( 'init', array( &$this, 'load_settings' ) );
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_general_settings' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_order_settings' ));
                //add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_accounting_settings' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_support' ));
                add_action( 'admin_menu', array( &$this, 'add_admin_menus' ) );

                // Register WooCommerce Hooks
                add_action( 'woocommerce_order_status_completed', array(&$this, 'send_contact_to_fortnox'), 10, 1 );
                add_action( 'save_post', array(&$this, 'send_product_to_fortnox'), 10, 1 );

                // install necessary tables
                register_activation_hook( __FILE__, array(&$this, 'install'));
                register_deactivation_hook( __FILE__, array(&$this, 'uninstall'));
            }

            /***********************************************************************************************************
             * ADMIN SETUP
             ***********************************************************************************************************/

            /**
             * Adds admin menu
             *
             * @access public
             * @param void
             * @return void
             */
            function add_admin_menus() {
                add_options_page( 'WooCommerce Fortnox', 'WooCommerce Fortnox', 'manage_options', $this->plugin_options_key, array( &$this, 'woocommerce_fortnox_options_page' ) );
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
                $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->general_settings_key;
                echo '<div class="wrap"><h2>Fortnox WooCommerce</h2><div id="icon-edit" class="icon32"></div></div>';

                $options = get_option('woocommerce_fortnox_general_settings');
                if(!isset($options['api-key']) || $options['api-key'] == ''){
                    echo "<button type=\"button button-primary\" class=\"button button-primary\" title=\"\" style=\"margin:5px\" onclick=\"window.open('http://wp-plugs.com','_blank');\">Hämta API-Nyckel</button>";
                }
                echo '<button type="button" class="button" title="Manuell Synkning" style="margin:5px" onclick="fetch_contacts()">Manuell synkning kontakter</button>';
                echo '<button type="button" class="button" title="Manuell Synkning Orders" style="margin:5px" onclick="sync_orders()">Manuell synkning ordrar</button>';
                echo '<button type="button" class="button" title="Manuell Synkning Products" style="margin:5px" onclick="initial_sync_products()">Manuell synkning produkter</button>';
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
                add_settings_field( 'woocommerce-fortnox-api-key', 'API Nyckel', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'api-key', 'desc' => 'Här anges API-nyckeln du har erhållit från oss via mail.') );
                //add_settings_field( 'woocommerce-fortnox-api-license-key', 'API Licensnyckel', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'api-key', 'desc' => 'Här anges API-licensnyckeln du har erhållit från Fortnox.') );
                add_settings_field( 'woocommerce-fortnox-authorization-code', 'Authorization Code', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'authorization_code', 'desc' => 'Här anges din Authorization Code från Fortnox') );
                add_settings_field( 'woocommerce-fortnox-client-secret', 'Client Secret', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'client_secret', 'desc' => 'Här anges ditt Client Secret från Fortnox') );
                add_settings_field( 'woocommerce-fortnox-access-token', 'Access Token', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'access_token', 'desc' => 'Access Token') );
                add_settings_field( 'woocommerce-fortnox-activate-orders', 'Aktivera synkning ordrar', array( &$this, 'field_option_checkbox'), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-orders', 'desc' => ''));
                add_settings_field( 'woocommerce-fortnox-activate-prices', 'Aktivera synkning priser', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-prices', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-activate-invoices', 'Skapa faktura för varje order', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-invoices', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-activate-bookkeeping', 'Aktivera automatisk bokföring för faktura', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-bookkeeping', 'desc' => '') );
            }

            /**
             * WooCommerce Fortnox Accounting Settings
             *
             * @access public
             * @param void
             * @return void
             */
            function register_woocommerce_fortnox_accounting_settings() {

                $this->plugin_settings_tabs[$this->accounting_settings_key] = 'Bokföringsinställningar';

                register_setting( $this->accounting_settings_key, $this->accounting_settings_key );
                add_settings_section( 'section_accounting', 'Bokföringsinställningar', array( &$this, 'section_accounting_desc' ), $this->accounting_settings_key );
                add_settings_field( 'woocommerce-fortnox-account-25-vat', 'Konto försäljning 25% Moms', array( &$this, 'field_option_text'), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'account-25-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-account-12-vat', 'Konto försäljning 12% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'account-12-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-account-6-vat', 'Konto försäljning 6% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'account-6-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-taxclass-account-25-vat', 'Skatteklass för 25% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'taxclass-account-25-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-taxclass-account-12-vat', 'Skatteklass för 12% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'taxclass-account-12-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-taxclass-account-6-vat', 'Skatteklass för 6% Moms', array( &$this, 'field_option_text' ), $this->accounting_settings_key, 'section_accounting', array ( 'tab_key' => $this->accounting_settings_key, 'key' => 'taxclass-account-6-vat', 'desc' => '') );

            }

            /**
             * WooCommerce Fortnox Accounting Settings
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
                $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->general_settings_key;
                if($tab == $this->support_key){ ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        </br>
                        <a href="wp-plugs.com/faq"><span>FAQ</span></a></br>
                        <a href="wp-plugs.com/support">Support</a>
                    </div>
                <?php
                }
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
             * Fetches contacts from Fortnox and writes them local db
             *
             * @access public
             * @return void
             */
            public function fetch_fortnox_contacts() {
                include_once("class-fortnox3-order-xml.php");
                include_once("class-fortnox3-database-interface.php");
                include_once("class-fortnox3-api.php");

                $apiInterface = new WCFAPI();
                $customers = $apiInterface->get_customers();
                $databaseInterface = new WCFDatabaseInterface();

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
             * @return void
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

                    //Init API
                    $apiInterface = new WCFAPI();

                    //create Order XML
                    $orderDoc = new WCFOrderXMLDocument();
                    $orderXml = $orderDoc->create($order, $customerNumber);

                    //send Order XML
                    $orderResponse = $apiInterface->create_order_request($orderXml);


                    if(array_key_exists('Error', $orderResponse)){
                        //Init DB
                        $database = new WCFDatabaseInterface();
                        //Save
                        $database->create_unsynced_order($orderId);
                        return 0;
                    }
                    if(!isset($options['activate-invoices'])){
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
                return 0;
            }

            /**
             * Sends order to Fortnox API
             *
             * @access public
             * @return void
             */
            public function sync_orders_to_fortnox() {
                include_once("class-fortnox3-order-xml.php");
                include_once("class-fortnox3-database-interface.php");
                include_once("class-fortnox3-api.php");

                $options = get_option('woocommerce_fortnox_general_settings');

                $apiInterface = new WCFAPI();
                $databaseInterface = new WCFDatabaseInterface();
                $unsyncedOrders = $databaseInterface->read_unsynced_orders();

                foreach($unsyncedOrders as $order){

                    $orderId = $order->order_id;
                    $order = new WC_Order($orderId);

                    $customerNumber = $this->get_or_create_customer($order);

                    //create Order XML
                    $orderDoc = new WCFOrderXMLDocument();
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

            public function initial_products_sync() {
                $args = array(
                    'post_type' => 'product',
                    'orderby' => 'id',
                );
                $the_query = new WP_Query( $args );

//                foreach($the_query->get_posts() as $product){
//                    $this->send_product_to_fortnox($product->ID);
//                }
                wp_reset_postdata();
                return true;
            }

            /**
             * Sends product to Fortnox API
             *
             * @access public
             * @param $productId
             * @internal param int $orderId
             * @return void
             */
            public function send_product_to_fortnox($productId) {
                global $wcdn;
                $options = get_option('woocommerce_fortnox_general_settings');
                if(!isset($options['activate-prices'])){
                    return;
                }
                if($options['activate-prices'] == 'on' && $this->is_api_key_valid()){
                    $post = get_post($productId);
                    if($post->post_type == 'product' && $post->post_status == 'publish'){
                        include_once("class-fortnox3-product-xml.php");
                        include_once("class-fortnox3-database-interface.php");
                        include_once("class-fortnox3-api.php");

                        //fetch Product
                        $pf = new WC_Product_Factory();
                        $product = $pf->get_product($productId);

                        //fetch meta
                        //Init API
                        $apiInterface = new WCFAPI();
                        //create Product XML
                        $productDoc = new WCFProductXMLDocument();
                        $sku = $product->get_sku();
                        $isSynced = get_post_meta( $productId, '_is_synced_to_fortnox' );

                        if (!empty($isSynced)) {
                            $productXml = $productDoc->update($product);
                            $apiInterface->update_product_request($productXml, $sku);
                            $productPriceXml = $productDoc->update_price($product);
                            $apiInterface->update_product_price_request($productPriceXml, $sku);

                        }
                        else{
                            $productXml = $productDoc->create($product);
                            $productResponseCode = $apiInterface->create_product_request($productXml);
                            $fortnox_id = $productResponseCode['ArticleNumber'];
                            //set sku;
                            update_post_meta($productId, '_sku', $fortnox_id);
                            update_post_meta($productId, '_is_synced_to_fortnox', 1);

                            $productPriceXml = $productDoc->create_price($product);
                            $postResponse = $apiInterface->create_product_price_request($productPriceXml, $fortnox_id);
                            if(array_key_exists('Code', $postResponse)){
                                if($postResponse['Code'] == '2000762'){
                                    $productPriceXml = $productDoc->update_price($product);
                                    $apiInterface->update_product_price_request($productPriceXml, $fortnox_id);
                                }
                            }
                        }

                        //set price


                    }
                }
            }

            /***********************************************************************************************************
             * WP-PLUGS API FUNCTIONS
             ***********************************************************************************************************/

            /**
             * Checks if API-key is valid
             *
             * @access public
             * @return void
             */
            public function is_api_key_valid() {
                include_once("class-fortnox3-api.php");
                $apiInterface = new WCFAPI();
                return $apiInterface->create_api_validation_request();
            }

            /**
             * Fetches customer from DB or creates it at Fortnox
             *
             * @access public
             * @param $order
             * @return void
             */
            private function get_or_create_customer($order){
                $databaseInterface = new WCFDatabaseInterface();
                $customer = $databaseInterface->get_customer_by_email($order->billing_email);

                if(empty($customer)){

                    $customerId = $databaseInterface->create_customer($order->billing_email);

                    //Init API
                    $apiInterface = new WCFAPI();
                    //create Order XML
                    $contactDoc = new WCFContactXMLDocument();
                    $contactXml = $contactDoc->create($order);

                    //send Contact XML
                    $contactResponseCode = $apiInterface->create_contact_request($contactXml);
                    $customerNumber = $contactResponseCode['CustomerNumber'];
                    $databaseInterface->update_customer($customerId, $customerNumber);

                }
                else{
                    $customerNumber = $customer[0]->customer_number;
                }
                return $customerNumber;
            }
        }
        $GLOBALS['wc_consuasor'] = new WC_Fortnox();
    }
}