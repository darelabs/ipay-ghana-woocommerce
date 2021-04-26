<?php
/*
Plugin Name: iPay Ghana WooCommerce
Plugin URI: https://www.ipaygh.com/
Description: Receive payments on your WooCommerce store in Ghana. Already have an account? Open one with us <a href="https://manage.ipaygh.com/xmanage/get-started">here</a>. Visit your <a href="https://manage.ipaygh.com/xmanage/">dashboard</a> to monitor your transactions.
Version: 1.0.7
Author: iPay Solutions Ltd.
Author URI: https://www.ipaygh.com/
Text Domain:
Domain Path:
License: GNU General Public License v3.0
*/


/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

function ipay_ghana_wc_text_domain() {
	load_plugin_textdomain( '', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'ipay_ghana_wc_text_domain' );

function ipay_ghana_wc_admin_styles_and_scripts() {
	wp_enqueue_style( 'ipay-ghana-wc-admin-style', plugins_url( '/assets/css/ipay-ghana-wc-admin.css', __FILE__ ), false, '', 'all' );
	wp_enqueue_script( 'ipay-ghana-wc-admin-script', plugins_url( '/assets/js/ipay-ghana-wc-admin.js', __FILE__ ), false, '' );
}
add_action( 'admin_enqueue_scripts', 'ipay_ghana_wc_admin_styles_and_scripts' );

function ipay_ghana_wc_styles_and_scripts() {
	wp_enqueue_style( 'ipay-ghana-wc-style', plugins_url( '/assets/css/ipay-ghana-wc.css', __FILE__ ), array(), '', 'all' );
	wp_enqueue_script( 'ipay-ghana-wc-script', plugins_url( '/assets/js/ipay-ghana-wc.js', __FILE__ ), array(), '', true );
}
add_action( 'wp_enqueue_scripts', 'ipay_ghana_wc_styles_and_scripts' );

function ipay_ghana_wc_plugin_action( $actions, $plugin_file ) {
	if ( false == strpos( $plugin_file, basename( __FILE__ ) ) ) {
		return $actions;
	}
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ipay-ghana-wc-payment' ) . '">Settings</a>';

	array_unshift( $actions, $settings_link );
	return $actions;
}
add_filter( 'plugin_action_links', 'ipay_ghana_wc_plugin_action', 10, 2 );

function ipay_ghana_wc_plugin_support( $meta, $plugin_file ) {
	if ( false == strpos( $plugin_file, basename( __FILE__ ) ) ) {
		return $meta;
	}
	$meta[] = '<a href="https://docs.ipaygh.com" target="_blank">Support </a>';
	return $meta;
}
add_filter( 'plugin_row_meta', 'ipay_ghana_wc_plugin_support', 10, 4 );

function init_ipay_ghana_wc_payment_gateway() {
	if ( class_exists( 'WC_Payment_Gateway' ) ) {

		class Ipay_Ghana_WC_Payment_Gateway extends WC_Payment_Gateway {
			public function __construct() {
				$this->id                   = 'ipay-ghana-wc-payment';
				$this->icon                 = 'https://payments.ipaygh.com/app/webroot/img/iPay_payments.png';
				$this->has_fields           = true;
				$this->method_title         = __( 'iPay Ghana Payment', '' );
				$this->init_form_fields();
				$this->init_settings();
				$this->title                = $this->get_option( 'title' );

				add_action( 'admin_notices', array( $this, 'do_ssl_check' ) );
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'pay_for_order' ) );
				add_action( 'woocommerce_api_' . $this->id, array( $this, 'check_ipn_response' ) );
			}

			public function ipn_url() {
				$ipn_url = '';

				if ( version_compare( WC_VERSION, '2.0', '<' ) ) {
					$ipn_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', $this->id, home_url( '/' ) ) );
				} else {
					$ipn_url = str_replace( 'https:', 'http:', home_url( '/wc-api/' . $this->id ) );
				}
				return $ipn_url;
			}

			public function do_ssl_check() {
				if ( $this->enabled === 'yes' ) {
					if ( get_option( 'woocommerce_force_ssl_checkout' ) === 'no' ) {
						echo '<div class="error"><p>' . sprintf( __( '<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href="%s">forcing the checkout pages to be secured</a>.' ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
					}
				}
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', '' ),
						'type'        => 'checkbox',
						'description' => __( 'Check in order to enable iPay Ghana WooCommerce Payment Gateway, otherwise, uncheck to disable.', '' ),
						'label'       => __( 'Enable iPay Ghana Payment', '' ),
						'default'     => 'no',
						'desc_tip'    => true,
					),
					'title' => array(
						'title'       => __( 'Title', '' ),
						'type'        => 'text',
						'class'       => 'is-read-only',
						'description' => __( 'This controls the title which the user sees during checkout.', '' ),
						'default'     => __( 'iPay', '' ),
						'desc_tip'    => true,
					),
					'extra_project_name' => array(
						'title'       => __( 'Extra project name', '' ),
						'type'        => 'text',
						'description' => __( 'Additional information you want to show beside your company name on the checkout page.', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => true,
					),
					'checkout_on_site' => array(
						'title'       => __( 'Collect payment onsite', '' ),
						'type'        => 'checkbox',
						'class'       => 'is-hidden',
						'label'       => __( 'Enable to collect onsite payment.', '' ),
						'description' => __( 'This controls the medium of payment. When disabled, payment will be collected on the iPay Ghana Checkout page.', '' ),
						'default'     => 'no',
						'desc_tip'    => true,
					),
					'merchant_key' => array(
						'title'       => __( 'Merchant key', '' ),
						'type'        => 'text',
						'description' => __( 'The value of merchant_key identifies the merchant who is using the gateway It is usually obtained by registering for the iPay gateway service. At the moment, registering for the iPay service is FREE.', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => true,
					),
					'merchant_id' => array(
						'title'       => __( 'Merchant ID', '' ),
						'type'        => 'text',
						'description' => __( 'Login to your iPay Dashboard to get your Merchant ID under Account Settings Tab.', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => true,
					),
					'success_url' => array(
						'title'       => __( 'Success URL', '' ),
						'type'        => 'text',
						'description' => __( 'The page to which iPay will redirect the user after user completes the iPay checkout process. Please note that this does not mean that payment has been received!', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => true,
					),
					'cancelled_url' => array(
						'title'       => __( 'Cancelled URL', '' ),
						'type'        => 'text',
						'description' => __( 'The page to which iPay will redirect the user after user cancels the order.', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => true,
					),
					'ipn_url' => array(
						'title'       => __( 'Payment notification link', '' ),
						'type'        => 'text',
						'class'       => 'is-read-only',
						'description' => __( 'To receive payment update notifications to your store/order, copy this URL and save it in your ipay dashboard, in the IPN_URL field, under account settings tab.', '' ),
						'default'     => __( esc_url( $this->ipn_url() ), '' ),
						'desc_tip'    => true,
					),
				);
			}

			public function payment_fields() {
				if ( $this->get_option( 'checkout_on_site' ) === 'no' ) {
					//to iPay Checkout name="checkout"
					echo wptexturize( __( 'Pay with MTN Mobile Money, Vodafone Cash, Tigo Cash, Airtel Money, VISA, MasterCard. No need to have an iPay Account to pay.' ) );
				} 
				else {
					echo '<p class="form-group">
							<label for="network_operator">Select Network</label>
							<select id="network_operator" class="" name="extra_wallet_issuer_hint" required>
								<option disabled selected value> -- Select One -- </option>
								<option value="airtel">Airtel Money</option>
								<option value="mtn">MTN Mobile Money</option>
								<option value="tigo">tiGO Cash</option>
							</select>
						</p>
						<p class="form-row form-row validate-required validate-phone" id="wallet_number_field">
							<label for="mobile_wallet_number">Phone Number <abbr class="required" title="required">*</abbr></label>
							<input type="tel" class="input-text" id="mobile_wallet_number" name="pymt_instrument" placeholder="Enter your wallet number here." autocomplete="on" required>
						</p>';
				}
			}

			public function pay_for_order( $order_id ) {
				echo '<p>' . __( 'Thank you for placing your order with us.', '' ) . '</p>';
				echo '<p>' . __( 'You will be redirected to iPay Ghana Payment Gateway checkout page so as to complete your payment.', '' ) . '</p>';
				echo $this->generate_ipay_ghana_wc_checkout_form( $order_id );
			}

			public function process_payment( $order_id ) {
				$order = new WC_Order( $order_id );
				$items = $order->get_items();
				
				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					foreach ( $items as $item ) {
						$items_array[] = $item['name'];
					}
				} 
				else {
					foreach ( $items as $item ) {
						$items_array[] = $item->get_name();
					}
				}
				
				$list_items = implode( ', ', $items_array );

				if ( $this->get_option( 'checkout_on_site' ) === 'no') {
					return array(
						'result' => 'success',
						'redirect' => $order->get_checkout_payment_url( true )
					);
				} 
				else {
					$api = 'https://community.ipaygh.com/';

					$payload = [
						'on_site' => [
							'merchant_key'               => $this->get_option( 'merchant_key' ),
							'extra_currency'             => $order->get_currency(),
							'total'             	     => $order->get_total(),
							'invoice_id'                 => str_replace( '#', '', $order->get_order_number() ),
							'extra_wallet_issuer_hint'   => ( isset( $_POST['extra_wallet_issuer_hint'] ) && ! empty( $_POST['extra_wallet_issuer_hint'] ) ) ? $_POST['extra_wallet_issuer_hint'] : $_POST['extra_wallet_issuer_hint'],
							'pymt_instrument'            => $wallet_number = ( ( isset( $_POST['pymt_instrument'] ) && ! empty( $_POST['pymt_instrument'] ) ) ? $_POST['pymt_instrument'] : $_POST['pymt_instrument'] ),
							'extra_name'         	     => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
							'extra_mobile'         	     => $order->get_billing_phone(),
							'extra_email'          	     => $order->get_billing_email(),
							'description'          	     => esc_attr( $list_items ),
						]
					];

					$response = wp_remote_post( $api . 'v1/mobile_agents_v2', [
						'method'    => 'POST',
						'body'      => http_build_query( $payload['on_site'] ),
						'timeout'   => 90,
						'sslverify' => false,
					] );

					if ( is_wp_error( $response ) ) {
						throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', '' ) );
					}

					if ( $response['response']['code'] === 500 ) {
						throw new Exception( __( 'An error was encountered. Please contact us with error code (' . $response['response']['code'] . ').', '' ) );
					}

					$response_body = wp_remote_retrieve_body( $response );
					$data = json_decode( $response_body, true );

					if ( $response['response']['code'] === 200 ) {
						if ( ( $data['success'] === true ) && ( $data['status'] === 'new' ) ) {
							$order->add_order_note( __( 'Transaction initiated successfully; a USSD prompt or message with Mobile Money payment completion steps has been triggered and sent to: ' . $wallet_number . '.', '' ) );
							// $order->update_status( 'on-hold', __( 'Awaiting Mobile Money payment.<br>', '' ) );
							// $order->reduce_order_stock();
							WC()->cart->empty_cart();

							return [
								'result'   => 'success',
								'redirect' => $this->get_return_url( $order ),
							];
						} else {
							wc_add_notice( __('Payment error:', '') . $response['response']['message'], 'error' );
							return null;
						}
					} else {
						wc_add_notice( 'An error was encountered. Please contact us with error code (' . $response['response']['code'] . ').', 'error' );
						$order->add_order_note( 'Error code: ' . $response['response']['code'] . PHP_EOL . 'Status: ' . $response['response']['status'] );
					}
				}
				return null;
			}

			public function generate_ipay_ghana_wc_checkout_form( $order_id ) {
				global $items_array;
				
				$order = new WC_Order( $order_id );
				$items = $order->get_items();
				
				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					foreach ( $items as $item ) {
						$items_array[] = $item['name'];
					}
				} else {
					foreach ( $items as $item ) {
						$items_array[] = $item->get_name();
					}
				}
				
				$list_items = implode( ', ', $items_array );
				
				$order->add_order_note( __( 'Order placed successfully; user has been redirected to the iPay Ghana Payment Gateway checkout page.', '' ) );
				WC()->cart->empty_cart();

				wc_enqueue_js( 'jQuery( "#submit-payload-to-ipay-ghana-wc-payment-gateway-checkout-url" ).click();' );

				return '<form action="' . 'https://manage.ipaygh.com/gateway/checkout' . '" method="post" id="ipay-ghana-wc-payment-gateway-checkout-url-form" target="_top">
				<input type="hidden" name="merchant_key" value="' . esc_attr( $this->get_option( 'merchant_key' ) ) . '">
				<input type="hidden" name="extra_currency" value="' . $order->get_currency() . '">
				<input type="hidden" name="extra_mobile" value="' . $order->get_billing_phone() . '">
				<input type="hidden" name="extra_email" value="' . $order->get_billing_email() . '">
				<input type="hidden" name="description" value="' . esc_attr( $list_items ) . '">
				<input type="hidden" name="success_url" value="' . esc_url( $this->get_option( 'success_url' ) ) . '">
				<input type="hidden" name="cancelled_url" value="' . esc_url( $this->get_option( 'cancelled_url' ) ) . '">
				<input type="hidden" name="invoice_id" value="' . str_replace( '#', '', $order->get_order_number() ) . '">
				<input type="hidden" name="total" value="' . $order->get_total() . '">
				<input type="hidden" name="source" value="WOOCOMMERCE">
				<input type="hidden" name="extra_project_name" value="' . $this->get_option( 'extra_project_name' ) . '">
				<div class="btn-submit-payment" style="display: none;">
				<button type="submit" class="button alt" id="submit-payload-to-ipay-ghana-wc-payment-gateway-checkout-url">' . __( 'Checkout with iPay Ghana', '' ) . '</button>
				</div>
				</form>';
			}

			public function check_ipn_response() {
				if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
					$merchant_key = $this->get_option( 'merchant_key' );
					$merchant_id  = $this->get_option( 'merchant_id' );
					$auth         = sanitize_text_field( $_SERVER['HTTP_AUTHORIZATION'] );
					$auth_array   = explode( ' ', $auth );
					$credential   = explode( ':', base64_decode( $auth_array[1] ) );
					$username     = $credential[0];
					$password     = $credential[1];

					if ( $username === $merchant_key && $password === $merchant_id ) {
						$raw_post_data  = file_get_contents( 'php://input' );

						if ( $raw_post_data !== null ) {
							$raw_post_array = json_decode( $raw_post_data, true );
							$status         = sanitize_text_field( $raw_post_array['status'] );
							$status_reason  = sanitize_text_field( $raw_post_array['status_reason'] );
							$invoice        = sanitize_text_field( $raw_post_array['invoice'] );
							$order          = new WC_Order( $invoice );

							if ( $status === 'paid' ) {
								$order->update_status( 'processing', __( esc_html( $status_reason ) . '<br>', '' ) );
								wc_reduce_stock_levels( $order->id );
								$order->payment_complete();
							} elseif ( $status === 'pending' ) {
								$order->update_status( 'pending', __( esc_html( $status_reason ) . '<br>', '' ) );
							} elseif ( $status === 'cancelled' || 'expired' || 'failed' ) {
								$order->update_status( 'cancelled', __( esc_html( $status_reason ) . '<br>', '' ) );
							}

							header( 'HTTP/1.1 200 OK' );
							exit();
						} else {
							wp_die( 'iPay Ghana WooCommerce Payment Gateway IPN Unprocessable Entity.', '', array( 'response' => 422 ) );
						}
					} else {
						wp_die( 'iPay Ghana WooCommerce Payment Gateway IPN Authorization Required.', '', array( 'response' => 401 ) );
					}
				}
				wp_die( 'iPay Ghana WooCommerce Payment Gateway IPN Request Failure.', '', array( 'response' => 500 ) );
			}
		}

		function ipay_ghana_wc_payment_gateway_label( $methods ) {
			$methods[] = 'Ipay_Ghana_WC_Payment_Gateway';
			return $methods;
		}
		add_filter( 'woocommerce_payment_gateways', 'ipay_ghana_wc_payment_gateway_label' );
	}
}
add_action( 'plugins_loaded', 'init_ipay_ghana_wc_payment_gateway', 0 );
