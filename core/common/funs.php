<?php

/**
 *  funs.php 公共函数库
 * @copyright            (C) 2013 CHENGHUITONG.COM
 * @lastmodify               2013-08-21   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');


/*
 * 记录日志  用户记录支付异步通知结果用于调试   （注:仅仅用于开发调试，开发完成后去除日志记录与历史调试信息）
 *
 * */
function log_to_mysql($logData, $flag = '') {
    $souce_url = $_SERVER['REQUEST_SCHEME'].'//'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

    if(is_array($logData)) $logData = json_encode($logData);
    $data = [
        'flag' => $flag,
        'log_text' => $logData,
        'souce_url' => $souce_url,
        'create_time' => time(),
    ];

    $model = O('model');
    return $model->db->insert('un_test_log', $data);
}


//后台操作日志
function admin_operation_log($admin_user_id, $log_type, $log_remark, $record_id = 0) {
    $logData = [
        'user_id' => $admin_user_id,
        'type' => $log_type,
        'remark' => $log_remark,
        'record_id' => $record_id,
        "addip" => ip(),
        "addtime" => time()
    ];
    $model = O('model');
    return $model->db->insert('un_admin_operation_log', $logData);
}


// 输出调试内容
function dump($l1) {
    if (C('debug_mode')) {
        echo '<div style="border:1px solid #dbdbdb; padding:5px; margin:5px; width:auto; color:#003300">';
        echo runtime();
    }
    echo "<pre>";
    if (is_array($l1) || is_object($l1)) {
        print_r($l1);
    } else {
        echo $l1;
    }
    echo '</pre></div>';
}



/**
 * json编码
 * @param $data array
 */
function encode($data){
    return json_encode($data,JSON_UNESCAPED_UNICODE);
}

/**
 *
 * json解码
 * @param $data array
 * 返回数组
 */
function decode($str){
    return json_decode($str,1);
}

/**
 * 日志系统
 * @param $f 文件名
 * @param $s 数据
 */
function lg($f,$s) {
    @$dirname=S_ROOT.'log';
    @$fileName = $dirname.'/'.date('Y_m_d').'_'.$f;
    @$fp = fopen($fileName,"a+");
    @fwrite($fp, date('Y-m-d H:i:s').'--------->'.$s."\n\n");
    @fclose($fp);
    if(!empty($fileName)){
//        chmod($fileName,0777); //防止shell写文件时www没在权限
    }
}

// 程序运行时间
function runtime($num = 3) {
    return round(microtime(true) - $GLOBALS['_SN']['starttime'], 3);
}

//递归创建目录
function mkdirs($l1, $l2 = 0777) {
    if (!is_dir($l1)) {
        mkdirs(dirname($l1), $l2);
        return @mkdir($l1, $l2);
    }
    return true;
}

// write_file
function write_file($l1, $l2 = '') {
    $dir = dirname($l1);
    if (!is_dir($dir)) {
        mkDirs($dir);
    }

    return @file_put_contents($l1, $l2);
}

// read_file
function read_file($l1) {
    return @file_get_contents($l1);
}

// 对象转换为数组
function obj2arr($obj) {
    $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
    foreach ($_arr as $key => $val) {
        $val = (is_array($val) || is_object($val)) ? obj2arr($val) : $val;
        $arr[$key] = $val;
    }

    return $arr;
}

// 数组保存到文件
function arr2file($filename, $arr = '') {
    is_object($arr) && $arr = obj2arr($arr);
    if (is_array($arr)) {
        $con = var_export($arr, true);
    } else {
        $con = $arr;
    }
    $con = "<?php\n!defined('IN_SNYNI') && die();\nreturn $con;\n?>";
    write_file($filename, $con);
}

// 转换成JS
function t2js($l1, $l2 = 1) {
    $I1 = str_replace(["\r", "\n"], ['', '\n'], addslashes($l1));

    return $l2 ? "document.write(\"$I1\");" : $I1;
}

// 优化的include_once
function include_cache($l1) {
    static $I = [];
    if (is_file($l1)) {
        if (!isset($I[$l1])) {
            include $l1;
            $I[$l1] = true;
        }
        return $I[$l1];
    } else {
        throw new Exception(L('error.nofile') . $l1);
    }

    return false;
}

// 去除空格
function trim_deep($l1) {
    $l1 = is_array($l1) ? array_map('trim_deep', $l1) : trim($l1);

    return $l1;
}

// 转义引号
function addslashes_deep($l1) {
    $l1 = is_array($l1) ? array_map('addslashes_deep', $l1) : addslashes($l1);

    return $l1;
}

// 清除转义($arr/$string)
function stripslashes_deep($l1) {
    $l1 = is_array($l1) ? array_map('stripslashes_deep', $l1) : stripslashes($l1);

    return $l1;
}

// 快速实例化一个类库
function O($name, $type = null, $initialize = 1) {
    static $_a = [];

    $name = strtolower($name);
    if ($type == 'ACTION') { // 控制器
        $path = S_PAGE . ACTION_DIR . DS . $name . '.php';
        $name = $name . 'Action';
    } elseif ($type == 'MODEL') {
        $path = S_PAGE . 'model' . DS . $name . '.php';
        $name = $name . 'Model';
    } else {
        $path = S_CORE . 'class' . DS . $name . '.php';
    }
    //已实例化,返回
    if (isset($_a[$name])) {
        return $_a[$name];
    }
    if (!class_exists($name)) {
        include_cache($path);
    }
    if (empty($initialize)) {
        return;
    }
    if (class_exists($name)) {
        $_a[$name] = new $name();
    }
    if (strstr($name, 'admin/')) {
        list($admin, $models) = explode("/", $name);
        $_a[$name] = new $models();
    }
    if (strstr($name, 'pay/')) {
        list($pay, $models) = explode("/", $name);
        $_a[$name] = new $models();
    }
    if (strstr($name, 'withdraw/')) {
        list($withdraw, $models) = explode("/", $name);
        $_a[$name] = new $models();
//        payLog('bb.txt',print_r($_a[$name]));
    }
    
    if (strstr($name, 'center/')) {
        list($pay, $models) = explode("/", $name);
        $_a[$name] = new $models();
    }

    return !isset($_a[$name]) ? false : $_a[$name];
}

// 设置配置
function C($l1 = NULL, $l2 = NULL) {
    static $_a = [];
    //如果是数组,写入配置数组
    if (is_array($l1)) {
        return $_a = array_merge($_a, array_change_key_case($l1));
    }
    $l1 = strtolower($l1);
    if (!is_null($l2)) {
        return $_a[$l1] = $l2;
    }
    if (empty($l1)) {
        return $_a;
    }

    return isset($_a[$l1]) ? $_a[$l1] : 0;
}

// 加载语言包
function L($l1 = NULL, $pars = NULL) {
    static $lang = [];
    if (is_array($l1)) {
        $lang = array_merge($lang, array_change_key_case($l1));

        return;
    }
    $l1 = strtolower($l1);
    if (empty($l1)) {
        return $lang;
    }

    $language = isset($lang[$l1]) ? $lang[$l1] : $l1;

    if ($pars && $language != $l1) {
        foreach ($pars AS $_k => $_v) {
            $language = str_replace('{' . $_k . '}', $_v, $language);
        }
    }

    return $language;
}

// 加载模型
function D($model) {
    $obj = O($model, 'MODEL');
    if ($obj) {
        return $obj;
    } else {
        throw new Exception(L('error.nomodel') . $model);
    }
}

// 通用快速文件缓存
function F($name, $value = '', $expire = -1, $path = '') {
    // $value  '':读取 null:清空 data:赋值
    //---------改用memcached缓存---------
    if (C('memc_enabled')) {
        return M($name, $value, $expire);
    }
    //----------------------------------

    static $_cache = [];
    !$path && $path = S_CACHE . "data" . DS;
    $file = $path . $name . '.php';
    if ($value !== '') {
        if (is_null($value)) { // 删除缓存
            $result = @unlink($file);
            if ($_cache[$name]) {
                unset($_cache[$name]);
            }
            $result = null;
        } else { // 缓存数据
            $_cache[$name] = $value;
            if (is_array($value)) {
                $con = var_export($value, true);
            } else {
                $con = "'" . $value . "'";
            }
            $con = str_replace(chr(13), '', $con);
            $content = "<?php\n!defined('IN_SNYNI') && die();\n/*" . sprintf('%012d', $expire) . "*/\nreturn $con;\n?>";
            $result = write_file($file, $content);
        }

        return $result;
    }
    if (isset($_cache[$name])) { // 静态缓存
        return $_cache[$name];
    }
    if (is_file($file) && false !== $content = file_get_contents($file)) {
        $expire = substr($content, 39, 12);
        // 缓存过期,删除文件
        if ($expire != -1 && SYS_TIME > filemtime($file) + intval($expire)) {
            @unlink($file);

            return false;
        }
        $value = require $file;
        $_cache[$name] = $value;

        return $value;
    } else {
        return false;
    }
}

// // 模板解析
// function template($html = null) {
//     $t = O('template');
//     if (defined('IN_ADMIN') && IN_ADMIN == true)
//         $t->templates_dir = S_THEMES . 'admin' . DS;                    //模板路径;
//     else
//         $t->templates_dir = S_THEMES . S_SKINS;            //模板路径;
//     $t->templates_cache = S_CACHE . 'cache_tpl' . DS;

//     return $t->display($html);
// }

//判断是否是手机端
function is_phone () {
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $is_pc = (strpos($agent, 'windows nt')) ? true : false;
    // $is_mac = (strpos($agent, 'mac os')) ? true : false;
    $is_iphone = (strpos($agent, 'iphone')) ? true : false;
    $is_android = (strpos($agent, 'android')) ? true : false;
    $is_ipad = (strpos($agent, 'ipad')) ? true : false;

    if(/*$is_mac ||*/ $is_pc){
          return  false;
    }

    if( $is_iphone || $is_android || $is_ipad){
          return  true;
    }
}

/**
 *
 * 判断是不是前台
 *
 * @param $k 配置项中的key
 * @return bool
 *
 */
function is_home($k){
    //strpos($k,'_admin')===false
    if(strpos($k,'_admin')===false){  //如果匹配不到就是前台
        return true;
    }else{ //否则就是后台
        return false;
    }
}


// 模板解析
function template($html = null,$type) {
    $t = O('template');
    if (defined('IN_ADMIN') && IN_ADMIN == true){
        if($type == 'new'){
            $t->templates_dir = S_THEMES . 'new_admin' . DS;            //新后台模板路径;
        }else{
            $t->templates_dir = S_THEMES . 'admin' . DS;            //模板路径;
        }
    }else {
        if (is_phone () || true) {
            $t->templates_dir = S_THEMES . S_SKINS;             //H5移动版模板路径;
        } else {
            $t->templates_dir = S_THEMES . 'win' . DS;           //PC版模板路径;
        }
    }
    $t->templates_cache = S_CACHE . 'cache_tpl' . DS;

    return $t->display($html);
}


// 异常处理
function error($err) {
    if (C('debug_mode')) {
        $trace = $err->getTrace();
        $traceInfo = '';
        $time = date("y-m-d H:i:m", time());
        foreach ($trace as $t) {
            $_file = isset($t['file']) ? $t['file'] : null;
            $_line = isset($t['line']) ? $t['line'] : null;
            $_class = isset($t['class']) ? $t['class'] : null;
            $_type = isset($t['type']) ? $t['type'] : null;
            $_args = isset($t['args']) ? $t['args'] : null;
            $_function = isset($t['function']) ? $t['function'] : null;
            $traceInfo .= '[' . $time . '] ' . $_file . ' (' . $_line . ') ';
            $traceInfo .= $_class . $_type . $_function . '(';
            if (!empty($_args)) {
                $traceInfo .= implode(', ', $_args);
            }
            $traceInfo .= ")\n";
        }
        $e = [];
        $e['message'] = $err->getMessage();
        $e['file'] = $err->getFile();
        $e['line'] = $err->getLine();
        $e['trace'] = $traceInfo;
        include(S_CORE . 'tpl/debug.tpl.php');
        exit();
    } else {
        $e = $err->getMessage();
        @file_put_contents(S_CACHE . "log" . DS . "object_error.php", date('m-d H:i:s', SYS_TIME) . ' | ' . $e . ' | ' . $err->getFile() . " " . $err->getLine() . "\t" . $_SERVER['REQUEST_URI'] . "\r\n", FILE_APPEND);

        return false;
        //alert($err->getMessage());
    }
}

// 组装 动态URL 地址$url=(module/controller/action), $param)
function url($module = '', $controller = '', $action = '', $param = [], $fullUrl = false) {
    if (!is_array($param))
        throw new Exception(L('error.noparam'));
    empty($module) && $module = ROUTE_M;
    empty($controller) && $controller = ROUTE_C;
    empty($action) && $action = ROUTE_A;
    $url = $param ? '&' . http_build_query($param) : '';
    $url = '?' . C('var_module') . '=' . $module . '&' . C('var_controllers') . '=' . $controller . '&' . C('var_action') . '=' . $action . $url;

    if (defined('IN_ADMIN') && IN_ADMIN == true && $fullUrl == false) {
        return $url;
    }
    if (C('url_mode') == 1) {
        $url = $module . "/" . $controller . C('url_line') . $action;
        if ($param) {
            $url .= $param ? C('url_line') . str_replace(['&', '='], C('url_line'), http_build_query($param)) : '';
        }
        $url .= C('url_suffix');
    }

    if ($module == 'article') {
        return str_replace('https', 'http', APP_PATH) . $url;
    } else {
        return APP_PATH . $url;
    }
}

function parseUrl() {
    $url_mode = C('url_mode');
    if (empty($url_mode)) {
        return;
    } else {
        $query = $_GET['p'];
        if (empty($query)) {
            return;
        }
        $url_suffix = C('url_suffix');
        $url_line = C('url_line');
        $param = explode($url_line, str_replace($url_suffix, '', $query));
        if (is_array($param)) {
            $for_sum = intval(count($param) / 2);
            for ($i = 0; $i < $for_sum; $i++) {
                $_GET[array_shift($param)] = urldecode(array_shift($param));
            }
            unset($for_sum);
        }
    }
}

//接参
function Parameters($keys, $method = 'GP') {
    !is_array($keys) && $keys = [$keys];
    $array = [];
    foreach ($keys as $val) {
        $safe_flg = false;
        if (is_array($val)) {
            $val = $val[0];
            $safe_flg = true;
        }

        $array[$val] = NULL;
        if ($method != 'P' && isset($_GET[$val])) {
            $array[$val] = $safe_flg ? $_GET[$val] : safe_replace($_GET[$val]);
        } elseif ($method != 'G' && isset($_POST[$val])) {
            $array[$val] = $safe_flg ? $_POST[$val] : safe_replace($_POST[$val]);
        }
    }

    return $array;
}

/**
 * 提示信息
 *
 * @param unknown_type $msg     错误信息
 * @param unknown_type $jumpUrl 跳转地址 0为返回上一页  1为刷新当前页
 * @param unknown_type $status  1为正确提示， 0为错误提示
 * @param unknown_type $time    跳转间隔时间 0为直接跳转
 */
function alert($msg = 'error!', $jumpUrl = 0, $status = 0, $time = 3, $dialog = '') {
    if (empty($jumpUrl)) { // 返回上一页
        $jumpUrl = 'javascript:history.back();';
    } elseif ($jumpUrl == 1) { // 刷新当前页
        $jumpUrl = 'javascript:location.reload();';
    }
    $js = "<script language='javascript' type='text/javascript'>window.setTimeout(function () { top.location.href=\"$jumpUrl\";}, '" . ($time * 1000) . "');</script>";

    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($_REQUEST['inajax'])) {
        echo $msg;
        exit;
    } else {
        if (!headers_sent()) {
            // redirect
            header('Content-Type:text/html; charset=utf-8');
            if ($time == 0) {
                header("Location:{$jumpUrl}");
            } else {
                include(S_CORE . 'tpl' . DS . 'redirect.tpl.php');
            }
            exit();
        } else {
            $l4 = "<meta http-equiv='refresh' content='$time'; url='$jumpUrl' />";
            if ($time != 0) {
                include(S_CORE . 'tpl' . DS . 'redirect.tpl.php');
                exit;
            }
            exit;
        }
    }
}

/**
 * 判断email格式是否正确
 *
 * @param $email
 */
function is_email($email) {
    return strlen($email) > 6 && preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $email);
}

function is_mobile($mobile) {
    return preg_match("/^1([0-9]{10})$/", $mobile);
}

function is_date($date) {
    return strlen($date) == 8 && preg_match("/^[0-9]{4}(0[1-9]|1[0-2])([0-2][0-9]|3[01])$/", $date);
}

/**
 * 输出自定义错误
 *
 * @param $errno   错误号
 * @param $errstr  错误描述
 * @param $errfile 报错文件地址
 * @param $errline 错误行号
 *
 * @return string 错误提示
 */
function my_error_handler($errno, $errstr, $errfile, $errline) {
    if ($errno == 8)
        return '';
    $errfile = str_replace(S_ROOT, '', $errfile);
    if (!C('debug_mode')) {
        $logfile = S_CACHE . "log" . DS . 'error_log.php';
        if (filesize($logfile) > (C('errorlog_size') * 1024 * 1024)) {
            @file_put_contents($logfile, date('m-d H:i:s', SYS_TIME) . ' | ' . $errno . ' | ' . str_pad($errstr, 30) . ' | ' . $errfile . ' | ' . $errline . "\r\n");
        } else {
            @file_put_contents($logfile, date('m-d H:i:s', SYS_TIME) . ' | ' . $errno . ' | ' . str_pad($errstr, 30) . ' | ' . $errfile . ' | ' . $errline . "\r\n", FILE_APPEND);
        }
    } else {
        $str = '<div style="font-size:12px;text-align:left; border-bottom:1px solid #9cc9e0; border-right:1px solid #9cc9e0;padding:1px 4px;color:#000000;font-family:Arial, Helvetica,sans-serif;"><span>errorno:' . $errno . ',str:' . $errstr . ',file:<font color="blue">' . $errfile . '</font>,line' . $errline . '</span></div>';
        echo $str;
    }
}

/**
 * 查询字符是否存在于某字符串
 *
 * @param $haystack 被搜索字符串
 * @param $needle   要查找的字符
 *
 * @return bool
 */
function strexists($haystack, $needle) {
    return !(strpos($haystack, $needle) === FALSE);
}

/**
 * 获取请求ip
 * @return ip地址
 */
function ip() {
    if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $ip = getenv('HTTP_CLIENT_IP');
    } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches [0] : '';
}

/**
 * 获取请求ip和ip归属地
 * @return array ip地址与ip归属地
 */
function getIp($ip = '') 
{
    $attribution = '';
    
    if (empty($ip)) {
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    }
    if(!checkIP($ip)) {
        return ['ip' => '', 'attribution' => ''];
    }
    if (preg_match('/[\d\.]{7,15}/', $ip, $matches)) {
        //新浪ip查询接口
        //$urlIp = 'http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=' . $ip;
        //太平洋电脑 IP库接口
        try {
            $urlIp = 'http://whois.pconline.com.cn/ip.jsp?ip=' . $ip;
            $curlData = curl_get_ip($urlIp);
            $attribution = trim(str_replace(' ', '|', iconv('GBK','UTF-8//IGNORE',$curlData)));
        }catch (\Exception $e) {
            $attribution = '';
        }
        //淘宝ip查询接口
        //$urlIp = 'http://ip.taobao.com/service/getIpInfo.php?ip=' . $ip;
        
        /* $curlData = curl_get_ip($urlIp);
        $data = json_decode($curlData, true);
        //var_dump($data);
        if (isset($data['code']) && $data['code'] == 0 || isset($data['ret']) && $data['ret'] == 1) {
            $attribution = isset($data['data']['country']) ? $data['data']['country'] : $data['country'];
            $attribution .= isset($data['data']['region']) ? $data['data']['region'] : $data['province'];
            $attribution .= isset($data['data']['city']) ? $data['data']['city'] : $data['city'];
            if (!empty($data['data']['isp']) || !empty($data['isp'])) {
                $attribution .= '|' . (isset($data['data']['isp']) ? $data['data']['isp'] : $data['isp']);
            }
        } */
    }
    
    return ['ip' => $ip, 'attribution' => $attribution];
}

/**
 * curl get ip归属地
 * @param $url string 请求地址
 * @return mixed
 */
function curl_get_ip($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_NOSIGNAL, true);    //注意，毫秒超时一定要设置这个
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
    //curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    //curl_setopt($ch, CURLOPT_HTTPHEADER,['Content-type: text/html; charset="utf-8"']);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

/**
 * 分页函数
 * JAMES  edit  20130910
 *
 * @param $num       信息总数
 * @param $curr_page 当前分页
 * @param $array     需要传递的数组，用于增加额外的方法
 * @param $perpage   每页显示数
 * @param $setpages  显示在页面的按钮数
 * @param $class     CSS类
 *
 * @return 分页
 */
