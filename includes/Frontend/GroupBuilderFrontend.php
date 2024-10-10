<?php

namespace GroupBuilder\Frontend;

use GroupBuilder\Traits\GroupBuilderHelperTrait;

class GroupBuilderFrontend
{
    use GroupBuilderHelperTrait;

    protected $tools;
    protected $has_tools;


    public function __construct()
    {
        $this->setup_shortcodes();
        $this->setup_frontend_actions();
    }


    private function setup_shortcodes()
    {
        add_shortcode('group_members', [$this, 'group_members_shortcode']);
        add_shortcode('group_edit_form', [$this, 'group_edit_form_shortcode']);
        add_shortcode('group_space_tools', [$this, 'group_space_tools_shortcode']);
        add_shortcode('group_events', [$this, 'group_events_shortcode']);
        add_shortcode('group_events_test', [$this, 'group_events_test_shortcode']);
        add_shortcode('element_channel', [$this, 'martrix_url_shortcode']);
        add_shortcode('faq_button', [$this, 'faq_button_shortcode']);
    }

    private function setup_frontend_actions()
    {
        add_action('blocksy:loop:card:end', [$this, 'get_avatar_list']);
        add_action('blocksy:comments:before', [$this, 'get_pinnwall_post_avatar_list']);
        add_action('blocksy:header:after', [$this, 'get_group_post_avatar_list']);
        add_action('blocksy:single:content:top', [$this, 'join_group_button']);
        add_action('blocksy:single:content:top', [$this, 'group_goal']);
        add_action('blocksy:comments:top', [$this, 'comments_header']);

        add_action('admin_bar_menu', [$this, 'set_adminbar'], 10000);

        add_action('wp', [$this, 'hide_comment_form_for_non_member']);
        add_filter('comments_open', [$this, 'hide_comments_for_non_member'], 10, 2);


        add_filter('acf/load_value/name=event_group_id', [$this, 'preset_event_group_id_value'], 10, 3);
        add_filter('acf/load_value/name=event_title', [$this, 'preset_event_title_value'], 10, 3);
        add_filter('acf/load_value/name=event_url', [$this, 'preset_event_url_value'], 10, 3);
        add_filter('acf/load_field/name=event_url', [$this, 'preset_event_url_defaulvalue'], 10, 1);
        add_filter('acf/load_field/name=event_visibility', [$this, 'preset_event_visibility_value'], 10, 1);

        add_action('init', [$this, 'download_ical']);
        add_action('init', [$this, 'set_user_meta_from_url'], 1);
    }

    public function faq_button_shortcode()
    {
        if (is_singular('group_post')) {
            return '';
        }
        return get_option('options_faq_button', '<a class="button" href="/faq/" class="faq-button">Wie funktioniert das?</a>');
    }

    public function preset_event_visibility_value($field)
    {
        if (is_admin()) {
            $field['default_value'] = 'guest';
        }
        return $field;

    }

    public function preset_event_group_id_value($value, $post_id, $field)
    {
        if (is_singular('group_post')) {
            $value = get_the_ID();
        }
        return $value;

    }

    public function preset_event_title_value($value, $post_id, $field)
    {
        if (is_singular('group_post')) {
            $value = 'Meeting der Gruppe "' . get_the_title() . '"';
        }
        return $value;

    }

    public function preset_event_url_defaulvalue($field)
    {
        if (is_admin()) {
            $field['default_value'] = get_option('options_videoconference_default_url');
        }
        return $field;

    }

    public function preset_event_url_value($value, $post_id, $field)
    {
        if (is_singular('group_post')) {
            $value = get_permalink() . "?group-space=1";
        }
        return $value;

    }

