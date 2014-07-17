<?php
class WCFDatabaseInterface{

    /**
     *
     */
    function __construct() {
    }

    /**
     * Creates a n XML representation of a n Order
     *
     * @access public
     * @internal param mixed $arr
     * @return mixed
     */
    public function read_unsynced_orders(){
        global $wpdb;
        return $wpdb->get_results("SELECT * from wcf_orders WHERE synced = 0");
    }

    /**
     * Sets an order to synced
     *
     * @access public
     * @param int $orderId
     * @return bool
     */
    public function set_as_synced($orderId){
        global $wpdb;
        $wpdb->query("UPDATE wcf_orders SET synced = 1 WHERE order_id = ".$orderId);
        return true;

    }

    /**
     * Writes an unsynced order to the database
     *
     * @access public
     * @param int $orderId
     * @return bool
     */
    public function create_unsynced_order($orderId){
        global $wpdb;
        $wpdb->query("INSERT INTO wcf_orders VALUES (NULL, ".mysql_real_escape_string($orderId).", 0)");
        return true;
    }

    /**
     * Creates a customer
     *
     * @access public
     * @param $email
     * @return bool
     */
    public function create_customer($email){
        global $wpdb;
        $wpdb->query("INSERT INTO wcf_customers VALUES (NULL, 0,'".mysql_real_escape_string($email)."')");
        return $wpdb->insert_id;
    }

    /**
     * Creates a customer
     *
     * @access public
     * @param $customer
     * @return bool
     */
    public function create_existing_customer($customer){
        global $wpdb;
        if(!is_array($customer['CustomerNumber']) && !is_array($customer['Email'])){

            $wpdb->query("INSERT INTO wcf_customers VALUES (NULL, '".mysql_real_escape_string($customer['CustomerNumber'])."', '".mysql_real_escape_string($customer['Email'])."')");
            return $wpdb->insert_id;
        }
    }

    /**
     * Gets customer by email
     *
     * @access public
     * @param $email
     * @return bool
     */
    public function get_customer_by_email($email){
        global $wpdb;
        return $wpdb->get_results("SELECT * from wcf_customers WHERE email = '". mysql_real_escape_string($email) ."'");
    }

    /**
     * Writes an unsynced order to the database
     *
     * @access public
     * @param $customerId
     * @param $customerNumber
     * @return bool
     */
    public function update_customer($customerId, $customerNumber){
        global $wpdb;
        $wpdb->query("UPDATE wcf_customers SET customer_number = '". $customerNumber ."' WHERE id = ".$customerId);
        return true;
    }
}