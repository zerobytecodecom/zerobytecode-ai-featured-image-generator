<?php

/*
* Plugin Name:       ZeroByteCode AI Featured Image Generator
* Plugin URI:        https://zerobytecode.com/create-a-wordpress-plugin-to-generate-featured-images-using-ai/
* Description:       One-click generate and set WordPress post featured image using OpenAI\'s Dalle 3 AI.
* Version:           1.0
* Author:            ZeroByteCode
* Author URI:        https://zerobytecode.com/
* Text Domain:       zerobytecode
*/

if (!defined('WPINC')) {
    die;
}

// Registering the Options Page
function zerobytecode_register_options_page()
{
    add_options_page(
        __('ZeroByteCode Featured Image Generator Settings', 'zerobytecode'),
        __('ZeroByteCode AI', 'zerobytecode'),
        'manage_options',
        'zerobytecode-ai-settings',
        'zerobytecode_options_page_html'
    );
}
add_action('admin_menu', 'zerobytecode_register_options_page');

// Set default options on plugin activation
function zerobytecode_activate_plugin()
{
    $default_template = 'Based on the user\'s input, you must generate a single sentence, detailed prompt to generate an image using an AI image generation. The image is the thumbnail for the blog post, and the content the user passes in is portions of that blog post. Distill a single concept or topic based on the user\'s input, then create the prompt for image generation.';

    if (get_option('zerobytecode_content_template') === false) {
        update_option('zerobytecode_content_template', $default_template);
    }
}
register_activation_hook(__FILE__, 'zerobytecode_activate_plugin');

// Displaying the Options Page
function zerobytecode_options_page_html()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['settings-updated'])) {
        add_settings_error('zerobytecode_messages', 'zerobytecode_message', __('Settings Saved', 'zerobytecode'), 'updated');
    }

    settings_errors('zerobytecode_messages');
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('zerobytecode_settings');
            do_settings_sections('zerobytecode-ai-settings');
            submit_button(__('Save Settings', 'zerobytecode'));
            ?>
        </form>
    </div>
<?php
}

// Registering the Settings
function zerobytecode_register_settings()
{
    register_setting('zerobytecode_settings', 'zerobytecode_openai_api_key', 'sanitize_text_field');
    register_setting('zerobytecode_settings', 'zerobytecode_content_template', 'sanitize_textarea_field');

    add_settings_section(
        'zerobytecode_settings_section',
        __('OpenAI API Settings', 'zerobytecode'),
        'zerobytecode_settings_section_callback',
        'zerobytecode-ai-settings'
    );

    add_settings_field(
        'zerobytecode_openai_api_key',
        __('OpenAI API Key', 'zerobytecode'),
        'zerobytecode_openai_api_key_render',
        'zerobytecode-ai-settings',
        'zerobytecode_settings_section'
    );

    add_settings_field(
        'zerobytecode_content_template',
        __('Content Template', 'zerobytecode'),
        'zerobytecode_content_template_render',
        'zerobytecode-ai-settings',
        'zerobytecode_settings_section'
    );
}
add_action('admin_init', 'zerobytecode_register_settings');

function zerobytecode_settings_section_callback()
{
    echo '<p>' . __('Enter your OpenAI API settings below.', 'zerobytecode') . '</p>';
}

function zerobytecode_openai_api_key_render()
{
    $openai_api_key = get_option('zerobytecode_openai_api_key');
?>
    <input type="text" name="zerobytecode_openai_api_key" value="<?php echo esc_attr($openai_api_key); ?>" size="50">
<?php
}

// Enqueue scripts for the admin area
function zerobytecode_enqueue_scripts($hook)
{
    // Check if we are on post.php or post-new.php on the admin side
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    wp_enqueue_script(
        'zerobytecode-admin-js',
        plugin_dir_url(__FILE__) . 'js/admin.js',
        ['jquery'],
        '1.0',
        true
    );

    // Localize the script with nonce and correct endpoint
    wp_localize_script('zerobytecode-admin-js', 'zerobytecode', [
        'nonce' => wp_create_nonce('wp_rest'), // Ensure the correct nonce action name
        'rest_url' => '/zerobytecode/v1/generate-image/'
    ]);
}
add_action('admin_enqueue_scripts', 'zerobytecode_enqueue_scripts');

