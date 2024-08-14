<?php
/*
Plugin Name: Group-Builder
Description: Ermöglicht Nutzern, Interesse an Beiträgen zu zeigen und Gruppen zu gründen.
Version: 1.0
Author: Joachim Happel
*/

if (!defined('ABSPATH')) exit;
require_once 'class_post_it_colors.php';

/**
 * Plugin-Klasse zur Verwaltung der Interessenbekundung und Gruppenerstellung
 */
class GroupBuilder {
    private static $instance = null;
    private static $adminbar;

    /**
     * Gibt eine Instanz der Klasse zurück
     *
     * @return GroupBuilder Instanz der Klasse
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Konstruktor
     */
    private function __construct() {
        $this->setup_actions();
    }
    /**
     * Aktualisiert den Zeitstempel der letzten Änderung
     *
     * @param int $post_id ID des Beitrags
     */
    private function update_change_timestamp($post_id) {
        update_post_meta($post_id, '_avatar_list_updated', time());
    }

    /**
     * Initialisiert die Action Hooks für das Plugins
     */
    private function setup_actions() {

        add_action('admin_bar_menu', array($this, 'set_adminbar'),10000);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'),800);


        // AJAX-Aktionen
        add_action('wp_ajax_show_interest', array($this, 'ajax_show_interest'));
        add_action('wp_ajax_create_group', array($this, 'ajax_create_group'));
        add_action('wp_ajax_leave_group', array($this, 'ajax_leave_group'));
        add_action('wp_ajax_get_avatar_list', array($this, 'ajax_get_avatar_list'));
        add_action('wp_ajax_withdraw_interest', array($this, 'ajax_withdraw_interest'));
        add_action('wp_ajax_join_group', array($this, 'ajax_join_group'));
        add_action('wp_ajax_toggle_join_option', array($this, 'ajax_toggle_join_option'));
        add_action('wp_ajax_generate_invite_link', array($this, 'ajax_generate_invite_link'));

        // Kommentar-Aktionen
        add_action('pre_get_comments', array($this, 'restrict_group_comments'));
        add_filter('comment_form_defaults', array($this, 'customize_comment_form'));
        add_filter('comments_template', array($this, 'customize_comments_template'));

        add_shortcode('group_members', array($this, 'group_members_shortcode'));
        add_shortcode('group_edit_form', array($this, 'group_edit_form_shortcode'));

        // Integration in das Blocksy-Theme
        add_action('blocksy:loop:card:end' , array($this, 'get_avatar_list'));
        add_action('blocksy:comments:before' , array($this, 'get_pinnwall_post_avatar_list'));
        add_action('blocksy:header:after' , array($this, 'get_group_post_avatar_list'));
        add_action('blocksy:single:content:top' , array($this, 'join_group_button'));
        add_action('blocksy:single:content:top' , array($this, 'group_goal'));
        add_action('blocksy:comments:top' , array($this, 'comments_header'));


        #add_action('blocksy:header:after' , array($this, 'invite_link_copy_button'));

        // Add Heartbeat API support
        add_filter('heartbeat_received', array($this, 'heartbeat_received'), 10, 2);
        add_filter('heartbeat_nopriv_received', array($this, 'heartbeat_received'), 10, 2);


        // Integration in Ultimate Member
        add_filter( 'um_profile_tabs', array( $this, 'ultimate_member_integration_tab' ), 1000 );
        add_filter( 'um_user_profile_tabs', array( $this, 'ultimate_member_integration_tab' ), 1000 );
        add_action('um_profile_content_group-builder', array( $this, 'ultimate_member_integration_content' ) );
        add_filter( 'um_profile_query_make_posts', [$this,'ultimate_member_integration_profile_query_make_posts'], 10, 1 );


        // Tägliche Bereinigung der Daten
        if (!wp_next_scheduled('group_builder_cleanup')) {
            wp_schedule_event(time(), 'daily', 'group_builder_cleanup');
        }
        add_action('group_builder_cleanup', array($this, 'cleanup_orphaned_data'));
    }

