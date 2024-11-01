<?php
/**
 * Plugin Name: Payment Gateway GMOPG for WooCommerce
 * Plugin URI: https://www.wpmarket.jp/product/wc_gmopg_gateway/
 * Description: Take GMOPG payments on your store of WooCommerce.
 * Author: Hiroaki Miyashita
 * Author URI: https://www.wpmarket.jp/
 * Version: 0.1.2
 * Requires at least: 4.4
 * Tested up to: 6.6
 * WC requires at least: 3.0
 * WC tested up to: 9.1.2
 * Text Domain: wc-gmopg-gateway
 * Domain Path: /
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wc_gmopg_gateway_missing_admin_notices() {
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'GMOPG requires WooCommerce to be installed and active. You can download %s here.', 'wc-gmopg-gateway' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

function wc_gmopg_gateway_mode_admin_notices() {
	echo '<div class="error"><p><strong><a href="https://www.wpmarket.jp/product/wc_gmopg_gateway/?domain='.esc_attr($_SERVER['HTTP_HOST']).'" target="_blank">'.__( 'In order to use GMOPG, you have to purchase the authentication key at the following site.', 'wc-gmopg-gateway' ).'</a></strong></p></div>';
}

add_action( 'plugins_loaded', 'wc_gmopg_gateway_plugins_loaded' );
add_filter( 'woocommerce_payment_gateways', 'wc_gmopg_gateway_woocommerce_payment_gateways' );

function wc_gmopg_gateway_plugins_loaded() {
	load_plugin_textdomain( 'wc-gmopg-gateway', false, plugin_basename( dirname( __FILE__ ) ) );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_gmopg_gateway_missing_admin_notices' );
		return;
	}
	
	$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
	if ( empty($gmopg_option['authentication_key']) ) :
		add_action( 'admin_notices', 'wc_gmopg_gateway_mode_admin_notices' );	
	endif;

	if ( ! class_exists( 'WC_Gateway_GMOPG_Credit' ) ) :
		class WC_Gateway_GMOPG_Credit extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = 'gmopg_credit';
				$this->method_title = __('GMOPG - Credit Card', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the credit card payment by GMOPG. You can change the other settings here.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->ShopID = $this->get_option( 'ShopID' );
				$this->ShopPass = $this->get_option( 'ShopPass' );
				$this->configid = $this->get_option( 'configid' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->mode = $this->get_option( 'mode' );
				$this->status = $this->get_option( 'status' );
				$this->logging = $this->get_option( 'logging' );
				$this->authentication_key = $this->get_option( 'authentication_key' );
																				
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
				add_action( 'woocommerce_api_wc_gmopg', array( $this, 'check_for_webhook' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - Credit Card', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Credit Card', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with your credit card', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'ShopID' => array(
						'title'       => __('Shop ID', 'wc-gmopg-gateway'),
						'type'        => 'text',
						'default'     => '' 
					),
					'ShopPass' => array(
						'title'       => __('Shop Password', 'wc-gmopg-gateway'),
						'type'        => 'text',
						'default'     => '' 
					),
					'configid' => array(
						'title'       => __( 'Config ID', 'wc-gmopg-gateway' ),
						'description' => __( 'Please input the Config ID of the Link Type Plus on the GMOPG admin panel.', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'default'     => '',
					),
					'mode'    => array(
						'title' => __('Mode', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'real' => __('Real', 'wc-gmopg-gateway'),
							'sandbox'  => __('Sandbox', 'wc-gmopg-gateway')
						)
					),
					'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
					'logging'    => array(
						'title'       => __( 'Logging', 'wc-gmopg-gateway' ),
						'label'       => __( 'Log debug messages', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'wc-gmopg-gateway' ),
						'default'     => 'no',
						'desc_tip'    => true,
					),
					'authentication_key'    => array(
						'title' => __('Authentication Key', 'wc-gmopg-gateway'),
						'type' => 'text',
						'default' => '',
						'description' => '<a href="https://www.wpmarket.jp/product/wc_gmopg_gateway/?domain='.esc_attr($_SERVER['HTTP_HOST']).'" target="_blank">'.__( 'In order to use GMOPG, you have to purchase the authentication key at the following site.', 'wc-gmopg-gateway' ).'</a>',
					),
				);
			}

			function process_admin_options( ) {
				$this->init_settings();

				$post_data = $this->get_post_data();
				
				$check_value = $this->wc_gmopg_gateway_check_authentication_key( $post_data['woocommerce_gmopg_credit_authentication_key'] );
				if ( $check_value == false ) :
					$_POST['woocommerce_gmopg_credit_authentication_key'] = '';
				endif;
				
				if ( $post_data['woocommerce_gmopg_credit_mode'] == 'real' && $check_value == false ) :
					$_POST['woocommerce_gmopg_credit_mode'] = 'sandbox';
			
					$settings = new WC_Admin_Settings();
         			$settings->add_error( __('Because Authentication Key is not valid, you can not set Real as the mode.', 'wc-gmopg-gateway') );
				endif;

				return parent::process_admin_options();
			}
			
			function wc_gmopg_gateway_check_authentication_key( $auth_key ) {
				$request = wp_remote_get('https://www.wpmarket.jp/auth/?gateway=gmopg&domain='.$_SERVER['HTTP_HOST'].'&auth_key='.$auth_key);
				if ( ! is_wp_error( $request ) && $request['response']['code'] == 200 ) :
					if ( $request['body'] == 1 ) :
						return true;
					else :
						return false;
					endif;
				else :
					return false;
				endif;
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $this->get_option( 'ShopID' );
				$this->ShopPass = $this->get_option( 'ShopPass' );
				$this->configid = $this->get_option( 'configid' );
				$this->mode = $this->get_option( 'mode' );
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('credit');
				if ( !empty($data['customer_id']) ) $param['credit']['MemberID'] = $data['customer_id'];
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;

				$param['credit']['JobCd'] = $this->JobCd;
				
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_credit' !== $order->get_payment_method() ) return;
				
				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
						wp_redirect(wc_get_checkout_url());
						exit;
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}
		
			function check_for_webhook() {
				echo '0';
				wc_gmopg_gateway_logging( $_POST, $this->logging );
				if ( empty($_POST['ShopID']) || empty($this->ShopID) || $_POST['ShopID'] != $this->ShopID || empty($_POST['Status']) ) exit;

				if ( $_POST['Status'] == 'CAPTURE' || $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'CHECK' || $_POST['Status'] == 'REQSUCCESS' || $_POST['Status'] == 'PAYSUCCESS' || $_POST['Status'] == 'EXPIRED' || $_POST['Status'] == 'CANCEL' || $_POST['Status'] == 'PAYFAIL' ) :
					$order = new WC_Order( str_replace( 'wcgmopg', '', $_POST['OrderID'] ) );
				
					if ( !empty($order) ) :
						switch ( $order->get_payment_method() ) :
							case 'gmopg_credit' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( isset($_POST['Amount']) && $_POST['Amount'] == $order->get_total() ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['TranID'] ) );
								else :
									$order->update_status( 'failed', sprintf( __( 'Total amount does not match (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['TranID'] ) );
								endif;
								break;
							case 'gmopg_cvs' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'REQSUCCESS' ) :
									$order->update_status( 'on-hold', sprintf( __( 'GMOPG settlement displayed (Transaction No: %s, Detail: %s).', 'wc-gmopg-gateway' ), $_POST['TranID'], $_POST['CvsCode']." ".$_POST['CvsConfNo']." ".$_POST['CvsReceiptNo'] ) );				
								elseif ( $_POST['Status'] == 'PAYSUCCESS' ) :
									$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );
								elseif ( $_POST['Status'] == 'EXPIRED' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement expired.', 'wc-gmopg-gateway' ) );
								elseif ( $_POST['Status'] == 'CANCEL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_payeasy' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'REQSUCCESS' ) :
									$order->update_status( 'on-hold', sprintf( __( 'GMOPG settlement displayed (Transaction No: %s, Detail: %s).', 'wc-gmopg-gateway' ), $_POST['TranID'], $_POST['CustID']." ".$_POST['BkCode']." ".$_POST['ConfNo'] ) );				
								elseif ( $_POST['Status'] == 'PAYSUCCESS' ) :
									$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );
								elseif ( $_POST['Status'] == 'EXPIRED' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement expired.', 'wc-gmopg-gateway' ) );
								elseif ( $_POST['Status'] == 'CANCEL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_docomo' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['DocomoSettlementCode'] ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' || $_POST['Status'] == 'UNPROCESSED' || $_POST['Status'] == 'AUTHPROCESS' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_au' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['AuPayInfoNo'] ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' || $_POST['Status'] == 'AUTHPROCESS' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_sb' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['SbTrackingId'] ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_epospay' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'AUTH' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['EposTradeId'] ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' || $_POST['Status'] == 'AUTHPROCESS' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_dcc' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['DccFtn'] ) );
								elseif ( $_POST['Status'] == 'UNPROSESSED' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_linepay' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['TranID'] ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								elseif ( $_POST['Status'] == 'PAYCANCEL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_famipay' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'PAYSUCCESS' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['UriageNO'] ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_merpay' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'REQSUCCESS' ) :
									$order->update_status( 'on-hold', sprintf(__( 'GMOPG settlement displayed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['MerpayInquiryCode'] ) );				
								elseif ( $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['DocomoSettlementCode'] ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_rakutenpayv2' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['RakutenChargeID'] ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_paypay' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'SALES' || $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST['PayPayTrackingID'] ) );
								elseif ( $_POST['Status'] == 'CANCEL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_aupay' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, sprintf( __( 'GMOPG settlement completed (Transaction No: %s).', 'wc-gmopg-gateway' ), $_POST[' 	AuPayInfoNo'] ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
							case 'gmopg_unionpay' :
								if ( empty($this->status) ) $this->status = 'processing';
								if ( $_POST['Status'] == 'AUTH' || $_POST['Status'] == 'CAPTURE' ) :
									$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );
								elseif ( $_POST['Status'] == 'PAYFAIL' ) :
									$order->update_status( 'failed', __( 'GMOPG settlement failed.', 'wc-gmopg-gateway' ) );
								endif;
								break;
						endswitch;
					endif;
				endif;
				
				exit;
			}
		}
	endif;
	
	if ( ! class_exists( 'WC_Gateway_GMOPG_Cvs' ) ) :

		class WC_Gateway_GMOPG_Cvs extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_cvs';
				$this->method_title = __('GMOPG - CVS', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the CVS payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - CVS', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'CVS', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with CVS', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('cvs');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_cvs' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( 'on-hold', __( 'GMOPG settlement requested.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;
	
	if ( ! class_exists( 'WC_Gateway_GMOPG_Payeasy' ) ) :

		class WC_Gateway_GMOPG_Payeasy extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_payeasy';
				$this->method_title = __('GMOPG - Pay-easy', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the Pay-easy payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - Pay-easy', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay-easy', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with Pay-easy', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('payeasy');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_payeasy' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );					
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
						wp_redirect(wc_get_checkout_url());
						exit;
					else :
						$order->update_status( 'on-hold', __( 'GMOPG settlement requested.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;

	if ( ! class_exists( 'WC_Gateway_GMOPG_Docomo' ) ) :

		class WC_Gateway_GMOPG_Docomo extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_docomo';
				$this->method_title = __('GMOPG - Docomo', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the Docomo payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - Docomo', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Docomo', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with Docomo', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('docomo');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				$param['docomo']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_docomo' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
						wp_redirect(wc_get_checkout_url());
						exit;
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;

	if ( ! class_exists( 'WC_Gateway_GMOPG_Au' ) ) :

		class WC_Gateway_GMOPG_Au extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_au';
				$this->method_title = __('GMOPG - AU', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the AU payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - AU', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'AU', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with AU', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('au');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				$param['au']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_au' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;

	if ( ! class_exists( 'WC_Gateway_GMOPG_Sb' ) ) :

		class WC_Gateway_GMOPG_Sb extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_sb';
				$this->method_title = __('GMOPG - SoftBank', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the SoftBank payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - SoftBank', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'SoftBank', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with SoftBank', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('sb');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				$param['sb']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_sb' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;
	
	if ( ! class_exists( 'WC_Gateway_GMOPG_Epospay' ) ) :

		class WC_Gateway_GMOPG_Epospay extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_epospay';
				$this->method_title = __('GMOPG - Epos Pay', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the Epos Pay payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - Epos Pay', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Epos Pay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with Epos Pay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = 'AUTH';
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('epospay');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				$param['epospay']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_epospay' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;
	
	if ( ! class_exists( 'WC_Gateway_GMOPG_Dcc' ) ) :

		class WC_Gateway_GMOPG_Dcc extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_dcc';
				$this->method_title = __('GMOPG - DCC', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the DCC payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - DCC', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'DCC', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with DCC', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('dcc');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
					
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_dcc' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;
	
	if ( ! class_exists( 'WC_Gateway_GMOPG_Linepay' ) ) :

		class WC_Gateway_GMOPG_Linepay extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_merypay';
				$this->method_title = __('GMOPG - LINE Pay', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the LINE Pay payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - LINE Pay', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'LINE Pay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with LINE Pay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('linepay');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				$param['linepay']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_linepay' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;
	
	if ( ! class_exists( 'WC_Gateway_GMOPG_Famipay' ) ) :

		class WC_Gateway_GMOPG_Famipay extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_famipay';
				$this->method_title = __('GMOPG - FamiPay', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the FamiPay payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - FamiPay', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'FamiPay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with FamiPay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('famipay');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_famipay' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;
	
	if ( ! class_exists( 'WC_Gateway_GMOPG_Merpay' ) ) :

		class WC_Gateway_GMOPG_Merpay extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_merpay';
				$this->method_title = __('GMOPG - Merpay', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the Merpay payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - Merpay', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Merpay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with Merpay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('merpay');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				$param['merpay']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_merpay' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;
	
	if ( ! class_exists( 'WC_Gateway_GMOPG_Rakutenpayv2' ) ) :

		class WC_Gateway_GMOPG_Rakutenpayv2 extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_rakutenpayv2';
				$this->method_title = __('GMOPG - Rakuten Pay V2', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the Merpay payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - Rakuten Pay V2', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Rakuten Pay V2', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with Rakuten Pay V2', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('rakutenpayv2');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				$param['rakutenpayv2']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_rakutenpayv2' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;

	if ( ! class_exists( 'WC_Gateway_GMOPG_Paypay' ) ) :

		class WC_Gateway_GMOPG_Paypay extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_paypay';
				$this->method_title = __('GMOPG - PayPay', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the PayPay payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				//$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - PayPay', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'PayPay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with PayPay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					/*'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),*/
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('paypay');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				//$param['paypay']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_paypay' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;
	
	if ( ! class_exists( 'WC_Gateway_GMOPG_Aupay' ) ) :

		class WC_Gateway_GMOPG_Aupay extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_aupay';
				$this->method_title = __('GMOPG - au PAY', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the au PAY payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - au PAY', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'au PAY', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with au PAY', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('aupay');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				$param['aupay']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_aupay' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;

	if ( ! class_exists( 'WC_Gateway_GMOPG_Unionpay' ) ) :

		class WC_Gateway_GMOPG_Unionpay extends WC_Payment_Gateway {			
			public function __construct() {
				$this->id = 'gmopg_unionpay';
				$this->method_title = __('GMOPG - UnionPay', 'wc-gmopg-gateway');
				$this->method_description = __('Enable the UnionPay payment by GMOPG.', 'wc-gmopg-gateway');
				$this->has_fields = false;
				
				$this->init_form_fields();
				$this->init_settings();
				
				$this->enabled = $this->get_option( 'enabled' );
				$this->title = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->JobCd = $this->get_option( 'JobCd' );
				$this->status = $this->get_option( 'status' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
				add_action( 'woocommerce_thankyou', array( &$this, 'wc_gmopg_gateway_woocommerce_thankyou' ) );
			}
			
			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', 'wc-gmopg-gateway' ),
						'label'       => __( 'Enable GMOPG - UnionPay', 'wc-gmopg-gateway' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title' => array(
						'title'       => __( 'Title', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'UnionPay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', 'wc-gmopg-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the description which the user sees during checkout.', 'wc-gmopg-gateway' ),
						'default'     => __( 'Pay with UnionPay', 'wc-gmopg-gateway' ),
						'desc_tip'    => true,
					),
					'JobCd'    => array(
						'title' => __('Authorization', 'wc-gmopg-gateway'),
						'type' => 'select',
						'default' => '',
						'options'     => array(
							'CAPTURE' => __('Capture', 'wc-gmopg-gateway'),
							'AUTH'  => __('Authorize', 'wc-gmopg-gateway')
						)
					),
					'status'    => array(
						'title' => __('Status', 'wc-gmopg-gateway'),
						'type' => 'select',
						'desc_tip'    => true,
						'default' => '',
						'options'     => array(
							'processing'  => __('Processing', 'wc-gmopg-gateway'),
							'completed' => __('Completed', 'wc-gmopg-gateway')
						)
					),
				);
			}
			
			function process_payment( $order_id ) {
				global $woocommerce, $current_user;
				
				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');
				
				$order = new WC_Order( $order_id );
				$data  = $order->get_data();
				$woocommerce->cart->empty_cart();
				
				$this->ShopID = $gmopg_option['ShopID'];
				$this->ShopPass =$gmopg_option['ShopPass'];
				$this->configid = $gmopg_option['configid'];
				$this->mode = $this->get_option( 'mode' );
				$this->logging = $gmopg_option['logging'];
				$this->JobCd = $this->get_option( 'JobCd' );
				
				$billing_phone = $order->get_billing_phone();
				$billing_email = $order->get_billing_email();
				$billing_last_name  = $order->get_billing_last_name();
				$billing_first_name = $order->get_billing_first_name();
				
				if ( $this->mode == 'real' ) :
					$url = 'https://p01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				else :
					$url = 'https://pt01.mul-pay.jp/payment/GetLinkplusUrlPayment.json';
				endif;

				$param = array();
				$param['configid'] = $this->configid;
				$param['geturlparam']['ShopID'] = $this->ShopID;
				$param['geturlparam']['ShopPass'] = $this->ShopPass;
				
				$param['transaction']['OrderID'] = "wcgmopg".$order_id;
				$param['transaction']['Amount'] = $order->get_total();			
				$param['transaction']['RetUrl'] = $this->get_return_url( $order );
				
				$param['transaction']['PayMethods'] = array('unionpay');
				
				if ( !empty($billing_email) ) $param['customer']['MailAddress'] = $billing_email;
				if ( !empty($billing_phone) ) $param['customer']['TelNo'] = $billing_phone;
				if ( !empty($billing_last_name) && !empty($billing_first_name) ) $param['customer']['CustomerName'] = $billing_last_name.$billing_first_name;
				$param['transaction']['ClientField1'] = $billing_email;
				
				$param['unionpay']['JobCd'] = $this->JobCd;
	
				$param_json = json_encode( $param );

				$args = [
					'headers' => [
						'content-type' => 'application/json'
					],
					'body' => $param_json,
				];

				$response = wp_remote_post( $url, $args );
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body( $response );
   				$response_data = json_decode($response_body, true);
				if( $response_code != 200 ) :
					$err_msg = 'ErrCode=' . $response_data[0]['errCode'] . ', ErrInfo=' . $response_data[0]['errInfo'];
					wc_gmopg_gateway_logging( $param, $this->logging );
					wc_gmopg_gateway_logging( $param_json, $this->logging );
					wc_gmopg_gateway_logging( $response_data, $this->logging );
					$order->add_order_note( $err_msg );
					return;
				endif;
								
				return array(
					'result' => 'success',
					'redirect' => $response_data['LinkUrl']
				);
			}
			
			function wc_gmopg_gateway_woocommerce_thankyou( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( 'gmopg_unionpay' !== $order->get_payment_method() ) return;

				$gmopg_option = get_option('woocommerce_gmopg_credit_settings');				
				$this->logging = $gmopg_option['logging'];

				if ( !empty($_POST['result']) ) :
					if ( empty($this->status) ) $this->status = 'processing';
					list($base64, $hash) = explode('.', $_POST['result']);
					$json = wc_gmopg_gateway_base64_url_decode($base64);
					$result = json_decode($json, true);
					wc_gmopg_gateway_logging( $result, $this->logging );
					if ( empty($result) || $result['transactionresult']['Result'] == 'PAYSTART' ) :
						$order->update_status( 'failed', __( 'GMOPG settlement canceled.', 'wc-gmopg-gateway' ) );
						wp_redirect(wc_get_checkout_url());
						exit;
					elseif ( !empty($result['transactionresult']['ErrCode']) && !empty($result['transactionresult']['ErrInfo']) ) :
						$order->update_status( 'failed', "ErrCode=" . esc_attr($result['transactionresult']['ErrCode']) . "\n" . "ErrInfo=" . esc_attr($result['transactionresult']['ErrInfo']) );
					else :
						$order->update_status( $this->status, __( 'GMOPG settlement completed.', 'wc-gmopg-gateway' ) );				
					endif;
				endif;
			}			
		}
	
	endif;

}
			
