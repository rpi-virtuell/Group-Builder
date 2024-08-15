<?php
namespace GroupBuilder\Traits;

trait GroupBuilderHelperTrait {
    public function update_change_timestamp($post_id) {
        update_post_meta($post_id, '_avatar_list_updated', time());
    }

    public function get_group_members($group_id) {
        return get_post_meta($group_id, '_group_members', true) ?: array();
    }

    public function check_group_limit($group_id) {
        $members = $this->get_group_members($group_id);
        $max_members = apply_filters('group_builder_max_members', get_option('group_builder_max_members', 10));
        return count($members) < $max_members;
    }

    public function get_invite_link($group_id = null) {
        if (!$group_id) {
            $group_id = get_the_ID();
        }
        if (!$group_id) {
            return 'Fehler: Gruppen-ID nicht gefunden';
        }

        $hash = get_post_meta($group_id, '_invite_hash', true);
        if (empty($hash)) {
            $hash = md5(uniqid(rand(), true));
            update_post_meta($group_id, '_invite_hash', $hash);
        }

        return get_permalink($group_id) . '?invite=' . $hash;
    }
    public function get_avatar_list($post_id = null) {
        $do_print_output = false;
        $has_groups = false;
        $output = '';

        $post_type = get_post_type($post_id);
        if($post_type !== 'group_post' && $post_type !== 'pinwall_post') {
            return null;
        }
        if($post_type === 'group_post'){
            $group_id = $post_id;
            $members = get_post_meta($post_id, '_group_members', true);
        } else {
            $assoziated_group_ids = get_post_meta($post_id, '_associated_group');
            $group_section = '';
            if(is_array($assoziated_group_ids) && !empty($assoziated_group_ids)){
                $has_groups = true;
                $group_section .= '<div class="assoziated-groups"><p class="assoziated-groups-label">Unsere Gruppe arbeiten bereits dazu:</p>';
                foreach($assoziated_group_ids as $assoziated_group_id){
                    $group_section .= '<li class="assoziated-group-link"><a href="'.get_permalink($assoziated_group_id).'"><span class="dashicons dashicons-groups"></span>'.get_the_title($assoziated_group_id).'</a></li>';
                }
                $group_section .= '</div>';
            } else {
                $assoziated_group_ids = false;
            }

            $members = get_post_meta($post_id, '_interested_users', true);
        }
        if (!$post_id) {
            $post_id = get_the_ID();
            $do_print_output = true;
            $output = '<div class="ghost"></div><div class="attendees" data-post-id="' . esc_attr($post_id) . '">';
        }
        if ($assoziated_group_ids) {
            $output .= $group_section;
        }
        $output .= '<div class="attendees-wrapper">';

        if($post_type === 'group_post'){
            //$output .= '<p class="attendees-label">Mitglieder</p>';
        } else {
            if ($has_groups) {
                $output .= '<p class="attendees-label">Interesse an weiterer Gruppe?</p>';
            } else {
                $output .= '<p class="attendees-label">Interessenten</p>';
            }
        }

        list($buttons, $create_button) = $this->get_action_buttons($post_id, $post_id, $has_groups);
        $output .= '<div class="avatar-list">';

        if (is_array($members) && !empty($members)) {
            foreach ($members as $user_id) {
                $output .= '<a href="'.get_author_posts_url($user_id).'"><span class="avatar-wrapper" title="' . esc_attr(get_the_author_meta('display_name', $user_id)) . '">';
                $output .= get_avatar($user_id, 50);
                $output .= '</span></a>';
            }
        }
        $output .= $buttons;
        $output .= '<div class="clearfix"></div>';
        $output .= '</div>';
        $output .= $create_button;
        $output .= '</div>'; //attendees-wrapper

        if($do_print_output){
            $output .= '</div>';
            echo $output;
            return false;
        }
        return $output;
    }