    public function heartbeat_received($response, $data) {
        if (empty($data['group_builder_request'])) {
            return $response;
        }

        $updated_lists = array();

        foreach ($data['group_builder_request'] as $request) {
            $post_id = intval($request['post_id']);
            $client_timestamp = intval($request['timestamp']);

            $server_timestamp = get_post_meta($post_id, '_avatar_list_updated', true);

            if (!$server_timestamp) {
                $server_timestamp = time();
                update_post_meta($post_id, '_avatar_list_updated', $server_timestamp);
            }

            if ($client_timestamp < $server_timestamp) {
                $avatar_list = $this->get_avatar_list($post_id);
                if ($avatar_list !== null) {
                    $updated_lists[] = array(
                        'post_id' => $post_id,
                        'updated' => true,
                        'avatar_list' => $avatar_list,
                        'timestamp' => $server_timestamp
                    );
                }
            } else {
                $updated_lists[] = array(
                    'post_id' => $post_id,
                    'updated' => false
                );
            }
        }

        $response['group_builder_response'] = $updated_lists;

        return $response;
    }

    /**
     * Integriert das Plugin Custom WP Adminbar von Joachim Happel
     * @see https://github.com/johappel/custom_wp_adminbar
     * @param $customized_wordpress_adminbar Custom_AdminBar Class
     * stellt eine angepasste Adminbar auch für Abonnenten bereit und stellt eine Modal-Fenster Funktionalität zur Verfügung.
     */
    public function set_adminbar(){
        if(is_admin()){
            return;
        }
        global $customized_wordpress_adminbar;

        $user = wp_get_current_user();
        $adminbar = $customized_wordpress_adminbar;;
        $adminbar->remove('search');
        $adminbar->remove('logout');
        $adminbar->remove('user-info');

        $adminbar->edit('my-account',null,'/user/');
        $adminbar->add('top-secondary','','#', ' ');
        $adminbar->add('user-actions','Profil ansehen','/user/', 'dashicons-admin-users');
        $adminbar->add('user-actions','Profil bearbeiten','/user?um_action=edit', 'dashicons-edit');
        $adminbar->add('user-actions','Meine Gruppen','/user/wpadmin/?profiletab=group-builder', 'dashicons-admin-groups');
        $adminbar->add('user-actions','Nachricht schreiben','/account/fep-um/', 'dashicons-email-alt');

        $adminbar->add('user-actions','Konto Einstellungen','/account/', 'dashicons-admin-settings');
        $adminbar->add('user-actions','Abmelden',wp_logout_url(), 'dashicons-exit');
        $parent ='';
        $parent_home    = $adminbar->add('',get_bloginfo('name'),home_url(), 'dashicons-admin-home');
        if(is_user_logged_in()){
            $adminbar->add($parent_home,'Leute', '/netwerk/');
            $adminbar->add($parent_home,'Gruppen', '/group_post/');
            $adminbar->add('','Pinwand Karte','/pinwand-karte-erstellen/', 'dashicons-plus');
        }
        if(is_singular('pinwall_post') && is_user_logged_in()){
            if(current_user_can('edit_post', get_the_ID())){
                $adminbar->addModal('', 'Bearbeiten', 'edit_pinwall_post', 'dashicons-edit');
                $adminbar->addModalContent('edit_pinwall_post',
                    '<div><h3>Pinwand Karte bearbeiten</h3>'.do_shortcode('[acfe_form name="edit_pinwall_post"]').'</div>',
                    'full');
            }
        }

        if(is_singular('group_post')){
            #echo do_shortcode('[acfe_form name="edit_group"]');
            $parent         ='';
            $group_settings_content = '<div class="form_wrapper">
                <h3>Gruppe konfigurieren</h3>'.
                    //do_shortcode('[acfe_form name="edit_group"]').
                '</div>';
            $group_tools_content = '';
            if(group_builder_user_can(get_the_ID(), 'edit')){
                $adminbar->addMegaMenu($parent, 'Gruppe konfigurieren', 'group-edit-modal', 'dashicons-edit');
                $adminbar->addMegaMenu($parent, 'Lernwerkzeuge konfigurieren', 'group_tools', 'dashicons-admin-generic');
                #$adminbar->addMegaMenuContent('group_settings',$group_settings_content);
                $adminbar->addMegaMenuContent('group_tools','<div><h3>Lernwerkzeuge konfigurieren</h3></div>');
            }
            $this->display_modal_frame();
        }
        $adminbar->add_modal_and_mega_menu_html();


    }


