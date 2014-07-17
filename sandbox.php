<?php
require_once( '../../../wp-load.php' );
/*$headers = array(
    'Accept: application/xml',
    'Authorization-Code: 9cc1ec4c-9970-4ed4-9276-a8db1de698d8',
    'Client-Secret: AQV9TbDU1k',
    'Content-Type: application/xml',
);
$ch = curl_init();
curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
curl_setopt ($ch, CURLOPT_URL, 'https://api.fortnox.se/3');
curl_setopt ($ch, CURLOPT_TIMEOUT,60);
curl_setopt ($ch, CURLOPT_VERBOSE,0);
curl_setopt ($ch, CURLOPT_POST, 0);
curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

$data = curl_exec($ch);
print $data;
$array_data = json_decode(json_encode(simplexml_load_string($data)), true);
$access_token = $array_data['AccessToken'];
curl_close($ch);
c13f0c47-546e-4af7-a21b-2da05d9800fd*/
include_once("woocommerce-fortnox3.php");
$options = get_option('fortnox_access_token');
if($options){
    print $options;
}
$f = WC_Fortnox();
$f->send_order_to_fortnox(2477);
?>
