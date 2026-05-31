<?php declare(strict_types=1);
namespace Gratis\Access;

final class Membership
{
    public static function register(): void
    {
        // Access levels taxonomy
        register_taxonomy('access_level', ['post', 'page', 'gratis_course', 'gratis_lesson'], [
            'labels' => ['name' => 'Access Levels'], 'public' => false,
            'show_ui' => true, 'show_in_rest' => true, 'hierarchical' => true,
        ]);

        // Default levels
        if (!term_exists('free', 'access_level')) {
            wp_insert_term('Free', 'access_level', ['slug' => 'free']);
            wp_insert_term('Premium', 'access_level', ['slug' => 'premium']);
            wp_insert_term('VIP', 'access_level', ['slug' => 'vip']);
        }

        // User access level meta
        register_meta('user', '_access_level', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
    }

    public static function restrictContent(string $content): string
    {
        if (is_admin() || !is_singular()) return $content;

        $post_id = get_the_ID();
        $required = wp_get_object_terms($post_id, 'access_level', ['fields' => 'slugs']);
        if (empty($required)) return $content; // No restriction

        if (!is_user_logged_in()) {
            return self::restrictedMessage('Please log in to view this content.');
        }

        $user_level = get_user_meta(get_current_user_id(), '_access_level', true) ?: 'free';
        $hierarchy = ['free' => 0, 'premium' => 1, 'vip' => 2];
        $userRank = $hierarchy[$user_level] ?? 0;
        $requiredRank = max(array_map(fn($l) => $hierarchy[$l] ?? 0, $required));

        if ($userRank < $requiredRank) {
            return self::restrictedMessage('This content requires a higher access level.');
        }

        return $content;
    }

    private static function restrictedMessage(string $msg): string
    {
        return '<div class="gratis-restricted" style="background:#1a1e2e;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:2rem;text-align:center;margin:2rem 0">'
            . '<p style="font-size:1.1rem;margin:0 0 1rem">🔒 ' . esc_html($msg) . '</p>'
            . '<a href="' . wp_login_url(get_permalink()) . '" style="color:#22C55E">Log in →</a>'
            . '</div>';
    }

    public static function grantAccess(int $userId, string $level): void
    {
        update_user_meta($userId, '_access_level', $level);
    }
}
