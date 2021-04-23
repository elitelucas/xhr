<?php
/**
 * User: HCHT
 * Date: 2018/1/8
 * Time: 10:33
 */

define('S_ROOT', __DIR__.'/../../');

define("IN_SNYNI",1);

$fconfig = require_once S_ROOT.'caches/config.php';

define('DB_HOST',$fconfig['db_host']); //数据库地址
define('DB_USER',$fconfig['db_user']); //用户名
define('DB_PWD',$fconfig['db_pwd']); //数据库密码
define('DB_NAME',$fconfig['db_name']); //库名
define('DB_PORT',!empty($fconfig['db_port'])?$fconfig['db_port']:3306); //端口

define('RD_HOST',$fconfig['redis_config']['host']); //Redis主机
define('RD_PORT',$fconfig['redis_config']['port']); //Redis端口
define('RD_PASS',isset($fconfig['redis_config']['pass'])?$fconfig['redis_config']['pass']:false); //Redis密码

//define('APP_HOME',$fconfig['app_home']);

//define('API_HOST',$fconfig['api_host']);