// Register the meta box for generating images
function zerobytecode_register_meta_box()
{
    add_meta_box(
        'zerobytecode_featured_image_generator',
        __('Generate Featured Image', 'zerobytecode'),
        'zerobytecode_display_generator_button',
        null,
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'zerobytecode_register_meta_box');

// Display the generator button in the meta box
function zerobytecode_display_generator_button($post)
{
?>
    <button type="button" id="zerobytecode_generate_btn" data-postid="<?php echo esc_attr($post->ID); ?>" class="button button-primary button-large">
        <?php esc_html_e('Generate Image', 'zerobytecode'); ?>
    </button>
<?php
}

// Register REST API route
function zerobytecode_register_rest_route()
{
    register_rest_route('zerobytecode/v1', '/generate-image/(?P<id>\d+)', [
        'methods' => 'POST',
        'callback' => 'zerobytecode_handle_generate_image',
        'permission_callback' => 'zerobytecode_check_permissions',
        'args' => [
            'id' => [
                'required' => true,
                'validate_callback' => 'is_numeric',
            ],
        ],
    ]);
}
add_action('rest_api_init', 'zerobytecode_register_rest_route');

// Check user permissions for generating image
function zerobytecode_check_permissions(WP_REST_Request $request)
{
    return current_user_can('edit_post', $request['id']);
}

function zerobytecode_content_template_render()
{
    $content_template = get_option('zerobytecode_content_template', 'Based on the user\'s input, you must generate a single sentence, detailed prompt to generate an image using an AI image generation. The image is the thumbnail for the blog post, and the content the user passes in is portions of that blog post. Distill a single concept or topic based on the user\'s input, then create the prompt for image generation.');
?>
    <textarea name="zerobytecode_content_template" rows="10" cols="50"><?php echo esc_textarea($content_template); ?></textarea>
<?php
}

// Handle the REST API request to generate an image
function zerobytecode_handle_generate_image(WP_REST_Request $request)
{

    // Verify the nonce
    $nonce = $request->get_param('_wpnonce');
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('rest_cookie_invalid_nonce', __('Cookie check failed', 'zerobytecode'), ['status' => 403]);
    }

    $post_id = $request['id'];
    $post_data = get_post($post_id);
    $excerpt = !empty($post_data->post_excerpt) ? $post_data->post_excerpt : wp_trim_words($post_data->post_content, 100);
    $api_key = get_option('zerobytecode_openai_api_key');
    $content_template = get_option('zerobytecode_content_template', 'Based on the user\'s input, you must generate a single sentence, detailed prompt to generate an image using an AI image generation. The image is the thumbnail for the blog post, and the content the user passes in is portions of that blog post. Distill a single concept or topic based on the user\'s input, then create the prompt for image generation.');

    if (empty($api_key)) {
        return new WP_Error('missing_api_key', __('Missing OpenAI API key.', 'zerobytecode'));
    }

    // Generate image prompt using OpenAI chat completions
    $prompt_response = wp_remote_post(
        'https://api.openai.com/v1/chat/completions',
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => sanitize_textarea_field($content_template),
                    ],
                    [
                        'role' => 'user',
                        'content' => sanitize_text_field($excerpt),
                    ],
                ],
            ]),
            'timeout' => 60,
        ]
    );

    if (is_wp_error($prompt_response) || wp_remote_retrieve_response_code($prompt_response) !== 200) {
        return new WP_Error('api_error', __('Error communicating with OpenAI API.', 'zerobytecode'));
    }

    $prompt_data = json_decode(wp_remote_retrieve_body($prompt_response), true);
    $prompt = $prompt_data['choices'][0]['message']['content'] ?? '';

    if (empty($prompt)) {
        return new WP_Error('prompt_error', __('Unable to generate image prompt.', 'zerobytecode'));
    }

    // Generate image using Dalle 3 API
    $image_response = wp_remote_post(
        'https://api.openai.com/v1/images/generations',
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'model' => 'dall-e-3',
                'prompt' => sanitize_text_field($prompt),
                'n' => 1,
                'size' => '1792x1024',
            ]),
            'timeout' => 60,
        ]
    );

    if (is_wp_error($image_response) || wp_remote_retrieve_response_code($image_response) !== 200) {
        return new WP_Error('api_error', __('Error generating image with Dalle 3 API.', 'zerobytecode'));
    }

    $image_data = json_decode(wp_remote_retrieve_body($image_response), true);
    $image_url = $image_data['data'][0]['url'] ?? '';

    if (empty($image_url)) {
        return new WP_Error('image_error', __('Unable to get image URL.', 'zerobytecode'));
    }

    // Upload image to media library and set as featured image
    $image_id = zerobytecode_upload_image_to_media_library($image_url, $post_id);

    if (is_wp_error($image_id)) {
        return $image_id;
    }

    set_post_thumbnail($post_id, $image_id);

    return rest_ensure_response(['success' => true]);
}

// Upload image to media library
function zerobytecode_upload_image_to_media_library($image_url, $post_id)
{
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    add_filter('upload_mimes', 'zerobytecode_custom_upload_mimes');

    $tmp = zerobytecode_custom_download_image($image_url);

    if (is_wp_error($tmp)) {
        return $tmp;
    }

    // Extract the file extension directly from the URL
    $file_ext = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
    $file_name = sanitize_file_name($post_id . '-' . time() . '.' . $file_ext);

    $file_array = [
        'name' => $file_name,
        'tmp_name' => $tmp,
    ];

    $id = media_handle_sideload($file_array, $post_id, __('Generated featured image', 'zerobytecode'));

    if (is_wp_error($id)) {
        @unlink($file_array['tmp_name']);
        return $id;
    }

    return $id;
}

// Custom MIME types for uploads
function zerobytecode_custom_upload_mimes($mimes)
{
    $mimes['png'] = 'image/png';
    return $mimes;
}

/**
 * Custom download function for images.
 *
 * @param string $image_url URL of the image to download.
 * @return string|WP_Error Path to the temporary file or WP_Error on failure.
 */
function zerobytecode_custom_download_image($image_url)
{
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        return new WP_Error('invalid_url', __('Invalid image URL.', 'zerobytecode'));
    }

    $response = wp_remote_get($image_url, [
        'timeout' => 60,
        'sslverify' => true,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('download_error', __('Error downloading image.', 'zerobytecode'));
    }

    $body = wp_remote_retrieve_body($response);

    if (empty($body)) {
        return new WP_Error('empty_body', __('Downloaded image data is empty.', 'zerobytecode'));
    }

    $file_ext = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
    $file_ext = sanitize_file_name($file_ext);
    $tmp_fname = wp_tempnam($image_url);

    if (!$tmp_fname) {
        return new WP_Error('temp_file_error', __('Unable to create a temporary file.', 'zerobytecode'));
    }

    file_put_contents($tmp_fname, $body);

    return $tmp_fname;
}
