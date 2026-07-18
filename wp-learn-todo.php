<?php
/**
 * Plugin Name:       Wp DSpace Query
 * Description:       Custom Gutenberg block querying external DSpace repositories cleanly.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-dspace-query
 *
 * @package CreateBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Registers the block(s) metadata from the `blocks-manifest.php`.
 */
function wp_dspace_query_block_init() {
    wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
}
add_action( 'init', 'wp_dspace_query_block_init' );

/**
 * Register a custom secure endpoint wrapper for DSpace inside the plugin.
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'dspace-block/v1', '/search', array(
        'methods'             => 'GET',
        'callback'            => 'wp_dspace_query_handle_dspace_proxy_request',
        'permission_callback' => function() {
            // Secure: Restrict endpoint to users who can actually edit posts in the dashboard
            return current_user_can( 'edit_posts' );
        },
        'args'                => array(
			'author' => array(
				'type'              => 'string',
				'default'           => 'Minerva',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'size'   => array(
				'type'              => 'integer',
				'default'           => 2,
				'sanitize_callback' => 'absint',
			),
		),
    ) );
} );

/**
 * Callback function to fetch and return DSpace data securely server-side.
 */
function wplearn_handle_dspace_proxy_request( $request ) {
    $author = $request->get_param( 'author' );
    $size   = $request->get_param( 'size' );

    // Sanitize parameters and ensure fallbacks
    $author = ! empty( $author ) ? trim( $author ) : 'Smith';
    $size   = ! empty( $size ) ? intval( $size ) : 2;

    $url = sprintf(
        'https://demo.dspace.org/server/api/discover/search/objects?query=author:%s&size=%d',
        rawurlencode( $author ),
        $size
    );

    // Fetch from DSpace with a reliable User-Agent
    $response = wp_remote_get( $url, array(
        'timeout'    => 15,
        'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
    ) );

    if ( is_wp_error( $response ) ) {
        return new WP_REST_Response( array( 'error' => $response->get_error_message() ), 500 );
    }

    $code         = wp_remote_retrieve_response_code( $response );
    $body_content = wp_remote_retrieve_body( $response );

    if ( $code !== 200 ) {
        return new WP_REST_Response( array( 'error' => 'DSpace server returned code ' . $code ), $code );
    }

    $data = json_decode( $body_content, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_REST_Response( array( 'error' => 'Invalid JSON signature from remote repository' ), 500 );
    }
    
    return new WP_REST_Response( $data, 200 );
}