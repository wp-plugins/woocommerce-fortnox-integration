<?php
/**
 * Plugin Name: WooCommerce Fortnox Integration
 * Plugin URI: http://plugins.svn.wordpress.org/woocommerce-fortnox-integration/
 * Description: A Fortnox 3 API Interface. Synchronizes products, orders and more to fortnox.
 * Also fetches inventory from fortnox and updates WooCommerce
 * Version: 1.39
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

            if(is_array($msg || is_object($msg))){
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


        // in javascript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
        function fortnox_enqueue(){
            wp_enqueue_script('jquery');
            wp_register_script( 'fortnox-script', plugins_url( '/woocommerce-fortnox-integration/js/fortnox.js' ) );
            wp_enqueue_script( 'fortnox-script' );
        }

        add_action( 'admin_enqueue_scripts', 'fortnox_enqueue' );
        add_action( 'wp_ajax_initial_sync_products', 'initial_sync_products_callback' );

        function initial_sync_products_callback() {
            global $wpdb; // this is how you get access to the database
            include_once("class-woo-fortnox-controller.php");
            $controller = new WC_Fortnox_Controller();
            ob_start();
            $message = $controller->initial_products_sync();
            ob_end_clean();
            echo $message;
            die(); // this is required to return a proper result
        }

        add_action( 'wp_ajax_fetch_contacts', 'fetch_contacts_callback' );

        function fetch_contacts_callback() {
            global $wpdb; // this is how you get access to the database
            include_once("class-woo-fortnox-controller.php");
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
            $sent = wp_mail( 'kircher.tomas@gmail.com', 'Fortnox Support', $message);
            echo $sent;
            //die(); // this is required to return a proper result
        }

        add_action( 'wp_ajax_sync_orders', 'sync_orders_callback' );

        function sync_orders_callback() {
            global $wpdb; // this is how you get access to the database
            include_once("class-woo-fortnox-controller.php");
            $controller = new WC_Fortnox_Controller();
            $message = $controller->sync_orders_to_fortnox();
            echo $message;
            die(); // this is required to return a proper result
        }

        add_action( 'wp_ajax_update_fortnox_inventory', 'update_fortnox_inventory_callback' );

        function update_fortnox_inventory_callback() {
            global $wpdb; // this is how you get access to the database
            include_once("class-woo-fortnox-controller.php");
            ob_start();
            $controller = new WC_Fortnox_Controller();
            $message = $controller->run_manual_inventory_cron_job();
            ob_end_clean();
            echo $message;
            die(); // this is required to return a proper result
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
            private $general_settings;
            private $accounting_settings;
            private $plugin_options_key = 'woocommerce_fortnox_options';
            private $plugin_settings_tabs = array();

            public $WCF_API_KEY_ERROR = 1;
            public $WCF_FORTNOX_KEY_ERROR = 2;
            public $WCF_ORDER_ERROR = 3;
            public $WCF_INVOICE_ERROR = 4;
            public $WCF_BOOKKEEPING_ERROR = 5;
            public $WCF_CONTACT_ERROR = 6;
            public $WCF_PRODUCT_ERROR = 7;
            public $WCF_ORDER_SUCCESS = 8;
            public $WCF_PRODUCT_SUCCESS = 9;

            public function __construct() {

                //call register settings function
                add_action( 'init', array( &$this, 'load_settings' ) );
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_start_action' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_general_settings' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_order_settings' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_manual_action' ));
                add_action( 'admin_init', array( &$this, 'register_woocommerce_fortnox_support' ));
                add_action( 'admin_menu', array( &$this, 'add_admin_menus' ) );
                add_action( 'admin_notices', array( $this, 'admin_notices' ) );

                // Register WooCommerce Hooks
                if(!AUTOMATED_TESTING)
                    add_action( 'woocommerce_order_status_completed', array(&$this, 'send_contact_to_fortnox'), 10, 1 );

                if(!AUTOMATED_TESTING)
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
                add_options_page( 'WooCommerce Fortnox Integration', 'WooCommerce Fortnox Integration', 'manage_options', $this->plugin_options_key, array( &$this, 'woocommerce_fortnox_options_page' ) );
            }

            public function admin_notices() {
                if ( ! isset( $_GET['fortnox_message'] ) ){
                    return;
                }
                $class = "error";
                $message = "";
                switch($_GET['fortnox_message']){
                    case $this->WCF_API_KEY_ERROR:
                        $message = "WooCommerce Fortnox Integration: Er API-Nyckel är ej giltig.";
                        break;
                    case $this->WCF_FORTNOX_KEY_ERROR:
                        $message = "WooCommerce Fortnox Integration: Inloggning till Fortnox misslyckades.";
                        break;
                    case $this->WCF_ORDER_ERROR:
                        $message = "WooCommerce Fortnox Integration: Synkronisering av order misslyckades.";
                        break;
                    case $this->WCF_INVOICE_ERROR:
                        $message = "WooCommerce Fortnox Integration: Lyckades EJ att skapa faktura av ordern.";
                        break;
                    case $this->WCF_BOOKKEEPING_ERROR:
                        $message = "WooCommerce Fortnox Integration: Lyckades EJ att bokföra orderns faktura.";
                        break;
                    case $this->WCF_CONTACT_ERROR:
                        $message = "WooCommerce Fortnox Integration: Lyckades EJ att skapa kund.";
                        break;
                    case $this->WCF_ORDER_SUCCESS:
                        $class = "updated";
                        $message = "WooCommerce Fortnox Integration: Ordern har synkroniserats till Fortnox.";
                        break;
                    case $this->WCF_PRODUCT_SUCCESS:
                        $class = "updated";
                        $message = "WooCommerce Fortnox Integration: Produkten har synkroniserats till Fortnox.";
                        break;
                    case $this->WCF_PRODUCT_ERROR:
                        $message = "WooCommerce Fortnox Integration: Lyckades EJ att synkronisera produkt.";
                        break;
                }

                ?>
                <div class="<?php echo $class;?>">
                    <p><?php esc_html_e( $message, 'text-domain' ); ?></p>
                </div>
            <?php
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
                $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->start_action_key;
                $options = get_option('woocommerce_fortnox_general_settings');
                echo '<div class="wrap"><h2>WooCommerce Fortnox Integration</h2><div id="icon-edit" class="icon32"></div></div>';
                if(!isset($options['api-key']) || $options['api-key'] == ''){
                    echo "<button type=\"button button-primary\" class=\"button button-primary\" title=\"\" style=\"margin:5px\" onclick=\"window.open('http://whmcs.onlineforce.net/cart.php?a=add&pid=49&billingcycle=mounthly','_blank');\">Hämta API-Nyckel</button>";
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
                add_settings_field( 'woocommerce-fortnox-api-key', 'API Nyckel', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'api-key', 'desc' => 'Här anges API-nyckeln du har erhållit från oss via mail. <a target="_blank" href="http://vimeo.com/107836260#t=0m50s">Videoinstruktion</a>') );
                add_settings_field( 'woocommerce-fortnox-authorization-code', 'Fortnox API-kod', array( &$this, 'field_option_text' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'authorization_code', 'desc' => 'Här anges din API kod från Fortnox. <a target="_blank" href="http://vimeo.com/107836260#t=1m20s">Videoinstruktion</a>') );
                add_settings_field( 'woocommerce-fortnox-activate-orders', 'Aktivera synkning ordrar', array( &$this, 'field_option_checkbox'), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-orders', 'desc' => ''));
                add_settings_field( 'woocommerce-fortnox-activate-prices', 'Aktivera synkning produkter', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-prices', 'desc' => 'Om du ska synkronisera variabla produkter är det VIKTIGT att ni läser avsnittet "<b>Hur fungerar synkronisering av variabla produkter?</b>" innan! <a target="_blank" href="http://wp-plugs.com/woocommerce-fortnox/#faq">Läs här</a>') );
                add_settings_field( 'woocommerce-fortnox-activate-invoices', 'Skapa faktura för varje order', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-invoices', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-activate-bookkeeping', 'Aktivera automatisk bokföring för faktura', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-bookkeeping', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-activate-fortnox-products-sync', 'Aktivera lagersaldosynkning från fortnox', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-fortnox-products-sync', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-activate-vat', 'Synkronisera inklusive moms', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'activate-vat', 'desc' => '') );
                add_settings_field( 'woocommerce-fortnox-sync-master', 'Synkronisera Master-produkten', array( &$this, 'field_option_checkbox' ), $this->general_settings_key, 'section_general', array ( 'tab_key' => $this->general_settings_key, 'key' => 'sync-master', 'desc' => '') );
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
                else if($tab == $this->manual_action_key){ ?>
                    <div class="wrap">
                        <?php $this->plugin_options_tabs(); ?>
                        <ul class="manuella">
                            <li class="full">
                                <button type="button" class="button" title="Manuell Synkning" style="margin:5px" onclick="fetch_contacts()">Manuell synkning kontakter</button>
                                <p>Hämtar alla kunder från er Fortnox. Detta görs för att undvika dubbletter.</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Manuell Synkning Orders" style="margin:5px" onclick="sync_orders()">Manuell synkning ordrar</button>
                                <p>Synkroniserar alla ordrar som misslyckats att synkronisera.</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Manuell Synkning Produkter" style="margin:5px" onclick="initial_sync_products()">Manuell synkning produkter</button>
                                <p>Skicka alla produkter till er Fortnox. Om ni har många produkter kan det ta ett tag.</p>
                            </li>
                            <li class="full">
                                <button type="button" class="button" title="Uppdatera lagersaldo från Fortnox" style="margin:5px" onclick="update_fortnox_inventory()">Uppdatera lagersaldo från Fortnox</button>
                                <p>Uppdatera lagersaldo från Fortnox. Om ni har många produkter kan det ta ett tag.</p>
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
            public function send_contact_to_fortnox($orderId) {
                include_once("class-woo-fortnox-controller.php");
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