function pagelist($num, $curr_page, $array = [], $perpage = 20, $setpages = 7, $class = 'a1') {
    $multipage = '';
    $curr_page = intval($curr_page) <= 0 ? 1 : intval($curr_page);
    if ($num > $perpage) {
        $page = $setpages + 1;
        $offset = ceil($setpages / 2 - 1);
        $pages = ceil($num / $perpage);
        $from = $curr_page - $offset;
        $to = $curr_page + $offset;
        $more = 0;
        if ($page >= $pages) {
            $from = 2;
            $to = $pages - 1;
        } else {
            if ($from <= 1) {
                $to = $page - 1;
                $from = 2;
            } elseif ($to >= $pages) {
                $from = $pages - ($page - 2);
                $to = $pages - 1;
            }
            $more = 1;
        }

        $multipage .= '<nav><ul class="pagination"> <li class="statistics"><a class="' . $class . '">共 ' . $num . ' 条  ' . $curr_page . '/' . $pages . '页 每页 ' . $perpage . ' 条</a></li>';
        if ($curr_page > 0) {
            $multipage .= '<li> <a href="' . pageurl($curr_page - 1, $array) . '" class="' . $class . '">上一页</a></li>';
            if ($curr_page == 1) {
                $multipage .= '<li class="active"> <span>1</span></li>';
            } elseif ($curr_page > 6 && $more) {
                $multipage .= '<li> <a href="' . pageurl(1, $array) . '">1</a></li><li><span>..</span></li>';
            } else {
                $multipage .= ' <li><a href="' . pageurl(1, $array) . '">1</a></li>';
            }
        }
        for ($i = $from; $i <= $to; $i++) {
            if ($i != $curr_page) {
                $multipage .= '<li> <a href="' . pageurl($i, $array) . '">' . $i . '</a></li>';
            } else {
                $multipage .= '<li class="active"> <span>' . $i . '</span></li>';
            }
        }
        if ($curr_page < $pages) {
            if ($curr_page < $pages - 5 && $more) {
                $multipage .= '<li><span> .. </span></li><li><a href="' . pageurl($pages, $array) . '">' . $pages . '</a></li> <li><a href="' . pageurl($curr_page + 1, $array) . '" class="' . $class . '">下一页</a></li></ul></nav>';
            } else {
                $multipage .= ' <li><a href="' . pageurl($pages, $array) . '">' . $pages . '</a></li><li> <a href="' . pageurl($curr_page + 1, $array) . '" class="' . $class . '">下一页</a></li></ul></nav>';
            }
        } elseif ($curr_page == $pages) {
            $multipage .= '<li class="active"> <span>' . $pages . '</span></li><li><a href="' . pageurl($curr_page, $array) . '" class="' . $class . '">下一页</a></li></ul></nav>';
        } else {
            $multipage .= ' <li><a href="' . pageurl($pages, $array) . '">' . $pages . '</a></li><li> <a href="' . pageurl($curr_page + 1, $array) . '" class="' . $class . '">下一页</a></li></ul></nav>';
        }
    }

    //返回数据:当前页, 分页的数组, 供SQL用的LIMIT, 显示用HTML
    $arr['limit'] = ($curr_page - 1) * $perpage . ',' . $perpage;
    $arr['html'] = $multipage;

    return $arr;
}

/**
 * 标的详情页面 投资记录JS分页
 *
 * @param type $num
 * @param type $curr_page
 * @param type $array
 * @param type $perpage
 * @param type $setpages
 * @param type $class
 *
 * @return string
 */
function pagelisttender($num, $curr_page, $id, $perpage = 20, $setpages = 7, $class = 'a1') {
    $multipage = '';
    $curr_page = intval($curr_page) <= 0 ? 1 : intval($curr_page);
    if ($num > $perpage) {
        $page = $setpages + 1;
        $offset = ceil($setpages / 2 - 1);
        $pages = ceil($num / $perpage);
        $from = $curr_page - $offset;
        $to = $curr_page + $offset;
        $more = 0;
        if ($page >= $pages) {
            $from = 2;
            $to = $pages - 1;
        } else {
            if ($from <= 1) {
                $to = $page - 1;
                $from = 2;
            } elseif ($to >= $pages) {
                $from = $pages - ($page - 2);
                $to = $pages - 1;
            }
            $more = 1;
        }

        $multipage .= '<nav><ul class="pagination"> <li class="statistics"><a class="' . $class . '">共 ' . $num . ' 条  ' . $curr_page . '/' . $pages . '页 每页 ' . $perpage . ' 条</a></li>';
        if ($curr_page > 0) {
            $multipage .= '<li> <a href="javascript:tenderpage(' . ($curr_page - 1) . ',' . $id . ');" class="' . $class . '">上一页</a></li>';
            if ($curr_page == 1) {
                $multipage .= '<li class="active"> <span>1</span></li>';
            } elseif ($curr_page > 6 && $more) {
                $multipage .= '<li> <a href="javascript:tenderpage(1,' . $id . ');">1</a></li><li><span>..</span></li>';
            } else {
                $multipage .= ' <li><a href="javascript:tenderpage(1,' . $id . ');">1</a></li>';
            }
        }
        for ($i = $from; $i <= $to; $i++) {
            if ($i != $curr_page) {
                $multipage .= '<li> <a href="javascript:tenderpage(' . $i . ',' . $id . ');">' . $i . '</a></li>';
            } else {
                $multipage .= '<li class="active"> <span>' . $i . '</span></li>';
            }
        }
        if ($curr_page < $pages) {
            if ($curr_page < $pages - 5 && $more) {
                $multipage .= '<li><span> .. </span></li><li><a href="javascript:tenderpage(' . $pages . ',' . $id . ');">' . $pages . '</a></li> <li><a href="javascript:tenderpage(' . ($curr_page + 1) . ',' . $id . ');" class="' . $class . '">下一页</a></li></ul></nav>';
            } else {
                $multipage .= ' <li><a href="javascript:tenderpage(' . $pages . ',' . $id . ');">' . $pages . '</a></li><li> <a href="javascript:tenderpage(' . ($curr_page + 1) . ',' . $id . ');" class="' . $class . '">下一页</a></li></ul></nav>';
            }
        } elseif ($curr_page == $pages) {
            $multipage .= '<li class="active"> <span>' . $pages . '</span></li><li><a href="javascript:tenderpage(' . $curr_page . ',' . $id . ');" class="' . $class . '">下一页</a></li></ul></nav>';
        } else {
            $multipage .= ' <li><a href="javascript:tenderpage(' . $pages . ',' . $id . ');">' . $pages . '</a></li><li> <a href="javascript:tenderpage(' . ($curr_page + 1) . ',' . $id . ');" class="' . $class . '">下一页</a></li></ul></nav>';
        }
    }

    //返回数据:当前页, 分页的数组, 供SQL用的LIMIT, 显示用HTML
    $arr['limit'] = ($curr_page - 1) * $perpage . ',' . $perpage;
    $arr['html'] = $multipage;

    return $arr;
}

/**
 * 分页函数
 * JAMES  edit  20130910
 *
 * @param $num       信息总数
 * @param $curr_page 当前分页
 * @param $perpage   每页显示数
 * @param $urlrule   URL规则
 * @param $array     需要传递的数组，用于增加额外的方法
 * @param $setpages  显示在页面的按钮数
 *
 * @return 分页
 */
function pagelistajax($num, $curr_page, $array = [], $perpage = 20, $setpages = 7, $ajax_fun = 'getSloganList') {
    $multipage = '';
    $curr_page = intval($curr_page) <= 0 ? 1 : intval($curr_page);
    if ($num > $perpage) {
        $page = $setpages + 1;
        $offset = ceil($setpages / 2 - 1);
        $pages = ceil($num / $perpage);
        $from = $curr_page - $offset;
        $to = $curr_page + $offset;
        $more = 0;
        if ($page >= $pages) {
            $from = 2;
            $to = $pages - 1;
        } else {
            if ($from <= 1) {
                $to = $page - 1;
                $from = 2;
            } elseif ($to >= $pages) {
                $from = $pages - ($page - 2);
                $to = $pages - 1;
            }
            $more = 1;
        }


        $multipage .= '<div class="page container">';
        if ($curr_page > 0) {
            $multipage .= ' <a href="' . pageurlajax($curr_page - 1, $array, $ajax_fun) . '" >上一页</a>';
            if ($curr_page == 1) {
                $multipage .= ' <a class="active" href="javascript:void(0)">1</a>';
            } elseif ($curr_page > 6 && $more) {
                $multipage .= ' <a href="' . pageurlajax(1, $array, $ajax_fun) . '">1</a><span>..</span>';
            } else {
                $multipage .= ' <a href="' . pageurlajax(1, $array, $ajax_fun) . '">1</a>';
            }
        }
        for ($i = $from; $i <= $to; $i++) {
            if ($i != $curr_page) {
                $multipage .= ' <a href="' . pageurlajax($i, $array, $ajax_fun) . '">' . $i . '</a>';
            } else {
                $multipage .= ' <a class="active"  href="javascript:void(0)"> ' . $i . '</a>';
            }
        }
        if ($curr_page < $pages) {
            if ($curr_page < $pages - 5 && $more) {
                $multipage .= ' <span> .. </span><a href="' . pageurlajax($pages, $array, $ajax_fun) . '">' . $pages . '</a> <a href="' . pageurlajax($curr_page + 1, $array, $ajax_fun) . '" >下一页</a>';
            } else {
                $multipage .= ' <a href="' . pageurlajax($pages, $array, $ajax_fun) . '">' . $pages . '</a> <a href="' . pageurlajax($curr_page + 1, $array, $ajax_fun) . '" >下一页</a>';
            }
        } elseif ($curr_page == $pages) {
            $multipage .= ' <a class="active"   href="javascript:void(0)">' . $pages . '</a> <a href="' . pageurlajax($curr_page, $array, $ajax_fun) . '" >下一页</a>';
        } else {
            $multipage .= ' <a href="' . pageurlajax($pages, $array, $ajax_fun) . '">' . $pages . '</a> <a href="' . pageurlajax($curr_page + 1, $array, $ajax_fun) . '" >下一页</a></div>';
        }
    }

    //返回数据:当前页, 分页的数组, 供SQL用的LIMIT, 显示用HTML
    $arr['limit'] = ($curr_page - 1) * $perpage . ',' . $perpage;
    $arr['html'] = $multipage;

    return $arr;
}

function pageurlajax($page, $array = [], $ajax_fun) {
    return "javascript:" . $ajax_fun . "($page)";
}

/**
 * 返回分页路径
 *
 * @param $page  当前页
 * @param $array 需要传递的数组，用于增加额外的方法
 *
 * @return 完整的URL路径
 */
function pageurl($page, $array = []) {
    $url = url(ROUTE_M, ROUTE_C, ROUTE_A);
    if (is_array($array)) {
        $array['page'] = $page;
        if (!strexists($url, "?")) {
            $url .= "?";
        }
        foreach ($array as $k => $v) {
            if (trim(strval($v)) != "") {
                $url .= "&" . "$k=" . $v;
            }
        }
        $url = str_replace("?&", "?", $url);
    }

    return $url;
}

/**
 * 慈善基金的分页函数
 * JAMES  edit  20130910
 *
 * @param $num       信息总数
 * @param $curr_page 当前分页
 * @param $perpage   每页显示数
 * @param $urlrule   URL规则
 * @param $array     需要传递的数组，用于增加额外的方法
 * @param $setpages  显示在页面的按钮数
 *
 * @return 分页
 */
function pagecare($num, $curr_page, $careid, $perpage = 20, $setpages = 7, $class = 'a1') {
    $multipage = '';
    $curr_page = intval($curr_page) <= 0 ? 1 : intval($curr_page);
    if ($num > $perpage) {
        $page = $setpages + 1;
        $offset = ceil($setpages / 2 - 1);
        $pages = ceil($num / $perpage);
        $from = $curr_page - $offset;
        $to = $curr_page + $offset;
        $more = 0;
        if ($page >= $pages) {
            $from = 2;
            $to = $pages - 1;
        } else {
            if ($from <= 1) {
                $to = $page - 1;
                $from = 2;
            } elseif ($to >= $pages) {
                $from = $pages - ($page - 2);
                $to = $pages - 1;
            }
            $more = 1;
        }

        $multipage .= '<nav><ul class="pagination"> <li class="statistics"><a class="' . $class . '">共 ' . $num . ' 条  ' . $curr_page . '/' . $pages . '页 每页 ' . $perpage . ' 条</a></li>';
        if ($curr_page > 0) {
            $spage = $curr_page - 1;
            $multipage .= '<li id="care' . $spage . '"> <a onclick="carejs(' . $spage . ',' . $careid . ')" class="' . $class . '">上一页</a></li>';
            if ($curr_page == 1) {
                $multipage .= '<li id="care1" class="active"> <span>1</span></li>';
            } elseif ($curr_page > 6 && $more) {
                $multipage .= '<li id="care1"> <a onclick="carejs(1,' . $careid . ')">1</a></li><li><span>..</span></li>';
            } else {
                $multipage .= ' <li id="care1"><a onclick="carejs(1,' . $careid . ')">1</a></li>';
            }
        }
        for ($i = $from; $i <= $to; $i++) {
            if ($i != $curr_page) {
                $multipage .= '<li id="care' . $i . '"> <a onclick="carejs(' . $i . ',' . $careid . ')">' . $i . '</a></li>';
            } else {
                $multipage .= '<li id="care' . $i . '" class="active"> <span>' . $i . '</span></li>';
            }
        }
        $xpage = $curr_page + 1;
        if ($curr_page < $pages) {
            if ($curr_page < $pages - 5 && $more) {
                $multipage .= '<li><span> .. </span></li><li id="care' . $pages . '"><a onclick="carejs(' . $pages . ',' . $careid . ')">' . $pages . '</a></li> <li id="care' . $pages . '"><a onclick="carejs(' . $xpage . ',' . $careid . ')" class="' . $class . '">下一页</a></li></ul></nav>';
            } else {
                $multipage .= ' <li id="care' . $pages . '"><a onclick="carejs(' . $pages . ',' . $careid . ')">' . $pages . '</a></li><li id="care' . $xpage . '"> <a  onclick="carejs(' . $xpage . ',' . $careid . ')" class="' . $class . '">下一页</a></li></ul></nav>';
            }
        } elseif ($curr_page == $pages) {
            $multipage .= '<li> <span>' . $pages . '</span></li><li id="care' . $curr_page . '"><a onclick="carejs(' . $curr_page . ',' . $careid . ')" class="' . $class . '">下一页</a></li></ul></nav>';
        } else {
            $multipage .= ' <li id="care' . $pages . '"><a onclick="carejs(' . $pages . ',' . $careid . ')">' . $pages . '</a></li><li id="care' . $xpage . '"> <a onclick="carejs(' . $xpage . ',' . $careid . ')" class="' . $class . '">下一页</a></li></ul></nav>';
        }
    }

    //返回数据:当前页, 分页的数组, 供SQL用的LIMIT, 显示用HTML
    $arr['limit'] = ($curr_page - 1) * $perpage . ',' . $perpage;
    $arr['html'] = $multipage;

    return $arr;
}

/**
 * 微信分页函数
 * JAMES  edit  20130910
 *
 * @param $num       信息总数
 * @param $curr_page 当前分页
 * @param $perpage   每页显示数
 * @param $urlrule   URL规则
 * @param $array     需要传递的数组，用于增加额外的方法
 * @param $setpages  显示在页面的按钮数
 *
 * @return 分页
 */
function wxpagelist($num, $curr_page, $array = [], $perpage = 20, $setpages = 2, $class = 'a1') {
    $multipage = '';
    $curr_page = intval($curr_page) <= 0 ? 1 : intval($curr_page);
    if ($num > $perpage) {
        $page = $setpages + 1;
        $offset = ceil($setpages / 2 - 1);
        $pages = ceil($num / $perpage);
        $from = $curr_page - $offset;
        $to = $curr_page + $offset;
        $more = 0;
        if ($page >= $pages) {
            $from = 2;
            $to = $pages - 1;
        } else {
            if ($from <= 1) {
                $to = $page - 1;
                $from = 2;
            } elseif ($to >= $pages) {
                $from = $pages - ($page - 2);
                $to = $pages - 1;
            }
            $more = 1;
        }

        $multipage .= '<nav><ul class="pagination"> ';
        if ($curr_page > 0) {
            $multipage .= '<li> <a href="' . pageurl($curr_page - 1, $array) . '" class="' . $class . '">上一页</a></li>';
            if ($curr_page == 1) {
                $multipage .= '<li class="active"> <span>1</span></li>';
            } elseif ($curr_page > 6 && $more) {
                $multipage .= '<li> <a href="' . pageurl(1, $array) . '">1</a></li><li><span>..</span></li>';
            } else {
                $multipage .= ' <li><a href="' . pageurl(1, $array) . '">1</a></li>';
            }
        }
        for ($i = $from; $i <= $to; $i++) {
            if ($i != $curr_page) {
                $multipage .= '<li> <a href="' . pageurl($i, $array) . '">' . $i . '</a></li>';
            } else {
                $multipage .= '<li class="active"> <span>' . $i . '</span></li>';
            }
        }
        if ($curr_page < $pages) {
            if ($curr_page < $pages - 5 && $more) {
                $multipage .= '<li><span> .. </span></li><li><a href="' . pageurl($pages, $array) . '">' . $pages . '</a></li> <li><a href="' . pageurl($curr_page + 1, $array) . '" class="' . $class . '">下一页</a></li></ul></nav>';
            } else {
                $multipage .= ' <li><a href="' . pageurl($pages, $array) . '">' . $pages . '</a></li><li> <a href="' . pageurl($curr_page + 1, $array) . '" class="' . $class . '">下一页</a></li></ul></nav>';
            }
        } elseif ($curr_page == $pages) {
            $multipage .= '<li> <span>' . $pages . '</span></li><li><a href="' . pageurl($curr_page, $array) . '" class="' . $class . '">下一页</a></li></ul></nav>';
        } else {
            $multipage .= ' <li><a href="' . pageurl($pages, $array) . '">' . $pages . '</a></li><li> <a href="' . pageurl($curr_page + 1, $array) . '" class="' . $class . '">下一页</a></li></ul></nav>';
        }
    }

    //返回数据:当前页, 分页的数组, 供SQL用的LIMIT, 显示用HTML
    $arr['limit'] = ($curr_page - 1) * $perpage . ',' . $perpage;
    $arr['html'] = $multipage;

    return $arr;
}

//获取中文字符串
function strlen_cn($str) {
    $str = preg_replace("/([" . chr(228) . chr(128) . chr(128) . "-" . chr(233) . chr(191) . chr(191) . "])/u", "*", $str);

    return strlen($str);
}

//获取字符串
function getstr($string, $length, $in_slashes = 0, $out_slashes = 0, $html = 0) {

    $string = trim($string);

    if ($in_slashes) {
        //传入的字符有slashes
        $string = stripslashes_deep($string);
    }
    if ($html < 0) {
        //去掉html标签
        $string = preg_replace("/(\<[^\<]*\>|\r|\n|\s|\[.+?\])/is", ' ', $string);
        //$string = shtmlspecialchars($string);
    } elseif ($html == 0) {
        //转换html标签
        //$string = shtmlspecialchars($string);
    }
    $string = trim(str_replace("&nbsp;", " ", $string));

    if ($length && strlen($string) > $length) {
        //截断字符
        $wordscut = '';
        //utf8编码
        $n = 0;
        $tn = 0;
        $noc = 0;
        while ($n < strlen($string)) {
            $t = ord($string[$n]);
            if ($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
                $tn = 1;
                $n++;
                $noc++;
            } elseif (194 <= $t && $t <= 223) {
                $tn = 2;
                $n += 2;
                $noc += 2;
            } elseif (224 <= $t && $t < 239) {
                $tn = 3;
                $n += 3;
                $noc += 2;
            } elseif (240 <= $t && $t <= 247) {
                $tn = 4;
                $n += 4;
                $noc += 2;
            } elseif (248 <= $t && $t <= 251) {
                $tn = 5;
                $n += 5;
                $noc += 2;
            } elseif ($t == 252 || $t == 253) {
                $tn = 6;
                $n += 6;
                $noc += 2;
            } else {
                $n++;
            }
            if ($noc >= $length) {
                break;
            }
        }
        if ($noc > $length) {
            $n -= $tn;
        }
        $wordscut = substr($string, 0, $n);
        $string = $wordscut . "...";
    }
    if ($out_slashes) {
        $string = addslashes_deep($string);
    }
    $string = str_replace(" ", "&nbsp;", $string);

    return trim($string);
}

// getconn
function getconn() {
    static $db = null;
    if (!$db) {
        $arr = [
            'db_host'   => C('db_host'),
            'db_user'   => C('db_user'),
            'db_pwd'    => C('db_pwd'),
            'db_name'   => C('db_name'),
            'db_lang'   => C('db_lang'),
            'db_prefix' => C('db_prefix'),
            'pconnect'  => C('db_pcon'),
            'debug'     => C('db_debug'),
        ];
        if(!empty(C('db_port'))) {
            $arr['db_port'] = C('db_port');
        }
        O('mysql', '', 0);
        $db = new mysql($arr);
        if(empty(C('db_port'))) {
            $db->connect()->select_db();
        }
    }

    return $db;
}

