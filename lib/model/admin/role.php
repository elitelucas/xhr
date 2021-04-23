<?php

/**
 * 用户表model
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class RoleModel extends CommonModel {

    protected $table = "#@_admin_role";
    protected $table1 = "#@_menu";
    protected $table2 = "#@_config";

    private $admin;

    public function setAdminUser($info) {
        $this->admin = $info;
    }

    //历史版本
    public function listVersion($where) {
        $sql = "select * from un_version where type = {$where['type']} order by addtime desc limit {$where['page_start']},{$where['page_size']} ";
//        dump($sql);
        return $this->db->getall($sql);
    }

    //历史版本总数
    public function cntVersion($where) {
        $rt = $this->db->getone("select count(*) as cnt from un_version where type = {$where['type']}");
        return $rt['cnt'];
    }

    //新增历史版本
    public function addVersion($data) {
        return $this->db->insert("un_version", $data);
    }

    //获取角色列表
    public function getRole() {
        return $this->db->getall("select * from " . $this->table);
    }

    //更新角色权限
    public function upRoleAuth($rId, $auth) {
        $json = json_encode(explode(",", $auth));
        return $this->db->update($this->table, array("power_config" => $json), array("roleid" => $rId));
    }

    //根据角色ID  获取权限
    public function roleAuth($roleid) {
        return $this->db->getone("select power_config from " . $this->table . " where roleid = $roleid");
    }

    //菜单列表
    public function menuList() {
        return $this->db->getall("select * from " . $this->table1 . " where display = 1");
    }

    //添加角色
    public function add_role($data) {
        $table = 'un_admin_role';
        return $this->db->insert($table, $data);
    }

    //删除角色

    public function del_role($id) {
        $where = "roleid={$id}";
        $table = 'un_admin_role';
        return$this->db->delete($table, $where);
    }

    //修改角色

    public function update_role($roleid) {

        $sql = "select * from un_admin_role where roleid={$roleid}";
        return $this->db->getone($sql);
    }

    public function update_role_ok($data) {

        $table = 'un_admin_role';
        $id = $data['roleid'];
        $where = "roleid =$id";
        return $this->db->update($table, $data, $where, $add = '');
    }

    //管理员
    public function getAdmin() {
        $sql = " select a.*,b.rolename groupName from un_admin a left join un_admin_role b on a.roleid = b.roleid";
        return $this->db->getall($sql);
    }

    //添加管理员

    public function add_admin($data) {
        $table = "un_admin";
        $rows = $this->db->getone("select username from $table where username = '".$data['username']."'");
        if(empty($rows)) {
            $arr['code'] = 0;
            $arr['msg'] = $this->db->insert($table, $data);
        } else {
            $arr['code'] = -1;
            $arr['msg'] = "管理员名称已存在";
        }
        return $arr;
    }

    //拉黑管理员

    public function update_dis($userid, $disabled) {

        if ($disabled == 0) {

            $table = 'un_admin';
            $where = "userid =$userid";
            $data = "disabled=1";
        } else {

            $table = 'un_admin';
            $where = "userid =$userid";
            $data = "disabled=0";
        }


        $res = $this->db->update($table, $data, $where, $add = '');
        $this->db->delete("un_session",['user_id'=>$userid,"is_admin"=>1]);

        return $res;
    }

    //删除管理员

    public function del_admin($userid) {

        $where = "userid={$userid}";
        $table = 'un_admin';
        return$this->db->delete($table, $where);
    }

    //修改管理员
    public function update_admin($userid) {

        $sql = "select * from un_admin where userid ={$userid}";

        return $this->db->getone($sql);
    }

    public function update_admin_ok($data) {
        return $this->db->update('un_admin', $data, array("userid" => $data['userid']));
    }

    //校验admin的密码是否OK
    public function checkAdminPwd($pwd) {
        $pwdMd5 = md5($pwd);
        $rt = $this->db->getone("select * from un_admin where userid=1 and password='{$pwdMd5}'");
        return empty($rt) ? false : true;
    }

    //日志查询
    public function log_check($offer, $pagesize) {

        $sql = "SELECT A.*,B.username,C.name FROM(
              select user_id,type,remark,addip,addtime time from un_account_log
              union
              select user_id,type,content,loginip,logintime from un_admin_log
              union
              select user_id,leibieid,neirong,ip,shtime from un_xitongshenghe
           ) A  LEFT JOIN un_user B ON A.user_id = B.id  LEFT JOIN  un_dictionary C ON A.type=C.id WHERE A.type =10 OR  A.type=11 OR  A.type=32 OR A.type=33 OR  A.type=34
           limit $offer,$pagesize";

        return $this->db->getall($sql);
    }

    //获取日志的记录数
    public function log_check_count() {

        $sql = "SELECT A.*,B.username,C.name FROM(
              select user_id,type,remark,addip,addtime time from un_account_log
              union
              select user_id,type,content,loginip,logintime from un_admin_log
              union
              select user_id,leibieid,neirong,ip,shtime from un_xitongshenghe
           ) A  LEFT JOIN un_user B ON A.user_id = B.id  LEFT JOIN  un_dictionary C ON A.type=C.id WHERE A.type =10 OR  A.type=11 OR  A.type=32 OR A.type=33 OR  A.type=34
";

        return count($this->db->getall($sql));
    }

    //日志搜索
    public function log_sousuo($arr, $offer, $pagesize) {
        $user_id = $this->getAdminId($arr['username']);
        $min_time = strtotime($arr['min_time']);
        if(!empty($arr['max_time'])) {
            $max_time = strtotime($arr['max_time'])+86400;
        }
        $where1 = "";
        $where2 = "";
        $where3 = "";
        $where4 = "";
        if ($arr['type'] != '') {
            $where1 .= " and type={$arr['type']}";
            $where2 .= " and type={$arr['type']}";
            $where3 .= " and leibieid={$arr['type']}";
            $where4 .= " and type={$arr['type']}";
        }
        if ($user_id >0) {
            $where1 .= " and verify={$user_id}";
            $where2 .= " and user_id={$user_id}";
            $where3 .= " and user_id={$user_id}";
            $where4 .= " and user_id={$user_id}";
        }
        if ($min_time != '') {
            $where1 .= " and addtime>{$min_time}";
            $where2 .= " and logintime>{$min_time}";
            $where3 .= " and sqtime>{$min_time}";
            $where4 .= " and addtime>{$min_time}";
        }
        if ($max_time != '') {
            $where1 .= " and addtime<{$max_time}";
            $where2 .= " and logintime<{$max_time}";
            $where3 .= " and sqtime<{$max_time}";
            $where4 .= " and addtime<{$max_time}";
        }
        $sql = "SELECT a.*,a.username FROM (SELECT verify,type,remark,addip,addtime AS time,'shenheren' AS username,
                    CONCAT(user_id,'@user') AS check_id_str FROM un_account_log WHERE type in(10,11,32) $where1
                    UNION ALL 
                SELECT user_id,type,content,loginip,logintime,'shenheren' AS username,
                    CONCAT(user_id,'@admin') AS check_id_str FROM un_admin_log WHERE 1=1 $where2
                    UNION ALL 
                SELECT user_id,leibieid AS type,neirong,ip AS addip,shtime,shenheren AS username,
                    CONCAT(user_id,'@shenhe') AS check_id_str FROM un_xitongshenghe WHERE 1=1 $where3
                    UNION ALL 
                SELECT user_id,type,remark,addip,addtime,'shenheren' AS username,CONCAT(user_id, '@admin') AS check_id_str FROM un_admin_operation_log where 1=1 $where4
                    ) a LEFT JOIN un_admin b ON a.verify = b.userid ORDER BY a.time DESC";
        $sql .= " LIMIT $offer,$pagesize";
        $dataAll = $this->db->getall($sql);
        return $dataAll;
    }

    public function log_sousuo_count($arr) {
        $user_id = $this->getAdminId($arr['username']);
        $min_time = strtotime($arr['min_time']);
        if(!empty($arr['max_time'])) {
            $max_time = strtotime($arr['max_time'])+86400;
        }
        $where1 = "";
        $where2 = "";
        $where3 = "";
        $where4 = "";
        if ($arr['type'] != '') {
            $where1 .= " and type={$arr['type']}";
            $where2 .= " and type={$arr['type']}";
            $where3 .= " and leibieid={$arr['type']}";
            $where4 .= " and type={$arr['type']}";
        }
        if ($user_id >0) {
            $where1 .= " and verify={$user_id}";
            $where2 .= " and user_id={$user_id}";
            $where3 .= " and user_id={$user_id}";
            $where4 .= " and user_id={$user_id}";
        }
        if ($min_time != '') {
            $where1 .= " and addtime>{$min_time}";
            $where2 .= " and logintime>{$min_time}";
            $where3 .= " and sqtime>{$min_time}";
            $where4 .= " and addtime>{$min_time}";
        }
        if ($max_time != '') {
            $where1 .= " and addtime<{$max_time}";
            $where2 .= " and logintime<{$max_time}";
            $where3 .= " and sqtime<{$max_time}";
            $where4 .= " and addtime<{$max_time}";
        }

        $sql = "(select count(*) as c from un_account_log where type in(10,11,32) $where1)
                 union all 
                 (select count(*) as c from un_admin_log where 1=1 $where2)
                 union all 
                 (select count(*) as c from un_xitongshenghe where 1=1 $where3)
                 union all 
                 (select count(*) as c from un_admin_operation_log where 1=1 $where4)";
        $rt = $this->db->getall($sql);
        return $rt[0]['c'] + $rt[1]['c'] + $rt[2]['c'] + $rt[3]['c'];
    }

    //发言设置

    public function speak() {

        $sql = "select * from un_config where id=13";
        $data1 = $this->db->getone($sql);
        $lower_money = $data1['value'];

        $sq = "select * from un_config where id=14";
        $data2 = $this->db->getone($sq);
        $lower_word = $data2['value'];

        $sql = "select * from un_config where `nid`='visitorLimit'";
        $data3 = $this->db->getone($sql);
        $visitor_limit = $data3['value'];

        $arr = array(
            'lower_money' => $lower_money,
            'lower_word' => $lower_word,
            'visitor_limit' => $visitor_limit,
        );

        return $arr;
    }

    public function speak_close($arr) {
        $table = 'un_config';
        $data  = '';
        $where = '';
        
        if (is_numeric($arr['lower_money'])) {
            $where = "nid = 'moneyLessNoSpeak'";
            $data  = "value = " . $arr['lower_money'];
            $ret1  = $this->db->update($table, $data, $where, $add = '');

        }
        
        if (is_numeric($arr['lower_word']) && ((int)$arr['lower_word'] == $arr['lower_word'])) {
            $where = "nid = 'speakWordsNumbers'";
            $data  = "value = " . $arr['lower_word'];
            $ret2  = $this->db->update($table, $data, $where, $add = '');
        }

        $value = $arr['visitor_limit'] == 1 ? 1 : 0;
        $where = "nid = 'visitorLimit'";
        $data  = "value = " . $value;
        $ret3  = $this->db->update($table, $data, $where, $add = '');
        
        return 1;
    }

    //获取单点投注的字符

    public function getRow() {

        $sql = "select * from un_dictionary where classid=7";

        return $this->db->getone($sql);
    }

    //设置单点投注的字符
    public function tz_set_ok($arr) {

        $table = 'un_dictionary';
        $where = "classid=7";
        $data = "value='{$arr}'";
        return $this->db->update($table, $data, $where, $add = '');
    }

    //系统审核

    public function sys_check($offer, $pagesize) {

        $sql = "select a.*,b.name from un_xitongshenghe a left join un_dictionary b on a.leibieid=b.id order by sqtime desc limit $offer,$pagesize";

        return $this->db->getall($sql);
    }

    public function sys_checkCount() {

        $sql = "select a.*,b.name from un_xitongshenghe a left join un_dictionary b on a.leibieid=b.id order by sqtime desc";

        return count($this->db->getall($sql));
    }

    //审核
    public function sys_check_ok($data) {
        //修改系统审核表的状态
        $status = $data['status'];
        $stat = $data['stat'];
        $leibieid = $data['leibieid'];
        $shenqingid = $data['shenqingid'];
        $order_num = $data['order_num'];
        $shenheren = $data['shenheren'];
        $operid = $data['operid'];
        //获取用户名
        $user_sql = $this->db->getone("select username from un_user where id={$data['user_id']}");
        $username = $user_sql['username'];
        $sql_sh = $this->db->getone("select status from un_xitongshenghe where id={$data['id']}");
        if ($sql_sh['status'] != 3) {
            return false;
        }

        //如果类别id为32代表系会员额度调整 33为信息审核
        //开启事物
        O('model')->db->query('BEGIN');
        try {
            if ($leibieid == 32) {

                $arr1 = array(
                    'beizhu' => $data['remark'],
                    'status' => $data['status'],
                    'shtime' => time(),
                    'shenheren' => $shenheren
                );

                $table1 = 'un_xitongshenghe';
                $where1 = "id={$data['id']}";

                $res_sh = $this->db->update($table1, $arr1, $where1, $add = '');
                if (!res_sh) {

                    throw new Exception();
                }

                if ($status == 1) {

                    //修改会员的总金额
                    $user_id = $data['user_id'];
                    $sql = "select money from un_account where user_id=$user_id LIMIT 1 FOR UPDATE";
                    $account = $this->db->getone($sql);
                    $account = $account['money'];
                    if ($stat == 1) {
                        $log_money = $data['account'];
                        $money = $data['account'] + $account;
                        $arr2 = array(
                            'money' => $money
                        );
                        $remark = "用户:{$username} 现金账户调整:+{$data['account']} ;调整前余额为{$account}";
                    } else {
                        $log_money = -$data['account'];
                        $money = $account - $data['account'];
                        $arr2 = array(
                            'money' => $money
                        );
                        $remark = "用户:{$username} 现金账户调整:-{$data['account']} ;调整前余额为{$account}";
                    }

                    //流水号
                    $data1 = array(
                        'user_id' => $data['user_id'],
                        'order_num' => $order_num,
                        'type' => 32,
                        'money' => $log_money,
                        'use_money' => $money,
                        'remark' => $remark,
                        'verify' => $operid,
                        'addip' => $_SERVER["SERVER_ADDR"],
                        'addtime' => time()
                    );
                    //var_dump($data1);exit;
                    //把资金调整写入日志
                    $tab1 = 'un_account_log';
                    $res_log = $this->db->insert($tab1, $data1);

                    if (!$res_log) {

                        throw new Exception();
                    }
                    //修改用户的总金额
                    $table2 = 'un_account';
                    $where2 = "user_id=$user_id";
                    $res_ac = $this->db->update($table2, $arr2, $where2, $add = '');

                    if (!$res_ac) {
                        throw new Exception();
                    }
                }
            } elseif ($leibieid == 33) {

                $arr1 = array(
                    'beizhu' => $data['remark'],
                    'status' => $data['status'],
                    'shtime' => time(),
                    'shenheren' => $shenheren,
                    'ip' => ip()
                );

                $table1 = 'un_xitongshenghe';
                $where1 = "id={$data['id']}";

                $res_sh = $this->db->update($table1, $arr1, $where1, $add = '');

                if (!$res_sh) {

                    throw new Exception();
                }

                //修改系统审核表的状态
                $arr2 = array();
                if ($status == 1) {
                    $arr2['state'] = 1;
                    $arr2['audit_status'] = 1;
                }else{
                    $arr2['state'] = 0;
                    $arr2['audit_status'] = 2;
                }
                //修改信息配置表的状态
                $table2 = "un_message_conf";
                $where2 = "id=$shenqingid";

                $res_xx = $this->db->update($table2, $arr2, $where2, $add = '');

                if (!$res_xx) {

                    throw new Exception();
                }
            }
            //提交事物
            O('model')->db->query('COMMIT');
            return true;
        } catch (Exception $e) {
            //回滚事物
            O('model')->db->query('ROLLBACK');

            return false;
        }
    }

    //获取游戏币的名称比列
    public function rmbname() {

        $sql = "select * from un_config where id=15";

        $data = $this->db->getone($sql);
        $value = $data['value'];

        $sq = "select * from un_config where id=16";
        $data1 = $this->db->getone($sq);
        $name = $data1['value'];
        $arr = array(
            'name' => $name,
            'value' => $value
        );

        return $arr;
    }

    public function edit($arr) {

        $id = $arr['id'];
        $name = $arr['minchen'];
        $bili = $arr['bili'];

        if ($id == 15) {
            $table = 'un_config';
            $data = "value='$bili'";
            $where = "id =$id";
            return $this->db->update($table, $data, $where, $add = '');
        } else {

            $table = 'un_config';
            $data = "value='$name'";
            $where = "id =$id";
            return $this->db->update($table, $data, $where, $add = '');
        }
    }

    //白名单

    public function whitelist() {

        $sql = "select * from un_whitelist order by id desc,addtime desc";

        return $this->db->getall($sql);
    }

    public function add_white($arr)
    {
        if(!checkIP($arr['ip'])) {
            return ;
        }
        $ipData = getIp($arr['ip']); //获取IP归属地
        
        $data = array(
            'ip' => $arr['ip'],
            'ip_attribution' => $ipData['attribution'],
            'status' => $arr['status'],
            'addtime' => time(),
            'beizhu' => $arr['beizhu']
        );

        $table = "un_whitelist";

        $res = $this->db->insert($table, $data);

        return $res;
    }

    //删除白名单
    public function del_white($id) {

        $where = "id={$id}";
        $table = 'un_whitelist';
        return $this->db->delete($table, $where);
    }

    public function up_switch($data, $where) {
        return $this->db->update($this->table2, $data, $where);
    }

    public function switch_card() {
        $sql = "select value from un_config where nid='100010'";
        $data = $this->db->getone($sql);
        return $data['value'];
    }

    //查询
    public function xiane() {
        $data1 = $this->getConfig('recharge');
        $data2 = $this->getConfig('cash');
        $data3 = $this->getConfig('recharge_time');
        $sql = "select value from un_config where nid = 'unauditAmount' ";
        $data4 = $this->db->getone($sql);
        $value = $data2['value'];
        $cash = json_decode($value, true);
        $cash_upper = $cash['cash_upper'];
        $cash_lower = $cash['cash_lower'];
        $arr = array(
            'recharge' => $data1['value'],
            'recharge_time' => $data3['value'],
            'cash_upper' => $cash_upper,
            'cash_lower' => $cash_lower,
            'unaudit' => $data4['value'],
        );

        return $arr;
    }

    //修改充值下限

    public function up_recharge($recharge) {

        $table = 'un_config';
        $data = "value=$recharge";
        $where = "nid='recharge'";
        return $this->db->update($table, $data, $where, $add = '');
    }

    public function up_recharge_time($recharge_time) {

        $table = 'un_config';
        $data = "value=$recharge_time";
        $where = "nid='recharge_time'";
        return $this->db->update($table, $data, $where, $add = '');
    }

    public function unauditWithdral($amount) {

        $table = 'un_config';
        $data = "value=$amount";
        $where = "nid='unauditAmount'";
        return $this->db->update($table, $data, $where, $add = '');
    }

    public function up_cash( $cash_upper, $cash_lower) {
            $arr = array(
                'cash_upper' => $cash_upper,
                'cash_lower' => $cash_lower
            );

            $value = json_encode($arr);

            $table = 'un_config';
            $data = "value='$value'";
            $where = "nid='cash'";
            return $this->db->update($table, $data, $where, $add = '');

    }

    public function up_cash_back($type, $money) {

        $sql = "select value from un_config where id=6";
        $data2 = $this->db->getone($sql);
        $value = $data2['value'];
        $cash = json_decode($value, true);
        $cash_upper_v = $cash['cash_upper'] ? $cash['cash_upper'] : 0;
        $cash_lower_v = $cash['cash_lower'] ? $cash['cash_lower'] : 0;
        if ($type == 1) {

            $arr = array(
                'cash_upper' => $money,
                'cash_lower' => $cash_lower_v
            );

            $value = json_encode($arr);

            $table = 'un_config';
            $data = "value='$value'";
            $where = "id=6";
            return $this->db->update($table, $data, $where, $add = '');
        } else {

            $arr = array(
                'cash_upper' => $cash_upper_v,
                'cash_lower' => $money
            );

            $value = json_encode($arr);

            $table = 'un_config';
            $data = "value='$value'";
            $where = "id=6";
            return $this->db->update($table, $data, $where, $add = '');
        }
    }

    //获取下注限额的数据
    public function general_note() {

        $sql = "select * from un_config where id=26";

        $data = $this->db->getone($sql);

        return $data;
    }

    //获取开奖时长
    public function kj_time() {
        $un_length = array();
        $redis  = initCacheRedis();
        $LotteryTypeIds = $redis->lRange('LotteryTypeIds',0,-1);
        foreach ($LotteryTypeIds as $k=>$v){
            $config = $redis->hget('LotteryType:'.$v,'config');
            $name = $redis->hget('LotteryType:'.$v,'name');
            $config = decode($config);
            $un_length[$v]['auto_open'] = $config['auto_open'];
            $un_length[$v]['name'] = $name;
            $un_length[$v]['id'] = $v;
        }
        deinitCacheRedis($redis);
        return $un_length;
    }

    //开奖时长设置
    public function up_length($arr) {
        $type = $arr['type'];
        $redis  = initCacheRedis();
        $ids = [];
        $sql = "UPDATE un_lottery_type SET config  = CASE id ";
        foreach ($arr as $key=>$val) {
            $config = $redis->hget('LotteryType:'.$val['id'],'config');
            $config = decode($config);
            $config['auto_open'] = $val['auto_open'];
            $config = encode($config);
            $sql .= "WHEN {$val['id']} THEN '".$config."' ";
            $ids[] = $val['id'];
        }
        deinitCacheRedis($redis);
        $ids = $ids = implode(',', $ids);
        $sql .= "END WHERE id IN ($ids)";
        return $this->db->query($sql);
    }

    //系统审核的搜索
    public function sys_check_search($arr, $offer, $pagesize) {
        $sql = "select a.*,b.name from un_xitongshenghe a left join un_dictionary b on a.leibieid=b.id where 1=1 ";
        //$sql2 = "select stat, account from un_xitongshenghe as a where status = 1 ";
        $leibieid = $arr['leibieid'];
        $faqiren = $arr['faqiren'];
        $neirong = $arr['neirong'];
        $min_time = strtotime($arr['min_time']);
        $max_time = strtotime($arr['max_time'] . " 23:59:59");

        if ($leibieid != '') {
            $sql .= " and a.leibieid={$leibieid}";
        }
        if ($faqiren != '') {
            $sql .= " and a.faqiren like '%{$faqiren}%'";
        }
        if ($neirong != '') {
            $sql .= " and a.neirong like '用户:{$neirong} 现金账户调整:%'";
        }
        if ($min_time != '') {
            $sql .= " and a.sqtime > $min_time";
        }
        if ($max_time != '') {
            $sql .= " and a.sqtime < $max_time";
        }
        $sql .= " order by a.sqtime desc limit $offer,$pagesize";
        return $this->db->getall($sql);
    }

    //获取搜索的记录数
    public function get_sys_count($arr) {
        $sql = "select count(*) as cnt from un_xitongshenghe as a where 1=1 ";
        $sql2 = "select stat, account from un_xitongshenghe as a where status = 1 ";

        $leibieid = $arr['leibieid'];
        $faqiren = $arr['faqiren'];
        $neirong = $arr['neirong'];
        $min_time = strtotime($arr['min_time']);
        $max_time = strtotime($arr['max_time'] . " 23:59:59");

        if ($leibieid != '') {
            $sql .= " and a.leibieid={$leibieid}";
            $sql2 .= " and a.leibieid={$leibieid}";
        }
        if ($neirong != '') {
            $sql .= " and a.neirong like '用户:{$neirong} 现金账户调整:%'";
            $sql2 .= " and a.neirong like '用户:{$neirong} 现金账户调整:%'";
        }
        if ($faqiren != '') {
            $sql .= " and a.faqiren like '%{$faqiren}%'";
            $sql2 .= " and a.faqiren like '%{$faqiren}%'";
        }
        if ($min_time != '') {
            $sql .= " and a.shtime > $min_time";
            $sql2 .= " and a.shtime > $min_time";
        }
        if ($max_time != '') {
            $sql .= " and a.shtime < $max_time";
            $sql2 .= " and a.shtime < $max_time";
        }

        $rt = $this->db->getone($sql);
        $res = $this->db->getAll($sql2);

        return array('cnt' => $rt['cnt'], 'total' => $res);
    }

    //修改白名单
    public function up_whitelist($id) {

        $sql = " select * from un_whitelist where id={$id}";

        return $this->db->getone($sql);
    }

    public function up_whitelist_ok($arr) {

        
        $table = 'un_whitelist';
        $id = $arr['id'];
        $where = "id =$id";
        
        if(!checkIP($arr['ip'])) {
            return ;
        }
        $ipData = getIp($arr['ip']); //获取IP归属地
        $arr['ip_attribution'] = $ipData['attribution'];

        return $this->db->update($table, $arr, $where, $add = '');
    }

    //大厅设置
    public function lobby() {
        return $this->db->getall("select nid,value from un_config where nid in (100001,100002,100003)"); //已为用户赚取元宝总数100001   回扣返水赚钱率100002 注册用户总数100003
    }

    //大厅设置
    public function dolobby($data, $where) {

        return $this->db->update('un_config', $data, $where);
    }

    //用户ID
    public function userId($username) {
        $rt = $this->db->getone("select id from un_user where username = '{$username}'");
        return empty($rt) ? -1 : $rt[0];
    }

    //用户ID
    public function getAdminId($username) {
        $rt = $this->db->getone("select userid from un_admin where username = '{$username}'");
        return empty($rt) ? -1 : $rt["userid"];
    }

    //用户name
    public function userName($uId) {
        $rt = $this->db->getone("select username from un_user where id = '{$uId}'");
        return $rt['username'];
    }

    //交易类型列表
    public function tranList() {
        $list = array(
            "32" => "会员额度调整",
            "33" => "信息审核",
            "34" => "登录",
            "10" => "充值",
            "11" => "提现",
            "12" => "银行卡管理",
            '40' => '返水设置',
            '50' => '开奖/补单/撤单',
            '60' => '生成期号',
            '70' => '赔率设置',
            '80' => '房间设置',
            '90' => '客服配置',
            '100' => '停售配置',
            '110' => '彩种设置',
            '120' => '活动设置',
            '130' => '支付方式设置',
            '140' => '公告设置',
        );
        return $list;
    }

    //跨级显示开关详情
    public function stage() {
        $rt = $this->db->getone("select value from " . $this->table2 . " where nid = 'stage'");
        return $rt['value'];
    }

    //跨级显示开关
    public function doStage($data, $where) {
        return $this->db->update($this->table2, $data, $where);
    }

    public function stopSellSet($data) {
        $tableName = "un_config";
        switch ($data['lottery_type']){
            case 1:
                $nid = 'xy28_stop_or_sell';
                break;
            case 2:
                $nid = 'bjpk10_stop_or_sell';
                break;
            case 3:
                $nid = 'jnd28_stop_or_sell';
                break;
            case 4:
                $nid = 'xyft_stop_or_sell';
                break;
            case 5:
                $nid = 'cqssc_stop_or_sell';
                break;
            case 6:
                $nid = 'sfc_stop_or_sell';
                break;
            case 7:
                $nid = 'lhc_stop_or_sell';
                break;
            case 8:
                $nid = 'jslhc_stop_or_sell';
                break;
            case 9:
                $nid = 'jssc_stop_or_sell';
                break;
            case 10:
                $nid = 'nn_stop_or_sell';
                break;
            case 11:
                $nid = 'ffc_stop_or_sell';
                break;
            case 12:
                $nid = 'sjb_stop_or_sell';
                break;
            case 13:
                $nid = 'tb_stop_or_sell';
                break;
            case 14:
                $nid = 'ffpk10_stop_or_sell';
                break;
            default:
                $arr['code'] = -1;
                $arr['msg'] = "操作失败";
                return $arr;
        }
        $sql = "select value from un_config where nid = '{$nid}'";
        $conSet = $this->db->getone($sql);
        $res = json_decode($conSet['value'],JSON_UNESCAPED_UNICODE);

        $getLotteryTypeSql = "SELECT id,`name` FROM un_lottery_type";
        $lottery_type_arr = $this->db->getall($getLotteryTypeSql);
        $lottery_type_arr = array_column($lottery_type_arr, 'name', 'id');

        $status_arr = [2 => '停售', 1 => '销售'];
        $log_remark = '停售信息设置调整--彩种:'.$lottery_type_arr[$data['lottery_type']];
        if(isset($data['title']) && $data['title'] != $res['title']) $log_remark .= '--提示语:'.$res['title'].'=>'.$data['title'];
        if(isset($data['status']) && $data['status'] != $res['status']) $log_remark .= '--状态:'.$status_arr[$res['status']].'=>'.$status_arr[$data['status']];

        $where['nid'] = $nid;
        //判断是否只修改状态
        if (empty($data['title'])) {
            $data['title'] = $res['title'];
        }
        $data['lottery_name'] = $res['lottery_name'];
        $tmp['value'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        $rows = $this->db->update($tableName, $tmp, $where);
        if ($rows === true || $rows > 0) {
            admin_operation_log($this->admin['userid'], 100, $log_remark);
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        } else {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    //
    /**
     * 获取机器人配置列表
     * @param $offer 起始条数
     * @param $pagesize 显示数量
     * @return mixed
     */
    public function getDummyConfList($offer, $pagesize){
        if(empty($offer) && empty($pagesize)) {
            return $this->db->getall("select * from un_person_config where type = 1 order by id desc");
        } else {
            return $this->db->getall("select * from un_person_config where type = 1 order by id desc limit $offer,$pagesize ");
        }

    }

    /**
     * 获取机器人数量
     */
    public function getDummyConfCount($where=array()){

        return $this->db->result("select COUNT(id) from un_person_config where type = 1");

    }


    //机器人添加配置
    public function addDummyConf($tmp)
    {
        if(json_decode(str_replace("\\","",$tmp['num']['data']),true) != null)
        {
            $tmp['num']['data'] = json_decode(str_replace("\\","",$tmp['num']['data']),true);
        }
        if(json_decode(str_replace("\\","",$tmp['money']['data']),true) != null)
        {
            $tmp['money']['data'] = json_decode(str_replace("\\","",$tmp['money']['data']),true);
        }
        $conf['state'] = $tmp['status'];
        $conf['type'] = 1;
        if(isset($tmp['type'])){
            $conf['type'] = $tmp['type'];
            unset($tmp['type']);
        }
        unset($tmp['status']);
        $conf['value'] = json_encode($tmp);
        $this->db->query('BEGIN');//开启事务
        if(empty($tmp['id']))
        {
            $rows = $this->db->insert("un_person_config",$conf);
        }
        else
        {
            $rows = $this->db->update("un_person_config",$conf,['id'=>$tmp['id']]);
            $this->db->delete("un_role",['conf_id'=>$tmp['id']]);
            $this->db->delete("un_bet_list",['conf_id'=>$tmp['id']]);
        }

        $check = true;
        if ($rows > 0) {
            foreach($tmp['ids'] as $val)
            {
                $role['user_id'] = $val['id'];
                if(empty($tmp['id']))
                {
                    $role['conf_id'] = $rows;
                }
                else
                {
                    $role['conf_id'] = $tmp['id'];
                }

                $rows1 = $this->db->insert("un_role",$role);
                if($rows1 < 0)
                {
                    $check = false;
                }
            }
            if($check === false && $rows < 0)
            {
                $this->db->query('ROLLBACK');//事务回滚
                $arr['code'] = -1;
                $arr['msg'] = "操作失败";
            }
            else
            {
                $this->db->query('COMMIT');//提交事务
                $arr['code'] = 0;
                $arr['msg'] = "操作成功";
            }
        } else {
            $this->db->query('ROLLBACK');//事务回滚
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    public function getBetStartMoney($start_money)
    {
        if($start_money > 5) {//判断下注金额是否大于5，则取当前值最接近5的倍数的值
            $startNum = $start_money%5;
            if($startNum == 0){
                $startNum = $start_money;
            }else{
                $startNum = 5- $startNum + $start_money;
            }
        } else if($start_money == 5) {//下注金额是否等于5，则取当前值
            $startNum = $start_money;
        } else {//如果小于5，则取5
            $startNum = 5;
        }
        return $startNum;
    }

    /*该方法移动到person中的PHP文件中
    public function addMsgList($conf_id = "")
    {
        $check = true;

        $this->db->delete("un_bet_list",['conf_id'=>$conf_id]);
        $rows = $this->db->getone("select * from un_person_config where id = $conf_id and state = 1");

        if(!empty($rows)) {
            $config = json_decode($rows['value'],true);
            $bet['conf_id'] = $rows['id'];
            $bet['room_id'] = $config['room'];
            $bet['lottery_type'] = $config['lottery_type'];
            $bet['spacing'] = 0;

            $wayArr = null;
            if($bet['lottery_type']!=2) $wayArr = $this->getWayInfo();//获取玩法
            else $wayArr = $this->getPK10WayInfo();

            //跨天处理
            if ($config['startTime'] > $config['endTime']){
                $startTime = strtotime('today') + $config['startTime']*3600;
                $endTime =  strtotime('today +1 day') + $config['endTime']*3600;
            } else {
                $startTime = strtotime('today') + $config['startTime']*3600;
                $endTime =  strtotime('today') + $config['endTime']*3600;
            }

            $timeArr = range($startTime,$endTime);//下注时间集合

//            $this->db->query('BEGIN');//开启事务
            if($config['num']['type'] == 1) {
                $count = $config['num']['data']/count($config['ids']);
                for($a=1;$a<=$count;$a++) {
                    shuffle($timeArr);
                    if($config['money']['type'] == 1) {
                        foreach($config['ids'] as $v) {
                            $moneyArr = [];
                            if($config['multiple'] == 1) {//判断下注金额是否开启5的倍数
                                $startNum = $this->getBetStartMoney($config['money']['data']['start_money']);
                                if($config['money']['data']['start_money'] == $config['money']['data']['end_money'] || $config['money']['data']['end_money'] < $startNum) {
                                    for($x=$startNum;$x<=$this->getBetStartMoney($config['money']['data']['start_money'])+5;$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                } else if($config['money']['data']['end_money'] < 5){
                                    $moneyArr[] = 5;
                                } else {
                                    for($x=$startNum;$x<=$config['money']['data']['end_money'];$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                }
                            } else {
                                $moneyArr = range($config['money']['data']['start_money'],$config['money']['data']['end_money']);
                            }
                            shuffle($moneyArr);
                            $key = array_rand($timeArr,1);
                            $g = $this->get_rand($config['way']);//确定玩法类型
                            shuffle($wayArr[$g]);
                            $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $v['id'];
                            $bet['avatar'] = $v['avatar'];
                            $bet['username'] = $v['username'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $rows = $this->db->insert("un_bet_list",$bet);
//                            if($rows < 0) {
//                                $check = false;
//                            }
                            unset($timeArr[$key]);
                        }
                    } else {
                        foreach($config['money']['data'] as $v) {
                            $moneyArr = [];
                            if($config['multiple'] == 1) {
                                $startNum = $this->getBetStartMoney($v['money_start']);
                                if($v['money_start'] == $v['money_end'] || $v['money_end'] < $startNum) {
                                    for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                } else if ($v['money_end'] < 5){
                                    $moneyArr[] = 5;
                                } else {
                                    for($x=$startNum;$x<=$v['money_end'];$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                }
                            } else {
                                $moneyArr = range($v['money_start'],$v['money_end']);
                            }
                            shuffle($moneyArr);
                            $key = array_rand($timeArr,1);
                            $g = $this->get_rand($config['way']);//确定玩法类型
                            shuffle($wayArr[$g]);
                            $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $v['id'];
                            $bet['username'] = $v['username'];
                            $bet['avatar'] = $v['avatar'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $rows = $this->db->insert("un_bet_list",$bet);
//                            if($rows < 0) {
//                                $check = false;
//                            }
                            unset($timeArr[$key]);
                        }
                    }
                }
            } else if($config['num']['type'] == 2) {
                $count = $config['num']['data'];
                for($a=1;$a<=$count;$a++) {
                    shuffle($timeArr);
                    if($config['money']['type'] == 1) {
                        foreach($config['ids'] as $v) {
                            $moneyArr = [];
                            if($config['multiple'] == 1) {
                                $startNum = $this->getBetStartMoney($config['money']['data']['start_money']);
                                if($config['money']['data']['start_money'] == $config['money']['data']['end_money'] || $config['money']['data']['end_money'] < $startNum){
                                    for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                } else if ($config['money']['data']['end_money'] < 5){
                                    $moneyArr[] = 5;
                                }else{
                                    for($x=$startNum;$x<=$config['money']['data']['end_money'];$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                }
                            } else {
                                $moneyArr = range($config['money']['data']['start_money'],$config['money']['data']['end_money']);
                            }
                            shuffle($moneyArr);
                            $key = array_rand($timeArr,1);
                            $g = $this->get_rand($config['way']);//确定玩法类型
                            shuffle($wayArr[$g]);
                            $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $v['id'];
                            $bet['avatar'] = $v['avatar'];
                            $bet['username'] = $v['username'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $rows = $this->db->insert("un_bet_list",$bet);
//                            if($rows < 0) {
//                                $check = false;
//                            }
                            unset($timeArr[$key]);
                        }
                    } else {
                        foreach($config['money']['data'] as $v) {
                            $moneyArr = [];
                            if($config['multiple'] == 1) {
                                $startNum = $this->getBetStartMoney($v['money_start']);
                                if($v['money_start'] == $v['money_end'] || $v['money_end'] < $startNum){
                                    for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                } else if($v['money_end'] < 5){
                                    $moneyArr[] = 5;
                                }else{
                                    for($x=$startNum;$x<=$v['money_end'];$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                }
                            } else {
                                $moneyArr = range($v['money_start'],$v['money_end']);
                            }
                            shuffle($moneyArr);
                            $key = array_rand($timeArr,1);
                            $g = $this->get_rand($config['way']);//确定玩法类型
                            shuffle($wayArr[$g]);
                            $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $v['id'];
                            $bet['username'] = $v['username'];
                            $bet['avatar'] = $v['avatar'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $rows = $this->db->insert("un_bet_list",$bet);
//                            if($rows < 0) {
//                                $check = false;
//                            }
                            unset($timeArr[$key]);
                        }
                    }
                }
            } else if($config['num']['type'] == 3) {
                foreach($config['num']['data'] as $keys=>$val) {
                    $count = $val['num'];
                    for($a=1;$a<=$count;$a++) {
                        $moneyArr = [];
                        shuffle($timeArr);
                        if($config['money']['type'] == 1) {
                            if($config['multiple'] == 1) {
                                $startNum = $this->getBetStartMoney($config['money']['data']['start_money']);
                                if($config['money']['data']['start_money'] == $config['money']['data']['end_money'] || $config['money']['data']['end_money'] < $startNum){
                                    for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                } else if($config['money']['data']['end_money'] < 5) {
                                    $moneyArr[] = 5;
                                }else{
                                    for($x=$startNum;$x<=$config['money']['data']['end_money'];$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                }
                            } else {
                                $moneyArr = range($config['money']['data']['start_money'],$config['money']['data']['end_money']);
                            }
                            shuffle($moneyArr);
                            $key = array_rand($timeArr,1);
                            $g = $this->get_rand($config['way']);//确定玩法类型
                            shuffle($wayArr[$g]);
                            $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $val['id'];
                            $bet['username'] = $val['username'];
                            $bet['avatar'] = $val['avatar'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $rows = $this->db->insert("un_bet_list",$bet);
//                            if($rows < 0) {
//                                $check = false;
//                            }
                            unset($timeArr[$key]);
                        } else {
                            if($config['multiple'] == 1) {
                                $startNum = $this->getBetStartMoney($config['money']['data'][$keys]['money_start']);
                                if($config['money']['data'][$keys]['money_start'] == $config['money']['data'][$keys]['money_end'] || $config['money']['data'][$keys]['money_end'] < $startNum){
                                    for($x=$startNum;$x<=$startNum+5;$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                }else if($config['money']['data'][$keys]['money_end'] < 5){
                                    $moneyArr[] = 5;
                                }else{
                                    for($x=$startNum;$x<=$config['money']['data'][$keys]['money_end'];$x+=5) {
                                        $moneyArr[] = $x;
                                    }
                                }
                            } else {
                                $moneyArr = range($config['money']['data'][$keys]['money_start'],$config['money']['data'][$keys]['money_end']);
                            }
                            shuffle($moneyArr);
                            $key = array_rand($timeArr,1);
                            $g = $this->get_rand($config['way']);//确定玩法类型
                            shuffle($wayArr[$g]);
                            $bet['way'] = $wayArr[$g][array_rand($wayArr[$g],1)];
                            $bet['bet_time'] = $timeArr[$key];
                            $bet['user_id'] = $val['id'];
                            $bet['username'] = $val['username'];
                            $bet['avatar'] = $val['avatar'];
                            $bet['bet_money'] = $moneyArr[array_rand($moneyArr,1)];
                            $rows = $this->db->insert("un_bet_list",$bet);
//                            if($rows < 0) {
//                                $check = false;
//                            }
                            unset($timeArr[$key]);
                        }
                    }
                }
            }
        }
//        if($check === true) {
//            $this->db->query('COMMIT');//提交事务
//        } else {
//            $this->db->query('ROLLBACK');//事务回滚
//        }
    }
    */

    public function get_rand($proArr) {
        $result = '';
        //概率数组的总概率精度
        $proSum = array_sum($proArr);
        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset ($proArr);
        return $result;
    }

    public function getWayInfo()
    {
       return $wayArr = [
            "1"=>["大","小","单","双"],//大小单双
            "2"=>["大单","小单","大双","小双"],
            "3"=>["极大","极小"],
            "4"=>range(0,27),
            "5"=>["红","绿","蓝","豹子","正顺","倒顺","半顺","乱顺","对子"]
        ];
    }

    public function getPK10WayInfo(){
        return $wayArr = [
            "1"=>["大","小","单","双"],//大小单双
            "2"=>["大单","小单","大双","小双"],
            "3"=>["龙","虎"],
            "4"=>range(1,10),
            "5"=>["冠亚大","冠亚小","冠亚单","冠亚双","和3","和4","和5","和6","和7","和8",
                "和9","和10","和11","和12","和13","和14","和15","和16","和17","和18","和19"]
        ];
    }



    public function delDummyConf($id)
    {

        $this->db->query('BEGIN');//开启事务
        $rows = $this->db->delete("un_person_config",['id'=>$id]);
        $row = $this->db->delete("un_role",['conf_id'=>$id]);
        $this->db->delete("un_bet_list",['conf_id'=>$id]);
        if($rows > 0 && $row > 0)
        {
            $this->db->query('COMMIT');//提交事务
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        else
        {
            $this->db->query('ROLLBACK');//事务回滚
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    public function updateDummyConf($tmp,$type)
    {
        if($type == "state")
        {
            if($tmp['state'] == 1)
            {
                $data['state'] = 0;
            }
            else
            {
                $data['state'] = 1;
            }
            $this->db->delete("un_bet_list",['conf_id'=>$tmp['id']]);
            $rows = $this->db->update("un_person_config", $data, ['id'=>$tmp['id']]);

        }
        elseif($type == "conf")
        {
            $row = $this->addDummyConf($tmp);
            if($row['code'] == 0)
            {
                $rows = true;
            }
            else
            {
                $rows = false;
            }
        }
        if($rows !== false)
        {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        }
        else
        {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;

    }

    public function getDummyList($offer,$pagesize,$post)
    {
        $where=" reg_type=9 ";
        if($post['search']==1){
            $username=$post['username'];
            $where.=" AND a.username LIKE '%{$username}%' ";
        }
        if(empty($offer) && empty($pagesize))
        {
            $list = $this->db->getall("select a.id,a.username,a.nickname,b.money from un_user a left join un_account b on a.id = b.user_id where {$where}");
        }
        else
        {
            $list = $this->db->getall("select a.id,a.username,a.nickname,b.money from un_user a left join un_account b on a.id = b.user_id  where {$where} limit $offer,$pagesize ");
        }
        $lists = $this->db->getall("select * from un_role");
        foreach($list as $key=>$val)
        {
            $list[$key]['start'] = 0;
            foreach($lists as $va)
            {
                if($va['user_id'] == $val['id'])
                {
                    $list[$key]['start'] = 1;
                }
            }
        }
        return $list;
    }

    public function tonePermissions($data)
    {
        $adminRoleList = $this->db->getall("select roleid from un_admin_role");
        foreach ($adminRoleList as $value){
            $groupList[] = $value['roleid'];
        }
        foreach ($data as $val)
        {
            if(!in_array($val,$groupList))
            {
                $arr['code'] = -1;
                $arr['msg'] = "非法参数";
                return $arr;
            }
        }
        $tonePermissions = $this->db->getone("select * from un_config where nid = 'tonePermissions'");
        if(empty($tonePermissions))
        {
            $conf['nid'] = "tonePermissions";
            $conf['value'] = json_encode($data);
            $conf['name'] = "提示音权限设置";
            $rows = $this->db->insert("un_config",$conf);
        }
        else
        {
            $rows = $this->db->update("un_config", ['value'=>json_encode($data)], ['id'=>$tonePermissions['id']]);
        }
        if($rows > 0 || $rows !== false)
        {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
            //初始化redis
            $redis = initCacheRedis();
            //刷新redis缓存
            $redis->del('Config:tonePermissions');
            $redis->hMset('Config:tonePermissions', $tonePermissions);
            //关闭redis链接
            deinitCacheRedis($redis);
            addMsgCue("prompt_authority", $data);
        }
        else
        {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    /*
     *统计一共有多少条符合条件的黑名单数据 
     */    
    public function coutIpBlacklist($arr){
        if($arr['ip']){
            $res=$this->db->getall('select count(*) as num from un_ipBlacklist where ip='.$arr['ip']);
        }else{
            $res=$this->db->getall('select count(*) as num from un_ipBlacklist');
        }
        return $res[0]['num'];
    }
    
    /*
     * 搜索黑名单
     */
    public function ipBlacklist_model($arr, $offer, $pagesize) {
        $ip = $arr['ip'];
    
        $where='1=1';
        if ($ip != '') {
            $where = "and ip={$ip}";
        }
    
        $sql = 'select * from un_ipBlacklist where '.$where;
        $sql .= " limit $offer,$pagesize ";
        return $this->db->getall($sql);
    }

    /*
     *编辑添加ip黑名单
     */
    public function ipBlackAct($data)
    {
        if($data['type'] == 1)//添加编辑操作
        {
            $arr = array(
                'ip' => $data['ip'],
                'ip_attribution' => isset($data['ip_attribution']) ? $data['ip_attribution'] : '',
                'status' => $data['status'],
                'url_content' => $data['url_content'],
                'remark' => $data['remark'],
                'addtime'=>time(),
                'mac'=>$data['mac']
            );
            if(empty($data['id'])) {
                if(!empty($data['ip'])) {
                    if (!checkIP($data['ip'])) {
                        $arr['code'] = -1;
                        $arr['msg'] = "非法的ip";
                        return $arr;
                    }
                    
                    $ipData = getIp($data['ip']); //获取IP归属地
                }else {
                    $ipData = ['attribution' => ''];
                }

                $arr['ip_attribution'] = $ipData['attribution'];
                
                if(!empty($data['mac']) && !empty($data['ip'])) {
                    $result = $this->db->getone("select id from un_ipBlacklist where ip='".$data['ip']."' and mac='".$data['mac']."'");
                } else if(!empty($data['mac'])) {
                    $result = $this->db->getone("select id from un_ipBlacklist where mac='".$data['mac']."'");
                } else if(!empty($data['ip'])) {
                    $result = $this->db->getone("select id from un_ipBlacklist where ip='".$data['ip']."'");
                }
                if($result){
                    $arr['code'] = -1;
                    $arr['msg'] = "已经存在此IP";
                    return $arr;
                }
                $res = $this->db->insert('un_ipBlacklist', $arr);
                $this->writeIpList($data['ip'],"insert", $arr['status']);
            } else {
                //处理历史问题
                if (empty($arr['ip_attribution'])) {
                    $ipData = getIp($arr['ip']); //获取IP归属地
                    $arr['ip_attribution'] = $ipData['attribution'];
                }

                $res = $this->db->update("un_ipBlacklist", $arr, ["id"=>$data['id']]);
            }
        }
        elseif($data['type'] == 2)//删除操作
        {
            $ip_info = $this->db->getone("select ip,status from #@_ipBlacklist where id = {$data['id']}");
            if (!empty($ip_info)) {
                $this->writeIpList($ip_info['ip'],"delete",$ip_info['status']);
            }
            $res = $this->db->delete("un_ipBlacklist", ["id"=>$data['id']]);
        }

        if($res > 0 || $res === true) {
            $arr['code'] = 0;
            $arr['msg'] = "操作成功";
        } else {
            $arr['code'] = -1;
            $arr['msg'] = "操作失败";
        }
        return $arr;
    }

    public function writeIpList($ip,$type,$status){
        $ip_list = [];
        $path = S_ROOT."up_files/ip_black_list.json";
        if (!file_exists($path)) {
            touch($path);
        }
        $centos = file_get_contents($path);
        if (empty($centos)) {
            $ip_list = $this->db->getall("select ip from #@_ipBlacklist where status = 0");
            $ip_list = array_column($ip_list,'ip');
        } else {
            $ip_list = explode("\n",$centos);
        }
        if ($type == "delete") {

            foreach ($ip_list as $key=>$val) {
                if ($val == $ip){
                    unset($ip_list[$key]);
                }
            }
            $ip_list=array_values($ip_list);

        } elseif($type == "insert"){
            if (!in_array($ip,$ip_list) && $status == 0) {
                $ip_list[] = $ip;
            }
        }
        return file_put_contents($path,implode("\n",$ip_list));
    }
}
