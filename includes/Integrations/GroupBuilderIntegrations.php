<?php
namespace GroupBuilder\Integrations;

class GroupBuilderIntegrations {
    public function __construct() {
        $this->setup_integrations();
        add_filter('pre_get_comments', [$this,'filter_comments_by_post_type']);
        add_action( 'user_register', [$this,'set_new_user_pending'] );
    }

    public function set_new_user_pending($user_id) {
        if(!get_option('options_set_new_user_pending', true)){
            return;
        }
        update_user_meta( $user_id, 'account_status', 'awaiting_admin_review');
        // alle Admins benachrichtigen
        $admin_emails = [];
        $admins = get_users(['role' => 'administrator']);
        foreach ($admins as $admin) {
            $admin_emails[] = $admin->user_email;
        }
        $admin_emails = implode(',', $admin_emails);
        $url = admin_url('users.php');

        wp_mail($admin_emails, '['.get_bloginfo('name').'] Neue Benutzer:in registriert',
            'Eine neue Benutzer:in hat sich registriert und wartet auf Freischaltung. Bitte überprüfe die Benutzer:in und schalte sie frei.'
            ."\n"
            .'Benutzername: '.get_userdata($user_id)->user_login
            ."\n"
            .'Zur Administrationsseite: '.$url
        );

    }

    private function setup_integrations() {
        add_filter('um_profile_tabs', [$this, 'ultimate_member_integration_tab'], 1000);
        add_filter('um_user_profile_tabs', [$this, 'ultimate_member_integration_tab'], 1000);
        add_action('um_profile_content_group-builder', [$this, 'ultimate_member_integration_content']);
        add_filter('um_profile_query_make_posts', [$this, 'ultimate_member_integration_profile_query_make_posts'], 10, 1);
        add_action( 'um_members_after_user_name', [$this, 'ultimate_member_meta'], 10 );
    }

    public function filter_comments_by_post_type($query) {
        if (!is_admin() && !is_singular('group_post')) {
            $post_types = array('pinwall_post', 'post', 'document_post'); // Hier die gewünschten Post-Types eintragen
            $query->query_vars['post_type'] = $post_types;
        }
        return $query;
    }

    /**
     * @param $user_id
     * @return void
     * Display the user meta data in the members list
     * @todo select set meta_keys to display in optionspage
     */
    public function ultimate_member_meta($user_id) {
        echo '<div class="um_member_meta">';
        $bio = get_user_meta( $user_id, 'description', true );
        if(!empty($bio)) {
            $bio = wp_trim_words($bio, 8, "...").". ";
            echo $bio;
        }
        $meta_keys = get_option('use_um_meta_keys',['schulform']);
        foreach ($meta_keys as $meta_key) {
            $meta_value = get_user_meta( $user_id, $meta_key, true );
            if(is_array($meta_value)) {
                $output = implode(', ', $meta_value);
            }else{
                $output = $meta_value;
            }

            if(!empty($meta_value)) {
                echo '<div class="um_member_meta_'.$meta_key.'">'.$output.'</div>';
            }
        }
        echo '</div>';

    }
    public function ultimate_member_integration_tab($tabs) {
        $tabs['group-builder'] = array(
            'name' => 'Gruppen',
            'icon' => 'um-icon-android-people',
            'custom' => true,
        );
        $custom_tab = \UM()->options()->get('profile_tab_group-builder');
        if ('' === $custom_tab) {
            \UM()->options()->update('profile_tab_group-builder', true);
        }
        return $tabs;
    }

    public function ultimate_member_integration_content() {
        $current_user_id = get_current_user_id();
        $args = array(
            'post_type' => 'group_post',
            'meta_query' => array(
                array(
                    'key' => '_group_members',
                    'value' => $current_user_id,
                    'compare' => 'LIKE'
                )
            )
        );
        $groups = get_posts($args);
        echo '<div class="um-group-list"><strong>Mitglied in folgenden Gruppen:</strong><br><br>';
        foreach ($groups as $group) {
            echo
            '<div class="assoziated-group-link">' .
            '<a href="'.get_permalink($group->ID).'"><span class="dashicons dashicons-groups"></span>'.$group->post_title.'</a>' .
            '<br>' .
            '<div class="group-goal">' . get_post_meta($group->ID, 'group_goal', true) . '</div>' .
            '<hr>'.
            '</div>';
        }
        echo '</ul>';
    }

    public function ultimate_member_integration_profile_query_make_posts($args) {
        $args['post_type'] = 'pinwall_post';
        return $args;
    }
}
