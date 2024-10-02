<?php
namespace GroupBuilder\Admin;

class GroupBuilderAdmin {
    public function __construct() {
        $this->setup_admin_actions();
    }

    private function setup_admin_actions() {
        add_action('admin_init', [$this, 'register_settings']);
    }


    public function register_settings() {
    }

}
