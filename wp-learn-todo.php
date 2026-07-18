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

// Permite sobrescrever via wp-config.php (ex: define('WP_DSPACE_QUERY_HOST', 'https://dspace.suarede.interna'))
if ( ! defined( 'WP_DSPACE_QUERY_HOST' ) ) {
	define( 'WP_DSPACE_QUERY_HOST', 'https://demo.dspace.org' );
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
            // Uso interno apenas: professores/editores da rede. Não expor busca a visitantes do site.
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
function wp_dspace_query_handle_dspace_proxy_request( $request ) {
    $author = trim( $request->get_param( 'author' ) );

    // Limita o tamanho para evitar payloads excessivos vindos do DSpace
    $size = min( max( (int) $request->get_param( 'size' ), 1 ), 50 );

    $url = sprintf(
        '%s/server/api/discover/search/objects?query=author:%s&size=%d',
        untrailingslashit( WP_DSPACE_QUERY_HOST ),
        rawurlencode( $author ),
        $size
    );

	$cache_key = 'wp_dspace_query_' . md5( $url );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return new WP_REST_Response( $cached, 200 );
	}

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

    set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS ); // Cache for 5 minutes
    
    return new WP_REST_Response( $data, 200 );
}