function getConnectByDb($config) {
    static $db = null;
    if (!$db) {
        $arr = [
            'db_host'   => $config['db_host'],
            'db_user'   => $config['db_user'],
            'db_pwd'    => $config['db_pwd'],
            'db_name'   => $config['db_name'],
            'db_lang'   => C('db_lang'),
            'db_prefix' => C('db_prefix'),
            'pconnect'  => C('db_pcon'),
            'debug'     => C('db_debug'),
        ];
        O('mysql', '', 0);
        $db = new mysql($arr);
        if(empty(C('db_port'))){
            $db->connect()->select_db();
        }
    }

    return $db;
}


/**
 * APP接口结果JSON输出
 *
 * @param array $result
 */
function jsonResult($result) {
    if ($result['status'] == 0 && !isset($result['ret_msg'])) {
        $result['ret_msg'] = '';
    } else {
        $result['ret_msg'] = ErrorCode::errorMsg($result['status']);
    }
    echo json_encode($result);
    exit;
}

/**
 * APP接口结果JSON输出
 *
 * @param array $result
 */
function jsonReturn($result) {
    echo encode($result);
    exit;
}

/**
 * 导出数据为excel表格
 *
 * @param $data     一个二维数组,结构如同从数据库查出来的数组
 * @param $title    excel的第一行标题,一个数组,如果为空则没有标题
 * @param $filename 下载的文件名
 *
 * @examlpe
exportexcel($arr,array('id','账户','密码','昵称'),'文件名!');
 */
function exportexcel($data = [], $title = [], $filename = '') {
    $filename = empty($filename) ? ROUTE_C . date('YmdHis') : trim($filename);
    $c = [];

    $key = ord("A");
    $key2 = ord("@");
    foreach($title as $v) {
        if($key>ord("Z")){
            $key2 += 1;
            $key = ord("A");
            $c[] = chr($key2).chr($key);//超过26个字母时才会启用
        }else{
            if($key2>=ord("A")){
                $c[] = chr($key2).chr($key);//超过26个字母时才会启用
            }else{
                $c[] = chr($key);
            }
        }
        $key += 1;
    }

    include S_CORE . 'class' . DS . 'PHPExcel.php';
    include S_CORE . 'class' . DS . 'PHPExcel' . DS . 'Writer' . DS . 'Excel2007.php';

    $objPHPExcel = new PHPExcel();
    $objPHPExcel->setActiveSheetIndex(0);

    if (!empty($title)) {
        foreach ($title as $k => $v) {
            $objPHPExcel->getActiveSheet()->setCellValueExplicit($c[$k] . '1', $v, PHPExcel_Cell_DataType::TYPE_STRING);
        }
    }

    if (!empty($data)) {
        foreach ($data as $key => $val) {
            $i = 0;
            foreach ($val as $ck => $cv) {
                lg('export',var_export(array(
                    '$key'=>$key,
                    '$val'=>$val,
                    '$ck'=>$ck,
                    '$cv)'=>$cv,
                ),1));
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($c[$i] . ($key + 2), $cv, PHPExcel_Cell_DataType::TYPE_STRING);
                $i++;
            }
        }
    }

    $objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
    header("Content-Type:application/force-download");
    header("Content-Type:application/vnd.ms-execl");
    header("Content-Type:application/octet-stream");
    header("Content-Type:application/download");
    header('Content-Disposition:attachment;filename="' . $filename . '.xlsx"');
    header("Content-Transfer-Encoding:binary");
    $objWriter->save('php://output');
}

/**
 * 隐藏用户名
 *
 * @param unknown_type $username
 *
 * @return unknown
 */
function f_name($username, $num = 2) {
    return mb_substr($username, 0, $num, "UTF-8") . "***";
}

/**
 * 隐藏邮箱账号
 *
 * @param unknown_type $email
 *
 * @return unknown
 */
function f_mail($email) {
    $endstr = explode('@', $email);

    return mb_substr($endstr[0], 0, 3, "UTF-8") . '***@' . $endstr[1];
}

/**
 * 隐藏银行帐号
 *
 * @param unknown_type $bank
 *
 * @return unknown
 */
function f_bank($bank) {
    return substr($bank, 0, -8) . "****" . substr($bank, -4);
}

/**
 * 隐藏真实姓名
 *
 * @param string $realname
 *
 * @return string
 */
function f_realname($realname) {
    $halfLen = intval(mb_strlen($realname, 'UTF-8') / 2);
    $hideName = str_repeat('*', $halfLen) . mb_substr($realname, $halfLen, null, 'UTF-8');

    return $hideName;
}

/**
 * 隐藏身份证号码
 *
 * @param string $cardid
 *
 * @return string
 */
function f_cardid($cardid) {
    return substr($cardid, 0, -8) . "******" . substr($cardid, -2);
}

/**
 * 格式化金额
 *
 * @param unknown_type $money
 */
function f_money($money) {
    if ($money >= 10000) {
        $str = round($money / 10000, 2) . "万";
    } else {
        $str = $money;
    }

    return $str;
}

/**
 * 加密解密处理
 *
 * @param unknown_type $string    密文
 * @param unknown_type $operation 加密 或 解密
 * @param unknown_type $key       密匙
 *
 * @return unknown
 */
function dencrypt($string, $operation = 'DECODE', $key = '') {
    if (empty($string)) {
        return false;
    }
    $operation != 'ENCODE' && $string = base64_decode(substr($string, 16));  //如果是解密就截16位以后的字符 并base64解密
    $code = '';
    $key = md5($key); //md5密匙
    $keya = strtoupper(substr($key, 0, 16));      //截取新密匙的前16位并大写
    $keylen = strlen($key);                      //计算密匙长度
    $strlen = strlen($string);
    $rndkey = [];
    for ($i = 0; $i < 128; $i++) {
        $rndkey[$i] = ord($key[$i % $keylen]);  //生成128个加密因子  （按密匙中单个字符的ASCII 值）

    }
    for ($i = 0; $i < $strlen; $i++) {
        $code .= chr(ord($string[$i]) ^ $rndkey[$i * $strlen % 128]);  //用字条串的每个字符ASCII值和加密因子里的（当前循环次数*字符串长度 求于 128） 按位异或  最后 ASCII 值返回字符
    }
    return ($operation != 'DECODE' ? $keya . str_replace('=', '', base64_encode($code)) : $code);  // 如果是加密就截取新密匙的前16位并加上base64加密码生成的密文
}

/**
 * 生成上传附件验证
 *
 * @param $args   参数
 */
function upload_key($args) {
    $pc_auth_key = md5(C('auth_key') . $_SERVER['HTTP_USER_AGENT']);
    $authkey = md5("snyni" . $args . $pc_auth_key);

    return $authkey;
}

//产生form防伪码
function formhash() {
    $formhash = substr(md5(substr(SYS_TIME, 0, -5) . '|' . $_SERVER['HTTP_USER_AGENT'] . '|' . md5(C('auth_key'))), 8, 8);

    return $formhash;
}

//判断提交是否正确
function submitcheck($var) {
    if (!empty($_POST[$var]) && $_SERVER['REQUEST_METHOD'] == 'POST') {
        if ((empty($_SERVER['HTTP_REFERER']) || preg_replace("/https?:\/\/([^\:\/]+).*/i", "\\1", $_SERVER['HTTP_REFERER']) == preg_replace("/([^\:]+).*/", "\\1", $_SERVER['HTTP_HOST'])) && $_POST['formhash'] == formhash()) {
            return true;
        } else {
            alert('您的请求来路不正确或表单验证串不符，无法提交。请尝试使用标准的web浏览器进行操作。');
        }
    } else {
        return false;
    }
}

/**
 * 转换字节数为其他单位
 *
 * @param    string $filesize 字节大小
 *
 * @return    string    返回大小
 */
function sizecount($filesize) {
    if ($filesize >= 1073741824) {
        $filesize = round($filesize / 1073741824 * 100) / 100 . ' GB';
    } elseif ($filesize >= 1048576) {
        $filesize = round($filesize / 1048576 * 100) / 100 . ' MB';
    } elseif ($filesize >= 1024) {
        $filesize = round($filesize / 1024 * 100) / 100 . ' KB';
    } else {
        $filesize = $filesize . ' Bytes';
    }

    return $filesize;
}

/**
 * 安全过滤函数
 *
 * @param $string
 *
 * @return string
 */
function safe_replace($string) {
    $string = str_replace('%20', '', $string);
    $string = str_replace('%27', '', $string);
    $string = str_replace('%2527', '', $string);
    $string = str_replace('*', '', $string);
    $string = str_replace('"', '&quot;', $string);
    $string = str_replace("'", '', $string);
    $string = str_replace('"', '', $string);
    $string = str_replace(';', '', $string);
    $string = str_replace('<', '&lt;', $string);
    $string = str_replace('>', '&gt;', $string);
    $string = str_replace("{", '', $string);
    $string = str_replace('}', '', $string);
    $string = str_replace('\\', '', $string);

    return $string;
}

/**
 * 取得文件扩展
 *
 * @param $filename 文件名
 *
 * @return 扩展名
 */
function fileext($filename) {
    return strtolower(trim(substr(strrchr($filename, '.'), 1, 10)));
}

/**
 * IE浏览器判断
 */
function is_ie() {
    $useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
    if ((strpos($useragent, 'opera') !== false) || (strpos($useragent, 'konqueror') !== false))
        return false;
    if (strpos($useragent, 'msie ') !== false)
        return true;

    return false;
}

/**
 *  函数功能：计算身份证号码中的检校码
 *  函数名称：idcard_verify_number
 *  参数表 ：string $idcard_base 身份证号码的前十七位
 *  返回值 ：string 检校码
 */
function idcard_verify_number($idcard_base) {
    $card_len = strlen($idcard_base);
    if ($card_len != 17) {
        return false;
    }
    $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2]; //debug 加权因子
    $verify_number_list = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2']; //debug 校验码对应值
    $checksum = 0;
    for ($i = 0; $i < $card_len; $i++) {
        $checksum += substr($idcard_base, $i, 1) * $factor[$i];
    }
    $mod = $checksum % 11;
    $verify_number = $verify_number_list[$mod];

    return $verify_number;
}

/**
 * 函数功能：18位身份证校验码有效性检查
 *  函数名称：idcard_checksum18
 * 参数表 ：string $idcard 十八位身份证号码
 * 返回值 ：bool
 */
function idcard_checksum18($idcard) {
    if (strlen($idcard) != 18) {
        return false;
    }
    $idcard_base = substr($idcard, 0, 17);
    if (idcard_verify_number($idcard_base) != strtoupper(substr($idcard, 17, 1))) {
        return false;
    } else {
        return true;
    }
}

/**
 * 函数功能：身份证号码检查接口函数
 * 函数名称：check_id
 * 参数表 ：string $idcard 身份证号码
 * 返回值 ：bool 是否正确
 */
function isIdCard($idcard) {
    if (strlen($idcard) == 15) {
        // 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码
        $idcard = substr($idcard, 0, 6);
        if (array_search(substr($idcard, 12, 3), ['996', '997', '998', '999']) !== false) {
            $idcard .= '18';
        } else {
            $idcard .= '19';
        }
        $idcard .= substr($idcard, 6, 9) . idcard_verify_number($idcard);
    }
    if (strlen($idcard) == 18 && idcard_checksum18($idcard)) {
        return true;
    } else {
        return false;
    }
}

function remaining_time($time) {
    $r_time = $time - SYS_TIME;
    if ($r_time > 0) {
        $str = '';
        if ($r_time >= 86400) {
            $str .= intval($r_time / 86400) . "天";
            $r_time = $r_time % 86400;
        }
        if ($r_time >= 3600) {
            $str .= intval($r_time / 3600) . "小时";
            $r_time = $r_time % 3600;
        }
        if ($r_time >= 60) {
            $str .= intval($r_time / 60) . "分";
            $r_time = $r_time % 60;
        }
        if ($r_time > 0) {
            $str .= $r_time . "秒";
        }

        return $str;
    } else {
        return "已过期";
    }
}

/**
 * 获取缩小图
 *
 * @param unknown_type $file
 *
 * @return unknown
 */
function get_thumb($file) {
    $t_file = str_replace(".", "_t.", $file);
    if (file_exists(C('upfile_path') . DS . $t_file)) {
        return $t_file;
    } else {
        return $file;
    }
}

function unescape($str) {
    $ret = '';
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        if ($str[$i] == '%' && $str[$i + 1] == 'u') {
            $val = hexdec(substr($str, $i + 2, 4));
            if ($val < 0x7f)
                $ret .= chr($val);
            else if ($val < 0x800)
                $ret .= chr(0xc0 | ($val >> 6)) . chr(0x80 | ($val & 0x3f));
            else
                $ret .= chr(0xe0 | ($val >> 12)) . chr(0x80 | (($val >> 6) & 0x3f)) . chr(0x80 | ($val & 0x3f));
            $i += 5;
        } else if ($str[$i] == '%') {
            $ret .= urldecode(substr($str, $i, 3));
            $i += 2;
        } else
            $ret .= $str[$i];
    }

    return $ret;
}

function get_string_between($string, $start, $end) {
    $string = " " . $string;
    $ini = strpos($string, $start);
    if ($ini == 0)
        return "";
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;

    return substr($string, $ini, $len);
}

function file_force_contents($path, $contents) {

    $info = pathinfo($path);
    if (!is_dir($info['dirname'])) {
        if (!mkdir($info['dirname'], 0, true)) {
//            die('Failed to create folders...');
        }
    }
    @file_put_contents($path, $contents, FILE_APPEND);
}

if (!function_exists('cut_str')) {

    /**
     * 隐藏用户名
     *
     * @param type $string
     * @param type $sublen
     * @param type $start
     * @param type $code
     *
     * @return type
     */
    function cut_str($string, $sublen, $start = 0, $code = 'UTF-8') {
        if ($code == 'UTF-8') {
            $pa = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/";
            preg_match_all($pa, $string, $t_string);
            if (count($t_string[0]) - $start > $sublen)
                return join('', array_slice($t_string[0], $start, $sublen));

            return join('', array_slice($t_string[0], $start, $sublen));
        } else {
            $start = $start * 2;
            $sublen = $sublen * 2;
            $strlen = strlen($string);
            $tmpstr = '';
            for ($i = 0; $i < $strlen; $i++) {
                if ($i >= $start && $i < ($start + $sublen)) {
                    if (ord(substr($string, $i, 1)) > 129) {
                        $tmpstr .= substr($string, $i, 2);
                    } else {
                        $tmpstr .= substr($string, $i, 1);
                    }
                }
                if (ord(substr($string, $i, 1)) > 129)
                    $i++;
            }

            //if(strlen($tmpstr)< $strlen ) $tmpstr.= "...";
            return $tmpstr;
        }
    }

}

/**
 * 获取memcached实例
 * @return Memcached
 */
function getMemc() {
    static $memc = null;
    if (!$memc) {
        $memc = new Memcached();
        $memc->addServer(C('memc_host'), C('memc_port'));
    }

    return $memc;
}

/**
 * memcached读写缓存
 *
 * @param string $key  缓存的key值
 * @param mixed $value 值（空字符串代表读取，null为删除缓存，其他值为写入缓存）
 * @param int $expire  生存时间（0为永久有效，大于0的为缓存的秒数）
 *
 * @return mixed
 */
function M($key, $value = '', $expire = 0) {
    static $cache = [];
    if ($value === '') { //读取
        if (isset($cache[$key])) {
            $result = $cache[$key];
        } else {
            $result = getMemc()->get($key);
            $cache[$key] = $result;
        }
    } elseif ($value === null) { //删除
        unset($cache[$key]);
        getMemc()->delete($key);
        $result = null;
    } else { //写入缓存
        unset($cache[$key]);
        $expire = $expire > 0 ? $expire : 0;
        $result = getMemc()->set($key, $value, $expire);
    }

    return $result;
}


//连接Redis简写
function CR(){
    return initCacheRedis();
}

//断开Redis简写
function DR($redis){
    $redis->close();
}

/**
 * @desc 连接CacheRedis缓存数据库
 */
function initCacheRedis() {
    //该参数待放入配置文件
    $redis_config = C('redis_config');
  	
    $cache_redis = new redis();
    $ret = $cache_redis->connect($redis_config['host'], $redis_config['port']);
    if (!$ret) {
        ErrorCode::errorResponse(9001, 'redis connect error');
        echo 'redis connect error';

        return;
    }
    if($redis_config['pass']) {
        // 关闭redis密码认证
        $ret = $cache_redis->auth($redis_config['pass']);
        if (!$ret) {
            echo 'redis auth error';

            return;
            //ErrorCode::errorResponse(9002, 'redis auth error');
        }
    }

    //如查有配置Redis的库
    if(!empty($redis_config['db'])){
        $cache_redis->select($redis_config['db']);
    }
    return $cache_redis;
}

/**
 * @desc 关闭CacheRedis缓存
 */
function deinitCacheRedis($redis) {
    $redis->close();
}

//处理数组中的值
function deal_array($data) {
    if (!empty($data) && is_array($data)) {
        foreach ($data as $k => $v) {
            $data[$k] = strip_tags(trim($v));
        }
    }

    return $data;
}

/**
 * 字符串截断+省略
 * @return [string] [$text]
 */
function subtext($text, $length, $start = null) {
    if ($start !== null) {
        if (mb_strlen($text, 'utf8') > $length) {
            return mb_substr($text, $start, $length, 'utf8');
        } else {
            return $text;
        }
    } else {
        if (mb_strlen($text, 'utf8') > $length) {
            return mb_substr($text, 0, $length, 'utf8') . '…';
        } else {
            return $text;
        }
    }
}

/**
 * 换算游戏币
 *
 * @param
 */
function convert($money) {
    $redis = initCacheRedis();
    $rmbratio = $redis->HMGet("Config:rmbratio", ['value']);
    $newMoney = bcmul($money, $rmbratio['value'], 2);
    //关闭redis链接
    deinitCacheRedis($redis);

    if (substr($newMoney, -2) == '00') {
        $newMoney = substr($newMoney, 0, -3);
    } elseif (substr($newMoney, -2, 1) != '0' && substr($newMoney, -1) == '0') {
        $newMoney = substr($newMoney, 0, -1);
    }

    return $newMoney;
}


function convert1($money) {
    if (substr($money, -2) == '00') {
        $money = substr($money, 0, -3);
    } elseif (substr($money, -2, 1) != '0' && substr($money, -1) == '0') {
        $money = substr($money, 0, -1);
    }
    return $money;
}


/**
 * curl post
 * @param $url string 请求地址
 * @param $data array 传入参数
 * @param $header array 返回Header
 * @param $nobody array 返回body
 * @return json
 */
function curl_post_content($url, $data = [], $header = false, $nobody = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, $header);//返回Header
    curl_setopt($ch, CURLOPT_NOBODY, $nobody);//不需要内容
    curl_setopt($ch, CURLOPT_POST, true);//POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output,true);
}

/**
 * curl post
 * @param $url string 请求地址
 * @param $data array 传入参数
 * @param $header array 返回Header
 * @param $nobody array 返回body
 * @return mixed
 */
function curl_post($url, $data = [], $header = false, $nobody = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, $header);//返回Header
    curl_setopt($ch, CURLOPT_NOBODY, $nobody);//不需要内容
    curl_setopt($ch, CURLOPT_POST, true);//POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT,5); //防止超时卡顿
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    $output = curl_exec($ch);
    curl_close($ch);
//    echo '111111x';
//    dump($output);
    return $output;
}

/**
 * curl get
 * @param $url string 请求地址
 * @return mixed
 */
function curl_get_content($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

/**
 * CURL
 */
function curl_get($myurl){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$myurl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);
    curl_close ($ch);
    return $result;
}

/**
 * 新增消息提醒
 */
function addMsgCue($type, $arr = []) {
    $redis = initCacheRedis();
    $res = $redis->hMSet($type, $arr);
    //关闭redis链接
    deinitCacheRedis($redis);

    if (!$res) {
        return false;
    }

    return true;
}

/**
 * 获取消息提醒
 */
function getMsgCue($type) {
//    lg('tsy',$type);
    $redis = initCacheRedis();
    $res = $redis->hGetAll($type);
    $redis->del($type);
    //关闭redis链接
    deinitCacheRedis($redis);

    return $res;
}

/**
 * 新增消息提醒字符串
 */
function redisAddMsgCue($type, $arr = []) {
    $redis = initCacheRedis();
    $res = $redis->Set($type, $arr);
    //关闭redis链接
    deinitCacheRedis($redis);

    if (!$res) {
        return false;
    }

    return true;
}

/**
 * 获取消息提醒字符串
 */
function redisGet($type) {
    $redis = initCacheRedis();
    $res = $redis->Get($type);
    $redis->del($type);
    //关闭redis链接
    deinitCacheRedis($redis);
    return $res;
}

/**
 * xml数组转化
 *
 * @param type $xml
 *
 * @return $arr
 */
function xmlToArray($xml) {
    //禁止引用外部xml实体
    libxml_disable_entity_loader(true);
    $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    $val = json_decode(json_encode($xmlstring), true);

    return $val;
}

/**
 *数组转xml
 *@$data 数组
 *@return xml
 **/

function arrayToXml($data) {
    $xml = "<xml>";
    foreach ($data as $k => $v) {
        $xml .= '<'.$k.'>'.$v.'</'.$k.'>';
    }
    $xml .= "</xml>";
    return $xml;
}

/**
 * 上传mp3
 * @return string
 */
