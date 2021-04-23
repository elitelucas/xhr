<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 9:30
 * desc: 游戏类型
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class LotteryTypeModel extends CommonModel {
    protected $table = '#@_lottery_type';

}