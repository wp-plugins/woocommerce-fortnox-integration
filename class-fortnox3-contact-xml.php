<?php
include_once("class-fortnox3-xml.php");

class WCF_Contact_XML_Document extends WCF_XML_Document{

    /**
     *
     */
    function __construct() {
        parent::__construct();
    }

    /**
     * Creates an XML representation of an order
     *
     * @access public
     * @param mixed $arr
     * @return mixed
     */
    public function create($arr){

        $options = get_option('woocommerce_fortnox_general_settings');
        $contact = array();

        if(!empty($arr->billing_company)){
            $contact['Type'] = 'COMPANY';
            $contact['Name'] = $arr->billing_company;
            $contact['OurReference'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        }
        else{
            $contact['Name'] = $arr->billing_first_name . " " . $arr->billing_last_name;
            $contact['Type'] = 'PRIVATE';
        }

        $contact['Address1'] = $arr->billing_address_1;
        $contact['ZipCode'] = $arr->billing_postcode;
        $contact['City'] = $arr->billing_city;
        $contact['Phone1'] = $arr->billing_phone;
        $contact['Email'] = $arr->billing_email;
        $contact['DeliveryAddress1'] = $arr->shipping_address_1;
        $contact['DeliveryZipCode'] = $arr->shipping_postcode;
        $contact['DeliveryCity'] = $arr->shipping_city;

        if(!isset($options['default-pricelist'])){
            $contact['PriceList'] = 'A';
        }
        else{
            if($options['default-pricelist'] != ''){
                $contact['PriceList'] = $options['default-pricelist'];
            }
            else{
                $contact['PriceList'] = 'A';
            }
        }

        $root = 'Customer';
        return $this->generate($root, $contact);
    }
}