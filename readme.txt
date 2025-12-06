=== LLMs.txt for WordPress ===
Contributors: vaneavasco
Tags: llms.txt, ai, seo, llm, chatgpt, claude, perplexity, markdown, geo
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serve AI-optimized content to ChatGPT, Claude, Perplexity and 40+ AI crawlers. Implements the llms.txt standard.

== Description ==

LLMs.txt for WordPress implements the [llms.txt standard](https://llmstxt.org/) — like robots.txt, but for AI. Make your WordPress content easily discoverable and understandable by ChatGPT, Claude, Perplexity, and other AI-powered systems.

= Features =

* **/llms.txt Endpoint** - Serve a structured markdown listing of all your published content following the [llms.txt standard](https://llmstxt.org/)
* **AI Content Endpoints** - `/ai/content/{post_type}/{slug}` endpoints for individual posts in markdown format
* **AI Bot Detection** - Automatically detect 45+ AI crawlers (GPTBot, ClaudeBot, PerplexityBot, etc.)
* **Smart Redirects** - Optionally redirect AI bots to optimized markdown endpoints
* **Any Post Type** - Works with posts, pages, WooCommerce products, or any custom post type
* **Caching** - Built-in transient caching for optimal performance
* **Rate Limiting** - Protect your server from excessive requests
* **Extensible** - Filter hooks for customizing every aspect of the output

= Why llms.txt? =

As AI-powered search engines become more prevalent, providing AI-readable content is becoming as important as traditional SEO. LLMs.txt for WordPress helps you:

* Make your content easily discoverable by AI systems
* Provide clean, structured markdown that AI can understand
* Control how AI systems access and interpret your content
* Monitor AI crawler activity on your site

= Detected AI Bots =

The plugin automatically detects 45+ AI crawlers including:

* **OpenAI:** GPTBot, ChatGPT, OAI-SearchBot
* **Anthropic:** ClaudeBot, Claude-Web, Claude-SearchBot
* **Google:** Google-Extended, Gemini-Deep-Research
* **Perplexity:** PerplexityBot, Perplexity-User
* **Meta:** Meta-ExternalAgent
* **Amazon:** Amazonbot, NovaAct
* And many more...

= Endpoints =

**`/llms.txt`**

Returns a markdown document listing all published content with metadata including titles, excerpts, dates, categories, and authors.

**`/ai/content/{post_type}/{slug}`**

Returns detailed markdown for a single post including full content, metadata, and navigation links.

== Installation ==

1. Upload the `llmstxt-wp` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > LLMs.txt to configure

= Manual Installation =

1. Download the plugin zip file
2. Navigate to Plugins > Add New > Upload Plugin
3. Choose the downloaded file and click Install Now
4. Activate the plugin

== Frequently Asked Questions ==

= What is llms.txt? =

llms.txt is a proposed standard for providing AI-friendly content summaries, similar to how robots.txt works for search engines. Learn more at [llmstxt.org](https://llmstxt.org/).

= Will this affect my regular visitors? =

No. The plugin only serves markdown content to AI bots or when explicitly requested via the `/llms.txt` or `/ai/content/` endpoints. Regular visitors see your site normally.

= Does this work with WooCommerce? =

Yes! You can enable products in the settings, and the plugin will include them in your llms.txt and provide individual product endpoints.

= Can I customize the output? =

Yes. The plugin provides numerous filter hooks to customize every aspect of the output. See the [documentation](https://github.com/vaneavasco/llmstxt-wp) for examples.

= How does caching work? =

The plugin uses WordPress transients for caching. Default cache times are 1 hour for llms.txt and 10 minutes for individual posts. You can adjust these in settings or clear the cache manually.

= What about rate limiting? =

Rate limiting protects your server from excessive requests. By default, it allows 60 requests per minute per IP address. You can adjust or disable this in settings.

== Screenshots ==

1. Settings page - General settings for enabling features
2. Content settings - Configure which post types to include
3. Cache settings - Adjust cache durations
4. Advanced settings - Rate limiting and custom bot patterns
5. Example llms.txt output

== Changelog ==

= 1.0.0 =
* Initial release
* /llms.txt endpoint following the llmstxt.org standard
* /ai/content/{post_type}/{slug} endpoints for individual posts
* Detection of 45+ AI crawlers
* Optional bot redirect to markdown endpoints
* Support for all post types
* Transient-based caching
* Rate limiting
* Comprehensive admin settings page
* Filter and action hooks for extensibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of LLMs.txt for WordPress.

== Privacy Policy ==

LLMs.txt for WordPress does not collect, store, or transmit any personal data. The plugin operates entirely on your WordPress installation.

Rate limiting uses IP addresses stored temporarily in WordPress transients. These are automatically cleared and are not transmitted to any external service.

== Credits ==

* Implements the [llms.txt standard](https://llmstxt.org/)
* AI bot patterns sourced from [darkvisitors.com](https://darkvisitors.com) and [ai-robots-txt](https://github.com/ai-robots-txt/ai.robots.txt)
