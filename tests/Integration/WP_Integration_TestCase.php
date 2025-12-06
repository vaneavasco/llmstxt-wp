<?php
/**
 * Base test case for WordPress plugin integration tests.
 *
 * Provides comprehensive WordPress function stubs for testing
 * component interactions.
 *
 * @package LLMSTXT_WP\Tests\Integration
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests\Integration;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Base integration test case.
 *
 * Sets up comprehensive WordPress function mocks for integration testing.
 */
abstract class WP_Integration_TestCase extends TestCase
{
    /**
     * Mock posts storage for simulating WordPress database.
     *
     * @var array<int, WP_Post>
     */
    protected array $mock_posts = [];

    /**
     * Mock options storage.
     *
     * @var array<string, mixed>
     */
    protected array $mock_options = [];

    /**
     * Mock transients storage.
     *
     * @var array<string, mixed>
     */
    protected array $mock_transients = [];

    /**
     * Set up Brain Monkey and comprehensive WordPress function stubs.
     */
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        $this->mock_posts = [];
        $this->mock_options = [
            'blogname' => 'Test Site',
            'blogdescription' => 'Test Description',
            'admin_email' => 'admin@test.com',
            'page_on_front' => 0,
            'siteurl' => 'https://example.com',
            'home' => 'https://example.com',
        ];
        $this->mock_transients = [];

