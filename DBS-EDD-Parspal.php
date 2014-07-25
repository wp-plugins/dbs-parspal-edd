<?php

/*
	Plugin Name: DBS Parspal EDD - درگاه پرداخت پارس پال 
	Plugin URI: http://www.dbstheme.com/?s=parspal
	Version: 1.2
	Description: درگاه بهینه سازی شده پارس پال برای افزونه ایزی دیجیال داونلودز.
	Author: دی بی اس تم
	Author URI: www.dbstheme.com
*/

@session_start();

require_once(plugin_dir_path( __FILE__ ) . '/lib/DBS-Parspal.class.php');

function add_iran_currencies( $currencies ) {
	$currencies['IRT'] = 'تومان ایران';
	$currencies['IRR'] = 'ریال ایران';
	return $currencies;
}
add_filter('edd_currencies', 'add_iran_currencies');

function IRR_format($formatted, $currency, $price) {
	return $price . ' ریال';
}
add_filter( 'edd_irr_currency_filter_after', 'IRR_format', 10, 3 );
add_filter( 'edd_irr_currency_filter_before', 'IRR_format', 10, 3 );

function IRT_format($formatted, $currency, $price) {
	return $price . ' تومان';
}
add_filter( 'edd_irt_currency_filter_after', 'IRT_format', 10, 3 );
add_filter( 'edd_irt_currency_filter_before', 'IRT_format', 10, 3 );

function remove_iran_decimal( $decimals ) {
	global $edd_options;
	if( $edd_options['currency'] == 'IRI' || $edd_options['currency'] == 'IRT' ) {
		return 0;
	}
}
add_filter( 'edd_format_amount_decimals', 'remove_iran_decimal' );

function add_iran_gateway($gateways) {
	$gateways['parspal'] = array(
		'admin_label' => 'درگاه پارس پال',
		'checkout_label' => 'پرداخت آنلاین با درگاه پارس پال'
	);
	return $gateways;
}
add_filter( 'edd_payment_gateways', 'add_iran_gateway' );

function empty_cc_form(){
	return;
}
add_filter( 'edd_parspal_cc_form', 'empty_cc_form' );

function parspal_pay($purchase) {

	global $edd_options;
	
	$payment = array( 
		'price' => $purchase['price'], 
		'date' => $purchase['date'], 
		'user_email' => $purchase['post_data']['edd_email'],
		'purchase_key' => $purchase['purchase_key'],
		'currency' => $edd_options['currency'],
		'downloads' => $purchase['downloads'],
		'cart_details' => $purchase['cart_details'],
		'user_info' => $purchase['user_info'],
		'status' => 'pending'
	);
	$payment = edd_insert_payment($payment);
	
	if ($payment) {

		$_SESSION['parspal_payment_id'] = $payment;

		$return = add_query_arg('order', 'parspal', get_permalink($edd_options['success_page']));

		$price = $purchase['price'];
		if( $edd_options['currency'] == 'IRR' ){
			$price = $price/10;
		}
		$_SESSION['parspal_payment_price'] = $price;
		
		$pnames ='';
		foreach ($purchase['cart_details'] as $key) {
			$pnames .= $key['name'] . '/ ';
		}
		$pnames = rtrim( $pnames, '/ ' );

		$name = $purchase['user_info']['first_name'] . ' ' . $purchase['user_info']['last_name'];
		$email = $purchase['post_data']['edd_email'];

		$DBSParspal = new DBSParspal();
		$DBSParspal -> Set($edd_options['PP_ID'], $edd_options['PP_Pass']);
	
		$go = array(
			'price'  => $price,
			'return' => $return,
			'resnum' => $payment,
			'desc'   => $pnames,
			'payer'  => $name,
			'mail'   => $email,
			'mob'    => '00000000000'
		);
	
		$DBSParspal -> Go($go);
				
		edd_empty_cart();	

		exit;
		
	} else {
		wp_die('متاسفانه مشکلی در ایجاد پرداخت در حالت در حال انتظار وجود داشته است. لطفا با مدیر تارنما تماس بگیرید.');
	}
}
add_action('edd_gateway_parspal', 'parspal_pay');

function parspal_pay_check() {

	global $edd_options;

	if (isset($_GET['order']) && $_GET['order'] == 'parspal' && isset($_POST['refnumber']) && isset($_POST['resnumber'])) {
 
		if(isset($_POST['status']) && $_POST['status'] == 100){

			$price = $_SESSION['parspal_payment_price'];
			$payment = $_SESSION['parspal_payment_id']; 
			$ref = $_POST['refnumber'];

			$DBSParspal = new DBSParspal();
			$DBSParspal -> Set($edd_options['PP_ID'], $edd_options['PP_Pass']);

			$res = $DBSParspal -> Check( $price, $ref );

			if ($res == 'success') {
				edd_update_payment_status($payment, 'publish');
				edd_send_to_success_page('?action=done');
			} else {
				wp_die('متاسفانه مشکلی در بازگشت تراکنش وجود داشته است. لطفا با مدیر تارنما تماس بگیرید. خطا شماره: ' . $res );
			}
		
		} elseif(isset($_POST['status']) && $_POST['status'] != 100) { 
			wp_die('متاسفانه مشکلی در تایید تراکنش واریز وجود داشته است. لطفا با مدیر تارنما تماس بگیرید. خطا شماره: ' . $_POST['status'] );
		}
	}
}
add_action('init', 'parspal_pay_check');

function parspal_settings($settings) {
	$parspal_settings = array (
		array (
			'id'		=>	'parspal_settings',
			'name'		=>	'<strong>پیکربندی درگاه پارس پال</strong>',
			'desc'		=>	'پیکربندی درگاه پارس پال با نسخه دی بی اس تم',
			'type'		=>	'header'
		),
		array (
			'id'		=>	'PP_ID',
			'name'		=>	'آی دی مرچنت درگاه',
			'desc'		=>	'MerchantID درگاه خود را وارد کنید',
			'type'		=>	'text',
			'size'		=>	'regular'
		),
		array (
			'id'		=>	'PP_Pass',
			'name'		=>	'رمز عبور',
			'desc'		=>	'رمز عبور درگاه خود را وارد کنید',
			'type'		=>	'text',
			'size'		=>	'regular'
		)
	);
	return array_merge( $settings, $parspal_settings );
}
add_filter('edd_settings_gateways', 'parspal_settings');

?>