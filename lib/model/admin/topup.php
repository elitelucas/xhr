<?php

/**
 * Created by PhpStorm.
 * User: wangrui
 * Date: 2016/11/18
 * Time: 22:27
 * desc: 用户邦定银行信息
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class TopupModel extends CommonModel {

    //线上充值统计  all
    public function onlineTJ($where) {
        $search_type = 0;
        $search_status = 1;
        $ret = [];
        $todaytime = strtotime(date('Y-m-d'));
        $search = ['online_pending_sum','online_sum','online_reject_sum'];
        $query = "";
        if ($where['order_sn'] != "") {
            $query .= " and a.order_sn = '{$where['order_sn']}'";
        }
        if ($where['username'] != "") {
            $uRt = $this->db->getone("select id from un_user where username = '{$where['username']}'");
            $uId = empty($uRt) ? 0 : $uRt['id'];
            $query .= " and a.user_id = {$uId}";
            $search_type = 1;
        }
        if ($where['reg_type'] != 0) {
            $query .= " and b.reg_type='{$where['reg_type']}' ";
            $search_type = 1;
        }else{
            $query .= " and b.reg_type not in(8,9,11) ";
        }
        if ($where['status'] != "") {
            if($where['status'] != 99) {
                $query .= " and a.status = {$where['status']}";
                $search_status =  $where['status'];
            }else {
                $search_status =  3;
            }
        }else{
            $query .= " and a.status = 1";
            $search_status =  1;
        }
        if ($where['payment_id'] != "") {
            $query .= " and a.payment_id = {$where['payment_id']}";
            $search_type = 1;
        }else{
            //获取在线充值的payment_id
            $sql = 'SELECT PC.id FROM un_payment_config PC RIGHT JOIN '
                . '(SELECT D.id FROM un_dictionary D LEFT JOIN un_dictionary_class DC ON D.classid=DC.id WHERE DC.id = 13) F'
                . ' ON F.id=PC.type';
            $paymentIdArr = $this->db->getall($sql);
            $paymentIdStr = '';
            foreach ($paymentIdArr as $v) {
                $paymentIdStr .= $v['id'] . ',';
            }
            
            $paymentIdStr = rtrim($paymentIdStr, ',');
            //$paymentIdStr = substr($paymentIdStr, 0, strlen($paymentIdStr) - 1);
            $query .= " and a.payment_id  in ({$paymentIdStr})";
        }
        
        $todaytime = strtotime(date('Y-m-d'));
        $start_time = strtotime($where['s_time']);
        $end_time = strtotime($where['e_time'] . " 23:59:59");
        $arr = array();
        $arr['succMoney'] = 0;
        $arr['dealMoney'] = 0;
        $arr['cancMoney'] = 0;
        if ($search_type || $todaytime <= $start_time) {
            $query .= " and a.addtime >= {$start_time}";
            $quert .= " and a.addtime <= {$end_time}";

            $sql = "select a.* from un_account_recharge as a left join un_user as b on a.user_id = b.id where 1=1".$query;
            $res = $this->db->getAll($sql);
            foreach ($res as $v){
                if($v['status'] == 1)//完成
                {
                    $arr['succMoney'] += $v['money'];
                }
                else if($v['status'] == 0)//未完成
                {
                    $arr['dealMoney'] += $v['money'];
                }
                else if($v['status'] == 2)//驳回
                {
                    $arr['cancMoney'] += $v['money'];
                }
            }
        }else {
            $sqls = '';
            $sqlss = '';
            $count = [];
        
            if ($todaytime < $end_time ) {
                $sqls .= " and a.addtime <= {$end_time}";
                $sqls .= " and a.addtime >= {$todaytime}";
    
                $sql_1 = "select a.* from un_account_recharge as a join un_user as b on a.user_id = b.id where " . $sqls;  // order by addtime desc
                $res = $this->db->getAll($sql);

                foreach ($res as $v){
                    if($v['status'] == 1)//完成
                    {
                        $arr['succMoney'] += $v['money'];
                    }
                    else if($v['status'] == 0)//未完成
                    {
                        $arr['dealMoney'] += $v['money'];
                    }
                    else if($v['status'] == 2)//驳回
                    {
                        $arr['cancMoney'] += $v['money'];
                    }
                }
    
                $sqlss .= " and create_time <= {$todaytime} ";
                $sqlss .= " and create_time >= {$start_time} ";
                
                if ($search_status == 3) {
                    $sqls_2 = "select sum(online_sum) as sum_1,sum(online_pending_sum) as sum_2,sum(online_reject_sum) as sum_3 from un_account_recharge_day where 1 = 1" . $sqlss;  // order by addtime desc
                    $sum_2 = $this->db->getone($sqls_2);
                    $arr['succMoney'] += $sum_2['sum_1'];
                    $arr['dealMoney'] += $sum_2['sum_2'];
                    $arr['cancMoney'] += $sum_2['sum_3'];
                }else {
                    $sqls_2 = "select sum({$search[$search_status]}) as sum from un_account_recharge_day where 1 = 1 " . $sqlss;  // order by addtime desc
                    $sum_2 = $this->db->getone($sqls_2);
                    if ($search_status == 1) {
                        $arr['succMoney'] += $sum_2['sum'];
                    }elseif ($search_status == 2) {
                        $arr['cancMoney'] += $sum_2['sum'];
                    }else {
                        $arr['dealMoney'] += $sum_2['sum'];
                    }
                }

            }else {
                $sqlss .= " and create_time >= {$start_time}";
                $sqlss .= " and create_time <= {$end_time}";
                
                if ($search_status == 3) {
                    $sqls_2 = "select sum(online_sum) as sum_1,sum(online_pending_sum) as sum_2,sum(online_reject_sum) as sum_3 from un_account_recharge_day where 1 = 1" . $sqlss;  // order by addtime desc
                    $sum_2 = $this->db->getone($sqls_2);
                    $arr['succMoney'] += $sum_2['sum_1'];
                    $arr['dealMoney'] += $sum_2['sum_2'];
                    $arr['cancMoney'] += $sum_2['sum_3'];
                }else {
                    $sqlss_1 = "select sum({$search[$search_status]}) as sum from un_account_recharge_day where 1 = 1 " . $sqlss;  // order by addtime desc
                    $sum_2 = $this->db->getone($sqlss_1);
                    if ($search_status == 1) {
                        $arr['succMoney'] += $sum_2['sum'];
                    }elseif ($search_status == 2) {
                        $arr['cancMoney'] += $sum_2['sum'];
                    }else {
                        $arr['dealMoney'] += $sum_2['sum'];
                    }
                }
            }
        }

        return $arr;
    }
    
    /*
    //线上充值统计  all
    public function onlineTJ($where) {
        lg('logtime.txt','@@@@@@@@@@@@@@@@ onlineTJ @@@@@@@@@@@@@@@@@');
        $query = "";
        if ($where['order_sn'] != "") {
            $query .= " and a.order_sn = '{$where['order_sn']}'";
        }
        if ($where['username'] != "") {
            $uRt = $this->db->getone("select id from un_user where username = '{$where['username']}'");
            $uId = empty($uRt) ? 0 : $uRt['id'];
            $query .= " and a.user_id = {$uId}";
        }
        if ($where['reg_type'] != 0) {
            $query .= " and b.reg_type='{$where['reg_type']}' ";
        }else{
            $query .= " and b.reg_type not in(8,9,11) ";
        }
        if ($where['payment_id'] != "") {
            $query .= " and a.payment_id = {$where['payment_id']}";
        }else{
            //获取在线充值的payment_id
            $sql = 'SELECT PC.id FROM un_payment_config PC RIGHT JOIN '
                . '(SELECT D.id FROM un_dictionary D LEFT JOIN un_dictionary_class DC ON D.classid=DC.id WHERE DC.id = 13) F'
                    . ' ON F.id=PC.type';
                    $paymentIdArr = $this->db->getall($sql);
                    $paymentIdStr = '';
                    foreach ($paymentIdArr as $v) {
                        $paymentIdStr .= $v['id'] . ',';
                    }
    
                    $paymentIdStr = rtrim($paymentIdStr, ',');
                    //$paymentIdStr = substr($paymentIdStr, 0, strlen($paymentIdStr) - 1);
                    $query .= " and a.payment_id  in ({$paymentIdStr})";
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query .= " and a.addtime > $time ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query .= " and a.addtime < $time ";
        }
        if ($where['status'] != "") {
            if($where['status'] != 99) {
                $query .= " and a.status = {$where['status']}";
            }
        }else{
            $query .= " and a.status = 1";
        }
    
        $sql = "select a.* from un_account_recharge as a left join un_user as b on a.user_id = b.id where 1=1".$query;
        lg('logtime.txt','onlineTJ_sql:' . $sql);
        $log_time['onlineTJ_1'] = msectime();
        $res = $this->db->getAll($sql);
        $log_time['onlineTJ_2'] = msectime();
        $log_time['onlineTJ_3'] = ($log_time['onlineTJ_2'] - $log_time['onlineTJ_1']) / 1000;
        $arr = array();
        $arr['succMoney'] = 0;
        $arr['dealMoney'] = 0;
        $arr['cancMoney'] = 0;
        foreach ($res as $v){
            if($v['status'] == 1)//完成
            {
                $arr['succMoney'] += $v['money'];
            }
            else if($v['status'] == 0)//未完成
            {
                $arr['dealMoney'] += $v['money'];
            }
            else if($v['status'] == 2)//驳回
            {
                $arr['cancMoney'] += $v['money'];
            }
        }
        lg('logtime.txt',print_r($log_time, true));
        return $arr;
    }
    */

    //payment_id   1-支付宝 2-微信
    public function topupList($where) {
        $sql = "select re.*,user.username,user.id as uid from un_account_recharge as re left join un_user as user on re.user_id = user.id where 1=1";  // order by addtime desc
        if ($where['order_sn'] != "") {
            $sql .= " and order_sn = '{$where['order_sn']}'";
        }
        if ($where['username'] != "") {
            $user_id = $this->userIdName($where['username']);
            $sql .= " and user_id = '{$user_id}'";
        }
        if ($where['reg_type'] != 0) {
            $sql .= " and user.reg_type='{$where['reg_type']}' ";
        }else{
            $sql .= " and user.reg_type not in(8,9,11) ";
        }
        if ($where['status'] != "") {
            if($where['status'] != 99) {
                $sql .= " and status = {$where['status']}";
            }
        }else{
            $sql .= " and status = 1";
        }
        if ($where['payment_id'] != "") {
            $sql .= " and payment_id = {$where['payment_id']}";
        }else{
            //获取在线充值的payment_id
            $sql1 = 'SELECT PC.id FROM un_payment_config PC RIGHT JOIN '
                . '(SELECT D.id FROM un_dictionary D LEFT JOIN un_dictionary_class DC ON D.classid=DC.id WHERE DC.id = 13) F'
                . ' ON F.id=PC.type';
            $paymentIdArr = $this->db->getall($sql1);
            $paymentIdStr = '';
            foreach ($paymentIdArr as $v) {
                $paymentIdStr .= $v['id'] . ',';
            }
            
            $paymentIdStr = rtrim($paymentIdStr, ',');
            //$paymentIdStr = substr($paymentIdStr, 0, strlen($paymentIdStr) - 2);
            $sql .= " and payment_id  in ({$paymentIdStr})";
        }
        if ($where['s_time'] != "") {
            $tmp = strtotime($where['s_time']);
            $sql .= " and addtime > {$tmp}";
        }
        if ($where['e_time'] != "") {
            $tmp = strtotime($where['e_time'] . " 23:59:59");
            $sql .= " and addtime < {$tmp}";
        }
        $sql .= " order by addtime desc limit {$where['page_start']},{$where['page_size']}";

//        echo $sql;
        $rt = $this->db->getall($sql);
        return $rt;
    }

    //payment_id   1-支付宝 2-微信
    public function topupCnt($where) {
        $search_type = 0;
        $search_status = 1;
        $ret = [];
        $search = ['online_pending_count','online_count','online_reject_count'];
        lg('logtime.txt','@@@@@@@@@@@@@@@@ topupCnt @@@@@@@@@@@@@@@@@');
        $sql = " ";  // order by addtime desc
        if ($where['order_sn'] != "") {
            $sql .= " and re.order_sn = '{$where['order_sn']}'";
            $search_type = 1;
        }
        if ($where['reg_type'] != 0) {
            $sql .= " and user.reg_type='{$where['reg_type']}' ";
            $search_type = 1;
        }else{
            $sql .= " and user.reg_type not in(8,9,11) ";
        }
        if ($where['username'] != "") {
            $user_id = $this->userIdName($where['username']);
            $sql .= " and re.user_id = '{$user_id}'";
            $search_type = 1;
        }
        if ($where['status'] != "") {
            if($where['status'] != 99) {
                $sql .= " and re.status = {$where['status']}";
                $search_status =  $where['status'];
            }else {
                $search_status =  3;
            }
        }else{
            $sql .= " and re.status = 1";
            $search_status = 1;
        }
        if ($where['payment_id'] != "") {
            $sql .= " and re.payment_id = {$where['payment_id']}";
            $search_type = 1;
        }else{
            //获取在线充值的payment_id
            $sql1 = 'SELECT PC.id FROM un_payment_config PC RIGHT JOIN '
                . '(SELECT D.id FROM un_dictionary D LEFT JOIN un_dictionary_class DC ON D.classid=DC.id WHERE DC.id = 13) F'
                    . ' ON F.id=PC.type';
            
            $paymentIdArr = $this->db->getall($sql1);

            $paymentIdStr = '';
            foreach ($paymentIdArr as $v) {
                $paymentIdStr .= $v['id'] . ',';
            }
            
            //$paymentIdStr = implode(',', $paymentIdArr);
            $paymentIdStr = rtrim($paymentIdStr, ',');

            //$paymentIdStr = substr($paymentIdStr, 0, strlen($paymentIdStr) - 2);
            $sql .= " and re.payment_id  in ({$paymentIdStr})";
        }

        $todaytime = strtotime(date('Y-m-d'));
        $start_time = strtotime($where['s_time']);
        $end_time = strtotime($where['e_time'] . " 23:59:59");
        if ($search_type || $start_time >= $todaytime) {
            $sql .= " and re.addtime > {$start_time}";
            $sql .= " and re.addtime < {$end_time}";

            $sql_1 = "select count(*) as cnt from un_account_recharge as re left join un_user as user on user.id = re.user_id  where 1=1 " . $sql;  // order by addtime desc
            payLog('cc.txt',$sql_1);
            $ret = $this->db->getone($sql_1);
        }else {
            $sqls = '';
            $sqlss = '';
            $count = [];

            if ($todaytime < $end_time ) {
                $sqls .= " and addtime <= {$end_time}";
                $sqls .= " and addtime >= {$todaytime}";

                $sqls_1 = "select count(*) as cnt from un_account_recharge as re left join un_user as user on user.id = re.user_id where user.reg_type not in(8,9,11) " . $sqls;  // order by addtime desc
                $count_1 = $this->db->getone($sqls_1);
                
                $sqlss .= " and create_time <= {$todaytime} ";
                $sqlss .= " and create_time >= {$start_time} ";
                
                if ($search_status == 3) {
                    $sqls_2 = "select sum(online_count) as count_1,sum(online_pending_count) as count_2,sum(online_reject_count) as count_3 from un_account_recharge_day where 1 = 1" . $sqlss;  // order by addtime desc
                    $count_2 = $this->db->getone($sqls_2);
                    $ret['cnt'] = $count_1['cnt'] + $count_2['count_1'] + $count_2['count_2'] + $count_2['count_3'];
                }else {
                    $sqls_2 = "select sum({$search[$search_status]}) as cnt from un_account_recharge_day where 1 = 1" . $sqlss;
                    $count_2 = $this->db->getone($sqls_2);
                    $ret['cnt'] = (empty($count_1['cnt']) ? 0 : $count_1['cnt'] + empty($count_2['cnt']) ? 0 : $count_2['cnt']);
                }
            }else {
                $sqlss .= " and create_time <= {$end_time} ";
                $sqlss .= " and create_time >= {$start_time} ";
                
                if ($search_status == 3) {
                    $sqls_2 = "select sum(online_count) as count_1,sum(online_pending_count) as count_2,sum(online_reject_count) as count_3 from un_account_recharge_day where 1 = 1" . $sqlss;  // order by addtime desc
                    $count_2 = $this->db->getone($sqls_2);
                    $ret['cnt'] = $count_2['count_1'] + $count_2['count_2'] + $count_2['count_3'];
                }else {
                    $sqls_2 = "select sum({$search[$search_status]}) as cnt from un_account_recharge_day where 1 = 1" . $sqlss;
                    $count_2 = $this->db->getone($sqls_2);
                    $ret['cnt'] = (empty($count_1['cnt']) ? 0 : $count_1['cnt'] + empty($count_2['cnt']) ? 0 : $count_2['cnt']);
                }
            }
        }
        return $ret['cnt'];
    }
       
    //获取用户金额
    public function userMoney($user_id) {
        if(!empty(C('db_port'))) { //使用mycat时 查主库数据
            $sql = "/*#mycat:db_type=master*/ select money from un_account where user_id = {$user_id}";
        }else{
            $sql = "select money from un_account where user_id = {$user_id}";
        }
        $rt = $this->db->getone($sql);
        return $rt['money'];
    }

    //资金日志表插入日志
    public function addLog($data) {
//        return $this->db->insert("un_account_log", $data);
        return $this->aadAccountLog($data);
    }

    //改变用户金额
    public function upAccount($money, $user_id) {
        return $this->db->query("update un_account set money = money + {$money} where user_id = {$user_id}");
    }

    //改变充值表一条记录
    public function upRecharge($data, $where) {
        return $this->db->update("un_account_recharge", $data, $where);
    }

    //根据用户账户获取ID
    public function userIdName($username) {
        $rt = $this->db->getone("select id from un_user where username = '{$username}'");
        return empty($rt) ? 0 : $rt['id'];
    }

    //根据ID查询充值表的一条记录
    public function recharge($id) {
        return $this->db->getone("select re.*,user.username from un_account_recharge as re left join un_user as user on re.user_id = user.id where re.id = {$id} ");
    }

    //更新配置表公司余额   返回即时余额
    public function paymentBalance($data) {
        $this->db->query("update un_payment_config set balance = balance + {$data['balance']} where id = {$data['id']}");
        $rt = $this->db->getone("select balance from un_payment_config where id = {$data['id']}");
        return $rt['balance'];
    }


    //获取当前充值信息
    public function getAccountRecharge($id) {
        return $this->db->getone("select `id`, `user_id`, `money` from `un_account_recharge` where `status` = 0 and `id` = {$id}");
    }
    
    //管理员姓名
    public function adminName($userid) {
        return $this->db->getone("select * from un_admin where userid = {$userid}");
    }

}
