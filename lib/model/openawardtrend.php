<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/23
 * Time: 9:32
 * desc: 开奖趋势
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class OpenAwardTrendModel extends CommonModel{
    protected $table = '#@_open_award_trend';
}