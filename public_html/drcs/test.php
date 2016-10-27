<?php
if(!defined('__XE__')) exit();
$logged_info = Context::get('logged_info');

$mid = Context::get('mid');

/*
$oMemberModel = &getModel('member');
$oMemberController= &getController('member');
$oMemberView= &getView('member');
*/


/*
var_dump($oMemberModel);
var_dump($oMemberController);
var_dump($oMemberView);
*/
/*
$id = $logged_info->user_id;
$name = $logged_info->user_name;
$nick = $logged_info->nick_name;
$birth = $logged_info->birthday;
$member_srl = $logged_info->member_srl;
*/

if( $logged_info->is_admin != 'Y') {
        if(!is_group("준회원", $logged_info->group_list)) {

                echo "권한없음";
                return;
        }
}

function is_group($group, $grouplist) {

        foreach ($grouplist as $key => $value) {

                if($value == $group)
                        return true;
        }
        return false;

}


echo "TEST";

?>
