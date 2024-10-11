<?php
/*
Plugin Name: Group-Builder
Description: Ermöglicht Nutzern, Interesse an Beiträgen zu zeigen und Gruppen zu gründen.
Version: 2.2.2
Author: Joachim Happel
*/

if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . 'includes/functions.php';

// Autoloader-Funktion
spl_autoload_register(function ($class) {
    $prefix = 'GroupBuilder\\';
    $base_dir = plugin_dir_path(__FILE__) . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use GroupBuilder\Core\GroupBuilderCore;

// Initialisierung des Plugins
add_action('plugins_loaded', [GroupBuilderCore::class, 'get_instance']);