        $this->stub_core_functions();
        $this->stub_post_functions();
        $this->stub_query_functions();
        $this->stub_cache_functions();
        $this->stub_url_functions();
        $this->stub_user_functions();
        $this->stub_taxonomy_functions();
        $this->stub_hook_functions();
    }

    /**
     * Tear down Brain Monkey.
     */
    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Stub core WordPress functions.
     */
    protected function stub_core_functions(): void
    {
        Functions\stubs([
            'sanitize_text_field' => function ($str) {
                return is_string($str) ? trim(strip_tags($str)) : '';
            },
            'sanitize_textarea_field' => function ($str) {
                return is_string($str) ? trim(strip_tags($str)) : '';
            },
            'sanitize_key' => function ($key) {
                return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
            },
            'sanitize_title' => function ($title) {
                return strtolower(preg_replace('/[^a-z0-9\-]/', '-', strtolower($title)));
            },
            'wp_unslash' => function ($value) {
                return is_string($value) ? stripslashes($value) : $value;
            },
            'absint' => function ($value) {
                return abs((int) $value);
            },
            'wp_parse_args' => function ($args, $defaults = []) {
                if (is_object($args)) {
                    $args = get_object_vars($args);
                }
                return array_merge($defaults, $args);
            },
            '__' => function ($text, $domain = 'default') {
                return $text;
            },
            'esc_html' => function ($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'esc_attr' => function ($text) {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'esc_url' => function ($url) {
                return filter_var($url, FILTER_SANITIZE_URL);
            },
            'wp_strip_all_tags' => function ($string) {
                return strip_tags($string);
            },
            'wp_list_pluck' => function ($list, $field) {
                return array_column($list, $field);
            },
        ]);
    }

    /**
     * Stub post-related functions.
     */
    protected function stub_post_functions(): void
    {
        $self = $this;

        Functions\stubs([
            'get_post' => function ($post_id) use ($self) {
                return $self->mock_posts[$post_id] ?? null;
            },
            'get_the_title' => function ($post) use ($self) {
                if (is_int($post)) {
                    $post = $self->mock_posts[$post] ?? null;
                }
                return $post instanceof WP_Post ? $post->post_title : '';
            },
            'get_the_date' => function ($format = '', $post = null) {
                return date($format ?: 'F j, Y');
            },
            'get_the_modified_date' => function ($format = '', $post = null) {
                return date($format ?: 'F j, Y');
            },
            'get_permalink' => function ($post) use ($self) {
                if (is_int($post)) {
                    $post = $self->mock_posts[$post] ?? null;
                }
                if ($post instanceof WP_Post) {
                    return "https://example.com/{$post->post_type}/{$post->post_name}/";
                }
                return 'https://example.com/';
            },
            'get_page_by_path' => function ($path) use ($self) {
                foreach ($self->mock_posts as $post) {
                    if ($post->post_type === 'page' && $post->post_name === $path) {
                        return $post;
                    }
                }
                return null;
            },
            'has_post_thumbnail' => function ($post) {
                return false;
            },
            'get_the_post_thumbnail_url' => function ($post, $size = 'full') {
                return null;
            },
            'get_post_thumbnail_id' => function ($post) {
                return 0;
            },
            'get_post_meta' => function ($post_id, $key = '', $single = false) {
                return $single ? '' : [];
            },
            'wp_is_post_revision' => function ($post_id) {
                return false;
            },
            'wp_count_posts' => function ($post_type) {
                return (object) ['publish' => 10, 'draft' => 2];
            },
        ]);
    }

    /**
     * Stub query-related functions.
     */
    protected function stub_query_functions(): void
    {
        Functions\stubs([
            'get_query_var' => function ($var) {
                return $_GET[$var] ?? '';
            },
            'is_singular' => function () {
                return false;
            },
            'is_front_page' => function () {
                return false;
            },
            'is_home' => function () {
                return false;
            },
            'get_queried_object' => function () {
                return null;
            },
        ]);
    }

    /**
     * Stub cache and transient functions.
     */
    protected function stub_cache_functions(): void
    {
        $self = $this;

        Functions\stubs([
            'get_transient' => function ($key) use ($self) {
                return $self->mock_transients[$key] ?? false;
            },
            'set_transient' => function ($key, $value, $expiration = 0) use ($self) {
                $self->mock_transients[$key] = $value;
                return true;
            },
            'delete_transient' => function ($key) use ($self) {
                unset($self->mock_transients[$key]);
                return true;
            },
            'wp_using_ext_object_cache' => function () {
                return false;
            },
            'wp_cache_add' => function ($key, $data, $group = '', $expire = 0) {
                return true;
            },
            'wp_cache_get' => function ($key, $group = '') {
                return false;
            },
            'wp_cache_set' => function ($key, $data, $group = '', $expire = 0) {
                return true;
            },
            'wp_cache_delete' => function ($key, $group = '') {
                return true;
            },
        ]);
    }

    /**
     * Stub URL-related functions.
     */
    protected function stub_url_functions(): void
    {
        $self = $this;

        Functions\stubs([
            'home_url' => function ($path = '') use ($self) {
                $home = $self->mock_options['home'] ?? 'https://example.com';
                return rtrim($home, '/') . '/' . ltrim($path, '/');
            },
            'site_url' => function ($path = '') use ($self) {
                $site = $self->mock_options['siteurl'] ?? 'https://example.com';
                return rtrim($site, '/') . '/' . ltrim($path, '/');
            },
            'admin_url' => function ($path = '') {
                return 'https://example.com/wp-admin/' . ltrim($path, '/');
            },
            'get_bloginfo' => function ($show) use ($self) {
                return match ($show) {
                    'name' => $self->mock_options['blogname'] ?? 'Test Site',
                    'description' => $self->mock_options['blogdescription'] ?? '',
                    'url', 'wpurl' => $self->mock_options['siteurl'] ?? 'https://example.com',
                    default => '',
                };
            },
            'get_option' => function ($option, $default = false) use ($self) {
                return $self->mock_options[$option] ?? $default;
            },
            'get_author_posts_url' => function ($author_id) {
                return "https://example.com/author/{$author_id}/";
            },
        ]);
    }

    /**
     * Stub user-related functions.
     */
    protected function stub_user_functions(): void
    {
        Functions\stubs([
            'get_the_author_meta' => function ($field, $user_id = 0) {
                return match ($field) {
                    'display_name' => 'Test Author',
                    'user_email' => 'author@test.com',
                    default => '',
                };
            },
            'is_user_logged_in' => function () {
                return false;
            },
            'current_user_can' => function ($capability) {
                return false;
            },
        ]);
    }

    /**
     * Stub taxonomy-related functions.
     */
    protected function stub_taxonomy_functions(): void
    {
        Functions\stubs([
            'get_object_taxonomies' => function ($object, $output = 'names') {
                return ['category', 'post_tag'];
            },
            'get_the_terms' => function ($post, $taxonomy) {
                return false;
            },
            'is_wp_error' => function ($thing) {
                return false;
            },
            'get_post_type_object' => function ($post_type) {
                return (object) [
                    'labels' => (object) [
                        'name' => ucfirst($post_type) . 's',
                        'singular_name' => ucfirst($post_type),
                    ],
                ];
            },
            'update_object_term_cache' => function ($post_ids, $taxonomies) {
                return true;
            },
        ]);
    }

    /**
     * Stub hook functions.
     */
    protected function stub_hook_functions(): void
    {
        Functions\stubs([
            'add_action' => function () {
                return true;
            },
            'add_filter' => function () {
                return true;
            },
            'do_action' => function () {
                return null;
            },
            'apply_filters' => function ($tag, $value, ...$args) {
                return $value;
            },
            'remove_action' => function () {
                return true;
            },
            'remove_filter' => function () {
                return true;
            },
        ]);
    }

    /**
     * Create a mock WP_Post object.
     *
     * @param array<string, mixed> $args Post arguments.
     * @return WP_Post Mock post object.
     */
    protected function create_mock_post(array $args = []): WP_Post
    {
        static $post_id = 0;
        $post_id++;

        $defaults = [
            'ID' => $post_id,
            'post_author' => 1,
            'post_date' => '2024-01-01 12:00:00',
            'post_date_gmt' => '2024-01-01 12:00:00',
            'post_content' => 'Test content',
            'post_title' => 'Test Post',
            'post_excerpt' => 'Test excerpt',
            'post_status' => 'publish',
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => 'test-post-' . $post_id,
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => '2024-01-01 12:00:00',
            'post_modified_gmt' => '2024-01-01 12:00:00',
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => 'https://example.com/?p=' . $post_id,
            'menu_order' => 0,
            'post_type' => 'post',
            'post_mime_type' => '',
            'comment_count' => 0,
            'filter' => 'raw',
        ];

        $data = array_merge($defaults, $args);

        $post = new WP_Post((object) $data);

        // Store in mock database
        $this->mock_posts[$post->ID] = $post;

        return $post;
    }

    /**
     * Set a mock option.
     *
     * @param string $key   Option key.
     * @param mixed  $value Option value.
     */
    protected function set_option(string $key, $value): void
    {
        $this->mock_options[$key] = $value;
    }

    /**
     * Set a mock transient.
     *
     * @param string $key   Transient key.
     * @param mixed  $value Transient value.
     */
    protected function set_transient(string $key, $value): void
    {
        $this->mock_transients[$key] = $value;
    }
}
