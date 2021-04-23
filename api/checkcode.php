<?php

/**
 *  checkcode.php 验证码生成api接口
 *
 * @copyright			(C) 2013 CHENGHUITONG.COM
 * @lastmodify			2013-08-27   by snyni
 */
define('S_ROOT', substr(dirname(__FILE__), 0, -3));
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
O('session', '', 0);
O('image', '', 0);
if (isset($_GET['width']) && intval($_GET['width']))
    $width = intval($_GET['width']);
if (isset($_GET['height']) && intval($_GET['height']))
    $height = intval($_GET['height']);
Session::start();
image::vCodenew('vcode', 4, 20, '../core/tpl/elephant.ttf', $width, $height);
?>