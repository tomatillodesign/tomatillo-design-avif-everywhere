<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Load Action Scheduler if it's not already loaded elsewhere (e.g. WooCommerce).
 */
if ( ! class_exists( 'ActionScheduler' ) ) {
	require_once __DIR__ . '/action-scheduler/action-scheduler.php';
}
