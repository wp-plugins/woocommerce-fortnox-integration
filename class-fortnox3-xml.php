<?php
class WCF_XML_Document {

    /** @public String XML */
    var $xml;

    /** @public String version */
    var $ver;

    /** @public String Charset */
    var $charset;

    /** @public String Charset */
    var $exceptions;

    /** @public String Charset */
    var $countries;

    /**
     *
     */
    function __construct() {
        $this->ver = '1.0';
        $this->charset = 'UTF-8';
        $this->exceptions = array(
            'Address1',
            'Address2',
            'DeliveryAddress1',
            'DeliveryAddress2',
            'Phone1',
            'Phone2',
        );

        $this->countries = array(
            'SE' => 'Sverige'
        );
    }

    /**
     *
     * generates XML Document
     *
     * @access public
     * @param $root
     * @param array $data
     * @internal param mixed $arr
     * @return String
     */
    function generate($root, $data=array()) {
        $this->xml = new XmlWriter();
        $this->xml->openMemory();
        $this->xml->startDocument($this->ver,$this->charset);
        $this->xml->startElement($root);
        $this->write($this->xml, $data);
        $this->xml->endElement();
        $this->xml->endDocument();
        $xml = $this->xml->outputMemory(true);
        $this->xml->flush();
        return $xml;
    }

    /**
     * writes Element
     *
     * @access public
     * @param mixed $xml , mixed $data
     * @param $data
     * @return void
     */
    private function write(XMLWriter $xml, $data){
        foreach($data as $key => $value){
            if (!in_array($key, $this->exceptions)) {
                $key = preg_replace("/[^A-Za-z?!]/",'',$key);
            }
            if(is_array($value)){

                $xml->startElement($key);
                $this->write($xml,$value);
                $xml->endElement();
                continue;
            }
            else{
                if(isset($value)){
                    $xml->writeElement($key,$value);
                }
            }

        }
    }
}