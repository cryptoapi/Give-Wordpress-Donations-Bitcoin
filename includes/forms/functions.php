<?php
/**
 * Give Form Functions
 *
 * @package     WordImpress
 * @subpackage  Forms
 * @copyright   Copyright (c) 2015, WordImpress
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determines if a user can checkout or not
 *
 * @since 1.0
 * @global $give_options Array of all the Give Options
 * @return bool Can user checkout?
 */
function give_can_checkout() {
	global $give_options;

	$can_checkout = true; // Always true for now

	return (bool) apply_filters( 'give_can_checkout', $can_checkout );
}

/**
 * Retrieve the Success page URI
 *
 * @access      public
 * @since       1.0
 * @return      string
 */
function give_get_success_page_uri() {
	global $give_options;

	$page_id = isset( $give_options['success_page'] ) ? absint( $give_options['success_page'] ) : 0;

	return apply_filters( 'give_get_success_page_uri', get_permalink( $give_options['success_page'] ) );
}

/**
 * Determines if we're currently on the Success page.
 *
 * @since 1.0
 * @return bool True if on the Success page, false otherwise.
 */
function give_is_success_page() {
	global $give_options;
	$is_success_page = isset( $give_options['success_page'] ) ? is_page( $give_options['success_page'] ) : false;

	return apply_filters( 'give_is_success_page', $is_success_page );
}

/**
 * Send To Success Page
 *
 * Sends the user to the succes page.
 *
 * @param string $query_string
 *
 * @access      public
 * @since       1.0
 * @return      void
 */
function give_send_to_success_page( $query_string = null ) {
	global $give_options;

	$redirect = give_get_success_page_uri();

	if ( $query_string ) {
		$redirect .= $query_string;
	}

	$gateway = isset( $_REQUEST['give-gateway'] ) ? $_REQUEST['give-gateway'] : '';

	wp_redirect( apply_filters( 'give_success_page_redirect', $redirect, $gateway, $query_string ) );
	give_die();
}


/**
 * Send back to checkout.
 *
 * Used to redirect a user back to the purchase
 * page if there are errors present.
 *
 * @param array $args
 *
 * @access public
 * @since  1.0
 * @return Void
 */
function give_send_back_to_checkout( $args = array() ) {

	$redirect = ( isset( $_POST['give-current-url'] ) ) ? $_POST['give-current-url'] : '';

	if ( ! empty( $args ) ) {
		// Check for backward compatibility
		if ( is_string( $args ) ) {
			$args = str_replace( '?', '', $args );
		}

		$args = wp_parse_args( $args );

		$redirect = esc_url( add_query_arg( $args, $redirect ) );
	}

	wp_redirect( apply_filters( 'give_send_back_to_checkout', $redirect, $args ) );
	give_die();
}

/**
 * Get Success Page URL
 *
 * Gets the success page URL.
 *
 * @param string $query_string
 *
 * @access      public
 * @since       1.0
 * @return      string
 */
function give_get_success_page_url( $query_string = null ) {
	global $give_options;

	$success_page = get_permalink( $give_options['success_page'] );
	if ( $query_string ) {
		$success_page .= $query_string;
	}

	return apply_filters( 'give_success_page_url', $success_page );
}

/**
 * Get the URL of the Transaction Failed page
 *
 * @since 1.0
 * @global     $give_options Array of all the Give Options
 *
 * @param bool $extras       Extras to append to the URL
 *
 * @return mixed|void Full URL to the Transaction Failed page, if present, home page if it doesn't exist
 */
function give_get_failed_transaction_uri( $extras = false ) {
	global $give_options;

	$uri = ! empty( $give_options['failure_page'] ) ? trailingslashit( get_permalink( $give_options['failure_page'] ) ) : home_url();
	if ( $extras ) {
		$uri .= $extras;
	}

	return apply_filters( 'give_get_failed_transaction_uri', $uri );
}

/**
 * Determines if we're currently on the Failed Transaction page.
 *
 * @since 1.0
 * @return bool True if on the Failed Transaction page, false otherwise.
 */
function give_is_failed_transaction_page() {
	global $give_options;
	$ret = isset( $give_options['failure_page'] ) ? is_page( $give_options['failure_page'] ) : false;

	return apply_filters( 'give_is_failure_page', $ret );
}