function upLodeMp3($file) {
    $file_name = date("YmdHis") . rand(100, 999);
    $file_all = "up_files/music/" . $file_name . ".mp3";
    if (move_uploaded_file($file["tmp_name"], $file_all)) {
        return $file_all;
    } else {
        return false;
    }
}

/**
 * 上传图片
 * @return string
 */
function upLodeImg($file) {
    if ($file['error'] != 0) {
        if ($file['error'] == 1) {
            $arr['code'] = -1;
            $arr['msg'] = "最大支持上传600KB大小的图片";

            return $arr;
        } elseif ($file['error'] == 2) {
            $arr['code'] = -1;
            $arr['msg'] = "上传文件的大小超过表单的最大值";

            return $arr;
        } elseif ($file['error'] == 3) {
            $arr['code'] = -1;
            $arr['msg'] = "文件只有部分被上传";

            return $arr;
        } elseif ($file['error'] == 4) {
            $arr['code'] = -1;
            $arr['msg'] = "没有文件被上传";

            return $arr;
        } elseif ($file['error'] == 6) {
            $arr['code'] = -1;
            $arr['msg'] = "找不到临时文件夹";

            return $arr;
        } elseif ($file['error'] == 7) {
            $arr['code'] = -1;
            $arr['msg'] = "文件写入失败";

            return $arr;
        }
    }
    if (!empty($file['tmp_name'])) {
        $hzName = substr($file['name'], strrpos($file['name'], '.') + 1);
        $imageexts = ['gif', 'jpg', 'jpeg', 'png', 'bmp'];
        if (!in_array($hzName, $imageexts)) {
            $arr['code'] = -1;
            $arr['msg'] = "非法文件格式";

            return $arr;
        }
        if ($file['size'] > 600 * 1024) {
            $arr['code'] = -1;
            $arr['msg'] = "最大支持上传600KB大小的图片";

            return $arr;
        }
        $file_name = date("YmdHis") . rand(100, 999);
        $file_all = "up_files/room/" . $file_name . "." . substr(strrchr($file['name'], '.'), 1);
        if (move_uploaded_file($file["tmp_name"], $file_all)) {
            $arr['code'] = 0;
            $arr['msg'] = "/".$file_all;
        } else {
            $arr['code'] = -1;
            $arr['msg'] = "上传失败";
        }

        return $arr;
    } else {
        $arr['code'] = -1;
        $arr['msg'] = "请选择上传文件";

        return $arr;
    }
}

// 转义html实体 标记
function htmlspecialchars_decode_deep($l1) {
    // $l1 = is_array($l1) ? array_map('htmlspecialchars_decode_deep', $l1) : htmlspecialchars_decode($l1);
    $arr = ["<" => "&lt;", ">" => "&gt;"];
    $l1 = is_array($l1) ? array_map('htmlspecialchars_decode_deep', $l1) : strtr($l1, $arr);

    /* if(is_array($l1)){
         $l1 = array_map('htmlspecialchars_decode_deep', $l1);
     }else{
         if(preg_match("/<*>/",$l1)){
             $l1 = strtr($l1,$arr);
         }
     }*/

    return $l1;
}

function interceptChinese($str) {
    $str=decrypt($str);
    $len = mb_strlen($str, 'utf-8');
    $str1 = mb_substr($str, 0, 1, 'utf-8');
    $str2 = mb_substr($str, $len - 1, 1, 'utf-8');

    return $str1 . '***' . $str2;
}


/**
 * 极光推送--按指定人员发送消息
 *
 * @param $title    内容标题
 * @param $msg      内容
 * @param $userids  用户id集合
 * @param $conf     配置
 * @param $type     私信、公告
 *
 * @return Exception|int|\JPush\Exceptions\APIConnectionException|\JPush\Exceptions\APIRequestException
 */
function push_send($title, $msg, $userids, $conf, $type) {
    require './JPush/autoload.php';
    $app_key = $conf['app_key'];
    $master_secret = $conf['master_secret'];
    $alias = $conf['user_alias'];
    $client = new \JPush\Client($app_key, $master_secret);
    $idss = substr($userids, 1, strlen($userids) - 2);
    $idss = explode('|', $idss);
    foreach ($idss as $k => $v) {
        $ids[$k] = $alias . $v;
    }

    try {
        $result = $client->push()
            ->setPlatform('all')
            //->addAllAudience()
            ->addAlias($ids)
            ->iosNotification($msg, [
                'title'  => $title,
                'sound'  => 'sound',  //提示音
                'badge'  => '+1',     //信息+1
                'extras' => [
                    'type' => $type  //附加信息
                ]
            ])
            ->androidNotification($msg, [
                'title'  => $title,
                'sound'  => 'sound',
                'badge'  => '+1',
                'extras' => [
                    'key' => $type
                ]
            ])
            ->message($msg, [
                'title'        => $title,
                'content_type' => 'text',
                'extras'       => [
                    'key' => $type,
                    'jiguang'
                ],
            ])
            ->options([
                'sendno'            => 100,        //表示推送序号，纯粹用来作为 API 调用标识，API 返回时被原样返回，以方便 API 调用方匹配请求与返回
                'time_to_live'      => 100,  //消息保存时间按秒计时
                //'override_msg_id' => 100,
                'apns_production'   => true,
                'big_push_duration' => 0
            ])
            ->send();

        return $result['http_code'] == 200 ? 200 : 0;
    } catch (\JPush\Exceptions\APIConnectionException $e) {
        // try something here
        return $e;
    } catch (\JPush\Exceptions\APIRequestException $e) {
        // try something here
        return $e;
    }
}

/**
 * 极光推送--给所有人员发送消息
 *
 * @param $title   内容标题
 * @param $msg     内容
 * @param $conf    配置
 * @param $type    私信、公告
 */
function push_send_all($title, $msg, $conf, $type) {
    require './JPush/autoload.php';
    $app_key = $conf['app_key'];
    $master_secret = $conf['master_secret'];
    $client = new \JPush\Client($app_key, $master_secret);

    try {
        $result = $client->push()
            ->setPlatform('all')
            ->addAllAudience()
            //->addAlias($ids)
            ->iosNotification($msg, [
                'title'  => $title,
                'sound'  => 'sound',  //提示音
                'badge'  => '+1',     //信息+1
                'extras' => [
                    'type' => $type
                ]
            ])
            ->androidNotification($msg, [
                'title'  => $title,
                'sound'  => 'sound',
                'badge'  => '+1',
                'extras' => [
                    'key' => $type
                ]
            ])
            ->message($msg, [
                'title'        => $title,
                'content_type' => 'text',
                'extras'       => [
                    'key' => $type,
                    'jiguang'
                ],
            ])
            ->options([
                'sendno'            => 100,        //表示推送序号，纯粹用来作为 API 调用标识，API 返回时被原样返回，以方便 API 调用方匹配请求与返回
                'time_to_live'      => 100,  //消息保存时间按秒计时
                //'override_msg_id' => 100,
                'apns_production'   => true,
                'big_push_duration' => 0
            ])
            ->send();

        return $result['http_code'] == 200 ? 200 : 0;
    } catch (\JPush\Exceptions\APIConnectionException $e) {
        return $e;
        // try something here
//        echo $e;
    } catch (\JPush\Exceptions\APIRequestException $e) {
        return $e;
        // try something here
//        echo $e;
    }
}

function checkIP($ip) {
    if (!preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $ip)) {
        return false;
    } else {
        return true;
    }
}

/**
 * 扣除积分
 * author: Aho
 *
 * @param $userId  用户ID
 *
 * @return mixed
 */
function loseScore($userId) {
    $res = json_decode(D('config')->getOneCoupon('value', "nid='honor_downgrade'")['value'], true);
    $loginTerm = $res['data']['loginData']['status'] ? $res['data']['loginData'] : 0;     //登录扣分条件
    $rechargeTerm = $res['data']['rechargeData']['status'] ? $res['data']['rechargeData'] : 0;  //充值扣分条件
    $betTerm = $res['data']['betData']['status'] ? $res['data']['betData'] : 0;       //投注扣分条件
    $nexus = $res['nexus'];  //扣分关系
    $now = time(); //现在的时间戳

    $userTime = D('user')->getOneCoupon('lastlogintime,regtime,honor_score,lose_time,lose_score', "id=$userId"); //最后登录时间戳、注册时间戳
    $userTime['lastlogintime']=$userTime['lastlogintime']?:$userTime['regtime'];
    $userTime['regtime']= strtotime(date("Y-m-d 23:59:59",$userTime['regtime']));
    $userTime['lastlogintime']= strtotime(date("Y-m-d 23:59:59",$userTime['lastlogintime']));
    $daysNotLogin = floor(($now - $userTime['lastlogintime']) / 86400);  //未登录天数
    $lastRechargeTime = D('accountrecharge')->getOneCoupon('addtime', "user_id=$userId and status=1", 'id desc')['addtime'];//最后充值时间戳
    $lastRechargeTime = $lastRechargeTime ?strtotime(date("Y-m-d 23:59:59",$lastRechargeTime)): $userTime['regtime'];
    $daysNotRecharge = floor(($now - $lastRechargeTime) / 86400);//未充值天数
    $lastBetTime = D('order')->getOneCoupon('addtime', "user_id=$userId and state=0", 'id desc')['addtime'];//最后投注时间戳
    $lastBetTime = $lastBetTime ?strtotime(date("Y-m-d 23:59:59",$lastBetTime)): $userTime['regtime'];
    $daysNotBet = floor(($now - $lastBetTime) / 86400);//未投注天数
    $loseTime = $userTime['lose_time']; //用户最后扣分时间
    $loseDays = (strtotime(date("Y-m-d 00:00:00",time())) > $loseTime)?true:false; //距离上次扣分天数
    $score = 0; //扣除分数
    if ($nexus == 1) {  //并存关系
        if ($loginTerm && $daysNotLogin && $loseDays ) { // 未登录X扣除的分数
            $score += floor($daysNotLogin / $loginTerm['day']) * $loginTerm['score'];
        }
        if ($rechargeTerm && $daysNotRecharge && $loseDays ) { // 未充值X扣除的分数
            $score += floor($daysNotRecharge / $rechargeTerm['day']) * $rechargeTerm['score'];
        }
        if ($betTerm && $daysNotBet && $loseDays ) { // 未登录X扣除的分数
            $score += floor($daysNotBet / $betTerm['day']) * $betTerm['score'];
        }
        $score = ($userTime['lose_score'] + $score) > $userTime['honor_score'] ? $userTime['honor_score'] : $userTime['lose_score'] + $score;
        return D('user')->db->query("update un_user set lose_score=$score,lose_time=$now where id=$userId");
    } else { //单一关系
        if ($loginTerm && $loseDays && $daysNotLogin) { // 未登录X扣除的分数
            $score = floor($daysNotLogin / $loginTerm['day']) * $loginTerm['score'];
        }
        if ($betTerm && $loseDays && $daysNotBet && !$score) { // 未投注X扣除的分数
            $score = floor($daysNotBet / $betTerm['day']) * $betTerm['score'];
        }
        if ($rechargeTerm && $loseDays && $daysNotRecharge && !$score) { // 未充值X扣除的分数
            $score = floor($daysNotRecharge / $rechargeTerm['day']) * $rechargeTerm['score'];
        }
        $score = ($userTime['lose_score'] + $score) > $userTime['honor_score'] ? $userTime['honor_score'] : $userTime['lose_score'] + $score;
        return D('user')->db->query("update un_user set lose_score=$score,lose_time=$now where id=$userId");
    }
}

/**
 * 用户充值加分
 * author: Aho
 *
 * @param $userId
 * @param $type
 */
function set_honor_score($userId) {
    $conf = json_decode(D('config')->getOneCoupon('value', "nid='honor_upgrade'")['value'], true); // 加分条件
    $userModel = D('user');
    $rechModel = D('accountrecharge');

    //累计充值
    $amount = $rechModel->db->result("select sum(money) from un_account_recharge where user_id=$userId and status=1");
    if ($conf['rechargeData']['status'] && $amount) {
        foreach ($conf['rechargeData']['data'] as $k => $v) {
            if (intval($amount) >= $v['then']) {
                $score = $v['end'];
            }
        }
        $score = empty($score)?0:$score;
        $userModel->db->query("update un_user set honor_score=$score where id=$userId");
    }
}


/**
 * 验证签名 公钥加密
 * @param $url string 请求地址
 * @param $data array 传入参数
*/
function signa($url,$data){
    //请求地址不能为空
    if(empty($url)){
        return false;
    }
    $signa = C('signa');
    //签名数据
    $key = $signa['key'];
    $secret_key = $signa['secret_key'];
    $param['timestamp'] = time();//时间戳
    $param['signature'] = md5(md5($param['timestamp']).$secret_key);//签名
    $param['key'] = $key;//key
    $param['source'] = 0;//接口来源:1 ios;  2 安卓; 3 H5; 4 PC ; 0 服务器本身
    $param['project'] = 0;//项目来源(0:pc手游, 1:讯彩; 2其它;  [待存字典表])
    $param['method'] = "POST";
    $params = base64_encode(json_encode($param));

    //业务数据
    $encrypted = "";
    if(!empty($data)){
        $datas = json_encode($data);

        //数据加密
//        $public_key = file_get_contents($signa['public_key']);
//        $pu_key = openssl_pkey_get_public($public_key);//这个函数可用来判断公钥是否是可用的
//        if(!$pu_key){
//            die("秘钥不可用!!");
//        }
//        $encrypted = "";
//        openssl_public_encrypt($datas,$encrypted,$pu_key);//公钥加密
//        $encrypted = base64_encode($encrypted);//加密后的内容通常含有特殊字符，需要编码转换下，在网络间通过url传输时要注意base64编码是否是url安全的
        $encrypted = dencrypt(base64_encode($datas),"ENCODE",$param['signature']);
    }

    //请求接口
    $res = curl_post($url, array('param'=>$params,'data'=>$encrypted));
    return $res;
}

//传入一个关联数组  返回一个索引数组
function toIndexArr($arr){
    $i=0;
    foreach($arr as $key => $value){
        $newArr[$i] = $value;
        $i++;
    }
    return $newArr;
}

/**
 * 验证签名
 * @param
 * @method
 */
function verificationSignature(){
    //当前时间戳
    $nowtime = time();

    //验证签名
    if(!$_REQUEST['param']){
        return array('status'=>'fail','msg'=>'签名失败','code'=>1);
    }
    $param = base64_decode($_REQUEST['param']);
    $param = json_decode($param,true);
    $timestamp = $param['timestamp']; //时间戳
    $signature = $param['signature']; //签名
    $key = $param['key']; //key
    $source= $param['source'];//接口来源:1 ios;  2 安卓; 3 H5; 4 PC
    $project = $param['project'];//项目来源(0:pc手游, 1:讯彩; 2其它;  [待存字典表])
    $method = strtoupper($param['method']);//POST, GET
    if(!$timestamp){
       lg('self_lottery_data_debug', "error-2: timestamp不能为空 \n");
        @file_put_contents('./scripts/api.log', "error-2: timestamp不能为空 \n", FILE_APPEND);
        return array('status'=>'fail','msg'=>"timestamp不能为空",'code'=>2);
    }
    if(!$key){
        @file_put_contents('./scripts/api.log', "error-2: secretKey不能为空 \n", FILE_APPEND);
        lg('self_lottery_data_debug', "error-2: secretKey不能为空 \n");
        return array('status'=>'fail','msg'=>"secretKey不能为空",'code'=>2);
    }
    if(!$signature){
        lg('self_lottery_data_debug', "error-2: signature不能为空 \n");
        @file_put_contents('./scripts/api.log', "error-2: signature不能为空 \n", FILE_APPEND);
        return array('status'=>'fail','msg'=>"signature不能为空",'code'=>2);
    }
    if(abs($nowtime-$timestamp)>300){
        lg('self_lottery_data_debug', "error-3: 签名时间相隔不得超过5分钟 ".abs($nowtime-$timestamp)." - ".$nowtime ."\n");
        @file_put_contents('./scripts/api.log', "error-3: 签名时间相隔不得超过5分钟 ".abs($nowtime-$timestamp)." - ".$nowtime ."\n", FILE_APPEND);
        return array('status'=>'fail','msg'=>'签名时间相隔不得超过5分钟','code'=>3);
    }
    $res = getSecret($key,$source,$project);
    if(!$res){
        lg('self_lottery_data_debug', "error-4: 密钥(".$key.")不存在\n");
        @file_put_contents('./scripts/api.log', "error-4: 密钥(".$key.")不存在\n", FILE_APPEND);
        return array('status'=>'fail','msg'=>"密钥($key)不存在",'code'=>4);
    }
    if((int)$res['status'] !== 0){
        lg('self_lottery_data_debug', "error-5: 密钥(".$key.")被限制使用\n");
        @file_put_contents('./scripts/api.log', "error-5: 密钥(".$key.")被限制使用\n", FILE_APPEND);
        return array('status'=>'fail','msg'=>"密钥($key)被限制使用",'code'=>5);
    }
    $signature2=md5(md5($timestamp).$res['secret_key']);
    if($signature!=$signature2){
        lg('self_lottery_data_debug', "error-6: 签名失败(".$signature." -- ".$signature2.")\n");
        @file_put_contents('./scripts/api.log', "error-6: 签名失败(".$signature." -- ".$signature2.")\n", FILE_APPEND);
        return array('status'=>'fail','msg'=>'签名失败','code'=>1);
    }

    //文件处理-图片
    $files = "";
    if($_REQUEST['images']){
        $files['images'] = $_REQUEST['images'];
    }

    //业务数据
    if($_REQUEST['data']){
        $decrypted = base64_decode(dencrypt($_REQUEST['data'],'DECODE',$signature));
        $data = json_decode($decrypted,true);
        if(!empty($files)){
            $data['images'] = $files['images'];
        }
        $_REQUEST = $data;
        if($method === "POST"){
            $_POST = $data;
        }
        if($method === "GET"){
            $_GET = $data;
        }
    }

    return array('status'=>'success','msg'=>'签名成功',);
}

/**
 * 获取密钥
 * @param $key string 密钥key
 * @param $source int 接口来源:1 ios;  2 安卓; 3 H5; 4 PC;
 * @param $project string 项目来源(0:pc手游, 1:讯彩; 2其它;  [待存字典表])
 */
function getSecret($key,$source,$project){
    $sql = "SELECT secret_key,status FROM `un_aip_keys` WHERE `key` = '{$key}' AND `type` = 1 AND `source` = {$source} AND `project` = {$project}";
    $res = O("model")->db->getone($sql);
    return $res;
}


/**
 * 手动补入统计数据方法（计算28类彩种tj字段值）
 * @param number $_28 本期的开奖号码（28类彩种专用，范围0~27）
 * @param string $tj_str 上期的tj字段值
 * @return string 计算后生成的tj字段值，该值可直接入库
 * 
 * tj值的各个位置表示的意义如下：
 * 0 => '大'
 * 1 => '小'
 * 2 => '单'
 * 3 => '双'
 * 4 => '大单'
 * 5 => '大双'
 * 6 => '小单'
 * 7 => '小双'
 * 8 => '极大'
 * 9 => '极小'
 */
function calculate_tj ($_28, $tj_str = '') {
    //定义大小单双属性
    $dd = [15,17,19,21,23,25,27];    //大单
    $ds = [14,16,18,20,22,24,26];    //大双
    $xd = [1,3,5,7,9,11,13];         //小单
    $xs = [0,2,4,6,8,10,12];         //小双

    //极大极小
    $jd = [22,23,24,25,26,27];       //极大
    $js = [0,1,2,3,4,5];             //极小

    $tmp_tj_arr = explode(',', $tj_str);
    //如果传入非法值，则初始化tj字段值为10个0
    if (count($tmp_tj_arr) != 10) {
        $tmp_tj_arr = [
            0,0,0,0,0,
            0,0,0,0,0,
        ];
    }

    //默认全部加1
    $tj_arr = array_map(function ($n) {
        return $n + 1;
    }, $tmp_tj_arr);
    
    //命中的值则重置为0
    if (in_array($_28, $xd)) {
        $tj_arr[1] = 0;     //小
        $tj_arr[2] = 0;     //单
        $tj_arr[6] = 0;     //小单
    } elseif (in_array($_28, $xs)) {
        $tj_arr[1] = 0;     //小
        $tj_arr[3] = 0;     //双
        $tj_arr[7] = 0;     //小双
    } elseif (in_array($_28, $dd)) {
        $tj_arr[0] = 0;     //大
        $tj_arr[2] = 0;     //单
        $tj_arr[4] = 0;     //大单
    } elseif (in_array($_28, $ds)) {
        $tj_arr[0] = 0;     //大
        $tj_arr[3] = 0;     //双
        $tj_arr[5] = 0;     //大双
    }


    //极大极小值处理，命中则重置为0
    if (in_array($_28, $js)) {
        $tj_arr[1] = 0;     //小
        $tj_arr[9] = 0;     //极小
    } elseif (in_array($_28, $jd)) {
        $tj_arr[0] = 0;     //大
        $tj_arr[8] = 0;     //极大
    }

    //返回可入库的tj字段值
    return implode(',', $tj_arr);
}

