<?php
include_once("class-fortnox3-xml.php");
class WCFOrderXMLDocument extends WCFXMLDocument{

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

        $root = 'Order';
        //$order['id'] = $arr->id;
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
        $order['CustomerName'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        $order['DeliveryName'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        $order['VATIncluded'] = 'true';

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
            $product = $pf->get_product($item['product_id']);

            $invoicerow = array();
            $invoicerow['ArticleNumber'] = $product->get_sku();
            $invoicerow['Description'] = $item['name'];
            $invoicerow['Unit'] = 'st';
            $invoicerow['DeliveredQuantity'] = $item['qty'];
            $invoicerow['OrderedQuantity'] = $item['qty'];
            $invoicerow['Price'] = $product->get_regular_price();
            $invoicerow['VAT'] = $item['tax_class'];
            //discount
            if($product->is_on_sale()){
                $invoicerow['Discount'] = $item['qty']*($product->get_regular_price() - $product->get_sale_price());
                $invoicerow['DiscountType'] = 'AMOUNT';
            }

            $index += 1;
            $invoicerows[$key] = $invoicerow;
        }
        $order['OrderRows'] = $invoicerows;
        logthis(print_r($order, true));
        return $this->generate($root, $order);
    }
}