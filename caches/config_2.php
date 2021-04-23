<?php

/**
 *  config_2.php 惯例配置文件
 *
 * @copyright
 */
!defined('IN_RUN') && die('Access Denied!');

return array(
    'workerman_status'=>array(
        'admin'=>1, //后台用的，主要是算开奖结果 1启动 0不启动
        'catalog'=>1, //前台的功能 1启动 0不启动
    ),
);
?>