/**
 *
 * 把发送数据和接收数据放到公共函数
 * 发送数据给前台,这里的前台一般有多个，并且是跑wokerman的
 * 特别注意:后台的配置文件要把home_arr这个加上去
 * 双活用的，请不要动
 */
 function send_home_data($data=array()){
     $key='DCCdPke3boPWr2Wp2Qb4yWF9MuiYq@9f';
     $time=time();
     $sign=md5($key.$time);
     $data['sign']=$sign;
     $data['timestamp']=$time;
     lg('send_home_data','发送给前台的数据::'.encode($data));
     foreach (C('home_arr') as $v){
         $url  =  $v."/index.php?m=api&c=workerman&a=get_admin_data";
         lg('send_home_data','url'.$url);
         http_post_json($url,json_encode($data,JSON_UNESCAPED_UNICODE),1);
     }
 }
/**
 *
 * 双活用的，请不要动
 */
function http_post_json($url, $jsonStr='[]',$timeout=false)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //这个是重点
    if($timeout){
        curl_setopt($ch, CURLOPT_TIMEOUT,3); //防止超时卡顿
    }
    curl_setopt($ch, CURLOPT_POST, true); //POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('CLIENT-IP:58.68.44.61','X-FORWARDED-FOR:58.68.44.61'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

/**
 * 合并多维数组
 * 非数字键名相同时，data2值会覆盖data1
 * data2的数字键会被添加到data1中，数字键名会发生变化
 * @param $data1 数组1
 * @param $data2 数组2
 * @return mixd
 */
function multiArrayMerge($data1, $data2)
{
    if (!is_array($data1) || !is_array($data2) || empty($data2)) {
        return $data1;
    }

    foreach ($data2 as $k => $v) {
        if (is_numeric($k)) {
            $data1[] = $data2[$k];
        }else {
            if (!is_array($v)) {
                $data1[$k] = $data2[$k];
            }else {
                if (!isset($data1[$k])) {
                    $data1[$k] = $data2[$k];
                }else {
                    $data1[$k] = $this->arrayMerge($data1[$k], $data2[$k]);
                }
            }

        }
    }

    return $data1;
}

/**
 * 支付日志
 * @param  string $fileName 文件名,如：payerror.log或者pay/error.log
 * @param  array $data 数据
 */
function payLog($fileName, $data)
{
    $pathLog = S_CACHE . 'log' . DS . $fileName;
    if (is_file($pathLog)) {
        if (filesize($pathLog) >= 10000000) {
            $new_file = dirname($pathLog) . '/' . date('Y_m_d_H_i_s').'_'  . basename($pathLog);
            copy($pathLog,$new_file);
            file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $data . "\n");
        }else {
            file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $data . "\n", FILE_APPEND);
        }
    }else {
        file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $data . "\n", FILE_APPEND);
    }
}

function clearLogData($fileName) {
    if(!$fileName) return;
    $pathLog = S_CACHE . 'log' . DS . $fileName;
    file_put_contents($pathLog, '');
}

/**
 * 加密
 * 如：new_encrypt('xxx');
 * 输出字符串：WzE1NCwxNzEsMTU1LDEwMl0=
 */
function new_encrypt ($str) {
    $token = 'pcsy_2017';
    $token = md5($token); //密钥 md5
    $str = base64_encode($str); //base64 参数
    $str_len = mb_strlen($str);
    $token_len_minus_one = strlen($token) - 1;
    $new_arr = [];
    $index = 0;
    for ($i = 0; $i < $str_len; $i++) {  //以参数长度为准，逐个取出参数，密钥。
        //如果参数的长度大于密钥长度，参数从第32位开始，一直对应密钥的32位（最后一位）。
        $index = ($i > $token_len_minus_one) ? $token_len_minus_one : $i;

        //把参数与密钥相加，保存进数组。
        $new_arr[] = ord(substr($str, $i, 1)) + ord(substr($token, $index, 1));
    }
    //把数组json化，然后再base64。
    return base64_encode(json_encode($new_arr));
}

/**
 * 解密
 * 如：new_decrypt('WzE1NCwxNzEsMTU1LDEwMl0=', 'pcsy_2017');
 * 输出字符串：xxx
 */
function new_decrypt ($str, $token) {
    $token = md5($token);
    $arr = json_decode(base64_decode($str), true);
    $token_len_minus_one = strlen($token) - 1;
    $str_len = count($arr);
    $index = 0;
    $arr2 = [];
    for ($i=0; $i < $str_len; $i++) { 
        $index = ($i > $token_len_minus_one) ? $token_len_minus_one : $i;
        $arr2[] = chr($arr[$i] - ord(substr($token, $index, 1)));
    }
    return base64_decode(implode('', $arr2));
}


/**
 * 生成签名
 * @param $data arrary 生成签名需要的数据
 * @param $arrKey arrary 用户密锁,键值对(一对)
 * @param $unData array data中排除键名在undata中的键值对签名
 * @param $type int 是否使用键和值，1：即使用键值，也使用相应的键名，0：只使用键值
 * @param $lowUp int MD5是否大写，1：MD5后全部大写，0：不执行大写操作
 * @param $add string 只使用键值时(不使用键名)是否使用'&'分割
 * @return string 生成签名
 */
function getSigned($data = [], $arrKey = [], $unData = [], $add = '&', $type = 1, $lowUp = 0)
{
    $string = '';
    $retStr = '';

    $string = toUrlParams($data, $unData);

    if (!empty($arrKey)) {
        foreach ($arrKey as $k => $v) {
            if (!is_array($v)) {
                if ($type == 0) {
                    $string .= $add . $v;
                }else {
                    $string .= $add . $k . '=' . $v;
                }
            }
        }
    }
    //var_dump($string);
    if ($lowUp == 1) {
        $retStr = strtoupper(md5($string));
    }else {
        $retStr = md5($string);
    }

    return $retStr;
}

/**
 * 格式化参数格式化成url参数
 * @param $data array 生成url需要的一维键值对数据
 * @param $unData array data中排除键名在undata中的键值对签名
 * @return string 生成url
 */
function toUrlParams($data,$unData = [])
{
    $buff = "";

    ksort($data);
    foreach ($data as $k => $v)
    {
        if (in_array($k, $unData) || $v == "" || is_array($v)) continue;

        $buff .= $k . "=" . $v . "&";
    }

    $buff = trim($buff, "&");

    return $buff;
}

/**
 * 撤单,回滚是否有权限
 * @param $aid 后台的用户权限组
 * @param $str 查询的字段
 * 返回 1和0  1表示有权限，0无
 */
function is_supper($rid,$str){
    $redis = initCacheRedis();
    $re = $redis->hGet('Config:cancal_callback_order','value');
    deinitCacheRedis($redis);
    $role = decode($re);
    $role = explode(',',$role[$str]);
    if(in_array($rid,$role)){
        return 1;
    }else{
        return 0;
    }
}

/**
 * 生成随机字符串
 * @param int $len 需要生成随机字符串的长度
 * @param string $chars 给定随机字符串母集，默认为a-Z0-9
 * @return string 生成的随机字符串
 */
function getRandomString($len, $chars = null)
{
    if (is_null($chars)) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    }

    for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < $len; $i++) {
        $str .= $chars[mt_rand(0, $lc)];
    }
    
    return $str;
}

/**
 * 积分兑换
 * @param float $money 金额
 * @param int $user_id 用户ID
 * @param string $type 类型，1：充值兑换，2：投注兑换，3：中奖兑换
 * @return boolean
 */
function exchangeIntegral($money, $user_id, $type = 0)
{
    $flag = 0;
    $amont_data = [];
    $log_data = [];
    $textType = ['', '充值', '投注', '中奖'];
    $strName = ['', 'recharge', 'betting', 'winning'];
    $model = O('model');
    
    if ($type > 3 || $type < 1 || $type != (int)$type) {
        return false;
    } else {
        $type = (int)$type;
    }

    $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
    $config = json_decode($conf['value'], true);

    $sql = 'select u.honor_score, u.group_id as groupid, u.reg_type as regtype, uat.* from un_user as u LEFT JOIN un_user_amount_total as uat ON u.id = uat.user_id where u.id = ' . $user_id;
    $userData = $model->db->getone($sql);
    if (empty($userData)) {
        payLog('exchange.log', 'ID为：' . $user_id . '的用户不存在，' . $textType[$type] . '金额' . $money . '添加到累计兑换积分失败！');

        return false;
    }
    //标志un_user_amount_total表没有用户记录
    if (empty($userData['id'])) {
        $flag = 1;
        $userData[$strName[$type] . '_exchange'] = 0;
    }

    $model->db->query('BEGIN');

    try {

        $exchangeMoney = $money + $userData[$strName[$type] . '_exchange'];
        $exMoney = (($exchangeMoney * 100) % ($config['plus'][$strName[$type]]['money'] * 100)) / 100;
        $logMoney = $exchangeMoney - $exMoney;

        $amont_data[$strName[$type] . '_exchange'] = $exchangeMoney;

        if ($config['plus']['status'] == 1 && $logMoney > 0) {
            $logScore = ($logMoney / $config['plus'][$strName[$type]]['money']) * $config['plus'][$strName[$type]]['score'];

            //修改用户积分
            $sql = 'update un_user set honor_score = honor_score + ' . $logScore . ' where id = ' . $user_id;
            $model->db->exec($sql);

            //添加积分记录
            $log_data = [
                'user_id' => $user_id,
                'money'   => $userData[$strName[$type] . '_exchange'],
                'log_money' => $money,
                'use_money' => $exMoney,
                'exchange_money'   => $logMoney,
                'score'   => $logScore,
                'honor_score' => ($userData['honor_score'] + $logScore),
                'type'      => $type,
                'create_time' => time(),
                'remarks' => '累计' . $textType[$type] . '金额每满：' . $config['plus'][$strName[$type]]['money'] . ',兑换积分：' . $config['plus'][$strName[$type]]['score']
            ];
            $model->db->insert('un_integral_log', $log_data);

            //修改未兑换积分的金额
            $amont_data[$strName[$type] . '_exchange'] = $exMoney;
        }

        if ($flag == 0) {
            $model->db->update('un_user_amount_total', $amont_data, 'user_id = ' . $user_id);
        } else {
            //添加用户统计记录
            $user_data = [
                'user_id' => $user_id,
                'group_id'   => $userData['groupid'],
                'reg_type'   => $userData['regtype'],
                'recharge_exchange' => $amont_data[$strName[$type] . '_exchange'],
                'create_time' => time()
            ];

            $model->db->insert('un_user_amount_total', $user_data);
        }

        $model->db->query('COMMIT');

        return true;
    } catch (Exception $e) {
        $model->db->query('ROLLBACK');

        payLog('exchange.log', $textType[$type] . '金额添加到兑换积分累计' . $textType[$type] . '失败！当前的累计' . $textType[$type] . '积分兑换设置为：每累计' . $textType[$type] . '金额' . $config['plus'][$strName[$type]]['money'] . ',兑换积分：' . $config['plus'][$strName[$type]]['score'] . '，用户ID为：' . $user_id . ',' . $textType[$type] . '金额为：' . $money);

        return false;
    }
}

/**
 * 中奖订单回滚，积分进行回滚
 * @param float $money 金额
 * @param int $user_id 用户ID
 * @param string $type 类型，5：充值兑换回滚，6：投注兑换回滚，7：中奖兑换回滚
 * @param number $period 彩票期数
 * @param number $period 彩票类型
 * @return boolean
 */
function callbackIntegral($money, $user_id, $types = 0, $periods = '', $lottery_type = '')
{
    $exMoney = 0;
    $remarks = '';
    $log_data = [];
    $textType = ['', '充值', '投注', '中奖'];
    $strName = ['', 'recharge', 'betting', 'winning'];
    $model = O('model');
    
    if ($types > 7 || $types < 5 || $types != (int)$types) {
        return false;
    } else {
        $types = (int)$types;
    }
    
    //对应积分兑换的类型
    $type = $types - 4;
    
    $conf = D('config')->getOneCoupon("value", "nid='level_honor'");
    $config = json_decode($conf['value'], true);
    
    $sql = 'select u.honor_score, u.group_id, u.reg_type, uat.* from un_user as u LEFT JOIN un_user_amount_total as uat ON uat.user_id = u.id where u.id = ' . $user_id;
    $userData = $model->db->getone($sql);
    if (empty($userData)) {
        payLog('exchange.log', 'ID为：' . $user_id . '的用户不存在，' . $textType[$type] . '金额' . $money . '添加到累计兑换积分失败！');

        return false;
    }
    //标志un_user_amount_total表没有用户记录
    if (empty($userData['id'])) {
        return false;
    }
    
    $model->db->query('BEGIN');
    
    try {
    
        if ($userData[$strName[$type] . '_exchange'] >= $money) {
            //$useMoney = $userData[$strName[$type] . '_exchange'];
            $exMoney = $userData[$strName[$type] . '_exchange'] - $money;  
        } else {
            $exchangeMoney = $money - $userData[$strName[$type] . '_exchange'];
            $eMoney = (($exchangeMoney * 100) % ($config['plus'][$strName[$type]]['money'] * 100)) / 100;
            if ($eMoney == 0) {
                $needMoney = $exchangeMoney - $eMoney;
                $useMoney  = $userData[$strName[$type] . '_exchange'] + $needMoney;
                $exMoney = $userData[$strName[$type] . '_exchange'] + $needMoney - $money;
            } else {
                $needMoney = $exchangeMoney - $eMoney + $config['plus'][$strName[$type]]['money'];
                $useMoney  = $userData[$strName[$type] . '_exchange'] + $needMoney;
                $exMoney   = $useMoney - $money;
            }
            
            $logScore = ($needMoney / $config['plus'][$strName[$type]]['money']) * $config['plus'][$strName[$type]]['score'];
            $userScore = ($userData['honor_score'] - $logScore) >= 0 ? ($userData['honor_score'] - $logScore) : 0;
            //修改用户积分
            $sql = 'update un_user set honor_score = ' . $userScore . ' where id = ' . $user_id;
            $model->db->exec($sql);
            
            if ($lottery_type != '') {
                $lotteryType = $model->db->getone("select id,name from un_lottery_type where id = " . $lottery_type);
                if (!empty($lotteryType['name'])) {
                    $remarks = '当前彩种：' . $lotteryType['name'] . '，回滚期数：' . $periods . '，';
                }
            } 
            
            //减少积分记录
            $log_data = [
                'user_id' => $user_id,
                'money'   => $useMoney,
                'log_money' => -$money,
                'use_money' => $exMoney,
                'exchange_money' => -$needMoney,
                'score'   => -$logScore,
                'honor_score' => $userScore,
                'type'      => $types,
                'create_time' => time(),
                'remarks' => $remarks . '累计' . $textType[$type] . '积分回滚时使用的兑换设置为：累计' . $textType[$type] . '金额每满：' . $config['plus'][$strName[$type]]['money'] . '，兑换积分：' . $config['plus'][$strName[$type]]['score']
            ];
            $model->db->insert('un_integral_log', $log_data);
            
        }

        $model->db->update('un_user_amount_total', [$strName[$type] . '_exchange' => $exMoney], 'user_id = ' . $user_id);
    
        $model->db->query('COMMIT');
    
        return true;
    } catch (Exception $e) {
        $model->db->query('ROLLBACK');
    
        if ($lottery_type != '') {
            $remarks = '当前彩种：' . $lottery_type . '，回滚期数：' . $periods . '，';
        }
        payLog('exchange.log', $remarks . $textType[$type] . '金额回滚兑换积分累计' . $textType[$type] . '失败！用户ID为：' . $user_id . '，' . $textType[$type] . '金额为：' . $money);
    
        return false;
    }
}

/**
 * 获取用户荣誉信息
 * @param $userId   用户ID
 * @return array
 */
function get_honor_info($userId)
{
    $user_honor = D('user')->db->getone("select honor_score, honor_upgrade from un_user where id = " . $userId);
    if (empty($user_honor['honor_score'])) {
        $user_honor['honor_score'] = 0;
    }
    $score = $user_honor['honor_score'] < 0 ? 0 : $user_honor['honor_score'];
    
    $honor = D('honor')->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and score <= $score order by score desc");

    //判断是否达到最高级 0否，1是
    $honor['next_status'] = 0;
    //判断下一级等级
    $nextLevel = D('honor')->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and sort > " . $honor['sort'] . ' order by score asc');
    if (empty($nextLevel)) {
        //判断下一级等级
        $nextLevel = D('honor')->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and score < " . $honor['score'] . ' order by score desc');
        if (!empty($nextLevel)) {
            $honor['next_status'] = 1;
        } else {
            $nextLevel['name'] = '已达最高级';
            $nextLevel['score'] = $score;
            $nextLevel['icon'] = $honor['icon'];
            $nextLevel['sort'] = $honor['sort'];
            $nextLevel['grade'] = $honor['grade'];
        }
    }

    //判断是否已升级
    if ($honor['sort'] > $user_honor['honor_upgrade']) {
        $honor['upgrade'] = 1;
        //D('user')->db->update('un_user', ['honor_upgrade' => $honor['sort']], 'id = ' . $userId);
    } else {
        $honor['upgrade'] = 0;
    }

    $honor['next_name']  = $nextLevel['name'];
    $honor['next_icon']  = $nextLevel['icon'];
    $honor['next_score'] = $nextLevel['score'];
    //$honor['next_sort']  = $nextLevel['sort'];
    $honor['next_sort']  = $nextLevel['grade'];

    $conf = D('config')->db->result("select value from un_config where nid='level_honor'");
    $config = json_decode($conf,true);

    $honor['honor_status'] = $config['status'];
    $honor['sort']  = $honor['grade'];
    $honor['user_score'] = $score;

    return $honor;

}

/**
 * 获取用户荣誉信息
 * @param $userId   用户ID
 * @return array
 */
function get_level_honor($userId)
{
    $score = D('user')->db->result("select honor_score from un_user where id=" . $userId);
    if (empty($score)) {
        $score = 0;
    }
    $score = $score < 0 ? 0 : $score;

    $honor = D('honor')->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and score <= $score order by score desc");

    $conf = D('config')->db->result("select value from un_config where nid='level_honor'");
    $config = json_decode($conf,true);

    $honor['honor_status'] = $config['status'];
    $honor['user_score'] = $score;
    //注意等级和排序号的关系
    $honor['sort']  = $honor['grade'];

    return $honor;

}
/**
 * 获取用户荣誉信息
 * author: Aho
 *
 * @param $userId   用户ID
 * @param int $type 返回类型 1：json 0：array
 *
 * @return bool|string
 */

function get_honor_level($userId) {
    $score = D('user')->db->result("select honor_score-lose_score from un_user where id=$userId");
    $score = $score < 0 ? 0 : $score;
    $honor = D('honor')->db->getone("select name,icon,status,score,num from un_honor where score<=$score order by score desc");
    $status = D('config')->db->result("select value from un_config where nid='is_show_honor'");
    $honor['status1'] = $status;
    return $honor;

}

/**
 * 获取机器人荣誉信息
 * @param $userId   用户ID
 * @return array
 */
function get_level_honor_robot($userId)
{
    $nowDayTime = strtotime(date('Y-m-d'));
    $robotData = D('user')->db->getone("select logintime, honor_score, reg_type from un_user where id = " . $userId);

    if (!empty($robotData) && $robotData['reg_type'] == 9) {
        if ($robotData['logintime'] == $nowDayTime) {
            $robotHonor = D('honor')->db->getone("select name, icon, sort, score, grade from un_honor where status = 1 and score <= " .$robotData['honor_score'] . " order by score desc");
            
            $honor['sort'] = $robotHonor['grade'];
            $honor['user_score'] = $robotHonor['honor_score'];
        }else {
            $honorGrade = D('honor')->db->getall("select grade from un_honor where status = 1");
            $count = count($honorGrade) - 1;
            $honor['sort']  = $honorGrade[rand(0,$count)]['grade'];

            $robotHonor = D('honor')->db->getone("select score from un_honor where status = 1 AND grade = " . $honor['sort']);
            
            $sql = 'update un_user set honor_score = ' . $robotHonor['score'] . ', logintime = ' . $nowDayTime . ' where id = ' . $userId;
            D('honor')->db->exec($sql);
            
            $honor['user_score'] = $robotHonor['score'];
        }
    } else {
        $honor['sort'] = 1;
        $honor['user_score'] = 0;
    }
    
    $conf = D('config')->db->result("select value from un_config where nid='level_honor'");
    $config = json_decode($conf,true);

    $honor['honor_status'] = $config['status'];

    return $honor;
}

/**
 * 防止高并发处理（主要用于后台操作,防止同时处理）
 * @param string $flag //该操作的唯一标识符，建议控制器 +操作+操作数据ID
 * @param int $time   超时时间（秒）
 * @return boolean true|false true,防止高并发，false，无高并发
 */
function preventSupervene($flag, $time = 3)
{
    $redis = initCacheRedis();

    //如果存在就组装key写不进去
    if($redis->setnx($flag, 1)) { 
        //设置它的超时
        $redis->expire($flag, $time); 
        deinitCacheRedis($redis);

        return false;
    }else{
        deinitCacheRedis($redis);

        return true;
    }
}

/**
 * 操作锁定处理（函数操作，数据操作）
 * @param string $flag 该操作的唯一标识符，建议控制器 +操作+操作数据ID
 * @param int $expireTime  过期时间（秒）
 * @param int $type    加解锁，1:加锁,0:解锁，2:判断Key是否存在或过期
 * @return boolean true|false true,操作修改成功，false，操作修改失败
 */
