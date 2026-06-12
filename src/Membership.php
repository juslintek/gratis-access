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

    /** Ordered access-level hierarchy (slug => rank). */
    public const HIERARCHY = ['free' => 0, 'premium' => 1, 'vip' => 2];

    /** Register the access-level taxonomy, default terms, and user meta. */
    public static function register(): void {
        register_taxonomy('access_level', ['post', 'page', 'gratis_course', 'gratis_lesson'], [
            'labels'       => ['name' => __('Access Levels', 'gratis-access')],
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]);

        if (!term_exists('free', 'access_level')) {
            wp_insert_term('Free', 'access_level', ['slug' => 'free']);
            wp_insert_term('Premium', 'access_level', ['slug' => 'premium']);
            wp_insert_term('VIP', 'access_level', ['slug' => 'vip']);
        }

        register_meta('user', '_access_level', [
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ]);
    }

    /** Filter post content, swapping in a restriction notice when required. */
    public static function restrictContent(string $content): string {
        if (is_admin() || !is_singular()) {
            return $content;
        }

        $post_id  = get_the_ID();
        $required = wp_get_object_terms($post_id, 'access_level', ['fields' => 'slugs']);
        if (empty($required) || is_wp_error($required)) {
            return $content;
        }

        if (!is_user_logged_in()) {
            return self::restrictedMessage(Settings::loginMessage());
        }

        $user_level   = self::userLevel(get_current_user_id());
        $userRank     = self::HIERARCHY[$user_level] ?? 0;
        $requiredRank = max(array_map(static fn($l) => self::HIERARCHY[$l] ?? 0, $required));

        if ($userRank < $requiredRank) {
            return self::restrictedMessage(Settings::upgradeMessage());
        }

        return $content;
    }

    /** Resolve a user's effective access level, falling back to the default. */
    public static function userLevel(int $userId): string {
        $level = get_user_meta($userId, '_access_level', true);
        return is_string($level) && $level !== '' ? $level : Settings::defaultLevel();
    }

    /** Build the restricted-content notice markup. */
    private static function restrictedMessage(string $msg): string {
        return '<div class="gratis-restricted" style="background:#1a1e2e;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:2rem;text-align:center;margin:2rem 0">'
            . '<p style="font-size:1.1rem;margin:0 0 1rem">🔒 ' . esc_html($msg) . '</p>'
            . '<a href="' . esc_url(wp_login_url(get_permalink())) . '" style="color:#22C55E">' . esc_html__('Log in →', 'gratis-access') . '</a>'
            . '</div>';
    }

    /** Grant an access level to a user. */
    public static function grantAccess(int $userId, string $level): void {
        update_user_meta($userId, '_access_level', $level);
    }
}
