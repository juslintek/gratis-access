<?php
/**
 * Plugin Name: Gratis Access
 * Plugin URI: https://github.com/juslintek/gratis-access
 * Description: Membership + content restriction. Access levels, drip content, and per-user gating.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-access
 */

defined('ABSPATH') || exit;

define('GRATIS_ACCESS_VERSION', '1.0.0');
define('GRATIS_ACCESS_FILE', __FILE__);
define('GRATIS_ACCESS_DIR', plugin_dir_path(__FILE__));
define('GRATIS_ACCESS_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('init', [\Gratis\Access\Membership::class, 'register']);
add_filter('the_content', [\Gratis\Access\Membership::class, 'restrictContent']);

add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-access', \Gratis\Access\CLI\AccessCommand::class);
    }
});

// Admin settings page + grant-access action.
add_action('admin_menu', [\Gratis\Access\Settings::class, 'register_menu']);
add_action('admin_init', [\Gratis\Access\Settings::class, 'register_settings']);
\Gratis\Access\Settings::register_actions();
