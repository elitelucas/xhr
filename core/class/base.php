<?php

/**
 *  base.php 基础类
 *
 * @copyright			(C) 2011 snyni.com
 * @lastmodify			2011-08-31   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

abstract class Base {

    public function __set($name, $value) {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    public function __get($name) {
        if (isset($this->$name)) {
            return $this->$name;
        } else {
            return null;
        }
    }

    public function __isset($name) {
        return isset($this->$name);
    }

    public function __unset($name) {
        unset($this->$name);
    }

}

?>