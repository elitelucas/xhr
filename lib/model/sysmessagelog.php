<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/16
 * Time: 17:20
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class SysMessageLogModel extends CommonModel{
    protected $table = '#@_sys_message_read';

    /**
     * 设置公告已经读取
     * @param $mids array 用户id
     * @param $uid int 用户id
     * @return array 未读公告id
     */
    public function setReadMessages($mids,$uid){
        $time= time();
        $sql = "INSERT INTO ".$this->table." (uid,smid,addtime) VALUES({$uid},{$mids},{$time})";
        $res = $this->db->query($sql);
        return $res;
    }


    /**
     * 查询读取记录
     * @param $mids array 用户id
     * @param $uid int 用户id
     * @return array 未读公告id
     */
    public function getReadMessages($mids,$uid){
        $sql = "SELECT id FROM ".$this->table." WHERE uid = ".$uid." AND smid = ".$mids;
        $res = $this->db->getone($sql);
        return $res;
    }
}