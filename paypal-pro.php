<?php
/*
Plugin Name: Payment Pro (Direct Payment + Hosted) - PayPal
Plugin URI: https://www.presto-changeo.com/woocommerce-plugins-free-modules/151-paypal-pro-for-woocommerce-direct-payment-hosted-solution-.html
Description: Receive and Refund payments using PayPal Pro with optional 3D Secure functionality
Author: Presto-Changeo
Version: 1.0.0
Author URI: https://www.presto-changeo.com/
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pocoppp_activate() {
	update_option( 'ppp_visa', 1 );
	update_option( 'ppp_mc', 1 );
}
register_activation_hook( __FILE__, 'pocoppp_activate' );

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	add_action( 'plugins_loaded', 'pocoppp_init_paypal_pro' );

	function pocoppp_init_paypal_pro() {

		load_plugin_textdomain( 'woocommerce-gateway-paypal-pro', false, basename( dirname( __FILE__ ) ) . '/languages' );

		define( 'POCOPPP_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		require_once(plugin_dir_path(__FILE__) . 'class-paypal-pro.php');

	}

	function pocoppp_add_paypal_pro_class( $methods ) {
		$methods[] = 'POCOPPP_WC_Gateway_PayPal_Pro'; 
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'pocoppp_add_paypal_pro_class' );
	add_action( 'add_meta_boxes', 'pocoppp_add_meta_boxes' );
	add_action( 'save_post', 'pocoppp_save_post' );

	function pocoppp_add_meta_boxes() {
		global $post;
		$order = new WC_Order( $post->ID );

		if ( $order->get_payment_method() == 'poco_paypal_pro' ) {
			add_meta_box( 'pppro_capture', __( 'Capture the transaction', 'woocommerce-gateway-paypal-pro'), 'pocoppp_capture', 'shop_order', 'side', 'core' );
		}
	}

	function pocoppp_capture() {
		global $post;
		$order = new WC_Order( $post->ID );
		$captured = get_post_meta($post->ID, '_captured', true);
		$captured_amount = get_post_meta($post->ID, '_captured_amount', true);

		if ( ! $captured ) {
			echo '<input type="hidden" name="pppro_nonce" value="' . wp_create_nonce() . '">
			<p style="border-bottom:solid 1px #eee;padding-bottom:13px;">
				Total Order: '. wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) .'
				<br>
				Capture: &nbsp;<input type="text" style="width:190px;";" name="pppro_capture_amount" value="' . $order->get_total() . '">
			</p>
			<button type="submit" class="button pppro_capture button-primary" name="pppro_capture">'. __( 'Capture', 'woocommerce-gateway-paypal-pro') .'</button>';
		} else {
			echo '<p style="border-bottom:solid 1px #eee;padding-bottom:13px;">
				Total Order: '. wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) .'
				<br>
				Captured: '. wc_price( $captured_amount, array( 'currency' => $order->get_currency() ) ) .'
			</p>';
		}

	}

	function pocoppp_save_post( $post_id ) {
		if ( ! isset( $_POST[ 'pppro_nonce' ] ) ) {
			return $post_id;
		}
		$nonce = $_REQUEST[ 'pppro_nonce' ];

		//Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce ) ) {
			return $post_id;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( 'shop_order' != $_POST[ 'post_type' ] || ! current_user_can( 'edit_page', $post_id )  ) {
			return $post_id;
		}

		$pppro = new POCOPPP_WC_Gateway_PayPal_Pro();
		$transaction_id = get_post_meta( $post_id, '_id_trans', true );
		$order = new WC_Order( $post_id );

		if ( isset( $_POST[ 'pppro_capture' ] ) ) {
			$res_arr = $pppro->doCapture( $post_id, $transaction_id, (float)$_POST[ 'pppro_capture_amount' ] );
			if( $res_arr[ 'ACK' ] == 'Success' ) {

				update_post_meta( $post_id, '_captured', 1 );
				update_post_meta( $post_id, '_captured_amount', (float)$_POST[ 'pppro_capture_amount' ] );

				$order->add_order_note( __( 'Transaction Capture of ', 'woocommerce-gateway-paypal-pro' ) . wc_price( $_POST[ 'pppro_capture_amount' ], array( 'currency' => $order->get_currency() ) ) );
			} else {
				$order->add_order_note( __( 'The capture failed. PayPal response: ', 'woocommerce-gateway-paypal-pro' ) . urldecode( $res_arr['L_LONGMESSAGE0'] ) );
			}
		} else {
			return $post_id;
		}
	}

}

if ( is_admin() ) {
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {

		$plugin_links = array(
		'<a href="admin.php?page=wc-settings&tab=checkout&section=poco_paypal_pro">' . esc_html__( 'Settings', 'woocommerce-gateway-paypal-pro' ) . '</a>'
		);
		return array_merge( $plugin_links, $links );

	} );
}




