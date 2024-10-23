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
        $max_members = apply_filters('options_group_builder_max_members', get_option('options_group_builder_max_members', 10));
        return count($members) < $max_members;
    }
    public function get_invite_message($group_id = null) {
        $link = $this->get_invite_link($group_id);
        if (!$link) {
            return 'Fehler: Gruppenlink konnte nicht erstellt werden';
        }
        $group_name = get_the_title($group_id);
        $message = 'Hallo, ich möchte zu unsere Arbeitsgruppe einladen und habe dir einen Link geschickt.'; ;
        $message .= "<br><a href='$link'>$group_name</a><br>";
        $message .= "Klicke dort auf den \"Gruppe beitreten\" Button.";
        return $message;

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
        if(isset($_GET['group-space'])){
            return null;
        }

        $do_print_output = false;
        $has_groups = false;
        $output = '';
        $assoziated_group_ids = null;

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
                $group_section .= '<div class="assoziated-groups"><p class="assoziated-groups-label">Diese Gruppe arbeiten bereits dazu:</p>';
                $group_section .= '<ul class="group-list">';
                foreach($assoziated_group_ids as $assoziated_group_id){
                    $group_section .= '<li class="assoziated-group-link"><a href="'.get_permalink($assoziated_group_id).'"><span class="dashicons dashicons-groups"></span>'.get_the_title($assoziated_group_id).'</a></li>';
                }
                $group_section .= '</ul></div>';
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

        if($post_type === 'pinwall_post'){
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
        if (!$current_user_id || !is_user_approved()) {
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
                $buttons .= sprintf($button_template, 'withdraw-interest', $post_id, 'Mein Interesse zurückziehen', $leave_svg);

                if (count($interested_users) >= get_option('options_group_builder_min_members', 2)) {
                    $create_button = sprintf($button2_template, 'create-group', $post_id, 'Gruppe gründen');
                }
            } else {
                $buttons .= sprintf($button_template, 'show-interest', $post_id, 'Interesse zeigen', $join_svg);
            }
        } elseif ($group_id) {
            $members = get_post_meta($group_id, '_group_members', true);
            $join_on_invitation = get_post_meta($group_id, '_join_option', true);


            if (!is_array($members)) {
                $members = array();
            }
            if(get_option('options_group_builder_avatar_actions')) {

                if (in_array($current_user_id, $members)) {
                    $buttons .= sprintf($button_template, 'leave-group', $group_id, 'Gruppe verlassen',$leave_svg);
                } else {

                    $user_can_join = $this->group_builder_user_can($group_id, 'join');
                    if ($user_can_join) {
                        $buttons .= sprintf($button_template, 'join-group', $group_id, 'Gruppe beitreten', $join_svg);
                    }
                }
            }
            error_log('buttons: ' . htmlentities($buttons));
        }

        return [$buttons, $create_button];
    }
    public function is_member($group_id = null, $user_id=null) {
        if(!$user_id){
            $user_id = get_current_user_id();
        }
        if(!$group_id){
            $group_id = get_the_ID();
        }
        $members = get_post_meta($group_id, '_group_members', true);
        return is_array($members) && in_array($user_id, $members);
    }


    public function group_builder_user_can($post_id = null, $action = 'edit') {
        if(!is_user_approved()){
            return false;
        }
        if(is_singular('pinwall_post')){
            $post=get_post($post_id);
            if($post->post_author == get_current_user_id()){
                return true;
            }
            return false;
        }
        $current_user_id = get_current_user_id();
        $group_members = get_post_meta($post_id, '_group_members', true);

        if ($action === 'join') {
            if (is_array($group_members) && in_array($current_user_id, $group_members)) {
                return false;
            }
            $slots_free = false;
            $join_option = get_post_meta($post_id, '_join_option', true);

            $members = get_post_meta($post_id, '_group_members', true);
            if($members){
                $max_members = get_option('options_group_builder_max_members', 4);
                if(count($members) < $max_members){
                    $slots_free = true;
                }
            }else{
                $slots_free = true;
            }

            if($join_option){
                $hash = get_post_meta($post_id, '_invite_hash', true);
                $invite = get_user_meta($current_user_id, '_group_user_invite_code', true);
            }

            if($slots_free && !$join_option){
                return true;
            }elseif($join_option && $hash === $invite){
                return true;

            }
            return false;
        }

        if (!is_array($group_members)) {
            return false;
        }

        if ($action === 'edit' || $action === 'invite') {
            return in_array($current_user_id, $group_members);
        }

        return false;
    }

    function generate_ical($event_id) {
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'event_post') {
            return false;
        }

        $event_title = get_post_meta($event_id, 'event_title', true);
        $event_url = get_post_meta($event_id, 'event_url', true);
        $event_start_date = get_post_meta($event_id, 'event_start_date', true);
        $event_end_date = get_post_meta($event_id, 'event_end_date', true);

        $start_timestamp = strtotime($event_start_date);
        $end_timestamp = strtotime($event_end_date);

        $name = bloginfo('name');
        $blog_url = get_bloginfo('url');
        $host = parse_url($blog_url, PHP_URL_HOST);
        $admin_email = get_bloginfo('admin_email');

        $ical = "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//-//$name//DE
