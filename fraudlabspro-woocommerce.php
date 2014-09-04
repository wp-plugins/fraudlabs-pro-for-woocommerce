<?php
/**
 * Plugin Name: FraudLabs Pro for WooCommerce
 * Plugin URI: http://www.fraudlabspro.com
 * Description: This plugin is an add-on for WooCommerce plugin that help you to screen your order transaction, such as credit card transaction, for online fraud.
 * Version: 1.1.0
 * Author: FraudLabs Pro
 * Author URI: http://www.fraudlabspro.com
 */

if(!defined('ABSPATH')){
	exit;
}

class FraudLabsPro_WooCommerce {
	private $plugin_name;
	private $domain = 'fraudlabs-pro-woocommerce';

	public function __construct() {
		add_action('admin_menu', array($this, 'admin_page'));
		add_action('woocommerce_checkout_order_processed', array($this, 'screen_order'));
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'render_fraud_report'));
		add_action( 'admin_notices', array($this, 'admin_notifications'));
	}

	private function fix_case($s) {
		$s = ucwords(strtolower($s));
		$s = preg_replace_callback("/( [ a-zA-Z]{1}')([a-zA-Z0-9]{1})/s", create_function('$matches', 'return $matches[1].strtoupper($matches[2]);') , $s);
		return $s;
	}

	public function admin_notifications() {
		$apiKey = get_option('woo_fraudlabspro_api_key');

		if(!$apiKey) {
			?>
			<div id="message" class="error">
				<p>
					<?php echo __('FraudLabs Pro setup is not complete. Please go to <a href="' . admin_url('/admin.php?page=woo-fraudlabs-pro') . '">setting page</a> to enter your API key.', $this->domain); ?>
				</p>
			</div>
			<?php
		}
	}

	public function admin_options() {
		if (is_admin()) {
			$enabled = (isset($_POST['save']) && isset($_POST['enabled'])) ? 1 : ((isset($_POST['save']) && !isset($_POST['enabled'])) ? 0 : get_option('woo_fraudlabspro_enabled'));
			$apiKey = (isset($_POST['apiKey'])) ? $_POST['apiKey'] : get_option('woo_fraudlabspro_api_key');
			$riskScore = (isset($_POST['riskScore'])) ? $_POST['riskScore'] : get_option('woo_fraudlabspro_score');
			$message = (isset($_POST['message'])) ? $_POST['message'] : get_option('woo_fraudlabspro_message');
			$testIP = (isset($_POST['testIP'])) ? $_POST['testIP'] : get_option('woo_fraudlabspro_test_ip');

			echo '
			<div class="wrap">
				<h2>' . __('FraudLabs Pro for WooCommerce', $this->domain) . '</h2>';
			if (isset($_POST['save'])) {
				if (!preg_match('/^[0-9]+$/', $riskScore)) {
					echo '<p style="color:#cc0000">Please provide a valid risk score.</p>';
				}elseif (!empty($testIP) && !filter_var($testIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
					echo '<p style="color:#cc0000">' . __('Please provide a valid test IP.', $this->domain) . '</p>';
				}else {
					update_option('woo_fraudlabspro_enabled', $enabled);
					update_option('woo_fraudlabspro_api_key', $apiKey);
					update_option('woo_fraudlabspro_score', $riskScore);
					update_option('woo_fraudlabspro_message', $message);
					update_option('woo_fraudlabspro_test_ip', $testIP);

					echo '
					<div class="updated settings-error">
						<p><strong>' . __('Changes have been successfully saved.', $this->domain) . '</strong></p>
					</div>';
				}
			}
			echo '
			<form method="post">
				<input type="hidden" name="save" value="true" />
				<p>
					<input type="checkbox" name="enabled" id="flpEnabled"' . (($enabled) ? ' checked' : '') . '>
					<label for="flpEnabled">' . __('Enabled', $this->domain) . '</label>
				</p>
				<p>
					<label style="width:100px;display:inline-block"><b>' . __('API Key', $this->domain) . ': </b></label>
					<input type="text" name="apiKey" value="' . $apiKey . '" maxlength="32" size="50" />
					<div style="font-size:11px;color:#535353">' . __('You can register for a free license key at <a href="http://www.fraudlabspro.com/sign-up" target="_blank">http://www.fraudlabspro.com/sign-up</a> if you do not have one.', $this->domain) . '</div>
				</p>
				<p>
					<label style="width:100px;display:inline-block"><b>' . __('Risk Score', $this->domain) . ': </b></label>
					<input type="text" name="riskScore" value="' . $riskScore . '" maxlength="2" size="5" />
					<div style="font-size:11px;color:#535353">' . __('Reject the transaction if the calculated risk score higher than this value.', $this->domain) . '</div>
				</p>
				<p>
					<label style="width:100px;display:inline-block"><b>' . __('Message', $this->domain) . ': </b></label>
					<input type="text" name="message" value="' . $message . '" maxlength="200" size="60" />
					<div style="font-size:11px;color:#535353">' . __('Display this message whenever transaction is marked as fraud.', $this->domain) . '</div>
				</p>
				<p>
					<label style="width:100px;display:inline-block"><b>' . __('Test IP', $this->domain) . ': </b></label>
					<input type="text" name="testIP" value="' . $testIP . '" maxlength="15" size="15" />
					<div style="font-size:11px;color:#535353">' . __('Simulate visitor IP. Clear this value for production run.', $this->domain) . '</div>
				</p>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Save Changes', $this->domain) . '"  /></p>
			</div>';
		}
	}

	public function screen_order($order_id) {
		global $woocommerce;

		$order = new WC_Order($order_id);
		$items = $order->get_items();

		$customer = new WC_Customer();
		$checkout = new WC_Checkout();

		if (!get_option('woo_fraudlabspro_enabled')) return;

		$qty = 0;
		foreach ($items as $key => $value) {
			$qty += $value['qty'];
		}

		switch($checkout->get_value('payment_method')) {
			case 'bacs':
				$paymentMode = 'bankdeposit';
				break;

			case 'paypal':
				$paymentMode = 'paypal';
				break;

			default:
				$paymentMode = 'others';
		}

		$request = array('key' => get_option('woo_fraudlabspro_api_key') ,
			'format' => 'json',
			'ip' => (get_option('woo_fraudlabspro_test_ip')) ? get_option('woo_fraudlabspro_test_ip') : $_SERVER['REMOTE_ADDR'],
			'bill_city' => $customer->get_city(),
			'bill_state' => $customer->get_state(),
			'bill_zip_code' => $customer->get_postcode(),
			'bill_country' => $customer->get_country(),
			'ship_addr' => trim($customer->get_shipping_address() . ' ' . $customer->get_shipping_address_2()),
			'ship_city' => $customer->get_shipping_city(),
			'ship_state' => $customer->get_shipping_state(),
			'ship_zip_code' => $customer->get_shipping_postcode(),
			'ship_country' => $customer->get_shipping_country(),
			'email_domain' => substr($order->billing_email, strpos($order->billing_email, '@') + 1) ,
			'email_hash' => $this->hash_string($order->billing_email) ,
			'user_order_id' => $order_id,
			'amount' => $order->get_total(),
			'quantity' => $qty,
			'currency' => $order->get_order_currency(),
			'payment_mode' => $paymentMode
			);
		$url = 'https://api.fraudlabspro.com/v1/order/screen?' . http_build_query($request);
		for ($i = 0; $i < 3; $i++) {
			if (is_null($json = json_decode(@file_get_contents($url))) === false) {
				add_post_meta($order_id, '_fraudlabspro', json_encode(array(
					'order_id' => $order_id,
					'is_country_match' => $json->is_country_match,
					'is_high_risk_country' => $json->is_high_risk_country,
					'distance_in_km' => $json->distance_in_km,
					'distance_in_mile' => $json->distance_in_mile,
					'ip_address' => (get_option('woo_fraudlabspro_test_ip')) ? get_option('woo_fraudlabspro_test_ip') : $_SERVER['REMOTE_ADDR'],
					'ip_country' => $json->ip_country,
					'ip_region' => $json->ip_region,
					'ip_city' => $json->ip_city,
					'ip_continent' => $json->ip_continent,
					'ip_latitude' => $json->ip_latitude,
					'ip_longitude' => $json->ip_longitude,
					'ip_timezone' => $json->ip_timezone,
					'ip_elevation' => $json->ip_elevation,
					'ip_domain' => $json->ip_domain,
					'ip_mobile_mnc' => $json->ip_mobile_mnc,
					'ip_mobile_mcc' => $json->ip_mobile_mcc,
					'ip_mobile_brand' => $json->ip_mobile_brand,
					'ip_netspeed' => $json->ip_netspeed,
					'ip_isp_name' => $json->ip_isp_name,
					'ip_usage_type' => $json->ip_usage_type,
					'is_free_email' => $json->is_free_email,
					'is_new_domain_name' => $json->is_new_domain_name,
					'is_proxy_ip_address' => $json->is_proxy_ip_address,
					'is_bin_found' => $json->is_bin_found,
					'is_bin_country_match' => $json->is_bin_country_match,
					'is_bin_name_match' => $json->is_bin_name_match,
					'is_bin_phone_match' => $json->is_bin_phone_match,
					'is_bin_prepaid' => $json->is_bin_prepaid,
					'is_address_ship_forward' => $json->is_address_ship_forward,
					'is_bill_ship_city_match' => $json->is_bill_ship_city_match,
					'is_bill_ship_state_match' => $json->is_bill_ship_state_match,
					'is_bill_ship_country_match' => $json->is_bill_ship_country_match,
					'is_bill_ship_postal_match' => $json->is_bill_ship_postal_match,
					'is_ip_blacklist' => $json->is_ip_blacklist,
					'is_email_blacklist' => $json->is_email_blacklist,
					'is_credit_card_blacklist' => $json->is_credit_card_blacklist,
					'is_device_blacklist' => $json->is_device_blacklist,
					'is_user_blacklist' => $json->is_user_blacklist,
					'fraudlabspro_score' => $json->fraudlabspro_score,
					'fraudlabspro_distribution' => $json->fraudlabspro_distribution,
					'fraudlabspro_status' => $json->fraudlabspro_status,
					'fraudlabspro_id' => $json->fraudlabspro_id,
					'fraudlabspro_error_code' => $json->fraudlabspro_error_code,
					'fraudlabspro_message' => $json->fraudlabspro_message,
					'fraudlabspro_credits' => $json->fraudlabspro_credits,
					'api_key' => get_option('woo_fraudlabspro_api_key')
				)));

				break;
			}
		}
		if ((int)$json->fraudlabspro_score > get_option('woo_fraudlabspro_score') || $json->fraudlabspro_status == 'REJECT') {
			$order->add_order_note(__('Marked as fraud by FraudLabs Pro', $this->domain));

			$woocommerce->cart->empty_cart();

			echo '<!--WC_START-->' . json_encode(array(
				'result' => 'failure',
				'messages' => '<ul class="woocommerce-error"><li>' . get_option('woo_fraudlabspro_message') . '</li></ul>',
			)) . '<!--WC_END-->';

			exit();
		}
	}

	public function render_fraud_report() {
		wp_enqueue_script('jquery');

		if (isset($_POST['approve'])) {
			$request = array('key' => get_option('woo_fraudlabspro_api_key'),
				'action' => 'APPROVE',
				'id' => $_POST['transactionId'],
				'format' => 'json'
			);

			$url = 'https://api.fraudlabspro.com/v1/order/feedback?' . http_build_query($request);

			for($i = 0; $i < 3; $i++) {
				if (is_null($json = json_decode(@file_get_contents($url))) === false) {
					if ($json->fraudlabspro_error_code == '' || $json->fraudlabspro_error_code == '304') {
						$result = get_post_meta($_GET['post'], '_fraudlabspro');
						$row = json_decode($result[0]);
						$row->fraudlabspro_status = 'APPROVE';
						update_post_meta($_GET['post'], '_fraudlabspro', json_encode($row));
					}
					break;
				}
			}
		}
		if (isset($_POST['reject'])) {
			$request = array('key' => get_option('woo_fraudlabspro_api_key') ,
				'action' => 'REJECT',
				'id' => $_POST['transactionId'],
				'format' => 'json'
			);

			$url = 'https://api.fraudlabspro.com/v1/order/feedback?' . http_build_query($request);
			for ($i = 0; $i < 3; $i++) {
				if (is_null($json = json_decode(@file_get_contents($url))) === false) {
					if ($json->fraudlabspro_error_code == '' || $json->fraudlabspro_error_code == '304') {
						$result = get_post_meta($_GET['post'], '_fraudlabspro');
						$row = json_decode($result[0]);
						$row->fraudlabspro_status = 'REJECT';
						update_post_meta($_GET['post'], '_fraudlabspro', json_encode($row));
					}
					break;
				}
			}
		}

		$result = get_post_meta($_GET['post'], '_fraudlabspro');

		if (count($result) > 0) {
			$row = json_decode($result[0]);
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
			if (strlen($row->ip_country) == 2) {
				$location = array($this->fix_case($row->ip_continent) ,
					$row->ip_country,
					$this->fix_case($row->ip_region) ,
					$this->fix_case($row->ip_city)
				);
				$location = array_unique($location);
			}
			$table .= '
				<tr>
					<td rowspan="3">
						<center><b>Score</b> <a href="javascript:;" title="Overall score between 0 and 100. 100 is the highest risk. 0 is the lowest risk.">[?]</a>
						<p style="font-size:3em">' . $row->fraudlabspro_score . '</p></center>
					</td>
					<td>
						<b>' . __('IP Address', $this->domain) . '</b>
						<p>' . $row->ip_address . '</p>
					</td>
					<td colspan="3">
						<b>' . __('IP Location', $this->domain) . '</b> <a href="javascript:;" title="' . __('Estimated location of the IP address.', $this->domain) . '">[?]</a>
						<p>' . implode(', ', $location) . ' <a href="http://www.geolocation.com/' . $row->ip_address . '" target="_blank">[' . __('Map', $this->domain) . ']</a></p>
					</td>
				</tr>
				<tr>
					<td>
						<b>' . __('IP Net Speed', $this->domain) . '</b> <a href="javascript:;" title="' . __('Connection speed.', $this->domain) . '">[?]</a>
						<p>' . $row->ip_netspeed . '</p>
					</td>
					<td colspan="3">
						<b>' . __('IP ISP Name', $this->domain) . '</b> <a href="javascript:;" title="' . __('Estimated ISP of the IP address.', $this->domain) . '">[?]</a>
						<p>' . $row->ip_isp_name . '</p>
					</td>
				</tr>';
			switch ($row->fraudlabspro_status) {
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
						<b>' . __('IP Domain', $this->domain) . '</b> <a href="javascript:;" title="' . __('Estimated domain name of the IP address.', $this->domain) . '">[?]</a>
						<p>' . $row->ip_domain . '</p>
					</td>
					<td>
						<b>' . __('IP Usage Type', $this->domain) . '</b> <a href="javascript:;" title="' . __('Estimated usage type of the IP address. ISP, Commercial, Residential.', $this->domain) . '">[?]</a>
						<p>' . ((empty($row->ip_usage_type)) ? '-' : $row->ip_usage_type) . '</p>
					</td>
					<td>
						<b>' . __('IP Time Zone', $this->domain) . '</b> <a href="javascript:;" title="' . __('Estimated timezone of the IP address.', $this->domain) . '">[?]</a>
						<p>' . $row->ip_timezone . '</p>
					</td>
					<td>
						<b>' . __('IP Distance', $this->domain) . '</b> <a href="javascript:;" title="' . __('Distance from IP address to Billing Location.', $this->domain) . '">[?]</a>
						<p>' . (($row->distance_in_km) ? ($row->distance_in_km . ' KM / ' . $row->distance_in_mile . ' Miles') : '-') . '</p>
					</td>
				</tr>
				<tr>
					<td rowspan="3">
						<center><b>' . __('Status', $this->domain) . '</b> <a href="javascript:;" title="' . __('FraudLabs Pro status.', $this->domain) . '">[?]</a>
						<p style="color:#' . $color . ';font-size:1.333em;font-weight:bold">' . $row->fraudlabspro_status . '</p></center>
					</td>
					<td>
						<b>' . __('IP Latitude', $this->domain) . '</b> <a href="javascript:;" title="' . __('Estimated latitude of the IP address.', $this->domain) . '">[?]</a>
						<p>' . $row->ip_latitude . '</p>
					</td>
					<td>
						<b>' . __('IP Longitude', $this->domain) . '</b> <a href="javascript:;" title="' . __('Estimated longitude of the IP address.', $this->domain) . '">[?]</a>
						<p>' . $row->ip_longitude . '</p>
					</td>
					<td colspan="2">&nbsp;</td>
				</tr>
				<tr>
					<td>
						<b>' . __('High Risk', $this->domain) . '</b> <a href="javascript:;" title="' . __('Whether IP address or billing address country is in the latest high risk list.', $this->domain) . '">[?]</a>
						<p>' . (($row->is_high_risk_country == 'Y') ? 'Yes' : (($row->is_high_risk_country == 'N') ? 'No' : '-')) . '</p>
					</td>
					<td>
						<b>' . __('Free Email', $this->domain) . '</b> <a href="javascript:;" title="' . __('Whether e-mail is from free e-mail provider.', $this->domain) . '">[?]</a>
						<p>' . (($row->is_free_email == 'Y') ? 'Yes' : (($row->is_free_email == 'N') ? 'No' : '-')) . '</p>
					</td>
					<td>
						<b>' . __('Ship Forward', $this->domain) . '</b> <a href="javascript:;" title="' . __('Whether shipping address is in database of known mail drops.', $this->domain) . '">[?]</a>
						<p>' . (($row->is_address_ship_forward == 'Y') ? 'Yes' : (($row->is_address_ship_forward == 'N') ? 'No' : '-')) . '</p>
					</td>
					<td>
						<b>' . __('Using Proxy', $this->domain) . '</b> <a href="javascript:;" title="' . __('Whether IP address is from Anonymous Proxy Server.', $this->domain) . '">[?]</a>
						<p>' . (($row->is_proxy_ip_address == 'Y') ? 'Yes' : (($row->is_proxy_ip_address == 'N') ? 'No' : '-')) . '</p>
					</td>
				</tr>
				<tr>
					<td>
						<b>' . __('BIN Found', $this->domain) . '</b> <a href="javascript:;" title="' . __('Whether the BIN information matches our BIN list.', $this->domain) . '">[?]</a>
						<p>' . (($row->is_bin_found == 'Y') ? 'Yes' : (($row->is_bin_found == 'N') ? 'No' : '-')) . '</p>
					</td>
					<td>
						<b>' . __('Email Blacklist', $this->domain) . '</b> <a href="javascript:;" title="' . __('Whether the email address is in our blacklist database.', $this->domain) . '">[?]</a>
						<p>' . (($row->is_email_blacklist == 'Y') ? 'Yes' : (($row->is_email_blacklist == 'N') ? 'No' : '-')) . '</p>
					</td>
					<td>
						<b>' . __('Credit Card Blacklist', $this->domain) . '</b> <a href="javascript:;" title="' . __('Whether the credit card is in our blacklist database.', $this->domain) . '">[?]</a>
						<p>' . (($row->is_credit_card_blacklist == 'Y') ? 'Yes' : (($row->is_credit_card_blacklist == 'N') ? 'No' : '-')) . '</p>
					</td>
					<td>&nbsp;</td>
				</tr>
				<tr>
					<td colspan="5">
						<b>' . __('Message', $this->domain) . '</b> <a href="javascript:;" title="' . __('FraudLabs Pro Web service message response.', $this->domain) . '">[?]</a>
						<p>' . (($row->fraudlabspro_message) ? $row->fraudlabspro_error_code . ':' . $row->fraudlabspro_message : '-') . '</p>
				</tr>
				<tr>
					<td colspan="5">
						<b>' . __('Link', $this->domain) . '</b>
						<p><a href="http://www.fraudlabspro.com/merchant/transaction-details/' . $row->fraudlabspro_id . '" target="_blank">http://www.fraudlabspro.com/merchant/transaction-details/' . $row->fraudlabspro_id . '</a></p>
				</tr>
				</table>';
			if ($row->fraudlabspro_status == 'REVIEW') {
				$table .= '
				<form method="post">
					<p align="center">
					<input type="hidden" name="transactionId" value="' . $row->fraudlabspro_id . '" >
					<input type="submit" name="approve" id="approve-order" value="' . __('Approve', $this->domain) . '" style="padding:10px 5px; background:#22aa22; border:1px solid #ccc; min-width:100px; cursor: pointer;" />
					<input type="submit" name="reject" id="reject-order" value="' . __('Reject', $this->domain) . '" style="padding:10px 5px; background:#cd2122; border:1px solid #ccc; min-width:100px; cursor: pointer;" />
					</p>
				</form>';
			}
			echo '
			<script>
			jQuery(function(){
				jQuery("#woocommerce-order-items").before(\'<div class="metabox-holder"><div class="postbox"><h3>FraudLabs Pro Details</h3><blockquote>' . preg_replace('/[\n]*/is', '', str_replace('\'', '\\\'', $table)) . '</blockquote></div></div>\');
			});
			</script>';
		}else {
			echo '
			<script>
			jQuery(function(){
				jQuery("#woocommerce-order-items").before(\'<div class="metabox-holder"><div class="postbox"><h3>FraudLabs Pro Details</h3><blockquote>This order has not been screened by FraudLabs Pro.</blockquote></div></div>\');
			});
			</script>';
		}
	}

	private function hash_string($s) {
		$hash = 'fraudlabspro_' . $s;

		for ($i = 0; $i < 65536; $i++)
			$hash = sha1('fraudlabspro_' . $hash);

		return $hash;
	}

	public function admin_page() {
		add_submenu_page('woocommerce', __('FraudLabs Pro', $this->domain), __('FraudLabs Pro', $this->domain), 'manage_woocommerce', 'woo-fraudlabs-pro', array($this, 'admin_options'));
	}

	public function activate() {
		// Initial default settings
		update_option('woo_fraudlabspro_enabled', 1);
		update_option('woo_fraudlabspro_api_key', '');
		update_option('woo_fraudlabspro_score', 60);
		update_option('woo_fraudlabspro_test_ip', '');
		update_option('woo_fraudlabspro_message', __('Thank you for shopping with us. Your order will be reviewed by us shortly.', 'fraudlabs-pro-woocommerce'));
	}

	public function uninstall() {
		// Remove all settings
		delete_option('woo_fraudlabspro_enabled');
		delete_option('woo_fraudlabspro_api_key');
		delete_option('woo_fraudlabspro_score');
		delete_option('woo_fraudlabspro_test_ip');
		delete_option('woo_fraudlabspro_message');
	}
}

// Only initialize plugin if WooCommerce is activated
if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	$FraudLabsPro_WooCommerce = new FraudLabsPro_WooCommerce();
	register_activation_hook(__FILE__, array('FraudLabsPro_WooCommerce', 'activate'));
	register_uninstall_hook(__FILE__, array('FraudLabsPro_WooCommerce', 'uninstall'));
}
?>