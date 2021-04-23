<?php

/**
 *  mysql.php	数据库类
 *
 * @copyright			(C) 2011 snyni.com
 * @lastmodify			2011-10-20   by snyni
 */
!defined('IN_SNYNI') && die('Access Denied!');

$debug_sql = array();

class mysql {

    /**
     * 配置信息
     *
     * @var unknown_type
     */
    protected $_config = array('db_host' => 'localhost', 'db_user' => 'edu', 'db_pwd' => 'edu',
        'db_name' => 'edu', 'db_lang' => 'utf8', 'db_prefix' => 'edu_', 'pconnect' => 0,'debug'=>1);
    protected $_sql;
    protected $_conn;
    protected $_rs;
    public $errormsg;
	private $lastsql;

    /**
     * 加载配置信息
     */
    public function __construct($config = array()) {
        extension_loaded('mysqli') or die('php服务没有加载MySqli模块...');
        $this->_config = array_merge($this->_config, $config);
    }

    /**
     * 创建数据库连接
     */
    public function connect() {
        $link = null;

        if(!empty($this->_config['db_port'])){
            //$host = '', $user = '', $password = '', $database = '', $port = '', $socket = ''
            if (!$this->_conn && (!$link = mysqli_connect($this->_config['db_host'], $this->_config['db_user'], $this->_config['db_pwd'],$this->_config['db_name'],$this->_config['db_port']))) {
                $this->halt('mycat not connect');
            }else{
                $this->_conn = &$link;
                if ($this->ServerVer() > '4.1') {
                    $dbcharset = $this->_config['db_lang'];
                    $serverset = $dbcharset ? 'character_set_connection=' . $dbcharset . ', character_set_results=' . $dbcharset . ', character_set_client=binary' : '';
                    $serverset .= $this->ServerVer() > '5.0.1' ? ((empty($serverset) ? '' : ',') . 'sql_mode=\'\'') : '';
                    $serverset && mysqli_query($this->_conn, "SET $serverset");
                }
            }
        }else{
            if (!$this->_conn && (!$link = mysqli_connect($this->_config['db_host'], $this->_config['db_user'], $this->_config['db_pwd']))) {
                $this->halt('notconnect');
            } else {
                $this->_conn = &$link;
                if ($this->ServerVer() > '4.1') {
                    $dbcharset = $this->_config['db_lang'];
                    $serverset = $dbcharset ? 'character_set_connection=' . $dbcharset . ', character_set_results=' . $dbcharset . ', character_set_client=binary' : '';
                    $serverset .= $this->ServerVer() > '5.0.1' ? ((empty($serverset) ? '' : ',') . 'sql_mode=\'\'') : '';
                    $serverset && mysqli_query($this->_conn, "SET $serverset");
                }
                //$dbname && @mysql_select_db($dbname, $link);
            }
        }
        return $this;
    }

    //选择数据库
    public function select_db() {
        $db = $this->_config['db_name'];
        mysqli_select_db($this->_conn, $db) or die('没连接到数据库,请检查....');
        return $this;
    }

    //查询一条数据
    public function getone($sql) {
        if (strpos($sql, 'LIMIT') == false) {
            $sql = $sql . " LIMIT 1";
        }
        $query = $this->query($sql);
        $this->lastsql = $sql;
        return $this->fetch($query);
    }

    //查询多条数据
    public function getall($sql, $key = '') {
        $arr = array();
        $query = $this->query($sql);
        $this->lastsql = $sql;
        while ($val = $this->fetch($query)) {
            if ($key) {
                $arr[$val[$key]] = $val;
            } else {
                $arr[] = $val;
            }
        }
        return $arr;
    }

    //插入数据
    public function insert($table, $data = array()) {
        $cols = array();
        $vals = array();
        $one = reset($data);
        if (is_array($one)) {
            $cols = $this->deal_field(array_keys($one));
            foreach ($data as $val) {
                $vals[] = '(' . implode(',', $this->deal_value($val)) . ')';
            }
            $vals = implode(',', $vals);
        } else {
            $cols = $this->deal_field(array_keys($data));
            $vals = '(' . implode(',', $this->deal_value($data)) . ')';
        }
        $sql = "INSERT INTO " . $this->deal_field($table) . " ( {$cols} ) VALUES {$vals}";
        $this->exec($sql);
		$this->lastsql =$sql;
        return $this->insert_id();
    }

	public function getLastSql()
	{
		return $this->lastsql;
	}

