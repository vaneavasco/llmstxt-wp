<?php
/**
 * Markdown Generator.
 *
 * Converts WordPress content to LLM-friendly markdown format.
 * Handles HTML to markdown conversion and content formatting.
 *
 * @package LLMSTXT_WP
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Content;

use LLMSTXT_WP\Core\Settings_Interface;
use WP_Post;

/**
 * Markdown Generator class.
 *
 * Transforms WordPress posts into structured markdown optimized
 * for consumption by AI crawlers and language models.
 */
final class Markdown_Generator {

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
	 * Generate full llms.txt content.
	 *
	 * @param array<int, WP_Post> $posts Array of WP_Post objects.
	 * @return string Complete markdown content.
	 */
	public function generate_llms_txt( array $posts ): string {
		// Prefetch all term relationships to avoid N+1 queries.
		$this->prefetch_post_terms( $posts );

		$site_name        = get_bloginfo( 'name' );
		$site_description = $this->get_site_description();
		$site_url         = home_url();
		$contact_email    = $this->get_contact_email();

		$lines = [];

		// Header section.
		$lines[] = "# {$site_name}";
		$lines[] = '';
		if ( $site_description ) {
			$lines[] = "> {$site_description}";
			$lines[] = '';
		}
		$lines[] = "> Website: {$site_url}";
		$lines[] = "> Contact: {$contact_email}";
		$lines[] = '';
		$lines[] = '---';
		$lines[] = '';

		// Group posts by type.
		$grouped = [];
		foreach ( $posts as $post ) {
			$type_label = $this->get_post_type_label( $post->post_type );
			if ( ! isset( $grouped[ $type_label ] ) ) {
				$grouped[ $type_label ] = [];
			}
			$grouped[ $type_label ][] = $post;
		}

		// Generate sections for each post type.
		foreach ( $grouped as $type_label => $type_posts ) {
			$lines[] = "## {$type_label}";
			$lines[] = '';

			$index = 1;
			foreach ( $type_posts as $post ) {
				$lines[] = $this->generate_post_entry( $post, $index );
				++$index;
			}
		}

		// Footer with important links.
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '## Quick Links';
		$lines[] = '';
		$lines[] = "- [Home]({$site_url})";

		// Add common pages if they exist.
		$common_pages = [ 'about', 'contact', 'faq' ];
		foreach ( $common_pages as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				$page_url   = get_permalink( $page );
				$page_title = get_the_title( $page );
				$lines[]    = "- [{$page_title}]({$page_url})";
			}
		}

		$lines[] = '';

		$content = implode( "\n", $lines );

