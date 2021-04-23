<?php

/**
 *  model.php 逻辑层父类
 *
 */
!defined('IN_SNYNI') && die('Access Denied!');

class Model {

    protected $db;
    protected $_data = array();
    protected $_SN;

    public function __construct() {
        $this->initModel();
    }

    public function initModel() {
        $this->db = getConn();
        $this->_SN = &$GLOBALS['_SN'];
    }

    public function __set($name, $value) {
        $this->_data[$name] = $value;
    }

    public function __get($name) {
        if (isset($this->_data[$name])) {
            return $this->_data[$name];
        } elseif (property_exists($this, $name)) {
            return $this->$name;
        } else {
            return null;
        }
    }

    protected function refreshRedis($action, $param) {
        if (empty($action) || empty($param)) {
            return array('status' => 100002, 'data' => " 缺少刷新参数");
        }
        //组装URL
        $url = C('app_home') . "/index.php?m=api&c=initCache&a=index";
        $param = array(
            'pass' => C('pass'),
            'action' => $action,
            'param' => $param
        );
        $result = curl_post_content($url, $param);
        return $result;
    }

}

?>