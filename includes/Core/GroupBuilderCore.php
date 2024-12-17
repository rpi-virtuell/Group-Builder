<?php
namespace GroupBuilder\Core;

use GroupBuilder\Ajax\GroupBuilderAjax;
use GroupBuilder\Frontend\GroupBuilderFrontend;
use GroupBuilder\Integrations\GroupBuilderIntegrations;
use GroupBuilder\Traits\GroupBuilderHelperTrait;
use GroupBuilder\Color\GroupBuilderColor;

class GroupBuilderCore {
    use GroupBuilderHelperTrait;

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->setup_actions();
    }

    private function load_dependencies() {
        new GroupBuilderAjax();
        new GroupBuilderFrontend();
        new GroupBuilderIntegrations();
        new GroupBuilderColor();
    }

    private function setup_actions() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 800);
        //add_action('group_builder_cleanup', [$this, 'cleanup_orphaned_data']);
        // Pinwwandbezüge beim Löschen einer Gruppe entfernen @todo  geht noch nicht
        //add_action('before_delete_post', [$this,'clean_associated_group_meta']);

        if (!wp_next_scheduled('group_builder_cleanup')) {
            wp_schedule_event(time(), 'daily', 'group_builder_cleanup');
        }
        // Füge einen täglichen Cron-Job hinzu
        if (!wp_next_scheduled('daily_event_notifications')) {
            wp_schedule_event(time(), 'daily', 'daily_event_notifications');
        }
        add_action('daily_event_notifications', [$this,'send_event_notifications']);


    }


    public function enqueue_scripts() {
        if(is_singular('group_post')) {
            $members = get_post_meta(get_the_ID(), '_group_members', true);
            if(!empty($members)) {
                $is_member = in_array(get_current_user_id(), $members)?'yes':'no';
            }else{
                $is_member = 'no';
            }
        }else{
            $is_member = 'yes';
        }
        wp_enqueue_script('group-builder-js', plugin_dir_url(__FILE__) . '../../js/group-builder.js', array('jquery'), '1.0', true);
        wp_enqueue_style('group-builder-css', plugin_dir_url(__FILE__) . '../../css/colors.css');
        wp_enqueue_style('group-builder-colors-css', plugin_dir_url(__FILE__) . '../../css/group-builder.css');
        wp_localize_script('group-builder-js', 'group_builder_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
        wp_localize_script('group-builder-js', 'group_builder_group', array('is_member' => $is_member, 'group_id' => get_the_ID()));
        wp_enqueue_style('dashicons');
        wp_enqueue_script('heartbeat');
    }

    public function cleanup_orphaned_data() {
        $args = array(
            'post_type' => 'pinwall_post',
            'meta_query' => array(
                array(
                    'key' => '_interested_users',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        $query = new \WP_Query($args);

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

        $args = array(
            'post_type' => 'pinwall_post',
            'meta_query' => array(
                array(
                    'key' => '_associated_group',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        $query = new \WP_Query($args);

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

    public function clean_associated_group_meta($post_id) {
        // Überprüfen, ob es sich um einen group_post handelt
        if (get_post_type($post_id) != 'group_post') {
            return;
        }

        // Alle pinnwall_post Beiträge abrufen
        $pinnwall_posts = get_posts(array(
            'post_type' => 'pinwall_post',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => '_associated_group',
                    'value' => $post_id,
                    'compare' => '='
                )
            )
        ));

        // Für jeden pinnwall_post das Meta-Feld löschen
        foreach ($pinnwall_posts as $post) {
            delete_post_meta($post->ID, '_associated_group', $post_id);
        }
    }
    public function send_message_to_group_post_member($group_id, $subject, $body) {
        $group_members = get_post_meta($group_id, '_group_members', true);
        if (!is_array($group_members)) {
            return;
        }

        if( function_exists('fep_send_message') ){
            try {
                $author = get_user_by( 'login', 'kibot' );
                if($author){
                    $sender_id = $author->ID;
                }else{
                    $sender_id = $group_members[0];
                }
                $post = array(
                    'mgs_title'	=> $subject,
                    'mgs_content'	=> $body,
                    'mgs_status'	=> 'publish',
                    'mgs_parent'	=> 0,
                    'mgs_type'		=> 'message',
                    'mgs_author'	=> $sender_id,
                    'mgs_created'	=> current_time( 'mysql', 1 ),
                );
                $post = wp_unslash( $post );
                $new_message = new \FEP_Message;
                $message_id = $new_message->insert( $post );
                // Insert the message into the database
                if ( ! $message_id  ) {
                    return false;
                }
                $message['message_to_id'] = (array) $group_members;
                //$message['message_to_id'][] = $new_message->mgs_author;
                $new_message->insert_participants( $message['message_to_id'] );


                fep_status_change( 'new', $new_message );

            } catch (Exception $e) {
                error_log($e->getMessage());
            }


            do_action('group_builder_send_message_to_group_member', $group_id, $subject, $message, $message_id);

        }

    }
    public function send_event_notifications() {
        // Hole alle Events, die morgen stattfinden
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $args = array(
            'post_type' => 'event_post',
            'meta_query' => array(
                array(
                    'key' => 'event_start_date',
                    'value' => $tomorrow,
                    'compare' => '=',
                    'type' => 'DATE'
                )
            )
        );
        $events = get_posts($args);

        foreach ($events as $event) {
            $event_id = $event->ID;
            $event_title = get_post_meta($event_id, 'event_title', true);
            $event_group_id = get_post_meta($event_id, 'event_group_id', true);
            $event_url = get_post_meta($event_id, 'event_url', true);

            if ($event_group_id) {
                // Event ist gruppenspezifisch
//                $member_ids = group_post_member($event_group_id);
//                foreach ($member_ids as $user_id) {
                    $message= get_option('options_remember_event_group_email');
                    $message = str_replace('{event_title}', $event_title, $message);
                    $message = str_replace('{event_url}', $event_url, $message);

                    $blog_name = get_bloginfo('name');
                    $subject = "[$blog_name] Erinnerung: {$event_title}";

                    //Nutze die Message Funktion von Frontend PM
                    $this->send_message_to_group_post_member($event_group_id, $subject, $message);
//                }
            } else {

                // Event ist für alle Benutzer
                $users = get_users();
                $to = [];
                foreach ($users as $user) {
                    $to[] = $user->user_email;
                }
                $to = implode(',', $to);
                //Massenmails werden als BCC verschickt
                $headers = ['Bcc: ' . $to];
                $this->send_notification($to, $event_title, $event_url, $is_group_event = false, $headers);
            }
        }
    }

    private function send_notification($to, $event_title, $event_url, $is_group_event = false, $headers = []) {

        $blog_name = get_bloginfo('name');
        $subject = "[$blog_name] Erinnerung: {$event_title}";
        $message= get_option('options_remember_event_email');
        $message = str_replace('{event_title}', $event_title, $message);
        $message = str_replace('{event_url}', $event_url, $message);

        wp_mail($to, $subject, $message, $headers);
    }

}
