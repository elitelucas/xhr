<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

/**
 * 红包相关逻辑处理
 * 2017-11-08
 */
class RedpacketAction extends Action
{
    /**
     * 数据表
     */
    // private $model;
    // private $model1;
    // private $model2;
    // private $model3;
    // private $model4;

    public function __construct()
    {
        parent::__construct();

        //红包相关页面需要登录验证
        $this->checkAuth();
        // $this->model = D('banner');
        // $this->model1 = D('user');
        // $this->model2 = D('room');
        // $this->model3 = D('message');
        // $this->model4 = D('account');
    }

    /**
     * 红包页面[抢红包]
     * 2017-11-08
     */
    public function redPacket()
    {
        $user_id = intval($this->userId);
        $redpacket_id = intval($_REQUEST['redpacket_id']);
        $token = $this->db->result("SELECT sessionid FROM un_session WHERE user_id = {$user_id}");
        include template('redPacket');
    }

    /**
     * 红包规则
     * 2017-11-09
     */
    public function redPacket_rule()
    {
        $user_id = intval($this->userId);
        $redpacket_id = intval($_REQUEST['redpacket_id']);
        $token = $this->db->result("SELECT sessionid FROM un_session WHERE user_id = {$user_id}");
        include template('redPacket_rule');
    }

    //红包记录
    public function redPacket_record()
    {
        $user_id = intval($this->userId);
        $token = $this->db->result("SELECT sessionid FROM un_session WHERE user_id = {$user_id}");

        $redpacket_count = json_encode($rt_data, JSON_UNESCAPED_UNICODE);
        include template('redPacket_record');
    }

}
