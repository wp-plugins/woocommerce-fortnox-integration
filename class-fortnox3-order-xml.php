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

        $orderOptions = get_option('woocommerce_fortnox_order_settings');
        $freight_options = get_option('woocommerce_fortnox_freight_settings');

        $root = 'Order';
        $order['DocumentNumber'] = $arr->id;
        $order['AdministrationFee'] = $orderOptions['admin-fee'];
        $order['OrderDate'] =  substr($arr->order_date, 0, 10);
        $order['DeliveryDate'] = substr($arr->order_date, 0, 10);
        $order['Currency'] = $arr->get_order_currency();
        $order['CurrencyRate'] = '1';
        $order['CurrencyUnit'] = '1';
        $order['YourOrderNumber'] = $arr->id;
        $order['CustomerNumber'] = $customerNumber;
        $order['Address1'] = $arr->billing_address_1;
        $order['City'] = $arr->billing_city;
        $order['Country'] = $this->countries[$arr->billing_country];
        $order['Phone1'] = $arr->billing_phone;
        $order['DeliveryAddress1'] = $arr->shipping_address_1;
        $order['DeliveryCity'] = $arr->shipping_city;
        $order['DeliveryCountry'] = $this->countries[$arr->shipping_country];
        $order['DeliveryZipCode'] =  $arr->shipping_postcode;

        $shipping_method = reset($arr->get_shipping_methods());
        if(!empty($shipping_method)){
            if(!empty($shipping_method['method_id'])){
                if(isset($freight_options[$shipping_method['method_id']])){
                    $order['WayOfDelivery'] = $freight_options[$shipping_method['method_id']];
                }
            }
        }

        if(isset($arr->billing_company) && $arr->billing_company != ''){
            $order['CustomerName'] = $arr->billing_company;
            $order['YourReference'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        }
        else{
            $order['CustomerName'] = $arr->billing_first_name . " " . $arr->billing_last_name;
            $order['DeliveryName'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        }

        $order['Freight'] = $arr->get_total_shipping();

        $order['VATIncluded'] = 'false';

        if($orderOptions['add-payment-type'] == 'on'){
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

            //handles missing product
            $invoicerow = array();

            if(!($product==NULL)){//!is_null($product)
                $invoicerow['ArticleNumber'] = $product->get_sku();
            }

            $invoicerow['Description'] = $item['name'];
            $invoicerow['Unit'] = 'st';
            $invoicerow['DeliveredQuantity'] = $item['qty'];
            $invoicerow['OrderedQuantity'] = $item['qty'];
            $invoicerow['Price'] = $this->get_product_price($item)/$item['qty'];
            $invoicerow['VAT'] = $this->get_tax_class_by_tax_name($item['tax_class']);

            $index += 1;
            $invoicerows[$key] = $invoicerow;
        }

        /****HANDLE FEES*****/
        foreach($arr->get_fees() as $item){
            $key = "OrderRow" . $index;

            $invoicerow['Description'] = $item['name'];
            $invoicerow['Unit'] = 'st';
            $invoicerow['DeliveredQuantity'] = 1;
            $invoicerow['OrderedQuantity'] = 1;
            $invoicerow['Price'] = $item['line_total'];
            $invoicerow['VAT'] = 25;

            $index += 1;
            $invoicerows[$key] = $invoicerow;
        }

        if($arr->get_total_discount() > 0){

            $coupon = $arr->get_used_coupons();
            $coupon = new WC_Coupon($coupon[0]);
            if(!$coupon->apply_before_tax()){
                $key = "OrderRow" . $index;
                $invoicerow = array();

                $invoicerow['Description'] = "Rabatt";
                $invoicerow['Unit'] = 'st';
                $invoicerow['DeliveredQuantity'] = 1;
                $invoicerow['OrderedQuantity'] = 1;
                $invoicerow['Price'] = -1 * $arr->get_total_discount();
                $invoicerow['VAT'] = 0;
                $invoicerows[$key] = $invoicerow;
            }
        }

        $order['OrderRows'] = $invoicerows;

        logthis(print_r($order, true));

        return $this->generate($root, $order);
    }

    /**
     * Sums up price and tax from order line
     *
     * @access private
     * @param mixed $product
     * @return float
     */
    private function get_product_price($product){
        return floatval($product['line_total']);
    }

    /**
     * Returns a products taxrate
     *
     * @access public
     * @param $tax_name
     * @return int
     */
    public function get_tax_class_by_tax_name( $tax_name ) {
        global $wpdb;
        if($tax_name == ''){
            return 25;
        }
        $tax_rate = $wpdb->get_var( $wpdb->prepare( "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_class = %s", $tax_name ) );
        
        return intval($tax_rate);
    }
}
