<?php
/**
 * Plugin Deactivator
 *
 * @package ImmoBridge
 * @subpackage Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Core;

/**
 * Plugin Deactivator
 *
 * Handles plugin deactivation tasks such as clearing scheduled events
 * and flushing rewrite rules.
 *
 * @since 1.0.0
 */
final class Deactivator
{
    /**
     * Deactivate the plugin
     */
    public function deactivate(): void
    {
        $this->clearScheduledEvents();
        $this->clearTransients();
        $this->flushRewriteRules();
        
        // Set deactivation flag
        update_option('immobridge_deactivated', true);
        update_option('immobridge_deactivation_time', time());
        
        do_action('immobridge_deactivated');
    }

    /**
     * Clear all scheduled events
     */
    private function clearScheduledEvents(): void
    {
        $events = [
            'immobridge_cleanup_imports',
            'immobridge_cleanup_cache',
            'immobridge_auto_import',
        ];

        foreach ($events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
        }

        // Clear all scheduled events for this plugin
        wp_clear_scheduled_hook('immobridge_cleanup_imports');
        wp_clear_scheduled_hook('immobridge_cleanup_cache');
        wp_clear_scheduled_hook('immobridge_auto_import');
    }

    /**
     * Clear plugin transients and cache
     */
    private function clearTransients(): void
    {
        global $wpdb;

        // Delete all plugin-specific transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_immobridge_%',
                '_transient_timeout_immobridge_%'
            )
        );

        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('immobridge');
        }
    }

    /**
     * Flush rewrite rules
     */
    private function flushRewriteRules(): void
    {
        flush_rewrite_rules();
    }
}