    /**
     * Session Replacement
     */
    public function set_user_meta_from_url()
    {
        //Wenn ein Invite-Code in der URL übergeben wurde, wird dieser in das User-Meta-Feld "_group_user_invite_code" geschrieben
        //dies ermöglicht etwa, den Invite-Code auch im Ajax-Requests zu verwenden
        if (isset($_GET['invite'])) {
            $invite = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['invite']);
            $user_id = get_current_user_id();
            update_user_meta($user_id, '_group_user_invite_code', $invite);
        }
    }

    public function group_members_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
        ), $atts, 'group_members');

        return $this->get_avatar_list($atts['post_id']);
    }

    public function group_edit_form_shortcode($atts)
    {
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

    public function martrix_url_shortcode()
    {
        $matrix_url = get_option('options_matrix_plenum_url');
        $template = get_option('options_martrix-widget-content');

        if ($matrix_url && $template) {
            $template = str_replace('%url%', $matrix_url, $template);

            return $template;
        }
        return '';
    }

    public function group_events_shortcode($atts)
    {
        global $post;
        $show_link = false;
        if (is_singular('group_post')) {
            if (is_user_logged_in() && $this->group_builder_user_can(get_the_ID(), 'edit')) {
                $show_link = true;
            }
            $group_id = get_the_ID();
        } else {
            $group_id = null;
        }
        //Wp_Query für Events erstellen
        if ($group_id) {
            $args = array(
                'post_type' => 'event_post',
                'meta_query' => array(
                    array(
                        'key' => 'event_group_id',
                        'value' => $group_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'event_start_date',
                        'value' => date('Y-m-d H:i:s'),
                        'compare' => '>',
                        'type' => 'DATETIME',
                    ),
                ),
                'orderby' => 'meta_value',
                'order' => 'ASC',
            );
        } else {
            $args = array(
                'post_type' => 'event_post',
                'meta_query' => array(
                    array(
                        'key' => 'event_start_date',
                        'value' => date('Y-m-d H:i:s'),
                        'compare' => '>',
                        'type' => 'DATETIME',
                    ),
                ),
                'orderby' => 'meta_value',
                'order' => 'ASC',
            );
        }
        $query = new \WP_Query($args);

        $html = '';
        if ($query->have_posts()) {
            $html .= '<ul class="main-events-lists">';
            while ($query->have_posts()) {
                $query->the_post();

                $event_title = get_field('event_title');
                $event_url = get_field('event_url');
                $event_start_date = get_field('event_start_date');
                $event_end_date = get_field('event_end_date');
                $event_group_id = get_field('event_group_id');
                $visible = get_field('event_visibility');


                //Zeitzone und Locale setzen
                setlocale(LC_TIME, 'de_DE');

                $date = date('d.m.Y', strtotime($event_start_date));
                //wochentag in deutsch
                $weekday = date('l', strtotime($event_start_date));
                $weekday = $this->get_german_weekday($weekday);

                $time = date('H:i', strtotime($event_start_date));
                $end_time = date('H:i', strtotime($event_end_date));

                if ($event_group_id && !$show_link) {
                    $show_event_link = ($this->group_builder_user_can($event_group_id, 'edit'));
                    $extra_class = 'my-group-event';
                }
                if ($visible == 'logged_in' && !is_user_logged_in()) {
                    $extra_class = 'member-event';
                    $show_event_link = true;
                } elseif ($visible == 'guest') {
                    $extra_class = 'public-event';
                    $show_event_link = true;
                }

                if ($show_link || $show_event_link) {
                    $html .= '<li class="event-entry ' . $extra_class . '">';
                    $html .= '<div class="date-row">';
                    $html .= '<a class="ical-picker calendar-button" title="In Kalender übernehmen" href="?ical=' . get_the_ID() . '"><span class="dashicons dashicons-calendar-alt"></span></a>';
                    $html .= '<p class="date"><span class="weekday">' . $weekday . '</span><br>' . $date . '</p>';
                    $html .= '</div><div class="description-row">';
                    $html .= '<a class="event-title" href="' . $event_url . '">' . $event_title . '</a>';
                    $html .= '<a href="' . $event_url . '" class="time">' . $time . ' - ' . $end_time . ' Uhr</a>';
                    $html .= '</li>';
                } else {

                    $html .= '<li class="event-entry">';
                    $html .= '<div class="date-row">';
                    $html .= '<div class="ical-picker calendar-button" title="In Kalender übernehmen"><span class="dashicons dashicons-calendar-alt"></span></div>';
                    $html .= '<div class="date"><span class="weekday">' . $weekday . '</span><br>' . $date . '</div>';
                    $html .= '</div><div class="description-row">';
                    $html .= '<p class="event-title">' . $event_title . '<br>';
                    $html .= '<span class="time">' . $time . ' - ' . $end_time . ' Uhr</span></p>';
                    $html .= '</li>';
                }

            }
            $html .= '</ul>';

        } else {
            $html .= '<p>Keine Termine.</p>';
        }

        wp_reset_postdata();
        return $html;
    }

    public function group_events_test_shortcode($atts)
    {
        global $post;
        $show_link = false;
        if (is_singular('group_post')) {
            if (is_user_logged_in() && $this->group_builder_user_can(get_the_ID(), 'edit')) {
                $show_link = true;
            }
            $group_id = get_the_ID();
        } else {
            $group_id = null;
        }
        //Wp_Query für Events erstellen
        if ($group_id) {
            $args = array(
                'post_type' => 'event_post',
                'meta_query' => array(
                    array(
                        'key' => 'event_group_id',
                        'value' => $group_id,
                        'compare' => '=',
                    ),
                    array(
                        'key' => 'event_start_date',
                        'value' => date('Y-m-d H:i:s'),
                        'compare' => '>',
                        'type' => 'DATETIME',
                    ),
                ),
                'orderby' => 'meta_value',
                'order' => 'ASC',
            );
        } else {
            $args = array(
                'post_type' => 'event_post',
                'meta_query' => array(
                    array(
                        'key' => 'event_start_date',
                        'value' => date('Y-m-d H:i:s'),
                        'compare' => '>',
                        'type' => 'DATETIME',
                    ),
                ),
                'orderby' => 'meta_value',
                'order' => 'ASC',
            );
        }
        $query = new \WP_Query($args);

        $html = '';
        if ($query->have_posts()) {
            $html .= '<ul class="main-events-lists">';
            while ($query->have_posts()) {
                $query->the_post();

                $event_title = get_field('event_title');
                $event_url = get_field('event_url');
                $event_start_date = get_field('event_start_date');
                $event_end_date = get_field('event_end_date');
                $event_group_id = get_field('event_group_id');
                $visible = get_field('event_visibility');


                //Zeitzone und Locale setzen
                setlocale(LC_TIME, 'de_DE');

                $date = date('d.m.Y', strtotime($event_start_date));
                //wochentag in deutsch
                $weekday = date('l', strtotime($event_start_date));
                $weekday = $this->get_german_weekday($weekday);

                $time = date('H:i', strtotime($event_start_date));
                $end_time = date('H:i', strtotime($event_end_date));

                if ($event_group_id && !$show_link) {
                    $show_event_link = ($this->group_builder_user_can($event_group_id, 'edit'));
                    $extra_class = 'my-group-event';
                }
                if ($visible == 'logged_in' && !is_user_logged_in()) {
                    $extra_class = 'member-event';
                    $show_event_link = true;
                } elseif ($visible == 'guest') {
                    $extra_class = 'public-event';
                    $show_event_link = true;
                }

                if ($show_link || $show_event_link) {
                    ob_start();
                    ?>
                    <div class="event-card">
                        <div class="event-card-header <?php echo $extra_class ?>">
                            <div class="event-card-day">
                                <?php echo date('d', strtotime($event_start_date)) ?>
                            </div>
                            <br>
                            <div class="event-card-weekday">
                                <?php echo $weekday ?>
                            </div>

                            <div class="event-card-header-subsection">
                                <?php echo date('m', strtotime($event_start_date)) ?>
                                <?php echo date('Y', strtotime($event_start_date)) ?>
                            </div>
                        </div>
                        <div class="event-card-description">
                            <?php echo $event_title ?>
                            <span class="time"><?php echo $time . ' - ' . $end_time ?> Uhr</span>
                        </div>
                        <div class="event-card-button-panel">
                            <a class="event-card-button">
                                <div class="event-card-button-icon meeting-button">
                                    <span class="dashicons dashicons-format-chat"></span>
                                </div>
                                <div class="event-card-button-label">Zum Gruppenchat</div>
                            </a>

                            <a class="event-card-button">
                                <div class="event-card-button-icon calendar-button">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                </div>
                                <div class="event-card-button-label">In den Kalender einbinden</div>
                            </a>

                            <a class="event-card-button">
                                <div class="event-card-button-icon delete-button">
                                    <span class="dashicons dashicons-trash"></span>
                                </div>
                                <div class="event-card-button-label">Termin löschen</div>
                            </a>
                        </div>
                    </div>

                    <?php
                    $html .= ob_get_clean();
                    $html .= '<li class="event-entry ' . $extra_class . '">';
                    $html .= '<div class="date-row">';
                    $html .= '<a class="ical-picker calendar-button" title="In Kalender übernehmen" href="?ical=' . get_the_ID() . '"><span class="dashicons dashicons-calendar-alt"></span></a>';
                    $html .= '<p class="date"><span class="weekday">' . $weekday . '</span><br>' . $date . '</p>';
                    $html .= '</div><div class="description-row">';
                    $html .= '<a class="event-title" href="' . $event_url . '">' . $event_title . '</a>';
                    $html .= '<a href="' . $event_url . '" class="time">' . $time . ' - ' . $end_time . ' Uhr</a>';
                    $html .= '</li>';
                } else {

                    $html .= '<li class="event-entry">';
                    $html .= '<div class="date-row">';
                    $html .= '<div class="ical-picker calendar-button" title="In Kalender übernehmen"><span class="dashicons dashicons-calendar-alt"></span></div>';
                    $html .= '<div class="date"><span class="weekday">' . $weekday . '</span><br>' . $date . '</div>';
                    $html .= '</div><div class="description-row">';
                    $html .= '<p class="event-title">' . $event_title . '<br>';
                    $html .= '<span class="time">' . $time . ' - ' . $end_time . ' Uhr</span></p>';
                    $html .= '</li>';
                }

            }
            $html .= '</ul>';

        } else {
            $html .= '<p>Keine Termine.</p>';
        }

        wp_reset_postdata();
        return $html;
    }

    public function download_ical()
    {
        if (isset($_GET['ical'])) {
            $event_id = intval($_GET['ical']);
            $ical_content = $this->generate_ical($event_id);

            if ($ical_content) {
                header('Content-Type: text/calendar; charset=utf-8');
                header('Content-Disposition: attachment; filename="event.ics"');
                echo $ical_content;
                exit;
            }
        }
    }

    public function group_space_tools_shortcode()
    {
        if ($this->group_builder_user_can(get_the_ID(), 'edit')) {
            $this->read_tools();
            if (!$tools = $this->tools)
                $tools = $this->read_tools();
            $html = '<ol><li><strong><a href="?group-space=1">Meeting Raum mit Etherpad</a></strong></li>';
            foreach ($tools as $tool) {
                $html .= '<li><a href="' . $tool['url'] . '">
                <span>' . $tool['name'] . '</span>
            </a></li>';
            }
            return $html . '</ol>';
        } else {
            return '<p>Um die Tools zu sehen, musst du Mitglied dieser Gruppe sein.</p>';
        }

    }

    public function read_tools()
    {
        $group_id = get_the_ID();

        $group_tool_matrixroom = get_field('group_tool_matrixroom', $group_id);       // url
        $group_tool_exclidraw = get_field('group_tool_exclidraw', $group_id);        // bool
        $group_tool_nuudel = get_field('group_tool_nuudel', $group_id);              // bool
        $group_tool_oer_maker = get_field('group_tool_oer_maker', $group_id);        // array
        $group_tool_custom_tools = get_field('group_tool_custom_tools', $group_id);    // array

        $tools = array();
        if ($group_tool_nuudel) {
            $url = 'https://nuudel.digitalcourage.de/create_poll.php?type=date';
            $tools[] = array('name' => 'Terminfinder (Nuudel)', 'url' => $url);
            $this->has_tools = true;
        }

        if ($group_tool_matrixroom) {
            $tools[] = array('name' => 'Matrix Channel (Messenger)', 'url' => $group_tool_matrixroom);
            $this->has_tools = true;
        }

        if ($group_tool_oer_maker) {
            $url = get_post_meta($group_id, 'group_tool_liascript_url', true);
            if (!$url) {
                $oer_id = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 24);
                $url = 'https://liascript.github.io/LiveEditor/?/edit/' . $oer_id . '/webrtc';
                update_post_meta(get_the_ID(), 'group_tool_liascript_url', $url);
            }
            $tools[] = array('name' => 'LiaScript Editor (Kollaborativer OER Editor)', 'url' => $url);
            $this->has_tools = true;
        }


        if ($group_tool_exclidraw) {
            $url = get_post_meta($group_id, 'group_tool_exclidraw_url', true);
            if (!$url) {
                // 22 zeichen langer schlüssel generieren
                $room = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 20);
                $random = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 22);
                $url = 'https://excalidraw.com/#room=' . $room . ',' . $random;
                update_post_meta(get_the_ID(), 'group_tool_exclidraw_url', $url);
            }
            $tools[] = array('name' => 'Whiteboard (Excalidraw)', 'url' => $url);
            $this->has_tools = true;
        }
        if ($group_tool_custom_tools) {
            error_log(print_r($group_tool_custom_tools, true));
            foreach ($group_tool_custom_tools as $tool) {
                if ($tool['active']) {
                    //$key = sanitize_key($tool['label']);
                    $tools[] = array('name' => $tool['label'], 'url' => $tool['url']);
                    $this->has_tools = true;
                }

            }
        }
        $this->tools = $tools;
        return $tools;

    }

    public function get_pinnwall_post_avatar_list()
    {
        if (is_singular(['pinwall_post'])) {
            $this->get_avatar_list();
        }
    }

    public function get_group_post_avatar_list()
    {
        if (is_singular(['group_post'])) {
            $this->get_avatar_list();
        }
    }

    public function join_group_button()
    {
        if (is_user_logged_in() && is_singular(['group_post']) && $this->group_builder_user_can(get_the_ID(), 'join')) {
            echo '<div class="group-builder-join-button-container">
                    <button class="join-group" data-post-id="' . get_the_ID() . '">
                        <span>Gruppe beitreten</span>
                    </button>
                </div>';
        }
    }

    public function group_goal()
    {
        if (is_singular(['group_post'])) {
            $goal = get_post_meta(get_the_ID(), 'group_goal', true);
            if ($goal) {
                echo '<div class="group-goal"><h2>Unser Ziel:</h2><p>' . $goal . '</p><h2>Herausforderungen und Schwerpunkte:</h2></div>';
            } else {
                $pin_id = get_post_meta(get_the_ID(), '_pinwall_post', true);
                $post = get_post($pin_id);
                $content = $post->post_content;
                $template = get_option('options_group_welcome_template');
                if ($template) {
                    $template = str_replace('%pin%', $content, $template);
                }
                echo $template;


            }

        }
    }

    public function comments_header()
    {
        if (is_singular(['group_post'])) {
            ?>
            <div class="group-builder-comment-header">
                <h2>Diskurs und Ergebnisse:</h2>
            </div>
            <?php
        } else if (is_singular(['pinwall_post'])) {
            ?>
            <div class="group-builder-comment-header">
                <h2>Diskussion:</h2>
            </div>
            <?php
        }
    }

    public function set_adminbar()
    {
        if (is_admin()) {
            return;
        }
        global $customized_wordpress_adminbar;

        $tools = $this->read_tools();

        $user = wp_get_current_user();
        $adminbar = $customized_wordpress_adminbar;
        $adminbar->remove('search');
        $adminbar->remove('logout');
        $adminbar->remove('user-info');

        $adminbar->edit('my-account', null, '/user/');
        #$adminbar->add('top-secondary', '', '#', ' ');
        $adminbar->add('user-actions', 'Profil ansehen', '/user/', 'dashicons-admin-users');
        $adminbar->add('user-actions', 'Profil bearbeiten', '/user?um_action=edit', 'dashicons-edit');
        $adminbar->add('user-actions', 'Meine Gruppen', '/user/wpadmin/?profiletab=group-builder', 'dashicons-groups');
        $adminbar->add('user-actions', 'Nachricht schreiben', '/account/fep-um/', 'dashicons-email-alt');

        $adminbar->add('user-actions', 'Konto Einstellungen', '/account/', 'dashicons-admin-settings');
        $adminbar->add('user-actions', 'Abmelden', wp_logout_url(), 'dashicons-exit');
        $parent = '';

        $parent_home = $adminbar->add('', get_bloginfo('name'), home_url(), 'dashicons-location');

        $adminbar->add($parent_home, 'Marktplatz / Termine', '/');
        $adminbar->add($parent_home, 'Pinnwand', '/pinwall_post/');
        $adminbar->add($parent_home, 'Gruppen', '/group_post/');
        $adminbar->add($parent_home, 'Mitglieder', '/netzwerk/');
        $adminbar->add($parent_home, 'Dokumente', '/dokument/');
        $adminbar->add($parent_home, 'Bedienungshilfen', '/faq/');
        if (is_user_logged_in()) {
            $adminbar->add('', '+ Pinnwand-Karte', '/pinnwand-karte-erstellen/', 'dashicons-admin-post');
        }
        if (current_user_can('administrator')) {
            $adminbar->add($parent_home, 'Konfiguration (Admin)', admin_url() . '/options-general.php?page=community-settings');
        }
        if (is_singular('pinwall_post') && is_user_logged_in()) {
            if ($this->group_builder_user_can(get_the_ID(), 'edit')) {
                $adminbar->addMegaMenu('', 'Bearbeiten', 'pin-edit-modal', 'dashicons-edit');
//
            }
        }

        if (is_singular('group_post')) {
            if ($this->group_builder_user_can(get_the_ID(), 'edit')) {


                if (get_query_var('group-space')) {
                    $group_view = $adminbar->add($parent, 'Zur Gruppenseite', '?', 'dashicons-exit');
                    $adminbar->addMegaMenu($group_view, 'Integrationen', 'group-tools-modal', 'dashicons-admin-generic');
                    $meeting = $adminbar->add($parent, 'Meeting', '#', 'dashicons-welcome-learn-more');
                    $this->display_modal_frame('group_config');
                } else {
                    $group_edit = $adminbar->addMegaMenu($parent, 'Gruppe bearbeiten', 'group-edit-modal', 'dashicons-edit');
                    $meeting = $adminbar->add($parent, 'Meeting', '?group-space=1', 'dashicons-welcome-learn-more');
                    $join_option = get_post_meta(get_the_ID(), '_join_option', true);

                    if ($join_option) {
                        $inviter = '<h1>Einladung zur Gruppe kopieren</h1>';
                        $inviter .= '<p>Um andere Mitglieder in diese Gruppe einzuladen, kopiere die Nachricht und füge sie in eine Mail an einen User ein.</p>';
                        $inviter .= '<div class="copy-invite-link-wrapper">';
                        $inviter .= '<span class="copy-invite-message-label">Einladungsnachricht für weitere Mitglieder:</span><button class="button copy-invite-message" data-post-id="' . get_the_ID() . '">Einladung kopieren</button></div>';
                        $inviter .= '<div id="invite-message-container"></div>';
                        $inviter .= '</div>';

                        $adminbar->addMegaMenu($group_edit, 'Einladelink', 'group-invite-modal', 'dashicons-email');
                        $adminbar->addMegaMenuContent('group-invite-modal', $inviter);
                    }
                    $adminbar->addMegaMenu($group_edit, 'Integrationen', 'group-tools-modal', 'dashicons-admin-generic');
                }
                $adminbar->addMegaMenu($parent, '+ Termin', 'group-events-modal', 'dashicons-calendar');

                if ($this->has_tools) {
                    // $tools = $adminbar->add('', 'Werkzeuge', '#','dashicons-admin-tools');
                    foreach ($this->tools as $tool) {
                        $meta = array('target' => '_blank');
                        $adminbar->add($meeting, $tool['name'], $tool['url'], 'dashicons-marker', $meta);
                    }

                }

            }

        }
        $this->display_modal_frame();
        $adminbar->add_modal_and_mega_menu_html();
    }

    /**
     * Zeigt das Modal-Fenster für die Gruppenbearbeitung an
     * Hinweis: Wir nutzen die Klassen-Definitionen von meinem custom_wp_adminbar Plugin
     */
    public function display_modal_frame()
    {
        if (is_singular('group_post') && $this->group_builder_user_can(get_the_ID(), 'edit')) {
            ?>
            <!-- Group Modal -->
            <div id="group-edit-modal" class="custom-modal" style="display: none;">
                <div class="custom-modal-content">
                    <span class="custom-modal-close">&times;</span>
                    <h2>Gruppe bearbeiten</h2>
                    <?php $this->invite_link_copy_button(); ?><br>
                    <?php echo do_shortcode('[acfe_form name="edit_group"]'); ?>

                </div>
            </div>
            <div id="group-tools-modal" class="custom-modal" style="display: none;">
                <div class="custom-modal-content">
                    <span class="custom-modal-close">&times;</span>
                    <h3>Werkzeuge und Integrationen für diese Gruppe</h3>
                    <?php echo do_shortcode('[acfe_form name="config-tools"]'); ?>

                </div>
            </div>
            <div id="group-events-modal" class="custom-modal" style="display: none;">
                <div class="custom-modal-content">
                    <span class="custom-modal-close">&times;</span>
                    <h3>Neuer Termin</h3>
                    <?php echo do_shortcode('[acfe_form name="community-events"]'); ?>

                </div>
            </div>
            <?php
        } elseif (is_singular('pinwall_post') && $this->group_builder_user_can(get_the_ID(), 'edit')) {
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

    /**
     * Gibt den HTML-Code für den Einladungslink zurück
     * @return string
     */
    public function invite_link_copy_button()
    {
        if (!is_singular('group_post')) {
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
        $group_id = get_the_ID();
        $join_option = get_post_meta($group_id, '_join_option', true);

        if ($join_option) {
            $link = $this->get_invite_link($group_id);
            $message = $this->get_invite_message($group_id);
            echo '<div class="copy-invite-link-wrapper">';
            echo '<span class="copy-invite-link-label">Weitere Mitglieder einladen:</span><button class="button copy-invite-link" data-post-id="' . $group_id . '">Einladungslink kopieren</button></div>';
            echo '<div style="display: none"><input type="text" name="invite-link" id="invite-link" value="' . $link . '">';
            echo '<input type="text" name="invite-message" id="invite-message" value="' . $message . '">';
            echo '</div>';
        }

        echo $this->leave_button($group_id);
    }

    private function leave_button($group_id)
    {
        return '<div class="leave-btn-wrapper"><span class="leave-group-label">Deine Mitarbeit in der Gruppe beenden: </span><button class="leave-group" data-post-id="' . $group_id . '">Gruppe verlassen</button></div>';
    }

    // Funktion zum Ausblenden des Kommentarbereichs

    function hide_comments_for_non_member($open, $post_id)
    {
        // Überprüfen, ob der Benutzer angemeldet ist
        if (!$this->group_builder_user_can($post_id, 'edit')) {
            // Hole den Post-Typ
            $post = get_post($post_id);

            // Überprüfen, ob es sich um den gewünschten post_type handelt (z.B. 'your_post_type')
            if ($post->post_type == 'group_post') {
                return false; // Schließt den Kommentarbereich
            }
        }
        return $open; // Gibt den ursprünglichen Wert zurück
    }

    // Kommentarformular ausblenden, wenn der Benutzer nicht angemeldet ist
    function hide_comment_form_for_non_member()
    {
        // Überprüfen, ob der Benutzer nicht angemeldet ist
        error_log('hide_comment_form_for_non_member');
        if (!$this->group_builder_user_can(get_the_ID(), 'edit')) {
            // Hole den aktuellen Post
            $type = get_post_type(get_the_ID());

            // Überprüfen, ob es sich um den gewünschten post_type handelt (z.B. 'your_post_type')
            if ($type == 'group_post') {
                error_log('remove_action comment_form');
                // Kommentarformular ausblenden
                remove_action('comment_form', 'comment_form', 10);
            }
        }
    }


}
