<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/22
 * Time: 13:56
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class GameWayModel extends CommonModel {
    protected $table='#@_odds';

}