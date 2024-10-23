<?php
/**
 * @description: This file contains helper functions to interact with the group builder plugin
 * @package: group-builder
 */

/**
 * @param $group_post_id
 * @return bool
 */
function is_user_group_post_member($group_post_id)
{
    if (is_user_logged_in()) {

        $members = get_post_meta($group_post_id, '_group_members', true);
        if($members){
            return in_array(get_current_user_id(), $members);
        }else{
            return false;
        }



    }
    return false;

}
/**
 * @param $pinwall_post_id
 * @return bool
 */
function is_user_pinwall_post_member($pinwall_post_id)
{
    if (is_user_logged_in()) {

        $members = get_post_meta($pinwall_post_id, '_interested_users', true);
        if($members){
            return in_array(get_current_user_id(), $members);
        }
        return false;

    }
    return false;

}
/**
 * @param $group_post_id
 * @return array|null
 */
function group_post_member($group_post_id)
{
    return get_post_meta($group_post_id, '_group_members', true);
}
/**
 * @param $pinwall_post_id
 * @return array|null
 */
function pinwall_post_member($pinwall_post_id){
    return get_post_meta($pinwall_post_id, '_interested_users', true);
}

function is_user_approved()
{
    if(is_user_logged_in()){
        if(!get_option('options_set_new_user_pending', true)){
            return true;
        }

        $user_id = get_current_user_id();
        $status = get_user_meta($user_id, 'account_status', true);
        return $status === 'approved';
    }
    return false;

}