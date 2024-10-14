<?php
namespace GroupBuilder\Admin;

class GroupBuilderAdmin {
    public function __construct() {
        $this->setup_admin_actions();
    }

    private function setup_admin_actions() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Register the settings for the group builder
     */
    public function register_settings() {

        //fallback if ACF is not installed

       if(!function_exists('get_field')){

           include_once 'acf.php';
           include_once 'acfe.php';


       }

    }

}