function superveneLock($flag, $expireTime, $type = 1)
{
    $redis = initCacheRedis();

    if ($type == 1) {  //加锁
        //如果存在，组装key写不进去
        if($redis->setnx($flag, 1)) {
            $redis->expire($flag, $expireTime); //设置它的超时 
            deinitCacheRedis($redis);
        
            return true;
        }else{
            deinitCacheRedis($redis);

            return false;
        }
    } elseif ($type == 2) {  //判断Key是否存在,是否过期
        if ($redis->ttl($flag) > 0) {
            deinitCacheRedis($redis);
            return false;
        }else {
            deinitCacheRedis($redis);
            return true;
        }
    } elseif ($type == 0) {  //解锁
        $redis->expire($flag, $expireTime); //设置它的超时 
        deinitCacheRedis($redis);
        
        return true;
    } else {
        deinitCacheRedis($redis);

        return false;
    }
}
/**
 * 检验相关操作（函数操作，数据操作）是否锁定
 * @param string $flag 该操作的唯一标识符，建议控制器 +操作+操作数据ID
 * @return boolean true|false true,操作被锁定，false，操作未被锁定
 */
function verifySuperveneLock($flag)
{
    $redis = initCacheRedis();

    if($redis->exists($flag)) {
        deinitCacheRedis($redis);

        return true;
    }else{
        deinitCacheRedis($redis);

        return false;
    }
}

/**
 * 获取ws的端口号
 * @return mixed
 *
 */
function getWsPort(){
    $domain = $_SERVER['HTTP_HOST'];
    $redis = initCacheRedis();
    $val = $redis->hget('Config:wsPorts','value');
    deinitCacheRedis($redis);
    $ports = decode($val);
    $port = $ports[$domain];
    return $port;
}

/**
 * 获取六合彩生肖对应的号码数组
 * @param string $sheng_xiao 生肖
 * @param int $time 时间戳，不传则默认当前时间
 * 2018-01-16
 */
function getLhcNumber($this_time = null, $sheng_xiao = null)
{
    // $date = '2017-06-14';
    // $this_time = strtotime($date);

    if (! $this_time) {
        $this_time = time();
    }

    //缓存每年的除夕日期时间
    $year_last_day = [
        2015 => ['2015-02-18','2016-02-07'],
        2016 => ['2016-02-07','2017-01-27'],
        2017 => ['2017-01-27','2018-02-15'],
        2018 => ['2018-02-15','2019-02-04'],
        2019 => ['2019-02-04','2020-01-24'],
        2020 => ['2020-01-24','2021-02-11'],
        2021 => ['2021-02-11','2022-01-31'],
        2022 => ['2022-01-31','2023-01-21'],
        2023 => ['2023-01-21','2024-02-09'],
        2024 => ['2024-02-09','2025-01-28'],
        2025 => ['2025-01-28','2026-02-16'],
    ];

    $year_key = 0;

    foreach ($year_last_day as $k => $year_during) {
        $tmp_timestamp_1 = strtotime($year_during[0] . ' 00:00:00');
        $tmp_timestamp_2 = strtotime($year_during[1] . ' 23:59:59');
        if ($tmp_timestamp_1 <= $this_time && $this_time <= $tmp_timestamp_2) {
            $year_key = $k;
            break;
        }
    }

    //基准值
    $year_map = [2017, '鸡'];

    //相差年份数
    $diff_year_num = $year_key - $year_map[0];

    $sheng_xiao_arr = [
        '猪', '狗', '鸡', '猴',
        '羊', '马', '蛇', '龙',
        '兔', '虎', '牛', '鼠', 
    ];

    //检索出默认生肖所在位置后，需要减去上文计算出的相差年份数（即变量$diff_year_num）
    $sheng_xiao_key = array_search($year_map[1], $sheng_xiao_arr) - $diff_year_num;

    $sheng_xiao_move = array_splice($sheng_xiao_arr, $sheng_xiao_key);

    //生成新的数组键
    $result_year_key = array_merge($sheng_xiao_move, $sheng_xiao_arr);

    $sheng_xiao_map = [
        [1, 13, 25, 37, 49,],
        [2, 14, 26, 38,],
        [3, 15, 27, 39,],
        [4, 16, 28, 40,],
        [5, 17, 29, 41,],
        [6, 18, 30, 42,],
        [7, 19, 31, 43,],
        [8, 20, 32, 44,],
        [9, 21, 33, 45,],
        [10, 22, 34, 46,],
        [11, 23, 35, 47,],
        [12, 24, 36, 48,],
    ];

    $year_map = array_combine($result_year_key, $sheng_xiao_map);

    //如果有传生肖，并且生肖存在，则返回该生肖对应的号码数组
    if ($sheng_xiao != false && $year_map[$sheng_xiao]) {
        return $year_map[$sheng_xiao];
    }
    return $year_map;
}

/**
 * 根据号码返回对应的生肖
 * @param int $number 号码，小于10的号码建议传不带前置0
 * @param int $time 时间戳，不传则默认当前时间
 * 2018-01-16
 */
function getLhcShengxiao($number, $time = 0)
{
    //原始数据需从 getLhcNumber 方法中获取
    $year_map = getLhcNumber($time);

    $number = intval($number);

    foreach ($year_map as $sheng_xiao => $sheng_xiao_value) {
        if (in_array($number, $sheng_xiao_value)) {
            return $sheng_xiao;
        }
    }
}

/**
 * 二维数组排序
 * @param array $multi_array 要排序的二维数组
 * @param array $sort_key 排序字段
 * @param array $sort 升序（SORT_ASC）或降序（SORT_DESC）
 *
 * 2018-01-16
 */
function multi_array_sort($multi_array, $sort_key, $sort = SORT_ASC){
    if(is_array($multi_array)){
        foreach ($multi_array as $row_array){
            if(is_array($row_array)){
                $key_array[] = $row_array[$sort_key];
            }else{
                return false;
            }
        }
    }else{
        return false;
    }
    array_multisort($key_array,$sort,$multi_array);
    return $multi_array;
}

/**
 * 返回连尾对应的数字
 * @param string $string 尾数（0尾，1尾，2尾，3尾，4尾，5尾，6尾，7尾，8尾，9尾）
 * 2018-01-16
 */
function getLianWei($string = null) {
    $num_arr = [
        [10,20,30,40],
        [1,11,21,31,41],
        [2,12,22,32,42],
        [3,13,23,33,43],
        [4,14,24,34,44],
        [5,15,25,35,45],
        [6,16,26,36,46],
        [7,17,27,37,47],
        [8,18,28,38,48],
        [9,19,29,39,49]
    ];
    $str_arr = [
       '0尾','1尾','2尾','3尾','4尾','5尾','6尾','7尾','8尾','9尾',
    ];

    $arr = array_combine($str_arr, $num_arr);

    //如果有传连尾，并且连尾存在，则返回该连尾对应的号码数组
    if (!empty($string) && $arr[$string]) {
        return $arr[$string];
    }
    return $arr;
}

/**
 * 判断私密房限制用户有没有权限进入该房间
 * @param int $uid 用户ID
 * @param int $lottery_id 采种ID
 * return bool|array true|false array 用户id数组
 */
function checkAuthorRoom($uid, $lottery_id) 
{
    $lottery_ids = [];
    $user_id = '';
    $user_ids = [];
    $model = D('user');
    
    $sql = "SELECT * FROM #@_user_tree WHERE user_id = " . $uid;
    $treeData = $model->db->getone($sql);
    if (empty($treeData)) {
        return false;
    }
    
    if (!empty($treeData['lottery_ids'])) {
        $lottery_ids = explode(',', $treeData['lottery_ids']);
        if (in_array($lottery_id, $lottery_ids)) {
           $user_id = $treeData['user_id'];
        }
    }
    
    if (!empty($treeData['pids']) && $treeData['pids'] != ',') {
        $treeData['pids'] = trim($treeData['pids'], ',');
        $sql = "SELECT `user_id`, `lottery_ids` FROM `un_user_tree` WHERE `user_id` IN (" . $treeData['pids'] . ')';
        $arrTree = $model->db->getall($sql);
        $strLotteryIds = '';
        if (empty($arrTree)) {
            if (empty($user_id)) {
                return false;
            } else {
                $user_ids[] = $user_id;
                return $user_ids;
            }
        }
        
        foreach ($arrTree as $ak => $av) {
            if (!empty($av['lottery_ids'])) $strLotteryIds .= $av['lottery_ids'];
        }
        
        if (empty($strLotteryIds)) {
            if (empty($user_id)) {
                return false;
            } else {
                $user_ids[] = $user_id;
                return $user_ids;
            }
        }
        
        $lottery_ids = explode(',', $strLotteryIds);
        
        if (in_array($lottery_id, $lottery_ids)) {
            $user_ids = explode(',', $treeData['pids']);
            if (!empty($user_id)) {
                $user_ids[] = $user_id;
            }
            return $user_ids;
        }
    } else {
        if (empty($user_id)) {
            return false;
        } else {
            $user_ids[] = $user_id;
            return $user_ids;
        }
    }
}


/**
 * 可显示日志
 * @param $file 文件名
 * @param $str 数据
 */
function show_log($str) {
    $pathLog = S_ROOT . 'log_info.txt';
    file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $str . "\n", FILE_APPEND);
}

/**
 * 根据5个开奖号码，计算各张牌的牌面、花色，结果是几牛
 * @param array $lottery_arr 开奖数组，含5个元素
 * @return array 结果数组，含牌面、花色以及结果牛数
 * 2018-02-27
 * 
 * 范例代码
 * 调用：checkNiuNiu(array(27,45,12,18,28));
 * 返回：
 * Array
 * (
 *     [hua] => Array
 *         (
 *             [0] => 3
 *             [1] => 1
 *             [2] => 4
 *             [3] => 2
 *             [4] => 4
 *         )
 * 
 *     [hua_str] => Array
 *         (
 *             [0] => 红心
 *             [1] => 方块
 *             [2] => 黑桃
 *             [3] => 梅花
 *             [4] => 黑桃
 *         )
 * 
 *     [pai_dx] => Array
 *         (
 *             [0] => 大
 *             [1] => 大
 *             [2] => 小
 *             [3] => 小
 *             [4] => 大
 *         )
 * 
 *     [pai_ds] => Array
 *         (
 *             [0] => 单
 *             [1] => 双
 *             [2] => 单
 *             [3] => 单
 *             [4] => 单
 *         )
 * 
 *     [pai_dxds] => Array
 *         (
 *             [0] => 大单
 *             [1] => 大双
 *             [2] => 小单
 *             [3] => 小单
 *             [4] => 大单
 *         )
 * 
 *     [pai] => Array
 *         (
 *             [0] => 7
 *             [1] => 12
 *             [2] => 3
 *             [3] => 5
 *             [4] => 7
 *         )
 * 
 *     [pai_str] => Array
 *         (
 *             [0] => 7
 *             [1] => Q
 *             [2] => 3
 *             [3] => 5
 *             [4] => 7
 *         )
 * 
 *     [lottery_pai_arr] => Array
 *         (
 *             [0] => 红心7
 *             [1] => 方块Q
 *             [2] => 黑桃3
 *             [3] => 梅花5
 *             [4] => 黑桃7
 *         )
 * 
 *     [lottery_max_num] => 45
 *     [lottery_pai] => 红心7,方块Q,黑桃3,梅花5,黑桃7
 *     [lottery_lh] => 虎
 *     [lottery_gp] => 1
 *     [lottery_sum] => 34
 *     [lottery_dx] => 小
 *     [lottery_ds] => 双
 *     [lottery_dxds] => 小双
 *     [lottery_niu] => 牛二
 *     [lottery_niu_num] => 2
 * )
 */
function checkNiuNiu($lottery_arr)
{
    //存放日志数据数组
    $lg_data = [];

    //花色对应
    $hua_map = [
        1 => '方块',
        2 => '梅花',
        3 => '红心',
        4 => '黑桃',
    ];

    //最终返回的结果数组
    $result_arr = [];

    //公牌数统计，下文用于两个地方的判断：1.是否存在公牌 2.是否为花色牛
    $gp_count = 0;

    foreach ($lottery_arr as $lottery_k => $each_lottery_num) {
        //花色：1.方块 2.梅花 3.红心 4.黑桃
        $tmp_hua = ($each_lottery_num - 1) % 4 + 1;

        $result_arr['hua'][] = $tmp_hua;
        $result_arr['hua_str'][] = $hua_map[$tmp_hua];

        //牌面
        $tmp_pai = ceil($each_lottery_num / 4);


        //定义A和公牌：J/Q/K
        if ($tmp_pai == 1) {
            $pai_str = 'A';
        } elseif ($tmp_pai == 11) {
            $gp_count++;
            $pai_str = 'J';
        } elseif ($tmp_pai == 12) {
            $gp_count++;
            $pai_str = 'Q';
        } elseif ($tmp_pai == 13) {
            $gp_count++;
            $pai_str = 'K';
        } else {
            $pai_str = $tmp_pai . '';
        }

        //单张牌大小
        if ($tmp_pai > 6) {
            $result_arr['pai_dx'][] = '大';
        } else {
            $result_arr['pai_dx'][] = '小';
        }

        //单张牌单双
        if ($tmp_pai % 2 === 0) {
            $result_arr['pai_ds'][] = '双';
        } else {
            $result_arr['pai_ds'][] = '单';
        }

        //单张牌组合
        $result_arr['pai_dxds'][] = $result_arr['pai_dx'][$lottery_k] . $result_arr['pai_ds'][$lottery_k];

        $result_arr['pai'][] = $tmp_pai;
        $result_arr['pai_str'][] = $pai_str;

        $result_arr['lottery_pai_arr'][] = $hua_map[$tmp_hua] . $pai_str;
    }

    //最大一张牌的对应值，该值用于：在当两组牌面的牛数一样时，比较该值，来得到最终胜负
    $result_arr['lottery_max_num'] = max($lottery_arr);

    //字符串形式的牌面数组
    $result_arr['lottery_pai'] = implode(',', $result_arr['lottery_pai_arr']);

    //龙虎判断
    if ($result_arr['pai'][0] > $result_arr['pai'][4]) {
        $result_arr['lottery_lh'] = '龙';
    }
    elseif ($result_arr['pai'][0] < $result_arr['pai'][4]) {
        $result_arr['lottery_lh'] = '虎';
    }
    //第1张和第5张相同，再判断花色
    else {
        if ($result_arr['hua'][0] > $result_arr['hua'][4]) {
            $result_arr['lottery_lh'] = '龙';
        }
        else {
            $result_arr['lottery_lh'] = '虎';
        }
    }

    $pai_arr = $result_arr['pai'];

    //有无公牌判断
    $result_arr['lottery_gp'] = ($gp_count > 0) ? 1 : 0;

    //总和
    $sum_value = array_sum($pai_arr);
    $result_arr['lottery_sum'] = $sum_value;

    //大小判断：大于等于35为大，小于等于34为小
    if ($sum_value >= 35) {
        $result_arr['lottery_dx'] = '大';
    } else {
        $result_arr['lottery_dx'] = '小';
    }

    //单双判断
    if ($sum_value % 2 === 0) {
        $result_arr['lottery_ds'] = '双';
    } else {
        $result_arr['lottery_ds'] = '单';
    }

    //组合
    $result_arr['lottery_dxds'] = $result_arr['lottery_dx'] . $result_arr['lottery_ds'];

    $lg_data['参数数组'] = $lottery_arr;
    $lg_data['公牌数'] = $gp_count;


    //花色牛判断：上文中 $gp_count 统计共有几个公牌，如果存在5个公牌，则为花色牛
    if ($gp_count === 5) {
        $result_arr['lottery_niu'] = '花色牛';
        $result_arr['lottery_niu_num'] = 11;

        $lg_data['结果数组'] = $result_arr;
//        lg('nn_lottery', var_export($lg_data, true));
        return $result_arr;
    }

    //将公牌转成10
    foreach ($pai_arr as $k => $v) {
        if ($v == 11 || $v == 12 || $v == 13) {
            $pai_arr[$k] = 10;
        }
    }

    //枚举数组，5张牌任选3张，共10种组合方式
    $enum_arr = [
        [
            'niu_sum' => [$pai_arr[0], $pai_arr[1], $pai_arr[2],],
            'how_many' => [$pai_arr[3], $pai_arr[4],],
        ],
        [
            'niu_sum' => [$pai_arr[0], $pai_arr[1], $pai_arr[3],],
            'how_many' => [$pai_arr[2], $pai_arr[4],],
        ],
        [
            'niu_sum' => [$pai_arr[0], $pai_arr[1], $pai_arr[4],],
            'how_many' => [$pai_arr[2], $pai_arr[3],],
        ],
        [
            'niu_sum' => [$pai_arr[0], $pai_arr[2], $pai_arr[3],],
            'how_many' => [$pai_arr[1], $pai_arr[4],],
        ],
        [
            'niu_sum' => [$pai_arr[0], $pai_arr[2], $pai_arr[4],],
            'how_many' => [$pai_arr[1], $pai_arr[3],],
        ],
        [
            'niu_sum' => [$pai_arr[0], $pai_arr[3], $pai_arr[4],],
            'how_many' => [$pai_arr[1], $pai_arr[2],],
        ],
        [
            'niu_sum' => [$pai_arr[1], $pai_arr[2], $pai_arr[3],],
            'how_many' => [$pai_arr[0], $pai_arr[4],],
        ],
        [
            'niu_sum' => [$pai_arr[1], $pai_arr[2], $pai_arr[4],],
            'how_many' => [$pai_arr[0], $pai_arr[3],],
        ],
        [
            'niu_sum' => [$pai_arr[1], $pai_arr[3], $pai_arr[4],],
            'how_many' => [$pai_arr[0], $pai_arr[2],],
        ],
        [
            'niu_sum' => [$pai_arr[2], $pai_arr[3], $pai_arr[4],],
            'how_many' => [$pai_arr[0], $pai_arr[1],],
        ],
    ];

    //取余数后转下标，映射中文字符
    $niu_map = ['牛', '一', '二', '三', '四', '五', '六', '七', '八', '九', ];

    foreach ($enum_arr as $each_enum) {
        if (array_sum($each_enum['niu_sum']) % 10 === 0) {
            $niu_mod = array_sum($each_enum['how_many']) % 10;

            $result_arr['lottery_niu'] = '牛' . $niu_map[$niu_mod];

            //如果剩余两张的总和为10的倍数，则为牛牛
            $result_arr['lottery_niu_num'] = ($niu_mod === 0) ? 10 : $niu_mod;

            $lg_data['结果数组'] = $result_arr;
//            lg('nn_lottery', var_export($lg_data, true));
            return $result_arr;
        }
    }

    $result_arr['lottery_niu'] = '无牛';
    $result_arr['lottery_niu_num'] = 0;

    $lg_data['结果数组'] = $result_arr;
//    lg('nn_lottery', var_export($lg_data, true));
    return $result_arr;
}

/**
 * 根据号码返回扑克花色及牌面
 * @param number $key 号码数
 * @return string 号码对应的牌面，含花色
 * 2018-02-27
 */
function num2poker($key)
{
    $key_map = [
        1  => '方块A',  2  => '梅花A',  3  => '红心A',  4  => '黑桃A',
        5  => '方块2',  6  => '梅花2',  7  => '红心2',  8  => '黑桃2',
        9  => '方块3',  10 => '梅花3',  11 => '红心3',  12 => '黑桃3',
        13 => '方块4',  14 => '梅花4',  15 => '红心4',  16 => '黑桃4',
        17 => '方块5',  18 => '梅花5',  19 => '红心5',  20 => '黑桃5',
        21 => '方块6',  22 => '梅花6',  23 => '红心6',  24 => '黑桃6',
        25 => '方块7',  26 => '梅花7',  27 => '红心7',  28 => '黑桃7',
        29 => '方块8',  30 => '梅花8',  31 => '红心8',  32 => '黑桃8',
        33 => '方块9',  34 => '梅花9',  35 => '红心9',  36 => '黑桃9',
        37 => '方块10', 38 => '梅花10', 39 => '红心10', 40 => '黑桃10',
        41 => '方块J',  42 => '梅花J',  43 => '红心J',  44 => '黑桃J',
        45 => '方块Q',  46 => '梅花Q',  47 => '红心Q',  48 => '黑桃Q',
        49 => '方块K',  50 => '梅花K',  51 => '红心K',  52 => '黑桃K',
    ];
    return $key_map[$key];
}

/**
 * 根据扑克牌面返回对应的号码
 * @param string $key 牌面，含花色
 * @return number 牌面对应的号码
 * 2018-02-28
 */
