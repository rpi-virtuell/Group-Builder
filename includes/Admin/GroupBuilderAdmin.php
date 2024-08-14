<?php
namespace GroupBuilder\Admin;

class GroupBuilderAdmin {
    public function __construct() {
        $this->setup_admin_actions();
    }

    private function setup_admin_actions() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_admin_menu() {
        add_options_page(
            'Group Builder Settings',
            'Group Builder',
            'manage_options',
            'group-builder-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('group-builder-settings-group', 'group_builder_max_members');
        register_setting('group-builder-settings-group', 'group_builder_avatar_actions');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Group Builder Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('group-builder-settings-group'); ?>
                <?php do_settings_sections('group-builder-settings-group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Maximum Group Members</th>
                        <td><input type="number" name="group_builder_max_members" value="<?php echo esc_attr(get_option('group_builder_max_members', 10)); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Show Avatar Actions</th>
                        <td><input type="checkbox" name="group_builder_avatar_actions" value="1" <?php checked(1, get_option('group_builder_avatar_actions', 0), true); ?> /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
