<?php
/**
 * Plugin Name: WP Admin Focus Mode
 * Plugin URI: https://github.com/TABARC-Code/wp-admin-focus-mode
 * Description: Lets me put specific roles into a focused WordPress admin view with a simplified menu and optional custom landing page.
 * Version: 1.0.0
 * Author: TABARC-Code
 * Author URI: https://github.com/TABARC-Code
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright (c) 2025 TABARC-Code
 * Original work by TABARC-Code.
 * You may modify and redistribute this software under the terms of
 * the GNU General Public License version 3 or (at your option) any later version.
 * You must preserve this notice and clearly state any changes you make.
 *
 * My goal with this plugin is simple:
 * I want to stop clients wandering into Settings, Plugins and random screens they do not need,
 * and give them a calmer admin experience without building a whole new dashboard.
 *
 * TODO: add per user overrides so I can give specific people a wider or narrower view than their role.
 * TODO: add a simple "Focus mode on or off" switch per user profile.
 * FIXME: might want a quick bypass mechanism for myself via a query parameter if I lock something too hard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    // If someone tries to load this file directly, I am not playing along.
    exit;
}

class WP_Admin_Focus_Mode {

    private $option_name = 'wp_admin_focus_mode_settings';

    public function __construct() {
        // Settings UI.
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Core behaviour hooks.
        add_action( 'admin_menu', array( $this, 'filter_admin_menu' ), 999 );
        add_filter( 'login_redirect', array( $this, 'handle_login_redirect' ), 10, 3 );
        add_action( 'admin_init', array( $this, 'maybe_tidy_admin_chrome' ) );

        // Brand integration. I want my icon showing up in the plugins list so I can recognise my work at a glance.
        add_action( 'admin_head-plugins.php', array( $this, 'inject_plugin_list_icon_css' ) );
    }

    /**
     * Central place for the brand icon URL.
     *
     * I am using an SVG in .branding so every project can share the same convention.
     */
    private function get_brand_icon_url() {
        return plugin_dir_url( __FILE__ ) . '.branding/tabarc-icon.svg';
    }

    /**
     * Default settings so I am not guessing at array keys later.
     */
    private function get_default_settings() {
        return array(
            // Per role focus switches and optional landing URLs.
            'roles' => array(
                // Example structure:
                // 'editor' => array(
                //     'enabled'  => true,
                //     'redirect' => 'edit.php',
                // ),
            ),
            // These are the menu slugs I will keep when focus mode kicks in.
            // I always keep profile.php even if it is not listed here, because people need to change their password.
            'allowed_menu_slugs' => array(
                'index.php',                // Dashboard.
                'edit.php',                 // Posts.
                'edit.php?post_type=page',  // Pages.
            ),
            // Light admin chrome cleanup, for example hiding the help tab and screen options.
            'tidy_chrome' => true,
        );
    }

    /**
     * Fetch settings with defaults merged in.
     */
    private function get_settings() {
        $defaults = $this->get_default_settings();
        $stored   = get_option( $this->option_name );

        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        // Simple merge, stored values win where present.
        $settings = array_merge( $defaults, $stored );

        // Make sure roles is always an array.
        if ( empty( $settings['roles'] ) || ! is_array( $settings['roles'] ) ) {
            $settings['roles'] = array();
        }

        if ( empty( $settings['allowed_menu_slugs'] ) || ! is_array( $settings['allowed_menu_slugs'] ) ) {
            $settings['allowed_menu_slugs'] = $defaults['allowed_menu_slugs'];
        }

        $settings['tidy_chrome'] = ! empty( $settings['tidy_chrome'] );

        return $settings;
    }

    /**
     * Get the roles that exist on this site.
     *
     * I use this to present a simple table where I can toggle focus per role.
     */
    private function get_all_roles() {
        if ( ! function_exists( 'wp_roles' ) ) {
            return array();
        }

        $wp_roles = wp_roles();

        if ( ! $wp_roles ) {
            return array();
        }

        return $wp_roles->roles;
    }

    /**
     * Work out if the current user is in a role that has focus mode enabled.
     */
    private function get_current_focus_role_config() {
        if ( ! is_user_logged_in() ) {
            return null;
        }

        $user     = wp_get_current_user();
        $settings = $this->get_settings();

        if ( empty( $user->roles ) || ! is_array( $user->roles ) ) {
            return null;
        }

        foreach ( $user->roles as $role ) {
            if ( isset( $settings['roles'][ $role ] ) && ! empty( $settings['roles'][ $role ]['enabled'] ) ) {
                // I return the specific role config that matched.
                return array(
                    'role'     => $role,
                    'config'   => $settings['roles'][ $role ],
                    'settings' => $settings,
                );
            }
        }

        return null;
    }

    public function add_settings_page() {
        add_options_page(
            'Admin Focus Mode',
            'Admin Focus Mode',
            'manage_options',
            'wp-admin-focus-mode',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting(
            $this->option_name,
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitise everything that comes from the settings form.
     */
    public function sanitize_settings( $input ) {
        $defaults  = $this->get_default_settings();
        $sanitized = $defaults;

        // Roles config.
        $sanitized['roles'] = array();

        if ( isset( $input['roles'] ) && is_array( $input['roles'] ) ) {
            foreach ( $input['roles'] as $role_key => $role_data ) {
                $role_key = sanitize_key( $role_key );

                if ( empty( $role_key ) ) {
                    continue;
                }

                $enabled  = ! empty( $role_data['enabled'] );
                $redirect = '';
                if ( isset( $role_data['redirect'] ) ) {
                    // I accept either relative admin paths or full URLs. I am not going to over police this.
                    $redirect = trim( $role_data['redirect'] );
                }

                $sanitized['roles'][ $role_key ] = array(
                    'enabled'  => $enabled,
                    'redirect' => $redirect,
                );
            }
        }

        // Allowed menu slugs.
        $sanitized['allowed_menu_slugs'] = array();
        if ( isset( $input['allowed_menu_slugs'] ) && is_array( $input['allowed_menu_slugs'] ) ) {
            foreach ( $input['allowed_menu_slugs'] as $slug ) {
                $slug = trim( (string) $slug );
                if ( $slug === '' ) {
                    continue;
                }
                // These slug strings come directly from the menu array, so I just trim and keep them.
                $sanitized['allowed_menu_slugs'][] = $slug;
            }
        }

        if ( empty( $sanitized['allowed_menu_slugs'] ) ) {
            // If someone empties everything, I fall back to defaults so I do not leave people with a blank menu.
            $sanitized['allowed_menu_slugs'] = $defaults['allowed_menu_slugs'];
        }

        $sanitized['tidy_chrome'] = ! empty( $input['tidy_chrome'] );

        return $sanitized;
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-admin-focus-mode' ) );
        }

        $settings = $this->get_settings();
        $roles    = $this->get_all_roles();

        // I am going to grab the current admin menu list so I can show slugs and labels in one place.
        global $menu;
        // admin.php has already populated $menu by the time this runs.

        $menu_items = array();
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                // $item[2] is the slug, $item[0] is the label.
                $slug  = isset( $item[2] ) ? $item[2] : '';
                $label = isset( $item[0] ) ? wp_strip_all_tags( $item[0] ) : $slug;

                if ( $slug ) {
                    $menu_items[ $slug ] = $label;
                }
            }
        }
        ?>
        <div class="wrap">
            <h1>Admin Focus Mode</h1>
            <p>
                I use this to give specific roles a simplified admin view. The idea is:
                pick which roles get focus mode and what they should see, then let them work without the clutter.
            </p>

            <form method="post" action="options.php">
                <?php settings_fields( $this->option_name ); ?>

                <h2>Roles in focus mode</h2>
                <p>Tick the roles that should see a simplified admin and optionally set their landing page.</p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Enable focus mode</th>
                            <th>Landing page after login (optional)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $roles as $role_key => $role_data ) : ?>
                        <?php
                        $role_settings = isset( $settings['roles'][ $role_key ] ) ? $settings['roles'][ $role_key ] : array();
                        $enabled       = ! empty( $role_settings['enabled'] );
                        $redirect      = isset( $role_settings['redirect'] ) ? $role_settings['redirect'] : '';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $role_data['name'] ); ?></strong><br>
                                <code><?php echo esc_html( $role_key ); ?></code>
                            </td>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $this->option_name ); ?>[roles][<?php echo esc_attr( $role_key ); ?>][enabled]"
                                           value="1" <?php checked( $enabled, true ); ?>>
                                    Put this role into focus mode
                                </label>
                            </td>
                            <td>
                                <input type="text"
                                       class="regular-text"
                                       name="<?php echo esc_attr( $this->option_name ); ?>[roles][<?php echo esc_attr( $role_key ); ?>][redirect]"
                                       value="<?php echo esc_attr( $redirect ); ?>"
                                       placeholder="Example: edit.php or /wp-admin/edit.php">
                                <p class="description">
                                    If set, I will send users with this role here after login.
                                    If empty, WordPress keeps its default behaviour.
                                </p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="margin-top:2em;">Allowed menu items</h2>
                <p>
                    Here I decide which main menu items survive when focus mode is active.
                    I always keep the profile screen even if it is not ticked.
                </p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Show in focus mode</th>
                            <th>Menu item</th>
                            <th>Slug</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $menu_items as $slug => $label ) : ?>
                        <?php
                        $checked = in_array( $slug, $settings['allowed_menu_slugs'], true );
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( $this->option_name ); ?>[allowed_menu_slugs][]"
                                       value="<?php echo esc_attr( $slug ); ?>"
                                    <?php checked( $checked, true ); ?>>
                            </td>
                            <td><?php echo esc_html( $label ); ?></td>
                            <td><code><?php echo esc_html( $slug ); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="margin-top:2em;">Admin chrome cleanup</h2>
                <label>
                    <input type="checkbox"
                           name="<?php echo esc_attr( $this->option_name ); ?>[tidy_chrome]"
                           value="1" <?php checked( $settings['tidy_chrome'], true ); ?>>
                    Hide screen options and help tabs for users in focus mode
                </label>
                <p class="description">
                    This keeps the interface less intimidating. Admins keep the full view. Focus roles see less clutter. Less stress or whats that do type client response
                </p>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Remove menu items that are not on the allowed list for focus roles.
     *
     * I run this late on admin_menu so I can see the full menu and carve away the noise.
     */
    public function filter_admin_menu() {
        $focus = $this->get_current_focus_role_config();
        if ( ! $focus ) {
            return;
        }

        $settings           = $focus['settings'];
        $allowed_menu_slugs = $settings['allowed_menu_slugs'];

        global $menu, $submenu;

        if ( ! is_array( $menu ) ) {
            return;
        }

        // I always allow Profile.
        if ( ! in_array( 'profile.php', $allowed_menu_slugs, true ) ) {
            $allowed_menu_slugs[] = 'profile.php';
        }

        foreach ( $menu as $index => $item ) {
            if ( ! isset( $item[2] ) ) {
                continue;
            }

            $slug = $item[2];

            // If this slug is not in the allowed list, I remove it.
            if ( ! in_array( $slug, $allowed_menu_slugs, true ) ) {
                unset( $menu[ $index ] );
                if ( isset( $submenu[ $slug ] ) ) {
                    unset( $submenu[ $slug ] );
                }
            }
        }
    }

    /**
     * Handle login redirect for focus roles.
     *
     * If a role has a redirect configured, I use it. Otherwise I respect whatever WordPress chose. ususallyy
     */
    public function handle_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( ! $user || is_wp_error( $user ) ) {
            return $redirect_to;
        }

        if ( empty( $user->roles ) || ! is_array( $user->roles ) ) {
            return $redirect_to;
        }

        $settings = $this->get_settings();

        foreach ( $user->roles as $role ) {
            if ( isset( $settings['roles'][ $role ] ) && ! empty( $settings['roles'][ $role ]['enabled'] ) ) {
                $role_config = $settings['roles'][ $role ];
                if ( ! empty( $role_config['redirect'] ) ) {
                    $target = trim( $role_config['redirect'] );

                    // If this looks like a relative admin path, I build the full URL. Otherwise I trust the admin.
                    if ( strpos( $target, 'http://' ) === 0 || strpos( $target, 'https://' ) === 0 ) {
                        return esc_url_raw( $target );
                    }

                    return admin_url( ltrim( $target, '/' ) );
                }
                break;
            }
        }

        return $redirect_to;
    }

    /**
     * Optionally tidy the admin chrome for focus roles.
     *
     * I am not doing anything heavy here. Just hiding a few superficial controls to make the UI less noisy.
     */
    public function maybe_tidy_admin_chrome() {
        $focus = $this->get_current_focus_role_config();
        if ( ! $focus ) {
            return;
        }

        $settings = $focus['settings'];

        if ( empty( $settings['tidy_chrome'] ) ) {
            return;
        }

        // Hide the Screen Options and Help tabs.
        add_filter( 'screen_options_show_screen', '__return_false' );
        add_filter( 'contextual_help', array( $this, 'hide_help_tab' ), 999, 3 );

        // Trim the admin bar slightly for focus users.
        add_action( 'wp_before_admin_bar_render', array( $this, 'tidy_admin_bar' ) );
    }

    public function hide_help_tab( $old_help, $screen_id, $screen ) {
        if ( method_exists( $screen, 'remove_help_tabs' ) ) {
            $screen->remove_help_tabs();
        }
        return $old_help;
    }

    /**
     * Strip some admin bar items for focus roles.
     *
     * I keep this conservative. The idea is calm, not punishment.
     */
    public function tidy_admin_bar() {
        if ( ! is_admin_bar_showing() ) {
            return;
        }

        global $wp_admin_bar;

        if ( ! $wp_admin_bar instanceof WP_Admin_Bar ) {
            return;
        }

        // Remove the WordPress logo menu, updates and comments for focus users.
        $wp_admin_bar->remove_node( 'wp-logo' );
        $wp_admin_bar->remove_node( 'updates' );
        $wp_admin_bar->remove_node( 'comments' );
    }

    /**
     * Injects CSS into the plugins list so my icon shows next to this plugin.
     *
     * This is purely cosmetic. It just helps me spot my work in a long list of plugins.
     */
    public function inject_plugin_list_icon_css() {
        $icon_url = esc_url( $this->get_brand_icon_url() );
        ?>
        <style>
            .wp-list-table.plugins tr[data-slug="wp-admin-focus-mode"] .plugin-title strong::before {
                content: '';
                display: inline-block;
                vertical-align: middle;
                width: 18px;
                height: 18px;
                margin-right: 6px;
                background-image: url('<?php echo $icon_url; ?>');
                background-repeat: no-repeat;
                background-size: contain;
            }
        </style>
        <?php
    }
}

new WP_Admin_Focus_Mode();
