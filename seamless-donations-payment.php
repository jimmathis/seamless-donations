<?php

/*
 Seamless Donations by David Gewirtz, adopted from Allen Snook

 Lab Notes: http://zatzlabs.com/lab-notes/
 Plugin Page: http://zatzlabs.com/seamless-donations/
 Contact: http://zatzlabs.com/contact-us/

 Copyright (c) 2015 by David Gewirtz
 */

// Load WordPress
include "../../../wp-config.php";

// Load Seamless Donations Core
require_once './inc/geography.php';
require_once './inc/currency.php';
require_once './inc/utilities.php';
require_once './inc/legacy.php';
require_once './inc/donations.php';

require_once './legacy/dgx-donate.php';
require_once './legacy/dgx-donate-admin.php';
require_once './seamless-donations-admin.php';
require_once './seamless-donations-form.php';
require_once './dgx-donate-paypalstd.php';

// Log
dgx_donate_debug_log ( '----------------------------------------' );
dgx_donate_debug_log ( 'DONATION TRANSACTION STARTED' );
dgx_donate_debug_log ( 'Test mode: A' );
$php_version = phpversion ();
dgx_donate_debug_log ( "PHP Version: $php_version" );
dgx_donate_debug_log ( "Seamless Donations Version: " . dgx_donate_get_version () );
dgx_donate_debug_log ( "User browser: " . seamless_donations_get_browser_name () );
dgx_donate_debug_log ( 'IPN: ' . plugins_url ( '/dgx-donate-paypalstd-ipn.php', __FILE__ ) );

$nonce = $_POST['nonce'];
if( ! wp_verify_nonce ( $nonce, 'dgx-donate-nonce' ) ) {
	dgx_donate_debug_log ( 'Payment process nonce validation failure.' );
	die( 'Busted!' );
} else {
	dgx_donate_debug_log ( "Payment process nonce $nonce validated." );
}

// todo: not getting session ID ***************************************************
// todo: reattach the javascript verification code

$sd4_mode   = get_option ( 'dgx_donate_start_in_sd4_mode' );
$session_id = $_POST['_dgx_donate_session_id'];
dgx_donate_debug_log ( "Session ID retrieved from _POST: $session_id" );

// now attempt to retrieve session data to see if it already exists (which would trigger an error)
if( $sd4_mode == false ) {
	// use the old transient system
	$session_data = get_transient ( $session_id );
	dgx_donate_debug_log ( 'Looking for pre-existing session data (legacy transient mode): ' . $session_id );
} else {
	// use the new guid/audit db system
	$session_data = seamless_donations_get_audit_option ( $session_id );
	dgx_donate_debug_log ( 'Looking for pre-existing session data (guid/audit db mode): ' . $session_id );
}