    /**
     * Lädt die benötigten Skripte und Stile
     */
    public function enqueue_scripts() {
        wp_enqueue_script('group-builder-js', plugin_dir_url(__FILE__) . 'js/group-builder.js', array('jquery'), '1.0', true);
        wp_enqueue_style('group-builder-css', plugin_dir_url(__FILE__) . 'css/colors.css');
        wp_enqueue_style('group-builder-colors-css', plugin_dir_url(__FILE__) . 'css/group-builder.css');
        wp_localize_script('group-builder-js', 'group_builder_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
        //Lade Dashicons https://developer.wordpress.org/resource/dashicons ins Frontend
        wp_enqueue_style('dashicons');
        wp_enqueue_script('heartbeat');


    }
    /**
     * AJAX-Funktion zum Anzeigen des Interesses an einem Beitrag
     */
    public function ajax_leave_group() {
        $post_id = $group_id= isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if(get_post_type($post_id) !== 'group_post'){
            wp_send_json_error('Ungültige Anfrage');
        }
        $user_id = get_current_user_id();


        if (!$post_id || !$user_id) {
            wp_send_json_error('Ungültige Anfrage');
        }


        $members = get_post_meta($group_id, '_group_members', true);
        if (!is_array($members)) {
            $members = array();
        }

        $key = array_search($user_id, $members);
        if ($key !== false) {
            unset($members[$key]);
            update_post_meta($group_id, '_group_members', array_values($members));

            // Aktualisiere den Zeitstempel nach erfolgreichem Verlassen der Gruppe
            $this->update_change_timestamp($post_id);

            wp_send_json_success(array('post_id' => $post_id));
        } else {
            wp_send_json_error('Benutzer nicht in der Gruppe');
        }
    }
    /**
     * AJAX-Funktion zum Aktualisieren
     * der Gruppen, Interessenten, Mitglieder und Aktionsschalter
     */
    public function ajax_get_avatar_list() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $client_timestamp = isset($_POST['timestamp']) ? intval($_POST['timestamp']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Ungültige Anfrage: Keine post_id angegeben'));
            return;
        }

        $post_type = get_post_type($post_id);
        if($post_type !== 'group_post' && $post_type !== 'pinwall_post') {
            wp_send_json_error(array('message' => 'Ungültiger Post-Typ: ' . $post_type));
            return;
        }

        $server_timestamp = get_post_meta($post_id, '_avatar_list_updated', true);

        if (!$server_timestamp) {
            $server_timestamp = time();
            update_post_meta($post_id, '_avatar_list_updated', $server_timestamp);
        }

        $avatar_list = $this->get_avatar_list($post_id);
        if ($avatar_list === null) {
            wp_send_json_error(array('message' => 'Fehler beim Abrufen der Avatar-Liste für Post-ID: ' . $post_id));
            return;
        }

        $updated = $client_timestamp < $server_timestamp;

        wp_send_json_success(array(
            'updated' => $updated,
            'avatar_list' => $updated ? $avatar_list : '',
            'timestamp' => $server_timestamp
        ));
    }


    /**
     * AJAX-Funktion zum Anzeigen des Interesses an einem Beitrag
     */
    public function ajax_show_interest() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $user_id = get_current_user_id();

        if (!$post_id || !$user_id) {
            wp_send_json_error('Ungültige Anfrage');
        }

        // Hier: Logik zum Hinzufügen des Nutzers zur Liste der Interessierten
        $success = $this->add_user_interest($post_id, $user_id);

