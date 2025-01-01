<?php
add_action('init', function() {
  require_once(ABSPATH . '/wp-admin/includes/file.php');
  WP_Filesystem();

  // ACF Options Pages
  if (function_exists('acf_add_options_page')) {
    acf_add_options_page([
      'page_title' => 'Site Settings',
      'menu_title' => 'Site Settings',
      'menu_slug' => 'site-settings',
      'position' => 10,
      'capability' => 'edit_posts',
      'icon_url' => 'dashicons-admin-settings',
      'redirect' => true,
      'show_in_graphql' => true,
      'graphql_field_name' => 'siteSettings'
    ]);
  }
});

add_action('after_setup_theme', function() {
  // This theme uses post thumbnails
  add_theme_support('post-thumbnails');

  // Images Sizes
  add_image_size('w2560', 2560);
  add_image_size('w1920', 1920);
  add_image_size('w768', 768);
  add_image_size('w30', 30);
});

add_filter('big_image_size_threshold', function() {
  return 2560;
});

// Disable unneeded scripts and styles
add_action('admin_init', function() {
  if (isset($_GET['post'])) { // Remove editor for blocks template.
    $template = get_post_meta(wp_unslash($_GET['post']), '_wp_page_template', true);

    if ($template === 'page-templates/flexible-content.php') {
      remove_post_type_support('page', 'editor');
    }
  }
});

// JP: Unregister the awful default WordPress scripts and styles
add_action('wp_print_styles', function() {
  wp_dequeue_style('wp-block-library');
  remove_action('wp_head', 'print_emoji_detection_script', 7);
  remove_action('admin_print_scripts', 'print_emoji_detection_script');
  remove_action('wp_print_styles', 'print_emoji_styles');
  remove_action('admin_print_styles', 'print_emoji_styles');
}, 100);

// Remove redundant bits from dashboard menu
add_action('admin_menu', function() {
  remove_menu_page('edit-comments.php');
  remove_menu_page('edit.php'); // Posts
});

// Remove redundant navigation links from the admin bar.
add_action('wp_before_admin_bar_render', function() {
  global $wp_admin_bar;
  $wp_admin_bar->remove_menu('comments');
  $wp_admin_bar->remove_menu('wp-logo');
  $wp_admin_bar->remove_menu('search');
});

// GraphQL Schema Additions
add_action('graphql_register_types', function() {
  // get SVG contents
  register_graphql_field('MediaItem', 'svgContents', [
    'type' => 'String',
    'resolve' => function($root) {
      global $wp_filesystem;
      return mime_content_type(get_attached_file($root->databaseId)) === 'image/svg+xml'
        ? $wp_filesystem->get_contents(get_attached_file($root->databaseId))
        : null;
    }
  ]);
});

// Custom Preview link
add_filter('preview_post_link', function () {
  global $post;

  $restNonce = wp_create_nonce('wp_rest');
  $home_url = get_home_url();
  $post_type = get_post_type($post);
  $post_status = get_post_status($post);
  $post_type_data = get_post_type_object($post_type);
  $post_type_slug = $post_type_data->rewrite['slug'];

  $preview_string = "?preview_id=$post->ID&preview=true&preview_nonce=$restNonce";

  if ($post_status !== 'publish') {
    if ($post_type === 'page') {
      return "$home_url/draft/$preview_string";
    }

    return "$home_url/$post_type_slug/draft/$preview_string";
  }

  if ($post_type === 'page') {
    return "$home_url/$post->post_name$preview_string";
  }

  return "$home_url/$post_type_slug/$post->post_name$preview_string";
});

