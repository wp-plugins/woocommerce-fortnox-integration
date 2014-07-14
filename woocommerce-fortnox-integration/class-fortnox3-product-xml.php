<?php
include_once("class-fortnox3-xml.php");
class WCFProductXMLDocument extends WCFXMLDocument{

    /**
     *
     */
    function __construct() {
        parent::__construct();
    }

    /**
     * Creates a XML representation of a Product
     *
     * @access public
     * @param mixed $product
     * @return mixed
     */
    public function create($product){

        $root = 'Article';
        $productNode = array();
        $productNode['Description'] = $product->get_title();
        $productNode['QuantityInStock'] = $product->get_stock_quantity();
        $productNode['Unit'] = 'st';
        $productNode['ArticleNumber'] = $product->get_sku();

        return $this->generate($root, $productNode);
    }

    /**
     * Updates a XML representation of a Product
     *
     * @access public
     * @param mixed $product
     * @return mixed
     */
    public function update($product){

        $root = 'Article';
        $productNode = array();
        $productNode['Description'] = $product->get_title();
        $productNode['ArticleNumber'] = $product->get_sku();
        $productNode['QuantityInStock'] = $product->get_stock_quantity();
        $productNode['Unit'] = 'st';
        return $this->generate($root, $productNode);
    }

    /**
     * Creates a XML representation of a Product
     *
     * @access public
     * @param mixed $product
     * @return mixed
     */
    public function create_price($product){

        $root = 'Price';
        $price = array();

        if(!isset($meta['pricelist_id'])){
            $price['PriceList'] = 'A';
        }
        else{
            $price['PriceList'] = $meta['pricelist_id'];
        }

        $options = get_option('woocommerce_fortnox_accounting_settings');
        if($product->get_tax_class()!=''){
            switch($product->get_tax_class()){
                case $options['taxclass-account-25-vat']:
                    $price['Price'] = (double)$product->get_price() * 1/1.25;
                    break;
                case $options['taxclass-account-12-vat']:
                    $price['Price'] = (double)$product->get_price() * 1/1.12;
                    break;
                case $options['taxclass-account-6-vat']:
                    $price['Price'] = (double)$product->get_price() * 1/1.06;
                    break;
                case 'Standard':
                    $price['Price'] = (double)$product->get_price() * 1/1.25;
                    break;
                default:
                    $price['Price'] = (double)$product->get_price() * 1/1.25;
            }
        }
        else{
            $price['Price'] = (double)$product->get_price() * 1/1.25;
        }


        $price['ArticleNumber'] = $product->get_sku();
        $price['FromQuantity'] = 1;

        return $this->generate($root, $price);
    }

    /**
     * Creates a XML representation of a Product
     *
     * @access public
     * @param mixed $product
     * @return mixed
     */
    public function update_price($product){

        $root = 'Price';
        $price = array();

        $options = get_option('woocommerce_fortnox_accounting_settings');
        if($product->get_tax_class()!=''){
            switch($product->get_tax_class()){
                case $options['taxclass-account-25-vat']:
                    $price['Price'] = (double)$product->get_price() * 1/1.25;
                    break;
                case $options['taxclass-account-12-vat']:
                    $price['Price'] = (double)$product->get_price() * 1/1.12;
                    break;
                case $options['taxclass-account-6-vat']:
                    $price['Price'] = (double)$product->get_price() * 1/1.06;
                    break;
                case 'Standard':
                    $price['Price'] = (double)$product->get_price() * 1/1.25;
                    break;
                default:
                    $price['Price'] = (double)$product->get_price() * 1/1.25;
            }
        }
        else{
            $price['Price'] = (double)$product->get_price() * 1/1.25;
        }

        return $this->generate($root, $price);
    }
}