<?php
function custom_taxonomy_bedrooms() {
  $labels = array(
    'name' => 'Bedrooms',
    'singular_name' => 'Bedroom',
    'menu_name' => 'Bedrooms',
    'all_items' => 'All Bedrooms',
    'parent_item' => 'Parent Bedroom',
    'parent_item_colon' => 'Parent Bedroom:',
    'new_item_name' => 'New Bedroom Name',
    'add_new_item' => 'Add New Bedroom',
    'edit_item' => 'Edit Bedroom',
    'update_item' => 'Update Bedroom',
    'separate_items_with_commas' => 'Separate bedrooms with commas',
    'search_items' => 'Search bedrooms',
    'add_or_remove_items' => 'Add or remove bedrooms',
    'choose_from_most_used' => 'Choose from the most used bedrooms',
  );
  $args = array(
    'default_term' => [
      'name' => '1',
      'slug' => '1',
    ],
    'labels' => $labels,
    'hierarchical' => true,
    'public' => true,
    'show_ui' => true,
    'show_admin_column' => true,
    'show_in_nav_menus' => true,
    'show_in_graphql' => true,
    'show_in_quick_edit' => true,
    'graphql_single_name' => 'bedrooms',
    'graphql_plural_name' => 'bedrooms',
    'rewrite' => array('slug' => 'bedrooms'),
  );
  register_taxonomy('bedrooms', ['apartment'], $args);
}

add_action('init', 'custom_taxonomy_bedrooms');
