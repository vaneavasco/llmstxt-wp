# LLMs.txt for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-5.9%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Implements the [llms.txt standard](https://llmstxt.org/) for WordPress** — like robots.txt, but for AI.

LLMs.txt for WordPress serves AI-optimized content endpoints, making your site easily discoverable and understandable by ChatGPT, Claude, Perplexity, and 40+ other AI crawlers.

## Features

- **`/llms.txt` Endpoint** — Serve a structured markdown listing of all your published content following the [llms.txt standard](https://llmstxt.org/)
- **AI Content Endpoints** — `/ai/content/{post_type}/{slug}` endpoints for individual posts in markdown format
- **AI Bot Detection** — Automatically detect 45+ AI crawlers (GPTBot, ClaudeBot, PerplexityBot, etc.)
- **Smart Redirects** — Optionally redirect AI bots to optimized markdown endpoints
- **Any Post Type** — Works with posts, pages, WooCommerce products, or any custom post type
- **Caching** — Built-in transient caching for optimal performance
- **Rate Limiting** — Protect your server from excessive requests
- **Extensible** — Filter hooks for customizing every aspect of the output

## Installation

### From GitHub

1. Download the latest release from the [Releases](https://github.com/vaneavasco/llmstxt-wp/releases) page
2. Upload to `/wp-content/plugins/llmstxt-wp`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings > LLMs.txt to configure

### Manual Installation

```bash
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/vaneavasco/llmstxt-wp.git
```

Then activate through WordPress admin.

## Configuration

Navigate to **Settings > LLMs.txt** in your WordPress admin.

### General Settings

| Setting | Description |
|---------|-------------|
| Enable Plugin | Master switch to enable/disable all features |
| Enable /llms.txt | Serve the llms.txt endpoint |
| Enable AI Endpoints | Enable /ai/content/* individual post endpoints |
| Redirect AI Bots | Automatically redirect detected AI bots to markdown versions |

### Content Settings

| Setting | Description |
|---------|-------------|
| Post Types | Select which post types to include (posts, pages, products, etc.) |
| Site Description | Custom description for llms.txt header |
| Contact Email | Contact email shown in llms.txt |

### Cache Settings

| Setting | Default | Description |
|---------|---------|-------------|
| llms.txt Cache TTL | 3600 (1 hour) | How long to cache the full listing |
| Content Cache TTL | 600 (10 minutes) | How long to cache individual posts |

### Advanced Settings

| Setting | Description |
|---------|-------------|
| Rate Limiting | Enable/disable and configure request limits |
| Noindex | Add X-Robots-Tag to prevent search engine indexing |
| Custom Bots | Add additional bot patterns to detect |

## Endpoints

### `/llms.txt`

Returns a markdown document listing all published content:

```markdown
# Your Site Name

> Your site description

> Website: https://yoursite.com
> Contact: admin@yoursite.com

---

## Posts

### 1. Your First Post
Post excerpt here...

- **Published:** January 1, 2025
- **Categories:** Category1, Category2
- **Author:** Author Name
- **Read More:** [https://yoursite.com/post-slug](https://yoursite.com/post-slug)

---

## Quick Links

- [Home](https://yoursite.com)
- [About](https://yoursite.com/about)
- [Contact](https://yoursite.com/contact)
```

### `/ai/content/{post_type}/{slug}`

Returns detailed markdown for a single post:

```markdown
# Post Title

Full post content converted to markdown...

---

## Details

- **Published:** January 1, 2025
- **Last Updated:** January 15, 2025
- **Author:** [Author Name](https://yoursite.com/author/name)
- **Categories:** Category1, Category2
- **Permalink:** [https://yoursite.com/slug](https://yoursite.com/slug)

---

*From [Your Site Name](https://yoursite.com)*
```

## Detected AI Bots

The plugin automatically detects 45+ AI crawlers including:

- **OpenAI:** GPTBot, ChatGPT, OAI-SearchBot
- **Anthropic:** ClaudeBot, Claude-Web, Claude-SearchBot
- **Google:** Google-Extended, Gemini-Deep-Research
- **Perplexity:** PerplexityBot, Perplexity-User
- **Meta:** Meta-ExternalAgent
- **Amazon:** Amazonbot, NovaAct
- **And many more...**

## Extensibility

### Filter Hooks

```php
// Modify the final llms.txt content
add_filter( 'aico_llms_txt_content', function( $content, $posts ) {
    return $content . "\n\n## Custom Section\n\nYour content here.";
}, 10, 2 );

// Add/remove post types
add_filter( 'aico_llms_txt_post_types', function( $post_types ) {
    $post_types[] = 'product'; // Add WooCommerce products
    return $post_types;
} );

// Customize individual post entries
add_filter( 'aico_post_entry', function( $entry, $post, $index ) {
    // Add custom metadata
    return $entry;
}, 10, 3 );

// Provide custom static page content
add_filter( 'aico_static_page_content', function( $content, $page_type ) {
    if ( 'pricing' === $page_type ) {
        return "# Pricing\n\nYour pricing info...";
    }
    return $content;
}, 10, 2 );
```

### Action Hooks

```php
// When AI bot is detected
add_action( 'aico_bot_detected', function( $bot_name ) {
    error_log( "AI bot detected: {$bot_name}" );
} );

// After cache is cleared
add_action( 'aico_cache_cleared', function( $count ) {
    error_log( "Cleared {$count} cached items" );
} );

// When rate limit is exceeded
add_action( 'aico_rate_limited', function( $ip, $count, $max ) {
    error_log( "Rate limit exceeded for {$ip}" );
}, 10, 3 );
```

## WooCommerce Integration Example

```php
// Add products to llms.txt
add_filter( 'aico_llms_txt_post_types', function( $post_types ) {
    if ( class_exists( 'WooCommerce' ) ) {
        $post_types[] = 'product';
    }
    return $post_types;
} );

// Add price to product entries
add_filter( 'aico_post_entry', function( $entry, $post, $index ) {
    if ( 'product' !== $post->post_type ) {
        return $entry;
    }

    $product = wc_get_product( $post->ID );
    if ( $product ) {
        $price = strip_tags( $product->get_price_html() );
        $entry = str_replace(
            '- **Read More:**',
            "- **Price:** {$price}\n- **Read More:**",
            $entry
        );
    }

    return $entry;
}, 10, 3 );
```

## Requirements

- WordPress 5.9 or higher
- PHP 7.4 or higher (8.1+ recommended)

## Development

### Prerequisites

- Docker and Docker Compose
- PHP 8.1+ (for local development without Docker)
- Composer

### Setup

```bash
# Clone the repository
git clone https://github.com/vaneavasco/llmstxt-wp.git
cd llmstxt-wp

# Install dependencies
composer install
```

### Running Tests

The test suite uses PHPUnit with Brain Monkey for WordPress function mocking.

```bash
# Run all tests (unit + integration) via Docker
docker-compose run --rm test

# Run only unit tests
docker-compose run --rm test vendor/bin/phpunit --testsuite Unit

# Run only integration tests
docker-compose run --rm test vendor/bin/phpunit --testsuite Integration

# Run with testdox output (human-readable)
docker-compose run --rm test vendor/bin/phpunit --testdox

# Run a specific test file
docker-compose run --rm test vendor/bin/phpunit tests/Unit/Core/BotDetectorTest.php

# Run tests locally (without Docker, requires PHP 8.1+)
composer test
```

### Test Structure

```
tests/
├── bootstrap.php                 # Test setup, loads WP mocks
├── Unit/                         # Fast unit tests (no WP dependencies)
│   └── Core/
│       ├── BotDetectorTest.php
│       ├── CacheManagerTest.php
│       └── RateLimiterTest.php
├── Integration/                  # Integration tests (mocked WP functions)
│   ├── WP_Integration_TestCase.php  # Base class with WP stubs
│   ├── Content/
│   │   ├── ContentFetcherIntegrationTest.php
│   │   └── MarkdownGeneratorIntegrationTest.php
│   ├── Endpoints/
│   │   └── EndpointManagerIntegrationTest.php
│   └── PluginIntegrationTest.php
└── Stubs/
    ├── FakeSettingsManager.php   # Test double for settings
    ├── MockWPPost.php            # Mock WP_Post class
    └── MockWPQuery.php           # Mock WP_Query class
```

### Code Style

```bash
# Run PHP CodeSniffer
composer lint

# Auto-fix coding standards
composer lint:fix
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Run the test suite (`docker-compose run --rm test`)
4. Commit your changes (`git commit -m 'Add some amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

- Implements the [llms.txt standard](https://llmstxt.org/)
- AI bot patterns sourced from [darkvisitors.com](https://darkvisitors.com) and [ai-robots-txt](https://github.com/ai-robots-txt/ai.robots.txt)

## Support

- [GitHub Issues](https://github.com/vaneavasco/llmstxt-wp/issues)
- [Documentation](https://github.com/vaneavasco/llmstxt-wp/wiki)
