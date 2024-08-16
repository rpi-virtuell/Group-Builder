<?php
namespace GroupBuilder\Frontend;

use GroupBuilder\Traits\GroupBuilderHelperTrait;

class GroupBuilderFrontend {
    use GroupBuilderHelperTrait;

    public function __construct() {
        $this->setup_shortcodes();
        $this->setup_frontend_actions();
    }

    private function setup_shortcodes() {
        add_shortcode('group_members', [$this, 'group_members_shortcode']);
        add_shortcode('group_edit_form', [$this, 'group_edit_form_shortcode']);
    }

    private function setup_frontend_actions() {
        add_action('blocksy:loop:card:end', [$this, 'get_avatar_list']);
        add_action('blocksy:comments:before', [$this, 'get_pinnwall_post_avatar_list']);
        add_action('blocksy:header:after', [$this, 'get_group_post_avatar_list']);
        add_action('blocksy:single:content:top', [$this, 'join_group_button']);
        add_action('blocksy:single:content:top', [$this, 'group_goal']);
        add_action('blocksy:comments:top', [$this, 'comments_header']);

        add_action('admin_bar_menu', [$this, 'set_adminbar'], 10000);
    }

    public function group_members_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
        ), $atts, 'group_members');

        return $this->get_avatar_list($atts['post_id']);
    }

    public function group_edit_form_shortcode($atts) {
        $group_id = get_the_ID();
        $current_user_id = get_current_user_id();
        $group_members = $this->get_group_members($group_id);

        if (!in_array($current_user_id, $group_members)) {
            return 'Sie sind nicht berechtigt, diese Gruppe zu bearbeiten.';
        }

        ob_start();
        acfe_form('edit_group');
        return ob_get_clean();
    }

    public function get_pinnwall_post_avatar_list(){
        if(is_singular(['pinwall_post'])){
            $this->get_avatar_list();
        }
    }

    public function get_group_post_avatar_list(){
        if(is_singular(['group_post'])){
            $this->get_avatar_list();
        }
    }

    public function join_group_button(){
        if(is_singular(['group_post']) && $this->group_builder_user_can(get_the_ID(), 'join')){
            echo '<div class="group-builder-join-button-container">
                    <button class="join-group" data-post-id="'.get_the_ID().'">
                        <span>Gruppe beitreten</span>
                    </button>
                </div>';
        }
    }

    public function group_goal(){
        if(is_singular(['group_post'])){
            $goal = get_post_meta(get_the_ID(), 'group_exerpt', true);
            if($goal){
                echo '<div class="group-goal"><h2>Unser Ziel:</h2><p>'.$goal.'</p><h2>Herausforderungen und Schwerkunkte:</h2></div>';
            }
        }
    }

    public function comments_header(){
        if(is_singular(['group_post'])){
            ?>
            <div class="group-builder-comment-header">
                <h2>Diskurs und Ergebnisse:</h2>
            </div>
            <?php
        }
        else if(is_singular(['pinwall_post'])){
            ?>
            <div class="group-builder-comment-header">
                <h2>Diskussion:</h2>
            </div>
            <?php
        }
    }

    private function get_action_buttons($post_id, $group_id = null, $has_groups = false) {
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
                $buttons .= sprintf($button_template, 'join-group', $group_id, 'Gruppe beitreten',$join_svg);
            }
        }

        return [$buttons, $create_button];
    }

    public function set_adminbar() {
        if (is_admin()) {
            return;
        }
        global $customized_wordpress_adminbar;

        $user = wp_get_current_user();
        $adminbar = $customized_wordpress_adminbar;
        $adminbar->remove('search');
        $adminbar->remove('logout');
        $adminbar->remove('user-info');

        $adminbar->edit('my-account', null, '/user/');
        #$adminbar->add('top-secondary', '', '#', ' ');
        $adminbar->add('user-actions', 'Profil ansehen', '/user/', 'dashicons-admin-users');
        $adminbar->add('user-actions', 'Profil bearbeiten', '/user?um_action=edit', 'dashicons-edit');
        $adminbar->add('user-actions', 'Meine Gruppen', '/user/wpadmin/?profiletab=group-builder', 'dashicons-admin-groups');
        $adminbar->add('user-actions', 'Nachricht schreiben', '/account/fep-um/', 'dashicons-email-alt');

        $adminbar->add('user-actions', 'Konto Einstellungen', '/account/', 'dashicons-admin-settings');
        $adminbar->add('user-actions', 'Abmelden', wp_logout_url(), 'dashicons-exit');
        $parent = '';
        $parent_home = $adminbar->add('', get_bloginfo('name'), home_url(), 'dashicons-admin-home');
        if (is_user_logged_in()) {
            $adminbar->add($parent_home, 'Leute', '/netwerk/');
            $adminbar->add($parent_home, 'Gruppen', '/group_post/');
            $adminbar->add('', 'Pinwand Karte', '/pinwand-karte-erstellen/', 'dashicons-plus');
        }
        if (is_singular('pinwall_post') && is_user_logged_in()) {
            if ($this->group_builder_user_can( get_the_ID(), 'edit')) {
                $adminbar->addMegaMenu('', 'Bearbeiten', 'pin-edit-modal', 'dashicons-edit');
//                $adminbar->addMegaMenuContent('edit_pinwall_post',
//                    '<div><h3>Pinwand Karte bearbeiten</h3>' . do_shortcode('[acfe_form name="edit_pinwall_post"]') . '</div>',
//                    'full');
                $this->display_modal_frame();
            }
        }

        if (is_singular('group_post')) {
            $group_settings_content = '<div class="form_wrapper">
            <h3>Gruppe konfigurieren</h3></div>';
            $group_tools_content = '';
            if ($this->group_builder_user_can(get_the_ID(), 'edit')) {
                $adminbar->addMegaMenu($parent, 'Gruppe konfigurieren', 'group-edit-modal', 'dashicons-edit');
                $adminbar->addMegaMenu($parent, 'Lernwerkzeuge konfigurieren', 'group_tools', 'dashicons-admin-generic');
                $adminbar->addMegaMenuContent('group_tools', '<div><h3>Lernwerkzeuge konfigurieren</h3></div>');
            }
            $this->display_modal_frame();
        }
        $adminbar->add_modal_and_mega_menu_html();
    }
    /**
     * Gibt den HTML-Code für den Einladungslink zurück
     * @return string
     */
    public function invite_link_copy_button(){
        if(!is_singular('group_post')){
            return;
        }
        if(!is_user_logged_in()){
            return;
        }
        $group_id = get_the_ID();
        $join_option = get_post_meta($group_id, '_join_option', true);

        if($join_option){
            $link = $this->get_invite_link($group_id);
            echo '<div class="copy-invite-link-wrapper">';
            echo '<span class="copy-invite-link-label">Weitere Mitglieder einladen:</span><button class="button copy-invite-link" data-post-id="' . $group_id . '">Einladungslink kopieren</button></div>';
            echo '<div style="display: none"><input type="text" name="invite-link" id="invite-link" value="' . $link . '">';
            echo '</div>';
        }

        echo $this->leave_button($group_id);
    }
    private function leave_button($group_id){
        return '<div class="leave-btn-wrapper"><span class="leave-group-label">Deine Mitarbeit in der Gruppe beenden: </span><button class="leave-group" data-post-id="' . $group_id . '">Gruppe verlassen</button></div>';
    }

    /**
     * Zeigt das Modal-Fenster für die Gruppenbearbeitung an
     * Hinweis: Wir nutzen die Klassen-Definitionen von meinem custom_wp_adminbar Plugin
     */
    public function display_modal_frame() {
        if (is_singular('group_post') && $this->group_builder_user_can(get_the_ID(), 'edit')) {
            ?>
            <!-- Group Modal -->
            <div id="group-edit-modal" class="custom-modal" style="display: none;">
                <div class="custom-modal-content">
                    <span class="custom-modal-close">&times;</span>
                    <h2>Gruppe konfigurieren</h2>
                    <?php $this->invite_link_copy_button(); ?><br>
                    <?php echo do_shortcode('[acfe_form name="edit_group"]'); ?>

                </div>
            </div>
            <?php
        }
        elseif (is_singular('pinwall_post') && $this->group_builder_user_can( get_the_ID(), 'edit')) {
            ?>
            <!-- Pinwall Modal -->
            <div id="pin-edit-modal" class="custom-modal" style="display: none;">
                <div class="custom-modal-content">
                    <span class="custom-modal-close">&times;</span>
                    <h2>Pinwand Karte bearbeiten</h2>
                    <?php echo do_shortcode('[acfe_form name="edit_pinwall_post"]'); ?>
                </div>
            </div>
            <?php
        }
    }
}
