<?php
$now_tiem = time();
@file_put_contents('tree.log', "运行时间:" . date("Y-m-d H:i:s",$now_tiem) . " | time:" . $now_tiem . "\n", FILE_APPEND);
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/4/27
 * Time: 19:18
 */
define('S_ROOT', dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
ini_set('max_execution_time', '0');
ini_set('memory_limit', '1024M');


$db = getconn();
//用户列表
$userList = $db->getall("select id, parent_id, username from un_user where reg_type  NOT IN(8,9,11)");
$users = array();
foreach ($userList as $v) {
    $users[$v['id']] = $v;
}

function index()
{
    global $users;
    foreach ($users as $v) {
        if($v['parent_id']!=0){
            $res = get_parent($v['parent_id'],$users);
            $parent = ','.implode(',',$res[0]).',';
        }else{
            $parent = ',';
            $res[1] = 1;
        }
        $db->query("INSERT INTO `un_user_tree` (`user_id`, `pids`, `layer`) VALUES ({$v['id']}, '".$parent."', {$res[1]})");
       //echo $parent." 用户层级: $res[1]<br>";
        @file_put_contents('tree.log', "用户id :" . $v['id'] . " | 用户名 :" . $v['username']  . " | 用户层级 :" . $res[1] ."\n父级用户 :" . $parent .  "\n\n", FILE_APPEND);
    }
}

//获取父级ids
function get_parent($uid,$users,$layer=2){
    $user = array($uid);
    if($users[$uid]['parent_id'] != 0){
        ++$layer;
        $res = get_parent($users[$uid]['parent_id'],$users,$layer);
        $user = array_merge($user,$res[0]);
        $layer=$res[1];
    }
    return array($user,$layer);
}
//获取子级ids 直属
function get_son($uid,$users){
    unset($users[$uid]);
    $user = array($uid);
    foreach ($users as $v) {
        if ($uid == $v['parent_id']) {
            $user[] = $v['id'];
        }
    }
    return implode(',',$user);
}

//获取子孙级ids
function get_child($uid, $users)
{
    unset($users[$uid]);
    $user = array($uid);
    foreach ($users as $v) {
        if ($uid == $v['parent_id']) {
            $res = get_child($v['id'], $users);
            $user = array_merge($user, $res);
        }
    }
    return $user;
}


index();
//运行结束
$now_tiem1 = time();
$new_time = $now_tiem1-$now_tiem;
@file_put_contents('tree.log', "\n\n\n运行结束时间:" . date("Y-m-d H:i:s",$now_tiem1) . " | time:" . $now_tiem1 .' 运行时长: ' .$new_time."\n\n\n", FILE_APPEND);