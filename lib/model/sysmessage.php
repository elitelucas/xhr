<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/16
 * Time: 9:27
 * desc 系统公告
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class SysMessageModel extends CommonModel {
    protected $table = '#@_sys_message';
    protected $table1 = '#@_sys_message_read';

    /**
     * 获取未读公告id
     * @param $mids array 用户id
     * @param $uid int 用户id
     * @return array 未读公告id
     */
    public function getUnreadMessages($mids,$uid){
        //查询公告
       /* $sql = "SELECT id FROM ".$this->table." WHERE state = 0";
        $res = $this->db->getall($sql);
        $mids = array();
        foreach ($res as $v){
            $mids[$v['id']] =$v['id'];
        }*/
        $ids = implode($mids,',');
        //查询读取记录
        $sql = "SELECT smid FROM ".$this->table1." WHERE uid = ".$uid." AND smid IN ({$ids})";
        $res1 = $this->db->getall($sql);
        //删除读取的记录
        foreach ($res1 as $v){
            if(in_array($v['smid'],$mids)){
                $key =array_search($v['smid'],$mids);
                unset($mids[$key]);
            }
        }
        return $mids;
    }

    /**
     * 获取未读公告id
     * @param $uid int 用户id
     * @return array 未读公告id
     */
    public function getSysMessages($filed = '*'){
        //查询公告
        $sql = "SELECT ".$filed." FROM ".$this->table." WHERE state = 0";
        $res = $this->db->getall($sql);
        return $res;
    }
}