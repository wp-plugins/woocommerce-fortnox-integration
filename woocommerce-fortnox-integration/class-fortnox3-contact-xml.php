<?php
include_once("class-fortnox3-xml.php");

class WCFContactXMLDocument extends WCFXMLDocument{

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
        $contact = array();
        $contact['Name'] = $arr->billing_first_name . " " . $arr->billing_last_name;
        $contact['Address1'] = $arr->billing_address_1;
        $contact['ZipCode'] = $arr->billing_postcode;
        $contact['City'] = $arr->billing_city;
        $contact['Phone1'] = $arr->billing_phone;
        $contact['Email'] = $arr->billing_email;
        $contact['Type'] = 'PRIVATE';
        $contact['DeliveryAddress1'] = $arr->shipping_address_1;
        $contact['DeliveryZipCode'] = $arr->shipping_postcode;
        $contact['DeliveryCity'] = $arr->shipping_city;
        $contact['PriceList'] = 'A';
        $root = 'Customer';
        return $this->generate($root, $contact);
    }
}