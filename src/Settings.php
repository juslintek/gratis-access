<?php declare(strict_types=1);

namespace Gratis\Access;

defined('ABSPATH') || exit;

/**
 * Admin settings + tools for Gratis Access.
 *
 * Configures the default access level and restriction copy, and provides a
 * nonce-gated tool to grant an access level to a user by ID.
 */
final class Settings {

    private const GROUP = 'gratis_access_settings_group';
    private const PAGE  = 'gratis-access';

    private const OPT_DEFAULT = 'gratis_access_default_level';
    private const OPT_LOGIN   = 'gratis_access_login_message';
    private const OPT_UPGRADE = 'gratis_access_upgrade_message';

    /** Register the settings submenu page. */
    public static function register_menu(): void {
        add_options_page(
            __('Gratis Access', 'gratis-access'),
            __('Gratis Access', 'gratis-access'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /** Register options with typed sanitisers. */
    public static function register_settings(): void {
        register_setting(self::GROUP, self::OPT_DEFAULT, [
            'type'              => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_level'],
            'default'           => 'free',
        ]);
        register_setting(self::GROUP, self::OPT_LOGIN, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Please log in to view this content.',
        ]);
        register_setting(self::GROUP, self::OPT_UPGRADE, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'This content requires a higher access level.',
        ]);
    }

    /** Whitelist the default level against the known hierarchy. */
    public static function sanitize_level($value): string {
        $value = sanitize_key((string) $value);
        return isset(Membership::HIERARCHY[$value]) ? $value : 'free';
    }

    /** Register the grant-access admin-post handler. */
    public static function register_actions(): void {
        add_action('admin_post_gratis_access_grant', [__CLASS__, 'handle_grant']);
    }

    /** Configured default access level. */
    public static function defaultLevel(): string {
        $level = (string) get_option(self::OPT_DEFAULT, 'free');
        return isset(Membership::HIERARCHY[$level]) ? $level : 'free';
    }

    /** Message shown to logged-out visitors. */
    public static function loginMessage(): string {
        return (string) get_option(self::OPT_LOGIN, 'Please log in to view this content.');
    }

    /** Message shown when the user's level is too low. */
    public static function upgradeMessage(): string {
        return (string) get_option(self::OPT_UPGRADE, 'This content requires a higher access level.');
    }

    /** Grant an access level to a user (nonce + capability gated). */
    public static function handle_grant(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'gratis-access'));
        }
        check_admin_referer('gratis_access_grant');

        // Nonce + capability verified above; inputs sanitised before use.
        $user_id = isset($_POST['user_id']) ? absint(wp_unslash($_POST['user_id'])) : 0;
        $level   = isset($_POST['level']) ? sanitize_key(wp_unslash($_POST['level'])) : 'free';
        $level   = isset(Membership::HIERARCHY[$level]) ? $level : 'free';

        $ok = '0';
        if ($user_id > 0 && get_userdata($user_id)) {
            Membership::grantAccess($user_id, $level);
            $ok = '1';
        }

        wp_safe_redirect(add_query_arg(
            ['page' => self::PAGE, 'gratis_access_granted' => $ok],
            admin_url('options-general.php')
        ));
        exit;
    }

    /** Render the admin screen. */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-access'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gratis Access', 'gratis-access') . '</h1>';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        if (isset($_GET['gratis_access_granted'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
            $granted = '1' === sanitize_key(wp_unslash($_GET['gratis_access_granted']));
            $class   = $granted ? 'notice-success' : 'notice-error';
            $text    = $granted
                ? __('Access level updated.', 'gratis-access')
                : __('Could not update access level — check the user ID.', 'gratis-access');
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
        }

        self::render_options_form();
        self::render_grant_form();

        echo '</div>';
    }

    /** Default level + restriction copy form. */
    private static function render_options_form(): void {
        echo '<h2>' . esc_html__('Restriction settings', 'gratis-access') . '</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="ga_default">' . esc_html__('Default access level', 'gratis-access') . '</label></th><td>';
        echo '<select id="ga_default" name="' . esc_attr(self::OPT_DEFAULT) . '">';
        $current = self::defaultLevel();
        foreach (array_keys(Membership::HIERARCHY) as $slug) {
            echo '<option value="' . esc_attr($slug) . '"' . selected($current, $slug, false) . '>' . esc_html(ucfirst($slug)) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Level assigned to users without an explicit one.', 'gratis-access') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ga_login">' . esc_html__('Logged-out message', 'gratis-access') . '</label></th><td>';
        echo '<input type="text" id="ga_login" class="large-text" name="' . esc_attr(self::OPT_LOGIN) . '" value="' . esc_attr(self::loginMessage()) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ga_upgrade">' . esc_html__('Upgrade-required message', 'gratis-access') . '</label></th><td>';
        echo '<input type="text" id="ga_upgrade" class="large-text" name="' . esc_attr(self::OPT_UPGRADE) . '" value="' . esc_attr(self::upgradeMessage()) . '">';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save settings', 'gratis-access'));
        echo '</form>';
    }

    /** Grant-access-to-user tool. */
    private static function render_grant_form(): void {
        echo '<h2>' . esc_html__('Grant access', 'gratis-access') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gratis_access_grant');
        echo '<input type="hidden" name="action" value="gratis_access_grant">';
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="ga_user">' . esc_html__('User ID', 'gratis-access') . '</label></th><td>';
        echo '<input type="number" min="1" id="ga_user" class="small-text" name="user_id" value="">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ga_level">' . esc_html__('Access level', 'gratis-access') . '</label></th><td>';
        echo '<select id="ga_level" name="level">';
        foreach (array_keys(Membership::HIERARCHY) as $slug) {
            echo '<option value="' . esc_attr($slug) . '">' . esc_html(ucfirst($slug)) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button(__('Grant access', 'gratis-access'), 'secondary', 'submit', false);
        echo '</form>';
    }
}
