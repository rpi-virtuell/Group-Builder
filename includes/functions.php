<?php

//function group_builder_user_can($group_id, $action = 'edit') {
//    $current_user_id = get_current_user_id();
//    $group_members = get_post_meta($group_id, '_group_members', true);
//
//    if ($action === 'join') {
//        if (is_array($group_members) && in_array($current_user_id, $group_members)) {
//            return false;
//        }
//        $join_option = get_post_meta($group_id, '_join_option', true);
//        $hash = get_post_meta($group_id, '_invite_hash', true);
//        return !$join_option || (isset($_GET['invite']) && $hash === $_GET['invite']);
//    }
//
//    if (!is_array($group_members)) {
//        return false;
//    }
//
//    if ($action === 'edit' || $action === 'invite') {
//        return in_array($current_user_id, $group_members);
//    }
//
//    return false;
//}
