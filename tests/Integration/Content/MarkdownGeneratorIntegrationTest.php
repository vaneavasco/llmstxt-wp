<?php
/**
 * Markdown Generator integration tests.
 *
 * Tests the Markdown_Generator component with WordPress content simulation.
 *
 * @package LLMSTXT_WP\Tests\Integration\Content
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests\Integration\Content;

use Brain\Monkey\Functions;
use LLMSTXT_WP\Content\Markdown_Generator;
use LLMSTXT_WP\Tests\Integration\WP_Integration_TestCase;
use LLMSTXT_WP\Tests\Stubs\FakeSettingsManager;
use WP_Post;

/**
 * Integration tests for Markdown_Generator.
 */
class MarkdownGeneratorIntegrationTest extends WP_Integration_TestCase
{
    /**
     * Settings manager.
     *
     * @var FakeSettingsManager
     */
    private FakeSettingsManager $settings;

    /**
     * Markdown generator.
     *
     * @var Markdown_Generator
     */
    private Markdown_Generator $generator;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = new FakeSettingsManager([
            'site_description' => 'A test site for testing',
            'contact_email' => 'test@example.com',
        ]);

        $this->generator = new Markdown_Generator($this->settings);
    }

    /**
     * Test generate_llms_txt creates proper header.
     */
    public function test_generate_llms_txt_creates_header(): void
    {
        $post = $this->create_mock_post([
            'post_type' => 'post',
            'post_title' => 'Test Post',
            'post_name' => 'test-post',
        ]);

        Functions\expect('get_bloginfo')
            ->with('name')
            ->andReturn('Test Site');

        Functions\expect('update_object_term_cache')
            ->andReturn(true);

        Functions\expect('apply_filters')
            ->andReturnUsing(function ($tag, $value) {
                return $value;
            });

        $markdown = $this->generator->generate_llms_txt([$post]);

        $this->assertStringContainsString('# Test Site', $markdown);
        $this->assertStringContainsString('> A test site for testing', $markdown);
        $this->assertStringContainsString('> Contact: test@example.com', $markdown);
    }

    /**
     * Test generate_llms_txt groups posts by type.
     */
    public function test_generate_llms_txt_groups_by_post_type(): void
    {
        $post1 = $this->create_mock_post([
            'post_type' => 'post',
            'post_title' => 'Blog Article',
        ]);

        $page1 = $this->create_mock_post([
            'post_type' => 'page',
            'post_title' => 'About Page',
        ]);

        Functions\expect('get_bloginfo')->andReturn('Test');

        Functions\expect('get_post_type_object')
            ->with('post')
            ->andReturn((object) ['labels' => (object) ['name' => 'Posts']]);

        Functions\expect('get_post_type_object')
            ->with('page')
            ->andReturn((object) ['labels' => (object) ['name' => 'Pages']]);

        Functions\expect('update_object_term_cache')->andReturn(true);
        Functions\expect('apply_filters')->andReturnUsing(fn($t, $v) => $v);

        $markdown = $this->generator->generate_llms_txt([$post1, $page1]);

        $this->assertStringContainsString('## Posts', $markdown);
        $this->assertStringContainsString('## Pages', $markdown);
        $this->assertStringContainsString('Blog Article', $markdown);
        $this->assertStringContainsString('About Page', $markdown);
    }

    /**
     * Test generate_post_entry includes metadata.
     */
    public function test_generate_post_entry_includes_metadata(): void
    {
        $post = $this->create_mock_post([
            'ID' => 1,
            'post_type' => 'post',
            'post_title' => 'My Article',
            'post_excerpt' => 'This is the excerpt.',
            'post_author' => 1,
            'post_name' => 'my-article',
        ]);

        Functions\when('get_permalink')->justReturn('https://example.com/my-article/');
        Functions\when('get_the_date')->justReturn('January 1, 2024');
        Functions\when('get_the_author_meta')->justReturn('John Doe');
        Functions\when('get_object_taxonomies')->justReturn(['category']);
        Functions\when('get_the_terms')->justReturn(false);
        Functions\when('apply_filters')->alias(fn($t, $v) => $v);

        $entry = $this->generator->generate_post_entry($post, 1);

        $this->assertStringContainsString('### 1. My Article', $entry);
        $this->assertStringContainsString('This is the excerpt', $entry);
        $this->assertStringContainsString('**Published:** January 1, 2024', $entry);
        $this->assertStringContainsString('**Author:** John Doe', $entry);
        $this->assertStringContainsString('**Read More:**', $entry);
    }

    /**
     * Test generate_post_detail creates full markdown.
     */
    public function test_generate_post_detail_creates_full_markdown(): void
    {
        $post = $this->create_mock_post([
            'ID' => 5,
            'post_type' => 'post',
            'post_title' => 'Complete Article',
            'post_content' => '<p>This is the <strong>full content</strong> of the article.</p>',
            'post_author' => 2,
        ]);

        Functions\when('get_permalink')->justReturn('https://example.com/complete-article/');
        Functions\when('get_the_date')->justReturn('February 15, 2024');
        Functions\when('get_the_modified_date')->justReturn('February 20, 2024');
        Functions\when('get_the_author_meta')->justReturn('Jane Smith');
        Functions\when('get_author_posts_url')->justReturn('https://example.com/author/jane/');
        Functions\when('get_object_taxonomies')->justReturn([]);
        Functions\when('has_post_thumbnail')->justReturn(false);
        Functions\when('get_bloginfo')->justReturn('Test Blog');
        Functions\when('apply_filters')->alias(fn($t, $v) => $v);

        $markdown = $this->generator->generate_post_detail($post);

        $this->assertStringContainsString('# Complete Article', $markdown);
        $this->assertStringContainsString('**full content**', $markdown);
        $this->assertStringContainsString('**Published:** February 15, 2024', $markdown);
        $this->assertStringContainsString('**Last Updated:** February 20, 2024', $markdown);
        $this->assertStringContainsString('[Jane Smith](https://example.com/author/jane/)', $markdown);
        $this->assertStringContainsString('*From [Test Blog]', $markdown);
    }

    /**
     * Test HTML to markdown converts headings.
     */
    public function test_html_to_markdown_converts_headings(): void
    {
        $html = '<h1>Main Title</h1><h2>Section</h2><h3>Subsection</h3>';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringContainsString('# Main Title', $markdown);
        $this->assertStringContainsString('## Section', $markdown);
        $this->assertStringContainsString('### Subsection', $markdown);
    }

    /**
     * Test HTML to markdown converts emphasis.
     */
    public function test_html_to_markdown_converts_emphasis(): void
    {
        $html = '<p>This is <strong>bold</strong> and <em>italic</em> text.</p>';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringContainsString('**bold**', $markdown);
        $this->assertStringContainsString('*italic*', $markdown);
    }

    /**
     * Test HTML to markdown converts links.
     */
    public function test_html_to_markdown_converts_links(): void
    {
        $html = '<a href="https://example.com">Click here</a>';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringContainsString('[Click here](https://example.com)', $markdown);
    }

    /**
     * Test HTML to markdown converts images.
     */
    public function test_html_to_markdown_converts_images(): void
    {
        $html = '<img src="https://example.com/image.jpg" alt="Test Image">';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringContainsString('![Test Image](https://example.com/image.jpg)', $markdown);
    }

    /**
     * Test HTML to markdown converts ordered lists.
     */
    public function test_html_to_markdown_converts_ordered_lists(): void
    {
        $html = '<ol><li>First item</li><li>Second item</li><li>Third item</li></ol>';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringContainsString('1. First item', $markdown);
        $this->assertStringContainsString('2. Second item', $markdown);
        $this->assertStringContainsString('3. Third item', $markdown);
    }

    /**
     * Test HTML to markdown converts unordered lists.
     */
    public function test_html_to_markdown_converts_unordered_lists(): void
    {
        $html = '<ul><li>Apple</li><li>Banana</li></ul>';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringContainsString('- Apple', $markdown);
        $this->assertStringContainsString('- Banana', $markdown);
    }

    /**
     * Test HTML to markdown converts code blocks.
     */
    public function test_html_to_markdown_converts_code_blocks(): void
    {
        $html = '<pre><code class="language-php">echo "Hello";</code></pre>';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringContainsString('```php', $markdown);
        $this->assertStringContainsString('echo "Hello";', $markdown);
        $this->assertStringContainsString('```', $markdown);
    }

    /**
     * Test HTML to markdown converts blockquotes.
     */
    public function test_html_to_markdown_converts_blockquotes(): void
    {
        $html = '<blockquote>This is a quote.</blockquote>';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringContainsString('> This is a quote.', $markdown);
    }

    /**
     * Test HTML to markdown converts tables.
     */
    public function test_html_to_markdown_converts_tables(): void
    {
        $html = '<table><thead><tr><th>Name</th><th>Age</th></tr></thead><tbody><tr><td>John</td><td>30</td></tr></tbody></table>';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringContainsString('| Name | Age |', $markdown);
        $this->assertStringContainsString('| --- | --- |', $markdown);
        $this->assertStringContainsString('| John | 30 |', $markdown);
    }

    /**
     * Test HTML to markdown strips scripts.
     */
    public function test_html_to_markdown_strips_scripts(): void
    {
        $html = '<p>Content</p><script>alert("XSS")</script><p>More content</p>';

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $markdown = $this->generator->html_to_markdown($html);

        $this->assertStringNotContainsString('script', $markdown);
        $this->assertStringNotContainsString('alert', $markdown);
        $this->assertStringContainsString('Content', $markdown);
        $this->assertStringContainsString('More content', $markdown);
    }

    /**
     * Test clean_text truncates with ellipsis.
     */
    public function test_clean_text_truncates_long_text(): void
    {
        $longText = str_repeat('word ', 100);

        Functions\expect('wp_strip_all_tags')
            ->andReturnUsing(fn($s) => strip_tags($s));

        $cleaned = $this->generator->clean_text($longText, 50);

        $this->assertLessThanOrEqual(53, strlen($cleaned)); // 50 + "..."
        $this->assertStringEndsWith('...', $cleaned);
    }

    /**
     * Test generate_llms_txt includes quick links.
     */
    public function test_generate_llms_txt_includes_quick_links(): void
    {
        $post = $this->create_mock_post([
            'post_type' => 'post',
            'post_title' => 'Test',
        ]);

        $aboutPage = $this->create_mock_post([
            'post_type' => 'page',
            'post_name' => 'about',
            'post_title' => 'About Us',
            'post_status' => 'publish',
        ]);

        Functions\expect('get_bloginfo')->andReturn('Test');
        Functions\expect('update_object_term_cache')->andReturn(true);
        Functions\expect('apply_filters')->andReturnUsing(fn($t, $v) => $v);

        Functions\expect('get_page_by_path')
            ->with('about')
            ->andReturn($aboutPage);

        Functions\expect('get_page_by_path')
            ->with('contact')
            ->andReturn(null);

        Functions\expect('get_page_by_path')
            ->with('faq')
            ->andReturn(null);

        $markdown = $this->generator->generate_llms_txt([$post]);

        $this->assertStringContainsString('## Quick Links', $markdown);
        $this->assertStringContainsString('[Home]', $markdown);
        $this->assertStringContainsString('[About Us]', $markdown);
    }

    /**
     * Test post terms are included when available.
     */
    public function test_post_entry_includes_categories(): void
    {
        $post = $this->create_mock_post([
            'post_type' => 'post',
            'post_title' => 'Categorized Post',
        ]);

        $term1 = (object) ['name' => 'News'];
        $term2 = (object) ['name' => 'Technology'];

        Functions\when('get_permalink')->justReturn('https://example.com/post/');
        Functions\when('get_the_date')->justReturn('Jan 1, 2024');
        Functions\when('get_the_author_meta')->justReturn('Author');
        Functions\when('get_object_taxonomies')->justReturn(['category', 'post_tag']);

        Functions\when('get_the_terms')
            ->alias(function ($post, $taxonomy) use ($term1, $term2) {
                if ($taxonomy === 'category') {
                    return [$term1, $term2];
                }
                return false;
            });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('apply_filters')->alias(fn($t, $v) => $v);

        $entry = $this->generator->generate_post_entry($post, 1);

        $this->assertStringContainsString('**Categories:** News, Technology', $entry);
    }

    /**
     * Test featured image is included in post detail.
     */
    public function test_post_detail_includes_featured_image(): void
    {
        $post = $this->create_mock_post([
            'ID' => 10,
            'post_title' => 'Image Post',
            'post_content' => 'Content here.',
        ]);

        Functions\when('get_permalink')->justReturn('https://example.com/');
        Functions\when('get_the_date')->justReturn('Jan 1, 2024');
        Functions\when('get_the_modified_date')->justReturn('Jan 2, 2024');
        Functions\when('get_the_author_meta')->justReturn('Author');
        Functions\when('get_author_posts_url')->justReturn('https://example.com/author/');
        Functions\when('get_object_taxonomies')->justReturn([]);
        Functions\when('get_bloginfo')->justReturn('Site');
        Functions\when('has_post_thumbnail')->justReturn(true);
        Functions\when('get_the_post_thumbnail_url')->justReturn('https://example.com/featured.jpg');
        Functions\when('get_post_thumbnail_id')->justReturn(100);
        Functions\when('get_post_meta')->justReturn('Featured Image Alt');
        Functions\when('apply_filters')->alias(fn($t, $v) => $v);

        $markdown = $this->generator->generate_post_detail($post);

        $this->assertStringContainsString('## Featured Image', $markdown);
        $this->assertStringContainsString('![Featured Image Alt](https://example.com/featured.jpg)', $markdown);
    }
}
