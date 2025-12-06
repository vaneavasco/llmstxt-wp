<?php
/**
 * Rate Limiter unit tests.
 *
 * @package LLMSTXT_WP\Tests\Unit\Core
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests\Unit\Core;

use Brain\Monkey\Functions;
use LLMSTXT_WP\Core\Rate_Limiter;
use LLMSTXT_WP\Tests\Stubs\FakeSettingsManager;
use LLMSTXT_WP\Tests\WP_TestCase;

/**
 * Test class for Rate_Limiter.
 */
class RateLimiterTest extends WP_TestCase
{
    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set default remote address.
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        // Clear other IP headers.
        unset(
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_CLIENT_IP']
        );
    }

    /**
     * Create settings with rate limit configuration.
     *
     * @param bool $enabled      Whether rate limiting is enabled.
     * @param int  $max_requests Maximum requests per window.
     * @param int  $window       Time window in seconds.
     * @return FakeSettingsManager
     */
    private function create_settings(
        bool $enabled = true,
        int $max_requests = 60,
        int $window = 60
    ): FakeSettingsManager {
        return new FakeSettingsManager([
            'rate_limit_enabled'  => $enabled,
            'rate_limit_requests' => $max_requests,
            'rate_limit_window'   => $window,
        ]);
    }

    /**
     * Test that requests are allowed when rate limiting is disabled.
     */
    public function test_allows_request_when_disabled(): void
    {
        $settings = $this->create_settings(false);
        $limiter = new Rate_Limiter($settings);

        $this->assertFalse($limiter->is_rate_limited());
    }

    /**
     * Test that first request is allowed with object cache.
     */
    public function test_allows_first_request_with_object_cache(): void
    {
        $settings = $this->create_settings();

        Functions\expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_cache_add')
            ->once()
            ->andReturn(true); // First request - key created successfully.

        $limiter = new Rate_Limiter($settings);

        $this->assertFalse($limiter->is_rate_limited());
    }

    /**
     * Test that requests are blocked when limit is exceeded with object cache.
     */
    public function test_blocks_when_limit_exceeded_with_object_cache(): void
    {
        $settings = $this->create_settings(true, 5);

        Functions\expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_cache_add')
            ->once()
            ->andReturn(false); // Key already exists.

        Functions\expect('wp_cache_incr')
            ->once()
            ->andReturn(6); // Over the limit of 5.

        Functions\expect('do_action')
            ->once();

        $limiter = new Rate_Limiter($settings);

        $this->assertTrue($limiter->is_rate_limited());
    }

    /**
     * Test that subsequent requests within limit are allowed.
     */
    public function test_allows_subsequent_requests_within_limit(): void
    {
        $settings = $this->create_settings(true, 10);

        Functions\expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_cache_add')
            ->once()
            ->andReturn(false); // Key exists.

        Functions\expect('wp_cache_incr')
            ->once()
            ->andReturn(5); // Still under the limit of 10.

        $limiter = new Rate_Limiter($settings);

        $this->assertFalse($limiter->is_rate_limited());
    }

    /**
     * Test get_remaining returns unlimited when disabled.
     */
    public function test_get_remaining_returns_unlimited_when_disabled(): void
    {
        $settings = $this->create_settings(false);
        $limiter = new Rate_Limiter($settings);

        $this->assertEquals(-1, $limiter->get_remaining());
    }

    /**
     * Test get_remaining calculates correctly.
     */
    public function test_get_remaining_calculates_correctly(): void
    {
        $settings = $this->create_settings(true, 100);

        Functions\expect('get_transient')
            ->once()
            ->andReturn(25); // 25 requests made.

        $limiter = new Rate_Limiter($settings);

        $this->assertEquals(75, $limiter->get_remaining()); // 100 - 25.
    }

    /**
     * Test get_window returns setting value.
     */
    public function test_get_window_returns_setting(): void
    {
        $settings = $this->create_settings(true, 60, 120);
        $limiter = new Rate_Limiter($settings);

        $this->assertEquals(120, $limiter->get_window());
    }

    /**
     * Test get_limit returns setting value.
     */
    public function test_get_limit_returns_setting(): void
    {
        $settings = $this->create_settings(true, 100);
        $limiter = new Rate_Limiter($settings);

        $this->assertEquals(100, $limiter->get_limit());
    }

    /**
     * Test reset deletes transient.
     */
    public function test_reset_deletes_transient(): void
    {
        $settings = $this->create_settings();

        Functions\expect('delete_transient')
            ->once()
            ->andReturn(true);

        $limiter = new Rate_Limiter($settings);

        $this->assertTrue($limiter->reset());
    }

    /**
     * Test get_headers returns correct structure.
     */
    public function test_get_headers_returns_correct_structure(): void
    {
        $settings = $this->create_settings(true, 100, 60);

        Functions\expect('get_transient')
            ->once()
            ->andReturn(10);

        $limiter = new Rate_Limiter($settings);
        $headers = $limiter->get_headers();

        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);
        $this->assertEquals(100, $headers['X-RateLimit-Limit']);
        $this->assertEquals(90, $headers['X-RateLimit-Remaining']); // 100 - 10.
    }
}
