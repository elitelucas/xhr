<?php

/**
 *  cookie.php COOKIE操作类
 *
 * @copyright			(C) 2011 snyni.com
 * @lastmodify			2011-10-21   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

class cookie {

    static function is_set($name) {
        return isset($_COOKIE[C('cookie_prefix')][$name]);
    }

    static function get($name) {
        return isset($_COOKIE[C('cookie_prefix')][$name]) ? $_COOKIE[C('cookie_prefix')][$name] : null;
    }

    static function set($name, $value, $expire = 0, $path = '/', $domain = '') {
        !$domain && $domain = C('cookie_domain');
        $expire && $expire += SYS_TIME;
        setcookie(C('cookie_prefix') . '[' . $name . ']', $value, $expire, $path, $domain, false, true);
    }

    static function del($name, $path = '/', $domain = '') {
        !$domain && $domain = C('cookie_domain');
        self::set($name, '', SYS_TIME - 3600, $path, $domain);
    }

}

?>