<?php
/**
 * Mock WP_Post for testing.
 *
 * @package LLMSTXT_WP\Tests\Stubs
 */

declare(strict_types=1);

// Note: This file must be loaded early to define WP_Post before the plugin code runs.
// It's in the global namespace to replace WordPress's WP_Post.

if (!class_exists('WP_Post')) {
    /**
     * Mock WP_Post class for unit/integration testing.
     *
     * Mimics the essential properties of WordPress's WP_Post class.
     */
    class WP_Post
    {
        /**
         * Post ID.
         *
         * @var int
         */
        public int $ID = 0;

        /**
         * Post author.
         *
         * @var int
         */
        public int $post_author = 0;

        /**
         * Post date.
         *
         * @var string
         */
        public string $post_date = '';

        /**
         * Post date GMT.
         *
         * @var string
         */
        public string $post_date_gmt = '';

        /**
         * Post content.
         *
         * @var string
         */
        public string $post_content = '';

        /**
         * Post title.
         *
         * @var string
         */
        public string $post_title = '';

        /**
         * Post excerpt.
         *
         * @var string
         */
        public string $post_excerpt = '';

        /**
         * Post status.
         *
         * @var string
         */
        public string $post_status = 'publish';

        /**
         * Comment status.
         *
         * @var string
         */
        public string $comment_status = 'open';

        /**
         * Ping status.
         *
         * @var string
         */
        public string $ping_status = 'open';

        /**
         * Post password.
         *
         * @var string
         */
        public string $post_password = '';

        /**
         * Post name (slug).
         *
         * @var string
         */
        public string $post_name = '';

        /**
         * To ping.
         *
         * @var string
         */
        public string $to_ping = '';

        /**
         * Pinged.
         *
         * @var string
         */
        public string $pinged = '';

        /**
         * Post modified date.
         *
         * @var string
         */
        public string $post_modified = '';

        /**
         * Post modified date GMT.
         *
         * @var string
         */
        public string $post_modified_gmt = '';

        /**
         * Post content filtered.
         *
         * @var string
         */
        public string $post_content_filtered = '';

        /**
         * Post parent.
         *
         * @var int
         */
        public int $post_parent = 0;

        /**
         * GUID.
         *
         * @var string
         */
        public string $guid = '';

        /**
         * Menu order.
         *
         * @var int
         */
        public int $menu_order = 0;

        /**
         * Post type.
         *
         * @var string
         */
        public string $post_type = 'post';

        /**
         * Post MIME type.
         *
         * @var string
         */
        public string $post_mime_type = '';

        /**
         * Comment count.
         *
         * @var int
         */
        public int $comment_count = 0;

        /**
         * Filter.
         *
         * @var string
         */
        public string $filter = 'raw';

        /**
         * Constructor.
         *
         * @param object|array $post Post data.
         */
        public function __construct($post = null)
        {
            if ($post !== null) {
                $post = is_array($post) ? (object) $post : $post;

                foreach (get_object_vars($post) as $key => $value) {
                    if (property_exists($this, $key)) {
                        $this->$key = $value;
                    }
                }
            }
        }

        /**
         * Convert to array.
         *
         * @return array
         */
        public function to_array(): array
        {
            return get_object_vars($this);
        }
    }
}
