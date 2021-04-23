<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/11
 * Time: 16:17
 * desc: 游戏房间
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class PublicRoomModel extends CommonModel {
    protected $table='#@_room_public';

}