BEGIN:VEVENT
UID:" . md5($event_id) . "@$host
DTSTAMP:" . gmdate('Ymd\THis\Z') . "
DTSTART:" . gmdate('Ymd\THis\Z', $start_timestamp) . "
DTEND:" . gmdate('Ymd\THis\Z', $end_timestamp) . "
SUMMARY:" . $event_title . "
DESCRIPTION:" . $event_url . "
END:VEVENT
END:VCALENDAR";

        return $ical;
    }



    function get_german_weekday($date, $short = false) {
        $weekday_english = date('l', strtotime($date));
        $weekdays = array(
            'Monday'    => ['Montag', 'Mo'],
            'Tuesday'   => ['Dienstag', 'Di'],
            'Wednesday' => ['Mittwoch', 'Mi'],
            'Thursday'  => ['Donnerstag', 'Do'],
            'Friday'    => ['Freitag', 'Fr'],
            'Saturday'  => ['Samstag', 'Sa'],
            'Sunday'    => ['Sonntag', 'So']
        );

        return $short ? $weekdays[$weekday_english][1] : $weekdays[$weekday_english][0];
    }

    function get_german_month($date, $short = false) {
        $month_english = date('F', strtotime($date));
        $months = array(
            'January'   => ['Januar', 'Jan'],
            'February'  => ['Februar', 'Feb'],
            'March'     => ['März', 'Mär'],
            'April'     => ['April', 'Apr'],
            'May'       => ['Mai', 'Mai'],
            'June'      => ['Juni', 'Jun'],
            'July'      => ['Juli', 'Jul'],
            'August'    => ['August', 'Aug'],
            'September' => ['September', 'Sep'],
            'October'   => ['Oktober', 'Okt'],
            'November'  => ['November', 'Nov'],
            'December'  => ['Dezember', 'Dez']
        );

        return $short ? $months[$month_english][1] : $months[$month_english][0];
    }

    public function ensure_comments_enabled($post_id) {
        return;
        // Überprüfen, ob es sich um einen relevanten Post-Typ handelt
        $post_type = get_post_type($post_id);
        if (!in_array($post_type, ['group_post', 'pinwall_post'])) {
            return;
        }
        if($post_status = get_post_status($post_id) !== 'publish'){
            return;
        }

        // Kommentarstatus auf "offen" setzen
        $post_data = array(
            'ID' => $post_id,
            'comment_status' => 'open'
        );

        // Beitrag aktualisieren
        wp_update_post($post_data);
    }

    public function set_random_featured_image_for_group_post($post_id) {
        // Überprüfen, ob es sich um einen neuen Beitrag handelt
        if (get_post_status($post_id) != 'publish' || get_post_type($post_id) != 'group_post') {
            return;
        }

        // Überprüfen, ob bereits ein Artikelbild gesetzt ist
        if (has_post_thumbnail($post_id)) {
            return;
        }

        // Holen der Bild-IDs aus den Optionen
        $bg_images = get_option('options_bg_images');

        // Überprüfen, ob Bilder vorhanden sind
        if (!is_array($bg_images) || empty($bg_images)) {
            return;
        }

        // Zufälliges Bild auswählen
        $random_image_id = $bg_images[array_rand($bg_images)];

        // Artikelbild setzen
        set_post_thumbnail($post_id, $random_image_id);
    }

}