function poker2num($key)
{
    $key_map = [
        '方块A' => 1,   '梅花A' => 2,   '红心A' => 3,   '黑桃A' => 4,
        '方块2' => 5,   '梅花2' => 6,   '红心2' => 7,   '黑桃2' => 8,
        '方块3' => 9,   '梅花3' => 10,  '红心3' => 11,  '黑桃3' => 12,
        '方块4' => 13,  '梅花4' => 14,  '红心4' => 15,  '黑桃4' => 16,
        '方块5' => 17,  '梅花5' => 18,  '红心5' => 19,  '黑桃5' => 20,
        '方块6' => 21,  '梅花6' => 22,  '红心6' => 23,  '黑桃6' => 24,
        '方块7' => 25,  '梅花7' => 26,  '红心7' => 27,  '黑桃7' => 28,
        '方块8' => 29,  '梅花8' => 30,  '红心8' => 31,  '黑桃8' => 32,
        '方块9' => 33,  '梅花9' => 34,  '红心9' => 35,  '黑桃9' => 36,
        '方块10' => 37, '梅花10' => 38, '红心10' => 39, '黑桃10' => 40,
        '方块J' => 41,  '梅花J' => 42,  '红心J' => 43,  '黑桃J' => 44,
        '方块Q' => 45,  '梅花Q' => 46,  '红心Q' => 47,  '黑桃Q' => 48,
        '方块K' => 49,  '梅花K' => 50,  '红心K' => 51,  '黑桃K' => 52,
    ];
    return $key_map[$key];

}

//获取胜方和结果
function getShengNiuNiu($str,$result=0){
    $arr = explode(',',$str);
    $blue = array_slice($arr,0,5);
    $red = array_slice($arr,5,5);

    $redStr = '红方:';
    $blueStr = '蓝方:';
    $tmpArr = array();
    foreach ($red as $v){
        $tmpArr[] = num2poker($v);
    }
    $redStr.=implode(',',$tmpArr);

    $tmpArr = array();
    foreach ($blue as $v){
        $tmpArr[] = num2poker($v);
    }
    $blueStr.=implode(',',$tmpArr);

    $blue = checkNiuNiu($blue);
    $red = checkNiuNiu($red);
    if($red['lottery_niu_num'] == $blue['lottery_niu_num']){
        if($red['lottery_max_num'] > $blue['lottery_max_num']){
            $sheng = '红方胜';
        }else{
            $sheng = '蓝方胜';
        }
    }elseif ($red['lottery_niu_num'] > $blue['lottery_niu_num']){
        $sheng = '红方胜';
    }else{
        $sheng = '蓝方胜';
    }

    if($result==0){
        return array($sheng,$sheng=='红方胜'?$red['lottery_niu']:$blue['lottery_niu'],$blueStr.' | '.$redStr,array('red_niu'=>$red['lottery_niu'],'blue_niu'=>$blue['lottery_niu']));
    }elseif($result==1){
        return array('blue'=>$blue,'red'=>$red,'sheng'=>$sheng);
    }elseif($result==2){
        if($sheng=='红方胜'){
            return array(
                'data'=>$red,
                'sheng'=>'红方胜'
            );
        }else{
            return array(
                'data'=>$blue,
                'sheng'=>'蓝方胜'
            );
        }
    }elseif($result==3){
        if($sheng=='红方胜'){
            return str_replace(':','胜:',$redStr);
        }else{
            return str_replace(':','胜:',$blueStr);
        }
    }
    return false;
}

/**
 * 获取采种信息
 * @param int $lottery_type 彩种ID
 * @param unknown $lottery_type
 */
function get_lottery_info($lottery_type)
{
    $lottery_info = [];

    if (empty($lottery_type)) {
        $lottery_type = 1;
    }

    switch ($lottery_type) {
        case 1: 
            $lottery_info['name'] = '幸运28';
            $lottery_info['table'] = 'un_open_award';
            $lottery_info['issue'] = 'issue';
            $lottery_info['lottery_result'] = 'open_no';
            $lottery_info['status'] = 'state';
            break;
        case 2: //北京pk10
            $lottery_info['name'] = '北京pk10';
            $lottery_info['table'] = 'un_bjpk10';
            $lottery_info['issue'] = 'qihao';
            $lottery_info['lottery_result'] = 'kaijianghaoma';
            $lottery_info['status'] = 'status';
            break;
        case 3: //加拿大28
            $lottery_info['name'] = '加拿大28';
            $lottery_info['table'] = 'un_open_award';
            $lottery_info['issue'] = 'issue';
            $lottery_info['lottery_result'] = 'open_no';
            $lottery_info['status'] = 'state';
            break;
        case 4: //幸运飞艇
            $lottery_info['name'] = '幸运飞艇';
            $lottery_info['table'] = 'un_xyft';
            $lottery_info['issue'] = 'qihao';
            $lottery_info['lottery_result'] = 'kaijianghaoma';
            $lottery_info['status'] = 'status';
            break;
        case 5: //重庆时时彩
            $lottery_info['name'] = '重庆时时彩';
            $lottery_info['table'] = 'un_ssc';
            $lottery_info['issue'] = 'issue';
            $lottery_info['lottery_result'] = 'lottery_result';
            $lottery_info['status'] = 'status';
            break;
        case 6: //三分彩
            $lottery_info['name'] = '三分彩';
            $lottery_info['table'] = 'un_ssc';
            $lottery_info['issue'] = 'issue';
            $lottery_info['lottery_result'] = 'lottery_result';
            $lottery_info['status'] = 'status';
            break;
        case 7: //六合彩
            $lottery_info['name'] = '六合彩';
            $lottery_info['table'] = 'un_lhc';
            $lottery_info['issue'] = 'issue';
            $lottery_info['lottery_result'] = 'lottery_result';
            $lottery_info['status'] = 'status';
            break;
        case 8: //急速六合彩
            $lottery_info['name'] = '急速六合彩';
            $lottery_info['table'] = 'un_lhc';
            $lottery_info['issue'] = 'issue';
            $lottery_info['lottery_result'] = 'lottery_result';
            $lottery_info['status'] = 'status';
            break;
        case 9: //急速赛车
            $lottery_info['name'] = '急速赛车';
            $lottery_info['table'] = 'un_bjpk10';
            $lottery_info['issue'] = 'qihao';
            $lottery_info['lottery_result'] = 'kaijianghaoma';
            $lottery_info['status'] = 'status';
            break;
        case 10: //百人牛牛
            $lottery_info['name'] = '百人牛牛';
            $lottery_info['table'] = 'un_nn';
            $lottery_info['issue'] = 'issue';
            $lottery_info['lottery_result'] = 'lottery_result';
            $lottery_info['status'] = 'status';
            break;
        default:
            $lottery_info['name'] = '';
            $lottery_info['table'] = '';
            break;
    }
    
    return $lottery_info;
}

//问路-大路实现
function make_big_load ($arr2, $null_value = '') {
    $bigLd = array();
    $index = 0;
    $index2 =0;//连续开出6期后，记录当前数组下标

    $lc = 0; //记录单双连出次数
    $lc2 = 0 ; //标志是否刚换行（0不是，1是）
    $lastIndex = 6; //换行前最后的下表,初始化为5

    $index2 = 0;


    for($i=0;$i<count($arr2);$i++){
        if($i==0){
            $bigLd[$index][] = $arr2[$i];
        }
        else {

            //如果相邻两个数相等
            if($arr2[$i][0]==$arr2[$i-1][0]){
                $lc++;
                //需要换行
                if($lc>=$lastIndex){
                    $lc2++;
                
                    //如果是刚换行，记录换行的最后下标
                    if($lc2==1){
                        if($lastIndex>1){
                            

                            if(isset($bigLd[$index+1])){


                                
                                $lastIndex = $lc-1;
                                
                            }
                            else{
                                
                                $lastIndex = $lc-1;
                                
                                if(count($bigLd[$index])<6){
                                    
                                    $lastIndex = 5;
                                    $bigLd[$index][] = $arr2[$i];
                                    
                                    $index2 = $index+1; 

                                    //如果数组长度未满6个
                                    if(count($bigLd[$index])<6){
                                        $index--;
                                    }
                                    continue;
                                }
                                
                            }
                            
                                    
                                
                            $index2 = $index+1; 
                            $index++;
                            $bigLd[$index][-$lastIndex] = $arr2[$i];
                        }
                        else{

                            
                            $lastIndex =3;
                            $lc2 =0;
                            $lc=0;
                            $index++;
                            $bigLd[$index][] = $arr2[$i];
                        }
                        
                    }
                    else{
                        
                        $index++;
                        $bigLd[$index][-$lastIndex] = $arr2[$i];
                        
                        
                    }
                    
                    
                    
                }
                else{
                    
                    if(count($bigLd[$index])<6){
                
                        $bigLd[$index][] = $arr2[$i];
                    }
                    else{
                        
                        $index++;
                        $bigLd[$index][] = $arr2[$i];
                    }
                    
                }
            }
            else{ //如果相邻两个数不相等
                
                //如果当前
                if($index2!=0){
                    
                    if($lc2>=1){
                    
                        $index = $index2;
                        $bigLd[$index]['0'] = $arr2[$i];
                        
                    }
                    else{
                    
                        $index++;
                        $bigLd[$index][] = $arr2[$i];
                    }
                    
                }
                else{
                    
                    $index++;
                    $bigLd[$index][] = $arr2[$i];
                }
                
                $lc = 0;
                $lc2 = 0;
            }
        }
        
    }



    foreach ($bigLd as $key => $value) {
        foreach ($value as $key2 => $value2) {
            
            if(preg_match('/^-[0-9]+$/',$key2)>0){
                $bigLd[$key][abs($key2)] = $value2;
                unset($bigLd[$key][$key2]);
            }
        }
        ksort($bigLd[$key]);
    }


    for($i=0;$i<count($bigLd);$i++){
        for($j=0;$j<6;$j++){
            if(!isset($bigLd[$i][$j])){
                $bigLd[$i][$j] = $null_value;
            }
        }
        ksort($bigLd[$i]);
    }
    //print_r($bigLd);
    // echo json_encode($bigLd);

    // $bigLd_a = array_column($bigLd, 0);

    // print_r($bigLd);


    // $bigLd_len = count($bigLd);
    $new_bigLd = turn_data($bigLd);

    return $new_bigLd;
}

//问路-蟑螂路实现
function make_zhang_lang_load ($arr2, $null_value = '') {
    
    $dyzLd = array();

    $startIndex = 4; //第4球开始计算的列数
    $index=0;
    for($i=0;$i<count($arr2);$i++){
        for($j=0;$j<count($arr2[$i]);$j++){

            //第一球
            if($i==3 && $j==1){
                if($arr2['3']['1']==''){
                    
                    //判断第一列和第二列球是否相等。相等则第一球为红色，不相等则第一球为蓝色。
                    if(count(array_filter($arr2['0']))==count(array_filter($arr2['3']))){
                        //echo count($arr2['0']);
                        $dyzLd[] = 'red';
                    }
                    else{
                        $dyzLd[] = 'blue';
                    }
                    //$startIndex=5;
                    $index = 1;
                }
                else{

                    //如果第一列第二排没有值，蓝色；有值，红色
                    if($arr2['0']['2']==''){
                        $dyzLd[] = 'blue';
                    }
                    else{
                        $dyzLd[] = 'red';
                    }
                    
                }

            }
            else if($i>=$startIndex){
                $j +=$index;
                $index = 0;
                if($arr2[$i][$j]!=''){
                    if($j==0){
                        //判断前两列是否相等,相等为红，不等为蓝
                        if( count(array_filter($arr2[$i-4])) == count(array_filter($arr2[$i-1]) )){
                            $dyzLd[] = 'red';
                        }
                        else{
                            $dyzLd[] = 'blue';
                        }
                    }
                    else{
                        //判断前隔一列是否有球，有球为红，无球为蓝
                        if($arr2[$i-3][$j]!=''){
                            $dyzLd[] = 'red';
                        }
                        else{
                            //$dyzLd[] = 'blue';
                            if($j>=2){
                                //前隔一列连续两次为空，则为红
                                if($arr2[$i-3][$j-1]==''&& $arr2[$i-3][$j]==''){
                                    
                                    $dyzLd[] = 'red';
                                }
                                else{
                                    $dyzLd[] = 'blue';
                                }
                            }
                            else{
                                $dyzLd[] = 'blue';
                            }
                        }

                    }
                }
            }
        }
    }
    // var_dump($dyzLd);
    $arr2 = $dyzLd;

    $lc = 0; //记录单双连出次数
    $lc2 = 0 ; //标志是否刚换行（0不是，1是）
    $lastIndex = 6; //换行前最后的下表,初始化为5
    $index = 0;
    $index2 = 0;
    $bigLd = array();

    for($i=0;$i<count($arr2);$i++){
        if($i==0){
            $bigLd[$index][] = $arr2[$i];
        }
        else {

            //如果相邻两个数相等
            if($arr2[$i]==$arr2[$i-1]){
                $lc++;
                //需要换行
                if($lc>=$lastIndex){
                    $lc2++;
                
                    //如果是刚换行，记录换行的最后下标
                    if($lc2==1){
                        if($lastIndex>1){
                            

                            if(isset($bigLd[$index+1])){


                                
                                $lastIndex = $lc-1;
                                
                            }
                            else{
                                
                                $lastIndex = $lc-1;
                                
                                if(count($bigLd[$index])<6){
                                    
                                    $lastIndex = 5;
                                    $bigLd[$index][] = $arr2[$i];
                                    
                                    $index2 = $index+1; 

                                    //如果数组长度未满6个
                                    if(count($bigLd[$index])<6){
                                        $index--;
                                    }
                                    continue;
                                }
                                
                            }
                            
                                    
                                
                            $index2 = $index+1; 
                            $index++;
                            $bigLd[$index][-$lastIndex] = $arr2[$i];
                        }
                        else{

                            
                            $lastIndex =3;
                            $lc2 =0;
                            $lc=0;
                            $index++;
                            $bigLd[$index][] = $arr2[$i];
                        }
                        
                    }
                    else{
                        
                        $index++;
                        $bigLd[$index][-$lastIndex] = $arr2[$i];
                        
                        
                    }
                    
                    
                    
                }
                else{
                    
                    if(count($bigLd[$index])<6){
                
                        $bigLd[$index][] = $arr2[$i];
                    }
                    else{
                        
                        $index++;
                        $bigLd[$index][] = $arr2[$i];
                    }
                    
                }
            }
            else{ //如果相邻两个数不相等
                
                //如果当前
                if($index2!=0){
                    
                    if($lc2>=1){
                    
                        $index = $index2;
                        $bigLd[$index]['0'] = $arr2[$i];
                        
                    }
                    else{
                    
                        $index++;
                        $bigLd[$index][] = $arr2[$i];
                    }
                    
                }
                else{
                    
                    $index++;
                    $bigLd[$index][] = $arr2[$i];
                }
                
                $lc = 0;
                $lc2 = 0;
            }
        }
        
    }



    foreach ($bigLd as $key => $value) {
        foreach ($value as $key2 => $value2) {
            
            if(preg_match('/^-[0-9]+$/',$key2)>0){
                $bigLd[$key][abs($key2)] = $value2;
                unset($bigLd[$key][$key2]);
            }
        }
        ksort($bigLd[$key]);
    }

    for($i=0;$i<count($bigLd);$i++){
        for($j=0;$j<6;$j++){
            if(!isset($bigLd[$i][$j])){
                $bigLd[$i][$j] = $null_value;
            }
        }
        ksort($bigLd[$i]);
    }

    $new_bigLd = turn_data($bigLd);
    return $new_bigLd;
}

//问路-大眼仔实现
function make_da_yan_zai_load ($arr2, $null_value = '') {

    $dyzLd = array();

    $startIndex = 2; //第二球开始计算的列数
    $index = 0 ;
    for($i=0;$i<count($arr2);$i++){
        for($j=0;$j<count($arr2[$i]);$j++){

            //第一球
            if($i==1 && $j==1){
                if($arr2['1']['1']==''){
                    
                    //判断第一列和第二列球是否相等。相等则第一球为红色，不相等则第一球为蓝色。
                    if(count(array_filter($arr2['0']))==count(array_filter($arr2['1']))){
                        // echo count($arr2['0']);
                        $dyzLd[] = 'red';
                    }
                    else{
                        $dyzLd[] = 'blue';
                    }
                    //$startIndex=3;
                    $index = 1;
                }
                else{

                    //如果第一列第二排没有值，蓝色；有值，红色
                    if($arr2['0']['1']==''){
                        $dyzLd[] = 'blue';
                    }
                    else{
                        $dyzLd[] = 'red';
                    }
                    
                }

            }
            else if($i>=$startIndex){
                $j +=$index;
                $index = 0;
                if($arr2[$i][$j]!=''){
                    if($j==0){
                        //判断前两列是否相等,相等为红，不等为蓝
                        if( count(array_filter($arr2[$i-2])) == count(array_filter($arr2[$i-1]) )){
                            $dyzLd[] = 'red';
                        }
                        else{
                            $dyzLd[] = 'blue';
                        }
                    }
                    else{
                        //判断左边是否有球，有球为红，无球为蓝
                        if($arr2[$i-1][$j]!=''){
                            $dyzLd[] = 'red';
                        }
                        else{
                            //$dyzLd[] = 'blue';
                            if($j>=2){
                                //左边连续两次为空，则为红
                                if($arr2[$i-1][$j-1]==''&& $arr2[$i-1][$j]==''){
                                    
                                    $dyzLd[] = 'red';
                                }
                                else{
                                    $dyzLd[] = 'blue';
                                }
                            }
                            else{
                                $dyzLd[] = 'blue';
                            }
                        }

                    }
                }
            }
        }
    }
    // var_dump($dyzLd);
    $arr2 = $dyzLd;

    $lc = 0; //记录单双连出次数
    $lc2 = 0 ; //标志是否刚换行（0不是，1是）
    $lastIndex = 6; //换行前最后的下表,初始化为5
    $index = 0;
    $index2 = 0;
    $bigLd = array();

    for($i=0;$i<count($arr2);$i++){
        if($i==0){
            $bigLd[$index][] = $arr2[$i];
        }
        else {

            //如果相邻两个数相等
            if($arr2[$i]==$arr2[$i-1]){
                $lc++;
                //需要换行
                if($lc>=$lastIndex){
                    $lc2++;
                
                    //如果是刚换行，记录换行的最后下标
                    if($lc2==1){
                        if($lastIndex>1){
                            

                            if(isset($bigLd[$index+1])){


                                
                                $lastIndex = $lc-1;
                                
                            }
                            else{
                                
                                $lastIndex = $lc-1;
                                
                                if(count($bigLd[$index])<6){
                                    
                                    $lastIndex = 5;
                                    $bigLd[$index][] = $arr2[$i];
                                    
                                    $index2 = $index+1; 

                                    //如果数组长度未满6个
                                    if(count($bigLd[$index])<6){
                                        $index--;
                                    }
                                    continue;
                                }
                                
                            }
                            
                                    
                                
                            $index2 = $index+1; 
                            $index++;
                            $bigLd[$index][-$lastIndex] = $arr2[$i];
                        }
                        else{

                            
                            $lastIndex =3;
                            $lc2 =0;
                            $lc=0;
                            $index++;
                            $bigLd[$index][] = $arr2[$i];
                        }
                        
                    }
                    else{
                        
                        $index++;
                        $bigLd[$index][-$lastIndex] = $arr2[$i];
                        
                        
                    }
                    
                    
                    
                }
                else{
                    
                    if(count($bigLd[$index])<6){
                
                        $bigLd[$index][] = $arr2[$i];
                    }
                    else{
                        
                        $index++;
                        $bigLd[$index][] = $arr2[$i];
                    }
                    
                }
            }
            else{ //如果相邻两个数不相等
                
                //如果当前
                if($index2!=0){
                    
                    if($lc2>=1){
                    
                        $index = $index2;
                        $bigLd[$index]['0'] = $arr2[$i];
                        
                    }
                    else{
                    
                        $index++;
                        $bigLd[$index][] = $arr2[$i];
                    }
                    
                }
                else{
                    
                    $index++;
                    $bigLd[$index][] = $arr2[$i];
                }
                
                $lc = 0;
                $lc2 = 0;
            }
        }
        
    }



    foreach ($bigLd as $key => $value) {
        foreach ($value as $key2 => $value2) {
            
            if(preg_match('/^-[0-9]+$/',$key2)>0){
                $bigLd[$key][abs($key2)] = $value2;
                unset($bigLd[$key][$key2]);
            }
        }
        ksort($bigLd[$key]);
    }

    for($i=0;$i<count($bigLd);$i++){
        for($j=0;$j<6;$j++){
            if(!isset($bigLd[$i][$j])){
                $bigLd[$i][$j] = $null_value;
            }
        }
        ksort($bigLd[$i]);
    }
    $new_bigLd = turn_data($bigLd);
    return $new_bigLd;
}

