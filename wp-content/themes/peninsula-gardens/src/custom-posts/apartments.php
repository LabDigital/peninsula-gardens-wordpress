<?php

/**
 * Post Type: Apartments
 */

add_action('init', function() {
  register_post_type('apartment', [
    'labels' => [
      'name' => 'Apartments',
      'singular_name' => 'Apartment',
      'all_items' => 'All Apartments',
    ],
    'supports' => [
      'title', 'thumbnail', 'revisions'
    ],
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true,
    'show_in_menu' => true,
    'query_var' => true,
    'has_archive' => false,
    'rewrite' => [
      'slug' => 'apartments',
      'with_front' => false,
    ],
    'menu_icon' => 'dashicons-admin-multisite',
    'show_in_graphql' => true,
    'graphql_single_name' => 'apartment',
    'graphql_plural_name' => 'apartments',
  ]);
});

register_activation_hook(__FILE__, function() {
  flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
  flush_rewrite_rules();
});
