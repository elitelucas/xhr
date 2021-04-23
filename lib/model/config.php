<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/14
 * Time: 11:49
 * desc: 配置
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class ConfigModel extends CommonModel {
    protected $table = '#@_config';
    //根据id获取一条记录
    public function getOne($nid) {
        return $this->db->getone("select * from " . $this->table . " where nid = '{$nid}'");
    }

    //修改极光推送配置
    public function editJPush($value){
        $table = 'un_config';
        return $this->db->update($table, array("value" => $value), array("nid" => 'JPush_config'));
    }
}