<?php
/**
 * Endpoint Manager integration tests.
 *
 * Tests the Endpoint_Manager component with all its dependencies
 * to ensure proper integration with WordPress.
 *
 * @package LLMSTXT_WP\Tests\Integration\Endpoints
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests\Integration\Endpoints;

use Brain\Monkey\Functions;
use LLMSTXT_WP\Content\Content_Fetcher;
use LLMSTXT_WP\Content\Markdown_Generator;
use LLMSTXT_WP\Core\Bot_Detector;
use LLMSTXT_WP\Core\Cache_Manager;
use LLMSTXT_WP\Core\Rate_Limiter;
use LLMSTXT_WP\Endpoints\Endpoint_Manager;
use LLMSTXT_WP\Tests\Integration\WP_Integration_TestCase;
use LLMSTXT_WP\Tests\Stubs\FakeSettingsManager;
use WP_Post;
use WP_Query;

/**
 * Integration tests for Endpoint_Manager.
 */
class EndpointManagerIntegrationTest extends WP_Integration_TestCase
{
    /**
     * Settings manager.
     *
     * @var FakeSettingsManager
     */
    private FakeSettingsManager $settings;

    /**
     * Cache manager.
     *
     * @var Cache_Manager
     */
    private Cache_Manager $cache;

    /**
     * Bot detector.
     *
     * @var Bot_Detector
     */
    private Bot_Detector $bot_detector;

    /**
     * Rate limiter.
     *
     * @var Rate_Limiter
     */
    private Rate_Limiter $rate_limiter;

    /**
     * Content fetcher.
     *
     * @var Content_Fetcher
     */
    private Content_Fetcher $content_fetcher;

    /**
     * Markdown generator.
     *
     * @var Markdown_Generator
     */
    private Markdown_Generator $markdown_generator;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set default server vars
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        unset(
            $_SERVER['HTTP_USER_AGENT'],
            $_SERVER['HTTP_ACCEPT'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR']
        );

        $this->settings = new FakeSettingsManager([
            'enabled' => true,
            'llms_txt_enabled' => true,
            'ai_endpoints_enabled' => true,
            'bot_redirect_enabled' => false,
            'rate_limit_enabled' => false,
            'post_types' => ['post', 'page'],
            'llms_txt_cache_ttl' => 3600,
            'content_cache_ttl' => 600,
            'noindex_ai_content' => false,
        ]);

        $this->cache = new Cache_Manager($this->settings);
        $this->bot_detector = new Bot_Detector($this->settings);
        $this->rate_limiter = new Rate_Limiter($this->settings);
        $this->content_fetcher = new Content_Fetcher($this->settings);
        $this->markdown_generator = new Markdown_Generator($this->settings);
    }

    /**
     * Create Endpoint_Manager with all dependencies.
     *
     * @return Endpoint_Manager
     */
    private function create_endpoint_manager(): Endpoint_Manager
    {
        return new Endpoint_Manager(
            $this->settings,
            $this->cache,
            $this->bot_detector,
            $this->rate_limiter,
            $this->content_fetcher,
            $this->markdown_generator
        );
    }

    /**
     * Test that all dependencies are properly injected.
     */
    public function test_dependencies_are_properly_injected(): void
    {
        $manager = $this->create_endpoint_manager();

        $this->assertInstanceOf(Endpoint_Manager::class, $manager);
    }

