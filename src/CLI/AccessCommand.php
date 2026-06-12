<?php declare(strict_types=1);
namespace Gratis\Access\CLI;

use Gratis\Access\Membership;

final class AccessCommand {

    /**
     * Grant access level to a user.
     * <user_id> : User ID.
     * <level> : Access level (free, premium, vip).
     * @when after_wp_load
     */
    public function grant($args, $assoc_args): void {
        unset($assoc_args);
        Membership::grantAccess((int) $args[0], (string) $args[1]);
        \WP_CLI::success("User #{$args[0]} granted '{$args[1]}' access.");
    }

    /**
     * List users by access level.
     * [--level=<level>] : Filter by level.
     * @when after_wp_load
     */
    public function users($args, $assoc_args): void {
        unset($args);
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded CLI lookup.
        $meta_query = !empty($assoc_args['level']) ? [['key' => '_access_level', 'value' => sanitize_key($assoc_args['level'])]] : [];
        $users = get_users(['meta_query' => $meta_query, 'number' => 50]);
        $rows = array_map(fn($u) => [
            'ID' => $u->ID, 'Email' => $u->user_email,
            'Level' => get_user_meta($u->ID, '_access_level', true) ?: 'free',
        ], $users);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Email', 'Level']);
    }
}
