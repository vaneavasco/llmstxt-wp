<?php
/**
 * Cache Manager unit tests.
 *
 * @package LLMSTXT_WP\Tests\Unit\Core
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests\Unit\Core;

use Brain\Monkey\Functions;
use LLMSTXT_WP\Core\Cache_Manager;
use LLMSTXT_WP\Tests\Stubs\FakeSettingsManager;
use LLMSTXT_WP\Tests\WP_TestCase;

/**
 * Test class for Cache_Manager.
 */
class CacheManagerTest extends WP_TestCase
{
    /**
     * Create settings with cache configuration.
     *
     * @param int $llms_ttl    LLMS.txt cache TTL.
     * @param int $content_ttl Content cache TTL.
     * @return FakeSettingsManager
     */
    private function create_settings(
        int $llms_ttl = 3600,
        int $content_ttl = 600
    ): FakeSettingsManager {
        return new FakeSettingsManager([
            'llms_txt_cache_ttl' => $llms_ttl,
            'content_cache_ttl'  => $content_ttl,
            'post_types'         => ['post', 'page'],
        ]);
    }

    /**
     * Test get returns cached value.
     */
    public function test_get_returns_cached_value(): void
    {
        $settings = $this->create_settings();

        Functions\expect('get_transient')
            ->once()
            ->with('aico_test_key')
            ->andReturn('cached_value');

        $cache = new Cache_Manager($settings);

        $this->assertEquals('cached_value', $cache->get('test_key'));
    }

    /**
     * Test get returns false when not cached.
     */
    public function test_get_returns_false_when_not_cached(): void
    {
        $settings = $this->create_settings();

        Functions\expect('get_transient')
            ->once()
            ->with('aico_test_key')
            ->andReturn(false);

        $cache = new Cache_Manager($settings);

        $this->assertFalse($cache->get('test_key'));
    }

    /**
     * Test set stores value with correct TTL.
     */
    public function test_set_stores_value_with_ttl(): void
    {
        $settings = $this->create_settings(3600, 600);

        Functions\expect('set_transient')
            ->once()
            ->with('aico_test_key', 'test_value', 600)
            ->andReturn(true);

        $cache = new Cache_Manager($settings);

        $this->assertTrue($cache->set('test_key', 'test_value'));
    }

    /**
     * Test set with custom TTL.
     */
    public function test_set_with_custom_ttl(): void
    {
        $settings = $this->create_settings();

        Functions\expect('set_transient')
            ->once()
            ->with('aico_test_key', 'test_value', 120)
            ->andReturn(true);

        $cache = new Cache_Manager($settings);

        $this->assertTrue($cache->set('test_key', 'test_value', 120));
    }

    /**
     * Test delete removes cached value.
     */
    public function test_delete_removes_cached_value(): void
    {
        $settings = $this->create_settings();

        Functions\expect('delete_transient')
            ->once()
            ->with('aico_test_key')
            ->andReturn(true);

        $cache = new Cache_Manager($settings);

        $this->assertTrue($cache->delete('test_key'));
    }

    /**
     * Test set_not_found caches with short TTL.
     */
    public function test_set_not_found_caches_marker(): void
    {
        $settings = $this->create_settings();

        Functions\expect('set_transient')
            ->once()
            ->with('aico_test_key', Cache_Manager::NOT_FOUND_MARKER, Cache_Manager::NOT_FOUND_TTL)
            ->andReturn(true);

        $cache = new Cache_Manager($settings);

        $this->assertTrue($cache->set_not_found('test_key'));
    }

    /**
     * Test is_not_found detects marker.
     */
    public function test_is_not_found_detects_marker(): void
    {
        $settings = $this->create_settings();
        $cache = new Cache_Manager($settings);

        $this->assertTrue($cache->is_not_found(Cache_Manager::NOT_FOUND_MARKER));
        $this->assertFalse($cache->is_not_found('some_content'));
        $this->assertFalse($cache->is_not_found(false));
    }

