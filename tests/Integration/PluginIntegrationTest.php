<?php
/**
 * Plugin initialization integration tests.
 *
 * Tests that all plugin components wire up correctly.
 *
 * @package LLMSTXT_WP\Tests\Integration
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests\Integration;

use Brain\Monkey\Functions;
use LLMSTXT_WP\Content\Content_Fetcher;
use LLMSTXT_WP\Content\Markdown_Generator;
use LLMSTXT_WP\Core\Bot_Detector;
use LLMSTXT_WP\Core\Cache_Manager;
use LLMSTXT_WP\Core\Rate_Limiter;
use LLMSTXT_WP\Core\Rewrite_Rules;
use LLMSTXT_WP\Core\Settings_Manager;
use LLMSTXT_WP\Endpoints\Endpoint_Manager;
use LLMSTXT_WP\Tests\Stubs\FakeSettingsManager;

/**
 * Integration tests for plugin initialization and component wiring.
 */
class PluginIntegrationTest extends WP_Integration_TestCase
{
    /**
     * Test all core components can be instantiated together.
     */
    public function test_all_components_instantiate_correctly(): void
    {
        $settings = new FakeSettingsManager([
            'enabled' => true,
            'llms_txt_enabled' => true,
            'ai_endpoints_enabled' => true,
            'post_types' => ['post', 'page'],
            'rate_limit_enabled' => false,
        ]);

        // Create all components with proper dependency injection
        $cache = new Cache_Manager($settings);
        $bot_detector = new Bot_Detector($settings);
        $rate_limiter = new Rate_Limiter($settings);
        $content_fetcher = new Content_Fetcher($settings);
        $markdown_generator = new Markdown_Generator($settings);

        $endpoint_manager = new Endpoint_Manager(
            $settings,
            $cache,
            $bot_detector,
            $rate_limiter,
            $content_fetcher,
            $markdown_generator
        );

        $this->assertInstanceOf(Cache_Manager::class, $cache);
        $this->assertInstanceOf(Bot_Detector::class, $bot_detector);
        $this->assertInstanceOf(Rate_Limiter::class, $rate_limiter);
        $this->assertInstanceOf(Content_Fetcher::class, $content_fetcher);
        $this->assertInstanceOf(Markdown_Generator::class, $markdown_generator);
        $this->assertInstanceOf(Endpoint_Manager::class, $endpoint_manager);
    }

    /**
     * Test Rewrite_Rules registers correct patterns.
     */
    public function test_rewrite_rules_register_correctly(): void
    {
        $rules_added = [];

        Functions\expect('add_rewrite_rule')
            ->andReturnUsing(function ($regex, $redirect, $after) use (&$rules_added) {
                $rules_added[] = [
                    'regex' => $regex,
                    'redirect' => $redirect,
                    'after' => $after,
                ];
            });

        Rewrite_Rules::register();

        // Verify llms.txt rule
        $llms_txt_rule = array_filter($rules_added, function ($rule) {
            return strpos($rule['regex'], 'llms\.txt') !== false;
        });
        $this->assertNotEmpty($llms_txt_rule);

        // Verify ai/content rule
        $ai_content_rule = array_filter($rules_added, function ($rule) {
            return strpos($rule['regex'], 'ai/content') !== false;
        });
        $this->assertNotEmpty($ai_content_rule);
    }

    /**
     * Test Rewrite_Rules query vars.
     */
    public function test_rewrite_rules_query_vars(): void
    {
        $vars = Rewrite_Rules::get_query_vars();

        $this->assertContains('aico_endpoint', $vars);
        $this->assertContains('aico_post_type', $vars);
        $this->assertContains('aico_slug', $vars);
        $this->assertContains('aico_page_type', $vars);
    }

    /**
     * Test QUERY_VAR constant is defined.
     */
    public function test_query_var_constant(): void
    {
        $this->assertEquals('aico_endpoint', Rewrite_Rules::QUERY_VAR);
    }

