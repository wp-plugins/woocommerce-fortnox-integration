<?php
include_once("class-fortnox3-xml.php");
class WCF_Product_XML_Document extends WCF_XML_Document{

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
        $options = get_option('woocommerce_fortnox_general_settings');
        $price = array();

        if(!isset($meta['pricelist_id'])){
            $price['PriceList'] = 'A';
        }
        else{
            $price['PriceList'] = $meta['pricelist_id'];
        }
        if($options['activate-vat'] == 'on'){
            $price['Price'] = $product->get_price_including_tax();
            logthis('YES');
        }
        else{
            $price['Price'] = $product->get_price_excluding_tax();
            logthis('NO');
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

        $options = get_option('woocommerce_fortnox_general_settings');
        if($options['activate-vat'] == 'on'){
            $price['Price'] = $product->get_price_including_tax();
            logthis('YES');
        }
        else{
            $price['Price'] = $product->get_price_excluding_tax();
            logthis('NO');
        }

        return $this->generate($root, $price);
    }
}