        if ($success) {
            $this->update_change_timestamp($post_id);
            $avatar_list = $this->get_avatar_list($post_id);
            wp_send_json_success($avatar_list);
        } else {
            wp_send_json_error('Fehler beim Hinzufügen des Interesses');
        }
    }
    /**
     * AJAX-Funktion zum Erstellen einer Gruppe
     */
    public function ajax_create_group() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error('Ungültige Anfrage');
        }

        $group_id = $this->create_group($post_id);

        if ($group_id) {
            $this->update_change_timestamp($post_id);
            wp_send_json_success(array('group_id' => $group_id));
        } else {
            wp_send_json_error('Fehler beim Erstellen der Gruppe');
        }
    }
    /**
     * AJAX-Funktion zum Verlassen einer Gruppe
     */
    public function ajax_withdraw_interest() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $user_id = get_current_user_id();

        if (!$post_id || !$user_id) {
            wp_send_json_error('Ungültige Anfrage');
        }

        $interested_users = get_post_meta($post_id, '_interested_users', true);
        if (!is_array($interested_users)) {
            $interested_users = array();
        }

        $key = array_search($user_id, $interested_users);
        if ($key  !== false) {
            unset($interested_users[$key]);
            update_post_meta($post_id, '_interested_users', array_values($interested_users));
            // Aktualisiere den Zeitstempel nach erfolgreichem Verlassen der Gruppe
            $this->update_change_timestamp($post_id);

            wp_send_json_success(array('post_id' => $post_id));
        } else {
            wp_send_json_error('Benutzer war nicht interessiert');
        }
    }
    /**
     * AJAX-Funktion zum Beitreten einer Gruppe
     */
    public function ajax_join_group() {
        $group_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($group_id===0) {
            $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        }
        $user_id = get_current_user_id();

        if (!$group_id || !$user_id || get_post_type($group_id) !== 'group_post') {
            wp_send_json_error('Ungültige Anfrage');
        }

        $members = get_post_meta($group_id, '_group_members', true);
        if (!is_array($members)) {
            $members = array();
        }

        if (!in_array($user_id, $members)) {
            if ($this->check_group_limit($group_id)) {
                $members[] = $user_id;
                update_post_meta($group_id, '_group_members', $members);
                $this->update_change_timestamp($group_id);

                // Finde den zugehörigen pinwall_post
                $args = array(
                    'post_type' => 'pinwall_post',
                    'meta_query' => array(
                        array(
                            'key' => '_associated_group',
                            'value' => $group_id,
                        ),
                    ),
                );
                $query = new WP_Query($args);

                if ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    //@todo: check if this update is really necessary
                    $this->update_change_timestamp($post_id);
                    wp_reset_postdata();
                    wp_send_json_success(array('post_id' => $post_id));
                } else {
                    wp_send_json_error('Zugehöriger pinwall_post nicht gefunden');
                }
            } else {
                wp_send_json_error('Benutzer ist bereits Mitglied der Gruppe');
            }
        } else {
            wp_send_json_error('Die Gruppe hat die maximale Anzahl an Mitgliedern erreicht');
        }
    }

    /**
     * Fügt das Nutzerinteresse zum Beitrag hinzu
     *
     * @param int $post_id ID des Beitrags
     * @param int $user_id ID des Nutzers
     * @return bool Erfolg oder Misserfolg
     */
    private function add_user_interest($post_id, $user_id) {
        $interested_users = get_post_meta($post_id, '_interested_users', true);
        if (!is_array($interested_users)) {
            $interested_users = array();
        }
        if (!in_array($user_id, $interested_users)) {
            $interested_users[] = $user_id;
            update_post_meta($post_id, '_interested_users', $interested_users);
            return true;
        }
        return false;
    }
    /**
     * Erstellt eine Gruppe zum Beitrag
     *
     * @param int $post_id ID des Beitrags
     * @return int ID der erstellten Gruppe
     */
    private function create_group($post_id) {
        $interested_users = get_post_meta($post_id, '_interested_users', true);
        if (!is_array($interested_users) || count($interested_users) < 2) {
            return false;
        }

        $group_id = wp_insert_post(array(
            'post_type' => 'group_post',
            'post_title' => 'Interessengruppe zum Beitrag ' . get_the_title($post_id),
            'post_status' => 'publish',
        ));

        if ($group_id) {
            update_post_meta($group_id, '_group_members', $interested_users);
            add_post_meta($post_id, '_associated_group', $group_id);
            delete_post_meta($post_id, '_interested_users');
            return $group_id;
        }

        return false;
    }
    /**
     * Shortcode für die Anzeige der Avatar-Liste
     *
     * @param array $atts Attribute des Shortcodes
     * @return string HTML-Code der Avatar-Liste
     */
    public function group_members_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
        ), $atts, 'group_members');

        return $this->get_avatar_list($atts['post_id']);
    }
    public function get_group_post_avatar_list(){
        if(is_singular(['group_post'])){
            $this->get_avatar_list();
        }
    }
    public function join_group_button(){
        if(is_singular(['group_post']) && group_builder_user_can(get_the_ID(), 'join')){
            echo '<div class="group-builder-join-button-container">
                    <button class="join-group" data-post-id="'.get_the_ID().'">
                        <span>Gruppe beitreten</span>
                    </button>
                </div>';
        }
    }

    public function get_pinnwall_post_avatar_list(){
        if(is_singular(['pinwall_post'])){
            $this->get_avatar_list();
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
                <?php
                ?>
            </div>
            <?php
        }
        else if(is_singular(['pinwall_post'])){
            ?>
            <div class="group-builder-comment-header">
                <h2>Diskussion:</h2>
                <?php
                ?>
            </div>
            <?php
        }
    }
    /**
     * Gibt die formatierte Avatar-Liste der Interessierten bzw Mitgliedern incl. Action Buttons zurück
     *
     * @param int $post_id ID des Beitrags
     * @return string Avatar-Liste
     */
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

        list($buttons, $create_button)= $this->get_action_buttons($post_id, $post_id,$has_groups);
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
    /**
     * Gibt die Aktionsschaltflächen für den Beitrag zurück
     *
     * @param int $post_id ID des Beitrags
     * @param int $group_id ID der Gruppe
     * @param bool $has_groups Gibt an, ob der Beitrag mit Gruppen assoziiert ist
     * @return string HTML-Code der Aktionsschaltflächen
     */
    private function get_action_buttons($post_id, $group_id = null, $has_groups = false) {

        $join_svg = '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 20 20" height="20px" viewBox="0 0 20 20" width="20px" fill="#5f6368"><g><rect fill="none" height="20" width="20"/></g><g><g><path d="M8,10c1.66,0,3-1.34,3-3S9.66,4,8,4S5,5.34,5,7S6.34,10,8,10z M8,5.5c0.83,0,1.5,0.67,1.5,1.5S8.83,8.5,8,8.5 S6.5,7.83,6.5,7S7.17,5.5,8,5.5z"/><path d="M13.03,12.37C11.56,11.5,9.84,11,8,11s-3.56,0.5-5.03,1.37C2.36,12.72,2,13.39,2,14.09V16h12v-1.91 C14,13.39,13.64,12.72,13.03,12.37z M12.5,14.5h-9v-0.41c0-0.18,0.09-0.34,0.22-0.42C5.02,12.9,6.5,12.5,8,12.5 s2.98,0.4,4.28,1.16c0.14,0.08,0.22,0.25,0.22,0.42V14.5z"/><polygon points="16.25,7.75 16.25,6 14.75,6 14.75,7.75 13,7.75 13,9.25 14.75,9.25 14.75,11 16.25,11 16.25,9.25 18,9.25 18,7.75"/></g></g></svg>';
        $leave_svg = '<svg xmlns="http://www.w3.org/2000/svg" enable-background="new 0 0 24 24" height="24px" viewBox="0 0 24 24" width="24px" fill="#5f6368"><rect fill="none" height="24" width="24"/><path d="M20,17.17l-3.37-3.38c0.64,0.22,1.23,0.48,1.77,0.76C19.37,15.06,19.98,16.07,20,17.17z M21.19,21.19l-1.41,1.41L17.17,20H4 v-2.78c0-1.12,0.61-2.15,1.61-2.66c1.29-0.66,2.87-1.22,4.67-1.45L1.39,4.22l1.41-1.41L21.19,21.19z M15.17,18l-3-3 c-0.06,0-0.11,0-0.17,0c-2.37,0-4.29,0.73-5.48,1.34C6.2,16.5,6,16.84,6,17.22V18H15.17z M12,6c1.1,0,2,0.9,2,2 c0,0.86-0.54,1.59-1.3,1.87l1.48,1.48C15.28,10.64,16,9.4,16,8c0-2.21-1.79-4-4-4c-1.4,0-2.64,0.72-3.35,1.82l1.48,1.48 C10.41,6.54,11.14,6,12,6z"/></svg>';

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return '';
        }
        $create_button = '';
        $button_template = '<div class="group-builder-button-container">
                                <button class="%s" data-post-id="%d">
                                    %s
                                    <span>%s</span>
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
                $buttons .= sprintf($button_template, 'withdraw-interest', $post_id,$leave_svg, 'Mein Interesse zurückziehen');

                if (count($interested_users) >= 2) {
                    $create_button = sprintf($button2_template, 'create-group', $post_id, 'Gruppe gründen');
                }
            } else {
                $buttons .= sprintf($button_template, 'show-interest', $post_id,$join_svg, 'Interesse zeigen');
            }
        } elseif ($group_id) {
            $members = get_post_meta($group_id, '_group_members', true);
            if (!is_array($members)) {
                $members = array();
            }
            if(get_option('group_builder_avatar_actions')) {

                if (in_array($current_user_id, $members)) {
                    $buttons .= sprintf($button_template, 'leave-group', $group_id,$leave_svg, 'Gruppe verlassen');
                } else {
                    $buttons .= sprintf($button_template, 'join-group', $group_id,$join_svg, 'Gruppe beitreten');
                }
            }
            if(!in_array($current_user_id, $members) && count($members) < get_option('group_builder_max_members',4)){
                $buttons .= sprintf($button_template, 'join-group', $group_id,$join_svg, 'Gruppe beitreten');
            }
        }

        return [$buttons, $create_button];
    }
    /**
     * Überprüft, ob die Gruppe die maximale Anzahl an Mitgliedern erreicht hat
     *
     * @param int $group_id ID der Gruppe
     * @return bool true, wenn die Gruppe die maximale Anzahl an Mitgliedern erreicht hat, sonst false
     */
    private function check_group_limit($group_id) {
        $members = get_post_meta($group_id, '_group_members', true);
        $max_members = apply_filters('group_builder_max_members', 10); // Standardmäßig 10, kann mit einem Filter angepasst werden
        return count($members) < $max_members;
    }

    /**
     * Bereinigt verwaiste Daten
     */
    public function cleanup_orphaned_data() {

        /**
         * Löscht _interested_users in den Metadaten der pinwall_post Beiträge, für die es keine existierenden Nutzer gibt
         */
        $args = array(
            'post_type' => 'pinwall_post',
            'meta_query' => array(
                array(
                    'key' => '_interested_users',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $interested_users = get_post_meta($post_id, '_interested_users', true);
                if (empty($interested_users)) {
                    delete_post_meta($post_id, '_interested_users');
                }
            }
        }

        /**
         * Überprüft ob, die Metadaten für assozierten Gruppen (_associated_group) in den pinwall_post Beiträge
         * und löscht die Metadaten, wenn die Gruppe nicht mehr existiert
         */
        $args = array(
            'post_type' => 'pinwall_post',
            'meta_query' => array(
                array(
                    'key' => '_associated_group',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $group_id = get_post_meta($post_id, '_associated_group', true);
                if (!get_post($group_id)) {
                    delete_post_meta($post_id, '_associated_group');
                }
            }
        }

        wp_reset_postdata();
    }
    /**
     * Shortcode für das bearbeitungsformular der Group_Post
     */
    public function group_edit_form_shortcode($atts) {
        // Überprüfen Sie, ob der Benutzer berechtigt ist, die Gruppe zu bearbeiten
        $group_id = get_the_ID();
        $current_user_id = get_current_user_id();
        $group_members = $this->get_group_members($group_id);

        if (!in_array($current_user_id, $group_members)) {
            return 'Sie sind nicht berechtigt, diese Gruppe zu bearbeiten.';
        }

        // Rendern Sie das ACF-Formular
        ob_start();
        acfe_form('edit_group');
        return ob_get_clean();
    }

    /**
     * AJAX-Funktion zum Bearbeiten eines Gruppenkommentars
     */
    public function restrict_group_comments($query) {
        if (!is_admin() && $query->is_comment_feed) {
            $post_id = $query->query_vars['post_id'];
            $post_type = get_post_type($post_id);

            if ($post_type === 'group_post') {
                $current_user_id = get_current_user_id();
                $group_members = $this->get_group_members($post_id);

                if (!in_array($current_user_id, $group_members)) {
                    $query->set('post__in', array(0)); // Keine Kommentare anzeigen
                }
            }
        }
    }
    public function customize_comments_template($comment_template) {
        if (get_post_type() === 'group_post') {
            global $post;
            $current_user_id = get_current_user_id();
            $group_members = $this->get_group_members($post->ID);
            if (!in_array($current_user_id, $group_members)) {
                $comment_template = '<p>Nur Mitglieder dieser Gruppe können Kommentare sehen.</p>';
            }
        }
        return $comment_template;
    }

    /**
     * Passt das Kommentarformular an
     */
    public function customize_comment_form($defaults) {
        global $post;
        $defaults['title_reply_before'] = '<h3>Kommentare erwünscht.</h3>';
        $defaults['title_reply']='';
        $defaults['logged_in_as'] = '';

        if (get_post_type($post) === 'group_post') {
            $current_user_id = get_current_user_id();
            $group_members = $this->get_group_members($post->ID);
            $defaults['label_submit'] = 'Beitrag abschicken';
            $defaults['logged_in_as'] = '';
            $defaults['title_reply'] = '<p>Beitrag schreiben</p>';
            $defaults['comment_field'] = str_replace('Kommentar','Beitrag',$defaults['comment_field']);
            if (!in_array($current_user_id, $group_members)) {
                $defaults['title_reply_before'] = '<p>Nur Mitglieder dieser Gruppe können hier Beiträge hinterlassen.</p>';
                $defaults['title_reply'] = ' -- ';
                $defaults['logged_in_as'] = '';
                $defaults['comment_field'] = '';
                $defaults['submit_button'] = '';
            }
        }

        return $defaults;
    }

    private function get_icon($name) {
        return '<span class="dashicons dashicons-' . $name . '"></i>';
    }


    /**
     * Zeigt das Modal-Fenster für die Gruppenbearbeitung an
     * Hinweis: Wir nutzen die Klassen-Definitionen von meinem custom_wp_adminbar Plugin
     */
    public function display_modal_frame() {
        if (is_singular('group_post') && group_builder_user_can(get_the_ID(), 'edit')) {
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
        elseif (is_singular('pinwall_post') && current_user_can('edit_post', get_the_ID())) {
            ?>
            <!-- Pinwall Modal -->
            <div id="group-edit-modal" class="custom-modal" style="display: none;">
                <div class="custom-modal-content">
                    <span class="custom-modal-close">&times;</span>
                    <h2>Pinwand Karte bearbeiten</h2>
                    <?php echo do_shortcode('[acfe_form name="edit_pinwall_post"]'); ?>
                </div>
            </div>
            <?php
        }
    }
    private function get_group_members($group_id) {
        return get_post_meta($group_id, '_group_members', true) ?: array();
    }
    /**
     * AJAX-Funktion zum Einstellen der Beitrittsoption:
     * wenn die die Gruppe den meta_key '_join_option' = 'invite_only' hat,
     * können nur eingeladene Benutzer der Gruppe beitreten, die einen speziellen Link erhalten haben
     * wenn die die Gruppe den meta_key '_join_option' = 'open' hat,
     * können alle Benutzer der Gruppe beitreten
     * Jedes Mitglied der Gruppe kann die Beitrittsoption ändern
     */
    public function ajax_toggle_join_option() {
        // Beitrittsoption ermitteln
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $current_join_option = get_post_meta($_POST['group_id'], '_join_option', true);
        // toggle join option
        $new_join_option = $current_join_option === 'invite_only' ? 'open' : 'invite_only';
        if($new_join_option === 'invite_only'){
            $new_status_text = 'Freier Beitritt zur Gruppe sperren: Nur mit Einladungslink';
            $new_status = 'lock';
            $status = 'unlock';
            $link = $this->get_invite_link();
        }else{
            $new_status_text = 'Beitritt zur Gruppe freigeben: Jeder kann beitreten';
            $new_status = 'unlock';
            $status = 'lock';
            $link = '';
        }
        update_post_meta($group_id, '_join_option', $new_join_option);
        wp_send_json_success(array(
                'new_status' => $new_status,
                'new_status_text' => $new_status_text,
                'status_icon' => $this->get_icon($status),
                'link' => $link

        ));
    }

    /**
     * AJAX-Funktion zur Rückgabe des Einladungslinks
     */
    public function ajax_generate_invite_link() {
        $invite_url = $this->get_invite_link();
        wp_send_json_success(array('invite_link' =>$invite_url));
    }

    /**
     * Gibt den Einladungslink zur Gruppe zurück
     * @return string|null //url to the invite link
     */
    public function get_invite_link($group_id = null)
    {
        if(!$group_id){
            $group_id = get_the_ID();
        }
        if(!$group_id){
            return 'Fehler: Gruppen-ID nicht gefunden';
        }

        $hash  = get_post_meta($group_id, '_invite_hash', true);
        // speichere neuen Hash für Einladungslink, wenn noch keiner existiert
        if (empty($hash)) {
            $hash = md5(uniqid(rand(), true));
            update_post_meta($group_id, '_invite_hash', $hash);
        }

        return get_permalink($group_id) . '?invite=' . $hash;
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
    public function get_invite_link_html($group_id = null){
        if(!$group_id){
            $group_id = get_the_ID();
        }
        $status = get_post_meta($group_id, '_join_option', true);
        if($status === 'invite_only') {
            $status_text = 'Jeder kann der Gruppe beitreten. Klicke auf das Schloss, um den Beitritt zur Gruppe zu sperren';
            $status_icon = $this->get_icon('unlock');
            $link = '';
            $html = '
                    <button  title="'.$status_text.'" class="toggle-join-option" data-group-id="' . $group_id . '">' . $status_icon . '</button>
                    <input type="hidden" name="invite-link" id="invite-link" value="' . $link . '">
                ';
        }else{
            $status_text = 'Der Beitritt zur Gruppe ist gesperrt. Nur Mitglieder mit Einladungslink können beitreten. Klicke auf das Schloss, um den Beitritt zur Gruppe freizugeben';
            $status_icon = $this->get_icon('lock');
            $link = $this->get_invite_link();
            $html = '
                    <button title="'.$status_text.'" class="toggle-join-option" data-group-id="' . $group_id . '">' . $status_icon . '</button>
                    <button class="copy-invite-link" data-group-id="' . $group_id . '" >Einladungslink kopieren</button>
                    <input type="hidden" name="invite-link" id="invite-link" value="' . $link . '">
                ';
        }
        return $html;

    }

    /**
     * Überprüft, ob der Benutzer über einen Einladungslink beigetreten ist
     */
    public function check_invite() {
        if (isset($_GET['invite'])) {
            $group_id = get_the_ID();
            $hash = $_GET['invite'];
            if ($this->check_invite_hash($group_id, $hash)) {
                $current_user_id = get_current_user_id();
                $members = get_post_meta($group_id, '_group_members', true);
                if (!is_array($members)) {
                    $members = array();
                }
                if (!in_array($current_user_id, $members)) {
                    $members[] = $current_user_id;
                    update_post_meta($group_id, '_group_members', $members);
                    $this->update_change_timestamp($group_id);
                }
                //redirect to the group page
                wp_redirect(get_permalink($group_id));
            }
        }

    }



    // Überprüfe den Einladungslink
    private function check_invite_hash($group_id, $hash) {
        $invite_hash = get_post_meta($group_id, '_invite_hash', true);
        return $invite_hash === $hash;
    }

    // Implementierung der Integration mit Ultimate Member

    /**
     * Post_types für Ultimate Member hinzufügen
     */
    public function ultimate_member_integration_profile_query_make_posts($args) {
        $args['post_type'] = 'pinwall_post';
        return $args;
    }

    /**
     * Fügt den Gruppen-Tab zu Ultimate Member hinzu
     */
    function ultimate_member_integration_tab( $tabs ) {

        $tabs['group-builder'] = array(
            'name' => 'Gruppen',
            'icon' => 'um-icon-android-people',
            'custom' => true,
        );
        $custom_tab = UM()->options()->get( 'profile_tab_' . 'group-builder' );
        if ( '' === $custom_tab ) {
            UM()->options()->update( 'profile_tab_' . 'group-builder', true );
        }

        return $tabs;

    }

    /**
     * Gibt die Liste von Group_Posts zurück, bei denen der User Mitglied ist
     */
    public function ultimate_member_integration_content()
    {
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
            // @todo: Hier können weitere infos zu den  Gruppen-Beiträge angezeigt werden
            echo '<li><a href="' . get_permalink($group->ID) . '">' . $group->post_title . '</a></li>';
        }
        echo '</ul>';
    }

}

function group_builder_user_can($group_id, $action = 'edit') {
    $current_user_id = get_current_user_id();
    $group_members = get_post_meta($group_id, '_group_members', true);

    if ($action === 'join') {

        if (is_array($group_members) && in_array($current_user_id, $group_members)) {
            return false;
        }
        $join_option = get_post_meta($group_id, '_join_option', true);
        $hash = get_post_meta($group_id, '_invite_hash', true);
        return !$join_option ||  (isset( $_GET['invite']) && $hash === $_GET['invite']);
    }

    if (!is_array($group_members)) {
        return false;
    }

    if ($action === 'edit' || $action === 'invite') {
        return in_array($current_user_id, $group_members);
    }



    return false;
}
// Initialisierung des Plugins
add_action('plugins_loaded', ['GroupBuilder', 'get_instance']);
