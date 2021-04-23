<?php

/**
 * @copyright by-chenerlin
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');

class CustomServiceAction extends Action {

    /**
     * 客服聊天室窗口
     */
    public function kfWeb() {
        $user = $this->admin;

        //接收参数
        $client_id = $_GET['client_id'];
        $username = $_GET['username'];

        //获取用户
        $userInfoArr = D("user")->getOneCoupon('id', array('username' => $username));

        include template('customService');
    }
}
