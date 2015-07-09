<?php
/**
 * Plugin Name: FraudLabs Pro for WooCommerce
 * Plugin URI: http://www.fraudlabspro.com
 * Description: This plugin is an add-on for WooCommerce plugin that help you to screen your order transaction, such as credit card transaction, for online fraud.
 * Author: FraudLabs Pro
 * Author URI: http://www.fraudlabspro.com/
 * Version: 2.2.1
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

if ( ! class_exists( 'FraudLabsPro_WooCommerce' ) ) :

class FraudLabsPro_WooCommerce {

	/**
	* Construct the plugin.
	*/
	public function __construct( ) {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}


	/**
	* Initialize the plugin.
	*/
	public function init( ) {

		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {
			// Include our integration class.
			include_once 'includes/class-wc-fraudlabspro-woocommerce.php';

			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		}
	}


	/**
	 * Add a new integration to WooCommerce.
	 */
	public function add_integration( $integrations ) {
		$integrations[] = 'WC_Integration_FraudLabs_Pro';

		return $integrations;
	}

}

// Only initialize plugin if WooCommerce is activated
if( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	$FraudLabsPro_WooCommerce = new FraudLabsPro_WooCommerce( __FILE__ );
}

endif;
