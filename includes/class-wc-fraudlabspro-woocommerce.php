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
		$this->enabled			= $this->get_option( 'woo_fraudlabspro_enabled' );
		$this->api_key			= $this->get_option( 'woo_fraudlabspro_api_key' );
		$this->risk_score		= $this->get_option( 'woo_fraudlabspro_score' );
		$this->message			= $this->get_option( 'woo_fraudlabspro_message' );
		$this->test_ip			= $this->get_option( 'woo_fraudlabspro_test_ip' );


		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

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
				'desc_tip'          => true,
				'default'           => 'yes'
			),
			'api_key' => array(
				'title'             => __( 'API Key', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'text',
				'description'       => __( 'API key to access FraudLabs Pro service.' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'risk_score' => array(
				'title'             => __( 'Risk Score', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'text',
				'description'       => __( 'Reject the transaction if the calculated risk score higher than this value.', 'woocommerce-fraudlabs-pro' ),
				'desc_tip'          => true,
				'default'           => '60'
			),
			'message' => array(
				'title'             => __( 'Message', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'text',
				'description'       => __( 'Display this message whenever transaction is marked as fraud.', 'woocommerce-fraudlabs-pro' ),
				'desc_tip'          => true,
				'default'           => 'Thank you for shopping with us. Your order will be reviewed by us shortly.'
			),
			'test_ip' => array(
				'title'             => __( 'Test IP', 'woocommerce-fraudlabs-pro' ),
				'type'              => 'text',
				'description'       => __( 'Simulate visitor IP. Clear this value for production run.', 'woocommerce-fraudlabs-pro' ),
				'desc_tip'          => true,
				'default'           => ''
			),
		);

	}


	/**
	 * Validate the Risk score
	 * @see validate_settings_fields()
	 */
	public function validate_risk_score_field( $key ) {
		// get the posted value
		$value = $_POST[ $this->plugin_id . $this->id . '_' . $key ];

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
		$value = $_POST[ $this->plugin_id . $this->id . '_' . $key ];

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
		$value = $_POST[ $this->plugin_id . $this->id . '_' . $key ];

		if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$this->errors[] = 'Test IP is not a valid IP address.';
		}
		return $value;
	}


	/**
	 * Display errors by overriding the display_errors() method
	 * @see display_errors()
	 */
	public function display_errors( ) {

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
