<?php
/*
Plugin Name: Group-Builder
Description: Ermöglicht Nutzern, Interesse an Beiträgen zu zeigen und Gruppen zu gründen.
Version: 1.0
Author: Joachim Happel
*/

if (!defined('ABSPATH')) exit;

/**
 * Plugin-Klasse zur Verwaltung der Interessenbekundung und Gruppenerstellung
 */
class GroupBuilder {
    private static $instance = null;

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
     * Initialisiert die Action Hooks für das Plugins
     */
    private function setup_actions() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_show_interest', array($this, 'ajax_show_interest'));
        add_action('wp_ajax_create_group', array($this, 'ajax_create_group'));
        add_filter('the_content', array($this, 'display_interest_button'));
    }
    /**
     * Lädt die benötigten Skripte und Stile
     */
    public function enqueue_scripts() {
        wp_enqueue_script('group-builder-js', plugin_dir_url(__FILE__) . 'js/group-builder.js', array('jquery'), '1.0', true);
        wp_enqueue_style('group-builder-css', plugin_dir_url(__FILE__) . 'css/group-builder.css');
        wp_localize_script('group-builder-js', 'group_builder_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
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
            wp_send_json_success(array('group_id' => $group_id));
        } else {
            wp_send_json_error('Fehler beim Erstellen der Gruppe');
        }
    }
    /**
     * Fügt den Interessen-Button und die Avatar-Liste zum Beitrag hinzu
     *
     * @param string $content Ursprünglicher Beitragstext
     * @return string Modifizierter Beitragstext
     */
    public function display_interest_button($content) {
        if (get_post_type() === 'pinwall_post') {
            $post_id = get_the_ID();
            $avatar_list = $this->get_avatar_list($post_id);
            $interest_button = '<button class="show-interest" data-post-id="' . $post_id . '">+</button>';
            $group_button = '<button class="create-group" data-post-id="' . $post_id . '">Gruppe gründen</button>';

            $content .= $interest_button . $avatar_list . $group_button;
        }
        return $content;
    }
    /**
     * Fügt das Nutzerinteresse zum Beitrag hinzu
     *
     * @param int $post_id ID des Beitrags
     * @param int $user_id ID des Nutzers
     * @return bool Erfolg oder Misserfolg
     */
    private function add_user_interest($post_id, $user_id) {
        // Implementierung der Logik zum Hinzufügen des Nutzerinteresses
        // Rückgabe: true bei Erfolg, false bei Fehler
    }
    /**
     * Erstellt eine Gruppe zum Beitrag
     *
     * @param int $post_id ID des Beitrags
     * @return int ID der erstellten Gruppe
     */
    private function create_group($post_id) {
        // Implementierung der Logik zur Gruppenerstellung
        $group_id = wp_insert_post(array(
            'post_type' => 'group_post',
            'post_title' => 'Interessengruppe zum Beitrag ' . get_the_title($post_id),
            // Weitere Felder...
        ));

        if ($group_id) {
            // Interessierte der Gruppe zuordnen
            // Link zum Pinwand-Beitrag aktualisieren
        }

        return $group_id;
    }
    /**
     * Gibt die formatierte Avatar-Liste der Interessierten zurück
     *
     * @param int $post_id ID des Beitrags
     * @return string Avatar-Liste
     */
    private function get_avatar_list($post_id) {
        // Implementierung der Logik zum Abrufen und Formatieren der Avatar-Liste
        return '<div class="avatar-list">...</div>';
    }
}

// Initialisierung des Plugins
function group_builder_init() {
    GroupBuilder::get_instance();
}
add_action('plugins_loaded', 'group_builder_init');