    /**
     * Test get_llms_txt_key includes post types in hash.
     */
    public function test_get_llms_txt_key_includes_post_types(): void
    {
        $settings1 = new FakeSettingsManager(['post_types' => ['post', 'page']]);
        $settings2 = new FakeSettingsManager(['post_types' => ['post']]);

        $cache1 = new Cache_Manager($settings1);
        $cache2 = new Cache_Manager($settings2);

        $key1 = $cache1->get_llms_txt_key();
        $key2 = $cache2->get_llms_txt_key();

        $this->assertNotEquals($key1, $key2);
        $this->assertStringStartsWith('llms_txt_', $key1);
        $this->assertStringStartsWith('llms_txt_', $key2);
    }

    /**
     * Test get_content_key generates unique keys.
     */
    public function test_get_content_key_generates_unique_keys(): void
    {
        $settings = $this->create_settings();
        $cache = new Cache_Manager($settings);

        $key1 = $cache->get_content_key('post', 'hello-world');
        $key2 = $cache->get_content_key('page', 'hello-world');
        $key3 = $cache->get_content_key('post', 'another-post');

        $this->assertEquals('content_post_hello-world', $key1);
        $this->assertEquals('content_page_hello-world', $key2);
        $this->assertEquals('content_post_another-post', $key3);
        $this->assertNotEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
    }

    /**
     * Test get_llms_txt_ttl returns setting value.
     */
    public function test_get_llms_txt_ttl_returns_setting(): void
    {
        $settings = $this->create_settings(7200);
        $cache = new Cache_Manager($settings);

        $this->assertEquals(7200, $cache->get_llms_txt_ttl());
    }

    /**
     * Test get_content_ttl returns setting value.
     */
    public function test_get_content_ttl_returns_setting(): void
    {
        $settings = $this->create_settings(3600, 300);
        $cache = new Cache_Manager($settings);

        $this->assertEquals(300, $cache->get_content_ttl());
    }

    /**
     * Test acquire_regeneration_lock with object cache.
     */
    public function test_acquire_regeneration_lock_with_object_cache(): void
    {
        $settings = $this->create_settings();

        Functions\expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_cache_add')
            ->once()
            ->with('aico_test_key_lock', 1, '', 30)
            ->andReturn(true);

        $cache = new Cache_Manager($settings);

        $this->assertTrue($cache->acquire_regeneration_lock('test_key'));
    }

    /**
     * Test acquire_regeneration_lock fails when locked.
     */
    public function test_acquire_regeneration_lock_fails_when_locked(): void
    {
        $settings = $this->create_settings();

        Functions\expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_cache_add')
            ->once()
            ->andReturn(false); // Lock already held.

        $cache = new Cache_Manager($settings);

        $this->assertFalse($cache->acquire_regeneration_lock('test_key'));
    }

    /**
     * Test release_regeneration_lock with object cache.
     */
    public function test_release_regeneration_lock_with_object_cache(): void
    {
        $settings = $this->create_settings();

        Functions\expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_cache_delete')
            ->once()
            ->with('aico_test_key_lock');

        $cache = new Cache_Manager($settings);
        $cache->release_regeneration_lock('test_key');

        // No assertion needed - just verify no exceptions.
        $this->assertTrue(true);
    }

    /**
     * Test get_with_lock returns cached content.
     */
    public function test_get_with_lock_returns_cached_content(): void
    {
        $settings = $this->create_settings();

        Functions\expect('get_transient')
            ->once()
            ->with('aico_test_key')
            ->andReturn('cached_content');

        $cache = new Cache_Manager($settings);
        $result = $cache->get_with_lock('test_key');

        $this->assertEquals('cached_content', $result['content']);
        $this->assertFalse($result['should_regenerate']);
    }

    /**
     * Test get_with_lock signals regeneration on cache miss.
     */
    public function test_get_with_lock_signals_regeneration_on_miss(): void
    {
        $settings = $this->create_settings();

        Functions\expect('get_transient')
            ->once()
            ->with('aico_test_key')
            ->andReturn(false);

        Functions\expect('wp_using_ext_object_cache')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_cache_add')
            ->once()
            ->andReturn(true); // Lock acquired.

        $cache = new Cache_Manager($settings);
        $result = $cache->get_with_lock('test_key');

        $this->assertFalse($result['content']);
        $this->assertTrue($result['should_regenerate']);
    }
}
