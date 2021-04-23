<?php

/**
 *  session.php session类
 *
 * @copyright			(C) 2011 snyni.com
 * @lastmodify			2011-09-02   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

class Session {

    static function start() {
       /* $path = S_CACHE . 'sessions' . DS;
        ini_set('session.save_handler', 'files');
        self::path($path);*/
        session_start();
        self::setTime();
    }

    static function pause() {
        session_write_close();
    }

    static function clear() {
        unset($_SESSION);
        session_destroy();
    }


    static function clearBack() { //后台
        foreach ($_SESSION[C('cookie_prefix')] as $k=> $v){
            if($k == 'admin'){
                unset($_SESSION[C('cookie_prefix')][$k]);
            }

        }
    }

    static function clearFore() { //前台
        foreach ($_SESSION[C('cookie_prefix')] as $k=> $v){
            if($k != 'admin'){
                unset($_SESSION[C('cookie_prefix')][$k]);
            }

        }
    }

    static function name($name = null) {
        $name && $name = C('cookie_prefix') . $name;
        return isset($name) ? session_name($name) : session_name();
    }

    static function id($id = null) {
        return isset($id) ? session_id($id) : session_id();
    }

    static function path($path = null) {
        return !empty($path) ? session_save_path($path) : session_save_path();
    }

    static function get($name) {
        return isset($_SESSION[C('cookie_prefix')][$name]) ? $_SESSION[C('cookie_prefix')][$name] : null;
    }

    static function set($name, $value) {
        if (null === $value) {
            unset($_SESSION[C('cookie_prefix')][$name]);
        } else {
            $_SESSION[C('cookie_prefix')][$name] = $value;
        }
        return;
    }

    static function is_set($name) {
        return isset($_SESSION[C('cookie_prefix')][$name]);
    }

    static function del($name) {
        unset($_SESSION[C('cookie_prefix')][$name]);
    }

    static function setTime($time = 86400) {
        $time = time() + $time;
        setcookie(session_name(), session_id(), $time, '/', '', false, true);
    }
}

?>