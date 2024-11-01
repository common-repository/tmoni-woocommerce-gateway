<?php
/*
* Plugin Name: Tmoni.ng - WooCommerce Gateway
* Plugin URI: https://github.com/samparsky/
* Description: Extends Woocommerce by Adding the Tmoni payment gateway
* Version: 1.0.2
* Author: Omidiora Samuel
* Author URI : http://samparsky.github.io
*/

if ( ! defined( 'ABSPATH' ) )exit;

add_action('plugins_loaded', 'tbz_wc_tmoni_init', 0);

function tbz_wc_tmoni_init(){
	// IF the parent WC_Payment_Gateway class doesn't exist
	// it means woocommerce is not installed on the site
	// so do nothing

	if(!class_exists('WC_Payment_Gateway')) return;
	
	include_once('woocommerce-tmoni.php');

	function tbz_wc_tmoni_message() {
		
		if( get_query_var( 'order-received' ) ){

			$order_id 		= absint( get_query_var( 'order-received' ) );
			$order 			= wc_get_order( $order_id );
			$payment_method = $order->payment_method;

			if( is_order_received_page() &&  ( 'tbz_tmoni_gateway' == $payment_method ) ){

				$notification 		= get_post_meta( $order_id, '_tbz_tmoni_wc_message', true );

				$message 			= isset( $notification['message'] ) ? $notification['message'] : '';
				$message_type 		= isset( $notification['message_type'] ) ? $notification['message_type'] : '';

				delete_post_meta( $order_id, '_tbz_tmoni_wc_message' );

				if( ! empty( $message) ){
					wc_add_notice( $message, $message_type );
				}
			}

		}
	}

	add_action( 'wp', 'tbz_wc_tmoni_message', 0 );

	function spyr_add_woocommerce_gateway($methods){
		$methods[] = 'WooCommerce_Tmoni_Gateway';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'spyr_add_woocommerce_gateway');


	/**
	*
	* Add Settings link to the plugin entry in the plugins menu for WC below 2.1
	*
	**/
	if ( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {

		add_filter('plugin_action_links', 'tbz_tmoni_plugin_action_links', 10, 2);

		function tbz_tmoni_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
	        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WooCommerce_Tmoni_Gateway">Settings</a>';
		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	} else {

		/**
			* Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
		**/
		add_filter('plugin_action_links', 'tbz_tmoni_plugin_action_links', 10, 2);

		function tbz_tmoni_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
		        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WooCommerce_Tmoni_Gateway">Settings</a>';
		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	}


}

