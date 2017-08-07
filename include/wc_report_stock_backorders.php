<?php
/** If this file is called directly, abort. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Report_Stock' ) ) {
	require_once( WP_PLUGIN_DIR . '/woocommerce/includes/admin/reports/class-wc-report-stock.php' );
}

/**
 * class WC_Report_Stock_Backorders
 * the 'On backorder' report class
 */
class WC_Report_Stock_Backorders extends WC_Report_Stock {


	/**
	 * No items found text
	 */
	public function no_items() {
		_e( 'No backordered items found.', 'woo-backorder-manager' );
	}


	/**
	 * Get Products matching stock criteria
	 */
	public function get_items() {
		global $wpdb;

		$this->items = array();

		$nostock = absint( max( get_option( 'woocommerce_notify_no_stock_amount' ), 0 ) );

		// Get products using a query (too advanced for get_posts)
		$this->items = $wpdb->get_results( "SELECT posts.ID AS id, posts.post_parent AS parent FROM {$wpdb->posts} as posts
			INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
			INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
			WHERE posts.post_type IN ( 'product', 'product_variation' )
			AND posts.post_status = 'publish'
			AND postmeta.meta_key = '_backorders' AND postmeta.meta_value != 'no'
			AND postmeta2.meta_key = '_stock' AND CAST(postmeta2.meta_value AS SIGNED) < '{$nostock}'
			GROUP BY posts.ID ORDER BY posts.post_title" );

	}


	/**
	 * Output the report
	 */
	public function output_report() {
		global $wcbm_total_backorder_items;

		$this->prepare_items();
		echo '<div id="poststuff" class="woocommerce-reports-wide">';
		$this->display();
		echo '</div>';
		echo '<p><a href="'. plugin_dir_url( __FILE__ ) . 'csv-export.php">' . __( 'CSV export', 'woo-backorder-manager' ) . '</a> | ' . sprintf ( __( 'Total items on backorder: %d', 'woo-backorder-manager' ), $wcbm_total_backorder_items ) . '</p>';
	}


	/**
	 * Get column value.
	 * @param mixed $item
	 * @param string $column_name
	 */
	public function column_default( $item, $column_name ) {
		global $product, $wcbm_total_backorder_items;

		if ( ! $product || $product->get_id() !== $item->id ) {
			$product = wc_get_product( $item->id );
		}

		if ( ! $wcbm_total_backorder_items ) {
			$wcbm_total_backorder_items = 0;
		}

		switch( $column_name ) {

			case 'product' :
				if ( $sku = $product->get_sku() ) {
					echo $sku . ' - ';
				}

				echo $product->get_title();

				// Get variation data
				if ( $product->is_type( 'variation' ) ) {
					$list_attributes = array();
					$attributes = $product->get_variation_attributes();

					foreach ( $attributes as $name => $attribute ) {
						$list_attributes[] = wc_attribute_label( str_replace( 'attribute_', '', $name ) ) . ': <strong>' . $attribute . '</strong>';
					}

					echo '<div class="description">' . implode( ', ', $list_attributes ) . '</div>';
				}
			break;

			case 'categories' :
				$item_categories = get_the_terms( $product->get_id(), 'product_cat' );
				if ( ! empty( $item_categories ) ) {
					$term_names = wp_list_pluck( $item_categories, 'name' );
					echo implode( ', ', $term_names );
				} else {
					echo '-';
				}

			break;

			case 'parent' :
				if ( $item->parent ) {
					echo get_the_title( $item->parent );
				} else {
					echo '-';
				}
			break;

			case 'units_backordered' :
				$backorders = - ( $product->get_stock_quantity() );
				echo $backorders;
				$wcbm_total_backorder_items += $backorders;
			break;

			case 'wc_actions' :
				?><p>
					<?php
						$actions = array();
						$action_id = $product->is_type( 'variation' ) ? $item->parent : $item->id;

						$actions['edit'] = array(
							'url'    => admin_url( 'post.php?post=' . $action_id . '&action=edit' ),
							'name'   => __( 'Edit', 'woo-backorder-manager' ),
							'action' => "edit"
						);

						if ( $product->is_visible() ) {
							$actions['view'] = array(
								'url'    => get_permalink( $action_id ),
								'name'   => __( 'View', 'woo-backorder-manager' ),
								'action' => "view"
							);
						}

						$actions = apply_filters( 'woocommerce_admin_stock_report_product_actions', $actions, $product );

						foreach ( $actions as $action ) {
							printf( '<a class="button tips %s" href="%s" data-tip="%s ' . __( 'product', 'woo-backorder-manager' ) . '">%s</a>', $action['action'], esc_url( $action['url'] ), esc_attr( $action['name'] ), esc_attr( $action['name'] ) );
						}
					?>
				</p><?php
			break;
		}
	}


	/**
	 * get_columns function.
	 */
	public function get_columns() {

		$columns = array(
			'product'           => __( 'Product', 'woo-backorder-manager' ),
			'categories'        => __( 'Categories', 'woo-backorder-manager' ),
			'units_backordered' => __( 'Units backordered', 'woo-backorder-manager' ),
			'parent'            => __( 'Parent', 'woo-backorder-manager' ),
			'wc_actions'        => __( 'Actions', 'woo-backorder-manager' ),
		);

		return $columns;
	}


	/**
	 * prepare_items function.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->get_items();
	}
}
