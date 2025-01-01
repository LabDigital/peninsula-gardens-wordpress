<?php
add_action(
    'init', function () {
        include_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();

        // ACF Options Pages
        if (function_exists('acf_add_options_page')) {
            acf_add_options_page(
                [
                'page_title' => 'Site Settings',
                'menu_title' => 'Site Settings',
                'menu_slug' => 'site-settings',
                'position' => 10,
                'capability' => 'edit_posts',
                'icon_url' => 'dashicons-admin-settings',
                'redirect' => true,
                'show_in_graphql' => true,
                'graphql_field_name' => 'siteSettings'
                ]
            );
        }
    }
);

add_action(
    'after_setup_theme', function () {
        // This theme uses post thumbnails
        add_theme_support('post-thumbnails');

        // Images Sizes
        add_image_size('w2560', 2560);
        add_image_size('w1920', 1920);
        add_image_size('w768', 768);
        add_image_size('w30', 30);
    }
);

add_filter(
    'big_image_size_threshold', function () {
        return 2560;
    }
);

// Custom Post Types
require_once 'custom-posts/apartments.php';

// Custom Taxonomies
require_once 'custom-taxonomies/bedrooms.php';

// Disable unneeded scripts and styles
add_action(
    'admin_init', function () {
        if (isset($_GET['post'])) { // Remove editor for blocks template.
            $template = get_post_meta(wp_unslash($_GET['post']), '_wp_page_template', true);

            if ($template === 'page-templates/flexible-content.php') {
                remove_post_type_support('page', 'editor');
            }
        }
    }
);

// JP: Unregister the awful default WordPress scripts and styles
add_action(
    'wp_print_styles', function () {
        wp_dequeue_style('wp-block-library');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
    }, 100
);

// Remove redundant bits from dashboard menu
add_action(
    'admin_menu', function () {
        remove_menu_page('edit-comments.php');
        remove_menu_page('edit.php'); // Posts
    }
);

// Remove redundant navigation links from the admin bar.
add_action(
    'wp_before_admin_bar_render', function () {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('comments');
        $wp_admin_bar->remove_menu('wp-logo');
        $wp_admin_bar->remove_menu('search');
    }
);

// GraphQL Schema Additions
add_action(
    'graphql_register_types', function () {
        // get SVG contents
        register_graphql_field(
            'MediaItem', 'svgContents', [
            'type' => 'String',
            'resolve' => function ($root) {
                global $wp_filesystem;
                return mime_content_type(get_attached_file($root->databaseId)) === 'image/svg+xml'
                ? $wp_filesystem->get_contents(get_attached_file($root->databaseId))
                : null;
            }
            ]
        );
    }
);

// Custom Preview link
add_filter(
    'preview_post_link', function ($link) {
        global $post;

        $post_type = get_post_type($post);
        $post_type_data = get_post_type_object($post_type);
        $post_type_slug = $post_type_data->rewrite['slug'];

        $preview_string = "?preview_id=$post->ID&preview=true";

        if (get_post_status($post) !== 'publish') {
            if ($post_type === 'page') {
                return get_home_url() . "/draft/$preview_string";
            } else {
                return get_home_url() . "/$post_type_slug/draft/$preview_string";
            }
        }

        if ($post_type === 'page') {
            return get_home_url() . '/' . $post->post_name . $preview_string;
        }

        return get_home_url() . "/$post_type_slug/$post->post_name" . $preview_string;
    }
);

add_action(
    'rest_api_init', function () {
        //  Redirects
        register_rest_route(
            'redirect', 'hambly-freeman(?:/(?P<permalink>.+))?', [
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
            ]
        );

        // INSTAGRAM
        register_rest_route(
            'peninsula', '/instagram-feed', [
            'methods' => 'GET',
            'callback' => function () {
                do_action('refresh_instagram_access_token');
                $time = time();
                $expires_in = $time + (60 * 20); // 20 minutes
                $cache_path = 'instagram-feed.cache';

                $posts = [];
                $fetch_new_posts = true;

                $access_token = "IGQVJWX2YyMmcwYXJHYjJsSHFrOENIYTItVVE2dVBkNl9VYXhzMmxmdnh6UkIxUXBkeTZA0bXNWVE5iZATVneGRRWGdvUk9yRzFjUWxuNUNjMHZA2azAwbG5KeWdTdmN5a3lMSUZAMcExmS1IxQXZA0dkN3SQZDZD";
                $token_cache_path = 'instagram-access-token.cache';

                if (file_exists($token_cache_path)) {
                    $token_cache = json_decode(file_get_contents($token_cache_path));
                    $access_token = $token_cache->access_token;
                }

                if (file_exists($cache_path)) {
                    $cache = json_decode(file_get_contents($cache_path));
                    $posts = $cache->posts;
                    if ($time < $cache->expires_in) {
                          $fetch_new_posts = false;
                    }
                }//*/

                if ($fetch_new_posts) {
                    $ch = curl_init();

                    curl_setopt_array(
                        $ch, array(
                        CURLOPT_URL => "https://graph.instagram.com/me/media?access_token=".$access_token."&fields=id,timestamp,username,media_type,caption,media_url,permalink",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'GET',
                        )
                    );

                    $response = curl_exec($ch);

                    $errstr=null;
                    //$response = curl_exec($ch);
                    if(curl_errno($ch)) {
                             $errstr = curl_errno($ch).": ".curl_error($ch);
                    }
                    curl_close($ch);

                    $response = json_decode($response);

                    if (!$response->error && $response->data) {
                        file_put_contents(
                            $cache_path, json_encode(
                                [
                                'posts' => $response->data,
                                'expires_in' => $expires_in,
                                ]
                            )
                        );
                    }
                    else {
                         print_r($errstr);
                         die("     hiuiu;lj");
                    }
                }

                return [
                'posts' => array_map(
                    function ($post) {
                        return [
                        'id' => $post->id,
                        'username' => $post->username,
                        'type' => $post->media_type,
                        'caption' => $post->caption,
                        'image_url' => $post->media_url,
                        'url' => $post->permalink
                        ];
                    }, $posts
                ),
                ];
            }
            ]
        );
    }
);

