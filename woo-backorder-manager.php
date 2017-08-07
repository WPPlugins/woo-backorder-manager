<?php
/**
 * Plugin Name: WooCommerce Backorder Manager
 * Plugin URI:  https://wordpress.org/plugins/woo-backorder-manager/
 * Description: View reports with units in backorder, manage backorder email notifications, export backorders to CSV.
 * Version: 1.2
 * Author: jeffrey-wp
 * Author URI: https://codecanyon.net/user/jeffrey-wp/?ref=jeffrey-wp
 * Text Domain: woo-backorder-manager
 * Domain Path: /languages/
 */

/** If this file is called directly, abort. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * class WooBackorderManager
 * the main class
  */
class WooBackorderManager {

	/**
	 * Initialize the hooks and filters
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'wcbm_admin_notices' ) );
		add_filter( 'plugin_action_links', array( $this, 'wcbm_plugin_action_links' ), 10, 2 );
		add_action( 'plugins_loaded', array( $this, 'wcbm_load_plugin_textdomain' ) );

		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_section_backordermanager' ) );
		add_filter( 'woocommerce_get_settings_products', array( $this, 'backordermanager_all_settings' ), 10, 2 );
		add_action( 'woocommerce_email', array( $this, 'woocommerce_remove_email_notifications' ) );

		add_filter( 'woocommerce_admin_reports', array( $this, 'register_backorder_report' ), 10, 1 );
	}


	/**
	 * Show admin notices
	 * @action admin_notices
	 */
	public function wcbm_admin_notices() {

		// check if WooCommerce is active
		if ( ! class_exists( 'Woocommerce' ) ) {
			?>
			<div class="error"><p>
				<?php printf(
					__( '%s plugin is enabled but not effective. It requires WooCommerce in order to work.', 'woo-backorder-manager' ),
					'<strong>WooCommerce Backorder Manager</strong>'
				); ?>
			</p></div>
			<?php
		}
	}


	/**
	 * Show extra links on plugin overview page
	 * @param array $links
	 * @param string $file
	 * @return array
	 * @filter plugin_action_links
	 */
	public function wcbm_plugin_action_links( $links, $file ) {
		if ( $file != plugin_basename( __FILE__ ) )
			return $links;

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=products&section=backordermanager' ) . '">' . __( 'Settings', 'woo-backorder-manager' ) . '</a>',
				'report'   => '<a href="' . admin_url( 'admin.php?page=wc-reports&tab=stock&report=on_backorder' ) . '">' . __( 'Report', 'woo-backorder-manager' ) . '</a>',
			),
			$links
		);
	}


	/**
	 * Load text domain
	 * @action plugins_loaded
	 */
	public function wcbm_load_plugin_textdomain() {
		load_plugin_textdomain( 'woo-backorder-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}


	/**
	 * Create the section 'Backorder Manager' beneath the products tab
	 * @param array $sections
	 * @filter woocommerce_get_sections_products
	 */
	public function add_section_backordermanager( $sections ) {
		$sections['backordermanager'] = __( 'Backorder Manager', 'woo-backorder-manager' );
		return $sections;
	}


	/**
	 * Add settings to the section 'Backorder Manager'
	 * @param array $settings
	 * @param string $current_section
	 * @filter woocommerce_get_sections_products
	 */
	public function backordermanager_all_settings( $settings, $current_section ) {

		// check if the current section is what we want
		if ( $current_section == 'backordermanager' ) {

			$settings = array(
				'section_title' => array(
					'name'     => __( 'Backorder email notifications', 'woo-backorder-manager' ),
					'type'     => 'title',
					'desc'     => '',
					'id'       => 'wcbm_section_title'
				),
				'backorder' => array(
					'name'     => __( 'Backorder', 'woo-backorder-manager' ),
					'type'     => 'checkbox',
					'desc_tip' => __( 'Don\'t send email notifications when a backorder is made.', 'woo-backorder-manager' ),
					'desc'     => __( 'Disable backorder email notifications', 'woo-backorder-manager' ),
					'id'       => 'wcbm_backorder_mail_notification'
				),
				'section_end' => array(
					'type' => 'sectionend',
					'id' => 'wcbm_section_end'
				)
			);

			return $settings;

		// if not, return the standard settings
		} else {

			return $settings;

		}
	}


	/*
	 * Unhook/remove WooCommerce Emails
	 * @param $email_class
	 * @action woocommerce_email
	 */
	function woocommerce_remove_email_notifications( $email_class ) {

		$backorder_mail_notification = get_option( 'wcbm_backorder_mail_notification' );

		if ( false !== $backorder_mail_notification && 'no' !== $backorder_mail_notification ) {
			// unhooks sending email backorders during store events
			remove_action( 'woocommerce_product_on_backorder_notification', array( $email_class, 'backorder' ) );
		}
	}

	/**
	 * Show report 'On Backorder'
	 * @param array $reports
	 * @filter woocommerce_admin_reports
	 */
	public function register_backorder_report( $reports ) {

		$report_info = array(
			'title'       => __( 'On backorder', 'woo-backorder-manager' ),
			'description' => '',
			'hide_title'  => true,
			'callback'    => array( $this, 'get_backorder_report' ),
		);

		$reports['stock']['reports']['on_backorder'] = $report_info;

		return $reports;
	}


	/**
	 * Callback for backorder report
	 */
	public function get_backorder_report() {
		include_once( 'include/wc_report_stock_backorders.php' );

		if ( ! class_exists( 'WC_Report_Stock_Backorders' ) )
			return;

		$report = new WC_Report_Stock_Backorders;
		$report->output_report();
	}

}
$woobackordermanager = new WooBackorderManager();