function wc_gmopg_gateway_logging( $error, $logging = false ) {
	if ( !empty($logging) ) :
		$logger = wc_get_logger();
		$logger->debug( wc_print_r( $error, true ), array( 'source' => 'wc-gmopg-gateway' ) );
	endif;
}
			
function wc_gmopg_gateway_base64_url_encode($input) {
	return strtr(base64_encode($input), '+/=', '-._');
}

function wc_gmopg_gateway_base64_url_decode($input) {
	return base64_decode(strtr($input, '-._', '+/='));
}

function wc_gmopg_gateway_woocommerce_payment_gateways( $methods ) {
	$methods[] = 'WC_Gateway_GMOPG_Credit';
	$methods[] = 'WC_Gateway_GMOPG_Cvs';
	$methods[] = 'WC_Gateway_GMOPG_Payeasy';
	$methods[] = 'WC_Gateway_GMOPG_Docomo';
	$methods[] = 'WC_Gateway_GMOPG_Au';
	$methods[] = 'WC_Gateway_GMOPG_Sb';
	$methods[] = 'WC_Gateway_GMOPG_Epospay';
	$methods[] = 'WC_Gateway_GMOPG_Dcc';
	//$methods[] = 'WC_Gateway_GMOPG_Linepay';
	$methods[] = 'WC_Gateway_GMOPG_FamiPay';
	//$methods[] = 'WC_Gateway_GMOPG_Merpay';
	$methods[] = 'WC_Gateway_GMOPG_Rakutenpayv2';
	$methods[] = 'WC_Gateway_GMOPG_Paypay';
	$methods[] = 'WC_Gateway_GMOPG_Aupay';
	$methods[] = 'WC_Gateway_GMOPG_Unionpay';
	return $methods;
}
?>