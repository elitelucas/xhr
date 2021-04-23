<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/9
 * Time: 21:28
 * desc: 登录日子
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class UserLogModel extends CommonModel {
    protected $table = '#@_user_login_log';

    /* *
     *  获取最近20次登录次数最多的ip
     */
    public function getMaxlistIp($id)
    {
        $sql = "SELECT ip, addtime,COUNT(ip)AS cn FROM (SELECT ip, addtime FROM un_user_login_log WHERE user_id = {$id} AND ip <> '' ORDER BY id DESC LIMIT 0,20) AS IP GROUP BY ip ORDER BY cn DESC, addtime DESC LIMIT 1";
        $res = $this->getOneData($sql);
        if($res['cn'] < 2){
            $res['ip'] = false;
        }
        return $res['ip'];
    }
    
    /* *
     *  获取最近20次登录次数最多的ip及ip归属地
     */
    public function getMaxlistIps($id)
    {
        $sql = "SELECT ip, ip_attribution, addtime,COUNT(ip)AS cn FROM (SELECT ip, ip_attribution, addtime FROM un_user_login_log WHERE user_id = {$id} AND ip <> '' ORDER BY id DESC LIMIT 0,20) AS IP GROUP BY ip ORDER BY cn DESC, addtime DESC LIMIT 1";
        $res = $this->getOneData($sql);
        if($res['cn'] < 2){
            $res['ip'] = false;
            $res['ip_attribution'] = '';
        }
        return $res;
    }
}