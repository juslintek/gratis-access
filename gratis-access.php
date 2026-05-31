<?php
/**
 * Plugin Name: Gratis Access
 * Description: Membership + content restriction. Drip content, access levels. Replaces MemberPress ($179/yr).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';
add_action('init', [\Gratis\Access\Membership::class, 'register']);
add_filter('the_content', [\Gratis\Access\Membership::class, 'restrictContent']);
add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) \WP_CLI::add_command('gratis-access', \Gratis\Access\CLI\AccessCommand::class);
});