/**
 * Mark payments as Failed when returning to the Failed Transaction page
 *
 * @access      public
 * @since       1.0
 * @return      void
 */
function give_listen_for_failed_payments() {

	$failed_page = give_get_option( 'failure_page', 0 );

	if ( ! empty( $failed_page ) && is_page( $failed_page ) && ! empty( $_GET['payment-id'] ) ) {

		$payment_id = absint( $_GET['payment-id'] );
		give_update_payment_status( $payment_id, 'failed' );

	}

}

add_action( 'template_redirect', 'give_listen_for_failed_payments' );


/**
 * Check if a field is required
 *
 * @param string $field
 *
 * @access      public
 * @since       1.0
 * @return      bool
 */
function give_field_is_required( $field = '' ) {
	$required_fields = give_purchase_form_required_fields();

	return array_key_exists( $field, $required_fields );
}

/**
 * Retrieve an array of banned_emails
 *
 * @since       1.0
 * @return      array
 */
function give_get_banned_emails() {
	$emails = array_map( 'trim', give_get_option( 'banned_emails', array() ) );

	return apply_filters( 'give_get_banned_emails', $emails );
}

/**
 * Determines if an email is banned
 *
 * @since       2.0
 * @return      bool
 */
function give_is_email_banned( $email = '' ) {

	if ( empty( $email ) ) {
		return false;
	}

	$ret = in_array( trim( $email ), give_get_banned_emails() );

	return apply_filters( 'give_is_email_banned', $ret, $email );
}

/**
 * Determines if secure checkout pages are enforced
 *
 * @since       1.0
 * @return      bool True if enforce SSL is enabled, false otherwise
 */
function give_is_ssl_enforced() {
	$ssl_enforced = give_get_option( 'enforce_ssl', false );

	return (bool) apply_filters( 'give_is_ssl_enforced', $ssl_enforced );
}

/**
 * Handle redirections for SSL enforced checkouts
 *
 * @since 1.0
 * @global $give_options Array of all the Give Options
 * @return void
 */
function give_enforced_ssl_redirect_handler() {
	if ( ! give_is_ssl_enforced() || is_admin() || is_ssl() ) {
		return;
	}

	if ( isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == "on" ) {
		return;
	}

	$uri = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	wp_safe_redirect( $uri );
	exit;
}

//add_action( 'template_redirect', 'give_enforced_ssl_redirect_handler' );

/**
 * Handle rewriting asset URLs for SSL enforced checkouts
 *
 * @since 1.0
 * @return void
 */
function give_enforced_ssl_asset_handler() {
	if ( ! give_is_ssl_enforced() || is_admin() ) {
		return;
	}

	$filters = array(
		'post_thumbnail_html',
		'wp_get_attachment_url',
		'wp_get_attachment_image_attributes',
		'wp_get_attachment_url',
		'option_stylesheet_url',
		'option_template_url',
		'script_loader_src',
		'style_loader_src',
		'template_directory_uri',
		'stylesheet_directory_uri',
		'site_url'
	);

	$filters = apply_filters( 'give_enforced_ssl_asset_filters', $filters );

	foreach ( $filters as $filter ) {
		add_filter( $filter, 'give_enforced_ssl_asset_filter', 1 );
	}
}

//add_action( 'template_redirect', 'give_enforced_ssl_asset_handler' );

/**
 * Filter filters and convert http to https
 *
 * @since 1.0
 *
 * @param mixed $content
 *
 * @return mixed
 */
function give_enforced_ssl_asset_filter( $content ) {

	if ( is_array( $content ) ) {

		$content = array_map( 'give_enforced_ssl_asset_filter', $content );

	} else {

		// Detect if URL ends in a common domain suffix. We want to only affect assets
		$suffixes = array(
			'br',
			'ca',
			'cn',
			'com',
			'de',
			'dev',
			'edu',
			'fr',
			'in',
			'info',
			'jp',
			'local',
			'mobi',
			'name',
			'net',
			'nz',
			'org',
			'ru',
		);

	}

	return $content;
}


/**
 * Record Sale In Log
 *
 * Stores log information for a form sale.
 *
 * @since 1.0
 * @global            $give_logs
 *
 * @param int         $give_form_id Give Form ID
 * @param int         $payment_id   Payment ID
 * @param bool|int    $price_id     Price ID, if any
 * @param string|null $sale_date    The date of the sale
 *
 * @return void
 */
