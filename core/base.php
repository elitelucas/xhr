<?php

/**
 *  index.php 系统入口
 *
 * @copyright			(C) 2013 CHENGHUITONG.COM
 * @lastmodify			2013-08-20   by snyni
 */
//定义基础常量
define('IN_SNYNI', TRUE);
define('DS', DIRECTORY_SEPARATOR);
define('S_CORE', S_ROOT . 'core' . DS);
define('S_CACHE', S_ROOT . 'caches' . DS);
define('S_PAGE', S_ROOT . 'lib' . DS);
define('S_THEMES', S_ROOT . 'template' . DS);
define('S_SKINS', 'web' . DS);   //皮肤
//主机协议
define('SITE_PROTOCOL', isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://');
// define('SITE_PROTOCOL', '//');
// print_r(SITE_PROTOCOL);die;
//当前访问的主机名
define("SITE_URL", (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
//来源
define('HTTP_REFERER', isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');

// 定义框架变量
$_SN = array();

//记录开始运行时间
$_SN['starttime'] = microtime(TRUE);

// 加载核心函数
require S_CORE . 'common/funs.php';

//加载参数过滤函数库
require S_CORE . 'common/parame_func.php';


//加载加密函数库
require S_CORE . 'common/encrypt_func.php';

// 加载兼容函数库
if (version_compare(PHP_VERSION, '5.2.0', '<')) {
    include_cache(S_CORE . 'common/compat.php');
}

// 加载核心配置
C(require S_CACHE . 'config.php');


// 加载核心语言包
L(require S_PAGE . 'lang/lang.php');

// 程序超时设置
set_time_limit(C('time_limit'));

// 设置错误输出
if (C('debug_mode')) {
	ini_set("display_errors", "On");
    error_reporting(7);
} else {
    error_reporting(0);
    set_error_handler('my_error_handler', E_ERROR | E_PARSE);
}
ini_set("display_errors", "On");
error_reporting(7);
error_reporting(E_ERROR  | E_PARSE);
// 设置时区
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set(C('time_zone'));
}

define('SYS_TIME', isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time());
parseUrl();
// 解析魔术引号
if (get_magic_quotes_gpc() == 0) {
    $_POST = addslashes_deep(trim_deep($_POST));
    $_POST = htmlspecialchars_decode_deep(trim_deep($_POST));
    $_GET = addslashes_deep(trim_deep($_GET));
    $_GET = htmlspecialchars_decode_deep(trim_deep($_GET));
    $_COOKIE = addslashes_deep(trim_deep($_COOKIE));
    $_COOKIE = htmlspecialchars_decode_deep(trim_deep($_COOKIE));
}
if (isset($_GET) && !empty($_GET)) {
    foreach ($_GET as $v) {
        if (preg_match("/(script|expression)/i", $v)) {
            $_GET = array('m' => 'content', 'c' => 'default', 'a' => 'index');
        }
    }
}
$_REQUEST = array_merge($_GET, $_POST);
// 禁止对全局变量注入
isset($_REQUEST['GLOBALS']) or isset($_FILES['GLOBALS']) && exit('Request tainting attempted.');

//CDN 路径
define('CDN_PATH', SITE_PROTOCOL.C('cdn_path'));
//动态程序路径
define('APP_PATH', C('app_home') . "/");
//上传文件路径
define('UPLOAD_PATH', APP_PATH . C('upfile_path') . "/");
// ob
if (C('gzip') && function_exists('ob_gzhandler')) {
    #ob_start('ob_gzhandler');
} else {
    #ob_start();
}
#header("Content-type: text/html; charset=utf-8");
// 表单自动保存
session_cache_limiter("private,must-revalidate");
//加载基础类
$_SN['run_cache'] = false;
if ($_SN['run_cache']) {
    if (is_file(S_CORE . '~runtime.php')) {
        require S_CORE . '~runtime.php';
    } else {
        $l.= php_strip_whitespace(S_CORE . 'class/app.php');
        $l.= php_strip_whitespace(S_CORE . 'class/model.php');
        write_file(S_CORE . '~runtime.php', $l);
        unset($l);
        require S_CORE . '~runtime.php';
    }
} else {
    require S_CORE . 'class/app.php';
    require S_CORE . 'class/model.php';
}