		/**
		 * Filter the complete llms.txt content.
		 *
		 * @param string $content The generated markdown.
		 * @param array  $posts   The posts included.
		 */
		return apply_filters( 'aico_llms_txt_content', $content, $posts );
	}

	/**
	 * Generate entry for a single post in listing.
	 *
	 * @param WP_Post $post  Post object.
	 * @param int     $index Position in list.
	 * @return string Markdown entry.
	 */
	public function generate_post_entry( WP_Post $post, int $index ): string {
		$lines = [];
		$url   = get_permalink( $post );
		$title = $this->clean_text( get_the_title( $post ) );

		$lines[] = "### {$index}. {$title}";
		$lines[] = '';

		// Excerpt or truncated content.
		$excerpt = $this->get_excerpt( $post, 300 );
		if ( $excerpt ) {
			$lines[] = $excerpt;
			$lines[] = '';
		}

		// Metadata.
		$lines[] = '- **Published:** ' . get_the_date( 'F j, Y', $post );

		// Categories/taxonomies.
		$terms = $this->get_post_terms( $post );
		if ( $terms ) {
			$lines[] = '- **Categories:** ' . $terms;
		}

		// Author.
		$author = get_the_author_meta( 'display_name', $post->post_author );
		if ( $author ) {
			$lines[] = "- **Author:** {$author}";
		}

		$lines[] = "- **Read More:** [{$url}]({$url})";
		$lines[] = '';

		$entry = implode( "\n", $lines );

		/**
		 * Filter single post entry in llms.txt.
		 *
		 * @param string  $entry The markdown entry.
		 * @param WP_Post $post  The post object.
		 * @param int     $index Position in list.
		 */
		return apply_filters( 'aico_post_entry', $entry, $post, $index );
	}

	/**
	 * Generate detailed markdown for a single post.
	 *
	 * @param WP_Post $post Post object.
	 * @return string Full markdown content.
	 */
	public function generate_post_detail( WP_Post $post ): string {
		$lines = [];
		$url   = get_permalink( $post );
		$title = $this->clean_text( get_the_title( $post ) );

		$lines[] = "# {$title}";
		$lines[] = '';

		// Full content converted to markdown.
		$content = $this->html_to_markdown( apply_filters( 'the_content', $post->post_content ) );
		if ( $content ) {
			$lines[] = $content;
			$lines[] = '';
		}

		// Metadata section.
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '## Details';
		$lines[] = '';
		$lines[] = '- **Published:** ' . get_the_date( 'F j, Y', $post );
		$lines[] = '- **Last Updated:** ' . get_the_modified_date( 'F j, Y', $post );

		$author = get_the_author_meta( 'display_name', $post->post_author );
		if ( $author ) {
			$author_url = get_author_posts_url( $post->post_author );
			$lines[]    = "- **Author:** [{$author}]({$author_url})";
		}

		$terms = $this->get_post_terms( $post );
		if ( $terms ) {
			$lines[] = '- **Categories:** ' . $terms;
		}

		$lines[] = "- **Permalink:** [{$url}]({$url})";
		$lines[] = '';

		// Featured image.
		if ( has_post_thumbnail( $post ) ) {
			$image_url = get_the_post_thumbnail_url( $post, 'full' );
			$image_alt = get_post_meta( get_post_thumbnail_id( $post ), '_wp_attachment_image_alt', true );
			$lines[]   = '## Featured Image';
			$lines[]   = '';
			$lines[]   = "![{$image_alt}]({$image_url})";
			$lines[]   = '';
		}

		// Footer.
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();
		$lines[]   = '---';
		$lines[]   = '';
		$lines[]   = "*From [{$site_name}]({$site_url})*";

		$markdown = implode( "\n", $lines );

		/**
		 * Filter single post detail markdown.
		 *
		 * @param string  $markdown The markdown content.
		 * @param WP_Post $post     The post object.
		 */
		return apply_filters( 'aico_post_detail', $markdown, $post );
	}

	/**
	 * Convert HTML to markdown.
	 *
	 * Handles common HTML elements including tables, lists, and code blocks.
	 *
	 * @param string $html HTML content.
	 * @return string Markdown content.
	 */
	public function html_to_markdown( string $html ): string {
		// Strip scripts, styles, and comments first.
		$html = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $html ) ?? $html;
		$html = preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html ) ?? $html;
		$html = preg_replace( '/<!--.*?-->/s', '', $html ) ?? $html;

		// Convert tables before stripping tags.
		$html = $this->convert_tables( $html );

		// Convert definition lists.
		$html = $this->convert_definition_lists( $html );

		// Convert figure/figcaption.
		$html = preg_replace_callback(
			'/<figure[^>]*>(.*?)<\/figure>/is',
			[ $this, 'convert_figure' ],
			$html
		) ?? $html;

		// Convert headings.
		for ( $i = 6; $i >= 1; $i-- ) {
			$hashes = str_repeat( '#', $i );
			$html   = preg_replace(
				"/<h{$i}[^>]*>(.*?)<\/h{$i}>/is",
				"\n{$hashes} $1\n",
				$html
			) ?? $html;
		}

		// Convert paragraphs.
		$html = preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $html ) ?? $html;

		// Convert line breaks.
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html ) ?? $html;

		// Convert bold.
		$html = preg_replace( '/<strong[^>]*>(.*?)<\/strong>/is', '**$1**', $html ) ?? $html;
		$html = preg_replace( '/<b[^>]*>(.*?)<\/b>/is', '**$1**', $html ) ?? $html;

		// Convert italic.
		$html = preg_replace( '/<em[^>]*>(.*?)<\/em>/is', '*$1*', $html ) ?? $html;
		$html = preg_replace( '/<i[^>]*>(.*?)<\/i>/is', '*$1*', $html ) ?? $html;

		// Convert strikethrough.
		$html = preg_replace( '/<del[^>]*>(.*?)<\/del>/is', '~~$1~~', $html ) ?? $html;
		$html = preg_replace( '/<s[^>]*>(.*?)<\/s>/is', '~~$1~~', $html ) ?? $html;

		// Convert links.
		$html = preg_replace( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $html ) ?? $html;

		// Convert images with various attribute orders.
		$html = preg_replace( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*\/?>/is', '![$2]($1)', $html ) ?? $html;
		$html = preg_replace( '/<img[^>]+alt=["\']([^"\']*)["\'][^>]*src=["\']([^"\']+)["\'][^>]*\/?>/is', '![$1]($2)', $html ) ?? $html;
		$html = preg_replace( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*\/?>/is', '![]($1)', $html ) ?? $html;

		// Convert ordered lists (with numbers).
		$html = preg_replace_callback(
			'/<ol[^>]*>(.*?)<\/ol>/is',
			[ $this, 'convert_ordered_list' ],
			$html
		) ?? $html;

		// Convert unordered lists.
		$html = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $html ) ?? $html;
		$html = preg_replace( '/<\/?ul[^>]*>/i', "\n", $html ) ?? $html;

		// Convert blockquotes (handle nested content).
		$html = preg_replace_callback(
			'/<blockquote[^>]*>(.*?)<\/blockquote>/is',
			[ $this, 'convert_blockquote' ],
			$html
		) ?? $html;

		// Convert code blocks with language detection.
		$html = preg_replace_callback(
			'/<pre[^>]*><code[^>]*class=["\'].*?language-([^"\'>\s]+)["\'][^>]*>(.*?)<\/code><\/pre>/is',
			static function ( array $matches ): string {
				$lang = $matches[1];
				$code = html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' );
				return "\n```{$lang}\n" . trim( $code ) . "\n```\n";
			},
			$html
		) ?? $html;

		// Convert remaining pre/code blocks.
		$html = preg_replace_callback(
			'/<pre[^>]*>(.*?)<\/pre>/is',
			static function ( array $matches ): string {
				$code = html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
				$code = preg_replace( '/<\/?code[^>]*>/i', '', $code ) ?? $code;
				return "\n```\n" . trim( $code ) . "\n```\n";
			},
			$html
		) ?? $html;

		// Convert inline code.
		$html = preg_replace( '/<code[^>]*>(.*?)<\/code>/is', '`$1`', $html ) ?? $html;

		// Convert horizontal rules.
		$html = preg_replace( '/<hr[^>]*\/?>/i', "\n---\n", $html ) ?? $html;

		// Convert abbreviations to text with title.
		$html = preg_replace( '/<abbr[^>]+title=["\']([^"\']+)["\'][^>]*>(.*?)<\/abbr>/is', '$2 ($1)', $html ) ?? $html;

		// Convert mark/highlight.
		$html = preg_replace( '/<mark[^>]*>(.*?)<\/mark>/is', '==$1==', $html ) ?? $html;

		// Strip remaining HTML.
		$html = wp_strip_all_tags( $html );

		// Decode HTML entities.
		$html = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );

		// Clean up whitespace.
		$html = preg_replace( '/\n{3,}/', "\n\n", $html ) ?? $html;
		$html = preg_replace( '/[ \t]+/', ' ', $html ) ?? $html;
		$html = preg_replace( '/^ +/m', '', $html ) ?? $html;

		return trim( $html );
	}

	/**
	 * Convert HTML tables to markdown.
	 *
	 * @param string $html HTML content.
	 * @return string HTML with tables converted to markdown.
	 */
	protected function convert_tables( string $html ): string {
		return preg_replace_callback(
			'/<table[^>]*>(.*?)<\/table>/is',
			[ $this, 'convert_single_table' ],
			$html
		) ?? $html;
	}

	/**
	 * Convert a single HTML table to markdown.
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string Markdown table.
	 */
	protected function convert_single_table( array $matches ): string {
		$table_html = $matches[1];
		$rows       = [];
		$has_header = false;

		// Extract thead rows.
		if ( preg_match( '/<thead[^>]*>(.*?)<\/thead>/is', $table_html, $thead_match ) ) {
			$has_header  = true;
			$header_rows = $this->extract_table_rows( $thead_match[1], true );
			$rows        = array_merge( $rows, $header_rows );
		}

		// Extract tbody rows.
		if ( preg_match( '/<tbody[^>]*>(.*?)<\/tbody>/is', $table_html, $tbody_match ) ) {
			$body_rows = $this->extract_table_rows( $tbody_match[1], false );
			$rows      = array_merge( $rows, $body_rows );
		} else {
			// No tbody, extract rows directly.
			$direct_rows = $this->extract_table_rows( $table_html, ! $has_header );
			$rows        = array_merge( $rows, $direct_rows );
		}

		if ( empty( $rows ) ) {
			return '';
		}

		// Build markdown table.
		$markdown  = "\n";
		$col_count = 0;

		foreach ( $rows as $index => $row ) {
			$col_count = max( $col_count, count( $row['cells'] ) );
			$markdown .= '| ' . implode( ' | ', $row['cells'] ) . " |\n";

			// Add separator after header row.
			if ( 0 === $index && ( $row['is_header'] || $has_header ) ) {
				$separator = array_fill( 0, count( $row['cells'] ), '---' );
				$markdown .= '| ' . implode( ' | ', $separator ) . " |\n";
			}
		}

		return $markdown . "\n";
	}

	/**
	 * Extract rows from table HTML.
	 *
	 * @param string $html           Table row HTML.
	 * @param bool   $first_is_header Whether first row should be treated as header.
	 * @return array<int, array{is_header: bool, cells: array<int, string>}> Extracted rows.
	 */
	protected function extract_table_rows( string $html, bool $first_is_header ): array {
		$rows = [];

		preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/is', $html, $tr_matches );

		foreach ( $tr_matches[1] as $index => $tr_content ) {
			$cells     = [];
			$is_header = ( 0 === $index && $first_is_header );

			// Check for th cells.
			if ( preg_match_all( '/<th[^>]*>(.*?)<\/th>/is', $tr_content, $th_matches ) ) {
				$is_header = true;
				foreach ( $th_matches[1] as $cell ) {
					$cells[] = $this->clean_text( $cell );
				}
			}

			// Check for td cells.
			if ( preg_match_all( '/<td[^>]*>(.*?)<\/td>/is', $tr_content, $td_matches ) ) {
				foreach ( $td_matches[1] as $cell ) {
					$cells[] = $this->clean_text( $cell );
				}
			}

			if ( ! empty( $cells ) ) {
				$rows[] = [
					'is_header' => $is_header,
					'cells'     => $cells,
				];
			}
		}

		return $rows;
	}

	/**
	 * Convert definition lists to markdown.
	 *
	 * @param string $html HTML content.
	 * @return string HTML with definition lists converted.
	 */
	protected function convert_definition_lists( string $html ): string {
		return preg_replace_callback(
			'/<dl[^>]*>(.*?)<\/dl>/is',
			static function ( array $matches ): string {
				$content = $matches[1];
				$result  = "\n";

				// Extract dt/dd pairs.
				preg_match_all( '/<dt[^>]*>(.*?)<\/dt>/is', $content, $dt_matches );
				preg_match_all( '/<dd[^>]*>(.*?)<\/dd>/is', $content, $dd_matches );

				$dt_count = count( $dt_matches[1] );
				$dd_count = count( $dd_matches[1] );

				for ( $i = 0; $i < $dt_count; $i++ ) {
					$term    = wp_strip_all_tags( $dt_matches[1][ $i ] );
					$result .= "**{$term}**\n";

					if ( isset( $dd_matches[1][ $i ] ) ) {
						$definition = wp_strip_all_tags( $dd_matches[1][ $i ] );
						$result    .= ": {$definition}\n\n";
					}
				}

				return $result;
			},
			$html
		) ?? $html;
	}

	/**
	 * Convert figure/figcaption to markdown.
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string Markdown representation.
	 */
	protected function convert_figure( array $matches ): string {
		$content = $matches[1];
		$result  = "\n";

		// Extract image.
		if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*\/?>/is', $content, $img_match ) ) {
			$result .= "![{$img_match[2]}]({$img_match[1]})\n";
		} elseif ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*\/?>/is', $content, $img_match ) ) {
			$result .= "![]({$img_match[1]})\n";
		}

		// Extract caption.
		if ( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/is', $content, $caption_match ) ) {
			$caption = wp_strip_all_tags( $caption_match[1] );
			$result .= "*{$caption}*\n";
		}

		return $result . "\n";
	}

	/**
	 * Convert ordered list to markdown with numbers.
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string Markdown ordered list.
	 */
	protected function convert_ordered_list( array $matches ): string {
		$content = $matches[1];
		$result  = "\n";
		$index   = 1;

		preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $content, $li_matches );

		foreach ( $li_matches[1] as $item ) {
			$item_text = $this->clean_text( $item );
			$result   .= "{$index}. {$item_text}\n";
			++$index;
		}

		return $result . "\n";
	}

	/**
	 * Convert blockquote to markdown with proper line prefixes.
	 *
	 * @param array<int, string> $matches Regex matches.
	 * @return string Markdown blockquote.
	 */
	protected function convert_blockquote( array $matches ): string {
		$content = $matches[1];

		// Strip nested tags and get text.
		$content = wp_strip_all_tags( $content );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
		$content = trim( $content );

		// Prefix each line with >.
		$lines  = explode( "\n", $content );
		$result = "\n";

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$result .= "> {$line}\n";
			}
		}

		return $result . "\n";
	}

	/**
	 * Clean text for markdown.
	 *
	 * Uses multibyte string functions to properly handle UTF-8 characters.
	 *
	 * @param string   $text       Text to clean.
	 * @param int|null $max_length Maximum length in characters (optional).
	 * @return string Cleaned text.
	 */
	public function clean_text( string $text, ?int $max_length = null ): string {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		$text = trim( $text );

		if ( $max_length && mb_strlen( $text, 'UTF-8' ) > $max_length ) {
			$text = mb_substr( $text, 0, $max_length, 'UTF-8' );
			// Cut at last complete word.
			$last_space = mb_strrpos( $text, ' ', 0, 'UTF-8' );
			if ( false !== $last_space && $last_space > $max_length * 0.8 ) {
				$text = mb_substr( $text, 0, $last_space, 'UTF-8' );
			}
			$text .= '...';
		}

		return $text;
	}

	/**
	 * Get post excerpt.
	 *
	 * @param WP_Post $post       Post object.
	 * @param int     $max_length Maximum length.
	 * @return string Excerpt.
	 */
	protected function get_excerpt( WP_Post $post, int $max_length = 300 ): string {
		if ( $post->post_excerpt ) {
			return $this->clean_text( $post->post_excerpt, $max_length );
		}
		return $this->clean_text( $post->post_content, $max_length );
	}

	/**
	 * Get post terms as comma-separated string.
	 *
	 * @param WP_Post $post Post object.
	 * @return string|null Terms string or null.
	 */
	protected function get_post_terms( WP_Post $post ): ?string {
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		$terms      = [];

		foreach ( $taxonomies as $taxonomy ) {
			// Skip post formats and internal taxonomies.
			if ( 'post_format' === $taxonomy || 0 === strpos( $taxonomy, '_' ) ) {
				continue;
			}

			$post_terms = get_the_terms( $post, $taxonomy );
			if ( $post_terms && ! is_wp_error( $post_terms ) ) {
				foreach ( $post_terms as $term ) {
					$terms[] = $term->name;
				}
			}
		}

		return ! empty( $terms ) ? implode( ', ', array_unique( $terms ) ) : null;
	}

	/**
	 * Get human-readable post type label.
	 *
	 * @param string $post_type Post type slug.
	 * @return string Post type label.
	 */
	protected function get_post_type_label( string $post_type ): string {
		$post_type_obj = get_post_type_object( $post_type );
		return $post_type_obj ? $post_type_obj->labels->name : ucfirst( $post_type );
	}

	/**
	 * Get site description from settings or WordPress.
	 *
	 * @return string Site description.
	 */
	protected function get_site_description(): string {
		$description = $this->settings->get( 'site_description', '' );
		if ( empty( $description ) ) {
			$description = get_bloginfo( 'description' );
		}
		return (string) $description;
	}

	/**
	 * Get contact email from settings or WordPress.
	 *
	 * @return string Contact email.
	 */
	protected function get_contact_email(): string {
		$email = $this->settings->get( 'contact_email', '' );
		if ( empty( $email ) ) {
			$email = get_option( 'admin_email' );
		}
		return (string) $email;
	}

	/**
	 * Prefetch all term relationships for posts to avoid N+1 queries.
	 *
	 * This loads all term data into WordPress object cache with a single
	 * database query per taxonomy, instead of one query per post per taxonomy.
	 *
	 * @param array<int, WP_Post> $posts Array of WP_Post objects.
	 * @return void
	 */
	protected function prefetch_post_terms( array $posts ): void {
		if ( empty( $posts ) ) {
			return;
		}

		// Collect all post IDs.
		$post_ids = wp_list_pluck( $posts, 'ID' );

		// Collect all unique taxonomies from all post types.
		$taxonomies = [];
		foreach ( $posts as $post ) {
			$post_taxonomies = get_object_taxonomies( $post->post_type, 'names' );
			foreach ( $post_taxonomies as $taxonomy ) {
				// Skip internal taxonomies.
				if ( 'post_format' !== $taxonomy && 0 !== strpos( $taxonomy, '_' ) ) {
					$taxonomies[ $taxonomy ] = true;
				}
			}
		}

		if ( empty( $taxonomies ) ) {
			return;
		}

		// Prefetch all term relationships in a single batch query.
		// This populates the WordPress object cache so subsequent
		// get_the_terms() calls hit the cache instead of the database.
		update_object_term_cache( $post_ids, array_keys( $taxonomies ) );
	}
}