    /**
     * @return mixed  最后一条SQL
     */
    public function _sql()
    {
        return $this->lastsql;
    }
	
	
    //替换插入数据
    public function replace($table, $data = array()) {
        $cols = array();
        $vals = array();
        $one = reset($data);
        if (is_array($one)) {
            $cols = $this->deal_field(array_keys($one));
            foreach ($data as $val) {
                $vals[] = '(' . implode(',', $this->deal_value($val)) . ')';
            }
            $vals = implode(',', $vals);
        } else {
            $cols = $this->deal_field(array_keys($data));
            $vals = '(' . implode(',', $this->deal_value($data)) . ')';
        }
        $sql = "REPLACE INTO " . $this->deal_field($table) . " ( {$cols} ) VALUES {$vals}";
        $this->lastsql =$sql;
        $this->exec($sql);
        return $this->affected_rows();
    }

    //修改数据
    public function update($table, $data, $where, $add = '') {
        $set = array();
        if (is_array($data)) {
            foreach ($data as $col => $val) {

                switch (substr($val, 0, 2)) {
                    case '+=':
                        $val = substr($val, 2);
                        if (is_numeric($val)) {
                            $set[] = $this->deal_field($col) . ' = ' . $this->deal_field($col) . '+' . $this->deal_value($val);
                        } else {
                            continue;
                        }
                        break;
                    case '-=':
                        $val = substr($val, 2);
                        if (is_numeric($val)) {
                            $set[] = $this->deal_field($col) . ' = ' . $this->deal_field($col) . '-' . $this->deal_value($val);
                        } else {
                            continue;
                        }
                        break;
                    case ':=':
                        $val = substr($val, 1);
                        $set[] = $col . $val;
                        break;
                    default:
                        $set[] = $this->deal_field($col) . ' = ' . $this->deal_value($val);
                }
            }
            $set = implode(',', $set);
        } else {
            $set = $data;
        }
        $where = $this->deal_where($where);
        !empty($where) && $where = " WHERE {$where}";
        $sql = "UPDATE " . $this->deal_field($table) . " SET {$set} {$where}";
        $this->lastsql =$sql;
        //$this->exec($sql . $add);
        //return $this->affected_rows();
		$rs = $this->exec($sql . $add);
		return $rs;
    }

    //删除数据
    public function delete($table, $where) {
        $where = $this->deal_where($where);
        !empty($where) && $where = " WHERE {$where}";
        $sql = "DELETE FROM " . $this->deal_field($table) . " {$where}";
        $this->exec($sql, $bind);
        return $this->affected_rows();
    }

    //执行sql语句（有结果集）
    public function query($sql) {
        return $this->execute($sql, 'mysqli_query');
    }

    //执行sql语句（无结果集）
    public function exec($sql) {
        return $this->execute($sql, 'mysqli_query');
    }

    //私有执行sql语句
    private function execute($sql, $func) {
        !$this->_conn && $this->connect();
        $this->_sql = $this->deal_prefix($sql);
        if ($this->_config['debug']) {
            $t = microtime(true);
        }
        if (!$this->_rs = $func($this->_conn, $this->_sql)) {
            $this->halt('Execution error', $this->_sql);
        }

        if ($this->_config['debug']) {
            $sqltime = microtime(true) - $t;
            $explain = array();
            $info = mysqli_info($this->_conn);
            if ($this->_rs && preg_match("/^(select )/i", $this->_sql)) {
                $explain = mysqli_fetch_assoc(mysqli_query($this->_conn, 'EXPLAIN ' . $this->_sql));
            }
            $GLOBALS['debug_sql'][] = array('sql' => $sql, 'time' => $sqltime, 'info' => $info, 'explain' => $explain);
        }

        if (!isset($GLOBALS['debug_sql']['querynum'])) {
            $GLOBALS['debug_sql']['querynum'] = 0;
        }
        $GLOBALS['debug_sql']['querynum']++;

        return $this->_rs;
    }

    //返回结果集中的字段值
    public function result($sql, $num = 0) {
        $query = $this->query($sql);
        return $this->num_rows($query) > 0 ? $this->mysqli_result($query, $num) : false;
    }

    function mysqli_result($res, $field = 0) {
        $datarow = $res->fetch_array();
        return isset($datarow[$field]) ? $datarow[$field] : null;
    }

    //返回数据，参数$query:结果集，$type:0为对象形式，1为数组形式，默认为1
    public function fetch($query = null, $type = 1) {
        $query = $query ? $query : $this->_rs;
        if ($type == 0) {
            return mysqli_fetch_object($query);
        } else {
            return mysqli_fetch_array($query, $type);
        }
    }

    //获取列的数目
    function num_rows($query = null) {
        !$query && $query = $this->_rs;
        return mysqli_num_rows($query);
    }

    //获取上一次影响的行数
    public function affected_rows() {
        return mysqli_affected_rows($this->_conn);
    }

    //返回自增id
    public function insert_id() {
        return ($I1 = mysqli_insert_id($this->_conn)) >= 0 ? $I1 : $this->result("SELECT last_insert_id();");
    }

