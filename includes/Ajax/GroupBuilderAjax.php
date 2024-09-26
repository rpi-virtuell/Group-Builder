<?php
namespace GroupBuilder\Ajax;

use GroupBuilder\Traits\GroupBuilderHelperTrait;

class GroupBuilderAjax {
    use GroupBuilderHelperTrait;
    public function __construct() {
        $this->setup_ajax_actions();
    }

    private function setup_ajax_actions() {
        add_action('wp_ajax_show_interest', [$this, 'ajax_show_interest']);
        add_action('wp_ajax_create_group', [$this, 'ajax_create_group']);
        add_action('wp_ajax_leave_group', [$this, 'ajax_leave_group']);
        add_action('wp_ajax_get_avatar_list', [$this, 'ajax_get_avatar_list']);
        add_action('wp_ajax_withdraw_interest', [$this, 'ajax_withdraw_interest']);
        add_action('wp_ajax_join_group', [$this, 'ajax_join_group']);
        add_action('wp_ajax_toggle_join_option', [$this, 'ajax_toggle_join_option']);
        add_action('wp_ajax_generate_invite_link', [$this, 'ajax_generate_invite_link']);


        add_filter('heartbeat_received', [$this, 'heartbeat_received'], 10, 2);
        add_filter('heartbeat_nopriv_received', [$this, 'heartbeat_received'], 10, 2);
    }

    public function ajax_show_interest() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $user_id = get_current_user_id();

        if (!$post_id || !$user_id) {
            wp_send_json_error('Ungültige Anfrage');
        }

        $success = $this->add_user_interest($post_id, $user_id);

        if ($success) {
            $this->update_change_timestamp($post_id);
            $avatar_list = $this->get_avatar_list($post_id);
            wp_send_json_success($avatar_list);
        } else {
            wp_send_json_error('Fehler beim Hinzufügen des Interesses');
        }
    }

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

    public function ajax_leave_group() {
        $post_id = $group_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (get_post_type($post_id) !== 'group_post') {
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
            $this->update_change_timestamp($post_id);
            wp_send_json_success(array('post_id' => $post_id));
        } else {
            wp_send_json_error('Benutzer nicht in der Gruppe');
        }
    }

    public function ajax_get_avatar_list() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $client_timestamp = isset($_POST['timestamp']) ? intval($_POST['timestamp']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Ungültige Anfrage: Keine post_id angegeben'));
            return;
        }

        $post_type = get_post_type($post_id);
        if ($post_type !== 'group_post' && $post_type !== 'pinwall_post') {
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
        if ($key !== false) {
            unset($interested_users[$key]);
            update_post_meta($post_id, '_interested_users', array_values($interested_users));
            $this->update_change_timestamp($post_id);

            wp_send_json_success(array('post_id' => $post_id));
        } else {
            wp_send_json_error('Benutzer war nicht interessiert');
        }
    }

    public function ajax_join_group() {
        $group_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($group_id === 0) {
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

                $args = array(
                    'post_type' => 'pinwall_post',
                    'meta_query' => array(
                        array(
                            'key' => '_associated_group',
                            'value' => $group_id,
                        ),
                    ),
                );
                $query = new \WP_Query($args);

                if ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $this->update_change_timestamp($post_id);
                    wp_reset_postdata();
                    wp_send_json_success(array('post_id' => $post_id));
                } else {
                    wp_send_json_error('Zugehöriger pinwall_post nicht gefunden');
                }
            } else {
                wp_send_json_error('Die Gruppe hat die maximale Anzahl an Mitgliedern erreicht');
            }
        } else {
            wp_send_json_error('Benutzer ist bereits Mitglied der Gruppe');
        }
    }

    public function ajax_toggle_join_option() {
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $current_join_option = get_post_meta($_POST['group_id'], '_join_option', true);
        $new_join_option = $current_join_option === 'invite_only' ? 'open' : 'invite_only';
        if ($new_join_option === 'invite_only') {
            $new_status_text = 'Freier Beitritt zur Gruppe sperren: Nur mit Einladungslink';
            $new_status = 'lock';
            $status = 'unlock';
            $link = $this->get_invite_link();
        } else {
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

    public function ajax_generate_invite_link() {
        $invite_url = $this->get_invite_link();
        wp_send_json_success(array('invite_link' => $invite_url));
    }

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
            update_post_meta($group_id, '_pinwall_post', $post_id);
            add_post_meta($post_id, '_associated_group', $group_id);
            delete_post_meta($post_id, '_interested_users');
            return $group_id;
        }

        return false;
    }

    private function get_icon($name) {
        return '<span class="dashicons dashicons-' . $name . '"></span>';
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
}
