<?php
/**
 * Bot Detector unit tests.
 *
 * @package LLMSTXT_WP\Tests\Unit\Core
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests\Unit\Core;

use LLMSTXT_WP\Core\Bot_Detector;
use LLMSTXT_WP\Tests\Stubs\FakeSettingsManager;
use LLMSTXT_WP\Tests\WP_TestCase;

/**
 * Test class for Bot_Detector.
 */
class BotDetectorTest extends WP_TestCase
{
    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any previous server variables.
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_ACCEPT']);
    }

    /**
     * Create a FakeSettingsManager with custom bot patterns.
     *
     * @param string $custom_patterns Custom bot patterns.
     * @return FakeSettingsManager
     */
    private function create_settings(string $custom_patterns = ''): FakeSettingsManager
    {
        return new FakeSettingsManager([
            'custom_bots' => $custom_patterns,
        ]);
    }

    /**
     * Test that GPTBot is detected.
     */
    public function test_detects_gptbot(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 AppleWebKit/537.36 (compatible; GPTBot/1.0; +https://openai.com/gptbot)';

        $detector = new Bot_Detector($this->create_settings());

        $this->assertTrue($detector->is_ai_bot());
        $this->assertEquals('GPTBot', $detector->get_bot_name());
    }

    /**
     * Test that ClaudeBot is detected.
     */
    public function test_detects_claudebot(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'ClaudeBot/1.0';

        $detector = new Bot_Detector($this->create_settings());

        $this->assertTrue($detector->is_ai_bot());
        $this->assertEquals('ClaudeBot', $detector->get_bot_name());
    }

    /**
     * Test that Claude-Web is detected.
     */
    public function test_detects_claude_web(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Claude-Web/1.0';

        $detector = new Bot_Detector($this->create_settings());

        $this->assertTrue($detector->is_ai_bot());
        $this->assertEquals('Claude-Web', $detector->get_bot_name());
    }

    /**
     * Test that PerplexityBot is detected.
     */
    public function test_detects_perplexitybot(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'PerplexityBot/1.0';

        $detector = new Bot_Detector($this->create_settings());

        $this->assertTrue($detector->is_ai_bot());
        $this->assertEquals('PerplexityBot', $detector->get_bot_name());
    }

    /**
     * Test that Google-Extended is detected.
     */
    public function test_detects_google_extended(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Google-Extended/2.1)';

        $detector = new Bot_Detector($this->create_settings());

        $this->assertTrue($detector->is_ai_bot());
        $this->assertEquals('Google-Extended', $detector->get_bot_name());
    }

    /**
     * Test that regular browsers are not detected as AI bots.
     */
    public function test_returns_false_for_regular_browser(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';

        $detector = new Bot_Detector($this->create_settings());

        $this->assertFalse($detector->is_ai_bot());
        $this->assertNull($detector->get_bot_name());
    }

    /**
     * Test that requests with text/markdown Accept header are detected.
     */
    public function test_detects_markdown_accept_header(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'curl/7.68.0';
        $_SERVER['HTTP_ACCEPT'] = 'text/markdown';

        $detector = new Bot_Detector($this->create_settings());

        $this->assertTrue($detector->is_ai_bot());
        $this->assertEquals('markdown-client', $detector->get_bot_name());
    }

    /**
     * Test that custom bot patterns work.
     */
    public function test_detects_custom_bot_pattern(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'MyCustomCrawler/1.0';

        $detector = new Bot_Detector($this->create_settings('MyCustomCrawler'));

        $this->assertTrue($detector->is_ai_bot());
        $this->assertEquals('custom:MyCustomCrawler', $detector->get_bot_name());
    }

    /**
     * Test that multiple custom patterns are supported.
     */
    public function test_detects_multiple_custom_patterns(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'AnotherBot/2.0';

        $detector = new Bot_Detector($this->create_settings("MyBot\nAnotherBot\nThirdBot"));

        $this->assertTrue($detector->is_ai_bot());
        $this->assertEquals('custom:AnotherBot', $detector->get_bot_name());
    }

    /**
     * Test detection without user agent.
     */
    public function test_handles_missing_user_agent(): void
    {
        // No user agent set.
        $detector = new Bot_Detector($this->create_settings());

        $this->assertFalse($detector->is_ai_bot());
        $this->assertNull($detector->get_bot_name());
    }
}
