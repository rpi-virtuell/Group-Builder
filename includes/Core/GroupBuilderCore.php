<?php
namespace GroupBuilder\Core;

use GroupBuilder\Ajax\GroupBuilderAjax;
use GroupBuilder\Frontend\GroupBuilderFrontend;
use GroupBuilder\Integrations\GroupBuilderIntegrations;
use GroupBuilder\Admin\GroupBuilderAdmin;
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
        new GroupBuilderAdmin();
        new GroupBuilderColor();
    }

    private function setup_actions() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 800);
        add_action('group_builder_cleanup', [$this, 'cleanup_orphaned_data']);

        if (!wp_next_scheduled('group_builder_cleanup')) {
            wp_schedule_event(time(), 'daily', 'group_builder_cleanup');
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script('group-builder-js', plugin_dir_url(__FILE__) . '../../js/group-builder.js', array('jquery'), '1.0', true);
        wp_enqueue_style('group-builder-css', plugin_dir_url(__FILE__) . '../../css/colors.css');
        wp_enqueue_style('group-builder-colors-css', plugin_dir_url(__FILE__) . '../../css/group-builder.css');
        wp_localize_script('group-builder-js', 'group_builder_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
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
}
