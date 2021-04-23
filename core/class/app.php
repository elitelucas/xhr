<?php

/**
 *  app.php 运行基础类
 *
 * @copyright
 * @lastmodify
 */
!defined('IN_SNYNI') && die('Access Denied!');

class App {

    /**
     * 构造函数
     */
    public function __construct() {
        define('ROUTE_M', $this->route_m());
        define('ROUTE_C', $this->route_c());
        define('ROUTE_A', $this->route_a());
        if(!empty(C('disable_h5'))){
            if(ROUTE_M=='web' && ROUTE_C=='app' && ROUTE_A=='index'){ //首页
                exit('404');
            }

            if(ROUTE_M=='web' && ROUTE_C=='chatRoom'){ //聊天室
                exit('404');
            }

            if(ROUTE_M=='web' && ROUTE_C=='user' && in_array(ROUTE_A,array('login','my','workTeam','myMemberWeb','myGroupWeb','myOneselfWeb','openAccount'))){ //会员信息
                exit('404');
            }

            if(ROUTE_M=='web' && ROUTE_C=='cash'){
                exit('404');
            }

            if(ROUTE_M=='web' && ROUTE_C=='redpacket'){
                exit('404');
            }

            if(ROUTE_M=='web' && ROUTE_C=='bank'){
                exit('404');
            }

            if(ROUTE_M=='web' && ROUTE_C=='account'){
                exit('404');
            }

            if(ROUTE_M=='web' && ROUTE_C=='recharge'){
                exit('404');
            }

            if(ROUTE_M=='web' && ROUTE_C=='order'){
                exit('404');
            }

            if(ROUTE_M=='web' && ROUTE_C=='openAward'){
                exit('404');
            }
        }
        $list=C('disable_module'); //获取配置文件中要屏蔽选项
        if(empty($list)){
            $this->init();
        }else{
            if(in_array(ROUTE_M,$list['m']) || in_array(ROUTE_C,$list['c'])){
                exit('404');
            }else{
                $this->init();
            }
        }
    }

    /**
     * 调用件事
     */
    private function init() {
        try {
            $controller = $this->load_controller();
            if (method_exists($controller, ROUTE_A)) {
                if (preg_match('/^[_]/i', ROUTE_A)) {
                    throw new Exception('You are visiting the action is to protect the private action.');
                } else {
                    call_user_func(array($controller, ROUTE_A));
                }
            } else {
                throw new Exception('Action does not exist.');
            }
        } catch (Exception $err) {
            error($err);
        }
        
    }

    /**
     * 加载控制器
     * @param string $filename
     * @param string $m
     * @return obj
     */
    private function load_controller($filename = '', $m = '') {
        if (empty($filename))
            $filename = ROUTE_C;
        if (empty($m))
            $m = ROUTE_M;
        $filepath = S_PAGE . 'controller' . DS . $m . DS . $filename . '.php';
        if (file_exists($filepath)) {
            $classname = ucfirst($filename) . "Action";
            include_cache($filepath);
            if (class_exists($classname)) {
                return new $classname;
            } else {
                throw new Exception('Controller does not exist.');
            }
        } else {
            throw new Exception('Controller does not exist.');
        }
    }

    /**
     * 获取模型
     */
    private function route_m() {
        $m = isset($_GET['m']) && !empty($_GET['m']) ? $_GET['m'] : (isset($_POST['m']) && !empty($_POST['m']) ? $_POST['m'] : '');
        $m = $this->safe_deal($m);
        if (empty($m)) {
            $urlPathData = explode('?', ltrim($_SERVER['REQUEST_URI'], '/'), 2);
            $urlData = explode('/', $urlPathData[0]);
            if (count($urlData) >= 3) {
                $this->safe_deal($urlData[0]);
                if (is_string($urlData[0])) return $urlData[0];
            }else {
                return C('default_module');
            }
        } else {
            if (is_string($m))
                return $m;
        }
    }

    /**
     * 获取控制器
     */
    private function route_c() {
        $c = isset($_GET['c']) && !empty($_GET['c']) ? $_GET['c'] : (isset($_POST['c']) && !empty($_POST['c']) ? $_POST['c'] : '');
        $c = $this->safe_deal($c);
        if (empty($c)) {
            $urlPathData = explode('?', ltrim($_SERVER['REQUEST_URI'], '/'), 2);
            $urlData = explode('/', $urlPathData[0]);
            if (count($urlData) == 2) {
                $this->safe_deal($urlData[0]);
                if (is_string($urlData[0])) return $urlData[0];
            }else if (count($urlData) >= 3) {
                $this->safe_deal($urlData[1]);
                if (is_string($urlData[1])) return $urlData[1];
            }else {
                return C('default_controllers');
            }
        } else {
            if (is_string($c))
                return $c;
        }
    }

    /**
     * 获取事件
     */
    private function route_a() {
        $a = isset($_GET['a']) && !empty($_GET['a']) ? $_GET['a'] : (isset($_POST['a']) && !empty($_POST['a']) ? $_POST['a'] : '');
        $a = $this->safe_deal($a);
        if (empty($a)) {
            $urlPathData = explode('?', ltrim($_SERVER['REQUEST_URI'], '/'), 2);
            $urlData = explode('/', $urlPathData[0]);
            if (count($urlData) == 2) {
                $this->safe_deal($urlData[1]);
                if (is_string($urlData[1])) return $urlData[1];
            }else if (count($urlData) >= 3) {
                $this->safe_deal($urlData[2]);
                if (is_string($urlData[2])) return $urlData[2];
            }else {
                return C('default_action');
            }
        } else {
            if (is_string($a))
                return $a;
        }
    }

    /**
     * 安全处理函数
     * 处理m,a,c
     */
    private function safe_deal($str) {
        return str_replace(array('/', '.'), '', $str);
    }

}

?>