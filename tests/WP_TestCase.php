<?php
/**
 * Base test case for WordPress plugin unit tests.
 *
 * Provides common WordPress function stubs via Brain Monkey.
 *
 * @package LLMSTXT_WP\Tests
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base test case that sets up common WordPress function stubs.
 *
 * All unit tests should extend this class to get automatic WordPress function mocking.
 */
abstract class WP_TestCase extends TestCase
{
    /**
     * Set up Brain Monkey and common WordPress function stubs.
     */
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();

        // Stub common WordPress sanitization functions.
        \Brain\Monkey\Functions\stubs([
            // Sanitization functions - return input as-is for testing.
            'sanitize_text_field' => function ($str) {
                return is_string($str) ? trim(strip_tags($str)) : '';
            },
            'sanitize_textarea_field' => function ($str) {
                return is_string($str) ? trim(strip_tags($str)) : '';
            },
            'sanitize_email' => function ($email) {
                return filter_var($email, FILTER_SANITIZE_EMAIL);
            },
            'sanitize_key' => function ($key) {
                return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
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
            'wp_rand' => function ($min = 0, $max = null) {
                return $max === null ? random_int($min, PHP_INT_MAX) : random_int($min, $max);
            },
            '__' => function ($text, $domain = 'default') {
                return $text;
            },
            'esc_html__' => function ($text, $domain = 'default') {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
            'esc_attr__' => function ($text, $domain = 'default') {
                return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            },
        ]);
    }

    /**
     * Tear down Brain Monkey.
     */
    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }
}