if( $session_data !== false ) {
	dgx_donate_debug_log ( 'Session data already exists, returning false' );
	die();
} else {

	dgx_donate_debug_log ( 'Duplicate session data not found. Payment process data assembly can proceed.' );

	// Repack the POST
	$post_data                     = array();
	$post_data['REFERRINGURL']     = $_POST['_dgx_donate_redirect_url'];
	$post_data['SUCCESSURL']     = $_POST['_dgx_donate_success_url'];
	$post_data['SESSIONID']        = $_POST['_dgx_donate_session_id'];
	$post_data['REPEATING']        = $_POST['_dgx_donate_repeating'];
	$post_data['DESIGNATED']       = $_POST['_dgx_donate_designated'];
	$post_data['DESIGNATEDFUND']   = $_POST['_dgx_donate_designated_fund'];
	$post_data['TRIBUTEGIFT']      = $_POST['_dgx_donate_tribute_gift'];
	$post_data['MEMORIALGIFT']     = $_POST['_dgx_donate_memorial_gift'];
	$post_data['HONOREENAME']      = $_POST['_dgx_donate_honoree_name'];
	$post_data['HONORBYEMAIL']     = $_POST['_dgx_donate_honor_by_email'];
	$post_data['HONOREEEMAIL']     = $_POST['_dgx_donate_honoree_email'];
	$post_data['HONOREEADDRESS']   = $_POST['_dgx_donate_honoree_address'];
	$post_data['HONOREECITY']      = $_POST['_dgx_donate_honoree_city'];
	$post_data['HONOREESTATE']     = $_POST['_dgx_donate_honoree_state'];
	$post_data['HONOREEPROVINCE']  = $_POST['_dgx_donate_honoree_province'];
	$post_data['HONOREECOUNTRY']   = $_POST['_dgx_donate_honoree_country'];
	$post_data['HONOREEZIP']       = $_POST['_dgx_donate_honoree_zip'];
	$post_data['HONOREEEMAILNAME'] = $_POST['_dgx_donate_honoree_email_name'];
	$post_data['HONOREEPOSTNAME']  = $_POST['_dgx_donate_honoree_post_name'];
	$post_data['FIRSTNAME']        = $_POST['_dgx_donate_donor_first_name'];
	$post_data['LASTNAME']         = $_POST['_dgx_donate_donor_last_name'];
	$post_data['PHONE']            = $_POST['_dgx_donate_donor_phone'];
	$post_data['EMAIL']            = $_POST['_dgx_donate_donor_email'];
	$post_data['ADDTOMAILINGLIST'] = $_POST['_dgx_donate_add_to_mailing_list'];
	$post_data['ADDRESS']          = $_POST['_dgx_donate_donor_address'];
	$post_data['ADDRESS2']         = $_POST['_dgx_donate_donor_address2'];
	$post_data['CITY']             = $_POST['_dgx_donate_donor_city'];
	$post_data['STATE']            = $_POST['_dgx_donate_donor_state'];
	$post_data['PROVINCE']         = $_POST['_dgx_donate_donor_province'];
	$post_data['COUNTRY']          = $_POST['_dgx_donate_donor_country'];
	$post_data['ZIP']              = $_POST['_dgx_donate_donor_zip'];
	$post_data['INCREASETOCOVER']  = $_POST['_dgx_donate_increase_to_cover'];
	$post_data['ANONYMOUS']        = $_POST['_dgx_donate_anonymous'];
	$post_data['EMPLOYERMATCH']    = $_POST['_dgx_donate_employer_match'];
	$post_data['EMPLOYERNAME']     = $_POST['_dgx_donate_employer_name'];
	$post_data['OCCUPATION']       = $_POST['_dgx_donate_occupation'];
	$post_data['UKGIFTAID']        = $_POST['_dgx_donate_uk_gift_aid'];

	// Resolve the donation amount
	if( strcasecmp ( $_POST['_dgx_donate_amount'], "OTHER" ) == 0 ) {
		$post_data['AMOUNT'] = floatval ( $_POST['_dgx_donate_user_amount'] );
	} else {
		$post_data['AMOUNT'] = floatval ( $_POST['_dgx_donate_amount'] );
	}
	if( $post_data['AMOUNT'] < 1.00 ) {
		$post_data['AMOUNT'] = 1.00;
	}

	if( 'US' == $post_data['HONOREECOUNTRY'] ) {
		$post_data['PROVINCE'] = '';
	} else if( 'CA' == $post_data['HONOREECOUNTRY'] ) {
		$post_data['HONOREESTATE'] = '';
	} else {
		$post_data['HONOREESTATE']    = '';
		$post_data['HONOREEPROVINCE'] = '';
	}

	if( 'US' == $post_data['COUNTRY'] ) {
		$post_data['PROVINCE'] = '';
	} else if( 'CA' == $post_data['COUNTRY'] ) {
		$post_data['STATE'] = '';
	} else {
		$post_data['STATE']    = '';
		$post_data['PROVINCE'] = '';
	}

	$post_data['PAYMENTMETHOD'] = "PayPal"; // $_POST['dgx_donate_payment_method']
	$post_data['SDVERSION']     = dgx_donate_get_version ();

	// Sanitize the data (remove leading, trailing spaces quotes, brackets)
	foreach( $post_data as $key => $value ) {
		$temp              = trim ( $value );
		$temp              = str_replace ( "\"", "", $temp );
		$temp              = strip_tags ( $temp );
		$post_data[ $key ] = $temp;
	}

	if( $sd4_mode == false ) {
		// Save it all in a transient
		$transient_token = $post_data['SESSIONID'];
		set_transient ( $transient_token, $post_data, 7 * 24 * 60 * 60 ); // 7 days
		dgx_donate_debug_log ( 'Saving transaction data using legacy mode' );
	} else {
		seamless_donations_update_audit_option ( $session_id, $post_data );
		dgx_donate_debug_log ( 'Saving transaction data using guid/audit db mode' );
	}

	// more log data
	dgx_donate_debug_log ( 'Name: ' . $post_data['FIRSTNAME'] . ' ' . $post_data['LASTNAME'] );
	dgx_donate_debug_log ( 'Amount: ' . $post_data['AMOUNT'] );

	dgx_donate_debug_log ( "Preparation complete. Entering PHP post code." );

	// new posting code
	// Build the PayPal query string
	$post_args = "?";

	$post_args .= "first_name=" . urlencode ( $post_data['FIRSTNAME'] ) . "&";
	$post_args .= "last_name=" . urlencode ( $post_data['LASTNAME'] ) . "&";
	$post_args .= "address1=" . urlencode ( $post_data['ADDRESS'] ) . "&";
	$post_args .= "address2=" . urlencode ( $post_data['ADDRESS2'] ) . "&";
	$post_args .= "city=" . urlencode ( $post_data['CITY'] ) . "&";
	$post_args .= "zip=" . urlencode ( $post_data['ZIP'] ) . "&";

	if( 'US' == $post_data['COUNTRY'] ) {
		$post_args .= "state=" . urlencode ( $post_data['STATE']  ) . "&";
	} else {
		if( 'CA' == $post_data['COUNTRY'] ) {
			$post_args .= "state=" . urlencode ( $post_data['PROVINCE']  ) . "&";
		}
	}

	$post_args .= "country=" . urlencode ( $post_data['COUNTRY'] ) . "&";
	$post_args .= "email=" . urlencode ( $post_data['EMAIL'] ) . "&";
	$post_args .= "custom=" . urlencode ( $post_data['SESSIONID'] ) . "&";

	if( $repeating == '' ) {
		$post_args .= "amount=" . urlencode ( $post_data['AMOUNT'] ) . "&";
		$post_args .= "cmd=" . urlencode ( '_donations' ) . "&";
	} else {
		$post_args .= "cmd=" . urlencode ( '_xclick-subscriptions' ) . "&";
		$post_args .= "p3=" . urlencode ( '1' ) . "&";  // 1, M = monthly
		$post_args .= "t3=" . urlencode ( 'M' ) . "&";
		$post_args .= "a3=" . urlencode ( $post_data['AMOUNT'] ) . "&";
	}

	$notifyUrl   = plugins_url ( '/dgx-donate-paypalstd-ipn.php', __FILE__ );
	$successUrl  = $post_data['SUCCESSURL'] . "?thanks=true";
	$paypalEmail = get_option ( 'dgx_donate_paypal_email' );
	$currency_code = get_option ( 'dgx_donate_currency' );

	dgx_donate_debug_log ( "Success URL: $successUrl" );

	$post_args .= "business=" . urlencode ( $paypalEmail ) . "&";
	$post_args .= "return=" . urlencode ( $successUrl ) . "&";
	$post_args .= "notify_url=" . urlencode ( $notifyUrl ) . "&";
	// $post_args .= "item_name=" . urlencode ( $item_name ) . "&";
	$post_args .= "quantity=" . urlencode ( '1' ) . "&";
	$post_args .= "currency_code=" . urlencode ( $currency_code ) . "&";
	$post_args .= "no_note=" . urlencode ( '1' ) . "&";

	$payPalServer = get_option ( 'dgx_donate_paypal_server' );
	if( $payPalServer == "SANDBOX" ) {
		$form_action = "https://www.sandbox.paypal.com/cgi-bin/webscr";
	} else {
		$form_action = "https://www.paypal.com/cgi-bin/webscr";
	}

//	var_dump ( $post_args );
//
//	die();

	// dgx_donate_debug_log ( "Post args: " . $post_args );

	dgx_donate_debug_log ( "Redirecting to PayPal... now!" );

	wp_redirect ( $form_action . $post_args );
	exit;
}