if (!wp_next_scheduled('refresh_instagram_access_token')) {
    wp_schedule_event(time(), 'daily', 'refresh_instagram_access_token');
}

add_action(
    'refresh_instagram_access_token', function () {
        $time = time();
        $expires_in = $time + (86400 * 2); // 2 days
        $cache_path = 'instagram-access-token.cache';
        //$access_token = 'IGQVJWWWdjVXlsRXFlT0tNa1hvOVZAUVnhKeXVGTE1LeDk5TzE0dzRSRzR3NGRIVUFKUUFCUWpqWWpFcXBRWEpQaGN3azJveEZACcmlTd1hOUkRxNVNZARG45Tl9penprTHhWd19kNUJnQmhTVnN2ZAEsyYwZDZD';
        $access_token = 'IGQVJWX2YyMmcwYXJHYjJsSHFrOENIYTItVVE2dVBkNl9VYXhzMmxmdnh6UkIxUXBkeTZA0bXNWVE5iZATVneGRRWGdvUk9yRzFjUWxuNUNjMHZA2azAwbG5KeWdTdmN5a3lMSUZAMcExmS1IxQXZA0dkN3SQZDZD';

        if (file_exists($cache_path)) {
            $cache = json_decode(file_get_contents($cache_path));

            if ($time >= $cache->access_token_expires_in) {
                $ch = curl_init(
                    'https://graph.instagram.com/refresh_access_token?' . http_build_query(
                        [
                        'access_token' => $cache->access_token,
                        'grant_type' => 'ig_refresh_token',
                        ]
                    )
                );

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

                $response = json_decode(curl_exec($ch));
                curl_close($ch);

                if (!$response->error && $access_token = $response->access_token) {
                    file_put_contents(
                        $cache_path, json_encode(
                            [
                            'access_token' => $access_token,
                            'expires_in' => $expires_in,
                            ]
                        )
                    );
                }
            }
        } else {
            file_put_contents(
                $cache_path, json_encode(
                    [
                    'access_token' => $access_token,
                    'expires_in' => $expires_in,
                    ]
                )
            );
        }
    }
);

// only published posts to appear in acf fields
add_filter(
    'acf/fields/post_object/query', function ( $args ) {
        $args['post_status'] = 'publish';

        return $args;
    }, 10, 3
);

// only published posts to be returned in acf fields
add_filter(
    'acf/load_value/type=relationship', function ( $value, $post_id, $field ) {
        $returned_value = array();
        foreach($value as $key => $id){
            if(get_post_status($id) == 'publish' ) {
                $returned_value[] = $id;
            }
        }
        return $returned_value;
    }, 10, 3
);

// hide default WP visibility options & some gravity form options
add_action(
    'admin_head', function () {
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
    }
);

// Stop WordPress from removing non-breaking spaces
function dmcg_allow_nbsp_in_tinymce( $init )
{
    $init['entities'] = '160,nbsp,38,amp,60,lt,62,gt';
    $init['entity_encoding'] = 'named';
    return $init;
}
add_filter('tiny_mce_before_init', 'dmcg_allow_nbsp_in_tinymce');

/*
 * Replacing domain for rest api requests from Gutenberg editor if youre using
 * WP headless and WP_SITEURL & WP_HOME are not the same domain
 * (has nothing to do with yoast)
 */
add_filter(
    'rest_url', function ($url) {
        $url = str_replace(home_url(), site_url(), $url);
        return $url;
    }
);

/*
 * Replacing domain for stylesheet to xml if youre using WP headless
 * and WP_SITEURL & WP_HOME are not the same domain
 */
function filter_wpseo_stylesheet_url( $stylesheet )
{
    $home = parse_url(get_option('home'));
    $site = parse_url(get_option('siteurl'));
    return str_replace($home, $site, $stylesheet);
};
add_filter('wpseo_stylesheet_url', 'filter_wpseo_stylesheet_url', 10, 1);

/*
 * Replacing domain for sitemap index if youre using WP headless
 * and WP_SITEURL & WP_HOME are not the same domain
 */
function filter_wpseo_sitemap_index_links( $links )
{
    $home = parse_url(get_option('home'));
    $site = parse_url(get_option('siteurl'));
    foreach($links as $i => $link) {
        $links[$i]['loc'] = str_replace($home, $site, $link['loc']);
    }
    return $links;
};
add_filter('wpseo_sitemap_index_links', 'filter_wpseo_sitemap_index_links', 10, 1);

// Make Post Apartments use radio box / one option only
add_action(
    'admin_footer', function () {
        echo '<script type="text/javascript">jQuery("#bedrooms-pop input, #bedroomschecklist input, .bedroomschecklist input").each(function(){this.type="radio"});</script>';
    }
);


// begone, previews
add_action(
    'admin_head', function () {
        echo '<style>#minor-publishing-actions #preview-action a.preview {display:none !important;}</style>';
    }
);
