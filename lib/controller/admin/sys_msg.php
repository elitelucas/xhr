<?php

/**
 */
!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');

include S_CORE . 'class' . DS . 'page.php';

class Sys_msgAction extends Action {

    //用户列表
    public function todo() {
        include template('to_do');
    }
}
