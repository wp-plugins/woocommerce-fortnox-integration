<?php
/**
 * Created by PhpStorm.
 * User: tomas
 * Date: 3/5/14
 * Time: 12:17 PM
 */
require_once( '../../../wp-load.php' );
include_once('woocommerce-fortnox3-extended.php');
$fortnox_interface = new WC_Fortnox_Extended();
error_log("Daily run");
//CRONTAB ENTRY: * * * * * /usr/bin/php /home/ubuntu/site/wp-content/plugins/woocommere-fortnox-interface-extendend/cron_job.php

//$fortnox_interface->run_inventory_cron_job();
$fortnox_interface->run_manual_inventory_cron_job();
error_log("Daily run DONE");