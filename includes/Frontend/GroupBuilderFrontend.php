<?php
namespace GroupBuilder\Frontend;

use GroupBuilder\Traits\GroupBuilderHelperTrait;

class GroupBuilderFrontend {
    use GroupBuilderHelperTrait;

    protected $tools;
    protected $has_tools;


    public function __construct() {
        $this->setup_shortcodes();
        $this->setup_frontend_actions();
    }


    private function setup_shortcodes() {
        add_shortcode('group_members', [$this, 'group_members_shortcode']);
        add_shortcode('group_edit_form', [$this, 'group_edit_form_shortcode']);
        add_shortcode('group_space_tools', [$this, 'group_space_tools_shortcode']);
    }

    private function setup_frontend_actions() {
        add_action('blocksy:loop:card:end', [$this, 'get_avatar_list']);
        add_action('blocksy:comments:before', [$this, 'get_pinnwall_post_avatar_list']);
        add_action('blocksy:header:after', [$this, 'get_group_post_avatar_list']);
        add_action('blocksy:single:content:top', [$this, 'join_group_button']);
        add_action('blocksy:single:content:top', [$this, 'group_goal']);
        add_action('blocksy:comments:top', [$this, 'comments_header']);

        add_action('admin_bar_menu', [$this, 'set_adminbar'], 10000);
    }