function give_record_sale_in_log( $give_form_id = 0, $payment_id, $price_id = false, $sale_date = null ) {
	global $give_logs;

	$log_data = array(
		'post_parent'   => $give_form_id,
		'log_type'      => 'sale',
		'post_date'     => isset( $sale_date ) ? $sale_date : null,
		'post_date_gmt' => isset( $sale_date ) ? $sale_date : null
	);

	$log_meta = array(
		'payment_id' => $payment_id,
		'price_id'   => (int) $price_id
	);

	$give_logs->insert_log( $log_data, $log_meta );
}


/**
 *
 * Increases the sale count of a download.
 *
 * @since 1.0
 *
 * @param int $give_form_id Give Form ID
 *
 * @return bool|int
 */
function give_increase_purchase_count( $give_form_id = 0 ) {
	$form = new Give_Donate_Form( $give_form_id );

	return $form->increase_sales();
}

/**
 * Decreases the sale count of a form. Primarily for when a donation is refunded.
 *
 * @since 1.0
 *
 * @param int $give_form_id Give Form ID
 *
 * @return bool|int
 */
function give_decrease_purchase_count( $give_form_id = 0 ) {
	$form = new Give_Donate_Form( $give_form_id );

	return $form->decrease_sales();
}

/**
 * Increases the total earnings of a form.
 *
 * @since 1.0
 *
 * @param int $give_form_id Give Form ID
 * @param int $amount       Earnings
 *
 * @return bool|int
 */
function give_increase_earnings( $give_form_id = 0, $amount ) {
	$form = new Give_Donate_Form( $give_form_id );

	return $form->increase_earnings( $amount );
}

/**
 * Decreases the total earnings of a form. Primarily for when a purchase is refunded.
 *
 * @since 1.0
 *
 * @param int $give_form_id Give Form ID
 * @param int $amount       Earnings
 *
 * @return bool|int
 */
function give_decrease_earnings( $give_form_id = 0, $amount ) {
	$form = new Give_Donate_Form( $give_form_id );

	return $form->decrease_earnings( $amount );
}


/**
 * Returns the total earnings for a form.
 *
 * @since 1.0
 *
 * @param int $give_form_id Give Form ID
 *
 * @return int $earnings Earnings for a certain form
 */
function give_get_form_earnings_stats( $give_form_id = 0 ) {
	$give_form = new Give_Donate_Form( $give_form_id );

	return $give_form->earnings;
}


/**
 * Return the sales number for a form.
 *
 * @since 1.0
 *
 * @param int $give_form_id Give Form ID
 *
 * @return int $sales Amount of sales for a certain form
 */
function give_get_form_sales_stats( $give_form_id = 0 ) {
	$give_form = new Give_Donate_Form( $give_form_id );

	return $give_form->sales;
}


/**
 * Retrieves the average monthly sales for a specific donation form
 *
 * @since 1.0
 *
 * @param int $form_id Form ID
 *
 * @return float $sales Average monthly sales
 */
function give_get_average_monthly_form_sales( $form_id = 0 ) {
	$sales        = give_get_form_sales_stats( $form_id );
	$release_date = get_post_field( 'post_date', $form_id );

	$diff = abs( current_time( 'timestamp' ) - strtotime( $release_date ) );

	$months = floor( $diff / ( 30 * 60 * 60 * 24 ) ); // Number of months since publication

	if ( $months > 0 ) {
		$sales = ( $sales / $months );
	}

	return $sales;
}


/**
 * Retrieves the average monthly earnings for a specific form
 *
 * @since 1.0
 *
 * @param int $form_id Form ID
 *
 * @return float $earnings Average monthly earnings
 */
function give_get_average_monthly_form_earnings( $form_id = 0 ) {
	$earnings     = give_get_form_earnings_stats( $form_id );
	$release_date = get_post_field( 'post_date', $form_id );

	$diff = abs( current_time( 'timestamp' ) - strtotime( $release_date ) );

	$months = floor( $diff / ( 30 * 60 * 60 * 24 ) ); // Number of months since publication

	if ( $months > 0 ) {
		$earnings = ( $earnings / $months );
	}

	return $earnings < 0 ? 0 : $earnings;
}