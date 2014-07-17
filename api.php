<?php
if(!isset($_GET['api_key'])){
    die('Please enter api_key');
}

$api_key = $_GET['api_key'];

$link = mysql_connect('database.consuasor.se', 'plugapi', 'v7vpXpi9VDPn4XkRe8UmfVsY7ehZ3g6X');
mysql_select_db('plugapi', $link) or die('Could not select database.');
if (!$link) {
    die('Could not connect: ' . mysql_error());
}

$query = sprintf("SELECT * FROM plugin_user
    WHERE api_key='%s' AND expiration_date >'%s'",
    mysql_real_escape_string($api_key),
    date("Y-m-d H:i:s"));
$result = mysql_query($query);

if(mysql_num_rows($result) == 1){
    echo "OK";
}
else{
    echo "NOT OK";
}
mysql_close($link);