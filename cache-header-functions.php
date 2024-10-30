<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *  Update HTTP headers by adding the 'Cache-Tag' header with the IDs of the current posts on the page.
 *  This function is linked to the 'wp_headers' hook.
 * @param $headers
 * @return mixed
 */
function isfwp_add_headers($headers) {
    // Check if the user is not in the WordPress admin area
    if (!is_admin()) {
        $object_ids = [];

        // Get object IDs based on page type
        if (is_front_page()) {
            $object_ids = isfwp_cache_handle_tag('front');
        } elseif (is_single() || is_page()) {
            $object_ids[] = 'p' . get_the_ID();
            $object_ids = array_merge($object_ids, isfwp_get_category_and_taxonomy_ids(get_the_ID()));
        } elseif (is_archive()) {
            $object_ids = isfwp_cache_handle_tag('archive');
        }

        // Remove duplicates IDs
        $object_ids = array_unique($object_ids);

        // Add IDs to 'Cache-tags' header
        if (!empty($object_ids)) {
            header('Cache-Tag: ' . implode(',', $object_ids));
        }
    }
    return;

    // Return the updated headers
    // return $headers;
}

/**
 *  Gets the IDs of the current posts based on the specified page type.
 * @param $type
 * @return array|mixed
 */
function isfwp_cache_handle_tag($type) {
    $object_ids = [];

    // Start WP loop to get current posts
    if (have_posts()) {
        while (have_posts()) {
            the_post();
            $object_ids[] = 'p' . get_the_ID();
            $object_ids = array_merge($object_ids, isfwp_get_category_and_taxonomy_ids(get_the_ID()));
        }
    }

    // Add the current taxonomy ID if we are in an archive
    if ($type === 'archive') {
        $queried_object = get_queried_object();
        if (isset($queried_object->taxonomy)) {
            $object_ids[] = 't' . $queried_object->term_id;
        }
    }

    // Add category and taxonomy IDs
    $categories = get_the_category();
    foreach ($categories as $category) {
        $object_ids[] = 'c' . $category->term_id;
    }

    return $object_ids;
}

/**
 * Retrieves the IDs of categories, tags, and other taxonomy terms associated with a post.
 *
 * @param int $post_id The ID of the post.
 * @return array The IDs of categories, tags, and taxonomy terms.
 */
function isfwp_get_category_and_taxonomy_ids($post_id) {
    $term_ids = [];

    // Fetch post type to ensure it's valid
    $post_type = get_post_type($post_id);
    if (!$post_type) {
        return $term_ids; // Return empty if post type is invalid
    }

    // Get all registered taxonomies for this post type
    $taxonomies = get_object_taxonomies($post_type, 'objects');

    // Loop through each taxonomy and get the terms associated with the post
    foreach ($taxonomies as $taxonomy_name => $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy_name);
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                // Check for specific taxonomies (category and post_tag)
                if ($taxonomy_name === 'category') {
                    $term_ids[] = 'c' . $term->term_id; // Prefix with 'c' for categories
                } elseif ($taxonomy_name === 'post_tag') {
                    $term_ids[] = 't' . $term->term_id; // Prefix with 't' for tags
                } else {
                    // Prefix with the first letter of the taxonomy name for other taxonomies
                    $term_ids[] = $taxonomy_name[0] . $term->term_id;
                }
            }
        }
    }

    // Remove duplicates if necessary
    $term_ids = array_unique($term_ids);

    return $term_ids;
}


// Hook to 'template_redirect' to ensures that the headers are set after the main query is executed.
add_action('template_redirect', 'isfwp_add_headers');
