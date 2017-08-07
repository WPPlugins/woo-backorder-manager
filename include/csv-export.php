<?php
// no need for the template engine
define( 'WP_USE_THEMES', false );
// Load WordPress Core
require_once( '../../../../wp-load.php' );

// user has sufficient capabilities?
if ( ! current_user_can( 'view_woocommerce_reports' ) ) {
	_e( 'No access! Please login first.', 'woo-backorder-manager' );
	die();
}

header( 'Content-type: text/csv' );
header( 'Content-Disposition: attachment; filename=backorders-' . date( 'Y-m-d' ) . '.csv' );

// header column
echo __( 'Product', 'woo-backorder-manager' ) . ';';
echo __( 'Categories', 'woo-backorder-manager' ) . ';';
echo __( 'Units backordered', 'woo-backorder-manager' ) . ';';
echo __( 'Parent', 'woo-backorder-manager' ) . "\r\n";

global $wpdb;


$nostock = absint( max( get_option( 'woocommerce_notify_no_stock_amount' ), 0 ) );

// Get products using a query (too advanced for get_posts)
$query_results = $wpdb->get_results( "SELECT posts.ID AS id, posts.post_parent AS parent FROM {$wpdb->posts} as posts
	INNER JOIN {$wpdb->postmeta} AS postmeta ON posts.ID = postmeta.post_id
	INNER JOIN {$wpdb->postmeta} AS postmeta2 ON posts.ID = postmeta2.post_id
	WHERE posts.post_type IN ( 'product', 'product_variation' )
	AND posts.post_status = 'publish'
	AND postmeta.meta_key = '_backorders' AND postmeta.meta_value != 'no'
	AND postmeta2.meta_key = '_stock' AND CAST(postmeta2.meta_value AS SIGNED) < '{$nostock}'
	GROUP BY posts.ID ORDER BY posts.post_title", OBJECT );

foreach ( $query_results as $query_result ) :

	$product = wc_get_product( $query_result->id );

	// Product
	$product_title = '';
	if ( $sku = $product->get_sku() ) {
		$product_title .= $sku . ' - ';
	}

	$product_title .= $product->get_title();

	// Get variation data
	if ( $product->is_type( 'variation' ) ) {
		$list_attributes = array();
		$attributes = $product->get_variation_attributes();

		foreach ( $attributes as $name => $attribute ) {
			$list_attributes[] = wc_attribute_label( str_replace( 'attribute_', '', $name ) ) . ': ' . $attribute;
		}

		$product_title .= ' ' . implode( ', ', $list_attributes );
	}

	// enclose product title and escape ""
	echo '"' . str_replace( '"', '""', $product_title ) . '";';


	// Categories
	$item_categories = get_the_terms( $product->get_id(), 'product_cat' );
	if ( ! empty( $item_categories ) ) {
		$term_names = wp_list_pluck( $item_categories, 'name' );
		echo implode( ', ', $term_names ) . ';';
	} else {
		echo '-;';
	}


	// Units backordered
	$backorders = - ( $product->get_stock_quantity() );
	echo $backorders . ';';


	// Parent
	if ( $query_result->parent ) {
		echo get_the_title( $query_result->parent ) . ';';
	} else {
		echo '-;';
	}

	echo "\r\n";

endforeach;
