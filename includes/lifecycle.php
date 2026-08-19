<?php
/**
 * Lightweight plugin lifecycle helpers.
 */

if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!class_exists('CBIA_Lifecycle')) {
    final class CBIA_Lifecycle
    {
        /**
         * Return every WP-Cron hook owned by the base runtime.
         *
         * All current producers schedule these hooks without arguments.
         *
         * @return string[]
         */
        public static function scheduled_event_hooks(): array
        {
            return array(
                'cbia_pending_fill_event',
                'cbia_generation_event',
                'cbia_oldposts_process_background_run',
            );
        }

        /**
         * Clear all pending instances of the plugin's known WP-Cron hooks.
         */
        public static function clear_scheduled_events(): void
        {
            if (!function_exists('wp_clear_scheduled_hook')) {
                return;
            }

            foreach (self::scheduled_event_hooks() as $hook) {
                wp_clear_scheduled_hook($hook);
            }
        }
    }
}
