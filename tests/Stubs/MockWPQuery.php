<?php
/**
 * Mock WP_Query for testing.
 *
 * @package LLMSTXT_WP\Tests\Stubs
 */

declare(strict_types=1);

// Note: This file must be loaded early to define WP_Query before the plugin code runs.
// It's in the global namespace to replace WordPress's WP_Query.

if (!class_exists('WP_Query')) {
    /**
     * Mock WP_Query class for unit/integration testing.
     */
    class WP_Query
    {
        /**
         * Posts returned by query.
         *
         * @var array
         */
        public array $posts = [];

        /**
         * Number of posts.
         *
         * @var int
         */
        public int $post_count = 0;

        /**
         * Query vars.
         *
         * @var array
         */
        public array $query_vars = [];

        /**
         * Static storage for mock posts.
         *
         * @var array
         */
        private static array $mock_posts = [];

        /**
         * Constructor.
         *
         * @param array|string $query Query arguments.
         */
        public function __construct($query = '')
        {
            if (!empty($query)) {
                $this->query($query);
            }
        }

        /**
         * Run the query.
         *
         * @param array|string $query Query arguments.
         * @return array Posts.
         */
        public function query($query): array
        {
            $this->query_vars = is_array($query) ? $query : [];
            $this->posts = $this->filter_posts();
            $this->post_count = count($this->posts);
            return $this->posts;
        }

        /**
         * Filter mock posts based on query vars.
         *
         * @return array Filtered posts.
         */
        private function filter_posts(): array
        {
            $posts = self::$mock_posts;

            // Filter by post_type
            if (!empty($this->query_vars['post_type'])) {
                $types = (array) $this->query_vars['post_type'];
                $posts = array_filter($posts, function ($post) use ($types) {
                    return in_array($post->post_type, $types, true);
                });
            }

            // Filter by post_status
            if (!empty($this->query_vars['post_status'])) {
                $status = $this->query_vars['post_status'];
                $posts = array_filter($posts, function ($post) use ($status) {
                    return $post->post_status === $status;
                });
            }

            // Filter by name (slug)
            if (!empty($this->query_vars['name'])) {
                $name = $this->query_vars['name'];
                $posts = array_filter($posts, function ($post) use ($name) {
                    return $post->post_name === $name;
                });
            }

            // Apply limit
            if (!empty($this->query_vars['posts_per_page']) && $this->query_vars['posts_per_page'] > 0) {
                $posts = array_slice($posts, 0, (int) $this->query_vars['posts_per_page']);
            }

            return array_values($posts);
        }

        /**
         * Check if query has posts.
         *
         * @return bool
         */
        public function have_posts(): bool
        {
            return $this->post_count > 0;
        }

        /**
         * Register mock posts for testing.
         *
         * @param array $posts Array of WP_Post objects.
         */
        public static function set_mock_posts(array $posts): void
        {
            self::$mock_posts = $posts;
        }

        /**
         * Clear mock posts.
         */
        public static function clear_mock_posts(): void
        {
            self::$mock_posts = [];
        }

        /**
         * Add a single mock post.
         *
         * @param WP_Post $post Post to add.
         */
        public static function add_mock_post(WP_Post $post): void
        {
            self::$mock_posts[$post->ID] = $post;
        }
    }
}