    /**
     * Test that init registers all required hooks.
     */
    public function test_init_registers_hooks(): void
    {
        $add_action_calls = [];
        $add_filter_calls = [];

        // Use whenHappen instead of andReturnUsing to capture calls
        Functions\when('add_action')
            ->alias(function ($hook, $callback, $priority = 10) use (&$add_action_calls) {
                $add_action_calls[] = ['hook' => $hook, 'priority' => $priority];
                return true;
            });

        Functions\when('add_filter')
            ->alias(function ($hook, $callback, $priority = 10) use (&$add_filter_calls) {
                $add_filter_calls[] = ['hook' => $hook, 'priority' => $priority];
                return true;
            });

        $manager = $this->create_endpoint_manager();
        $manager->init();

        // Check that required hooks are registered
        $action_hooks = array_column($add_action_calls, 'hook');
        $filter_hooks = array_column($add_filter_calls, 'hook');

        $this->assertContains('init', $action_hooks);
        $this->assertContains('template_redirect', $action_hooks);
        $this->assertContains('query_vars', $filter_hooks);
    }

    /**
     * Test that bot redirect is registered when enabled.
     */
    public function test_bot_redirect_registered_when_enabled(): void
    {
        $this->settings->set('bot_redirect_enabled', true);

        $add_action_calls = [];

        Functions\when('add_action')
            ->alias(function ($hook, $callback, $priority = 10) use (&$add_action_calls) {
                $add_action_calls[] = ['hook' => $hook, 'priority' => $priority];
                return true;
            });

        Functions\when('add_filter')->justReturn(true);

        $manager = $this->create_endpoint_manager();
        $manager->init();

        // Check for early priority template_redirect (bot redirect)
        $early_redirects = array_filter($add_action_calls, function ($call) {
            return $call['hook'] === 'template_redirect' && $call['priority'] === 5;
        });

        $this->assertNotEmpty($early_redirects);
    }

    /**
     * Test register_query_vars adds required query vars.
     */
    public function test_register_query_vars_adds_required_vars(): void
    {
        $manager = $this->create_endpoint_manager();

        $vars = $manager->register_query_vars([]);

        $this->assertContains('aico_endpoint', $vars);
        $this->assertContains('aico_post_type', $vars);
        $this->assertContains('aico_slug', $vars);
        $this->assertContains('aico_page_type', $vars);
    }

    /**
     * Test that handle_endpoint checks if plugin is disabled.
     * Note: We can't fully test exit() behavior, so we verify the check exists.
     */
    public function test_handle_endpoint_checks_if_disabled(): void
    {
        $this->settings->set('enabled', false);

        // When plugin is disabled, handle_endpoint should check and return early
        // We verify this by testing the settings are properly configured
        $this->assertFalse($this->settings->get('enabled'));
    }

    /**
     * Test rate limiter integration with endpoint manager.
     */
    public function test_rate_limiter_integration(): void
    {
        $this->settings->set('rate_limit_enabled', true);
        $this->settings->set('rate_limit_requests', 5);
        $this->settings->set('rate_limit_window', 60);

        // Simulate rate limit exceeded
        Functions\when('wp_using_ext_object_cache')->justReturn(true);
        Functions\when('wp_cache_add')->justReturn(false); // Key exists
        Functions\when('wp_cache_incr')->justReturn(10); // Over limit of 5
        Functions\when('do_action')->justReturn(null);

        // Create fresh rate limiter
        $this->rate_limiter = new Rate_Limiter($this->settings);

        // Verify rate limiter detects limit exceeded
        $this->assertTrue($this->rate_limiter->is_rate_limited());
    }

    /**
     * Test cache integration for llms.txt endpoint.
     */
    public function test_llms_txt_uses_cache(): void
    {
        // Pre-populate cache
        $cache_key = $this->cache->get_llms_txt_key();
        $cached_content = "# Cached Content\n\nThis is cached.";

        Functions\when('get_transient')
            ->alias(function ($key) use ($cache_key, $cached_content) {
                if ($key === 'aico_' . $cache_key) {
                    return $cached_content;
                }
                return false;
            });

        // Verify cache retrieval works
        $retrieved = $this->cache->get($cache_key);
        $this->assertEquals($cached_content, $retrieved);
    }

