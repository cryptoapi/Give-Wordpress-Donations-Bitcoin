<?php
/**
 * Admin Payment Actions
 *
 * @package     Give
 * @subpackage  Admin/Payments
 * @copyright   Copyright (c) 2015, WordImpress
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Process the payment details edit
 *
 * @access      private
 * @since       1.0
 * @return      void
 */
function give_update_payment_details( $data ) {

	if ( ! current_user_can( 'edit_give_payments', $data['give_payment_id'] ) ) {
		wp_die( __( 'You do not have permission to edit this payment record', 'give' ), __( 'Error', 'give' ), array( 'response' => 403 ) );
	}

	check_admin_referer( 'give_update_payment_details_nonce' );

	// Retrieve the payment ID
	$payment_id = absint( $data['give_payment_id'] );

	// Retrieve existing payment meta
	$meta      = give_get_payment_meta( $payment_id );
	$user_info = give_get_payment_meta_user_info( $payment_id );

	$status  = $data['give-payment-status'];
	$user_id = intval( $data['give-payment-user-id'] );
	$date    = sanitize_text_field( $data['give-payment-date'] );
	$hour    = sanitize_text_field( $data['give-payment-time-hour'] );
	$minute  = sanitize_text_field( $data['give-payment-time-min'] );
	$email   = sanitize_text_field( $data['give-payment-user-email'] );
	$names   = sanitize_text_field( $data['give-payment-user-name'] );
	$address = array_map( 'trim', $data['give-payment-address'][0] );

	$curr_total = give_sanitize_amount( give_get_payment_amount( $payment_id ) );
	$new_total  = give_sanitize_amount( $_POST['give-payment-total'] );

	// Setup date from input values
	$date = date( 'Y-m-d', strtotime( $date ) ) . ' ' . $hour . ':' . $minute . ':00';

	// Setup first and last name from input values
	$names      = explode( ' ', $names );
	$first_name = ! empty( $names[0] ) ? $names[0] : '';
	$last_name  = '';
	if ( ! empty( $names[1] ) ) {
		unset( $names[0] );
		$last_name = implode( ' ', $names );
	}

	do_action( 'give_update_edited_purchase', $payment_id );

	// Update main payment record
	$updated = wp_update_post( array(
		'ID'        => $payment_id,
		'post_date' => $date
	) );

	if ( 0 === $updated ) {
		wp_die( __( 'Error Updating Payment', 'give' ), __( 'Error', 'give' ), array( 'response' => 400 ) );
	}

	if ( $user_id !== $user_info['id'] || $email !== $user_info['email'] ) {

		$user = get_user_by( 'id', $user_id );
		if ( ! empty( $user ) && strtolower( $user->data->user_email ) !== strtolower( $email ) ) {
			// protect a purcahse from being assigned to a customer with a user ID and Email that belong to different users
			wp_die( __( 'User ID and User Email do not match.', 'give' ), __( 'Error', 'give' ), array( 'response' => 400 ) );
			exit;
		}

		// Remove the stats and payment from the previous customer
		$previous_customer = Give()->customers->get_by( 'email', $user_info['email'] );
		Give()->customers->remove_payment( $previous_customer->id, $payment_id );

		// Attribute the payment to the new customer and update the payment post meta
		$new_customer_id = Give()->customers->get_column_by( 'id', 'email', $email );

		if ( ! $new_customer ) {

			// No customer exists for the given email so create one
			$new_customer_id = Give()->customers->add( array(
				'email' => $email,
				'name'  => $first_name . ' ' . $last_name
			) );

		}

		Give()->customers->attach_payment( $new_customer_id, $payment_id );

		// If purchase was completed and not ever refunded, adjust stats of customers
		if ( 'revoked' == $status || 'publish' == $status ) {

			Give()->customers->decrement_stats( $previous_customer->id, $total );
			Give()->customers->increment_stats( $new_customer_id, $total );

		}

		update_post_meta( $payment_id, '_give_payment_customer_id', $new_customer_id );
	}

	// Set new meta values
	$user_info['id']         = $user_id;
	$user_info['email']      = $email;
	$user_info['first_name'] = $first_name;
	$user_info['last_name']  = $last_name;
	$user_info['address']    = $address;
	$meta['user_info']       = $user_info;


	// Check for payment notes
	if ( ! empty( $data['give-payment-note'] ) ) {

		$note = wp_kses( $data['give-payment-note'], array() );
		give_insert_payment_note( $payment_id, $note );

	}

	// Set new status
	give_update_payment_status( $payment_id, $status );

	give_update_payment_meta( $payment_id, '_give_payment_user_id', $user_id );
	give_update_payment_meta( $payment_id, '_give_payment_user_email', $email );
	give_update_payment_meta( $payment_id, '_give_payment_meta', $meta );
	give_update_payment_meta( $payment_id, '_give_payment_total', $new_total );

	// Adjust total store earnings if the payment total has been changed
	if ( $new_total !== $curr_total && ( 'publish' == $status || 'revoked' == $status ) ) {

		$total_earnings = get_option( 'give_earnings_total' ) - $curr_total + $new_total;
		update_option( 'give_earnings_total', $total_earnings );

	}

	do_action( 'give_updated_edited_purchase', $payment_id );

	wp_safe_redirect( admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-order-details&give-message=payment-updated&id=' . $payment_id ) );
	exit;
}

add_action( 'give_update_payment_details', 'give_update_payment_details' );

/**
 * Trigger a Purchase Deletion
 *
 * @since 1.0
 *
 * @param $data Arguments passed
 *
 * @return void
 */
function give_trigger_purchase_delete( $data ) {
	if ( wp_verify_nonce( $data['_wpnonce'], 'give_payment_nonce' ) ) {

		$payment_id = absint( $data['purchase_id'] );

		if ( ! current_user_can( 'edit_give_payments', $payment_id ) ) {
			wp_die( __( 'You do not have permission to edit this payment record', 'give' ), __( 'Error', 'give' ), array( 'response' => 403 ) );
		}

		give_delete_purchase( $payment_id );
		wp_redirect( admin_url( '/edit.php?post_type=give_forms&page=give-payment-history&give-message=payment_deleted' ) );
		give_die();
	}
}

add_action( 'give_delete_payment', 'give_trigger_purchase_delete' );

function give_ajax_store_payment_note() {

	$payment_id = absint( $_POST['payment_id'] );
	$note       = wp_kses( $_POST['note'], array() );

	if ( ! current_user_can( 'edit_give_payments', $payment_id ) ) {
		wp_die( __( 'You do not have permission to edit this payment record', 'give' ), __( 'Error', 'give' ), array( 'response' => 403 ) );
	}

	if ( empty( $payment_id ) ) {
		die( '-1' );
	}

	if ( empty( $note ) ) {
		die( '-1' );
	}

	$note_id = give_insert_payment_note( $payment_id, $note );
	die( give_get_payment_note_html( $note_id ) );
}

add_action( 'wp_ajax_give_insert_payment_note', 'give_ajax_store_payment_note' );

/**
 * Triggers a payment note deletion without ajax
 *
 * @since 1.0
 *
 * @param array $data Arguments passed
 *
 * @return void
 */
function give_trigger_payment_note_deletion( $data ) {

	if ( ! wp_verify_nonce( $data['_wpnonce'], 'give_delete_payment_note_' . $data['note_id'] ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_give_payments', $data['payment_id'] ) ) {
		wp_die( __( 'You do not have permission to edit this payment record', 'give' ), __( 'Error', 'give' ), array( 'response' => 403 ) );
	}

	$edit_order_url = admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-order-details&give-message=payment-note-deleted&id=' . absint( $data['payment_id'] ) );

	give_delete_payment_note( $data['note_id'], $data['payment_id'] );

	wp_redirect( $edit_order_url );
}

add_action( 'give_delete_payment_note', 'give_trigger_payment_note_deletion' );

/**
 * Delete a payment note deletion with ajax
 *
 * @since 1.0
 *
 * @param array $data Arguments passed
 *
 * @return void
 */
function give_ajax_delete_payment_note() {

	if ( ! current_user_can( 'edit_give_payments', $_POST['payment_id'] ) ) {
		wp_die( __( 'You do not have permission to edit this payment record', 'give' ), __( 'Error', 'give' ), array( 'response' => 403 ) );
	}

	if ( give_delete_payment_note( $_POST['note_id'], $_POST['payment_id'] ) ) {
		die( '1' );
	} else {
		die( '-1' );
	}

}

add_action( 'wp_ajax_give_delete_payment_note', 'give_ajax_delete_payment_note' );