<?php
/**
 * Plugin Name: LeanCart Invoice Numbering
 * Plugin URI:  https://vasectomycostguide.com/
 * Description: Gives each new order a sequential invoice number using your configured prefix and starting number. The number appears in order details and emails.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: invoice-numbering
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_INVOICE_NUMBERING_DEMO_SITE' ) ) {
	define( 'LEANCART_INVOICE_NUMBERING_DEMO_SITE', 'https://vasectomycostguide.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Invoice_Numbering {

	const SLUG = 'invoice-numbering';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Invoice Numbering',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_INVOICE_NUMBERING_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'invoice-numbering', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Invoice Numbering' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'invoice-numbering' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'prefix', __('Prefix', 'invoice-numbering'), __('Example: FTR-', 'invoice-numbering'), 'text' );
		LeanCart_Framework::field_text( 'start', __('Starting number', 'invoice-numbering'), __('Default: 1000', 'invoice-numbering'), 'number' );
	}


	public static function hooks() {
		add_action( 'woocommerce_new_order', array( __CLASS__, 'assign' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'admin_show' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'customer_show' ) );
		add_filter( 'woocommerce_email_order_meta_fields', array( __CLASS__, 'email_show' ), 10, 3 );
	}

	public static function assign( $order_id, $order ) {
		if ( ! is_object( $order ) && function_exists( 'wc_get_order' ) ) { $order = wc_get_order( $order_id ); }
		if ( ! is_object( $order ) || $order->get_meta( '_lc_invoice_no' ) ) { return; }
		global $wpdb;
		$start = LeanCart_Framework::setting( 'start', 1000 );
		$start = ( '' === $start || ! is_numeric( $start ) ) ? 1000 : max( 1, (int) $start );
		// Atomic increment: LAST_INSERT_ID() is connection-specific; concurrent orders cannot read each other's numbers.
		$affected = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no') ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(option_value + 1)", 'lc_invoice_counter', $start ) );
		// In the case of INSERT (1 row affected), MariaDB overwrites the LAST_INSERT_ID() value
		// with the option_id AUTO_INCREMENT; use the starting value directly.
		$counter = ( 1 === (int) $affected ) ? $start : (int) $wpdb->get_var( "SELECT LAST_INSERT_ID()" );
		if ( $counter < 1 ) { $counter = $start; }
		$prefix = LeanCart_Framework::setting( 'prefix', 'FTR-' );
		$order->update_meta_data( '_lc_invoice_no', $prefix . $counter );
		$order->save_meta_data();
	}

	public static function admin_show( $order ) {
		$no = $order->get_meta( '_lc_invoice_no' );
		if ( $no ) {
			echo '<p class="form-field"><strong>' . esc_html__( 'Invoice No:', 'invoice-numbering' ) . '</strong> ' . esc_html( $no ) . '</p>';
		}
	}

	public static function customer_show( $order ) {
		$no = $order->get_meta( '_lc_invoice_no' );
		if ( $no ) {
			echo '<p><strong>' . esc_html__( 'Invoice No:', 'invoice-numbering' ) . '</strong> ' . esc_html( $no ) . '</p>';
		}
	}

	public static function email_show( $fields, $sent, $order ) {
		$no = $order->get_meta( '_lc_invoice_no' );
		if ( $no ) {
			$fields['lc_invoice'] = array( 'label' => __( 'Invoice No', 'invoice-numbering' ), 'value' => $no );
		}
		return $fields;
	}


}

LeanCart_Invoice_Numbering::init();

