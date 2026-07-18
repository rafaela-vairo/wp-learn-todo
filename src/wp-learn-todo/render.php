<?php
/**
 * PHP Render template for the DSpace block.
 *
 * @var array    $attributes Block attributes saved in the editor.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

$author_query = isset( $attributes['authorQuery'] ) ? trim( $attributes['authorQuery'] ) : '';
$max_results  = isset( $attributes['maxResults'] ) ? intval( $attributes['maxResults'] ) : 2;

if ( empty( $author_query ) ) {
    return;
}

// Keep cache keys compact and distinct per setting variation
$transient_key = 'dspace_query_' . md5( $author_query . '_' . $max_results );
$formatted_results = get_transient( $transient_key );

if ( false === $formatted_results ) {
    $formatted_results = array();

    $target_url = sprintf(
        'https://demo.dspace.org/server/api/discover/search/objects?query=author:%s&size=%d',
        rawurlencode( $author_query ),
        $max_results
    );

    $response = wp_remote_get( $target_url, array(
        'timeout'    => 10,
        'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
    ) );

    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $items = isset( $body['_embedded']['searchResult']['_embedded']['objects'] ) 
            ? $body['_embedded']['searchResult']['_embedded']['objects'] 
            : array();

        foreach ( $items as $item ) {
            $object_data = isset( $item['_embedded']['indexableObject'] ) ? $item['_embedded']['indexableObject'] : array();
            $item_id     = isset( $object_data['id'] ) ? $object_data['id'] : '';

            // Generate clean frontend URLs matching your DSpace 7 environment
            $public_url = ! empty( $item_id ) 
                ? 'https://demo.dspace.org/entities/publication/' . $item_id 
                : '#';

            $formatted_results[] = array(
                'title' => isset( $object_data['name'] ) ? $object_data['name'] : __( 'Untitled Publication', 'dspace-block' ),
                'url'   => $public_url,
            );
        }

        // Cache responses safely for 1 hour to prevent server performance degradation
        set_transient( $transient_key, $formatted_results, HOUR_IN_SECONDS );
    }
}
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
    <div class="dspace-frontend-query-box">
        <?php if ( empty( $formatted_results ) ) : ?>
            <p><?php esc_html_e( 'No items found or repository unavailable.', 'dspace-block' ); ?></p>
        <?php else : ?>
            <ul class="dspace-results-list">
                <?php foreach ( $formatted_results as $item ) : ?>
                    <li class="dspace-item">
                        <a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noreferrer">
                            <?php echo esc_html( $item['title'] ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>