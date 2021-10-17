<?php
/**
 * Plugin Name: WooCommerce PayPro
 * Plugin URI: https://www.paypro.com.pk
 * Description: End-to-End Digital Payment Solution
 * Version: 1.2.6
 * Author: Mirza Inshal Baig
 * Author URI: https://www.paypro.com.pk/
 */


add_action( 'plugins_loaded', 'init_pp_woo_gateway');

function mib_ppbycp_settings( $links ) {
    $settings_link = '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mibwoo_pp' ).'">Setup</a>';
    array_push( $links, $settings_link );
    return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'mib_ppbycp_settings' );

function init_pp_woo_gateway(){

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Gateway_MIB_PayPro extends WC_Payment_Gateway {

        // Logging
        public static $log_enabled = false;
        public static $log = false;

        var $merchant_id;
        var $merchant_password;
        var $test_mode;
        var $plugin_url;
        var $timeout;
        var $checkout_url;
        var $api_url;
        var $timeout_in_days;

        public function __construct(){

            global $woocommerce;

            $this -> plugin_url = WP_PLUGIN_URL . DIRECTORY_SEPARATOR . 'woocommerce-paypro-payment';

            $this->id 					= 'mibwoo_pp';
            $this->has_fields   		= false;
            $this->checkout_url     	= 'https://marketplace.paypro.com.pk/';
            $this->icon 				= $this->plugin_url.'/images/master-visa.png';
            $this->method_title 		= 'PayPro';
            $this->method_description 	= 'End-to-End Digital Payment Solution';

            $this->title 				= $this->get_option( 'method_title' );
            $this->description 			= $this->get_option( 'method_description' );
            $this->merchant_id			= $this->get_option( 'merchant_id' );
            $this->merchant_password	= trim($this->get_option( 'merchant_password' ));
            $this->merchant_secret		= $this->get_option( 'merchant_secret' );
            $this->test_mode 			= $this->get_option('test_mode');
            $this->timeout_in_days 		= $this->get_option('timeout_in_days');
            $this->debug 				= $this->get_option('debug');
            $this->pay_method 		    = "PayPro";
            if($this->timeout_in_days==='yes'){
                $this->timeout          	= trim($this->get_option( 'merchant_timeout_days' ));
            }
            else{
                $this->timeout          	= trim($this->get_option( 'merchant_timeout_minutes' ));
            }
            if($this->test_mode==='yes'){
                $this->api_url          	= 'https://demoapi.paypro.com.pk';
            }
            else{
                $this->api_url          	= 'https://api.paypro.com.pk';
            }

            $this->init_form_fields();
            $this->init_settings();

            self::$log_enabled = $this->debug;

            // Save options
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Payment listener/API hook
            add_action( 'woocommerce_api_wc_gateway_mib_paypro', array( $this, 'paypro_response' ) );
            add_action( 'woocommerce_api_paypro_callback', array( $this, 'paypro_callback' ) );
//            add_action( 'woocommerce_api_wc_gateway_callback_paypro_auto', array( $this, 'paypro_response' ) );

        }

        function init_form_fields(){

            $this->form_fields = array(

                'enabled' => array(
                    'title' => __( 'Enable', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Yes', 'woocommerce' ),
                    'default' => 'yes'
                ),

                'method_title' => array(
                    'title' => __( 'Method Title', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'Enter Method Title to display in checkout', 'woocommerce' ),
                    'default' => 'PayPro',
                    'desc_tip'      => true,
                ),

                'method_description' => array(
                    'title' => __( 'Method Description', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'Enter Method Description to display in checkout', 'woocommerce' ),
                    'default' => 'End-to-End Digital Payment Solution',
                    'desc_tip' => true,
                ),

                'merchant_id' => array(
                    'title' => __( 'Merchant Username', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This Merchant Username Provided by PayPro', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                ),

                'test_mode' => array(
                    'title' => __( 'Enable Test Mode', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Yes', 'woocommerce' ),
                    'default' => 'yes'
                ),

                'merchant_password' => array(
                    'title' => __( 'Merchant Password', 'woocommerce' ),
                    'type' => 'password',
                    'description' => __( 'Merchant Password Provided by PayPro', 'woocommerce' ),
                    'default' => __( '', 'woocommerce' ),
                    'desc_tip'      => true,
                ),

                'merchant_secret' => array(
                    'title' => __( 'Secret Key', 'woocommerce' ),
                    'type' => 'password',
                    'description' => __( 'Any Secret Key Or Word with No Spaces', 'woocommerce' ),
                    'default' => __( rand(), 'woocommerce' ),
                    'desc_tip'      => true,
                ),

                'merchant_timeout_minutes' => array(
                    'title' => __( 'Timeout (In Minutes)', 'woocommerce' ),
                    'type' => 'number',
                    'description' => __( 'Timeout Before order expires it can be between 5 to 30 minutes', 'woocommerce' ),
                    'default' => __( 5, 'woocommerce' ),
                    'desc_tip'      => true,
                    'custom_attributes' => array(
                        'min' => 5,
                        'max' => 30
                    )
                ),

                'timeout_in_days' => array(
                    'title' => __( 'Enable Timeout in days', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Yes', 'woocommerce' ),
                    'default' => 'no'
                ),

                'merchant_timeout_days' => array(
                    'title' => __( 'Timeout (In Days)', 'woocommerce' ),
                    'type' => 'number',
                    'description' => __( 'Minimum 1 day Max 3 Days. Remember, It works as due date you\'ve selected 1 day the expiration date of the PayPro id will be set as the day after today.', 'woocommerce' ),
                    'default' => __( 1, 'woocommerce' ),
                    'desc_tip'      => true,
                    'custom_attributes' => array(
                        'min' => 1,
                        'max' => 3
                    )
                ),

                'debug' => array(
                    'title'       => __( 'Debug Log', 'woocommerce' ),
                    'type'        => 'checkbox',
                    'label'       => __( 'Enable logging', 'woocommerce' ),
                    'default'     => 'no',
                    'description' => sprintf( __( 'Debug Information <em>%s</em>', 'woocommerce' ), wc_get_log_file_path( 'paypro' ) )
                ),
            );
        }
        /**
         * Logging method
         * @param  string $message
         */
        public static function log( $message ) {
            if ( self::$log_enabled ) {
                if ( empty( self::$log ) ) {
                    self::$log = new WC_Logger();
                }

                $message = is_array($message) ? json_encode($message) : $message;
                self::$log->add( 'paypro', $message );
            }
        }


        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment( $order_id ) {

            $order = new WC_Order( $order_id );

            $paypro_args = $this->get_paypro_args( $order );

            $paypro_args = http_build_query( $paypro_args, '', '&' );
            $this->log("========== Payment Processing Started: args =========");
            $this->log($paypro_args);

            //if demo is enabled
            $checkout_url = $this->checkout_url;;
            return array(
                'result' 	=> 'success',
//                'redirect'	=> 'http://localhost:8000/secureform?'.$paypro_args
                'redirect'	=> 'https://marketplace.paypro.com.pk/secureform?'.$paypro_args
            );
        }


        /**
         * Get PayPro Args for passing to PP
         *
         * @access public
         * @param mixed $order
         * @return array
         */
        function get_paypro_args( $order ) {
            global $woocommerce;

            $order_id = $order->get_id();
            //Getting date as per PST time
            $dt = new DateTime();
            $dt->setTimezone(new DateTimeZone('Asia/Karachi'));
            $dt->setTimestamp(time());
            //Encrypting the username and password
            $token1         = $this->merchant_id;
            $token2         = $this->merchant_password;
            $cipher_method  = 'aes-128-ctr';
            $secret_word    = md5($this->merchant_secret);
            $enc_key        = openssl_digest($secret_word.$dt->format('d/m/y'), 'SHA256', TRUE);
            $enc_iv         = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher_method));
            $crypted_token1 = openssl_encrypt($token1, $cipher_method, $enc_key, 0, $enc_iv) . "::" . bin2hex($enc_iv);
            $crypted_token2 = openssl_encrypt($token2, $cipher_method, $enc_key, 0, $enc_iv) . "::" . bin2hex($enc_iv);
            unset($token, $cipher_method, $enc_key, $enc_iv);

            // PayPro Args
            $paypro_args = array(
                'mid' 					=> $crypted_token1,
                'mpw' 					=> $crypted_token2,
                'secret_public'			=> base64_encode($this->merchant_secret),
                'is_encrypted'			=> 1,
                'mode' 					=> $this->test_mode,
//                'timeout_in_days'		=> $this->timeout_in_days,
                'merchant_order_id'		=> $order_id,
                'merchant_name' 		=> get_bloginfo( 'name' ),
                'request_is_valid'		=> 'true',
                'request_from'          => 'woocommerce',

                // Billing Address info
                'first_name'			=> $order->get_billing_first_name(),
                'last_name'				=> $order->get_billing_last_name(),
                'street_address'		=> $order->get_billing_address_1(),
                'street_address2'		=> $order->get_billing_address_2(),
                'city'					=> $order->get_billing_city(),
                'state'					=> $order->get_billing_state(),
                'zip'					=> $order->get_billing_postcode(),
                'country'				=> $order->get_billing_country(),
                'email'					=> $order->get_billing_email(),
                'phone'					=> $order->get_billing_phone(),
            );
            if (!function_exists('is_plugin_active')) {
                include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }
            if(is_plugin_active( 'custom-order-numbers-for-woocommerce/custom-order-numbers-for-woocommerce.php' )){
                $paypro_args['paypro_order_id']			    = time().'-'.get_option( 'alg_wc_custom_order_numbers_counter', 1 );
            }
            else{
                $paypro_args['paypro_order_id']			    = time().'-'.$order_id;
            }
            // Shipping

            if ($order->needs_shipping_address()) {
                $paypro_args['ship_name']			    = $order->get_shipping_first_name().' '.$order->get_shipping_last_name();
                $paypro_args['company']				    = $order->get_shipping_company();
                $paypro_args['ship_street_address']	    = $order->get_shipping_address_1();
                $paypro_args['ship_street_address2']    = $order->get_shipping_address_2();
                $paypro_args['ship_city']			    = $order->get_shipping_city();
                $paypro_args['ship_state']			    = $order->get_shipping_state();
                $paypro_args['ship_zip']				= $order->get_shipping_postcode();
                $paypro_args['ship_country']			= $order->get_shipping_country();
            }

            $paypro_args['x_receipt_link_url'] 	        = $this->get_return_url( $order );
            $paypro_args['request_site_url'] 	        = get_site_url();
            $paypro_args['request_site_checkout_url'] 	= wc_get_checkout_url();
            $paypro_args['return_url']			        = $order->get_cancel_order_url();
            $paypro_args['issueDate']                   = $dt->format('d/m/Y');
            $paypro_args['cartTotal']                   = $order->get_total();
            $paypro_args['store_currency']              = get_woocommerce_currency();
            $paypro_args['store_currency_symbol']       = get_woocommerce_currency_symbol();

            //Getting Cart Items
            $billDetails= array();
            $flag = 0;
            foreach ($order->get_items() as $item => $values){
                // Get the product name
                $product_name = $values['name'];
                // Get the item quantity
                $item_quantity = $order->get_item_meta($item, '_qty', true);
                // Get the item line total
                $item_total = $order->get_item_meta($item, '_line_total', true);
                $price = $item_total/$item_quantity;
                $billDetails[$flag]['LineItem']     = esc_html($product_name);
                $billDetails[$flag]['Quantity']     = $item_quantity;
                $billDetails[$flag]['UnitPrice']    = $price;
                $billDetails[$flag++]['SubTotal']   = $item_total;
            }
            $paypro_args['cartItemList'] = urlencode(json_encode($billDetails));

            //setting payment method
            if ($this->pay_method)
                $paypro_args['pay_method'] = $this->pay_method;

            //if test_mode is enabled
            if ($this -> test_mode == 'yes'){
                $paypro_args['test_mode'] =	'Y';
            }
            //if timeout_in_days is enabled
            if ($this -> timeout_in_days == 'yes'){
                $paypro_args['timeout'] = ((($this->timeout)*24)*60)*60;
            }
            else{
                $paypro_args['timeout'] = $this->timeout*60;
            }

            $paypro_args = apply_filters( 'woocommerce_paypro_args', $paypro_args );

            return $paypro_args;
        }


        /**
         * this function is return product object for two
         * different version of WC
         */
        function get_product_object(){
            return $product;
        }


        /**
         * Check for PayPro Response
         *
         * @access public
         * @return void
         */
        function paypro_response() {


            global $woocommerce;


            $this->log(__("== INS Response Received == ", "PayPro") );
            $this->log( $_REQUEST );

            $wc_order_id = '';

            if( !isset($_REQUEST['merchant_order_id']) ) {
                if( !isset($_REQUEST['vendor_order_id']) ) {
                    $this->log( '===== NO ORDER NUMBER FOUND =====' );
                    exit;
                } else {
                    $wc_order_id = $_REQUEST['vendor_order_id'];
                }
            } else {

                $wc_order_id = $_REQUEST['merchant_order_id'];
            }

            $this->log(" ==== ORDER -> {$wc_order_id} ====");

            $wc_order_id = apply_filters('woocommerce_order_no_received', $wc_order_id, $_REQUEST);
            $this->log( "Order Received ==> {$wc_order_id}" );


            $wc_order 		= new WC_Order( absint( $wc_order_id ) );
            $this->log("Order ID {$wc_order_id}");

            $this->log("WC API ==> ".$_GET['wc-api']);
            // If redirect after payment
            if( isset($_GET['key']) && (isset($_GET['wc-api']) && strtolower($_GET['wc-api']) == 'wc_gateway_mib_paypro') )  {
                $this->verify_order_by_hash($wc_order_id);
                exit;
            }

            $message_type	= isset($_REQUEST['message_type']) ? $_REQUEST['message_type'] : '';
            $sale_id		= isset($_REQUEST['sale_id']) ? $_REQUEST['sale_id'] : '';
            $invoice_id		= isset($_REQUEST['invoice_id']) ? $_REQUEST['invoice_id'] : '';
            $fraud_status	= isset($_REQUEST['fraud_status']) ? $_REQUEST['fraud_status'] : '';

            $this->log( "Message Type/Fraud Status: {$message_type}/{$fraud_status}" );

            switch( $message_type ) {

                case 'ORDER_CREATED':
                    $wc_order->add_order_note( sprintf(__('ORDER_CREATED with Sale ID: %d', 'woocommerce'), $sale_id) );
                    $this->log(sprintf(__('ORDER_CREATED with Sale ID: %d', 'woocommerce'), $sale_id));
                    break;

                case 'FRAUD_STATUS_CHANGED':
                    if( $fraud_status == 'pass' ) {
                        // Mark order complete
                        $wc_order->payment_complete();
                        $wc_order->add_order_note( sprintf(__('Payment Status Clear with Invoice ID: %d', 'woocommerce'), $invoice_id) );
                        $this->log(sprintf(__('Payment Status Clear with Invoice ID: %d', 'woocommerce'), $invoice_id));
                        add_action('woocommerce_order_completed', $order, $sale_id, $invoice_id);

                    } elseif( $fraud_status == 'fail' ) {

                        $wc_order->update_status('failed');
                        $wc_order->add_order_note(  __("Payment Declined", 'woocommerce') );
                        $this->log( __("Payment Declined", 'woocommerce') );
                    }

                    break;
            }

            exit;
        }

        /**
         * Mark Order Paid Via Callback
         *
         * @access public
         * @return void
         */
        function paypro_callback() {

            global $woocommerce;

            $this->log(__("== PayPro Callback Service Received == ", "PayPro") );
            $this->log( $_REQUEST );

            if( !isset($_REQUEST['merchant_order_id']) ) {
                if( !isset($_REQUEST['vendor_order_id']) ) {
                    $this->log( '===== NO ORDER NUMBER FOUND =====' );
                    exit;
                } else {
                    $wc_order_id = $_REQUEST['vendor_order_id'];
                }
            } else {
                $wc_order_id = $_REQUEST['merchant_order_id'];
            }
            $wc_order 		= wc_get_order( $wc_order_id );

            if(empty($wc_order)){
                $this->log('Received and Fetched Order ID\'s Didn\'t match.');
                $response_array= [
                    [
                        'StatusCode' => "03",
                        'InvoiceID' => $_REQUEST['tpaycode'],
                        'Description' => "Invalid Order ID"
                    ]
                ];
                echo json_encode($response_array);
                exit;
            }

            $this->log(" ==== ORDER -> {$wc_order_id} ====");

            // echo $wc_order_id;
            $wc_order_id = apply_filters('woocommerce_order_no_received', $wc_order_id, $_REQUEST);
            $this->log( "Order Received ==> {$wc_order_id}" );

            $wc_order 		= new WC_Order( absint( $wc_order_id ) );
            $this->log("Order ID {$wc_order_id}");

            // If redirect after payment
            if( isset($_GET['key']) ) {
                $paypro_id	    = $_REQUEST['paypro_id'];
                $tpaycode	    = $_REQUEST['tpaycode'];
                $order_id	    = $_REQUEST['merchant_order_id'];
                $wc_order 		= wc_get_order( $wc_order_id );
                $order_total	= $wc_order->get_total();

                $compare_string =  $this->merchant_id . $paypro_id . $order_total;
                $compare_hash1  = strtoupper(md5($compare_string));
                $this->log("Compare String ===>" .$compare_string);
                $compare_hash2 = $_REQUEST['key'];
                if ($compare_hash1 != $compare_hash2) {
                    $this->log("Hash_1 ==> {$compare_hash1}");
                    $this->log("Hash_2 ==> {$compare_hash2}");
                    wp_die( "PayPro Hash Mismatch... check your secret word." );
                } else {
                    //Curl Request
                    $url = $this->api_url . '/cpay/gos?userName=' . $this->merchant_id . '&password=' . $this->merchant_password . '&cpayId=' . $tpaycode;
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
                    // Submit the GET request
                    $result = curl_exec($ch);
                    if (curl_errno($ch)) { //catch if curl error exists and show it
//                    echo 'Curl error: ' . curl_error($ch);
                        $this->log("Curl Error ==> " . json_encode(curl_error($ch)));
                    } else {
                        //Check if the order ID passed is the same or fake
                        $res = json_decode($result, true);
                        $returnedOrderID = explode('-', $res[1]['OrderNumber']);
                        $this->log("Response From PayPro Order Status Api ==> " . json_encode($res));
                        if ($returnedOrderID[1] === $order_id) {
                            if (strtoupper($res[1]['OrderStatus']) == "PAID") {
                                if($wc_order->get_status()!='processing'){

                                    $wc_order->add_order_note(sprintf(__('Payment completed via PayPro Order Number %d', 'paypro'), $tpaycode));
                                    $this->log(sprintf(__('Payment completed via PayPro Order Number %d', 'paypro'), $tpaycode));
                                    // Mark order complete
                                    $wc_order->payment_complete();
                                    // Empty cart and clear session
                                    $woocommerce->cart->empty_cart();
                                    $order_redirect = add_query_arg('paypro', 'processed', $this->get_return_url($wc_order));
                                    // Close cURL session handle
                                    curl_close($ch);
                                    $response_array= [[
                                        'StatusCode' => "00",
                                        'InvoiceID' => $tpaycode,
                                        'Description' => "Invoice successfully marked as paid"
                                    ]];
                                    $this->log('Invoice successfully marked as paid');

                                }else{
                                    $this->log('Invoice is already marked paid in system');
                                    $response_array= [[
                                        'StatusCode' => "00",
                                        'InvoiceID' => $tpaycode,
                                        'Description' => "Invoice is already paid"
                                    ]];
                                }
                            }else{
                                $this->log('Invoice is unpaid on PayPro\'s system');
                                $response_array= [[
                                    'StatusCode' => "03",
                                    'InvoiceID' => $tpaycode,
                                    'Description' => "Invoice is unpaid on PayPro's system"
                                ]];
                            }
                        } else {
                            $this->log('Received and Fetched Order ID\'s Didn\'t match.');
                            $response_array= [
                                [
                                    'StatusCode' => "03",
                                    'InvoiceID' => $tpaycode,
                                    'Description' => "Order ids did not match"
                                ]
                            ];
                        }
                    }
                }
                echo json_encode($response_array);
                exit;
            }
        }


        function verify_order_by_hash($wc_order_id) {

            global $woocommerce;

            @ob_clean();

            $paypro_id	= $_REQUEST['paypro_id'];
            $tpaycode	= $_REQUEST['tpaycode'];
            $order_id	= $_REQUEST['merchant_order_id'];
            $wc_order 		= wc_get_order( $wc_order_id );
            // $order_total	= isset($_REQUEST['total']) ? $_REQUEST['total'] : '';
            $order_total	= $wc_order->get_total();

            $compare_string =  $this->merchant_id . $paypro_id . $order_total;
            $compare_hash1 = strtoupper(md5($compare_string));
            $this->log("Compare String ===>" .$compare_string);
            $compare_hash2 = $_REQUEST['key'];
            if ($compare_hash1 != $compare_hash2) {
                $this->log("Hash_1 ==> {$compare_hash1}");
                $this->log("Hash_2 ==> {$compare_hash2}");
                wp_die( "PayPro Hash Mismatch... check your secret word." );
            } else {
                //Curl Request
                $url = $this->api_url.'/cpay/gos?userName=' . $this->merchant_id . '&password=' . $this->merchant_password . '&cpayId=' . $tpaycode;
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLINFO_HEADER_OUT, true);
                // Submit the GET request
                $result = curl_exec($ch);
                if (curl_errno($ch)) { //catch if curl error exists and show it
                    $this->log("Curl Error ==> ".json_encode(curl_error($ch)));
                }
                else {
                    //Check if the order ID passed is the same or fake
                    $res = json_decode($result, true);
                    $returnedOrderID = explode('-',$res[1]['OrderNumber']);
                    $this->log("Response From PayPro Order Status Api ==> ".json_encode($res));
                    if ($returnedOrderID[1]===$order_id) {
                        if (strtoupper($res[1]['OrderStatus']) == "PAID") {

                            $wc_order->add_order_note( sprintf(__('Payment completed via PayPro Order Number %d', 'paypro'), $tpaycode) );
                            $this->log(sprintf(__('Payment completed via PayPro Order Number %d', 'paypro'), $tpaycode));
                            // Mark order complete
                            $wc_order->payment_complete();
                            // Empty cart and clear session
                            $woocommerce->cart->empty_cart();
                            $order_redirect = add_query_arg('paypro','processed', $this->get_return_url( $wc_order ));
                            // Close cURL session handle
                            curl_close($ch);
                            wp_redirect( $order_redirect );
                            exit;

                        } elseif (strtoupper($res[1]['OrderStatus']) == "BLOCKED") {
                            $wc_order->add_order_note( sprintf(__('Error processing the payment of Order Number %d', 'paypro'), $tpaycode) );
                            $this->log(sprintf(__('Order Status Blocked On PayPro\'s System : %d', 'paypro'), $tpaycode));
                            $order_redirect = add_query_arg('paypro','canceled', $wc_order->get_cancel_order_url());
                            // Close cURL session handle
                            curl_close($ch);
                            wp_redirect( $order_redirect );
                            exit;
                        }
                        elseif (strtoupper($res[1]['OrderStatus']) == "UNPAID") {
                            $wc_order->add_order_note( sprintf(__('Error processing the payment of Order Number %d', 'paypro'), $tpaycode) );
                            $this->log(sprintf(__('Order Status Unpaid On PayPro\'s System : %d', 'paypro'), $tpaycode));
                            $order_redirect = add_query_arg('paypro','pending', $wc_order->get_cancel_order_url());
                            // Close cURL session handle
                            curl_close($ch);
                            wp_redirect( $order_redirect );
                            exit;
                        }
                    }else{
                        $this->log('Received and Fetched Order ID\'s Doesnt\'t match.');
                    }
                }
            }
        }

        function get_price($price){

            $price = wc_format_decimal($price, 2);

            return apply_filters('mib_get_price', $price);
        }

    }

}

function add_mib_payment_gateway( $methods ) {
    $methods[] = 'WC_Gateway_MIB_PayPro';
    return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_mib_payment_gateway' );


function payproco_log( $log ) {

    if ( true === WP_DEBUG ) {
        if ( is_array( $log ) || is_object( $log ) ) {
            $resp = error_log( print_r( $log, true ), 3, plugin_dir_path(__FILE__).'payproco.log' );
        } else {
            $resp = error_log( $log, 3, plugin_dir_path(__FILE__).'payproco.log' );
        }

        var_dump($resp);
    }
}


