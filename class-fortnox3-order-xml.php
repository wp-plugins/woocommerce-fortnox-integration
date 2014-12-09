<?php
include_once("class-fortnox3-xml.php");

class WCF_Order_XML_Document extends WCF_XML_Document{

    /**
     *
     */
    function __construct() {
        parent::__construct();
    }

    /**
     * Creates a n XML representation of an Order
     *
     * @access public
     * @param mixed $arr
     * @param $customerNumber
     * @return mixed
     */
    public function create($arr, $customerNumber){

        $order_options = $options = get_option('woocommerce_fortnox_order_settings');
        $options = get_option('woocommerce_fortnox_general_settings');

        $root = 'Order';
        $order['DocumentNumber'] = $arr->id;
        $order['AdministrationFee'] = $order_options['admin-fee'];
        $order['OrderDate'] =  substr($arr->order_date, 0, 10);
        $order['DeliveryDate'] = substr($arr->order_date, 0, 10);
        $order['Currency'] = $arr->get_order_currency();
        $order['CurrencyRate'] = '1';
        $order['CurrencyUnit'] = '1';
        $order['Freight'] = $arr->get_total_shipping();
        $order['CustomerNumber'] = $customerNumber;
        $order['Address1'] = $arr->billing_address_1;
        $order['City'] = $arr->billing_city;
        $order['Country'] = $this->countries[$arr->billing_country];
        $order['Phone1'] = $arr->billing_phone;
        $order['DeliveryAddress1'] = $arr->shipping_address_1;
        $order['DeliveryCity'] = $arr->shipping_city;
        $order['DeliveryCountry'] = $this->countries[$arr->shipping_country];
        $order['DeliveryZipCode'] =  $arr->shipping_postcode;

        if(isset($arr->billing_company)){
            $order['CustomerName'] = $arr->billing_first_name . " " . $arr->billing_last_name . " " . $arr->billing_company;
        }
        else{
            $order['DeliveryName'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        }

        $order['VATIncluded'] = 'false';
        if($options['activate-vat'] == 'on'){
            $order['VATIncluded'] = 'true';
        }
        else{
            $order['VATIncluded'] = 'false';
        }


        if($order_options['add-payment-type'] == 'on'){
            $payment_method = get_post_meta( $arr->id, '_payment_method_title');
            $order['Remarks'] = $payment_method[0];
        }
        $email = array();
        $email['EmailAddressTo'] = $arr->billing_email;
        $order['EmailInformation'] =  $email;

        $invoicerows = array();

        //loop all items
        $index = 0;
        foreach($arr->get_items() as $item){
            $key = "OrderRow" . $index;

            //fetch product
            $pf = new WC_Product_Factory();

            //if variable product there might be a different SKU
            if(empty($item['variation_id'])){
                $productId = $item['product_id'];
            }
            else{
                $productId = $item['variation_id'];
            }

            $product = $pf->get_product($productId);
            logthis(print_r($product, true));
            $invoicerow = array();
            $invoicerow['ArticleNumber'] = $product->get_sku();
            $invoicerow['Description'] = $item['name'];
            $invoicerow['Unit'] = 'st';
            $invoicerow['DeliveredQuantity'] = $item['qty'];
            $invoicerow['OrderedQuantity'] = $item['qty'];

            $invoicerow['Price'] = $this->get_product_price($item);

            $invoicerow['VAT'] = $this->get_tax_class_by_tax_name($item['tax_class']);
            $index += 1;
            $invoicerows[$key] = $invoicerow;
        }
        $order['OrderRows'] = $invoicerows;

        logthis(print_r($order, true));

        return $this->generate($root, $order);
    }

    private function get_product_price($product){
        return floatval($product['line_total']);
    }

    public function get_tax_class_by_tax_name( $tax_name ) {
        global $wpdb;
        if($tax_name == ''){
            return 25;
        }
        $tax_rate = $wpdb->get_var( $wpdb->prepare( "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_name = %d", $tax_name ) );
        return intval($tax_rate);
    }
}