    public function get_action_buttons($post_id, $group_id = null, $has_groups = false) {
        $join_svg = '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 20 20" height="20px" viewBox="0 0 20 20" width="20px" fill="#5f6368"><g><rect fill="none" height="20" width="20"/></g><g><g><path d="M8,10c1.66,0,3-1.34,3-3S9.66,4,8,4S5,5.34,5,7S6.34,10,8,10z M8,5.5c0.83,0,1.5,0.67,1.5,1.5S8.83,8.5,8,8.5 S6.5,7.83,6.5,7S7.17,5.5,8,5.5z"/><path d="M13.03,12.37C11.56,11.5,9.84,11,8,11s-3.56,0.5-5.03,1.37C2.36,12.72,2,13.39,2,14.09V16h12v-1.91 C14,13.39,13.64,12.72,13.03,12.37z M12.5,14.5h-9v-0.41c0-0.18,0.09-0.34,0.22-0.42C5.02,12.9,6.5,12.5,8,12.5 s2.98,0.4,4.28,1.16c0.14,0.08,0.22,0.25,0.22,0.42V14.5z"/><polygon points="16.25,7.75 16.25,6 14.75,6 14.75,7.75 13,7.75 13,9.25 14.75,9.25 14.75,11 16.25,11 16.25,9.25 18,9.25 18,7.75"/></g></g></svg>';
        $leave_svg = '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="24px" viewBox="0 0 24 24" width="24px" fill="#5f6368"><rect fill="none" height="24" width="24"/><path d="M20,17.17l-3.37-3.38c0.64,0.22,1.23,0.48,1.77,0.76C19.37,15.06,19.98,16.07,20,17.17z M21.19,21.19l-1.41,1.41L17.17,20H4 v-2.78c0-1.12,0.61-2.15,1.61-2.66c1.29-0.66,2.87-1.22,4.67-1.45L1.39,4.22l1.41-1.41L21.19,21.19z M15.17,18l-3-3 c-0.06,0-0.11,0-0.17,0c-2.37,0-4.29,0.73-5.48,1.34C6.2,16.5,6,16.84,6,17.22V18H15.17z M12,6c1.1,0,2,0.9,2,2 c0,0.86-0.54,1.59-1.3,1.87l1.48,1.48C15.28,10.64,16,9.4,16,8c0-2.21-1.79-4-4-4c-1.4,0-2.64,0.72-3.35,1.82l1.48,1.48 C10.41,6.54,11.14,6,12,6z"/></svg>';

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return ['', ''];
        }
        $create_button = '';
        $button_template = '<div class="group-builder-button-container">
                                <button class="%s" data-post-id="%d" title="%s">
                                    %s
                                </button>
                            </div>';

        $button2_template = '<div class="group-builder-create-button-container">
                                <button class="%s"  data-post-id="%d">
                                     %s
                                </button>
                            </div>';
        $buttons = '';

        if (get_post_type($post_id) === 'pinwall_post') {
            $interested_users = get_post_meta($post_id, '_interested_users', true);
            if (!is_array($interested_users)) {
                $interested_users = array();
            }
            if (in_array($current_user_id, $interested_users)) {
                $buttons .= sprintf($button_template, 'withdraw-interest', $post_id, 'Mein Interesse zurückziehen', $leave_svg,);

                if (count($interested_users) >= 2) {
                    $create_button = sprintf($button2_template, 'create-group', $post_id, 'Gruppe gründen');
                }
            } else {
                $buttons .= sprintf($button_template, 'show-interest', $post_id, 'Interesse zeigen', $join_svg,);
            }
        } elseif ($group_id) {
            $members = get_post_meta($group_id, '_group_members', true);
            if (!is_array($members)) {
                $members = array();
            }
            if(get_option('group_builder_avatar_actions')) {
                if (in_array($current_user_id, $members)) {
                    $buttons .= sprintf($button_template, 'leave-group', $group_id, 'Gruppe verlassen',$leave_svg);
                } else {
                    $buttons .= sprintf($button_template, 'join-group', $group_id, 'Gruppe beitreten',$join_svg);
                }
            }
            if(!in_array($current_user_id, $members) && count($members) < get_option('group_builder_max_members',4)){
                if(get_option('group_builder_avatar_actions')) {
                    $buttons .= sprintf($button_template, 'join-group', $group_id, 'Gruppe beitreten', $join_svg);
                }
            }
        }

        return [$buttons, $create_button];
    }

    public function group_builder_user_can($group_id, $action = 'edit') {
        $current_user_id = get_current_user_id();
        $group_members = get_post_meta($group_id, '_group_members', true);

        if ($action === 'join') {
            if (is_array($group_members) && in_array($current_user_id, $group_members)) {
                return false;
            }
            $join_option = get_post_meta($group_id, '_join_option', true);
            $hash = get_post_meta($group_id, '_invite_hash', true);
            return !$join_option || (isset($_GET['invite']) && $hash === $_GET['invite']);
        }

        if (!is_array($group_members)) {
            return false;
        }

        if ($action === 'edit' || $action === 'invite') {
            return in_array($current_user_id, $group_members);
        }

        return false;
    }
}