add_action('rest_api_init', function () {
  // Sitemaps
  $sitemap_post_types = [
    ['slug' => 'pages', 'post_type' => 'page'], // Pages
  ];

  foreach ($sitemap_post_types as $post_type) {
    register_rest_route('sitemap', "/{$post_type['slug']}", [
      'methods' => 'GET',
      'callback' => function($data) use ($post_type) {
        $posts = [];

        $query = new WP_Query([
          'order' => 'ASC',
          'orderby' => 'title',
          'post_status' => 'publish',
          'post_type' => $post_type['post_type'],
          'paged' => $data->get_param('page') ?? 1,
          'posts_per_page' => $data->get_param('limit') ?? 100,
        ]);

        while ($query->have_posts()) {
          $query->the_post();
          global $post;

          $posts[] = [
            'uri' => str_replace(home_url(), '', get_permalink()),
            'last_modified' => $post->post_modified,
          ];
        }

        return new WP_REST_Response([
          'posts' => $posts,
          'total' => $query->found_posts,
        ]);
      },
    ]);
  }

  //  Redirects
  register_rest_route('redirect', 'hambly-freeman(?:/(?P<permalink>.+))?', [
    'methods' => 'GET',
    'callback' => function ($data) {
      $permalink = $data->get_param('permalink');
      if (!$permalink) {
        $permalink = '';
      }

      if (get_field('redirects', 'option')) {
        foreach (explode("\n", get_field('redirects', 'option')) as $redirect) {
          $wildcard = false;
          $softWildcard = false; // soft wildcard retains subpages

          list($from, $to) = explode(' -> ', trim($redirect));

          if (substr($from, 0, 1) === '/') { // if from === /path/
            $from = substr($from, 1); // /path/ becomes path/ (remove starting slash)
          }

          if (substr($from, -1) === '/') {
            $from = substr($from, 0, strlen($from) - 1); // remove ending slash too, if there
          }

          if (substr($from, -1) === '*') { // if it ends in an asterisk...
            $wildcard = true; // its a wildcard
            $from = substr($from, 0, strlen($from) - 1); // remove wildcard asterisk

            if (substr($from, -1) === '/') {
              $from = substr($from, 0, strlen($from) - 1); // remove ending slash if there (again)
            }
          }

          if (substr($from, -1) === '+') { // if it ends in a plus...
            $softWildcard = true; // it's a soft wildcard
            $from = substr($from, 0, strlen($from) - 1); // remove soft wildcard plus

            if (substr($from, -1) === '/') {
              $from = substr($from, 0, strlen($from) - 1); // remove ending slash if there (again)
            }
          }

          if (strpos($permalink, '/') !== false) { // if there's still a slash in there somewhere...
            $subpage = substr($permalink, strpos($permalink, '/') + 1); // get subpage
            $mainpage = str_replace('/' . $subpage, '', $permalink); // get parent page
          } else {
            $subpage = false;
            $mainpage = false;
          }

          if ('/' . $permalink === $to) { // if we're trying to redirect to the same page ...
            return false; // return false, to combat infinite redirects
          }

          if ($softWildcard && $from === $permalink) { // if the current url just matches from, redirect normally
            // (soft wildcard)
            return [
              'to' => $to,
              'permanent' => true,
              'subpage' => $subpage,
              'wildcard' => false,
              'soft-wildcard' => true,
            ];
          }

          if ($softWildcard && $from === $mainpage) { // if the parent page matches and there's a subpage, redirect
            // with the subpage (soft wildcard)
            if (substr($to, -1) !== '/') {
              $to = $to . '/'; // add ending slash if needed
            }

            return [
              'to' => $to . $subpage,
              'permanent' => true,
              'subpage' => $subpage,
              'wildcard' => false,
              'soft-wildcard' => true,
            ];
          }

          if ($wildcard && ($from === $mainpage || $from === $permalink)) { // wildcard redirect, redirect if subpage
            // without retaining subpage
            return [
              'to' => $to,
              'permanent' => true,
              'subpage' => $subpage,
              'wildcard' => true,
              'soft-wildcard' => false,
            ];
          }

          if (!$softWildcard && !$wildcard && $from === $permalink) { // redirect only parent page, no subpages involved
            return [
              'to' => $to,
              'permanent' => true,
              'subpage' => $subpage,
              'wildcard' => false,
              'soft-wildcard' => false,
            ];
          }
        }
      }

      return false; // else... don't redirect!
    },
  ]);
});

