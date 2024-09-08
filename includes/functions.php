<?php

function is_user_groupmember()
{
    if (is_user_logged_in()) {

        $group_builder = GroupBuilder\Core\GroupBuilderCore::get_instance();
        return $group_builder->group_builder_user_can(get_current_user_id());
    }
    return false;

}

add_action('init', 'is_user_groupmember');
