<?php
/**
 * PHPUnit bootstrap file for unit tests.
 *
 * Loads Composer autoloader and initializes Brain Monkey for WordPress function mocking.
 *
 * @package LLMSTXT_WP\Tests
 */

declare(strict_types=1);

// Load WordPress mock classes FIRST (before autoloader loads any plugin code).
// These must be in global namespace to replace WordPress classes.
require_once __DIR__ . '/Stubs/MockWPPost.php';
require_once __DIR__ . '/Stubs/MockWPQuery.php';

// Composer autoloader.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants that may be needed by the plugin.
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (!defined('AICO_PLUGIN_FILE')) {
    define('AICO_PLUGIN_FILE', dirname(__DIR__) . '/llmstxt-wp.php');
}
if (!defined('AICO_PLUGIN_DIR')) {
    define('AICO_PLUGIN_DIR', dirname(__DIR__) . '/');
}
if (!defined('AICO_VERSION')) {
    define('AICO_VERSION', '1.0.0');
}

// Load the base test case classes.
require_once __DIR__ . '/WP_TestCase.php';
require_once __DIR__ . '/Integration/WP_Integration_TestCase.php';