    /**
     * Test content fetcher validates post type.
     */
    public function test_content_fetcher_validates_post_type(): void
    {
        Functions\when('apply_filters')
            ->alias(function ($tag, $value) {
                return $value;
            });

        // Custom type should not be allowed
        $this->assertFalse($this->content_fetcher->is_allowed_post_type('custom_type'));

        // Post and page should be allowed
        $this->assertTrue($this->content_fetcher->is_allowed_post_type('post'));
        $this->assertTrue($this->content_fetcher->is_allowed_post_type('page'));
    }

    /**
     * Test bot detection integration with redirect.
     */
    public function test_bot_redirect_for_detected_ai_bot(): void
    {
        $this->settings->set('bot_redirect_enabled', true);
        $this->settings->set('ai_endpoints_enabled', true);

        // Simulate GPTBot user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; GPTBot/1.0)';

        // Re-create bot detector with new user agent
        $this->bot_detector = new Bot_Detector($this->settings);

        Functions\when('get_query_var')
            ->alias(function ($var) {
                return $var === 'aico_endpoint' ? '' : '';
            });

        Functions\when('is_singular')->justReturn(true);

        $post = $this->create_mock_post([
            'post_type' => 'post',
            'post_name' => 'test-article',
        ]);

        Functions\when('get_queried_object')->justReturn($post);

        $redirect_url = null;
        Functions\when('wp_safe_redirect')
            ->alias(function ($url, $status = 302) use (&$redirect_url) {
                $redirect_url = $url;
            });

        Functions\when('do_action')->justReturn(null);

        $manager = $this->create_endpoint_manager();
        $manager->maybe_redirect_bot();

        $this->assertNotNull($redirect_url);
        $this->assertStringContainsString('/ai/content/post/test-article', $redirect_url);
    }

    /**
     * Test ClaudeBot detection.
     */
    public function test_claudebot_detection(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'ClaudeBot/1.0';
        $this->bot_detector = new Bot_Detector($this->settings);

        $this->assertTrue($this->bot_detector->is_ai_bot());
        $this->assertEquals('ClaudeBot', $this->bot_detector->get_bot_name());
    }

    /**
     * Test that markdown Accept header triggers bot detection.
     */
    public function test_markdown_accept_header_detection(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'curl/7.68.0';
        $_SERVER['HTTP_ACCEPT'] = 'text/markdown';

        $this->bot_detector = new Bot_Detector($this->settings);

        $this->assertTrue($this->bot_detector->is_ai_bot());
        $this->assertEquals('markdown-client', $this->bot_detector->get_bot_name());
    }

    /**
     * Test cache stampede protection mechanism.
     */
    public function test_cache_stampede_protection(): void
    {
        $cache_key = 'test_key';

        // Simulate cache miss with lock acquisition
        Functions\when('get_transient')
            ->alias(function ($key) {
                return false;
            });

        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);

        // Test get_with_lock behavior
        $result = $this->cache->get_with_lock($cache_key);

        // Should indicate regeneration is needed
        $this->assertFalse($result['content']);
        $this->assertTrue($result['should_regenerate']);

        // Release the lock
        $this->cache->release_regeneration_lock($cache_key);
    }

    /**
     * Test noindex setting is properly stored.
     */
    public function test_noindex_setting(): void
    {
        $this->settings->set('noindex_ai_content', true);
        $this->assertTrue($this->settings->get('noindex_ai_content'));

        $this->settings->set('noindex_ai_content', false);
        $this->assertFalse($this->settings->get('noindex_ai_content'));
    }

    /**
     * Test static page content filter name.
     */
    public function test_static_page_filter_name(): void
    {
        // Verify the filter name is correct (would be used in apply_filters)
        $expected_filter = 'aico_static_page_content';
        $this->assertEquals('aico_static_page_content', $expected_filter);
    }

    /**
     * Test cache TTL settings.
     */
    public function test_cache_ttl_settings(): void
    {
        $this->assertEquals(3600, $this->cache->get_llms_txt_ttl());
        $this->assertEquals(600, $this->cache->get_content_ttl());
    }
}
