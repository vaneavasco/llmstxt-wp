<?php
/**
 * Fake Settings Manager for testing.
 *
 * A simple test double that doesn't require Mockery.
 *
 * @package LLMSTXT_WP\Tests\Stubs
 */

declare(strict_types=1);

namespace LLMSTXT_WP\Tests\Stubs;

use LLMSTXT_WP\Core\Settings_Interface;

/**
 * Fake Settings Manager for unit tests.
 *
 * Implements Settings_Interface for use in tests.
 * This works around Mockery's inability to mock final classes.
 */
class FakeSettingsManager implements Settings_Interface
{
    /**
     * Settings storage.
     *
     * @var array<string, mixed>
     */
    private array $settings = [];

    /**
     * Constructor.
     *
     * @param array<string, mixed> $settings Initial settings.
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * Get a setting value.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed Setting value or default.
     */
    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a setting value.
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->settings[$key] = $value;
    }

    /**
     * Get all settings.
     *
     * @return array<string, mixed> All settings.
     */
    public function get_all(): array
    {
        return $this->settings;
    }

    /**
     * Get default settings.
     *
     * @return array<string, mixed> Default settings.
     */
    public function get_defaults(): array
    {
        return $this->settings;
    }

    /**
     * Register settings (no-op for tests).
     *
     * @return void
     */
    public function register_settings(): void
    {
        // No-op for testing.
    }
}
