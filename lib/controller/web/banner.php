<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 13:34
 * desc: 天天反利 玩法介绍
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class BannerAction extends Action{


    public function __construct(){
        parent::__construct();
    }

    /**
     * 轮播图--分享福利
     * @method get
     * @return mixed
     */
    public function welfare(){
        include template('banner/welfare');
    }

}