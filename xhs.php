<?php
/**
 *  admin.php 系统入口
 *
 * @copyright			(C) 2013 CHENGHUITONG.COM
 * @lastmodify			2013-08-20   by snyni
 */
define('S_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
$_GET['m']='admin';
$_GET['c']= isset($_GET['c']) ? $_GET['c'] : 'login';
new app();
?>