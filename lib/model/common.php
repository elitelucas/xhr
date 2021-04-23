<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/14
 * Time: 16:18
 */
class CommonModel extends Model
{

    /**
     * 得到数据
     * @param string $filed 字段
     * @param mixed $where 条件
     * @param string $order 排序
     * @param string $limit 条数
     * @return array 查询数据
     */
    public function getlist($filed = '', $where = '', $order = '', $limit = '')
    {
        $filed = empty($filed) ? '*' : $filed;
        $order = empty($order) ? '' : $order;
        $sql = $this->db->c_sql($where, $filed, $this->table, $limit, $order);
//        echo $sql; exit;
        return $this->db->getall($sql);
    }

    /**
     * 得到单条数据
     * @param string $filed 字段
     * @param mixed $where 条件
     * @param string $order 排序
     * @return array 查询数据
     */
    public function getOneCoupon($field = '', $where = '', $order = '')
    {
        $sql = $this->db->c_sql($where, $field, $this->table, '', $order);
        //echo $sql;exit;
        return $this->db->getone($sql);
    }


    /**
     * 得到单条数据
     * @param $sql sql 语句
     * @return mixed 查询数据
     */
    public function getOneData($sql)
    {
        return $this->db->getone($sql);
    }

    /**
     * 更新数据
     * @param string $filed 字段
     * @param mixed $where 条件
     * @param string $order 排序
     * @return array 查询数据
     */
    public function save($data, $where)
    {
        return $this->db->update($this->table, $data, $where);
    }

    /**
     * 插入数据
     * @param string $filed 字段
     * @param mixed $where 条件
     * @param string $order 排序
     * @return array 查询数据
     */
    public function add($data)
    {
        return $this->db->insert($this->table, $data);
    }

    //团队集合ID  包含自身
    public function teamListsLimit($userId, $limit)
    {
        $sql = "SELECT user_id AS id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},%' $limit";
        $res = O('model')->db->getAll($sql);
        $self = array('id' => $userId);
        if (empty($res)) {
            return array($self);
        } else {
//            array_push($res, $self);
            return $res;
        }
    }

    //团队集合ID  包含自身
    public function teamLists($userId)
    {
        $sql = "SELECT user_id AS id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},%' ";
        $res = O('model')->db->getAll($sql);
        $self = array('id' => $userId);
        if (empty($res)) {
            return array($self);
        } else {
            array_push($res, $self);
            return $res;
        }
    }

    //直属会员    包括自己
    public function leaguer($userId)
    {
        $sql = "SELECT user_id AS id FROM `un_user_tree` WHERE `pids` LIKE '%,{$userId},'";
        $res = O('model')->db->getAll($sql);
        $self = array('id' => $userId);
        if (empty($res)) {

            return array($self);
        } else {
            array_push($res, $self);

            return $res;
        }
    }

    /**
     * 插入流水记录表数据
     * @param string $uid 字段
     * @param array $data 条件
     * @return array 查询数据
     */
    public function aadAccountLog($data)
    {
        $sql = "SELECT reg_type FROM `un_user` WHERE `id` = {$data['user_id']}";

        $reg_type = $this->db->result($sql);
        $data['reg_type'] = $reg_type;
        return $this->db->insert("un_account_log", $data);
    }