    public function group_members_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
        ), $atts, 'group_members');

        return $this->get_avatar_list($atts['post_id']);
    }

    public function group_edit_form_shortcode($atts) {
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

    public function group_space_tools_shortcode()
    {
        if(!$tools = $this->tools)
            $tools = $this->read_tools();
        $html = '';
        foreach ($tools as $tool) {
            $html .= '<li><a href="'.$tool['url'].'">
                <span>' . $tool['name'] . '</span>
            </a></li>';
        }
        return $html;
    }
    public function get_pinnwall_post_avatar_list(){
        if(is_singular(['pinwall_post'])){
            $this->get_avatar_list();
        }
    }

    public function get_group_post_avatar_list(){
        if(is_singular(['group_post'])){
            $this->get_avatar_list();
        }
    }

    public function join_group_button(){
        if(is_user_logged_in() && is_singular(['group_post']) && $this->group_builder_user_can(get_the_ID(), 'join')){
            echo '<div class="group-builder-join-button-container">
                    <button class="join-group" data-post-id="'.get_the_ID().'">
                        <span>Gruppe beitreten</span>
                    </button>
                </div>';
        }
    }

    public function group_goal(){
        if(is_singular(['group_post'])){
            $goal = get_post_meta(get_the_ID(), 'group_goal', true);
            if($goal){
                echo '<div class="group-goal"><h2>Unser Ziel:</h2><p>'.$goal.'</p><h2>Herausforderungen und Schwerkunkte:</h2></div>';
            }
        }
    }

    public function comments_header(){
        if(is_singular(['group_post'])){
            ?>
            <div class="group-builder-comment-header">
                <h2>Diskurs und Ergebnisse:</h2>
            </div>
            <?php
        }
        else if(is_singular(['pinwall_post'])){
            ?>
            <div class="group-builder-comment-header">
                <h2>Diskussion:</h2>
            </div>
            <?php
        }
    }

    public function read_tools()
    {
        $group_id = get_the_ID();

        $group_tool_matrixroom = get_field('group_tool_matrixroom',$group_id);       // url
        $group_tool_exclidraw = get_field('group_tool_exclidraw', $group_id);        // bool
        $group_tool_nuudel = get_field('group_tool_nuudel', $group_id);              // bool
        $group_tool_oer_maker = get_field('group_tool_oer_maker', $group_id);        // array
        $group_tool_custom_tools = get_field('group_tool_custom_tools', $group_id);    // array

        $tools = array();
        if ($group_tool_nuudel) {
            $url = 'https://nuudel.digitalcourage.de/create_poll.php?type=date';
            $tools[]=array('name'=>'Terminfinder','url'=>$url);
            $this->has_tools = true;
        }

        if($group_tool_matrixroom){
            $tools[]=array('name'=>'Matrix Channel','url'=>$group_tool_matrixroom);
            $this->has_tools = true;
        }

        if($group_tool_oer_maker){
            $url = get_post_meta($group_id, 'group_tool_liascript_url', true);
            if(!$url){
                $oer_id = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 24);
                $url = 'https://liascript.github.io/LiveEditor/?/edit/'.$oer_id.'/webrtc';
                update_post_meta(get_the_ID(), 'group_tool_liascript_url', $url);
            }
            $tools[]=array('name'=>'LiaScript Editor','url'=>$url);
            $this->has_tools = true;
        }


        if($group_tool_exclidraw){
            $url = get_post_meta($group_id, 'group_tool_exclidraw_url', true);
            if(!$url){
                // 22 zeichen langer schlüssel generieren
                $room = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 20);
                $random = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 22);
                $url = 'https://excalidraw.com/#room='.$room.','.$random;
                update_post_meta(get_the_ID(), 'group_tool_exclidraw_url', $url);
            }
            $tools[]=array('name'=>'Whiteboard','url'=>$url);
            $this->has_tools = true;
        }
        if($group_tool_custom_tools){
            error_log(print_r($group_tool_custom_tools, true));
            foreach($group_tool_custom_tools as $tool){
                if($tool['active']){
                    //$key = sanitize_key($tool['label']);
                    $tools[]=array('name'=>$tool['label'],'url'=>$tool['url']);
                    $this->has_tools = true;
                }

            }
        }
        $this->tools = $tools;
        return $tools;

    }

    public function set_adminbar() {
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

        $parent_home = $adminbar->add('', get_bloginfo('name'), home_url(), 'dashicons-admin-home');
        if (is_user_logged_in()) {
            $adminbar->add($parent_home, 'Leute', '/netwerk/');
            $adminbar->add($parent_home, 'Gruppen', '/group_post/');
            $adminbar->add('', 'Pinwand Karte', '/pinwand-karte-erstellen/', 'dashicons-plus');
        }
        if (is_singular('pinwall_post') && is_user_logged_in()) {
            if ($this->group_builder_user_can( get_the_ID(), 'edit')) {
                $adminbar->addMegaMenu('', 'Bearbeiten', 'pin-edit-modal', 'dashicons-edit');
//
            }
        }

        if (is_singular('group_post')) {
            if ($this->group_builder_user_can(get_the_ID(), 'edit')) {


                if(get_query_var('group-space') ) {
                    $group_view = $adminbar->add($parent, 'Zur Gruppenseite', '?', 'dashicons-exit');

                    $adminbar->addMegaMenu($group_view, 'Integrationen', 'group-tools-modal', 'dashicons-admin-generic');
                    $this->display_modal_frame('group_config');
                }else{
                    $group_edit = $adminbar->addMegaMenu($parent, 'Gruppe bearbeiten', 'group-edit-modal', 'dashicons-edit');
                    $adminbar->add($parent, 'Meeting', '?group-space=1', 'dashicons-welcome-learn-more');
                    $adminbar->addMegaMenu($group_edit, 'Integrationen', 'group-tools-modal', 'dashicons-admin-generic');
                }

                if($this->has_tools){
                    $tools = $adminbar->add('', 'Werkzeuge', '#','dashicons-admin-tools');
                    foreach($this->tools as $tool){
                        $meta = array('target'=>'_blank');
                        $adminbar->add($tools, $tool['name'], $tool['url'], 'dashicons-marker',$meta);
                    }

                }

            }

        }
        $this->display_modal_frame();
        $adminbar->add_modal_and_mega_menu_html();
    }
    /**
     * Gibt den HTML-Code für den Einladungslink zurück
     * @return string
     */
    public function invite_link_copy_button(){
        if(!is_singular('group_post')){
            return;
        }
        if(!is_user_logged_in()){
            return;
        }
        $group_id = get_the_ID();
        $join_option = get_post_meta($group_id, '_join_option', true);

        if($join_option){
            $link = $this->get_invite_link($group_id);
            echo '<div class="copy-invite-link-wrapper">';
            echo '<span class="copy-invite-link-label">Weitere Mitglieder einladen:</span><button class="button copy-invite-link" data-post-id="' . $group_id . '">Einladungslink kopieren</button></div>';
            echo '<div style="display: none"><input type="text" name="invite-link" id="invite-link" value="' . $link . '">';
            echo '</div>';
        }

        echo $this->leave_button($group_id);
    }
    private function leave_button($group_id){
        return '<div class="leave-btn-wrapper"><span class="leave-group-label">Deine Mitarbeit in der Gruppe beenden: </span><button class="leave-group" data-post-id="' . $group_id . '">Gruppe verlassen</button></div>';
    }

    /**
     * Zeigt das Modal-Fenster für die Gruppenbearbeitung an
     * Hinweis: Wir nutzen die Klassen-Definitionen von meinem custom_wp_adminbar Plugin
     */
    public function display_modal_frame() {
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
                    <?php echo do_shortcode('[acfe_form name="config-tools"]');?>

                </div>
            </div>
            <?php
        }
        elseif (is_singular('pinwall_post') && $this->group_builder_user_can( get_the_ID(), 'edit')) {
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
}
