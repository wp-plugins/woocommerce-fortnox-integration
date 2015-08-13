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
        $productNode['StockGoods'] = true;
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
        $productNode['StockGoods'] = true;
        $productNode['QuantityInStock'] = $product->managing_stock() ? $product->get_stock_quantity() : 0;
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

        if(!isset($options['default-pricelist'])){
            $price['PriceList'] = 'A';
        }
        else{
            if($options['default-pricelist'] != ''){
                $price['PriceList'] = $options['default-pricelist'];
            }
            else{
                $price['PriceList'] = 'A';
            }
        }

        if($options['product-price-including-vat'] == 'on'){
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

        if(!isset($options['default-pricelist'])){
            $price['PriceList'] = 'A';
        }
        else{
            if($options['default-pricelist'] != ''){
                $price['PriceList'] = $options['default-pricelist'];
            }
            else{
                $price['PriceList'] = 'A';
            }
        }

        $price['Price'] = $product->get_price_excluding_tax();

        if($options['product-price-including-vat'] == 'on'){
            $price['Price'] = $product->get_price_including_tax();
            logthis('YES');
        }
        else{
            $price['Price'] = $product->get_price_excluding_tax();
            logthis('NO');
        }
        logthis(print_r($price, true));
        return $this->generate($root, $price);
    }
}