<?php

/**
 * Created by PhpStorm.
 * User: KQ
 * Date: 2017/5/16
 * Time: 17:01
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');

class JpushAction extends Action
{
    //极光推送配置页
    function config(){
        $jpush = D('config')->getOne('JPush_config');
        $conf = json_decode($jpush['value'],true);
        include template('jpush');
    }

    //修改配置
    function edit(){
        $value = json_encode($_POST);
        $res = D('config')->editJPush($value);
        if($res){
            echo json_encode(['code'=>1,'msg'=>'设置成功']);
        }else{
            echo json_encode(['code'=>1,'msg'=>'设置失败']);
        }
    }
}