<?php

class Post_Color
{

    public function __construct($post_id) {
        return $this->get_post_it_color($post_id);
    }
    function generate_post_it_colors() {
        // Überprüfe, ob die Farben bereits in der wp_options gespeichert sind
        $colors = get_option('post_it_colors');

        // Wenn noch keine Farben vorhanden sind, generiere 12 Farben
        if (!$colors) {
            $colors = [];
            for ($i = 0; $i < 12; $i++) {
                $colors[] = $this->generate_contrast_color();
            }
            // Speichere die generierten Farben in wp_options
            update_option('post_it_colors', $colors);
        }

        return $colors;
    }

    function generate_contrast_color() {
        // Helligkeit der Farben für gute Lesbarkeit mit schwarzer Schrift
        $min_brightness = 130;

        do {
            // Generiere eine zufällige Farbe
            $color = sprintf('#%02X%02X%02X', mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            // Berechne die Helligkeit der Farbe
            $brightness = $this->calculate_brightness($color);
        } while ($brightness < $min_brightness);

        return $color;
    }

    function calculate_brightness($hex) {
        // Entferne das "#" Zeichen, wenn vorhanden
        $hex = str_replace('#', '', $hex);

        // Wandle HEX in RGB um
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Berechne die Helligkeit (simplifiziert)
        return ($r * 299 + $g * 587 + $b * 114) / 1000;
    }

    function get_post_it_color($post_id) {
        // Überprüfe, ob die Farbe bereits als Meta-Information gespeichert ist
        $color = get_post_meta($post_id, '_post_it_color', true);

        if (!$color) {
            // Hol die Liste der 12 Farben
            $colors = $this->generate_post_it_colors();

            // Wähle eine zufällige Farbe aus
            $color = $colors[array_rand($colors)];

            // Speichere die Farbe als Post-Meta
            update_post_meta($post_id, '_post_it_color', $color);
        }

        return $color;
    }

}