    /**
     * 交易类型
     * @return json
     */
    public function getTrade()
    {
        //初始化redis
        $redis = initCacheRedis();
        $LTrade = $redis->lRange('DictionaryIds2', 0, -1);
        $tranType = array();
        foreach ($LTrade as $v) {
            $res = $redis->hMGet("Dictionary2:" . $v, array('id', 'name'));
            $tranType[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return array('tranTypeIds' => $LTrade, 'tranType' => $tranType);
    }

    /**
     * 会员组
     * @return array
     */
    public function getGroup()
    {
        //初始化redis
        $redis = initCacheRedis();
        $LGroup = $redis->lRange('groupIds', 0, -1);
        $group = array();
        foreach ($LGroup as $v) {
            $group[] = $redis->hMGet("group:" . $v, array('id', 'name'));
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $group;
    }

    /**
     * 交易记录
     * @param $start_date string  起始日期
     * @param $end_date string  结束日期
     * @param $uids string  用户
     * @param $type array  类型
     * @return array
     */
    public function getTradeLog($start_date, $end_date, $type = array(), $uids = "")
    {
        $start_time = strtotime($start_date . " 00:00:00");
        $end_time = strtotime($end_date . " 23:59:59");

        //交易流水金额
        $trades = array();
        foreach ($type as $v) {
            $trades[$v] = 0;
        }

        $users = "";
        if ($uids != "" && $uids != 0) {
            $users = " AND user_id IN({$uids}) ";
        } elseif ($uids === 0) {
            return $trades;
        }

        //今天实时数据
        if ($end_time >= time()) {
            $start_time1 = strtotime(date("Y-m-d 00:00:00"));
            $end_time1 = strtotime(date("Y-m-d 23:59:59"));

            //交易流水金额
            foreach ($type as $v) {
                $sql = "SELECT IFNULL(SUM(money),0) FROM un_account_log WHERE addtime BETWEEN {$start_time1} and {$end_time1} AND `type` = {$v} {$users} AND reg_type NOT IN (0,8,9,11)";
                $tradeLog = O('model')->db->result($sql);
                $trades[$v] += $tradeLog;
            }
        }

        //历史数据
        if ($start_time < strtotime(date("Y-m-d 00:00:00"))) {
            //交易流水金额
            foreach ($type as $v) {
                $sql = "SELECT IFNULL(SUM(money),0) FROM `un_daily_flow` WHERE `addtime`  BETWEEN '{$start_date}' and '{$end_date}' AND `type` = {$v} {$users}";
                //echo $sql;
                $tradeLog = O('model')->db->result($sql);
                $trades[$v] += $tradeLog;
            }
        }
        return $trades;
    }

    /**
     * 会员层级
     * @return array
     */
    public function getUserLayer()
    {
        //初始化redis
        $redis = initCacheRedis();
        $LLayer = $redis->lRange('LayerIds', 0, -1);
        $layer = array();
        foreach ($LLayer as $v) {
            $res = $redis->hGetAll("Layer:" . $v);
            $layer[$res['layer']] = $res;
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $layer;
    }

    /**
     * 获取公告
     * @return array
     */
    public function getMessage()
    {
        //初始化redis
        $redis = initCacheRedis();
        $LLayer = $redis->lRange('SysMessageIds', 0, -1);
        $layer = array();
        foreach ($LLayer as $v) {
            $res = $redis->hGetAll("SysMessage:" . $v);
            $layer[$res['id']] = $res;
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $layer;
    }

    /**
     * 默认会员层级
     * @return array
     */
    public function getDefaultLayer()
    {
        $sql = "SELECT layer FROM `un_user_layer` ORDER BY `id`";
        $layer = O("model")->db->result($sql);
        $layer = $layer ? $layer : 0;
        return $layer;
    }

    /**
     * 配置参数
     * @param $k
     * @return $config array
     */
    public function getConfig($k, $value = '')
    {
        //初始化redis
        $redis = initCacheRedis();
        if (empty($value)) {
            $config = $redis->hGetAll("Config:" . $k);
        } else {
            if (is_array($value)) {
                $config = $redis->hMGet("Config:" . $k, $value);
            } else {
                $config = $redis->hGet("Config:" . $k, $value);
            }
        }

        //关闭redis链接
        deinitCacheRedis($redis);
        return $config;
    }

    /**
     * 玩法配置参数
     * @param $k
     * @return $config array
     */
    public function getWay($k)
    {
        //初始化redis
        $redis = initCacheRedis();
        $way = $redis->Get("way" . $k);
        $way = json_decode($way, true);
        //关闭redis链接
        deinitCacheRedis($redis);
        return $way;
    }

    /**
     * 获取用户荣誉信息
     * @param $userId   User id
     * @return array
     */
    public function getHonorLevel($userId)
    {
        $config = $this->getConfig('is_show_honor');
        $sql = "select (honor_score - lose_score) AS current_score from un_user where id=$userId";
        $score = $this->db->result($sql);
        $current_score = $score < 0 ? 0 : $score;
        $honor = $this->db->getone("select name,icon,status,score,num from un_honor where score<=$score order by score desc");
        $honor['status'] = $config['value'] ? $honor['status'] : 0;
        $honor['current_score'] = $current_score;
        return $honor;

    }

    /**
     * 获取sql
     * @param $table  表名
     * @param $data  数据
     * @return string
     */
    public function insert($table, $data = array())
    {
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
        return $sql;
    }

    //私有处理表名
    private static function deal_field($str = '')
    {
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
    public static function deal_value($str = '')
    {
        if (is_array($str)) {
            $str = array_map(array(__class__, __method__), $str);
            return $str;
        }
        $str = "'{$str}'";
        return $str;
    }

    /**
     * 获取缓存信息
     */
    public function getRedisHashValues($key, $value = '')
    {
        //初始化redis
        $redis = initCacheRedis();
        if (empty($value)) {
            $res = $redis->hGetAll($key);
        } else {
            if (is_array($value)) {
                $res = $redis->hMGet($key, $value);
            } else {
                $res = $redis->hGet($key, $value);
            }
        }
        //关闭redis链接
        deinitCacheRedis($redis);

        return $res;
    }

    /**
     * 会员层级
     * @return array
     */
    public function getRooms()
    {
        //初始化redis
        $redis = initCacheRedis();
        $LRoom = $redis->lRange('allroomIds', 0, -1);
        $rooms = array();
        foreach ($LRoom as $v) {
            $res = $redis->hGetAll("allroom:" . $v);
            $rooms[$res['id']] = $res;
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $rooms;
    }

    /**
     * 查询提示音
     * @return bool|mixed|void
     */
    public function getMusicTips($record_id, $type)
    {
        $sql = "SELECT id, record_id, `type`, tip, url, click_uid, click_status, click_time, `status`, `time`, uids, msg FROM un_music_tips WHERE record_id = '{$record_id}' AND type IN({$type})";
        return $this->db->getone($sql);
    }

    /**
     * 添加提示音到un_music_tips表
     * @return bool|mixed|void
     */
    public function setMusicTips($data)
    {
        $res = $this->db->insert('un_music_tips', $data);
        return $res;
    }

    /**
     * 添加充值提示音
     * @return bool|mixed|void
     */
    public function setRechargeMusic($map)
    {
        if (empty($map)) return false;
        $data = array();
        if ($map['type'] == 1) {
            $recType = '线下';
            $data['tip'] = 'Offline deposit';
            $data['url'] = '?m=admin&c=finance&a=charge';
        } else {
            $recType = '线上';
            $data['tip'] = 'Online deposit';
            $data['url'] = '?m=admin&c=topup&a=topup';
        }
        //$sql = "SELECT COUNT(*) FROM `un_account_recharge` WHERE `user_id` = '{$map['user_id']}' AND `status` IN ('1','0') ";
        $sql = "SELECT COUNT(*) FROM `un_account_recharge` WHERE `user_id` = '{$map['user_id']}' AND `status` = '1' ";
        lg('get_music_ips', '充值SQL::' . $sql);
        $cnt = $this->db->result($sql);
        lg('get_music_ips', '充值SQL:判断是不是首充::' . $cnt);
        $data['record_id'] = $map['id'];

        $data['time'] = time();

        if (!$cnt) {
            //首充
            $data['msg'] = "The first charge needs to be reviewed";
            $data['type'] = 5;
            $data['remark'] = "User id: {$map['user_id']}, First {$recType} deposit, deposit amount: {$map['money']}";
            $this->setMusicTips($data);
        } else {
            //充值
            $data['msg'] = "There is a new recharge that needs to be reviewed";
            $data['type'] = 2;
            $data['remark'] = "User id: {$map['user_id']}, {$recType} deposit, deposit amount: {$map['money']}";
            $this->setMusicTips($data);
        }
    }

    /**
     * 添加提现提示音
     * @return bool|mixed|void
     */
    public function setCashMusic($map)
    {
        if (empty($map)) return false;
        $data = array();
        $data['tip'] = 'Withdrawal management';
        $data['url'] = '?m=admin&c=finance&a=drawal';
        $sql = "SELECT COUNT(*) FROM `un_account_cash` WHERE `user_id` = '{$map['user_id']}' AND `status` IN ('1','0') ";
        lg('get_music_ips', '提现SQL::' . $sql);
        $cnt = $this->db->result($sql);
        lg('get_music_ips', '提现SQL:判断是不是首提::' . encode($cnt));
        $data['record_id'] = $map['id'];

        $data['time'] = time();

        if ($cnt == 1) {
            //首提
            $data['msg'] = "The first mention needs to be reviewed";
            $data['type'] = 6;
            $data['remark'] = "User id: {$map['user_id']}, first withdrawl, withdrawal amount: {$map['money']}";
            $this->setMusicTips($data);
        } else {
            //提现
            $data['msg'] = "There are new withdrawals that need to be reviewed";
            $data['type'] = 1;
            $data['remark'] = "User id: {$map['user_id']}, withdrawal, withdrawal amount: {$map['money']}";
            $this->setMusicTips($data);
        }
    }

    /**
     * 得到数据
     * @param string $filed 字段
     * @param array $where 条件
     * @param string $order 排序
     * @param string $limit 条数
     * @param string $table 表名
     * @return array 查询数据
     */
    public function getListNew($filed = '', $where = '', $order = '', $limit = '', $table)
    {
        $filed = empty($filed) ? '*' : $filed;
        $order = empty($order) ? '' : $order;
        $sql = $this->db->c_sql($where, $filed, $table, $limit, $order);
        //echo $sql; exit;
        return $this->db->getall($sql);
    }

    /**
     * 得到记录总数
     * @param array $where 条件
     * @param string $table 表名
     * @return array 查询数据
     */
    public function getCount($table, $where = [])
    {
        $where = $this->deal_where($where);
        if (empty($where)) {
            $rows = $this->db->getone("select count(*) as num from {$table}");
        } else {
            $rows = $this->db->getone("select count(*) as num from {$table} where {$where}");
        }
        return $rows['num'];
    }

    //私有处理where语句，参加例如：array("a='b'" , "a = 'c'",'_logic'=>'OR');。也可直接写
    private static function deal_where($where)
    {
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
     * 得到单条数据
     * @param string $filed 字段
     * @param mixed $where 条件
     * @param string $order 排序
     * @param string $table 表名
     * @return array 查询数据
     */
    public function getOneCouponNew($field = '', $where = '', $order = '', $table)
    {
        $sql = $this->db->c_sql($where, $field, $table, '', $order);
        //echo $sql;exit;
        return $this->db->getone($sql);
    }

    /**
     * 判断是否分享注册充值，若是，进行分享返现操作
     * @param int $userId 充值User id
     * @return bool true|false
     */
    public function shareRebate($userId,$amount)
    {
        $model = O('model');
        //判断用户是否为分享注册首充
        $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $userId));
        if (empty($shareIdArr['share_id'])) {
            return true;
        }

        //判断用户是否首充
        $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $userId));
        if (!empty($rechargeRecord)) {
            return true;
        }

        //初始化redis
        $redis = initCacheRedis();
        $fsConfig = $redis->HMGet("Config:cashBack", array('value'));
        //关闭redis链接
        deinitCacheRedis($redis);

//        $sql = "SELECT * FROM un_account WHERE user_id = '{$shareIdArr['share_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
        if(!empty(C('db_port'))){ //使用mycat时 查主库数据
            $sql="/*#mycat:db_type=master*/ SELECT * FROM un_account WHERE user_id = '{$shareIdArr['share_id']}' LIMIT 1 FOR UPDATE";
        }else{
            $sql="SELECT * FROM un_account WHERE user_id = '{$shareIdArr['share_id']}' LIMIT 1 FOR UPDATE";
        }
        $accountInfo = O('model')->db->getone($sql);
        //扫码返现百分率
        $cashback_rate = 0;
        $cashback_list = json_decode($fsConfig['value'],true);
        foreach ($cashback_list as $k=>$i){
            if($i["low"]<=$amount&&$amount<=$i["upper"]){
                $cashback_rate = $i["rate"];
            }
        }
        $cashback_amount = bcdiv(($cashback_rate*$amount),100,2);
        $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

        //生成账户流水
        $logArr = array(
            'user_id' => $shareIdArr['share_id'],
            'order_num' => "JL" . date("YmdHis") . rand(100, 999),
            'type' => 66,
            'money' => $cashback_amount,
            'use_money' => $money,
            'remark' => 'User id:' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
            'verify' => 1,
            'addtime' => SYS_TIME,
            'addip' => ip(),
        );

        $logId = D('accountlog')->aadAccountLog($logArr);
        //更新用户账户金额
        $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));

        //产生充值流水
        $logId = D('accountlog')->aadAccountLog($logArr);
        //更新用户账户金额
        $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));

        if ($logId && $res) {
            return true;
        } else {
            return false;
        }
    }
}