    //获取错误信息
    public function error() {
        return $this->_conn ? mysqli_error($this->_conn) : 'mysql unknow error';
    }

    //获取错误码
    public function errno() {
        return intval($this->_conn ? mysqli_errno($this->_conn) : '-9999');
    }

    //获取mysql版本
    public function ServerVer() {
        return mysqli_get_server_info($this->_conn);
    }

    //释放结果内存
    public function free() {
        if (is_resource($this->_rs)) {
            return mysqli_free_result($this->_rs);
        }
    }

    //关闭数据库连接
    public function close() {
        if (is_resource($this->_conn)) {
            return mysqli_close($this->_conn);
        }
    }

    //私有给sql语句加上表前缀
    public function deal_prefix($sql = '') {
        if ($sql && strpos($sql, '#@_') !== false) {
            $sql = str_replace('#@_', $this->_config['db_prefix'], $sql);
        }
        return $sql;
    }

    //私有处理表名
    private static function deal_field($str = '') {
        if (is_array($str)) {
            $str = array_map(array(__class__, __method__), $str);
            $str = implode(',', $str);
            return $str;
        }
        if (strpos($str, ',') !== false && strpos($str, '`') === false) {
            $arr = explode(',', $str);
            $str = array_map(array(__class__, __method__), $arr);
            $str = implode(',', $str);
            return $str;
        }
        if ($str && $str != '*' && strpos($str, 'COUNT') === false && strpos($str, 'SUM') === false && strpos($str, 'AS') === false)
            $str = "`" . trim($str) . "`";
        return $str;
    }

    //私有处理数据值
    public static function deal_value($str = '') {
        if (is_array($str)) {
            $str = array_map(array(__class__, __method__), $str);
            return $str;
        }
        $str = "'{$str}'";
        return $str;
    }

    //私有处理where语句，参加例如：array("a='b'" , "a = 'c'",'_logic'=>'OR');。也可直接写
    public function deal_where($where) {
        if (is_array($where) && !empty($where)) {
            if (array_key_exists('_logic', $where)) {
                $logic = strtoupper($where['_logic']);
                unset($where['_logic']);
            } else {
                $logic = 'AND';
            }
            foreach ($where as $key => $term) {
                if (is_numeric($key)) {
                    $sql[] = $term;
                } else {
                    $sql[] = "`" . $key . "`='" . $term . "'";
                }
            }
            $where = implode(' ' . $logic . ' ', $sql);
        } elseif (empty($where)) {
            $where = '';
        }
        return $where;
    }

    /**
     * 组建查询语句
     *
     * @param unknown_type $where 条件，可数组也可字符串
     * @param unknown_type $data  查询字段
     * @param unknown_type $table 数据表名
     * @param unknown_type $limit LIMIT
     * @param unknown_type $order 排序
     * @param unknown_type $group 分组
     * @return unknown
     */
    public function c_sql($where = '', $data = '*', $table = '', $limit = '', $order = '', $group = '') {
        $where = $this->deal_where($where);
        $where = $where == '' ? '' : ' WHERE ' . $where;
        $order = $order == '' ? '' : ' ORDER BY ' . $order;
        $group = $group == '' ? '' : ' GROUP BY ' . $group;
        $limit = $limit == '' ? '' : ' LIMIT ' . $limit;
        $data = $data == '' ? '*' : $data;
        $sql = 'SELECT ' . $this->deal_field($data) . ' FROM ' . $table . $where . $group . $order . $limit;
        if (preg_match("/(,|JOIN)/", $table, $key)) {
            $sql = str_replace('`', '', $sql);
        }
        return $sql;
    }

    public function halt($message = '', $sql = '') {
        if (C("debug_mode")) {
            $this->errormsg = "<b>MySQL Query : </b> $sql <br /><b> MySQL Error : </b>" . $this->error() . " <br /> <b>MySQL Errno : </b>" . $this->errno() . " <br /><b> Message : </b> $message <br />";
            $msg = $this->_config['debug'] ? $this->errormsg : 'Data manipulation errors...';
            echo '<div style="font-size:12px;text-align:left; border:1px solid #9cc9e0; padding:1px 4px;color:#000000;font-family:Arial, Helvetica,sans-serif;"><span>' . $msg . '</span></div>';
            exit;
        } else {
            $msg = date("Y-m-d H:i:s", SYS_TIME) . "\t" . $sql . "\t" . $this->error() . "\t" . ip() . "\t" . $_SERVER['REQUEST_URI'] . "\n";
            @file_put_contents(S_CACHE . "log" . DS . "sql_error.log", $msg, FILE_APPEND);
        }
    }
}

?>
