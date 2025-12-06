<?php
/**
 * Bot Detector.
 *
 * Detects AI crawlers and language model bots from User-Agent strings.
 * Includes patterns for 45+ known AI bots including GPTBot, ClaudeBot, etc.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Core;

/**
 * Bot Detector class.
 *
 * Uses compiled regex for O(1) matching against known AI bot patterns.
 * Updated based on darkvisitors.com, ai-robots-txt, and Cloudflare data.
 */
final class Bot_Detector {

	/**
	 * Compiled regex pattern for AI bot detection.
	 *
	 * Covers approximately 95% of identified AI crawler traffic.
	 * Last updated: December 2024.
	 */
	public const BOT_PATTERN = '/(' .
		// OpenAI.
		'GPTBot|ChatGPT|OAI-SearchBot|Operator|' .
		// Anthropic.
		'ClaudeBot|Claude-Web|Claude-User|Claude-SearchBot|anthropic-ai|' .
		// Google AI.
		'Google-Extended|Google-CloudVertexBot|GoogleAgent-Mariner|' .
		'Gemini-Deep-Research|Google-NotebookLM|GoogleOther|' .
		// Perplexity.
		'PerplexityBot|Perplexity-User|' .
		// Meta AI.
		'Meta-ExternalAgent|meta-externalagent|' .
		// Amazon AI.
		'Amazonbot|NovaAct|' .
		// Apple AI.
		'Applebot-Extended|' .
		// Mistral.
		'MistralAI-User|' .
		// ByteDance AI (Doubao LLM).
		'Bytespider|' .
		// Cohere.
		'cohere-ai|cohere-training-data-crawler|' .
		// Common Crawl (used extensively for LLM training).
		'CCBot|' .
		// AI Search Engines.
		'DuckAssistBot|YouBot|AndiBot|PhindBot|ExaBot|LinerBot|' .
		// Emerging LLMs.
		'DeepSeekBot|PanguBot|PetalBot|Groq-Bot|HuggingFace-Bot|' .
		// AI Data Scrapers.
		'Diffbot|AI2Bot|Ai2Bot-Dolma|FirecrawlAgent|Webzio-Extended|' .
		'omgili|ImagesiftBot|img2dataset|Timpibot|ICC-Crawler|' .
		// Enterprise AI.
		'SemrushBot-OCOB|QualifiedBot' .
	')/i';

	/**
	 * Settings manager instance.
	 *
	 * @var Settings_Interface
	 */
	private Settings_Interface $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings_Interface $settings Settings manager instance.
	 */
	public function __construct( Settings_Interface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Check if the current request is from an AI bot.
	 *
	 * Checks User-Agent against known bot patterns and Accept header
	 * for text/markdown requests.
	 *
	 * @return bool True if request is from an AI bot.
	 */
	public function is_ai_bot(): bool {
		// Get User-Agent header.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

		// Get Accept header.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ?? '' ) );

		// Check if client explicitly requests markdown.
		if ( false !== strpos( $accept, 'text/markdown' ) ) {
			return true;
		}

		// Match against compiled bot pattern.
		if ( preg_match( self::BOT_PATTERN, $user_agent ) ) {
			return true;
		}

		// Check custom bot patterns from settings.
		if ( $this->matches_custom_patterns( $user_agent ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the detected bot name.
	 *
	 * @return string|null Bot identifier or null if not a bot.
	 */
	public function get_bot_name(): ?string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );

		// Try to match known bot pattern.
		if ( preg_match( self::BOT_PATTERN, $user_agent, $matches ) ) {
			return $matches[1];
		}

		// Check for text/markdown Accept header.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ?? '' ) );

		if ( false !== strpos( $accept, 'text/markdown' ) ) {
			return 'markdown-client';
		}

		// Check custom patterns.
		$custom_match = $this->get_custom_match( $user_agent );
		if ( $custom_match ) {
			return $custom_match;
		}

		return null;
	}

	/**
	 * Check if User-Agent matches any custom patterns.
	 *
	 * @param string $user_agent User-Agent string to check.
	 * @return bool True if matches custom pattern.
	 */
	private function matches_custom_patterns( string $user_agent ): bool {
		$custom_bots = $this->settings->get( 'custom_bots', '' );

		if ( empty( $custom_bots ) ) {
			return false;
		}

		$patterns = $this->parse_custom_patterns( (string) $custom_bots );

		foreach ( $patterns as $pattern ) {
			if ( stripos( $user_agent, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the custom pattern that matched.
	 *
	 * @param string $user_agent User-Agent string to check.
	 * @return string|null Matching pattern or null.
	 */
	private function get_custom_match( string $user_agent ): ?string {
		$custom_bots = $this->settings->get( 'custom_bots', '' );

		if ( empty( $custom_bots ) ) {
			return null;
		}

		$patterns = $this->parse_custom_patterns( (string) $custom_bots );

		foreach ( $patterns as $pattern ) {
			if ( stripos( $user_agent, $pattern ) !== false ) {
				return 'custom:' . $pattern;
			}
		}

		return null;
	}

	/**
	 * Parse custom bot patterns from settings.
	 *
	 * @param string $custom_bots Custom patterns string (one per line).
	 * @return array<int, string> Array of patterns.
	 */
	private function parse_custom_patterns( string $custom_bots ): array {
		$lines = explode( "\n", $custom_bots );
		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}

	/**
	 * Get all known bot patterns as an array.
	 *
	 * Useful for display in admin or debugging.
	 *
	 * @return array<int, string> List of bot patterns.
	 */
	public static function get_known_patterns(): array {
		return [
			// OpenAI.
			'GPTBot',
			'ChatGPT',
			'OAI-SearchBot',
			'Operator',
			// Anthropic.
			'ClaudeBot',
			'Claude-Web',
			'Claude-User',
			'Claude-SearchBot',
			'anthropic-ai',
			// Google AI.
			'Google-Extended',
			'Google-CloudVertexBot',
			'GoogleAgent-Mariner',
			'Gemini-Deep-Research',
			'Google-NotebookLM',
			'GoogleOther',
			// Perplexity.
			'PerplexityBot',
			'Perplexity-User',
			// Meta AI.
			'Meta-ExternalAgent',
			'meta-externalagent',
			// Amazon AI.
			'Amazonbot',
			'NovaAct',
			// Apple AI.
			'Applebot-Extended',
			// Mistral.
			'MistralAI-User',
			// ByteDance.
			'Bytespider',
			// Cohere.
			'cohere-ai',
			'cohere-training-data-crawler',
			// Common Crawl.
			'CCBot',
			// AI Search Engines.
			'DuckAssistBot',
			'YouBot',
			'AndiBot',
			'PhindBot',
			'ExaBot',
			'LinerBot',
			// Emerging LLMs.
			'DeepSeekBot',
			'PanguBot',
			'PetalBot',
			'Groq-Bot',
			'HuggingFace-Bot',
			// AI Data Scrapers.
			'Diffbot',
			'AI2Bot',
			'Ai2Bot-Dolma',
			'FirecrawlAgent',
			'Webzio-Extended',
			'omgili',
			'ImagesiftBot',
			'img2dataset',
			'Timpibot',
			'ICC-Crawler',
			// Enterprise AI.
			'SemrushBot-OCOB',
			'QualifiedBot',
		];
	}
}
