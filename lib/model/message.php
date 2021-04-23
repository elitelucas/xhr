<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/14
 * Time: 16:13
 * desc: 信息
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class MessageModel extends CommonModel {
    protected $table = '#@_message';

}