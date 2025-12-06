<?php
/**
 * Content Fetcher integration tests.
 *
 * Tests the Content_Fetcher component with WordPress query simulation.
 *
 * @package LLMSTXT_WP\Tests\Integration\Content
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests\Integration\Content;

use Brain\Monkey\Functions;
use LLMSTXT_WP\Content\Content_Fetcher;
use LLMSTXT_WP\Tests\Integration\WP_Integration_TestCase;
use LLMSTXT_WP\Tests\Stubs\FakeSettingsManager;
use WP_Post;
use WP_Query;

/**
 * Integration tests for Content_Fetcher.
 */
class ContentFetcherIntegrationTest extends WP_Integration_TestCase
{
    /**
     * Settings manager.
     *
     * @var FakeSettingsManager
     */
    private FakeSettingsManager $settings;

    /**
     * Content fetcher.
     *
     * @var Content_Fetcher
     */
    private Content_Fetcher $fetcher;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = new FakeSettingsManager([
            'post_types' => ['post', 'page'],
        ]);

        $this->fetcher = new Content_Fetcher($this->settings);

        // Clear any previous mock posts
        WP_Query::clear_mock_posts();
    }

    /**
     * Tear down.
     */
    protected function tearDown(): void
    {
        WP_Query::clear_mock_posts();
        parent::tearDown();
    }

    /**
     * Test get_all_published returns posts from configured types.
     */
    public function test_get_all_published_returns_configured_post_types(): void
    {
        // Create mock posts
        $post1 = $this->create_mock_post([
            'ID' => 1,
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_title' => 'Blog Post 1',
        ]);

        $post2 = $this->create_mock_post([
            'ID' => 2,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'About Page',
        ]);

        $post3 = $this->create_mock_post([
            'ID' => 3,
            'post_type' => 'custom',
            'post_status' => 'publish',
            'post_title' => 'Custom Post',
        ]);

        WP_Query::set_mock_posts([$post1, $post2, $post3]);

        Functions\when('apply_filters')
            ->alias(function ($tag, $value) {
                return $value;
            });

        Functions\when('do_action')->justReturn(null);

        $posts = $this->fetcher->get_all_published();

        // Should only return post and page types
        $this->assertCount(2, $posts);

        $types = array_map(fn($p) => $p->post_type, $posts);
        $this->assertContains('post', $types);
        $this->assertContains('page', $types);
        $this->assertNotContains('custom', $types);
    }

    /**
     * Test get_all_published respects post limit filter.
     */
    public function test_get_all_published_respects_post_limit(): void
    {
        // Create 10 posts
        $posts = [];
        for ($i = 1; $i <= 10; $i++) {
            $posts[] = $this->create_mock_post([
                'ID' => $i,
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_title' => "Post {$i}",
            ]);
        }

        WP_Query::set_mock_posts($posts);

        // Use alias to handle multiple filter calls
        $limit = 5;
        Functions\when('apply_filters')
            ->alias(function ($tag, $value) use ($limit) {
                if ($tag === 'aico_llms_txt_post_limit') {
                    return $limit;
                }
                return $value;
            });

        Functions\when('do_action')->justReturn(null);

        $result = $this->fetcher->get_all_published();

        $this->assertCount($limit, $result);
    }

    /**
     * Test get_all_published only returns published posts.
     */
    public function test_get_all_published_only_returns_published(): void
    {
        $published = $this->create_mock_post([
            'ID' => 1,
            'post_type' => 'post',
            'post_status' => 'publish',
        ]);

        $draft = $this->create_mock_post([
            'ID' => 2,
            'post_type' => 'post',
            'post_status' => 'draft',
        ]);

        $pending = $this->create_mock_post([
            'ID' => 3,
            'post_type' => 'post',
            'post_status' => 'pending',
        ]);

        WP_Query::set_mock_posts([$published, $draft, $pending]);

        Functions\expect('apply_filters')->andReturnUsing(function ($tag, $value) {
            return $value;
        });

        Functions\expect('do_action')->andReturn(null);

        $result = $this->fetcher->get_all_published();

        $this->assertCount(1, $result);
        $this->assertEquals('publish', $result[0]->post_status);
    }

    /**
     * Test get_by_slug returns correct post.
     */
    public function test_get_by_slug_returns_matching_post(): void
    {
        $post = $this->create_mock_post([
            'ID' => 1,
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_name' => 'my-test-post',
            'post_title' => 'My Test Post',
        ]);

        WP_Query::add_mock_post($post);

        Functions\expect('apply_filters')
            ->with('aico_allowed_post_types', ['post', 'page'])
            ->andReturn(['post', 'page']);

        Functions\expect('apply_filters')
            ->with('aico_single_post_query_args', \Mockery::type('array'), 'post', 'my-test-post')
            ->andReturnUsing(function ($tag, $args) {
                return $args;
            });

        $result = $this->fetcher->get_by_slug('post', 'my-test-post');

        $this->assertInstanceOf(WP_Post::class, $result);
        $this->assertEquals('My Test Post', $result->post_title);
        $this->assertEquals('my-test-post', $result->post_name);
    }

    /**
     * Test get_by_slug returns null for non-existent post.
     */
    public function test_get_by_slug_returns_null_for_missing_post(): void
    {
        WP_Query::clear_mock_posts();

        Functions\expect('apply_filters')
            ->with('aico_allowed_post_types', ['post', 'page'])
            ->andReturn(['post', 'page']);

        Functions\expect('apply_filters')
            ->with('aico_single_post_query_args', \Mockery::type('array'), 'post', 'non-existent')
            ->andReturnUsing(function ($tag, $args) {
                return $args;
            });

        $result = $this->fetcher->get_by_slug('post', 'non-existent');

        $this->assertNull($result);
    }

    /**
     * Test get_by_slug returns null for disallowed post type.
     */
    public function test_get_by_slug_returns_null_for_disallowed_type(): void
    {
        $post = $this->create_mock_post([
            'ID' => 1,
            'post_type' => 'secret',
            'post_status' => 'publish',
            'post_name' => 'secret-post',
        ]);

        WP_Query::add_mock_post($post);

        Functions\expect('apply_filters')
            ->with('aico_allowed_post_types', ['post', 'page'])
            ->andReturn(['post', 'page']);

        $result = $this->fetcher->get_by_slug('secret', 'secret-post');

        $this->assertNull($result);
    }

    /**
     * Test get_by_id returns correct post.
     */
    public function test_get_by_id_returns_matching_post(): void
    {
        $post = $this->create_mock_post([
            'ID' => 42,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'About Us',
        ]);

        Functions\expect('get_post')
            ->with(42)
            ->andReturn($post);

        Functions\expect('apply_filters')
            ->with('aico_allowed_post_types', ['post', 'page'])
            ->andReturn(['post', 'page']);

        $result = $this->fetcher->get_by_id(42);

        $this->assertInstanceOf(WP_Post::class, $result);
        $this->assertEquals(42, $result->ID);
        $this->assertEquals('About Us', $result->post_title);
    }

    /**
     * Test get_by_id returns null for draft posts.
     */
    public function test_get_by_id_returns_null_for_draft(): void
    {
        $post = $this->create_mock_post([
            'ID' => 10,
            'post_type' => 'post',
            'post_status' => 'draft',
        ]);

        Functions\expect('get_post')
            ->with(10)
            ->andReturn($post);

        $result = $this->fetcher->get_by_id(10);

        $this->assertNull($result);
    }

    /**
     * Test get_by_id returns null for disallowed post type.
     */
    public function test_get_by_id_returns_null_for_disallowed_type(): void
    {
        $post = $this->create_mock_post([
            'ID' => 15,
            'post_type' => 'attachment',
            'post_status' => 'publish',
        ]);

        Functions\expect('get_post')
            ->with(15)
            ->andReturn($post);

        Functions\expect('apply_filters')
            ->with('aico_allowed_post_types', ['post', 'page'])
            ->andReturn(['post', 'page']);

        $result = $this->fetcher->get_by_id(15);

        $this->assertNull($result);
    }

    /**
     * Test is_allowed_post_type respects settings.
     */
    public function test_is_allowed_post_type_respects_settings(): void
    {
        Functions\expect('apply_filters')
            ->with('aico_allowed_post_types', ['post', 'page'])
            ->andReturn(['post', 'page']);

        $this->assertTrue($this->fetcher->is_allowed_post_type('post'));
        $this->assertTrue($this->fetcher->is_allowed_post_type('page'));
        $this->assertFalse($this->fetcher->is_allowed_post_type('custom'));
    }

    /**
     * Test is_allowed_post_type respects filter.
     */
    public function test_is_allowed_post_type_respects_filter(): void
    {
        // Filter adds custom post type
        Functions\when('apply_filters')
            ->alias(function ($tag, $value) {
                if ($tag === 'aico_allowed_post_types') {
                    return ['post', 'page', 'product'];
                }
                return $value;
            });

        $this->assertTrue($this->fetcher->is_allowed_post_type('product'));
    }

    /**
     * Test get_allowed_post_types returns filtered types.
     */
    public function test_get_allowed_post_types_returns_filtered_types(): void
    {
        Functions\when('apply_filters')
            ->alias(function ($tag, $value) {
                if ($tag === 'aico_allowed_post_types') {
                    return ['post', 'page', 'custom'];
                }
                return $value;
            });

        $types = $this->fetcher->get_allowed_post_types();

        $this->assertCount(3, $types);
        $this->assertContains('custom', $types);
    }

    /**
     * Test get_content_counts returns counts per post type.
     */
    public function test_get_content_counts_returns_per_type_counts(): void
    {
        Functions\when('apply_filters')
            ->alias(fn($t, $v) => $v);

        Functions\when('wp_count_posts')
            ->alias(function ($type) {
                switch ($type) {
                    case 'post':
                        return (object) ['publish' => 25, 'draft' => 5];
                    case 'page':
                        return (object) ['publish' => 10, 'draft' => 2];
                    default:
                        return (object) ['publish' => 0];
                }
            });

        $counts = $this->fetcher->get_content_counts();

        $this->assertEquals(25, $counts['post']);
        $this->assertEquals(10, $counts['page']);
    }

    /**
     * Test get_total_count sums all post types.
     */
    public function test_get_total_count_sums_all_types(): void
    {
        Functions\when('apply_filters')
            ->alias(fn($t, $v) => $v);

        Functions\when('wp_count_posts')
            ->alias(function ($type) {
                switch ($type) {
                    case 'post':
                        return (object) ['publish' => 50];
                    case 'page':
                        return (object) ['publish' => 30];
                    default:
                        return (object) ['publish' => 0];
                }
            });

        $total = $this->fetcher->get_total_count();

        $this->assertEquals(80, $total);
    }

    /**
     * Test custom post types can be added via settings.
     */
    public function test_custom_post_types_via_settings(): void
    {
        $this->settings->set('post_types', ['post', 'page', 'product', 'service']);

        $fetcher = new Content_Fetcher($this->settings);

        Functions\expect('apply_filters')
            ->with('aico_allowed_post_types', ['post', 'page', 'product', 'service'])
            ->andReturn(['post', 'page', 'product', 'service']);

        $types = $fetcher->get_allowed_post_types();

        $this->assertCount(4, $types);
        $this->assertContains('product', $types);
        $this->assertContains('service', $types);
    }

    /**
     * Test default post limit constant.
     */
    public function test_default_post_limit_constant(): void
    {
        $this->assertEquals(500, Content_Fetcher::DEFAULT_POST_LIMIT);
    }

    /**
     * Test query uses no_found_rows for performance.
     */
    public function test_query_uses_performance_optimizations(): void
    {
        WP_Query::set_mock_posts([]);

        $query_args_captured = null;

        Functions\when('apply_filters')
            ->alias(function ($tag, $value) use (&$query_args_captured) {
                if ($tag === 'aico_llms_txt_query_args') {
                    $query_args_captured = $value;
                }
                return $value;
            });

        Functions\when('do_action')->justReturn(null);

        $this->fetcher->get_all_published();

        // Verify performance optimizations
        $this->assertIsArray($query_args_captured);
        $this->assertArrayHasKey('no_found_rows', $query_args_captured);
        $this->assertTrue($query_args_captured['no_found_rows']);
    }
}