// only published posts to appear in acf fields
add_filter('acf/fields/post_object/query', function ( $args ) {
  $args['post_status'] = 'publish';

  return $args;
}, 10, 3);

// only published posts to be returned in acf fields
add_filter( 'acf/load_value/type=relationship', function( $value, $post_id, $field ) {
  $returned_value = array();
  foreach($value as $key => $id){
    if( get_post_status( $id ) == 'publish' ){
      $returned_value[] = $id;
    }
  }
  return $returned_value;
}, 10, 3);

// hide default WP visibility options & some gravity form options
add_action('admin_head', function () {
  echo '<style>
    .misc-pub-section.misc-pub-visibility,
    .gforms_edit_form .autocomplete_setting,
    .gforms_edit_form .preview-form,
    .gforms_edit_form .duplicate_setting,
    .gforms_edit_form .input_mask_setting,
    .gforms_edit_form .label_placement_setting,
    .gforms_edit_form .size_setting,
    .gforms_edit_form .password_field_setting,
    .gforms_edit_form .visibility_setting,
    .gforms_edit_form .prepopulate_field_setting,
    .gforms_edit_form .conditional_logic_wrapper,
    .gforms_edit_form .email_confirm_setting,
    .gforms_edit_form .enable_enhanced_ui_setting field_setting,
    .gforms_edit_form .rich_text_editor_setting,
    .gforms_edit_form button[aria-controls="add_advanced_fields"] + div button:not([data-type="email"]),
    .gforms_edit_form button[aria-controls="add_post_fields"],
    .gforms_edit_form button[aria-controls="add_pricing_fields"],
    .gforms_edit_form button[data-type="hidden"],
    .gforms_edit_form button[data-type="section"],
    .gforms_edit_form button[data-type="page"]
    .gforms_edit_form .submit_type_setting #submit_type_image,
    .gforms_edit_form .submit_type_setting #submit_type_image + label,
    .gforms_edit_form .submit_width_setting,
    .gforms_edit_form .submit_location_setting,
    .gforms_edit_form .search-button,
    #gform-settings-section-form-layout,
    #gform-settings-section-form-button,
    #gform-settings-section-save-and-continue,
    #gform-settings-section-restrictions,
    #gform-settings-section-form-options {
      display: none !important;
    }
  </style>';
});

/*
 * Replacing domain for rest api requests from Gutenberg editor if youre using
 * WP headless and WP_SITEURL & WP_HOME are not the same domain
 * (has nothing to do with yoast)
 */
add_filter('rest_url', function($url) {
  $url = str_replace(home_url(), site_url(), $url);
  return $url;
});

/*
 * Replacing domain for stylesheet to xml if youre using WP headless
 * and WP_SITEURL & WP_HOME are not the same domain
 */
function filter_wpseo_stylesheet_url( $stylesheet ) {
  $home = parse_url(get_option('home'));
  $site = parse_url(get_option('siteurl'));
  return str_replace($home, $site, $stylesheet);
};
add_filter( 'wpseo_stylesheet_url', 'filter_wpseo_stylesheet_url', 10, 1 );

/*
 * Replacing domain for sitemap index if youre using WP headless
 * and WP_SITEURL & WP_HOME are not the same domain
 */
function filter_wpseo_sitemap_index_links( $links ) {
  $home = parse_url(get_option('home'));
  $site = parse_url(get_option('siteurl'));
  foreach($links as $i => $link)
    $links[$i]['loc'] = str_replace($home, $site, $link['loc']);
  return $links;
};
add_filter( 'wpseo_sitemap_index_links', 'filter_wpseo_sitemap_index_links', 10, 1 );
