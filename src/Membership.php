<?php declare(strict_types=1);
namespace Gratis\Access;

defined('ABSPATH') || exit;

/**
 * Membership + content restriction engine.
 *
 * Restricts singular content by an `access_level` taxonomy term, comparing the
 * required level against the logged-in user's `_access_level` meta. Default
 * level and restriction copy are configurable via {@see Settings}.
 */
final class Membership {

    public const TAXONOMY = 'gratis_access_level';

    /** Ordered access-level hierarchy (slug => rank). */
    public const HIERARCHY = ['free' => 0, 'premium' => 1, 'vip' => 2];

    /** Register the access-level taxonomy, default terms, and user meta. */
    public static function register(): void {
        register_taxonomy(self::TAXONOMY, ['post', 'page', 'gratis_course', 'gratis_lesson'], [
            'labels'       => ['name' => __('Access Levels', 'gratis-access')],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]);

        if (!get_option('gratis_access_seeded')) {
            if (!term_exists('free', self::TAXONOMY)) {
                wp_insert_term('Free', self::TAXONOMY, ['slug' => 'free']);
                wp_insert_term('Premium', self::TAXONOMY, ['slug' => 'premium']);
                wp_insert_term('VIP', self::TAXONOMY, ['slug' => 'vip']);
            }
            update_option('gratis_access_seeded', 1, false);
        }

        register_meta('user', '_access_level', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_key',
            'auth_callback'     => static fn(): bool => current_user_can('manage_options'),
        ]);
    }

    /** Filter post content and excerpts (including REST API and RSS feeds) when restricted. */
    public static function restrictContent(string $content): string {
        $is_rest = defined('REST_REQUEST') && REST_REQUEST;
        if (is_admin() && !$is_rest) {
            return $content;
        }
        if (!$is_rest && !is_singular() && !is_feed()) {
            return $content;
        }

        $post_id = (int) get_the_ID();
        if ($post_id <= 0) {
            return $content;
        }
        if (current_user_can('edit_post', $post_id)) {
            return $content;
        }

        $required = wp_get_object_terms($post_id, self::TAXONOMY, ['fields' => 'slugs']);
        if (empty($required) || is_wp_error($required)) {
            return $content;
        }

        if (!is_user_logged_in()) {
            return self::restrictedMessage(Settings::loginMessage(), true);
        }

        $user_level   = self::userLevel(get_current_user_id());
        $userRank     = self::HIERARCHY[$user_level] ?? 0;
        $requiredRank = max(array_map(static fn($l) => self::HIERARCHY[$l] ?? 0, $required));

        if ($userRank < $requiredRank) {
            return self::restrictedMessage(Settings::upgradeMessage(), false);
        }

        return $content;
    }

    /** Resolve a user's effective access level, falling back to the default. */
    public static function userLevel(int $userId): string {
        $level = get_user_meta($userId, '_access_level', true);
        return is_string($level) && $level !== '' ? $level : Settings::defaultLevel();
    }

    /** Build the restricted-content notice markup. */
    private static function restrictedMessage(string $msg, bool $show_login = true): string {
        $html = '<div class="gratis-restricted" style="background:#1a1e2e;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:2rem;text-align:center;margin:2rem 0">'
            . '<p style="font-size:1.1rem;margin:0 0 1rem">🔒 ' . esc_html($msg) . '</p>';
        if ($show_login) {
            $html .= '<a href="' . esc_url(wp_login_url((string) get_permalink())) . '" style="color:#22C55E">' . esc_html__('Log in →', 'gratis-access') . '</a>';
        }
        return $html . '</div>';
    }

    /** Grant an access level to a user. */
    public static function grantAccess(int $userId, string $level): void {
        update_user_meta($userId, '_access_level', sanitize_key($level));
    }
}
