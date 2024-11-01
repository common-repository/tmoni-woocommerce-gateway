<?php

class WooCommerce_Tmoni_Gateway extends WC_Payment_Gateway {

	public function __construct(){

		$this->id 				  	= "tbz_tmoni_gateway";
		$this->method_title 	  	= __('tmoni Payment Gateway', 'tmoni');
		$this->method_description 	= "Tmoni Payment Gateway Plug-in for WooCommerce";
		
		$this->has_fields 		  	= false;

		$this->testurl              = 'http://payment.tmoni.ng/payment/';
		$this->liveurl              = 'http://payment.tmoni.ng/payment/';

		$this->redirect_url        	= WC()->api_request_url( 'WooCommerce_Tmoni_Gateway' );

		$this->icon 				= apply_filters('woocommerce_webpay_icon', plugins_url( 'assets/tmoni-logo.png' , __FILE__ ) );
		// Load the form fields
		$this->init_form_fields();
		// Load the settings
		$this->init_settings();
		// Define user set variables
        $this->title            	= $this->get_option('title');
        $this->description          = $this->get_option('description');
        $this->tmoniMerchantId      = $this->get_option('tmoniMerchantId');
		$this->testmode				= $this->get_option( 'testmode' );
        
        // Add Actions
        add_action('woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));
		add_action('woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );
		// API hook
		add_action('woocommerce_api_woocommerce_tmoni_gateway', array( $this, 'check_tmoni_response' ) );
		// Display Transaction Reference on checkout
		// add_action('before_woocommerce_pay', array( $this, 'display_transaction_id'));

		// Check if the gateway can be used
		if(!$this->is_valid_for_use()){
			$this->enabled = false;
		}

	}

	/**
 	* Check if the store curreny is set to NGN
 	**/ 	
	public function is_valid_for_use() {
		if( ! in_array( get_woocommerce_currency(), array('NGN') ) ){
			$this->msg = 'Tmoni doesn\'t support your store currency, set it to Nigerian Naira &#8358; <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=general">here</a>';
			return false;
		}
		return true;
	}

	/**
	 * Check if this gateway is enabled
	 */	
	public function is_available() {
		if ( $this->enabled == "yes" ) {
			if ( ! ( $this->tmoniMerchantId ) ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     **/
	public function admin_options() {
        echo '<h3>Tmoni Payment Gateway</h3>';
        echo '<p>Tmoni allows you to accept micro-payments for digital content on your WooCommerce store.</p>';

		if ( $this->is_valid_for_use() ){
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }
		else{	 ?>
		<div class="inline error"><p><strong>TMoni Payment Gateway Disabled</strong>: <?php echo $this->msg ?></p></div>
		<?php 
		}
    }


	public function init_form_fields(){
		$this->form_fields = [
			'enabled' => [
				'title'		=> __( 'Enable / Disable', 'tmoni' ),
				'label'		=> __( 'Enable this payment gateway', 'tmoni' ),
				'type'      => 'checkbox',
				'default'   => 'yes'
			],
			'title'    => [
				'title'		=> __( 'Title', 'tmoni' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'tmoni' ),
				'default'	=> 'Tmoni Payment Gateway',
			],
			'description' => [
				'title'		=> __( 'Description', 'tmoni' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'tmoni' ),
				'default'	=> __( 'Pay securely using the bank account linked to your mobile phone.', 'tmoni' ),
				'css'		=> 'max-width:350px;'
			],
			'tmoniMerchantId' => array(
				'title' 		=> 'Tmoni Merchant ID',
				'type' 			=> 'text',
				'description' 	=> 'Enter Your TMoni Merchant ID, this can be gotten on your account page when you login on Tmoni' ,
				'default' 		=> '',
    			'desc_tip'      => true
			),
			'testmode' => array(
				'title'		=> __( 'Tmoni Test Mode', 'tmoni' ),
				'label'		=> __( 'Enable Test Mode', 'tmoni' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'tmoni' ),
				'default'	=> 'yes',
			)
		];
	}


	public function get_tmoni_args( $order ){

		$merchantID     	= $this->tmoniMerchantId;
		$amount         	= $order->get_total();
		$product_id         = $order->name;
		$transaction_id     = uniqid().'_'.$order->id;

		$customer_id        = get_current_user_id();

		$phone              = get_user_meta( $customer_id, 'billing_phone', true );
		$email              = get_user_meta( $customer_id, 'billing_email', true );
		$last_name          = get_user_meta( $customer_id, 'billing_last_name', true );
		$first_name         = get_user_meta( $customer_id, 'billing_first_name', true );

		$tmoni_args         = [
			'merchant_id'     => $merchantID,
			'order_id'  	  => $transaction_id,
			'product'         => $product_id,
			'debit_amount'    => $amount,
			'debit_fname'     => $first_name,
			'debit_lname'     => $last_name,
			'debit_email'     => $email,
			'debit_mobile'    => $phone,
			'redirect_url'    => $this->redirect_url,
		];

		$tmoni_args         = apply_filters( 'woocommerce_tmoni_args', $tmoni_args);
		return $tmoni_args;
	}

	public function generate_tmoni_form( $order_id ){
		$order  = wc_get_order( $order_id );

		$tmoni_adr = $this->liveurl;
		if( $this->testmode == 'yes'){
			$tmoni_adr = $this->testurl;
		}

		$tmoni_args = $this->get_tmoni_args($order);

		// before payment hook
		do_action('tbz_wc_tmoni_before_payment', $tmoni_args);

		$tmoni_args_array = [];

		foreach ($tmoni_args as $key => $value) {
			$tmoni_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
		}

		wc_enqueue_js( '
			$.blockUI({
					message: "' . esc_js( __( 'Thank you for your order.
					We are now redirecting you to Tmoni to make payment.', 'woocommerce' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
						padding:        "20px",
						zindex:         "9999999",
						textAlign:      "center",
						color:          "#555",
						border:         "3px solid #aaa",
						backgroundColor:"#fff",
						cursor:         "wait",
						lineHeight:		"24px",
					}
				});

			jQuery("#submit_tmoni_payment_form").click();

		' );

		return '<form action="' . $tmoni_adr . '" method="post" id="tmoni_payment_form" target="_top">
		' . implode( '', $tmoni_args_array ) . '
		<!-- Button Fallback -->
		<div class="payment_buttons">
			<input type="submit" class="button alt" id="submit_tmoni_payment_form" value="Pay via Tmoni DD" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">Cancel order &amp; restore cart</a>
		</div>
		<script type="text/javascript">
			jQuery(".payment_buttons").hide();
		</script>
		</form>';
	}

	// Submit payment and handle response
	function process_payment( $order_id ) {
		$order = wc_get_order($order_id);

		return [
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true )
		];
	}

	function receipt_page($order){
		echo '<p>' . __( 'Thank you - your order is now pending payment. You should be automatically redirected to Tmoni to make payment.', 'woocommerce' ) . '</p>';
		echo $this->generate_tmoni_form( $order );
	}

	/**
	 * Verify a successful Payment!
	 *
	**/

	public function check_tmoni_response( ){
		// error_log("gasgsga", 3, "/var/www/html/wordpress/myerror.log");

		$merchantid = $this->tmoniMerchantId;
		// $tmoni_args = $this->tmoni_args
		$query_url  = 'http://payment.tmoni.ng/api/get_transaction/';
		// wp_die( "asgsa", "sgasgd" );
		if(isset($_REQUEST['transaction-id'])){
			$transaction_id  = $_REQUEST['transaction-id'];

			$order_details   = explode('_', $transaction_id);

			$transaction_ref = $order_details[0];
			$order_id 		 = (int)$order_details[1];

			$order           = wc_get_order($order_id);
			$order_total     = $order->get_total();
			
			$json 			 = wp_remote_get($query_url.'?merchant_id='.$merchantid.'&transaction_id='.$transaction_id, ['timeout' => 60]);
			// var_dump($json['body']);
			// sgagsagsdgasg
			$response 		 = json_decode($json['body'], true);

            // do_action('tbz_wc_tmoni_after_payment', $_POST, $response );

            $status = $response['response']['status'];

            $amount_paid   = $response['response']['amount'];
            $response_desc = $response['message']; 

            // this means that the transaction is successful
            if($status == '00'){

            	if($amount_paid < $order_total){
            		
            		$order->update_status('on-hold','');

            		$message      = 'Payment successful, but the amount paid is less than the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.<br />Transaction Reference: '.$transaction_ref;
					$message_type = 'notice';
					// Add Customer Order note
					$order->add_order_note($message,1 );
					//Add Admin Order Note
                    $order->add_order_note( 'Look into this order. <br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was &#8358;'.$amount_paid.' while the total order amount is &#8358;'.$order_total.'<br />Transaction Reference: '.$transaction_ref);
                    add_post_meta( $order_id, '_transaction_id', $transaction_ref, true );
					// Reduce stock levels
					$order->reduce_order_stock();
					// Empty cart
					wc_empty_cart();    
            	} else {

            		$message = 'Payment Successful.<br />Transaction Reference: '.$transaction_ref;
					$message_type = 'success';

                	//Add admin order note
                    $order->add_order_note('Payment Via Tmoni<br />Transaction Reference: '.$transaction_ref);

                    //Add customer order note
 					$order->add_order_note( 'Payment Successful.<br />Transaction Reference: '.$transaction_ref, 1);

 					$order->payment_complete( $transaction_ref);
					// Empty cart
					wc_empty_cart();
            	}

            } else {
            	$query_url 	   = 'http://payment.tmoni.ng/api/transactions/';
				$json 		   = wp_remote_get($query_url.'?merchant_id='.$merchantid.'&transaction_id='.$transaction_id, ['timeout' => 60]);
				$response 	   = json_decode($json['body'], true);

				$response_desc = $response['response']['list'][0]['message'];

            	$message 	   = 	'Payment Failed<br />Reason: '. $response_desc.'<br />Transaction Reference: '.$transaction_ref;
				$message_type  = 'error';
				//Add Customer Order Note
               	$order->add_order_note( $message, 1 );
                //Add Admin Order Note
              	$order->add_order_note( $message );
                //Update the order status
				$order->update_status( 'failed', '' );
            }
		} else {
			$message = 	'Payment Failed';
			$message_type = 'error';
		}

		$notification_message = array(
            	'message'	=> $message,
            	'message_type' => $message_type
            );

		update_post_meta( $order_id, '_tbz_tmoni_wc_message', $notification_message );

        $redirect_url = $this->get_return_url( $order );

        wp_redirect( $redirect_url );

        exit();
	}
}

		/**
	 * only add the naira currency and symbol if WC versions is less than 2.1
	 */
	if ( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {

		/**
		* Add NGN as a currency in WC
		**/
		add_filter( 'woocommerce_currencies', 'tbz_add_my_currency' );

		if( ! function_exists( 'tbz_add_my_currency' )){
			function tbz_add_my_currency( $currencies ) {
			     $currencies['NGN'] = __( 'Naira', 'woocommerce' );
			     return $currencies;
			}
		}

		/**
		* Enable the naira currency symbol in WC
		**/
		add_filter('woocommerce_currency_symbol', 'tbz_add_my_currency_symbol', 10, 2);

		if( ! function_exists( 'tbz_add_my_currency_symbol' ) ){
			function tbz_add_my_currency_symbol( $currency_symbol, $currency ) {
			     switch( $currency ) {
			          case 'NGN': $currency_symbol = '&#8358; '; break;
			     }
			     return $currency_symbol;
			}
		}
	}
