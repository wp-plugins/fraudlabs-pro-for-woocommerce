<?php
/**
 * FraudLabs Pro Integration.
 *
 * @package  WC_Integration_FraudLabs_Pro
 * @category Integration
 * @author   FraudLabs Pro
 */

if ( ! class_exists( 'WC_Integration_FraudLabs_Pro' ) ) :

class WC_Integration_FraudLabs_Pro extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;

		$this->id                 = 'woocommerce-fraudlabs-pro';
		$this->method_title       = __( 'FraudLabs Pro', 'woocommerce-fraudlabs-pro' );
		$this->method_description = __( 'FraudLabs Pro helps you to screen your order transaction, such as credit card transaction, for online fraud. Get a <a href="http://www.fraudlabspro.com/sign-up?r=woocommerce" target="_blank">free API key</a> if you do not have one.', 'woocommerce-fraudlabs-pro' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->enabled				= $this->get_option( 'enabled' );
		$this->api_key				= $this->get_option( 'api_key' );
		$this->risk_score			= $this->get_option( 'risk_score' );
		$this->message				= $this->get_option( 'message' );
		$this->test_ip				= $this->get_option( 'test_ip' );
		$this->store_admin_email	= $this->get_option( 'store_admin_email' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_fraud_report' ) );
		add_action( 'admin_notices', array( $this, 'admin_notifications' ) );
	}


	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'             => __( 'Enabled', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'checkbox',
				'description'       => __( 'Enable or disable FraudLabs Pro.', 'woocommerce-fraudlabs-pro' ),
				'desc_tip'          => TRUE,
				'default'           => 'yes'
			),
			'api_key' => array(
				'title'             => __( 'API Key', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'text',
				'description'       => __( 'API key to access FraudLabs Pro service. Get it from <a href="https://www.fraudlabspro.com" target="_blank">https://www.fraudlabspro.com/?r=woocommerce</a>' ),
				'desc_tip'          => TRUE,
				'default'           => ''
			),
			'risk_score' => array(
				'title'             => __( 'Risk Score', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'text',
				'description'       => __( 'Reject the transaction if the calculated risk score higher than this value.', 'woocommerce-fraudlabs-pro' ),
				'desc_tip'          => TRUE,
				'default'           => '60'
			),
			'message' => array(
				'title'             => __( 'Message', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'text',
				'description'       => __( 'Display this message whenever transaction is marked as fraud.', 'woocommerce-fraudlabs-pro' ),
				'desc_tip'          => TRUE,
				'default'           => 'Thank you for shopping with us. Your order will be reviewed by us shortly.'
			),
			'test_ip' => array(
				'title'             => __( 'Test IP', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'text',
				'description'       => __( 'Simulate visitor IP. Clear this value for production run.', 'woocommerce-fraudlabs-pro' ),
				'desc_tip'          => TRUE,
				'default'           => ''
			),
			'store_admin_email' => array(
				'title'             => __( 'Store Admin Email Address', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'text',
				'description'       => __( 'Inserts your email address to receive fraud check result in your inbox.', 'woocommerce-fraudlabs-pro' ),
				'desc_tip'          => TRUE,
				'default'           => ''
			),
		);

	}


	/**
	 * Notification message.
	 */
	public function admin_notifications() {

		if( ! $this->api_key ) {
			global $woocommerce;

			$settings_url = admin_url( 'admin.php?page=woocommerce_settings&tab=integration&section=woocommerce-fraudlabs-pro' );

			if ( $woocommerce->version >= '2.1' ) {
				$settings_url = admin_url( 'admin.php?page=wc-settings&tab=integration&section=woocommerce-fraudlabs-pro' );
			}

			echo '
			<div id="message" class="error">
				<p>
					' . __( 'FraudLabs Pro setup is not complete. Please go to <a href="' . $settings_url . '">setting page</a> to enter your API key.', 'woocommerce-fraudlabs-pro' ) . '
				</p>
			</div>
			';
		}
	}


	/**
	 * Validate the Risk score
	 * @see validate_settings_fields()
	 */
	public function validate_risk_score_field( $key ) {
		// get the posted value
		$value = $_POST[$this->plugin_id . $this->id . '_' . $key];

		if ( !preg_match( '/^[0-9]{1,2}$/', $value ) ) {
			$this->errors[] = 'Risk score should be number from 1 - 99.';
		}
		return $value;
	}


	/**
	 * Validate the Message
	 * @see validate_settings_fields()
	 */
	public function validate_message_field( $key ) {
		// get the posted value
		$value = $_POST[$this->plugin_id . $this->id . '_' . $key];

		if ( empty( $value ) ) {
			$this->errors[] = 'You must provide a message.';
		}
		return $value;
	}


	/**
	 * Validate the Test IP
	 * @see validate_settings_fields()
	 */
	public function validate_test_ip_field( $key ) {
		// get the posted value
		$value = $_POST[$this->plugin_id . $this->id . '_' . $key];

		if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) )
			$this->errors[] = 'Test IP is not a valid IP address.';

		return $value;
	}

	/**
	 * Validate the Store Admin Email
	 * @see validate_settings_fields()
	 */
	public function validate_store_admin_email_field( $key ) {
		// get the posted value
		$value = $_POST[$this->plugin_id . $this->id . '_' . $key];

		if ( !empty( $value ) && !filter_var( $value,  FILTER_VALIDATE_EMAIL ) )
			$this->errors[] = 'Please insert a valid email address for store admin.';

		return $value;
	}

	/**
	 * Proceess order with FraudLabs Pro API service.
	 */
	public function process_order( $order_id ) {
		if ( ! $this->enabled )
			return;

		$order = new WC_Order( $order_id );
		$items = $order->get_items();

		$customer = new WC_Customer();
		$checkout = new WC_Checkout();

		$qty = 0;
		foreach( $items as $key => $value )
			$qty += $value['qty'];

		switch( $checkout->get_value( 'payment_method' ) ) {
			case 'bacs':
				$paymentMode = 'bankdeposit';
				break;

			case 'paypal':
				$paymentMode = 'paypal';
				break;

			default:
				$paymentMode = 'others';
		}

		$client_ip = $_SERVER['REMOTE_ADDR'];

		if( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && filter_var( $_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP ) )
			$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];

		$url = 'https://api.fraudlabspro.com/v1/order/screen?' . http_build_query( array(
			'key'			=> $this->api_key ,
			'format'		=> 'json',
			'ip'			=> ( filter_var( $this->test_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) ? $this->test_ip : $client_ip,
			'bill_city'		=> $customer->get_city(),
			'bill_state'	=> $customer->get_state(),
			'bill_zip_code'	=> $customer->get_postcode(),
			'bill_country'	=> $customer->get_country(),
			'ship_addr'		=> trim( $customer->get_shipping_address() . ' ' . $customer->get_shipping_address_2() ),
			'ship_city'		=> $customer->get_shipping_city(),
			'ship_state'	=> $customer->get_shipping_state(),
			'ship_zip_code'	=> $customer->get_shipping_postcode(),
			'ship_country'	=> $customer->get_shipping_country(),
			'email_domain'	=> substr( $order->billing_email, strpos( $order->billing_email, '@' ) + 1 ) ,
			'email_hash'	=> $this->hash_string( $order->billing_email ) ,
			'user_order_id'	=> $order_id,
			'amount'		=> $order->get_total(),
			'quantity'		=> $qty,
			'currency'		=> $order->get_order_currency(),
			'payment_mode'	=> $paymentMode,
		) );

		for( $i = 0; $i < 3; $i++ ) {
			if( is_null( $json = json_decode( $this->http( $url ) ) ) === FALSE ) {
				add_post_meta( $order_id, '_fraudlabspro', json_encode( array(
					'order_id'						=> $order_id,
					'is_country_match'				=> $json->is_country_match,
					'is_high_risk_country'			=> $json->is_high_risk_country,
					'distance_in_km'				=> $json->distance_in_km,
					'distance_in_mile'				=> $json->distance_in_mile,
					'ip_address'					=> $client_ip,
					'ip_country'					=> $json->ip_country,
					'ip_region'						=> $json->ip_region,
					'ip_city'						=> $json->ip_city,
					'ip_continent'					=> $json->ip_continent,
					'ip_latitude'					=> $json->ip_latitude,
					'ip_longitude'					=> $json->ip_longitude,
					'ip_timezone'					=> $json->ip_timezone,
					'ip_elevation'					=> $json->ip_elevation,
					'ip_domain'						=> $json->ip_domain,
					'ip_mobile_mnc'					=> $json->ip_mobile_mnc,
					'ip_mobile_mcc'					=> $json->ip_mobile_mcc,
					'ip_mobile_brand'				=> $json->ip_mobile_brand,
					'ip_netspeed'					=> $json->ip_netspeed,
					'ip_isp_name'					=> $json->ip_isp_name,
					'ip_usage_type'					=> $json->ip_usage_type,
					'is_free_email'					=> $json->is_free_email,
					'is_new_domain_name'			=> $json->is_new_domain_name,
					'is_proxy_ip_address'			=> $json->is_proxy_ip_address,
					'is_bin_found'					=> $json->is_bin_found,
					'is_bin_country_match'			=> $json->is_bin_country_match,
					'is_bin_name_match'				=> $json->is_bin_name_match,
					'is_bin_phone_match'			=> $json->is_bin_phone_match,
					'is_bin_prepaid'				=> $json->is_bin_prepaid,
					'is_address_ship_forward'		=> $json->is_address_ship_forward,
					'is_bill_ship_city_match'		=> $json->is_bill_ship_city_match,
					'is_bill_ship_state_match'		=> $json->is_bill_ship_state_match,
					'is_bill_ship_country_match'	=> $json->is_bill_ship_country_match,
					'is_bill_ship_postal_match'		=> $json->is_bill_ship_postal_match,
					'is_ip_blacklist'				=> $json->is_ip_blacklist,
					'is_email_blacklist'			=> $json->is_email_blacklist,
					'is_credit_card_blacklist'		=> $json->is_credit_card_blacklist,
					'is_device_blacklist'			=> $json->is_device_blacklist,
					'is_user_blacklist'				=> $json->is_user_blacklist,
					'fraudlabspro_score'			=> $json->fraudlabspro_score,
					'fraudlabspro_distribution'		=> $json->fraudlabspro_distribution,
					'fraudlabspro_status'			=> $json->fraudlabspro_status,
					'fraudlabspro_id'				=> $json->fraudlabspro_id,
					'fraudlabspro_error_code'		=> $json->fraudlabspro_error_code,
					'fraudlabspro_message'			=> $json->fraudlabspro_message,
					'fraudlabspro_credits'			=> $json->fraudlabspro_credits,
					'api_key'						=> $this->api_key,
				) ) );

				break;
			}
		}

		if ( filter_var( $this->store_admin_email,  FILTER_VALIDATE_EMAIL ) ) {
			$location = array();
			if( strlen( $json->ip_country ) == 2 ) {
				$location = array(
					$this->fix_case( $json->ip_continent ),
					$this->get_country_by_code( $json->ip_country),
					$this->fix_case( $json->ip_region ),
					$this->fix_case( $json->ip_city )
				);
				$location = array_unique( $location );
			}

			$message = array(
				'Your WooCommerce Order #' . $order_id . ' has been processed by FraudLabs Pro fraud prevention service.',
				'',
				'Please review the result:',
				'',
				'Is Country Match           : ' . $this->parse_fraud_result($json->is_country_match),
				'Is High Risk Country       : ' . $this->parse_fraud_result($json->is_high_risk_country),
				'Distance                   : ' . $json->distance_in_km . ' KM / ' . $json->distance_in_mile . ' Miles',
				'IP Address                 : ' . $client_ip,
				'ISP Name                   : ' . $json->ip_isp_name,
				'Location                   : ' . implode( ', ', $location ),
				'Using Free Email           : ' . $this->parse_fraud_result($json->is_free_email),
				'Using Proxy Server         : ' . $this->parse_fraud_result($json->is_proxy_ip_address),
				'Using Address Forwarder    : ' . $this->parse_fraud_result($json->is_address_ship_forward),
				'Is IP Blacklisted          : ' . $this->parse_fraud_result($json->is_ip_blacklist),
				'Is Email Blacklisted       : ' . $this->parse_fraud_result($json->is_email_blacklist),
				'Fraud Score                : ' . $json->fraudlabspro_score,
				'FraudLabs Pro Status       : ' . $json->fraudlabspro_status,
				'FraudLabs Pro Message      : ' . $json->fraudlabspro_message,
				'Credits Left               : ' . $json->fraudlabspro_credits,
				'Transaction Details        : https://www.fraudlabspro.com/merchant/transaction-details/' . $json->fraudlabspro_id,
				'',
				'',
				'Regards,',
				'FraudLabs Pro',
				'www.fraudlabspro.com',
			);

			wp_mail( $this->store_admin_email, '[#' . $order_id . '] FraudLabs Pro Result (Score: ' . $json->fraudlabspro_score . ')', implode( "\n", $message ) );
		}

		if( ( int )$json->fraudlabspro_score > $this->risk_score || $json->fraudlabspro_status == 'REJECT' ) {
			$order->add_order_note( __( 'Marked as fraud by FraudLabs Pro', 'woocommerce-fraudlabs-pro' ) );

			global $woocommerce;
			$woocommerce->cart->empty_cart() ;

			echo '<!--WC_START-->' . json_encode( array(
				'result'	=> 'failure',
				'messages'	=> '<ul class="woocommerce-error"><li>' . $this->message . '</li></ul>',
			) ) . '<!--WC_END-->';

			die;
		}
	}


	/**
	 * Render fraud report into order details.
	 */
	public function render_fraud_report() {
		wp_enqueue_script( 'jquery' );

		if( isset( $_POST['approve'] ) ) {
			$url = 'https://api.fraudlabspro.com/v1/order/feedback?' . http_build_query( array(
				'key'		=> $this->api_key,
				'action'	=> 'APPROVE',
				'id'		=> $_POST['transactionId'],
				'format'	=> 'json'
			) );

			for( $i = 0; $i < 3; $i++ ) {
				if( is_null( $json = json_decode( $this->http( $url ) ) ) === FALSE ) {
					if( $json->fraudlabspro_error_code == '' || $json->fraudlabspro_error_code == '304' ) {
						$result = get_post_meta( $_GET['post'], '_fraudlabspro' );
						$row = json_decode( $result[0] );
						$row->fraudlabspro_status = 'APPROVE';
						update_post_meta( $_GET['post'], '_fraudlabspro', json_encode( $row ) );
					}
					break;
				}
			}
		}
		if( isset( $_POST['reject'] ) ) {
			$url = 'https://api.fraudlabspro.com/v1/order/feedback?' . http_build_query( array(
				'key'		=> $this->api_key,
				'action'	=> 'REJECT',
				'id'		=> $_POST['transactionId'],
				'format'	=> 'json'
			) );

			for( $i = 0; $i < 3; $i++ ) {
				if( is_null( $json = json_decode( $this->http( $url ) ) ) === FALSE ) {
					if( $json->fraudlabspro_error_code == '' || $json->fraudlabspro_error_code == '304' ) {
						$result = get_post_meta( $_GET['post'], '_fraudlabspro' );
						$row = json_decode( $result[0] );
						$row->fraudlabspro_status = 'REJECT';
						update_post_meta( $_GET['post'], '_fraudlabspro', json_encode( $row ) );
					}
					break;
				}
			}
		}

		$result = get_post_meta( $_GET['post'], '_fraudlabspro' );

		if( count( $result ) > 0 ) {
			$row = json_decode( $result[0] );
			$table = '
			<style type="text/css">
				.fraudlabspro{border:1px solid #ccced7;border-collapse:collapse;margin:auto;padding:4px;table-layout:fixed;width:100%}
				.fraudlabspro td{border-bottom:1px solid #ccced7;border-left:1px solid #ccced7;padding:5px 0 0 5px;text-align:left;white-space:nowrap;font-size:11px}
			</style>

			<table class="fraudlabspro">
				<col width="80">
				<col width="100">
				<col width="140">
				<col width="140">
				<col width="140">';

			$location = array();
			if( strlen( $row->ip_country ) == 2 ) {
				$location = array(
					$this->fix_case( $row->ip_continent ),
					$this->get_country_by_code( $row->ip_country ),
					$this->fix_case( $row->ip_region ),
					$this->fix_case( $row->ip_city )
				);
				$location = array_unique( $location );
			}

			$table .= '
				<tr>
					<td rowspan="3">
						<center><b>Score</b> <a href="javascript:;" title="Overall score between 0 and 100. 100 is the highest risk. 0 is the lowest risk.">[?]</a>
						<p style="font-size:3em">' . $row->fraudlabspro_score . '</p></center>
					</td>
					<td>
						<b>' . __( 'IP Address', 'woocommerce-fraudlabs-pro' ) . '</b>
						<p>' . $row->ip_address . '</p>
					</td>
					<td colspan="3">
						<b>' . __( 'IP Location', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Estimated location of the IP address.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . implode( ', ', $location ) . ' <a href="http://www.geolocation.com/' . $row->ip_address . '" target="_blank">[' . __( 'Map', 'woocommerce-fraudlabs-pro' ) . ']</a></p>
					</td>
				</tr>
				<tr>
					<td>
						<b>' . __( 'IP Net Speed', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Connection speed.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $row->ip_netspeed . '</p>
					</td>
					<td colspan="3">
						<b>' . __( 'IP ISP Name', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Estimated ISP of the IP address.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $row->ip_isp_name . '</p>
					</td>
				</tr>';

			switch( $row->fraudlabspro_status ) {
				case 'REVIEW':
					$color = 'ffcc00';
					break;

				case 'REJECT':
					$color = 'cc0000';
					break;

				case 'APPROVE':
					$color = '336600';
					break;
			}

			$table .= '
				<tr>
					<td>
						<b>' . __( 'IP Domain', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Estimated domain name of the IP address.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $row->ip_domain . '</p>
					</td>
					<td>
						<b>' . __( 'IP Usage Type', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Estimated usage type of the IP address. ISP, Commercial, Residential.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . ( ( empty( $row->ip_usage_type ) ) ? '-' : $row->ip_usage_type ) . '</p>
					</td>
					<td>
						<b>' . __( 'IP Time Zone', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Estimated timezone of the IP address.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $row->ip_timezone . '</p>
					</td>
					<td>
						<b>' . __( 'IP Distance', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Distance from IP address to Billing Location.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . ( ( $row->distance_in_km ) ? ( $row->distance_in_km . ' KM / ' . $row->distance_in_mile . ' Miles' ) : '-' ) . '</p>
					</td>
				</tr>
				<tr>
					<td rowspan="3">
						<center><b>' . __( 'Status', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'FraudLabs Pro status.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p style="color:#' . $color . ';font-size:1.333em;font-weight:bold">' . $row->fraudlabspro_status . '</p></center>
					</td>
					<td>
						<b>' . __( 'IP Latitude', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Estimated latitude of the IP address.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $row->ip_latitude . '</p>
					</td>
					<td>
						<b>' . __( 'IP Longitude', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Estimated longitude of the IP address.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $row->ip_longitude . '</p>
					</td>
					<td colspan="2">&nbsp;</td>
				</tr>
				<tr>
					<td>
						<b>' . __( 'High Risk', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Whether IP address or billing address country is in the latest high risk list.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $this->parse_fraud_result( $row->is_high_risk_country ) . '</p>
					</td>
					<td>
						<b>' . __( 'Free Email', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Whether e-mail is from free e-mail provider.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $this->parse_fraud_result( $row->is_free_email ) . '</p>
					</td>
					<td>
						<b>' . __( 'Ship Forward', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Whether shipping address is in database of known mail drops.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $this->parse_fraud_result( $row->is_address_ship_forward ) . '</p>
					</td>
					<td>
						<b>' . __( 'Using Proxy', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Whether IP address is from Anonymous Proxy Server.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $this->parse_fraud_result( $row->is_proxy_ip_address ) . '</p>
					</td>
				</tr>
				<tr>
					<td>
						<b>' . __( 'BIN Found', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Whether the BIN information matches our BIN list.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $this->parse_fraud_result( $row->is_bin_found ) . '</p>
					</td>
					<td>
						<b>' . __( 'Email Blacklist', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Whether the email address is in our blacklist database.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $this->parse_fraud_result( $row->is_email_blacklist ) . '</p>
					</td>
					<td>
						<b>' . __( 'Credit Card Blacklist', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'Whether the credit card is in our blacklist database.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . $this->parse_fraud_result( $row->is_credit_card_blacklist ) . '</p>
					</td>
					<td>&nbsp;</td>
				</tr>
				<tr>
					<td colspan="5">
						<b>' . __( 'Message', 'woocommerce-fraudlabs-pro' ) . '</b> <a href="javascript:;" title="' . __( 'FraudLabs Pro Web service message response.', 'woocommerce-fraudlabs-pro' ) . '">[?]</a>
						<p>' . ( ( $row->fraudlabspro_message ) ? $row->fraudlabspro_error_code . ':' . $row->fraudlabspro_message : '-' ) . '</p>
				</tr>
				<tr>
					<td colspan="5">
						<b>' . __( 'Link', 'woocommerce-fraudlabs-pro' ) . '</b>
						<p><a href="http://www.fraudlabspro.com/merchant/transaction-details/' . $row->fraudlabspro_id . '" target="_blank">http://www.fraudlabspro.com/merchant/transaction-details/' . $row->fraudlabspro_id . '</a></p>
				</tr>
				</table>';
			if( $row->fraudlabspro_status == 'REVIEW' ) {
				$table .= '
				<form method="post">
					<p align="center">
					<input type="hidden" name="transactionId" value="' . $row->fraudlabspro_id . '" >
					<input type="submit" name="approve" id="approve-order" value="' . __( 'Approve', 'woocommerce-fraudlabs-pro' ) . '" style="padding:10px 5px; background:#22aa22; border:1px solid #ccc; min-width:100px; cursor: pointer;" />
					<input type="submit" name="reject" id="reject-order" value="' . __( 'Reject', 'woocommerce-fraudlabs-pro' ) . '" style="padding:10px 5px; background:#cd2122; border:1px solid #ccc; min-width:100px; cursor: pointer;" />
					</p>
				</form>';
			}
			echo '
			<script>
			jQuery(function(){
				jQuery("#woocommerce-order-items").before(\'<div class="metabox-holder"><div class="postbox"><h3>FraudLabs Pro Details</h3><blockquote>' . preg_replace( '/[\n]*/is', '', str_replace( '\'', '\\\'', $table ) ) . '</blockquote></div></div>\');
			});
			</script>';
		}
		else {
			echo '
			<script>
			jQuery(function(){
				jQuery("#woocommerce-order-items").before(\'<div class="metabox-holder"><div class="postbox"><h3>FraudLabs Pro Details</h3><blockquote>This order has not been screened by FraudLabs Pro.</blockquote></div></div>\');
			});
			</script>';
		}
	}


	/**
	 * Hash a string to send to FraudLabs Pro API.
	 */
	private function hash_string( $s ) {
		$hash = 'fraudlabspro_' . $s;

		for( $i = 0; $i < 65536; $i++ )
			$hash = sha1( 'fraudlabspro_' . $hash );

		return $hash;
	}


	/**
	 * Convert string into mix case.
	 */
	private function fix_case( $s ) {
		$s = ucwords( strtolower( $s ) );
		$s = preg_replace_callback( "/( [ a-zA-Z]{1}')([a-zA-Z0-9]{1})/s", create_function( '$matches', 'return $matches[1].strtoupper($matches[2]);' ) , $s );

		return $s;
	}


	/**
	 * Send HTTP request.
	 */
	function http( $url ) {
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) )
			return FALSE;

		return $response['body'];
	}


	/**
	 * Parse FraudLabs Pro API result.
	 */
	function parse_fraud_result( $result ) {
		if ( $result == 'Y' )
			return 'Yes';

		if ( $result == 'N' )
			return 'No';

		if ( $result == 'NA' )
			return '-';

		return $result;
	}


	/**
	 * Get country name by country code.
	 */
	function get_country_by_code( $code ) {
		$countries = array( 'AF' => 'Afghanistan','AL' => 'Albania','DZ' => 'Algeria','AS' => 'American Samoa','AD' => 'Andorra','AO' => 'Angola','AI' => 'Anguilla','AQ' => 'Antarctica','AG' => 'Antigua and Barbuda','AR' => 'Argentina','AM' => 'Armenia','AW' => 'Aruba','AU' => 'Australia','AT' => 'Austria','AZ' => 'Azerbaijan','BS' => 'Bahamas','BH' => 'Bahrain','BD' => 'Bangladesh','BB' => 'Barbados','BY' => 'Belarus','BE' => 'Belgium','BZ' => 'Belize','BJ' => 'Benin','BM' => 'Bermuda','BT' => 'Bhutan','BO' => 'Bolivia','BA' => 'Bosnia and Herzegovina','BW' => 'Botswana','BV' => 'Bouvet Island','BR' => 'Brazil','IO' => 'British Indian Ocean Territory','BN' => 'Brunei Darussalam','BG' => 'Bulgaria','BF' => 'Burkina Faso','BI' => 'Burundi','KH' => 'Cambodia','CM' => 'Cameroon','CA' => 'Canada','CV' => 'Cape Verde','KY' => 'Cayman Islands','CF' => 'Central African Republic','TD' => 'Chad','CL' => 'Chile','CN' => 'China','CX' => 'Christmas Island','CC' => 'Cocos (Keeling) Islands','CO' => 'Colombia','KM' => 'Comoros','CG' => 'Congo','CK' => 'Cook Islands','CR' => 'Costa Rica','CI' => 'Cote D\'Ivoire','HR' => 'Croatia','CU' => 'Cuba','CY' => 'Cyprus','CZ' => 'Czech Republic','CD' => 'Democratic Republic of Congo','DK' => 'Denmark','DJ' => 'Djibouti','DM' => 'Dominica','DO' => 'Dominican Republic','TP' => 'East Timor','EC' => 'Ecuador','EG' => 'Egypt','SV' => 'El Salvador','GQ' => 'Equatorial Guinea','ER' => 'Eritrea','EE' => 'Estonia','ET' => 'Ethiopia','FK' => 'Falkland Islands (Malvinas)','FO' => 'Faroe Islands','FJ' => 'Fiji','FI' => 'Finland','FR' => 'France','FX' => 'France, Metropolitan','GF' => 'French Guiana','PF' => 'French Polynesia','TF' => 'French Southern Territories','GA' => 'Gabon','GM' => 'Gambia','GE' => 'Georgia','DE' => 'Germany','GH' => 'Ghana','GI' => 'Gibraltar','GR' => 'Greece','GL' => 'Greenland','GD' => 'Grenada','GP' => 'Guadeloupe','GU' => 'Guam','GT' => 'Guatemala','GN' => 'Guinea','GW' => 'Guinea-bissau','GY' => 'Guyana','HT' => 'Haiti','HM' => 'Heard and Mc Donald Islands','HN' => 'Honduras','HK' => 'Hong Kong','HU' => 'Hungary','IS' => 'Iceland','IN' => 'India','ID' => 'Indonesia','IR' => 'Iran (Islamic Republic of)','IQ' => 'Iraq','IE' => 'Ireland','IL' => 'Israel','IT' => 'Italy','JM' => 'Jamaica','JP' => 'Japan','JO' => 'Jordan','KZ' => 'Kazakhstan','KE' => 'Kenya','KI' => 'Kiribati','KR' => 'Korea, Republic of','KW' => 'Kuwait','KG' => 'Kyrgyzstan','LA' => 'Lao People\'s Democratic Republic','LV' => 'Latvia','LB' => 'Lebanon','LS' => 'Lesotho','LR' => 'Liberia','LY' => 'Libyan Arab Jamahiriya','LI' => 'Liechtenstein','LT' => 'Lithuania','LU' => 'Luxembourg','MO' => 'Macau','MK' => 'Macedonia','MG' => 'Madagascar','MW' => 'Malawi','MY' => 'Malaysia','MV' => 'Maldives','ML' => 'Mali','MT' => 'Malta','MH' => 'Marshall Islands','MQ' => 'Martinique','MR' => 'Mauritania','MU' => 'Mauritius','YT' => 'Mayotte','MX' => 'Mexico','FM' => 'Micronesia, Federated States of','MD' => 'Moldova, Republic of','MC' => 'Monaco','MN' => 'Mongolia','MS' => 'Montserrat','MA' => 'Morocco','MZ' => 'Mozambique','MM' => 'Myanmar','NA' => 'Namibia','NR' => 'Nauru','NP' => 'Nepal','NL' => 'Netherlands','AN' => 'Netherlands Antilles','NC' => 'New Caledonia','NZ' => 'New Zealand','NI' => 'Nicaragua','NE' => 'Niger','NG' => 'Nigeria','NU' => 'Niue','NF' => 'Norfolk Island','KP' => 'North Korea','MP' => 'Northern Mariana Islands','NO' => 'Norway','OM' => 'Oman','PK' => 'Pakistan','PW' => 'Palau','PA' => 'Panama','PG' => 'Papua New Guinea','PY' => 'Paraguay','PE' => 'Peru','PH' => 'Philippines','PN' => 'Pitcairn','PL' => 'Poland','PT' => 'Portugal','PR' => 'Puerto Rico','QA' => 'Qatar','RE' => 'Reunion','RO' => 'Romania','RU' => 'Russian Federation','RW' => 'Rwanda','KN' => 'Saint Kitts and Nevis','LC' => 'Saint Lucia','VC' => 'Saint Vincent and the Grenadines','WS' => 'Samoa','SM' => 'San Marino','ST' => 'Sao Tome and Principe','SA' => 'Saudi Arabia','SN' => 'Senegal','SC' => 'Seychelles','SL' => 'Sierra Leone','SG' => 'Singapore','SK' => 'Slovak Republic','SI' => 'Slovenia','SB' => 'Solomon Islands','SO' => 'Somalia','ZA' => 'South Africa','GS' => 'South Georgia And The South Sandwich Islands','ES' => 'Spain','LK' => 'Sri Lanka','SH' => 'St. Helena','PM' => 'St. Pierre and Miquelon','SD' => 'Sudan','SR' => 'Suriname','SJ' => 'Svalbard and Jan Mayen Islands','SZ' => 'Swaziland','SE' => 'Sweden','CH' => 'Switzerland','SY' => 'Syrian Arab Republic','TW' => 'Taiwan','TJ' => 'Tajikistan','TZ' => 'Tanzania, United Republic of','TH' => 'Thailand','TG' => 'Togo','TK' => 'Tokelau','TO' => 'Tonga','TT' => 'Trinidad and Tobago','TN' => 'Tunisia','TR' => 'Turkey','TM' => 'Turkmenistan','TC' => 'Turks and Caicos Islands','TV' => 'Tuvalu','UG' => 'Uganda','UA' => 'Ukraine','AE' => 'United Arab Emirates','GB' => 'United Kingdom','US' => 'United States','UM' => 'United States Minor Outlying Islands','UY' => 'Uruguay','UZ' => 'Uzbekistan','VU' => 'Vanuatu','VA' => 'Vatican City State (Holy See)','VE' => 'Venezuela','VN' => 'Viet Nam','VG' => 'Virgin Islands (British)','VI' => 'Virgin Islands (U.S.)','WF' => 'Wallis and Futuna Islands','EH' => 'Western Sahara','YE' => 'Yemen','YU' => 'Yugoslavia','ZM' => 'Zambia','ZW' => 'Zimbabwe' );

		return ( isset( $countries[$code] ) ) ? $countries[$code] : NULL;
	}


	/**
	 * Display errors by overriding the display_errors() method
	 * @see display_errors()
	 */
	public function display_errors() {
		// loop through each error and display it
		foreach ( $this->errors as $key => $value ) {
			?>
			<div class="error">
				<p><?php _e( $value, 'woocommerce-fraudlabs-pro' ); ?></p>
			</div>
			<?php
		}
	}
}

endif;
