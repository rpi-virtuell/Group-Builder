<?php
namespace GroupBuilder\Color;

class GroupBuilderColor
{

    public function __construct() {

        add_filter( 'post_class', [$this,'add_post_class'], 10, 3 );
        add_action('wp_head', [$this,'add_stylesheet_colors']);

    }
    public function add_post_class($classes, $css, $post_id) {

        $post_type = get_post_type($post_id);
        if($post_type==='pinwall_post') {
            $class = $this->get_post_it_class($post_id);
            array_push($classes, $class);
        }
        return $classes;
    }
    public  function set_post_it_colors() {
        $static_colors = get_option('post_it_colors');
        if (empty($static_colors)){
            $static_colors = ["Gold", "Yellow",
                "Lime", "Cyan", "Magenta", "Orange",
                "LightCoral", "LightSalmon", "LightGreen",
                "LightSkyBlue", "LightPink", "LightGoldenRodYellow"];

            update_option('post_it_colors', $static_colors);
        }

        return $static_colors;
    }

    /**
     * @param $colors
     * @return void
     * Generate a Stylesheet for each color
     */
    public function add_stylesheet_colors(){
        $colors = $this->set_post_it_colors();
        echo '<style>';
        foreach ($colors as $color) {
            $class_name = 'post_it_'.$color;

            $rgba_color = $this->hexToRgba($this->css3ColorToHex($color), 0.5);

            echo "\n.$class_name { 
                background-image: linear-gradient(to top right, rgba(255,255,255,1),$rgba_color);
            }\n";

        }
        echo '</style>';
    }

    public function get_post_it_class($post_id) {
        // Überprüfe, ob die Farbe bereits als Meta-Information gespeichert ist
        $color = get_post_meta($post_id, '_post_it_color', true);

        if (!$color) {
            // Hol die Liste der 12 Farben
            $colors = $this->set_post_it_colors();

            // Wähle eine zufällige Farbe aus
            $color = $colors[array_rand($colors)];

            // Speichere die Farbe als Post-Meta
            update_post_meta($post_id, '_post_it_color', $color);
        }

        return 'post_it_'.$color;
    }


    function css3ColorToHex($colorName)
    {
        $colors = array(
            "AliceBlue" => "#F0F8FF",
            "AntiqueWhite" => "#FAEBD7",
            "Aqua" => "#00FFFF",
            "Aquamarine" => "#7FFFD4",
            "Azure" => "#F0FFFF",
            "Beige" => "#F5F5DC",
            "Bisque" => "#FFE4C4",
            "Black" => "#000000",
            "BlanchedAlmond" => "#FFEBCD",
            "Blue" => "#0000FF",
            "BlueViolet" => "#8A2BE2",
            "Brown" => "#A52A2A",
            "BurlyWood" => "#DEB887",
            "CadetBlue" => "#5F9EA0",
            "Chartreuse" => "#7FFF00",
            "Chocolate" => "#D2691E",
            "Coral" => "#FF7F50",
            "CornflowerBlue" => "#6495ED",
            "Cornsilk" => "#FFF8DC",
            "Crimson" => "#DC143C",
            "Cyan" => "#00FFFF",
            "DarkBlue" => "#00008B",
            "DarkCyan" => "#008B8B",
            "DarkGoldenRod" => "#B8860B",
            "DarkGray" => "#A9A9A9",
            "DarkGreen" => "#006400",
            "DarkKhaki" => "#BDB76B",
            "DarkMagenta" => "#8B008B",
            "DarkOliveGreen" => "#556B2F",
            "DarkOrange" => "#FF8C00",
            "DarkOrchid" => "#9932CC",
            "DarkRed" => "#8B0000",
            "DarkSalmon" => "#E9967A",
            "DarkSeaGreen" => "#8FBC8F",
            "DarkSlateBlue" => "#483D8B",
            "DarkSlateGray" => "#2F4F4F",
            "DarkTurquoise" => "#00CED1",
            "DarkViolet" => "#9400D3",
            "DeepPink" => "#FF1493",
            "DeepSkyBlue" => "#00BFFF",
            "DimGray" => "#696969",
            "DodgerBlue" => "#1E90FF",
            "FireBrick" => "#B22222",
            "FloralWhite" => "#FFFAF0",
            "ForestGreen" => "#228B22",
            "Fuchsia" => "#FF00FF",
            "Gainsboro" => "#DCDCDC",
            "GhostWhite" => "#F8F8FF",
            "Gold" => "#FFD700",
            "GoldenRod" => "#DAA520",
            "Gray" => "#808080",
            "Green" => "#008000",
            "GreenYellow" => "#ADFF2F",
            "HoneyDew" => "#F0FFF0",
            "HotPink" => "#FF69B4",
            "IndianRed" => "#CD5C5C",
            "Indigo" => "#4B0082",
            "Ivory" => "#FFFFF0",
            "Khaki" => "#F0E68C",
            "Lavender" => "#E6E6FA",
            "LavenderBlush" => "#FFF0F5",
            "LawnGreen" => "#7CFC00",
            "LemonChiffon" => "#FFFACD",
            "LightBlue" => "#ADD8E6",
            "LightCoral" => "#F08080",
            "LightCyan" => "#E0FFFF",
            "LightGoldenRodYellow" => "#FAFAD2",
            "LightGray" => "#D3D3D3",
            "LightGreen" => "#90EE90",
            "LightPink" => "#FFB6C1",
            "LightSalmon" => "#FFA07A",
            "LightSeaGreen" => "#20B2AA",
            "LightSkyBlue" => "#87CEFA",
            "LightSlateGray" => "#778899",
            "LightSteelBlue" => "#B0C4DE",
            "LightYellow" => "#FFFFE0",
            "Lime" => "#00FF00",
            "LimeGreen" => "#32CD32",
            "Linen" => "#FAF0E6",
            "Magenta" => "#FF00FF",
            "Maroon" => "#800000",
            "MediumAquaMarine" => "#66CDAA",
            "MediumBlue" => "#0000CD",
            "MediumOrchid" => "#BA55D3",
            "MediumPurple" => "#9370DB",
            "MediumSeaGreen" => "#3CB371",
            "MediumSlateBlue" => "#7B68EE",
            "MediumSpringGreen" => "#00FA9A",
            "MediumTurquoise" => "#48D1CC",
            "MediumVioletRed" => "#C71585",
            "MidnightBlue" => "#191970",
            "MintCream" => "#F5FFFA",
            "MistyRose" => "#FFE4E1",
            "Moccasin" => "#FFE4B5",
            "NavajoWhite" => "#FFDEAD",
            "Navy" => "#000080",
            "OldLace" => "#FDF5E6",
            "Olive" => "#808000",
            "OliveDrab" => "#6B8E23",
            "Orange" => "#FFA500",
            "OrangeRed" => "#FF4500",
            "Orchid" => "#DA70D6",
            "PaleGoldenRod" => "#EEE8AA",
            "PaleGreen" => "#98FB98",
            "PaleTurquoise" => "#AFEEEE",
            "PaleVioletRed" => "#DB7093",
            "PapayaWhip" => "#FFEFD5",
            "PeachPuff" => "#FFDAB9",
            "Peru" => "#CD853F",
            "Pink" => "#FFC0CB",
            "Plum" => "#DDA0DD",
            "PowderBlue" => "#B0E0E6",
            "Purple" => "#800080",
            "RebeccaPurple" => "#663399",
            "Red" => "#FF0000",
            "RosyBrown" => "#BC8F8F",
            "RoyalBlue" => "#4169E1",
            "SaddleBrown" => "#8B4513",
            "Salmon" => "#FA8072",
            "SandyBrown" => "#F4A460",
            "SeaGreen" => "#2E8B57",
            "SeaShell" => "#FFF5EE",
            "Sienna" => "#A0522D",
            "Silver" => "#C0C0C0",
            "SkyBlue" => "#87CEEB",
            "SlateBlue" => "#6A5ACD",
            "SlateGray" => "#708090",
            "Snow" => "#FFFAFA",
            "SpringGreen" => "#00FF7F",
            "SteelBlue" => "#4682B4",
            "Tan" => "#D2B48C",
            "Teal" => "#008080",
            "Thistle" => "#D8BFD8",
            "Tomato" => "#FF6347",
            "Turquoise" => "#40E0D0",
            "Violet" => "#EE82EE",
            "Wheat" => "#F5DEB3",
            "White" => "#FFFFFF",
            "WhiteSmoke" => "#F5F5F5",
            "Yellow" => "#FFFF00",
            "YellowGreen" => "#9ACD32"
        );

        $color = isset($colors[$colorName]) ? $colors[$colorName] : null;
        if(!$color){
            error_log($colorName);
        }
        return $color ;
    }

    function hexToRgba($hex, $alpha = 1.0) {
        // Entferne das Hashtag (#) falls vorhanden
        $hex = str_replace('#', '', $hex);

        // Prüfe die Länge des Hex-Codes
        if(strlen($hex) == 3) {
            // Falls 3 Zeichen, erweitere auf 6 Zeichen (z.B. #FFF -> #FFFFFF)
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } elseif(strlen($hex) == 6) {
            // Falls 6 Zeichen, extrahiere die jeweiligen RGB-Werte
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            // Ungültiger Hex-Code
            return null;
        }

        // Korrigiere den Alpha-Wert, falls nötig
        $alpha = max(0, min(1, $alpha)); // Alpha muss zwischen 0 und 1 liegen

        // Erzeuge den RGBA-Wert als String
        return "rgba($r, $g, $b, $alpha)";
    }

}
