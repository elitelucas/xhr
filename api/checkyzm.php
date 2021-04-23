<?php

/**
 *  checkyzm.php 验证码生成api接口
 *
 * @copyright			(C) 2013 CHENGHUITONG.COM
 * @lastmodify			2013-08-27   by snyni
 */
define('S_ROOT', substr(dirname(__FILE__), 0, -3));
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
O('session', '', 0);
Session::start();
$yzm=$_GET['code'];
$code=Session::get('vcode');
if(strtolower($yzm)==strtolower($code)){
	alert("success");
}else{
	alert("fail");
}