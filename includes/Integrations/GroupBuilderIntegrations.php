<?php
namespace GroupBuilder\Integrations;

class GroupBuilderIntegrations {
    public function __construct() {
        $this->setup_integrations();
    }

    private function setup_integrations() {
        add_filter('um_profile_tabs', [$this, 'ultimate_member_integration_tab'], 1000);
        add_filter('um_user_profile_tabs', [$this, 'ultimate_member_integration_tab'], 1000);
        add_action('um_profile_content_group-builder', [$this, 'ultimate_member_integration_content']);
        add_filter('um_profile_query_make_posts', [$this, 'ultimate_member_integration_profile_query_make_posts'], 10, 1);
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
        echo '<h3>Mitglied in folgenden Gruppen</h3>';
        echo '<ul>';
        foreach ($groups as $group) {
            echo '<li><a href="' . get_permalink($group->ID) . '">' . $group->post_title . '</a></li>';
        }
        echo '</ul>';
    }

    public function ultimate_member_integration_profile_query_make_posts($args) {
        $args['post_type'] = 'pinwall_post';
        return $args;
    }
}