    /**
     * Test settings interface is implemented correctly.
     */
    public function test_settings_interface_contract(): void
    {
        $settings = new FakeSettingsManager([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        // Test get with existing key
        $this->assertEquals('value1', $settings->get('key1'));

        // Test get with default
        $this->assertEquals('default', $settings->get('nonexistent', 'default'));

        // Test set
        $settings->set('key3', 'value3');
        $this->assertEquals('value3', $settings->get('key3'));

        // Test get_all
        $all = $settings->get_all();
        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayHasKey('key2', $all);
        $this->assertArrayHasKey('key3', $all);
    }

    /**
     * Test cache manager uses correct prefix.
     */
    public function test_cache_manager_prefix(): void
    {
        $this->assertEquals('aico_', Cache_Manager::PREFIX);
    }

    /**
     * Test cache manager not found marker.
     */
    public function test_cache_manager_not_found_marker(): void
    {
        $this->assertEquals('__AICO_NOT_FOUND__', Cache_Manager::NOT_FOUND_MARKER);
    }

    /**
     * Test content fetcher default post limit.
     */
    public function test_content_fetcher_default_limit(): void
    {
        $this->assertEquals(500, Content_Fetcher::DEFAULT_POST_LIMIT);
    }

    /**
     * Test bot detector detects multiple AI bots.
     */
    public function test_bot_detector_detects_ai_bots(): void
    {
        $settings = new FakeSettingsManager([]);

        $bots = [
            'GPTBot/1.0' => 'GPTBot',
            'ClaudeBot/1.0' => 'ClaudeBot',
            'Claude-Web/1.0' => 'Claude-Web',
            'PerplexityBot/1.0' => 'PerplexityBot',
            'Amazonbot/1.0' => 'Amazonbot',
        ];

        foreach ($bots as $userAgent => $expectedName) {
            $_SERVER['HTTP_USER_AGENT'] = $userAgent;
            unset($_SERVER['HTTP_ACCEPT']);

            $detector = new Bot_Detector($settings);

            $this->assertTrue(
                $detector->is_ai_bot(),
                "Failed to detect: {$userAgent}"
            );
            $this->assertEquals(
                $expectedName,
                $detector->get_bot_name(),
                "Wrong name for: {$userAgent}"
            );
        }
    }

    /**
     * Test rate limiter respects enabled setting.
     */
    public function test_rate_limiter_respects_enabled_setting(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        // Disabled
        $settings = new FakeSettingsManager([
            'rate_limit_enabled' => false,
        ]);
        $limiter = new Rate_Limiter($settings);
        $this->assertFalse($limiter->is_rate_limited());
    }

    /**
     * Test rate limiter headers format.
     */
    public function test_rate_limiter_headers_format(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $settings = new FakeSettingsManager([
            'rate_limit_enabled' => true,
            'rate_limit_requests' => 100,
            'rate_limit_window' => 60,
        ]);

        // 50 requests made, so 50 remaining out of 100
        Functions\when('get_transient')
            ->justReturn(50);

        $limiter = new Rate_Limiter($settings);
        $headers = $limiter->get_headers();

        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);

        $this->assertEquals(100, $headers['X-RateLimit-Limit']);
        // Remaining = limit - count = 100 - 50 = 50
        $this->assertEquals(50, $headers['X-RateLimit-Remaining']);
    }

    /**
     * Test cache key generation is deterministic.
     */
    public function test_cache_key_generation_is_deterministic(): void
    {
        $settings = new FakeSettingsManager([
            'post_types' => ['post', 'page'],
        ]);
        $cache = new Cache_Manager($settings);

        $key1 = $cache->get_llms_txt_key();
        $key2 = $cache->get_llms_txt_key();

        $this->assertEquals($key1, $key2);
    }

    /**
     * Test cache key changes when post types change.
     */
    public function test_cache_key_changes_with_post_types(): void
    {
        $settings1 = new FakeSettingsManager([
            'post_types' => ['post', 'page'],
        ]);
        $cache1 = new Cache_Manager($settings1);

        $settings2 = new FakeSettingsManager([
            'post_types' => ['post', 'page', 'custom'],
        ]);
        $cache2 = new Cache_Manager($settings2);

        $this->assertNotEquals(
            $cache1->get_llms_txt_key(),
            $cache2->get_llms_txt_key()
        );
    }

    /**
     * Test content key format.
     */
    public function test_content_key_format(): void
    {
        $settings = new FakeSettingsManager([]);
        $cache = new Cache_Manager($settings);

        $key = $cache->get_content_key('post', 'my-slug');

        $this->assertEquals('content_post_my-slug', $key);
    }

    /**
     * Test markdown generator handles empty posts array.
     */
    public function test_markdown_generator_handles_empty_posts(): void
    {
        $settings = new FakeSettingsManager([
            'site_description' => 'Test',
            'contact_email' => 'test@test.com',
        ]);
        $generator = new Markdown_Generator($settings);

        Functions\expect('get_bloginfo')
            ->with('name')
            ->andReturn('Test Site');

        Functions\expect('update_object_term_cache')
            ->andReturn(true);

        Functions\expect('get_page_by_path')
            ->andReturn(null);

        Functions\expect('apply_filters')
            ->andReturnUsing(fn($t, $v) => $v);

        $markdown = $generator->generate_llms_txt([]);

        // Should still have header and quick links
        $this->assertStringContainsString('# Test Site', $markdown);
        $this->assertStringContainsString('## Quick Links', $markdown);
    }

    /**
     * Test components share same settings instance.
     */
    public function test_components_share_settings_instance(): void
    {
        $settings = new FakeSettingsManager(['test' => 'initial']);

        $cache = new Cache_Manager($settings);
        $content_fetcher = new Content_Fetcher($settings);

        // Change setting
        $settings->set('test', 'modified');

        // Both components should see the change
        $this->assertEquals('modified', $settings->get('test'));
    }

    /**
     * Test markdown to HTML conversion edge cases.
     */
    public function test_html_to_markdown_edge_cases(): void
    {
        $settings = new FakeSettingsManager([]);
        $generator = new Markdown_Generator($settings);

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        // Empty string
        $result = $generator->html_to_markdown('');
        $this->assertEquals('', $result);

        // Plain text
        $result = $generator->html_to_markdown('Just plain text');
        $this->assertStringContainsString('Just plain text', $result);

        // Nested tags
        $result = $generator->html_to_markdown('<p><strong><em>Nested</em></strong></p>');
        $this->assertStringContainsString('***Nested***', $result);
    }

    /**
     * Test trusted proxy validation in rate limiter.
     */
    public function test_rate_limiter_trusted_proxy_validation(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

        $settings = new FakeSettingsManager([
            'rate_limit_enabled' => true,
            'rate_limit_requests' => 60,
            'rate_limit_window' => 60,
            'trusted_proxy_ips' => ['10.0.0.0/8'],
        ]);

        // With trusted proxy, should use forwarded IP
        Functions\expect('wp_using_ext_object_cache')
            ->andReturn(false);

        Functions\expect('get_transient')
            ->andReturn(false);

        Functions\expect('set_transient')
            ->andReturn(true);

        $limiter = new Rate_Limiter($settings);

        // Should not be rate limited (first request)
        $this->assertFalse($limiter->is_rate_limited());
    }
}
