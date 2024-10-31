<?php

class POCOPPP_WC_Gateway_PayPal_Pro extends WC_Payment_Gateway {

	public function __construct() {

		$this->id = 'poco_paypal_pro';
		$this->icon = POCOPPP_PLUGIN_URL . '/assets/images/combo.jpg';

		$this->method_title = __( 'PayPal Pro', 'woocommerce-gateway-paypal-pro' );
		$this->method_description = __( 'Receive and Refund payments using PayPal Pro with optional 3D Secure functionality', 'woocommerce-gateway-pasypal-pro' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title = __( 'Pay with Visa, Mastercard', 'woocommerce-gateway-paypal-pro' );
		$this->supports = array(
			'products',
			'refunds',
		);

		$this->account_email = $this->get_option( 'account_email' );
		$this->api_username = $this->get_option( 'api_username' );
		$this->api_password = $this->get_option( 'api_password' );
		$this->api_signature = $this->get_option( 'api_signature' );
		$this->enable_sandbox = $this->get_option( 'enable_sandbox' );
		$this->solution_type = $this->get_option( 'solution_type' );
		$this->transaction_type = $this->get_option( 'transaction_type' );
		$this->enable_3d_secure = $this->get_option( 'enable_3d_secure' );
		$this->enable_3d_secure_sandbox = $this->get_option( 'enable_3d_secure_sandbox' );
		$this->centinel_merchant_id = $this->get_option( 'centinel_merchant_id' );
		$this->centinel_processor_id = $this->get_option( 'centinel_processor_id' );
		$this->centinel_transaction_password = $this->get_option( 'centinel_transaction_password' );
		$this->pay_button_text_color = $this->get_option( 'pay_button_text_color' );
		$this->pay_button_background_color = $this->get_option( 'pay_button_background_color' );
		$this->require_address = $this->get_option( 'require_address' );
		$this->require_cvn = $this->get_option( 'require_cvn' );
		$this->log = new WC_Logger();
		$this->ppp_3d_msg_v = '1.7';
		$this->ppp_3d_timeout_cancel = '5000';
		$this->ppp_3d_timeout_read = '3500';

		if ( $this->solution_type == '0' ) {
			$this->has_fields = true;
		} else {
			$this->has_fields = false;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_ppp_response', array( $this, 'ppp_response' ) );
		add_action( 'admin_enqueue_scripts' , array( $this, 'enqueue_scripts' ) );
	}
 
	public function validate_fields() {
		$is_valid = parent::validate_fields();

		if ( $this->solution_type == '1' ) {
			return $is_valid;
		}

		$firstname = self::getPostValue( 'pppro_cc_fname' );
		$lastname = self::getPostValue( 'pppro_cc_lname' );
		$cc_number = self::getPostValue( 'pppro_cc_number' );
		$cc_month = self::getPostValue( 'pppro_cc_Month' );
		$cc_year = self::getPostValue( 'pppro_cc_Year' );
		$cvn = self::getPostValue( 'pppro_cc_cvm' );
		$address = self::getPostValue( 'pppro_cc_address' );
		$city = self::getPostValue( 'pppro_cc_city' );
		$zip = self::getPostValue( 'pppro_cc_zip' );
		$country = self::getPostValue( 'pppro_country' );
		$state = self::getPostValue( 'pppro_state' );

		if ( ! $firstname || ! is_string( $firstname ) ) {
			wc_add_notice( __( 'Firstname is invalid', 'woocommerce-gateway-paypal-pro' ), 'error' );
			$is_valid = false;
		}

		if ( ! $lastname || ! is_string( $lastname ) ) {
			wc_add_notice( __( 'Lastname is invalid', 'woocommerce-gateway-paypal-pro' ), 'error' );
			$is_valid = false;
		}

		if ( ! $this->validate_card( $cc_number ) ) {
			wc_add_notice( __( 'Card Number is invalid', 'woocommerce-gateway-paypal-pro' ), 'error' );
			$is_valid = false;
		}

		$current_year  = date( 'Y' );
		$current_month = date( 'n' );

		if ( ! ctype_digit( $cc_month ) || ! ctype_digit( $cc_year ) ||
			$cc_month > 12 ||
			$cc_month < 1 ||
			$cc_year < $current_year ||
			( $cc_year == $current_year && $cc_month < $current_month ) ||
			$cc_year > $current_year + 10
		) {
			wc_add_notice( __( 'Card expiration date is invalid', 'woocommerce-gateway-paypal-pro' ), 'error' );
			$is_valid = false;
		}

		if ( $this->require_cvn == 'yes' ) {
			if ( empty( $cvn ) || ! ctype_digit( $cvn ) || strlen( $cvn ) < 3 || strlen( $cvn ) > 4 ) {
				wc_add_notice( __( 'Card security code is invalid', 'woocommerce-gateway-paypal-pro' ), 'error' );
				$is_valid = false;
			}
		}

		if ( $this->require_address == 'yes' ) {
			if ( ! $address || ! is_string( $address ) ) {
				wc_add_notice( __( 'Address is invalid', 'woocommerce-gateway-paypal-pro' ), 'error' );
				$is_valid = false;
			}

			if ( ! $city || ! is_string( $city ) ) {
				wc_add_notice( __( 'City is invalid', 'woocommerce-gateway-paypal-pro' ), 'error' );
				$is_valid = false;
			}

			// Validate State
			$valid_states = WC()->countries->get_states( $country );
			if ( ! empty( $valid_states ) && is_array( $valid_states ) && count( $valid_states ) > 0 ) {
				$valid_state_values = array_map( 'wc_strtoupper', array_flip( array_map( 'wc_strtoupper', $valid_states ) ) );
				$state = wc_strtoupper( $state );

				if ( isset( $valid_state_values[ $state ] ) ) {
					// With this part we consider state value to be valid as well, convert it to the state key for the valid_states check below.
					$state = $valid_state_values[ $state ];
				}

				if ( ! in_array( $state, $valid_state_values, true ) ) {
					wc_add_notice( sprintf( __( 'State is not valid. Please enter one of the following: %1$s', 'woocommerce-gateway-paypal-pro' ), implode( ', ', $valid_states ) ), 'error' );
					$is_valid = false;
				}
			}

			// Validate Zip
			if ( ! WC_Validation::is_postcode( $zip, $country ) ) {
				wc_add_notice( __( 'postcode / ZIP is not valid.', 'woocommerce-gateway-paypal-pro' ), 'error' );
				$is_valid = false;
			}
		}

		return $is_valid;
	}

	public function validate_card( $number ) {
		$card_types = array(
			'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
			'mastercard' => '/^5[1-5][0-9]{14}$/',
			'amex' => '/^3[47][0-9]{13}$/',
			'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
		);

		if ( preg_match( $card_types[ 'visa' ], $number ) || preg_match( $card_types[ 'mastercard' ], $number ) || preg_match( $card_types[ 'amex' ], $number ) || preg_match( $card_types[ 'discover' ], $number ) ) {
			return true;
		
		} else {
			return false;
		}
	}


	public function payment_fields() {
		if ( $this->solution_type == '0' ) {
			require_once( plugin_dir_path( __FILE__ ) . 'templates/direct.php' );
		} else {
			return ;
		}
	}

	public function receipt_page( $order_id ) {
		if ( $this->solution_type == '1' ) {
			require_once( plugin_dir_path( __FILE__ ) . 'templates/hosted.php' );
		}
	}

	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		wp_enqueue_script('woocommerce_ppp_admin', plugin_dir_url(__FILE__) . '/assets/js/admin.js');
	}

	public function get_transaction_details( $trans_id ) {
		$params = array(
			'TRANSACTIONID' => $trans_id
		);
		return $this->doQuery( 'GetTransactionDetails', $params );
	}

	public function confirm_hosted_payment( $transaction_details, $order ) {
		$transaction_details = $this->decode_array( $transaction_details );
		$result = false;
		$order_total = number_format( $order->get_total(), 2, '.', '' );
		if ( isset( $transaction_details[ 'AMT' ] ) && ( $order_total == $transaction_details[ 'AMT' ] ) ) {
			$result = true;
		}

		return $result;
	}

	private function decode_array( $data ) {
		$result = array();
		if ( count( $data ) > 0 ) {
			foreach ( $data as $key => $value ) {
				$result[ $key ] = urldecode( $value );
			}
		}
		return $result;
	}

	public function ppp_response() {

		$this->log->add( $this->id, 'Response from PayPal: ' . print_r( wc_clean($_REQUEST), true ) );

		$req = 'cmd=_notify-validate';
		foreach ($_POST as $key => $value) {
			$value = urlencode(stripslashes($value));
			$req .= "&$key=$value";
		}
		// post back to PayPal system to validate
		$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
		$header .= "Host: www.paypal.com\r\n";
		$header .= "Connection: close\r\n\r\n";
		$fp = fsockopen('ssl://www.paypal.com', 443, $errno, $errstr, 30);
		if (!$fp) {
			// HTTP ERROR
			$this->log->add( $this->id, 'An error happens on attempt to open a connection to ssl://www.paypal.com:443 ' . date('Y-m-d H:i:s'));

		} else {
			fputs($fp, $header . $req);
			while (!feof($fp)) {
				$res = fgets($fp, 1024);
				$this->log->add( $this->id, 'Socket successfully opened. Status = ' . trim($res) . ' on ' . date('Y-m-d H:i:s'));

			}
			fclose($fp);
		}

		$order_id = (int)$_REQUEST['id_order'];
		$order = wc_get_order( $order_id );

		if ( isset( $_REQUEST[ 'txn_id' ] ) ) {
			$transaction_id = wc_clean($_REQUEST[ 'txn_id' ]);
		} else {
			$transaction_id = wc_clean($_REQUEST[ 'tx' ]);
		}
		$transaction_details = $this->get_transaction_details( $transaction_id );
		$is_payment_valid = $this->confirm_hosted_payment( $transaction_details, $order );

		if ( $is_payment_valid == false ) {
			// Logging and sending info about error to customer
			$this->log->add($this->id, 'A fraudulent order was tried to be made from Paypal | is_payment_valid == false | on ' . date('Y-m-d H:i:s') . ' | id_cart = ' . $order->get_id() );
			$this->log->add($this->id, 'redirect to home');
			wp_redirect( home_url( '/' ) );
			exit();
		}
		if ( $is_payment_valid && ( $transaction_details[ 'ACK' ] == 'Success' ) ) {

			$this->log->add($this->id, 'Order ID: ' . $order_id. '| ACK Success, order validation final process');

			$order->payment_complete();
			WC()->cart->empty_cart();

			$note = ( $this->transaction_type == 'AUTH_ONLY' ? 'Authorization Only - ' : '' ) . 'Transaction ID: ' . $transaction_details[ 'TRANSACTIONID' ];
			if ( $transaction_details[ 'PAYMENTSTATUS' ] == 'Pending' ) {
				$note .= ' | Payment Status: Pending - Reason: ' . $transaction_details[ 'PENDINGREASON' ];
			}
			$order->add_order_note( $note );
			update_post_meta( $order_id, '_id_trans', $transaction_details[ 'TRANSACTIONID' ] );
			update_post_meta( $order_id, '_card', '0000' );
			if( isset( $transaction_details[ 'AuthorizationID' ] ) ) {
				update_post_meta( $order_id, '_auth_code', $transaction_details[ 'AuthorizationID' ] );
			}

			if ( $this->transaction_type == 'AUTH_ONLY' ) {
				$captured = '0';
			} else {
				$captured = '1';
			}
			update_post_meta( $order_id, '_captured', $captured );

			if ( $captured ) {
				update_post_meta( $order_id, '_captured_amount', $order->get_total() );
			}
		} else {
			$this->log->add($this->id, 'A fraudulent order was tried to be made from Paypal.');
			$order->update_status( 'failed' );
		}

		wp_redirect( $this->get_return_url( $order ) );
		exit;
	}

	public function init_form_fields(){

		$this->form_fields = array(
			'account_email' => array(
				'title' => __( 'PayPal Account email', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'text',
				'default' => ''
			),
			'api_username' => array(
				'title' => __( 'PayPal API Username', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'text',
				'default' => ''
			),
			'api_password' => array(
				'title' => __( 'PayPal API Password', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'text',
				'default' => ''
			),
			'api_signature' => array(
				'title' => __( 'PayPal API Signature', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'text',
				'default' => ''
			),
			'enable_sandbox' => array(
				'title' => __( 'Enable SandBox Mode', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'select',
				'options' => array(
					'1' => __( 'Yes', 'woocommerce-gateway-paypal-pro' ),
					'0' => __( 'No', 'woocommerce-gateway-paypal-pro' )
				)
			),
			'solution_type' => array(
				'title' => __( 'PayPal Pro Solution type', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'select',
				'options' => array(
					'0' => __( 'Direct Payment', 'woocommerce-gateway-paypal-pro' ),
					'1' => __( 'Hosted Solution', 'woocommerce-gateway-paypal-pro' )
				),
				'description' => __( 'Hosted Solution is not available for US customer, but is available for UK, France, Australia, Honk Kong, Italy, Spain, Japan, Singapore users', 'woocommerce-gateway-paypal-pro' )
			),
			'transaction_type' => array(
				'title' => __( 'Transaction type', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'select',
				'options' => array(
					'AUTH_CAPTURE' => __( 'Authorize and Capture', 'woocommerce-gateway-paypal-pro' ),
					'AUTH_ONLY' => __( 'Authorize Only', 'woocommerce-gateway-paypal-pro' )
				)
			),
			'enable_3d_secure' => array(
				'title' => __( 'Enable 3D Secure (UK only)', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'select',
				'options' => array(
					'1' => __( 'Yes', 'woocommerce-gateway-paypal-pro' ),
					'0' => __( 'No', 'woocommerce-gateway-paypal-pro' )
				),
				'description' => __( 'For more information please visit', 'woocommerce-gateway-paypal-pro' ) . ' <a href="http://www.paypal-business.co.uk/3Dsecure.asp" target="_blank">PayPal</a> ' . __( 'and', 'woocommerce-gateway-paypal-pro' ) . ' <a href="http://www.cardinalcommerce.com/" target="_blank">Cardinal Commerce</a>',
				'class' => 'direct-payment-option'
			),
			'enable_3d_secure_sandbox' => array(
				'title' => __( 'Enable 3D Secure SandBox Mode', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'select',
				'options' => array(
					'1' => __( 'Yes', 'woocommerce-gateway-paypal-pro' ),
					'0' => __( 'No', 'woocommerce-gateway-paypal-pro' )
				),
				'class' => 'direct-payment-option'
			),
			'centinel_merchant_id' => array(
				'title' => __( 'Centinel Merchant ID', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'text',
				'default' => '',
				'class' => 'direct-payment-option'
			),
			'centinel_processor_id' => array(
				'title' => __( 'Centinel Processor ID', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'text',
				'default' => '',
				'class' => 'direct-payment-option'
			),
			'centinel_transaction_password' => array(
				'title' => __( 'Centinel Transaction Password', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'text',
				'default' => '',
				'class' => 'direct-payment-option'
			),
			'pay_button_text_color' => array(
				'title' => __( 'Pay Button Text Color', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'color',
				'default' => '',
				'class' => 'hosted-solution-option'
			),
			'pay_button_background_color' => array(
				'title' => __( 'Pay Button Background Color', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'color',
				'default' => '',
				'class' => 'hosted-solution-option'
			),
			// 'paypal_account_settings' => array(
			// 	'title' => __( 'PayPay Account Settings', 'woocommerce-gateway-paypal-pro' ),
			// 	'description' => __( 'On PayPal.com set: Profile -> Website payment settings -> Website Payments Pro -> Settings -> Payment Confirmation Page Where would you like to display the payment confirmation message? Select -> On my site\'s confirmation page. Unter the following URL', 'woocommerce-gateway-paypal-pro' ) . '<br><br>' . __( 'Profile -> Website Payments Standard and Express Checkout -> Preferences -> Auto Return for Website Payments Auto Return: SET TO ON, Return URL' ),
			// 	'type' => 'html',
			// 	'class' => 'hosted-solution-option'
			// ),
			'accpeted_cards' => array(
				'title' => __( 'Accepted Cards', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'cardsettings'
			),
			'require_address' => array(
				'title' => __( 'Require Address', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'checkbox',
				'default' => 'no',
				'label' => __( 'User must enter an address (Their billing info will be entered by default)', 'woocommerce-gateway-paypal-pro' ),
				'class' => 'direct-payment-option'
			),
			'require_cvn' => array(
				'title' => __( 'Require CVN', 'woocommerce-gateway-paypal-pro' ),
				'type' => 'checkbox',
				'default' => 'no',
				'label' => __( 'User must enter the 3-4 digit code from the back of the card.', 'woocommerce-gateway-paypal-pro' ),
				'class' => 'direct-payment-option'
			),
		);
	}

	public function generate_cardsettings_html( $key, $data ){

		$ppa_visa = get_option('ppa_visa');
		$ppa_mc = get_option('ppa_mc');
		$ppa_amex = get_option('ppa_amex');
		$ppa_discover = get_option('ppa_discover');

		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>

					<input type="checkbox" value="1" id="ppa_visa" name="ppp_visa" <?php if ( $ppa_visa == 1 ) echo 'checked'; ?>>
					<img src="<?php echo POCOPPP_PLUGIN_URL; ?>/assets/images/visa.gif" style="vertical-align: middle;">
					&nbsp;&nbsp;
					<input type="checkbox" value="1" id="ppa_mc" name="ppp_mc" <?php if ( $ppa_mc == 1 ) echo 'checked'; ?>>
					<img src="<?php echo POCOPPP_PLUGIN_URL; ?>/assets/images/mc.gif" style="vertical-align: middle;">
					&nbsp;&nbsp;
					<input type="checkbox" value="1" id="ppa_amex" name="ppp_amex"<?php if ( $ppa_amex == 1 ) echo 'checked'; ?>>
					<img src="<?php echo POCOPPP_PLUGIN_URL; ?>/assets/images/amex.gif" style="vertical-align: middle;">
					&nbsp;&nbsp;
					<input type="checkbox" value="1" id="ppa_discover" name="ppp_discover"<?php if ( $ppa_discover == 1 ) echo 'checked'; ?>>
					<img src="<?php echo POCOPPP_PLUGIN_URL; ?>/assets/images/discover.gif" style="vertical-align: middle;">
					&nbsp;&nbsp;<p>(<?php echo __( 'For payment logo display only', 'woocommerce-gateway-paypal-pro' ); ?>)</p>


					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	public function generate_html_html( $key, $data ){

		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'class'             => '',
			'css'               => '',
			'description'       => '',
			'desc_tip'          => false,
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset class="<?php echo esc_attr( $data['class'] ); ?>">
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	public function process_admin_options() {

		update_option( 'account_email', sanitize_email($_POST['woocommerce_poco_paypal_pro_account_email'] ));
		update_option( 'api_username', wc_clean($_POST['woocommerce_poco_paypal_pro_api_username'] ));
		update_option( 'api_password', wc_clean($_POST['woocommerce_poco_paypal_pro_api_password'] ));
		update_option( 'api_signature', wc_clean($_POST['woocommerce_poco_paypal_pro_api_signature'] ));
		update_option( 'enable_sandbox', wc_clean($_POST['woocommerce_poco_paypal_pro_enable_sandbox'] ));
		update_option( 'solution_type', wc_clean($_POST['woocommerce_poco_paypal_pro_solution_type'] ));
		update_option( 'transaction_type', wc_clean($_POST['woocommerce_poco_paypal_pro_transaction_type'] ));
		update_option( 'enable_3d_secure', wc_clean($_POST['woocommerce_poco_paypal_pro_enable_3d_secure'] ));
		update_option( 'enable_3d_secure_sandbox', wc_clean($_POST['woocommerce_poco_paypal_pro_enable_3d_secure_sandbox'] ));
		update_option( 'centinel_merchant_id', wc_clean($_POST['woocommerce_poco_paypal_pro_centinel_merchant_id'] ));
		update_option( 'centinel_transaction_password', wc_clean($_POST['woocommerce_poco_paypal_pro_centinel_transaction_password'] ));
		update_option( 'pay_button_text_color', wc_clean($_POST['woocommerce_poco_paypal_pro_pay_button_text_color'] ));
		update_option( 'pay_button_background_color', wc_clean($_POST['woocommerce_poco_paypal_pro_pay_button_background_color'] ));

		if ( isset( $_POST['woocommerce_poco_paypal_pro_require_address'] ) && $_POST['woocommerce_poco_paypal_pro_require_address'] ) {
			update_option( 'require_address', 1 );
		} else {
			update_option( 'require_address', 0 );
		}

		if ( isset( $_POST['woocommerce_poco_paypal_pro_require_cvn'] ) && $_POST['woocommerce_poco_paypal_pro_require_cvn'] ) {
			update_option( 'require_cvn', 1 );
		} else {
			update_option( 'require_cvn', 0 );
		}

		if ( isset( $_POST[ 'ppp_visa' ] ) ) {
			update_option( 'ppp_visa', 1 );
		} else {
			update_option( 'ppp_visa', 0 );
		}

		if ( isset( $_POST[ 'ppp_mc' ] ) ) {
			update_option( 'ppp_mc', 1 );
		} else {
			update_option( 'ppp_mc', 0 );
		}

		if ( isset( $_POST[ 'ppp_amex' ] ) ) {
			update_option( 'ppp_amex', 1 );
		} else {
			update_option( 'ppp_amex', 0 );
		}

		if ( isset( $_POST[ 'ppp_discover' ] ) ) {
			update_option( 'ppp_discover', 1 );
		} else {
			update_option( 'ppp_discover', 0 );
		}

		$this->create_combo( get_option( 'ppp_visa' ), get_option( 'ppp_mc' ), get_option( 'ppp_amex' ), get_option( 'ppp_discover' ) );

		parent::process_admin_options();
	}

	public static function getPostValue($key, $default_value = false)
	{
		if (!isset($key) || empty($key) || !is_string($key)) {
			return false;
		}

		$ret = isset($_POST[$key]) ? wc_clean($_POST[$key]) : $default_value;

		return $ret;
	}

	public function process_payment( $order_id ) {
		if ( $this->solution_type == '1' ) {
			$order = new WC_Order( $order_id );

			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);
		}


		$confirm = self::getPostValue('confirm');

		if ( $confirm || self::getPostValue( 'authenticationResponse' ) ) {
			// Paypal Pro functionality with Centinel 3D Secure validation

			$cen_error = '';
			$centinel_info = array();
			//--- 3D secure authentication response execution
			if ( self::getPostValue( 'authenticationResponse' ) ) {
				// getting the response from the redirected form
				// (the user already input his password to 3D Secure
				// and now we are getting the response of it here before anything else
				$card_enrolled = true;
				$auth_response = $this->doCentinelAuth( wc_clean($_POST['PaRes']), wc_clean($_POST[ 'transactionId' ]), wc_clean($_POST['MD']) );
				if ( ( $auth_response[ 'paresStatus' ] == 'Y' && $auth_response[ 'signatureVerification' ] == 'Y' && $auth_response[ 'errorno' ] == 0)
					|| ( $auth_response[ 'paresStatus' ] == 'A' && $auth_response[ 'signatureVerification' ] == 'Y') && $auth_response[ 'errorno' ] == 0) {
					$cardVerified = true;
				} else {
					// something went wrong
					$isError = true;
					$isError = true;
					wc_add_notice( __( 'Card was not authorised with 3D Secure.', 'woocommerce-gateway-paypal-pro' ), 'error' );
					$this->log->add( $this->id, 'centinel auth error number | description', $auth_response[ 'errorno' ] . ' | ' . $auth_response[ 'errordesc' ] );
				}
				// prepare information for PayPal payment call
				$centinel_info[ 'paresStatus' ] = $auth_response[ 'paresStatus' ];
				$centinel_info[ 'enrolled' ] = 'Y';
				$centinel_info[ 'cavv' ] = $auth_response[ 'cavv' ];
				$centinel_info[ 'xid' ] = $auth_response[ 'response' ][ 'Xid' ];
				$centinel_info[ 'eciflag' ] = $auth_response[ 'eciflag' ];
			} else {
				// if no call was sent to 3D Secure servers, we start from the begining
				$cardVerified = false;
				$card_enrolled = false;
			}
			//--- end of authentication response

			if ( $this->enable_3d_secure == true && ! $card_enrolled ) {
				$lookup_response = $this->doCentinelLookup( $order_id );
				if ( $lookup_response[ 'errorno' ] == 0 ) {
					// no errors, check if card is enrolled in 3D Secure program
					if ( $lookup_response[ 'enrolled' ] == 'Y' ) {
						$this->log->add( $this->id, 'lookupresponse: ' . print_r( $lookup_response, true ) );
						// card is in program, redirect for authentication
						// $cookie->centinel_acs_url = $lookup_response['acsurl'];
						// $cookie->centinel_payload = $lookup_response['payload'];
						// $cookie->centinel_orderId = $lookup_response['orderId'];
						// $cookie->transactionId = $lookup_response['transactionId'];

						$card_enrolled = true;
						$auth_response = $this->doCentinelAuth( wc_clean($_POST['PaRes']), $lookup_response[ 'transactionId' ], wc_clean($_POST['MD']) );
						if ( ( $auth_response[ 'paresStatus' ] == 'Y' && $auth_response[ 'signatureVerification' ] == 'Y' && $auth_response[ 'errorno' ] == 0)
							|| ( $auth_response[ 'paresStatus' ] == 'A' && $auth_response[ 'signatureVerification' ] == 'Y') && $auth_response[ 'errorno' ] == 0) {
							$cardVerified = true;
						} else {
							// something went wrong
							$isError = true;
							$isError = true;
							wc_add_notice( __( 'Card was not authorised with 3D Secure.', 'woocommerce-gateway-paypal-pro' ), 'error' );
							$this->log->add( $this->id, 'centinel auth error number | description', $auth_response[ 'errorno' ] . ' | ' . $auth_response[ 'errordesc' ] );
						}
						// prepare information for PayPal payment call
						$centinel_info[ 'paresStatus' ] = $auth_response[ 'paresStatus' ];
						$centinel_info[ 'enrolled' ] = 'Y';
						$centinel_info[ 'cavv' ] = $auth_response[ 'cavv' ];
						$centinel_info[ 'xid' ] = $auth_response[ 'response' ][ 'Xid' ];
						$centinel_info[ 'eciflag' ] = $auth_response[ 'eciflag' ];



					} elseif ( $lookup_response[ 'enrolled' ] == 'N' ) {
						$cardVerified = true;
						// prepare information for PayPal payment call
						$centinel_info[ 'paresStatus' ] = '';
						$centinel_info[ 'enrolled' ] = 'N';
						$centinel_info[ 'cavv' ] = '';
						$centinel_info[ 'eciflag' ] = '';
					} elseif ( $lookup_response[ 'enrolled' ] == 'U' ) {
						$isError = true;
						wc_add_notice( __('Either your card is not allowed to be prepaid or the bank is unavailable to authorise you.', 'woocommerce-gateway-paypal-pro'), 'error' );
						return;
					} else {
						$isError = true;
						wc_add_notice( __('The connection to authentication servers timed out.', 'woocommerce-gateway-paypal-pro'), 'error' );
						return;
					}
				} else {
					// something went wrong
					$isError = true;
					
					$this->log->add( $this->id, 'centinel lookup error number | description: ' . $lookup_response['errorno'] . ' | ' . $lookup_response['errordesc'] );
					
					wc_add_notice( __( 'Card was not authorised with 3D Secure.', 'woocommerce-gateway-paypal-pro' ), 'error' );
					return;
				}
			}
			$pp_error = '';
			if ( ( $this->enable_3d_secure && $cardVerified ) || !$this->enable_3d_secure ) {
				$response_array = $this->doPayment( $centinel_info, $order_id );
				if ( ( $response_array[ 'ACK' ] == 'Success' ) || ( $response_array[ 'ACK' ] == 'SuccessWithWarning' ) ) {
					$order = new WC_Order( $order_id );
					$order->payment_complete();
					WC()->cart->empty_cart();

					$auth_only = '';
					if ( $this->transaction_type == 'AUTH_ONLY' ) {
						$auth_only = 'Authorization Only - ';
					}
					$order->add_order_note( sprintf( __( '%s Transaction ID: %s  - Last 4 digits of the card: %s  - AVS Response: %s - Card Code Response: %s', 'woocommerce-gateway-paypal-pro' ), $auth_only, $response_array[ 'TRANSACTIONID' ], substr( self::getPostValue( 'pppro_cc_number' ), -4 ), $response_array[ 'AVSCODE' ], $response_array[ 'CVV2MATCH' ] ) );

					update_post_meta( $order_id, '_id_trans', $response_array[ 'TRANSACTIONID' ] );
					update_post_meta( $order_id, '_card', substr( self::getPostValue( 'pppro_cc_number' ), -4 ) );
					update_post_meta( $order_id, '_auth_code', $response_array[ 'AuthorizationID' ] );

					if ( $this->transaction_type == 'AUTH_ONLY' ) {
						$captured = '0';
					} else {
						$captured = '1';
					}
					update_post_meta( $order_id, '_captured', $captured );

					if ( $captured ) {
						update_post_meta( $order_id, '_captured_amount', $order->get_total() );
					}

					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				} else {
					$order = new WC_Order( $order_id );
					$pp_error = urldecode( isset( $response_array[ 'L_LONGMESSAGE0' ] ) ? $response_array[ 'L_LONGMESSAGE0' ] : $response_array[ 'L_SHORTMESSAGE0' ] );

					$order->update_status( 'failed' );
					$order->add_order_note( sprintf( __( 'Error Message: %s', 'woocommerce-gateway-paypal-pro' ), $pp_error ) );

					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				}
			}
		}

		wc_add_notice( __('Payment error:', 'woocommerce-gateway-paypal-pro') . print_r(wc_clean($_POST), true), 'error' );
		return;
	}

	/**
     * Check if the card is within 3D Secure and pass all the necessary
     * information to Cardinal services
     *
     * @global type $_POST
     * @global type $_GET
     * @return type
     */
    public function doCentinelLookup( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		require_once( plugin_dir_path( __FILE__ ) . 'centinel/CentinelErrors.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'centinel/CentinelClient.php' );
		require_once( plugin_dir_path( __FILE__ ) . 'centinel/CentinelUtility.php' );

		$centinel_client = new POCOPPPCentinelClient;

		$centinel_client->add( 'MsgType', 'cmpi_lookup' );
		$centinel_client->add( 'Version', $this->ppp_3d_msg_v );
		$centinel_client->add( 'ProcessorId', $this->centinel_processor_id );
		$centinel_client->add( 'MerchantId', $this->centinel_merchant_id );
		$centinel_client->add( 'TransactionPwd', $this->centinel_transaction_password );
		$centinel_client->add( 'UserAgent', $_SERVER[ 'HTTP_USER_AGENT' ] );
		$centinel_client->add( 'BrowserHeader', $_SERVER[ 'HTTP_ACCEPT' ] );
		$centinel_client->add( 'IPAddress', $_SERVER['REMOTE_ADDR'] );

		$centinel_client->add( 'OrderNumber', $order_id );
		$centinel_client->add( 'Amount', number_format( $order->get_total(), 2, '', '' ) );
		$centinel_client->add( 'CurrencyCode', $this->get_currency_digit_code( get_option('woocommerce_currency') ) );

		$centinel_client->add( 'TransactionMode', urlencode( 'S' ) );   // meaning e-commerce solution
		$centinel_client->add( 'TransactionType', 'C' ); // meaning credit or debit card
		// Payer Authentication specific fields
		$centinel_client->add( 'CardNumber', trim( self::getPostValue( 'pppro_cc_number' ) ) );
		$exp_date_month = self::getPostValue( 'pppro_cc_Month' );
		// Month must be padded with leading zero
		$pad_date_month = urlencode( str_pad( $exp_date_month, 2, '0', STR_PAD_LEFT ) );
		$centinel_client->add( 'CardExpMonth', $pad_date_month );
		$centinel_client->add( 'CardExpYear', self::getPostValue( 'pppro_cc_Year' ) );

		// adding products:
		$i = 1;
		foreach ( $order->get_items() as $item_id => $item_data ) {
			$product = $item_data->get_product();
			$centinel_client->add( 'Item_Name_' . $i, $product->get_name() );
			$centinel_client->add( 'Item_Price_' . $i, number_format( $product->get_price(), 2, '', '' ) );
			$centinel_client->add( 'Item_Quantity_' . $i, $item_data->get_quantity() );
			$centinel_client->add( 'Item_Desc_' . $i, substr( $product->get_description(), 0, 256 ) );
			$i++;
		}

		// DEBUG - also have in mind that you cannot test the whole process
		// of transaction from Cardinal to Paypal. You can only test either
		// Cardinal or Paypal.
		if ( $this->enable_3d_secure_sandbox == '1' ) {
			$centinel_client->sendHttp('https://centineltest.cardinalcommerce.com/maps/txns.asp', $this->ppp_3d_timeout_cancel, $this->ppp_3d_timeout_read);
		} else {
			$centinel_client->sendHttp('https://paypal.cardinalcommerce.com/maps/txns.asp', $this->ppp_3d_timeout_cancel, $this->ppp_3d_timeout_read);
		}

		$response = array(
			'response' => $centinel_client->response,
			'enrolled' => $centinel_client->getValue( 'Enrolled' ),
			'transactionId' => $centinel_client->getValue( 'TransactionId' ),
			'orderId' => $centinel_client->getValue( 'OrderId' ),
			'acsurl' => $centinel_client->getValue( 'ACSUrl' ),
			'payload' => $centinel_client->getValue( 'Payload' ),
			'errorno' => $centinel_client->getValue( 'ErrorNo' ),
			'errordesc' => $centinel_client->getValue( 'ErrorDesc' )
		);

		return $response;
	}

	public function doCentinelAuth( $payload, $transactionId, $centinelOrderId )
	{
		if (empty($payload) || empty($transactionId)) {
			return false;
		}

		require_once( plugin_dir_path( __FILE__ ) . 'centinel/CentinelErrors.php');
		require_once( plugin_dir_path( __FILE__ ) . 'centinel/CentinelClient.php');
		require_once( plugin_dir_path( __FILE__ ) . 'centinel/CentinelUtility.php');

		$centinel_client = new POCOPPPCentinelClient;

		$centinel_client->add( 'MsgType', 'cmpi_authenticate' );
		$centinel_client->add( 'Version', $this->ppp_3d_msg_v );
		$centinel_client->add( 'ProcessorId', $this->centinel_processor_id );
		$centinel_client->add( 'MerchantId', $this->centinel_merchant_id );
		$centinel_client->add( 'TransactionPwd', $this->centinel_transaction_password );
		$centinel_client->add( 'TransactionType', 'C' ); // meaning credit or debit card
		$centinel_client->add( 'TransactionId', $transactionId );
		$centinel_client->add( 'OrderId', $centinelOrderId );
		$centinel_client->add( 'PAResPayload', $payload );

		//$centinel_client->sendHttp('https://centineltest.cardinalcommerce.com/maps/txns.asp', $this->_pppro_3d_timeout_cancel, $this->_pppro_3d_timeout_read);
		$centinel_client->sendHttp('https://paypal.cardinalcommerce.com/maps/txns.asp', $this->ppp_3d_timeout_cancel, $this->ppp_3d_timeout_read);


		$response = array(
			'response' => $centinel_client->response,
			'paresStatus' => $centinel_client->getValue( 'PAResStatus' ),
			'cavv' => $centinel_client->getValue( 'Cavv' ),
			'eciflag' => $centinel_client->getValue( 'EciFlag' ),
			'signatureVerification' => $centinel_client->getValue( 'SignatureVerification' ),
			'errorno' => $centinel_client->getValue( 'ErrorNo' ),
			'errordesc' => $centinel_client->getValue( 'ErrorDesc' )
		);

		return $response;
	}


	public function doPayment( $centinel_info = array(), $order_id ) {
		$order = new WC_Order( $order_id );

		$request = array();
		$card_number = self::getPostValue( 'pppro_cc_number' );
		// the card might be either MasterCard or Maestro
		// checking if it is Maestro card
		if ( $this->checkMaestroCard( $card_number ) ) {
			$card_type = 'Maestro';
		} else {
			$first_card_number = substr( $card_number, 0, 1 );
			switch ( $first_card_number ) {
				case '4':
					$card_type = 'Visa';
					break;
				case '3':
					$card_type = 'Amex';
					break;
				case '5':
				case '2': // new MC BINs range where first 6 number are 222100 to 272099
					$card_type = 'MasterCard';
					break;
				case '6':
					$card_type = 'Discover';
					break;
				default:
					$card_type = '';
			}
		}

		// if Maestro card detected
		// check the currency as it must be GBP
		// and add additional info to the query
		// if ( $card_type == 'Maestro' ) {
		// 	if ($cookie->maestro_issue_number) {
		// 		// if it is only one number, we add zero to the left
		// 		$maestroIssueNumber = $cookie->maestro_issue_number;
		// 		$request .= "&ISSUENUMBER=" . urlencode($maestroIssueNumber);
		// 	} else {
		// 		$maestroStartDate = $cookie->maestro_Month . $cookie->maestro_Year;
		// 		$request .= "&STARTDATE=" . urlencode($maestroStartDate);
		// 	}
		// }

		$amount = number_format( $order->get_total(), 2, '.', '' );

		// Only send product list if there are no discounts.
		$amt = 0;
		$i = 1;
		foreach ( $order->get_items() as $item_id => $item_data ) {
			$product = $item_data->get_product();
			$request['L_NAME' . $i] = substr( urlencode( $product->get_name() ), 0, 127 );
			$request['L_AMT' . $i] = urlencode( number_format( $product->get_price(), 2, '.', '' ) );
			$request['L_QTY' . $i] = urlencode( $item_data->get_quantity() );
			$amt += number_format( $product->get_price(), 2, '.', '' ) * $item_data->get_quantity();
			$i++;
		}

		$shipping = number_format( $order->get_shipping_total(), 2, '.', '' ); // gets only shipping.
		$request['ITEMAMT'] = urlencode( $amt );
		$request['SHIPPINGAMT'] = urlencode( $shipping );
		$request['TAXAMT'] = urlencode( number_format( $order->get_total_tax(), 2, '.', '' ) );

		// --------------------------------------------------------------------------------
		// Set request-specific fields.
		if ( $this->transaction_type == 'AUTH_ONLY' ) {
			$paymentType = urlencode( 'Authorization' );
		} elseif ( $this->transaction_type == 'AUTH_CAPTURE' ) {
			$paymentType = urlencode('Sale');
		}
		$deliveryFirstName = urlencode( $order->get_shipping_first_name() );
		$deliveryLastName = urlencode( $order->get_shipping_last_name() );

		$firstName = urlencode( ( self::getPostValue( 'pppro_cc_fname' ) ? self::getPostValue( 'pppro_cc_fname' ) : $order->get_billing_first_name() ) );
		$lastName = urlencode( ( self::getPostValue( 'pppro_cc_lname' ) ? self::getPostValue( 'pppro_cc_lname' ) : $order->get_billing_last_name() ) );

		$credit_card_type = urlencode( $card_type );

		$credit_card_number = urlencode( $card_number );
		$exp_date_month = self::getPostValue( 'pppro_cc_Month' );
		// Month must be padded with leading zero
		$pad_date_month = urlencode( str_pad( $exp_date_month, 2, '0', STR_PAD_LEFT ) );

		$exp_date_year = urlencode( self::getPostValue( 'pppro_cc_Year' ) );
		$cvv2Number = urlencode( self::getPostValue( 'pppro_cc_cvm' ) );
		$address1 = urlencode( self::getPostValue( 'pppro_cc_address' ) ? self::getPostValue( 'pppro_cc_address' ) : $order->get_billing_address_1() );
		$deliveryAddress1 = urlencode( $order->get_shipping_address_1() );

		$deliveryAddress2 = urlencode( $order->get_shipping_address_2() );
		$address2 = urlencode( $order->get_billing_address_2() );

		$city = urlencode( self::getPostValue( 'pppro_cc_city' ) ? self::getPostValue( 'pppro_cc_city' ) : $order->get_billing_city() );
		$deliveryCity = urlencode( $order->get_shipping_city() );

		$state = ( self::getPostValue( 'pppro_state' ) ? self::getPostValue( 'pppro_state' ) : $order->get_billing_state() );
		$deliveryState = $order->get_shipping_state();

		$deliveryCountry = urlencode( $order->get_shipping_country() );

		$zip = urlencode( self::getPostValue( 'pppro_cc_zip' ) ? self::getPostValue( 'pppro_cc_zip' ) : $order->get_billing_postcode() );
		$deliveryZip = urlencode( $order->get_shipping_postcode() );

		$country = ( self::getPostValue( 'pppro_country' ) ? self::getPostValue( 'pppro_country' ) : $order->get_billing_country() );

		$amount = urlencode( $amount );
		$currency = $order->get_currency();

		$customer_email = urlencode( $order->get_billing_email() );
		// --------------------------------------------------------------------------------
		// Add request-specific fields to the request string.

		$params = array(
			'PAYMENTACTION' => $paymentType,
			'AMT' => $amount,
			'CREDITCARDTYPE' => $credit_card_type,
			'ACCT' => $credit_card_number,
			'EXPDATE' => $pad_date_month . $exp_date_year,
			'CVV2' => $cvv2Number,
			'FIRSTNAME' => $firstName,
			'LASTNAME' => $lastName,
			'STREET' => $address1 . ' ' . $address2,
			'CITY' => $city,
			'STATE' => $state,
			'ZIP' => $zip,
			'COUNTRYCODE' => $country,
			'CURRENCYCODE' => $currency,
			'BUTTONSOURCE' => 'PrestoChangeo_SP',
			'SHIPTONAME' => $deliveryFirstName . ' ' . $deliveryLastName,
			'SHIPTOSTREET' => $deliveryAddress1 . ' ' . $deliveryAddress2,
			'SHIPTOCITY' => $deliveryCity,
			'SHIPTOSTATE' => $deliveryState,
			'SHIPTOZIP' => $deliveryZip,
			'SHIPTOCOUNTRY' => $deliveryCountry,
			'EMAIL' => $customer_email
		);

		$params = array_merge($params, $request);

		if ( $this->enable_3d_secure ) {
			// 59.0 version of this requires a little bit different EXPMONTH and
			// EXP YEAR formatting
			$params['EXPMONTH'] = $pad_date_month;
			$params['EXPYEAR'] = $exp_date_year;
		}
		// --------------------------------------------------------------------------------
		return $this->doQuery( 'DoDirectPayment', $params, $centinel_info );
	}

	public function checkMaestroCard( $card_number )
	{
		$maestro_digits = array( '5018', '5020', '5038', '6304', '6759', '6761', '6762', '6763' );
		$first_four_digits = substr( $card_number, 0, 4 );
		if ( in_array( $first_four_digits, $maestro_digits ) ) {
			return true;
		}
		return false;
	}

	private function doQuery($method, $request_info = array(), $centinel_info = array())
	{
		// Set up your API credentials, PayPal end point, and API version.
		$API_UserName = urlencode( $this->api_username );
		$API_Password = urlencode( $this->api_password );
		$API_Signature = urlencode( $this->api_signature );
		$API_Endpoint = "https://api-3t.paypal.com/nvp";
		if ( $this->enable_sandbox == '1' ) {
			$API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
		}
		$version = urlencode( '85.0' );
		if ( $this->enable_3d_secure && ( $method == 'DoDirectPayment' ) ) {
			// Needed for 3D Secure - only works with this version
			$version = urlencode( '59.0' );
		}

		$params = array(
			'METHOD' => $method,
			'VERSION' => $version,
			'PWD' => $API_Password,
			'USER' => $API_UserName,
			'SIGNATURE' => $API_Signature,
		);

		$body = array_merge($params, $request_info);

		$args = array(
			'method'      => 'POST',
			'body'        => $body,
			'user-agent'  => __CLASS__,
			'httpversion' => '1.1',
			'timeout'     => 30,
		);

		$httpResponse = wp_safe_remote_post( $API_Endpoint, $args );

		if ( is_wp_error( $httpResponse ) ) {
			// Translators: placeholder is an error message.
			exit( sprintf( __( 'An error occurred while trying to connect to PayPal: %s', 'woocommerce-gateway-paypal-pro' ), $response->get_error_message() ) );
		}

		parse_str( wp_remote_retrieve_body( $httpResponse ), $httpResponse );

		if (!$httpResponse) {
			// DEBUG
			// exit("$methodName_ failed: ".curl_error($ch).'('.curl_errno($ch).')');
			exit( 'Something went wrong - contact shop owner' );
		}

		if ( ( 0 == sizeof( $httpResponse ) ) || !array_key_exists( 'ACK', $httpResponse ) ) {
			// DEBUG
			//exit("Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.");
			exit( 'Invalid HTTP Response for POST request.' );
		}

		return $httpResponse;
	}

	public function get_returnUrl() {
		return str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'ppp_response', home_url( '/' ) ) );
	}

	private function create_combo( $ppa_visa, $ppa_mc, $ppa_amex, $ppa_discover ) {
		if ( ! $ppa_visa && ! $ppa_mc && ! $ppa_amex && ! $ppa_discover ) {
			return;
		}

		$img_buf = array();
		if ( $ppa_visa ) {
			array_push( $img_buf, imagecreatefromgif( dirname( __FILE__ ) . '/assets/images/visa.gif' ) );
		}
		if ( $ppa_mc ) {
			array_push( $img_buf, imagecreatefromgif( dirname( __FILE__ ) . '/assets/images/mc.gif' ) );
		}
		if ( $ppa_amex ) {
			array_push( $img_buf, imagecreatefromgif( dirname( __FILE__ ) . '/assets/images/amex.gif' ) );
		}
		if ( $ppa_discover ) {
			array_push( $img_buf, imagecreatefromgif( dirname( __FILE__ ) . '/assets/images/discover.gif' ) );
		}

		$i_out = imagecreatetruecolor ( '86', ceil( sizeof( $img_buf ) / 2 ) * 26 );
		$bg_color = imagecolorallocate( $i_out, 255, 255, 255 );
		imagefill( $i_out, 0, 0, $bg_color );
		foreach ( $img_buf as $i => $img ) {
			imagecopy( $i_out, $img, ( $i % 2 == 0 ? 0 : 49 ) - 1, floor( $i / 2 ) * 26 - 1, 0, 0, imagesx( $img ), imagesy( $img ) );
			imagedestroy( $img );
		}
		imagejpeg( $i_out, dirname( __FILE__ ) . '/assets/images/combo.jpg', 100 );
	}

	public function get_currency_digit_code( $code ) {
		$currencies = array(
			'AED' => '784',
			'AFN' => '971',
			'ALL' => '008',
			'AMD' => '051',
			'ANG' => '532',
			'AOA' => '973',
			'ARS' => '032',
			'AUD' => '036',
			'AWG' => '533',
			'AZN' => '944',
			'BAM' => '977',
			'BBD' => '052',
			'BDT' => '050',
			'BGN' => '975',
			'BHD' => '048',
			'BIF' => '108',
			'BMD' => '060',
			'BND' => '096',
			'BOB' => '068',
			'BRL' => '986',
			'BSD' => '044',
			'BTN' => '064',
			'BWP' => '072',
			'BYN' => '933',
			'BZD' => '084',
			'CAD' => '124',
			'CDF' => '976',
			'CHF' => '756',
			'CLP' => '152',
			'CNY' => '156',
			'COP' => '170',
			'CRC' => '188',
			'CUC' => '931',
			'CUP' => '192',
			'CVE' => '132',
			'CZK' => '203',
			'DJF' => '262',
			'DKK' => '208',
			'DKK' => '208',
			'DOP' => '214',
			'DZD' => '012',
			'EGP' => '818',
			'ERN' => '232',
			'ETB' => '230',
			'EUR' => '978',
			'FJD' => '242',
			'FKP' => '238',
			'GBP' => '826',
			'GEL' => '981',
			'GHS' => '936',
			'GIP' => '292',
			'GMD' => '270',
			'GNF' => '324',
			'GTQ' => '320',
			'GYD' => '328',
			'HKD' => '344',
			'HNL' => '340',
			'HRK' => '191',
			'HTG' => '332',
			'HUF' => '348',
			'IDR' => '360',
			'ILS' => '376',
			'INR' => '356',
			'IQD' => '368',
			'IRR' => '364',
			'ISK' => '352',
			'JMD' => '388',
			'JOD' => '400',
			'JPY' => '392',
			'KES' => '404',
			'KGS' => '417',
			'KHR' => '116',
			'KMF' => '174',
			'KPW' => '408',
			'KRW' => '410',
			'KWD' => '414',
			'KYD' => '136',
			'KZT' => '398',
			'LAK' => '418',
			'LBP' => '422',
			'LKR' => '144',
			'LRD' => '430',
			'LSL' => '426',
			'LYD' => '434',
			'MAD' => '504',
			'MDL' => '498',
			'MGA' => '969',
			'MKD' => '807',
			'MMK' => '104',
			'MNT' => '496',
			'MOP' => '446',
			'MUR' => '480',
			'MVR' => '462',
			'MWK' => '454',
			'MXN' => '484',
			'MYR' => '458',
			'MZN' => '943',
			'NAD' => '516',
			'NGN' => '566',
			'NIO' => '558',
			'NOK' => '578',
			'NPR' => '524',
			'NZD' => '554',
			'OMR' => '512',
			'PAB' => '590',
			'PEN' => '604',
			'PGK' => '598',
			'PHP' => '608',
			'PKR' => '586',
			'PLN' => '985',
			'PYG' => '600',
			'QAR' => '634',
			'RON' => '946',
			'RSD' => '941',
			'RUB' => '643',
			'RWF' => '646',
			'SAR' => '682',
			'SBD' => '090',
			'SCR' => '690',
			'SDG' => '938',
			'SEK' => '752',
			'SGD' => '702',
			'SHP' => '654',
			'SLL' => '694',
			'SOS' => '706',
			'SRD' => '968',
			'SSP' => '728',
			'SYP' => '760',
			'SZL' => '748',
			'THB' => '764',
			'TJS' => '972',
			'TMT' => '934',
			'TND' => '788',
			'TOP' => '776',
			'TRY' => '949',
			'TTD' => '780',
			'TWD' => '901',
			'TZS' => '834',
			'UAH' => '980',
			'UGX' => '800',
			'USD' => '840',
			'UYU' => '858',
			'UZS' => '860',
			'VES' => '928',
			'VND' => '704',
			'VUV' => '548',
			'WST' => '882',
			'XAF' => '950',
			'XCD' => '951',
			'XOF' => '952',
			'XPF' => '953',
			'YER' => '886',
			'ZAR' => '710',
			'ZMW' => '967'
		);

		if ( isset( $currencies[ $code ] ) ) {
			return $currencies[ $code ];
		}

		return false;
	}

	public function doCapture( $order_id, $trans_id, $amount ) {
		// Set request-specific fields.
		$authorizationID = urlencode( $trans_id );
		$amount = urlencode($amount);
		$order = new WC_Order( $order_id );
		$currency = $order->get_currency();

		$complete_code_type = urlencode( 'Complete' ); // or 'NotComplete'
		$invoiceID = urlencode( __( 'Order', 'woocommerce-gateway-paypal-pro' ) . ' #' . $order_id );
		// Add request-specific fields to the request string.

		$params = array(
			'AUTHORIZATIONID' => $authorizationID,
			'AMT' => $amount,
			'COMPLETETYPE' => $complete_code_type,
			'CURRENCYCODE' => $currency,
			'INVNUM' => $invoiceID
		);

		return $this->doQuery( 'DoCapture', $params );
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		$is_captured = get_post_meta( $order_id, '_captured', true );
		$transaction_id = get_post_meta( $order_id, '_id_trans', true );
		$result = $this->doRefund( $order_id, !$is_captured, $transaction_id, $amount );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Refund Failed: ' . $result->get_error_message(), 'error' );
			return new WP_Error( 'error', $result->get_error_message() );
		}

		$this->log->log( $this->id, 'Refund Result: ' . wc_print_r( $result, true ) );

		switch ( $result[ 'ACK' ] ) {
			case 'Success':
				if ( isset ( $result[ 'REFUNDTRANSACTIONID'] ) ) {
					$order->add_order_note(
						__( 'Refund ID: ', 'woocommerce' ) . $result[ 'REFUNDTRANSACTIONID']
					);
				}
				return true;
		}

		return isset( $result['L_LONGMESSAGE0'] ) ? new WP_Error( 'error', urldecode( $result['L_LONGMESSAGE0'] ) ) : false;
	}

	public function doRefund( $order_id, $is_void, $trans_id, $amount ) {
		$trans_id = urlencode( $trans_id );
		$amount = urlencode( $amount );

		$trans_details = $this->get_transaction_details( $trans_id );
		if( isset( $trans_details[ 'PAYMENTSTATUS' ] ) && $trans_details[ 'PAYMENTSTATUS' ] == 'Pending' ) {
			$is_void = true;
		}

		if ($is_void) {
			$params = array(
				'AUTHORIZATIONID' => $trans_id
			);
			return $this->doQuery( 'DOVoid', $params );
		}
		$order = new WC_Order( $order_id );
		$order_amount = number_format( $order->get_total(), 2, '.', '' );
		$currency = $order->get_currency();
		$nvpStr_amount = array();
		if ($order_amount == $amount) {
			$refundType = urlencode( 'Full' );
		} else {
			$refundType = urlencode( 'Partial' );
			// $nvpStr_amount = "&AMT=$amount";
			$nvpStr_amount = array(
				'AMT' => $amount
			);
		}
		$params = array(
			'TRANSACTIONID' => $trans_id,
			'REFUNDTYPE' => $refundType,
			'CURRENCYCODE' => $currency
		);
		$params = array_merge($params, $nvpStr_amount);
		return $this->doQuery( 'RefundTransaction', $params );
	}

}