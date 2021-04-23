<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/9
 * Time: 17:56
 * desc: 会员组
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class GroupModel extends CommonModel {
    protected $table = '#@_user_group';

}