//小路
function make_small_load ($arr2, $null_value = '') {

    $dyzLd = array();

    $startIndex = 3; //第二球开始计算的列数
    $index = 0;
    for($i=0;$i<count($arr2);$i++){
        for($j=0;$j<count($arr2[$i]);$j++){

            //第一球
            if($i==2 && $j==1){
                if($arr2['2']['1']==''){
                    
                    //判断第一列和第二列球是否相等。相等则第一球为红色，不相等则第一球为蓝色。
                    if(count(array_filter($arr2['0']))==count(array_filter($arr2['2']))){
                        //echo count($arr2['0']);
                        $dyzLd[] = 'red';
                    }
                    else{
                        $dyzLd[] = 'blue';
                    }
                    //$startIndex=4;
                    $index = 1;
                }
                else{

                    //如果第一列第二排没有值，蓝色；有值，红色
                    if($arr2['0']['1']==''){
                        $dyzLd[] = 'blue';
                    }
                    else{
                        $dyzLd[] = 'red';
                    }
                    
                }

            }
            else if($i>=$startIndex){
                $j +=$index;
                $index = 0;
                if($arr2[$i][$j]!=''){
                    if($j==0){
                        //判断前两列是否相等,相等为红，不等为蓝
                        if( count(array_filter($arr2[$i-3])) == count(array_filter($arr2[$i-1]) )){
                            $dyzLd[] = 'red';
                        }
                        else{
                            $dyzLd[] = 'blue';
                        }
                    }
                    else{
                        //判断前隔一列是否有球，有球为红，无球为蓝
                        if($arr2[$i-2][$j]!=''){
                            $dyzLd[] = 'red';
                        }
                        else{
                            //$dyzLd[] = 'blue';
                            if($j>=2){
                                //前隔一列连续两次为空，则为红
                                if($arr2[$i-2][$j-1]==''&& $arr2[$i-2][$j]==''){
                                    
                                    $dyzLd[] = 'red';
                                }
                                else{
                                    $dyzLd[] = 'blue';
                                }
                            }
                            else{
                                $dyzLd[] = 'blue';
                            }
                        }

                    }
                }
            }
        }
    }
    // var_dump($dyzLd);
    $arr2 = $dyzLd;

    $lc = 0; //记录单双连出次数
    $lc2 = 0 ; //标志是否刚换行（0不是，1是）
    $lastIndex = 6; //换行前最后的下表,初始化为5
    $index = 0;
    $index2 = 0;

    $bigLd = array();


    for($i=0;$i<count($arr2);$i++){
        if($i==0){
            $bigLd[$index][] = $arr2[$i];
        }
        else {

            //如果相邻两个数相等
            if($arr2[$i]==$arr2[$i-1]){
                $lc++;
                //需要换行
                if($lc>=$lastIndex){
                    $lc2++;
                
                    //如果是刚换行，记录换行的最后下标
                    if($lc2==1){
                        if($lastIndex>1){
                            

                            if(isset($bigLd[$index+1])){


                                
                                $lastIndex = $lc-1;
                                
                            }
                            else{
                                
                                $lastIndex = $lc-1;
                                
                                if(count($bigLd[$index])<6){
                                    
                                    $lastIndex = 5;
                                    $bigLd[$index][] = $arr2[$i];
                                    
                                    $index2 = $index+1; 

                                    //如果数组长度未满6个
                                    if(count($bigLd[$index])<6){
                                        $index--;
                                    }
                                    continue;
                                }
                                
                            }
                            
                                    
                                
                            $index2 = $index+1; 
                            $index++;
                            $bigLd[$index][-$lastIndex] = $arr2[$i];
                        }
                        else{

                            
                            $lastIndex =3;
                            $lc2 =0;
                            $lc=0;
                            $index++;
                            $bigLd[$index][] = $arr2[$i];
                        }
                        
                    }
                    else{
                        
                        $index++;
                        $bigLd[$index][-$lastIndex] = $arr2[$i];
                        
                        
                    }
                    
                    
                    
                }
                else{
                    
                    if(count($bigLd[$index])<6){
                
                        $bigLd[$index][] = $arr2[$i];
                    }
                    else{
                        
                        $index++;
                        $bigLd[$index][] = $arr2[$i];
                    }
                    
                }
            }
            else{ //如果相邻两个数不相等
                
                //如果当前
                if($index2!=0){
                    
                    if($lc2>=1){
                    
                        $index = $index2;
                        $bigLd[$index]['0'] = $arr2[$i];
                        
                    }
                    else{
                    
                        $index++;
                        $bigLd[$index][] = $arr2[$i];
                    }
                    
                }
                else{
                    
                    $index++;
                    $bigLd[$index][] = $arr2[$i];
                }
                
                $lc = 0;
                $lc2 = 0;
            }
        }
        
    }



    foreach ($bigLd as $key => $value) {
        foreach ($value as $key2 => $value2) {
            
            if(preg_match('/^-[0-9]+$/',$key2)>0){
                $bigLd[$key][abs($key2)] = $value2;
                unset($bigLd[$key][$key2]);
            }
        }
        ksort($bigLd[$key]);
    }

    for($i=0;$i<count($bigLd);$i++){
        for($j=0;$j<6;$j++){
            if(!isset($bigLd[$i][$j])){
                $bigLd[$i][$j] = $null_value;
            }
        }
        ksort($bigLd[$i]);
    }
    $new_bigLd = turn_data($bigLd);
    return $new_bigLd;
}

//珠盘路
function make_zhu_pan_load ($data_for_others, $null_value = '') {

    $zhu_pan_load = array();

    foreach ($data_for_others as $v) {
        $zhu_pan_load = array_merge($zhu_pan_load, $v);
    }

    //过滤空元素
    $zhu_pan_load = array_filter($zhu_pan_load);

    //每组6个，做切割
    $zhu_pan_slice_arr = array_chunk($zhu_pan_load, 6);

    //取出数组的最后一个元素
    $last_data_arr = end($zhu_pan_slice_arr);
    $new_last_data_arr = array_pad($last_data_arr, 6, $null_value);

    //补齐数据后重新写入原数组
    $zhu_pan_slice_arr[count($zhu_pan_slice_arr) - 1] = $new_last_data_arr;

    $new_zhu_pan_load = turn_data($zhu_pan_slice_arr);

    return $new_zhu_pan_load;
}

//横列数据转换成竖列
function turn_data ($ask_way_data, $arr_len = 5) {

    $new_bigLd = array();

    for ($n = 0; $n <= $arr_len; $n++) {
        $new_bigLd[] = array_column($ask_way_data, $n);
    }
    return $new_bigLd;
}

//获取当前周周一凌晨的时间戳
function this_monday(){
    return strtotime(date('Y-m-d', (time() - ((date('w') == 0 ? 7 : date('w')) - 1) * 24 * 3600)));
}

//获取当前周周日凌晨的时间戳
function this_sunday(){
    return strtotime(date('Y-m-d', (time() + (7 - (date('w') == 0 ? 7 : date('w'))) * 24 * 3600)));
}


/**
 * 将中文编码成拼音
 * @param string $utf8Data utf8字符集数据
 * @param string $sRetFormat 返回格式 [head:首字母|all:全拼音]
 * @return string
 */
function toPY($utf8Data, $sRetFormat='head'){
    $sGBK = iconv('UTF-8', 'GBK', $utf8Data);
    $aBuf = array();
    for ($i=0, $iLoop=strlen($sGBK); $i<$iLoop; $i++) {
        $iChr = ord($sGBK{$i});
        if ($iChr>160)
            $iChr = ($iChr<<8) + ord($sGBK{++$i}) - 65536;
        if ('head' === $sRetFormat)
            $aBuf[] = substr(zh2py($iChr),0,1);
        else
            $aBuf[] = zh2py($iChr);
    }
    if ('head' === $sRetFormat){
        return implode('', $aBuf);
    }else{
        return implode('', $aBuf);
    }
}

/**
 * 中文转换到拼音(每次处理一个字符)
 * @param number $iWORD 待处理字符双字节
 * @return string 拼音
 */
function zh2py($iWORD) {
    $_aMaps = array(
        'a'=>-20319,'ai'=>-20317,'an'=>-20304,'ang'=>-20295,'ao'=>-20292,
        'ba'=>-20283,'bai'=>-20265,'ban'=>-20257,'bang'=>-20242,'bao'=>-20230,'bei'=>-20051,'ben'=>-20036,'beng'=>-20032,'bi'=>-20026,'bian'=>-20002,'biao'=>-19990,'bie'=>-19986,'bin'=>-19982,'bing'=>-19976,'bo'=>-19805,'bu'=>-19784,
        'ca'=>-19775,'cai'=>-19774,'can'=>-19763,'cang'=>-19756,'cao'=>-19751,'ce'=>-19746,'ceng'=>-19741,'cha'=>-19739,'chai'=>-19728,'chan'=>-19725,'chang'=>-19715,'chao'=>-19540,'che'=>-19531,'chen'=>-19525,'cheng'=>-19515,'chi'=>-19500,'chong'=>-19484,'chou'=>-19479,'chu'=>-19467,'chuai'=>-19289,'chuan'=>-19288,'chuang'=>-19281,'chui'=>-19275,'chun'=>-19270,'chuo'=>-19263,'ci'=>-19261,'cong'=>-19249,'cou'=>-19243,'cu'=>-19242,'cuan'=>-19238,'cui'=>-19235,'cun'=>-19227,'cuo'=>-19224,
        'da'=>-19218,'dai'=>-19212,'dan'=>-19038,'dang'=>-19023,'dao'=>-19018,'de'=>-19006,'deng'=>-19003,'di'=>-18996,'dian'=>-18977,'diao'=>-18961,'die'=>-18952,'ding'=>-18783,'diu'=>-18774,'dong'=>-18773,'dou'=>-18763,'du'=>-18756,'duan'=>-18741,'dui'=>-18735,'dun'=>-18731,'duo'=>-18722,
        'e'=>-18710,'en'=>-18697,'er'=>-18696,
        'fa'=>-18526,'fan'=>-18518,'fang'=>-18501,'fei'=>-18490,'fen'=>-18478,'feng'=>-18463,'fo'=>-18448,'fou'=>-18447,'fu'=>-18446,
        'ga'=>-18239,'gai'=>-18237,'gan'=>-18231,'gang'=>-18220,'gao'=>-18211,'ge'=>-18201,'gei'=>-18184,'gen'=>-18183,'geng'=>-18181,'gong'=>-18012,'gou'=>-17997,'gu'=>-17988,'gua'=>-17970,'guai'=>-17964,'guan'=>-17961,'guang'=>-17950,'gui'=>-17947,'gun'=>-17931,'guo'=>-17928,
        'ha'=>-17922,'hai'=>-17759,'han'=>-17752,'hang'=>-17733,'hao'=>-17730,'he'=>-17721,'hei'=>-17703,'hen'=>-17701,'heng'=>-17697,'hong'=>-17692,'hou'=>-17683,'hu'=>-17676,'hua'=>-17496,'huai'=>-17487,'huan'=>-17482,'huang'=>-17468,'hui'=>-17454,'hun'=>-17433,'huo'=>-17427,
        'ji'=>-17417,'jia'=>-17202,'jian'=>-17185,'jiang'=>-16983,'jiao'=>-16970,'jie'=>-16942,'jin'=>-16915,'jing'=>-16733,'jiong'=>-16708,'jiu'=>-16706,'ju'=>-16689,'juan'=>-16664,'jue'=>-16657,'jun'=>-16647,
        'ka'=>-16474,'kai'=>-16470,'kan'=>-16465,'kang'=>-16459,'kao'=>-16452,'ke'=>-16448,'ken'=>-16433,'keng'=>-16429,'kong'=>-16427,'kou'=>-16423,'ku'=>-16419,'kua'=>-16412,'kuai'=>-16407,'kuan'=>-16403,'kuang'=>-16401,'kui'=>-16393,'kun'=>-16220,'kuo'=>-16216,
        'la'=>-16212,'lai'=>-16205,'lan'=>-16202,'lang'=>-16187,'lao'=>-16180,'le'=>-16171,'lei'=>-16169,'leng'=>-16158,'li'=>-16155,'lia'=>-15959,'lian'=>-15958,'liang'=>-15944,'liao'=>-15933,'lie'=>-15920,'lin'=>-15915,'ling'=>-15903,'liu'=>-15889,'long'=>-15878,'lou'=>-15707,'lu'=>-15701,'lv'=>-15681,'luan'=>-15667,'lue'=>-15661,'lun'=>-15659,'luo'=>-15652,
        'ma'=>-15640,'mai'=>-15631,'man'=>-15625,'mang'=>-15454,'mao'=>-15448,'me'=>-15436,'mei'=>-15435,'men'=>-15419,'meng'=>-15416,'mi'=>-15408,'mian'=>-15394,'miao'=>-15385,'mie'=>-15377,'min'=>-15375,'ming'=>-15369,'miu'=>-15363,'mo'=>-15362,'mou'=>-15183,'mu'=>-15180,
        'na'=>-15165,'nai'=>-15158,'nan'=>-15153,'nang'=>-15150,'nao'=>-15149,'ne'=>-15144,'nei'=>-15143,'nen'=>-15141,'neng'=>-15140,'ni'=>-15139,'nian'=>-15128,'niang'=>-15121,'niao'=>-15119,'nie'=>-15117,'nin'=>-15110,'ning'=>-15109,'niu'=>-14941,'nong'=>-14937,'nu'=>-14933,'nv'=>-14930,'nuan'=>-14929,'nue'=>-14928,'nuo'=>-14926,
        'o'=>-14922,'ou'=>-14921,
        'pa'=>-14914,'pai'=>-14908,'pan'=>-14902,'pang'=>-14894,'pao'=>-14889,'pei'=>-14882,'pen'=>-14873,'peng'=>-14871,'pi'=>-14857,'pian'=>-14678,'piao'=>-14674,'pie'=>-14670,'pin'=>-14668,'ping'=>-14663,'po'=>-14654,'pu'=>-14645,
        'qi'=>-14630,'qia'=>-14594,'qian'=>-14429,'qiang'=>-14407,'qiao'=>-14399,'qie'=>-14384,'qin'=>-14379,'qing'=>-14368,'qiong'=>-14355,'qiu'=>-14353,'qu'=>-14345,'quan'=>-14170,'que'=>-14159,'qun'=>-14151,
        'ran'=>-14149,'rang'=>-14145,'rao'=>-14140,'re'=>-14137,'ren'=>-14135,'reng'=>-14125,'ri'=>-14123,'rong'=>-14122,'rou'=>-14112,'ru'=>-14109,'ruan'=>-14099,'rui'=>-14097,'run'=>-14094,'ruo'=>-14092,
        'sa'=>-14090,'sai'=>-14087,'san'=>-14083,'sang'=>-13917,'sao'=>-13914,'se'=>-13910,'sen'=>-13907,'seng'=>-13906,'sha'=>-13905,'shai'=>-13896,'shan'=>-13894,'shang'=>-13878,'shao'=>-13870,'she'=>-13859,'shen'=>-13847,'sheng'=>-13831,'shi'=>-13658,'shou'=>-13611,'shu'=>-13601,'shua'=>-13406,'shuai'=>-13404,'shuan'=>-13400,'shuang'=>-13398,'shui'=>-13395,'shun'=>-13391,'shuo'=>-13387,'si'=>-13383,'song'=>-13367,'sou'=>-13359,'su'=>-13356,'suan'=>-13343,'sui'=>-13340,'sun'=>-13329,'suo'=>-13326,
        'ta'=>-13318,'tai'=>-13147,'tan'=>-13138,'tang'=>-13120,'tao'=>-13107,'te'=>-13096,'teng'=>-13095,'ti'=>-13091,'tian'=>-13076,'tiao'=>-13068,'tie'=>-13063,'ting'=>-13060,'tong'=>-12888,'tou'=>-12875,'tu'=>-12871,'tuan'=>-12860,'tui'=>-12858,'tun'=>-12852,'tuo'=>-12849,
        'wa'=>-12838,'wai'=>-12831,'wan'=>-12829,'wang'=>-12812,'wei'=>-12802,'wen'=>-12607,'weng'=>-12597,'wo'=>-12594,'wu'=>-12585,
        'xi'=>-12556,'xia'=>-12359,'xian'=>-12346,'xiang'=>-12320,'xiao'=>-12300,'xie'=>-12120,'xin'=>-12099,'xing'=>-12089,'xiong'=>-12074,'xiu'=>-12067,'xu'=>-12058,'xuan'=>-12039,'xue'=>-11867,'xun'=>-11861,
        'ya'=>-11847,'yan'=>-11831,'yang'=>-11798,'yao'=>-11781,'ye'=>-11604,'yi'=>-11589,'yin'=>-11536,'ying'=>-11358,'yo'=>-11340,'yong'=>-11339,'you'=>-11324,'yu'=>-11303,'yuan'=>-11097,'yue'=>-11077,'yun'=>-11067,
        'za'=>-11055,'zai'=>-11052,'zan'=>-11045,'zang'=>-11041,'zao'=>-11038,'ze'=>-11024,'zei'=>-11020,'zen'=>-11019,'zeng'=>-11018,'zha'=>-11014,'zhai'=>-10838,'zhan'=>-10832,'zhang'=>-10815,'zhao'=>-10800,'zhe'=>-10790,'zhen'=>-10780,'zheng'=>-10764,'zhi'=>-10587,'zhong'=>-10544,'zhou'=>-10533,'zhu'=>-10519,'zhua'=>-10331,'zhuai'=>-10329,'zhuan'=>-10328,'zhuang'=>-10322,'zhui'=>-10315,'zhun'=>-10309,'zhuo'=>-10307,'zi'=>-10296,'zong'=>-10281,'zou'=>-10274,'zu'=>-10270,'zuan'=>-10262,'zui'=>-10260,'zun'=>-10256,'zuo'=>-10254
    );
    if($iWORD>0 && $iWORD<160 ) {
        return chr($iWORD);
    } elseif ($iWORD<-20319||$iWORD>-10247) {
        return '';
    } else {
        foreach ($_aMaps as $py => $code) {
            if($code > $iWORD) break;
            $result = $py;
        }
        return $result;
    }
}

/**
 * 获取执行时间
 * @copyright gpgao
 * @date 2018-07-11 18:44:15
 * @param end_time 结束时间
 * @param start_time 开始时间
 * @return string 执行时间（毫秒）
 */
function getRunTime($end_time,$start_time){
    return bcmul(bcsub($end_time,$start_time,4),1000,3).'ms';
}




//生成静态缓存页
function createCacheHtml($html, $html_text = '') {
    $cacheHtmlDir = S_CACHE . 'cache_tpl' . DS . 'cache_html' . DS . $html . '.html';
    file_put_contents($cacheHtmlDir, $html_text, LOCK_EX);
}

function existeCacheHtml($html) {
    $cacheHtmlDir = S_CACHE . 'cache_tpl' . DS . 'cache_html' . DS . $html . '.html';
    if(file_exists($cacheHtmlDir)) {
        return true;
    }else {
        return false;
    }
}
function getCacheHtml($html) {
    $cacheHtmlDir = S_CACHE . 'cache_tpl' . DS . 'cache_html' . DS . $html . '.html';

    @include($cacheHtmlDir);
}


/**
 * 快速排序
 * @access public
 * @param array $arr 需要排序的数组
 * @param string $name 二维数组排序条件字段
 * @author Cloud
 */
function quickSort($arr, $name = "",$is_reverse=false)
{
    //判断参数是否是一个数组
    if (!is_array($arr)) return false;
    //递归出口数组长度为1，直接返回数组
    $length = count($arr);
    if ($length <= 1) return $arr;
    //数组元素有多个,则定义两个空数组
    $left = $right = [];
    //使用for循环进行遍历，把第一个元素当做比较的对象
    if ($name == "") {
        for ($i = 1; $i < $length; $i++) {
            //判断当前元素的大小
            $front = floatval($arr[$i]);
            $init = floatval($arr[0]);

            if (bccomp($front, $init, 2) == -1) {
                $left[] = $arr[$i];
            } else {
                $right[] = $arr[$i];
            }
        }
    } else {
        for ($i = 1; $i < $length; $i++) {
            //判断当前元素的大小
            $front = floatval($arr[$i][$name]);
            $init = floatval($arr[0][$name]);

            if (bccomp($front, $init, 2) == -1) {
                $left[] = $arr[$i];
            } else {
                $right[] = $arr[$i];
            }
        }
    }

    //递归调用
    $left = quickSort($left);
    $right = quickSort($right);
    //将所有的结果合并
    $result = array_merge($left, [$arr[0]], $right);
    if($is_reverse) $result = array_reverse($result);
    return $result;
}


/*
 *
 * */
function showTitle($title, $length = 100, $encoding = 'utf-8') {
    if(mb_strlen($title, $encoding) > $length) {
        echo 'data-title="'.$title.'"';
    }
}


function strCut($str, $length, $encoding = 'utf-8') {
    if (mb_strlen($str, $encoding) > $length) $str=mb_substr($str,0,$length, $encoding) . '...';
    return $str;
}

/**
 *
 * @param $token
 * @param $uid
 * @return bool
 */

function checkToken($token,$uid,$db){
    lg('check_token',encode(array(
        '$uid'=>$uid,
        '$token'=>$token,
    )));
    if(empty($token)){
        return 1;
    }
    //验证Token取数据库
    $sql = "SELECT sessionid FROM un_session WHERE user_id={$uid}";
    $dbToken = $db->result($sql);
    lg('check_token',encode(array(
        '$uid'=>$uid,
        '$token'=>$token,
        '($dbToken!=$token)'=>($dbToken!=$token),
    )));
    if(empty($dbToken)){
        return 2;
    }
    if($dbToken!=$token){
        return 1;
    }
    return 0;
}


/**
 * 获取id和name列表
 * @access public
 * @param array $array 变换的数组
 * @param array $field 提取字段
 * @return array $column
 * @author Cloud
 */
function columnIdName($array,$field=null){
    $column = [];
    foreach ($array as $i){
        if(empty($field)) $column[$i["id"]] = $i["name"];
        else $column[$i["id"]] = $i[$field];
    }
    return $column;
}