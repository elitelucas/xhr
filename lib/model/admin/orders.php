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

class OrdersModel extends CommonModel {

    protected $table = '#@_orders';
    protected $table1 = '#@_user';
    protected $table2 = '#@_account_log';
    protected $table3 = '#@_account';
    protected $table4 = '#@_dictionary';
    protected $table5 = '#@_lottery_type';
    protected $table6 = '#@_open_award';


    public function listOrder($where,$type=1) {
        if($type == 2) {
            $fields = 'count(*) as count_num';
            $orderby = '';
        }else {
            $fields = "o.is_legal,o.state,o.lottery_type,o.id,o.order_no,o.way,o.issue,o.money, o.single_money, o.addtime,o.award_state,u.username,o.award,o.room_no,o.user_id,o.ext_a,o.ext_b,o.chase_number";
            $orderby = ' ORDER BY addtime DESC,`id` DESC';
        }

        $arr = [];
//        $where['e_time'] = $where['e_time'] . " 23:59:59";
        $sql = "select $fields from " . $this->table . " as o"
            . " left join " . $this->table1 . " as u on o.user_id = u.id";
              //  . " left join un_room as room on o.room_no = room.id"
               // . " left join " . $this->table6 . " as oa on o.issue = oa.issue";
        $query_sql = " where 1=1 ";
        if($where['username'] != "" && $where['type'] != "")
        {
            $ids = '';
            $ret = $this->db->getone("select id from un_user where username = '".$where['username']."'");
            if(!empty($ret))
            {

             if($where['type'] == 2)//直属查询
            {
                $idInfo = $this->sonsList($ret['id']);
//                dump($idInfo);
            }
            else if($where['type'] == 3)//团队查询
            {
                $idInfo = $this->teamLists($ret['id']);
            }
            if(!empty($idInfo))
            {
                foreach($idInfo as $val)
                {
                    $ids .= $val['id'].",";
                }
                $query_sql .= " and u.id in(".trim($ids,",").") ";
            }else{
                return $arr;
            }
//               if($where['type'] == 2)//直属查询
//                {
//                    $query_sql .=" AND u.id in( SELECT id FROM un_user where parent_id={$ret['id']}) ";
//                    //$idInfo = $this->sonsList($ret['id']);
//                }
//                else if($where['type'] == 3)//团队查询
//                {
//                    $id=$ret['id'];
//                    $query_sql.=" AND u.id IN( SELECT user_id FROM un_user_tree WHERE FIND_IN_SET($id,pids)) ";
//                    //$idInfo = $this->teamLists($ret['id']);
//                }
                
//                 if(!empty($idInfo))
//                 {
//                     foreach($idInfo as $val)
//                     {
//                         $ids .= $val['id'].",";
//                     }
//                     $query_sql .= " and u.id in(".trim($ids,",").") ";
//                 }
                
            }else{
                return $arr;
            }
        }
        else if($where['username'] != '')
        {
//            $query_sql .= " and u.username like '%{$where['username']}%' ";
            $query_sql .= " and u.username like '{$where['username']}' ";
        }
        if ($where['order_no'] != '') {
            $query_sql .= " and o.order_no='{$where['order_no']}' ";
        }
        if($where['is_fb']!=1){
            //新增按彩种条件查询订单
            if ($where['lottery_type'] != '') {
                $query_sql .= " and o.lottery_type='{$where['lottery_type']}'";
            }
        }else{
            if ($where['lottery_type'] != '') {
                $query_sql .= " and o.lottery_type='{$where['lottery_type']}' ";
            }
        }


        if ($where['Issue'] != '') {
            $query_sql .= " and o.Issue='{$where['Issue']}' ";
        }
        if ($where['rg_type'] != 0) {
//            $query_sql .= " and u.reg_type = {$where['rg_type']} ";
            $query_sql .= " and o.reg_type = {$where['rg_type']} ";
        } else {
//            $query_sql .= " and u.reg_type not in(0,8,9,11)";
            $query_sql .= " and o.reg_type not in(0,8,9,11)";
        }
        if ($where['way'] != '') {
            if($where['way'] == "组合")
            {
                $query_sql .= " and o.way in('大双','大单','小双','小单') ";
            }
            else if($where['way'] == "极值")
            {
                $query_sql .= " and o.way in('极大','极小') ";
            }
            else if($where['way'] == "冠亚"){
                $query_sql .= " and o.way like '冠亚\_%\_%' ";
            }
            else if($where['way'] == "冠亚"){
                $query_sql .= " and o.way like '冠亚\_%\_%' ";
            }
            else if(in_array($where['way'],['三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中','五不中','六不中','七不中','八不中','九不中','十不中','二尾连中','三尾连中','四尾连中','二尾连不中','三尾连不中','四尾连不中']) ){
                $query_sql .= " and o.way like '".$where['way']."\_%' ";
            }
            else if($where['way'] == "单点")
            {
                $query_sql .= " and o.way in('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27') ";
            }
            else
            {
                $query_sql .= " and o.way='{$where['way']}' ";
            }

        }
        if ($where['award_state'] != '') {
            if ($where['award_state'] == 3) { //撤单
                $query_sql .= " and o.state = 1 ";
            }elseif ($where['award_state'] == 4){ //和局
                $query_sql .= " and o.award_state=3 and o.state = 0 ";
            } else {
                $query_sql .= " and o.award_state={$where['award_state']} and o.state = 0 ";
            }
        }
        if ($where['s_time'] != '') {
            $query_sql .= " and o.addtime >= {$where['s_time']} ";
            //$query_sql .= " and oa.open_time > {$time} ";
        }
        if ($where['e_time'] != '') {
            $query_sql .= " and o.addtime <= {$where['e_time']} ";
            //$query_sql .= " and oa.open_time < {$time} ";
        }
        if ($where['room'] != '') {
            $query_sql .= " and o.room_no = {$where['room']} ";
        }
        $sql .= $query_sql;
        $sql .= $orderby;
        if(isset($where['page_start'])) $sql.= " limit {$where['page_start']},{$where['page_size']}";
        $arr = $this->db->getall($sql);

        return $arr;
    }

    /**
     * @param $where
     * @return mixed
     */
    public function cntOrder($where)
    {
        $ret = [];
        $search_type = 0;
        $sql = "select count(*) as cnt from " . $this->table . " as o left join " . $this->table1 . " as u on o.user_id = u.id ";
        $query_sql = " where 1=1 ";
        if($where['username'] != "" && $where['type'] != "")
        {
            $ids = '';
            $search_type = 1;
            $rt = $this->db->getone("select id from un_user where username = '".$where['username']."'");
            if(!empty($rt))
            {
                if($where['type'] == 2)//直属查询
                {
                    $idInfo = $this->sonsList($rt['id']);
                }
                else if($where['type'] == 3)//团队查询
                {
                    $idInfo = $this->teamLists($rt['id']);
                }
                if(!empty($idInfo))
                {
                    foreach($idInfo as $val)
                    {
                        $ids .= $val['id'].",";
                    }
                    $query_sql .= " and u.id in(".trim($ids,",").") ";
                }else{
                    return 0;
                }
            }else{
                return 0;
            }
        }
        else if($where['username'] != '')
        {
            $query_sql .= " and u.username like '{$where['username']}' ";
            $search_type = 1;
        }
        if ($where['order_no'] != '') {
            $query_sql .= " and o.order_no='{$where['order_no']}' ";
            $search_type = 1;
        }
        if($where['is_fb']!=1){
            //新增按彩种条件查询订单
            if ($where['lottery_type'] != '') {
                $query_sql .= " and o.lottery_type='{$where['lottery_type']}'";
            }
        }else{
            if ($where['lottery_type'] != '') {
                $query_sql .= " and o.lottery_type='{$where['lottery_type']}' ";
            }
        }
        if ($where['Issue'] != '') {
            $query_sql .= " and o.Issue='{$where['Issue']}' ";
            $search_type = 1;
        }
        if ($where['rg_type'] != 0) {
            $query_sql .= " and u.reg_type = {$where['rg_type']} ";
            $search_type = 1;
        } else {
//            $query_sql .= " and u.reg_type not in(0,8,9,11)";
            $query_sql .= " and o.reg_type not in(0,8,9,11)";
        }
        if ($where['way'] != '') {
            $search_type = 1;
            if($where['way'] == "组合")
            {
                $query_sql .= " and o.way in('大双','大单','小双','小单') ";
            }
            else if($where['way'] == "极值")
            {
                $query_sql .= " and o.way in('极大','极小') ";
            }
            else if($where['way'] == "单点")
            {
                $query_sql .= " and o.way in('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27') ";
            }
            else if($where['way'] == "冠亚")
            {
                $query_sql .= " and o.way like '冠亚\_%\_%' ";
            }
            else if(in_array($where['way'],['三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中','五不中','六不中','七不中','八不中','九不中','十不中','二尾连中','三尾连中','四尾连中','二尾连不中','三尾连不中','四尾连不中']) ){
                $query_sql .= " and o.way like '".$where['way']."\_%' ";
            }
            else
            {
                $query_sql .= " and o.way='{$where['way']}' ";
            }

        }
        if ($where['award_state'] != '') {
            $search_type = 1;
            if ($where['award_state'] == 3) { //撤单
                $query_sql .= " and o.state = 1 ";
            }elseif($where['award_state'] == 4) { //和局
                $query_sql .= " and o.award_state=3 and o.state = 0 ";
            } else {
                $query_sql .= " and o.award_state={$where['award_state']} and o.state = 0 ";
            }
        }
        
        if ($where['room'] != '') {
            $search_type = 1;
            $query_sql .= " and o.room_no = {$where['room']} ";
        }

        $sql .= $query_sql;
        
        $todaytime = strtotime(date('Y-m-d'));
        $start_time = strtotime($where['s_time']);
        $end_time = strtotime($where['e_time'] . " 23:59:59");

        if ($search_type || $start_time >= $todaytime) {
            $sql .= " and o.addtime >= {$start_time} ";
            $sql .= " and o.addtime <= {$end_time} ";
            $ret = $this->db->getone($sql);
        }else {
            $sqls = '';
            $sqlss = '';
            $count = [];
        
            if ($todaytime < $end_time ) {
                $sqls .= " and addtime <= {$end_time}";
                $sqls .= " and addtime >= {$todaytime}";
        
                $sqls_1 = "select count(*) as cnt from " . $this->table . " as o left join " . $this->table1 . " as u on o.user_id = u.id where u.reg_type not in(0,8,9,11) " . $sqls;
                $count_1 = $this->db->getone($sqls_1);
        
                $sqlss .= " and create_time <= {$todaytime} ";
                $sqlss .= " and create_time >= {$start_time} ";

                $sqls_2 = "select sum(order_count) as count_2 from un_orders_day where 1 = 1" . $sqlss;
                $count_2 = $this->db->getone($sqls_2);
                $ret['cnt'] = $count_1['cnt'] + $count_2['count_2'];
               
            }else {
                $sqlss .= " and create_time <= {$end_time} ";
                $sqlss .= " and create_time >= {$start_time} ";

                $sqls_2 = "select sum(order_count) as cnt from un_orders_day where 1 = 1" . $sqlss;
                $count_2 = $this->db->getone($sqls_2);
                $ret['cnt'] = $count_2['cnt'];
            }
        }
        
        return $ret['cnt'];
    }

    public function listMoney($where) {
        $sql = "select log.id,log.order_num,user.username,log.addtime,log.money,log.use_money as money_usable,log.remark,log.type from " . $this->table2 . " as log"
                . " left join " . $this->table1 . " as user on log.user_id = user.id"
                . " left join " . $this->table3 . " as account on log.user_id = account.user_id";
        $query_sql = " where 1=1 ";
        if ($where['username'] != '') {
//            $query_sql .= " and user.username like '%{$where['username']}%' ";
            $query_sql .= " and user.username like '{$where['username']}' ";
        }
        if ($where['order_num'] != '') {
            $query_sql .= " and log.order_num='{$where['order_num']}' ";
        }
        if ($where['type'] != '') {
            $query_sql .= " and log.type='{$where['type']}' ";
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and log.addtime > {$time} ";
        }
        if ($where['rg_type'] != 0) {
            $query_sql .= " and user.reg_type = {$where['rg_type']} ";
        } else {
            $query_sql .= " and user.reg_type  NOT IN (8,9,11) ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and log.addtime < {$time} ";
        }
        $sql .= $query_sql;
        $sql .= " order by log.id desc limit {$where['page_start']},{$where['page_size']}";
        $rt = $this->db->getall($sql);
        return($rt);
    }

    public function cntMoney($where) {
//        $sql = "select count(*) as cnt,sum(log.money) as money from " . $this->table2 . " as log"
//                . " left join " . $this->table1 . " as user on log.user_id = user.id";
        $sql = "select count(*) as cnt from " . $this->table2 . " as log"
            . " left join " . $this->table1 . " as user on log.user_id = user.id";
        $query_sql = " where 1=1 ";
        if ($where['username'] != '') {
//            $query_sql .= " and user.username like '%{$where['username']}%' ";
            $query_sql .= " and user.username like '{$where['username']}' ";
        }
        if ($where['order_num'] != '') {
            $query_sql .= " and log.order_num='{$where['order_num']}' ";
        }
        if ($where['type'] != '') {
            $query_sql .= " and log.type='{$where['type']}' ";
        }
        if ($where['rg_type'] != 0) {
            $query_sql .= " and user.reg_type = {$where['rg_type']} ";
        } else {
            $query_sql .= " and user.reg_type  NOT IN (8,9,11)";
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and log.addtime > {$time} ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and log.addtime < {$time} ";
        }
        $sql .= $query_sql;
        $rt = $this->db->getone($sql);
//        if (empty($rt['money'])) {
//            $rt['money'] = 0;
//        }
        return($rt);
    }

    public function cntMoneyNew($where) {
        $sql = "select sum(log.money) as money from " . $this->table2 . " as log"
                . " left join " . $this->table1 . " as user on log.user_id = user.id";
        $query_sql = " where 1=1 ";
        if ($where['username'] != '') {
//            $query_sql .= " and user.username like '%{$where['username']}%' ";
            $query_sql .= " and user.username like '{$where['username']}' ";
        }
        if ($where['order_num'] != '') {
            $query_sql .= " and log.order_num='{$where['order_num']}' ";
        }
        if ($where['type'] != '') {
            $query_sql .= " and log.type='{$where['type']}' ";
        }
        if ($where['rg_type'] != 0) {
            $query_sql .= " and user.reg_type = {$where['rg_type']} ";
        } else {
            $query_sql .= " and user.reg_type  NOT IN (8,9,11)";
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and log.addtime > {$time} ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time'] . " 23:59:59");
            $query_sql .= " and log.addtime < {$time} ";
        }
        $sql .= $query_sql;
        $rt = $this->db->result($sql);
        if (empty($rt)) {
            $rt = 0;
        }
        return($rt);
    }

    //订单统计数据
    public function orderTJ() { 
        /*
        $noSql = "select sum(money) as noOpen from un_orders left join un_user on un_user.id = un_orders.user_id where un_orders.state = 0 and award_state = 0 and un_orders.reg_type not in(0,8,9,11)";
        $yeSql = "select sum(money) as yeOpen from un_orders left join un_user on un_user.id = un_orders.user_id  where un_orders.state = 0 and award_state != 0 and un_orders.reg_type not in(0,8,9,11)";
        $cancelSql = "select sum(money) as cancel from un_orders left join un_user on un_user.id = un_orders.user_id  where un_orders.state = 1 and un_orders.reg_type not in(0,8,9,11)";
        $betSql = "select sum(money) as bet from un_orders left join un_user on un_user.id = un_orders.user_id  where un_orders.state = 0 and un_orders.reg_type not in(0,8,9,11)";
        $bonusSql = "select sum(award) as bonus from un_orders left join un_user on un_user.id = un_orders.user_id  where un_orders.state = 0 and award_state = 2 and un_orders.reg_type not in(0,8,9,11)";
        $rt1 = $this->db->getone($noSql);
        $rt2 = $this->db->getone($yeSql);
        $rt3 = $this->db->getone($cancelSql);
        $rt4 = $this->db->getone($betSql);
        $rt5 = $this->db->getone($bonusSql);
        return array("noOpen" => $rt1['noOpen'], "yeOpen" => $rt2['yeOpen'], "cancel" => $rt3['cancel'], "bet" => $rt4['bet'], "bonus" => $rt5['bonus'], "gain" => $rt4['bet'] - $rt5['bonus']);
        */
        $redis = initCacheRedis();
        $order_count_string = $redis->Get('countOrderAmount');
        return json_decode($order_count_string,true);

    }

    //订单统计数据
    public function orderTJ2($where) {
        $query_sql = '';
        $search_type = 0;
        $ret = [];
        if($where['username'] != "" && $where['type'] != "")
        {
            $ids = '';
            $search_type = 1;
            $rt = $this->db->getone("select id from un_user where username = '".$where['username']."'");
            if(!empty($rt))
            {
                if($where['type'] == 2)//直属查询
                {
                   // $idInfo = $this->sonsList($rt['id']);
                    $query_sql.=" AND u.id IN( SELECT id FROM un_user where parent_id={$rt['id']}) ";
                }
                else if($where['type'] == 3)//团队查询
                {
                    $id=$rt['id'];
                    $query_sql.=" AND ( u.id={$id} or u.id IN( SELECT user_id FROM un_user_tree WHERE FIND_IN_SET('$id',pids)) ) ";
                    /*
                    $idInfo = $this->teamLists($rt['id']);
                    if(!empty($idInfo))
                    {
                        foreach($idInfo as $val)
                        {
                            $ids .= $val['id'].",";
                        }
                        $query_sql .= " and u.id in(".trim($ids,",").") ";
                    }
                    */
                }
            }
        }
        else if($where['username'] != '')
        {
            $search_type = 1;
//            $query_sql .= " and u.username like '%{$where['username']}%' ";
            $query_sql .= " and u.username like '{$where['username']}' ";
        }
        if ($where['order_no'] != '') {
            $query_sql .= " and o.order_no='{$where['order_no']}' ";
        }
        if ($where['Issue'] != '') {
            $search_type = 1;
            $query_sql .= " and o.Issue='{$where['Issue']}' ";
        }
        if ($where['lottery_type'] != '') {
            $search_type = 1;
            $query_sql .= " and o.lottery_type='{$where['lottery_type']}' ";
        }
        if ($where['rg_type'] != 0) {
            $search_type = 1;
            $query_sql .= " and o.reg_type = {$where['rg_type']} ";
//            $query_sql .= " and u.reg_type = {$where['rg_type']} ";
        } else {
            $query_sql .= " and o.reg_type not in(0,8,9,11)";
//            $query_sql .= " and u.reg_type not in(0,8,9,11)";
        }
        if ($where['way'] != '') {
            $search_type = 1;
            if($where['way'] == "组合")
            {
                $query_sql .= " and o.way in('大双','大单','小双','小单') ";
            }
            else if($where['way'] == "极值")
            {
                $query_sql .= " and o.way in('极大','极小') ";
            }
            else if($where['way'] == "单点")
            {
                $query_sql .= " and o.way in('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27') ";
            }
            else if($where['way'] == "冠亚")
            {
                $query_sql .= " and o.way like '冠亚\_%\_%' ";
            }
            else if(in_array($where['way'],['三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中','五不中','六不中','七不中','八不中','九不中','十不中','二尾连中','三尾连中','四尾连中','二尾连不中','三尾连不中','四尾连不中']) ){
                $query_sql .= " and o.way like '".$where['way']."\_%' ";
            }
            else
            {
                $query_sql .= " and o.way='{$where['way']}' ";
            }

        }

        if ($where['award_state'] != '') {
            $search_type = 1;
            if ($where['award_state'] == 3) { //撤单
                $query_sql .= " and o.state = 1 ";
            }elseif($where['award_state'] == 4){
                $query_sql .= " and o.award_state=3 and o.state = 0 ";
            } else {
                $query_sql .= " and o.award_state={$where['award_state']} and o.state = 0 ";
            }
        }
        if ($where['room'] != '') {
            $search_type = 1;
            $query_sql .= " and o.room_no = {$where['room']} ";
        }
        
        $todaytime = strtotime(date('Y-m-d'));
        $start_time = strtotime($where['s_time']);
        $end_time = strtotime($where['e_time'] . " 23:59:59");
        $rt1 = [];
        $rt2 = [];
        $rt3 = [];
        $rt4 = [];
        $rt5 = [];

        if ($search_type || $start_time >= $todaytime) {
            $query_sql .= " and o.addtime >= {$start_time} ";
            $query_sql .= " and o.addtime <= {$end_time} ";
            
            $noSql = "select sum(money) as noOpen from un_orders o left join un_user u on u.id = o.user_id where o.state = 0 and o.award_state = 0".$query_sql;
            $yeSql = "select sum(money) as yeOpen from un_orders o left join un_user u on u.id = o.user_id where o.state = 0 and o.award_state != 0".$query_sql;
            $cancelSql = "select sum(money) as cancel from un_orders o left join un_user u on u.id = o.user_id where o.state = 1".$query_sql;
            $betSql = "select sum(money) as bet from un_orders o left join un_user u on u.id = o.user_id where o.state = 0".$query_sql;
            $bonusSql = "select sum(award) as bonus from un_orders o left join un_user u on u.id = o.user_id where o.state = 0 ".$query_sql;

            $rt1 = $this->db->getone($noSql);

            $rt2 = $this->db->getone($yeSql);

            $rt3 = $this->db->getone($cancelSql);
            
            $rt4 = $this->db->getone($betSql);
            
            $rt5 = $this->db->getone($bonusSql);
        }else {
            $sqls = '';
            $sqlss = '';
            $count = [];
            $ret['noOpen'] = 0;
            $ret['yeOpen'] = 0;
        
            if ($todaytime < $end_time ) {
                $sqls .= " and addtime <= {$end_time}";
                $sqls .= " and addtime >= {$todaytime}";
                $query_sql .= $sqls;
                
                $noSql = "select sum(money) as noOpen from un_orders o left join un_user u on u.id = o.user_id where o.state = 0 and o.award_state = 0".$query_sql;
                $yeSql = "select sum(money) as yeOpen from un_orders o left join un_user u on u.id = o.user_id where o.state = 0 and o.award_state != 0".$query_sql;
                $cancelSql = "select sum(money) as cancel from un_orders o left join un_user u on u.id = o.user_id where o.state = 1".$query_sql;
                $betSql = "select sum(money) as bet from un_orders o left join un_user u on u.id = o.user_id where o.state = 0".$query_sql;
                $bonusSql = "select sum(award) as bonus from un_orders o left join un_user u on u.id = o.user_id where o.state = 0 ".$query_sql;
                
                $rt1 = $this->db->getone($noSql);

                $rt2 = $this->db->getone($yeSql);
                
                $rt3 = $this->db->getone($cancelSql);

                $rt4 = $this->db->getone($betSql);

                $rt5 = $this->db->getone($bonusSql);
 
                $sqlss .= " and create_time <= {$todaytime} ";
                $sqlss .= " and create_time >= {$start_time} ";
                $sqls_2 = "select sum(betting_sum) as betting, sum(award_sum) as award, sum(cancel_sum) as cancel from un_orders_day where 1 = 1" . $sqlss;
                $sum_2 = $this->db->getone($sqls_2);
                $rt2['yeOpen'] = $rt2['yeOpen'] + $sum_2['betting'];
                $rt4['bet'] = $rt4['bet'] + $sum_2['betting'];
                $rt3['cancel'] = $rt3['cancel'] + $sum_2['cancel'];
                $rt5['bonus'] = $rt5['bonus'] + $sum_2['award'];
            }else {
                $sqlss .= " and create_time <= {$end_time} ";
                $sqlss .= " and create_time >= {$start_time} ";

                $sqls_2 = "select sum(betting_sum) as betting, sum(award_sum) as award, sum(cancel_sum) as cancel from un_orders_day where 1 = 1" . $sqlss;
                $sum_2 = $this->db->getone($sqls_2);
                $rt2['yeOpen'] = $sum_2['betting'];
                $rt4['bet']    = $sum_2['betting'];
                $rt3['cancel'] = $sum_2['cancel'];
                $rt5['bonus']  = $sum_2['award'];
            }
        }

        return array("noOpen" => $rt1['noOpen'], "yeOpen" => $rt2['yeOpen'], "cancel" => $rt3['cancel'], "bet" => $rt4['bet'], "bonus" => $rt5['bonus'], "gain" => ($rt4['bet'] - $rt5['bonus']));
    }

    //彩种类型
    public function lottyList() {
        return $this->db->getall("select * from un_lottery_type");
    }

    //交易类型
    public function listType() {
        //初始化redis
        $redis = initCacheRedis();
        $LTrade = $redis->lRange('DictionaryIds2', 0, -1);
        $tranType = array();
        foreach ($LTrade as $v){
            $res = $redis->hMGet("Dictionary2:" . $v, array('id', 'name'));
            $tranType[$res['id']] = $res['name'];
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        return $tranType;

    }

    //局分析
    public function analys($where) {
        $s_time = strtotime($where['s_time']);
        $e_time = strtotime($where['e_time'] . " 23:59:59");
        if($where['quick']!="0"&&$where['quick']!=""){
            switch ($where['quick']){
                case 1:
                    $s_time = strtotime(date("Y-m-d",strtotime("0 day")));
                    $e_time = $s_time + 86399;
                    break;
                case 2:
                    $s_time = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $e_time = $s_time + 86399;
                    break;
                case 3:
                    $s_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $e_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $s_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $e_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $s_time = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $e_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
        }

        if (in_array($where['lottery_type'],['5','6','8','9','10','11'])) {
            $s_time = $s_time + 1;
        }

        switch ($where['lottery_type']){
            case 1:
                $sql = "select issue from " . $this->table6 . " where lottery_type = {$where['lottery_type']} and open_time > {$s_time} and open_time < {$e_time} order by issue desc";
                break;
            case 2:
                $s_time = date("Y-m-d H:i:s",$s_time);
                $e_time = date("Y-m-d H:i:s",$e_time);
                $sql = "select qihao AS issue  from un_bjpk10 where lottery_type = {$where['lottery_type']} and kaijiangshijian > '{$s_time}' and kaijiangshijian < '{$e_time}' order by issue desc";
                break;
            case 3:
                $sql = "select issue from " . $this->table6 . " where lottery_type = {$where['lottery_type']} and open_time > {$s_time} and open_time < {$e_time} order by issue desc";
                break;
            case 4:
                $s_time = date("Y-m-d H:i:s",$s_time);
                $e_time = date("Y-m-d H:i:s",$e_time);
                $sql = "select qihao AS issue from un_xyft where kaijiangshijian > '{$s_time}' and kaijiangshijian < '{$e_time}' order by issue desc";
                break;
            case 5:
                $s_time = date("Y-m-d H:i:s",$s_time);
                $e_time = date("Y-m-d H:i:s",$e_time);
                $sql = "select issue from un_ssc where lottery_type = 5 and lottery_time BETWEEN {$s_time} AND $e_time order by issue desc";
                break;
            case 6:
                $s_time = date("Y-m-d H:i:s",$s_time);
                $e_time = date("Y-m-d H:i:s",$e_time);
                $sql = "select issue from un_ssc where lottery_type = 6 and lottery_time BETWEEN {$s_time} AND $e_time order by issue desc";
                break;
            case 7:
                $sql = "select issue from un_lhc where lottery_type = 7 and lottery_time BETWEEN {$s_time} AND $e_time order by issue desc";
                break;
            case 8:
                $sql = "select issue from un_lhc where lottery_type = 8 and lottery_time BETWEEN {$s_time} AND $e_time order by issue desc";
                break;
            case 9:
                $sql = "select qihao AS issue  from un_bjpk10 where lottery_type = {$where['lottery_type']} and kaijiangshijian > '{$s_time}' and kaijiangshijian < '{$e_time}' order by issue desc";
                break;
            case 10:
                $sql = "select issue from un_nn where lottery_type = 10 and lottery_time BETWEEN {$s_time} AND $e_time order by issue desc";
                break;
            case 11:
                $sql = "select issue from un_ssc where lottery_type = 11 and lottery_time BETWEEN {$s_time} AND $e_time order by issue desc";
                break;
            case 12:
                $sql = "select match_id from un_cup_against where match_date BETWEEN {$s_time} AND $e_time order by match_date desc";
                break;
            case 13:
                $sql = "select issue from un_sb where lottery_type = 13 and lottery_time BETWEEN {$s_time} AND $e_time order by issue desc";
                break;
            case 14:
                $sql = "select issue from un_ffpk10 where lottery_type = 14 and lottery_time BETWEEN {$s_time} AND $e_time order by issue desc";
                break;
        }
        $issue = $this->db->getall($sql);

        return $issue;
    }

    //局分析数据
    public function analysData($where) {
        $issue = $where['issue'];
        $lottery_type = $where['lottery_type'];
        if ($lottery_type == 12) {
            $sql = "select O.* from #@_orders as O left join #@_user as U on U.id = O.user_id left join #@_room as R on R.id = O.room_no where R.match_id = {$issue} and O.lottery_type = {$lottery_type} and O.state = 0 AND U.reg_type NOT IN(9)";
            $result = $this->db->getall($sql);
        } else {
            $result = $this->db->getall("select O.* from " . $this->table . " AS O left join " . $this->table1 ." AS U on U.id=O.user_id  where O.issue=$issue and O.lottery_type=$lottery_type and O.state=0 AND U.reg_type NOT IN(9)");
        }
        return $result;
    }

    //测试数据库数据
    public function testDB($sql) {
        return $this->db->getall($sql);
    }


    //团队集合ID  包含自身
    public function teamLists($userId) {
        $userTeams = $this->recursive_query($userId); //团队ID集合
        if (empty($userTeams)) {
            $userTeams[0]['id'] = $userId;
        } else {
            array_unshift($userTeams, array("id" => $userId));
        }
        return $userTeams;
    }

    //直属id集合
    public function sonsList($userId) {
        $rt = $this->db->getAll("select id from un_user where parent_id = {$userId}");
        return $rt;
    }

    //递归团队用户ID集合  不包含自身
    public function recursive_query($id, $filed = 'id', $where = '') {
        $sql = "SELECT {$filed} FROM un_user WHERE parent_id = {$id} {$where}";
        $res = O('model')->db->getAll($sql);
        if ($res) {
            foreach ($res as $v) {
                $res_c = $this->recursive_query($v['id'], $filed, $where);
                $res = array_merge($res, $res_c);
            }
        }
        return $res;
    }

    /*
    public function listOrder($where) {
        $arr = [];
        $sql = "select o.is_legal,o.state,o.lottery_type,o.id,o.order_no,o.way,o.issue,o.money, o.single_money, o.addtime,o.award_state,u.username,o.award,o.room_no,o.user_id,o.ext_a from " . $this->table . " as o"
            . " left join " . $this->table1 . " as u on o.user_id = u.id";
            //  . " left join un_room as room on o.room_no = room.id"
            // . " left join " . $this->table6 . " as oa on o.issue = oa.issue";
            $query_sql = " where 1=1 ";
            if($where['username'] != "" && $where['type'] != "")
            {
                $ids = '';
                $ret = $this->db->getone("select id from un_user where username = '".$where['username']."'");
                if(!empty($ret))
                {
    
                    if($where['type'] == 2)//直属查询
                    {
                        $idInfo = $this->sonsList($ret['id']);
                        //                dump($idInfo);
                    }
                    else if($where['type'] == 3)//团队查询
                    {
                        $idInfo = $this->teamLists($ret['id']);
                    }
                    if(!empty($idInfo))
                    {
                        foreach($idInfo as $val)
                        {
                            $ids .= $val['id'].",";
                        }
                        $query_sql .= " and u.id in(".trim($ids,",").") ";
                    }else{
                        return $arr;
                    }
                    //               if($where['type'] == 2)//直属查询
                    //                {
                    //                    $query_sql .=" AND u.id in( SELECT id FROM un_user where parent_id={$ret['id']}) ";
                    //                    //$idInfo = $this->sonsList($ret['id']);
                    //                }
                    //                else if($where['type'] == 3)//团队查询
                    //                {
                    //                    $id=$ret['id'];
                    //                    $query_sql.=" AND u.id IN( SELECT user_id FROM un_user_tree WHERE FIND_IN_SET($id,pids)) ";
                    //                    //$idInfo = $this->teamLists($ret['id']);
                    //                }
    
                    //                 if(!empty($idInfo))
                        //                 {
                        //                     foreach($idInfo as $val)
                            //                     {
                            //                         $ids .= $val['id'].",";
                            //                     }
                        //                     $query_sql .= " and u.id in(".trim($ids,",").") ";
                        //                 }
    
                }else{
                    return $arr;
                }
            }
            else if($where['username'] != '')
            {
                //            $query_sql .= " and u.username like '%{$where['username']}%' ";
                $query_sql .= " and u.username like '{$where['username']}' ";
            }
            if ($where['order_no'] != '') {
                $query_sql .= " and o.order_no='{$where['order_no']}' ";
            }
            //新增按彩种条件查询订单
            if ($where['lottery_type'] != '') {
                $query_sql .= " and o.lottery_type='{$where['lottery_type']}' ";
            }
            if ($where['Issue'] != '') {
                $query_sql .= " and o.Issue='{$where['Issue']}' ";
            }
            if ($where['rg_type'] != 0) {
                //            $query_sql .= " and u.reg_type = {$where['rg_type']} ";
                $query_sql .= " and o.reg_type = {$where['rg_type']} ";
            } else {
                //            $query_sql .= " and u.reg_type not in(0,8,9,11)";
                $query_sql .= " and o.reg_type not in(0,8,9,11)";
            }
            if ($where['way'] != '') {
                if($where['way'] == "组合")
                {
                    $query_sql .= " and o.way in('大双','大单','小双','小单') ";
                }
                else if($where['way'] == "极值")
                {
                    $query_sql .= " and o.way in('极大','极小') ";
                }
                else if($where['way'] == "冠亚"){
                    $query_sql .= " and o.way like '冠亚\_%\_%' ";
                }
                else if($where['way'] == "冠亚"){
                    $query_sql .= " and o.way like '冠亚\_%\_%' ";
                }
                else if(in_array($where['way'],['三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中','五不中','六不中','七不中','八不中','九不中','十不中','二尾连中','三尾连中','四尾连中','二尾连不中','三尾连不中','四尾连不中']) ){
                    $query_sql .= " and o.way like '".$where['way']."\_%' ";
                }
                else if($where['way'] == "单点")
                {
                    $query_sql .= " and o.way in('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27') ";
                }
                else
                {
                    $query_sql .= " and o.way='{$where['way']}' ";
                }
    
            }
            if ($where['award_state'] != '') {
                if ($where['award_state'] == 3) { //撤单
                    $query_sql .= " and o.state = 1 ";
                }elseif ($where['award_state'] == 4){ //和局
                    $query_sql .= " and o.award_state=3 and o.state = 0 ";
                } else {
                    $query_sql .= " and o.award_state={$where['award_state']} and o.state = 0 ";
                }
            }
            if ($where['s_time'] != '') {
                $time = strtotime($where['s_time']);
                $query_sql .= " and o.addtime > {$time} ";
                //$query_sql .= " and oa.open_time > {$time} ";
            }
            if ($where['e_time'] != '') {
                $time = strtotime($where['e_time']);
                $query_sql .= " and o.addtime < {$time} ";
                //$query_sql .= " and oa.open_time < {$time} ";
            }
            if ($where['room'] != '') {
                $query_sql .= " and o.room_no = {$where['room']} ";
            }
            $sql .= $query_sql;
            $sql .= " order by addtime desc limit {$where['page_start']},{$where['page_size']}";
            //        dump($sql);
            $arr = $this->db->getall($sql);
            //        if($where['type'] != "" && !empty($idInfo)){
            //        }
            return $arr;
    }
    */
    /*
      public function cntOrder($where) {
        $sql = "select count(*) as cnt from " . $this->table . " as o left join " . $this->table1 . " as u on o.user_id = u.id ";
        $query_sql = " where 1=1 ";
        if($where['username'] != "" && $where['type'] != "")
        {
            $ids = '';
            $rt = $this->db->getone("select id from un_user where username = '".$where['username']."'");
            if(!empty($rt))
            {
                if($where['type'] == 2)//直属查询
                {
                    $idInfo = $this->sonsList($rt['id']);
                }
                else if($where['type'] == 3)//团队查询
                {
                    $idInfo = $this->teamLists($rt['id']);
                }
                if(!empty($idInfo))
                {
                    foreach($idInfo as $val)
                    {
                        $ids .= $val['id'].",";
                    }
                    $query_sql .= " and u.id in(".trim($ids,",").") ";
                }else{
                    return 0;
                }
            }else{
                return 0;
            }
        }
        else if($where['username'] != '')
        {
            $query_sql .= " and u.username like '{$where['username']}' ";
        }
        if ($where['order_no'] != '') {
            $query_sql .= " and o.order_no='{$where['order_no']}' ";
        }
        //新增按彩种条件查询订单
        if ($where['lottery_type'] != '') {
            $query_sql .= " and o.lottery_type='{$where['lottery_type']}' ";
        }
        if ($where['Issue'] != '') {
            $query_sql .= " and o.Issue='{$where['Issue']}' ";
        }
        if ($where['rg_type'] != 0) {
            $query_sql .= " and u.reg_type = {$where['rg_type']} ";
        } else {
//            $query_sql .= " and u.reg_type not in(0,8,9,11)";
            $query_sql .= " and o.reg_type not in(0,8,9,11)";
        }
        if ($where['way'] != '') {
            if($where['way'] == "组合")
            {
                $query_sql .= " and o.way in('大双','大单','小双','小单') ";
            }
            else if($where['way'] == "极值")
            {
                $query_sql .= " and o.way in('极大','极小') ";
            }
            else if($where['way'] == "单点")
            {
                $query_sql .= " and o.way in('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27') ";
            }
            else if($where['way'] == "冠亚")
            {
                $query_sql .= " and o.way like '冠亚\_%\_%' ";
            }
            else if(in_array($where['way'],['三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中','五不中','六不中','七不中','八不中','九不中','十不中','二尾连中','三尾连中','四尾连中','二尾连不中','三尾连不中','四尾连不中']) ){
                $query_sql .= " and o.way like '".$where['way']."\_%' ";
            }
            else
            {
                $query_sql .= " and o.way='{$where['way']}' ";
            }

        }
        if ($where['award_state'] != '') {
            if ($where['award_state'] == 3) { //撤单
                $query_sql .= " and o.state = 1 ";
            }elseif($where['award_state'] == 4) { //和局
                $query_sql .= " and o.award_state=3 and o.state = 0 ";
            } else {
                $query_sql .= " and o.award_state={$where['award_state']} and o.state = 0 ";
            }
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and o.addtime > {$time} ";
            //$query_sql .= " and oa.open_time > {$time} ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time']);
            $query_sql .= " and o.addtime < {$time} ";
            //$query_sql .= " and oa.open_time < {$time} ";
        }
        if ($where['room'] != '') {
            $query_sql .= " and o.room_no = {$where['room']} ";
        }

        $sql .= $query_sql;
//        if(!empty($idInfo)){
//
//        }else{
//            return 0;
//        }
        $rt = $this->db->getone($sql);
        return $rt['cnt'];

    }
     */
              
    /*
      //订单统计数据
    public function orderTJ2($where) {
        $query_sql = '';
        if($where['username'] != "" && $where['type'] != "")
        {
            $ids = '';
            $rt = $this->db->getone("select id from un_user where username = '".$where['username']."'");
            if(!empty($rt))
            {
                if($where['type'] == 2)//直属查询
                {
                   // $idInfo = $this->sonsList($rt['id']);
                    $query_sql.=" AND u.id IN( SELECT id FROM un_user where parent_id={$rt['id']}) ";
                }
                else if($where['type'] == 3)//团队查询
                {
                    $id=$rt['id'];
                    $query_sql.=" AND ( u.id={$id} or u.id IN( SELECT user_id FROM un_user_tree WHERE FIND_IN_SET('$id',pids)) ) ";
                    
//                     $idInfo = $this->teamLists($rt['id']);
//                     if(!empty($idInfo))
//                     {
//                         foreach($idInfo as $val)
//                         {
//                             $ids .= $val['id'].",";
//                         }
//                         $query_sql .= " and u.id in(".trim($ids,",").") ";
//                     }
                    
                }
            }
        }
        else if($where['username'] != '')
        {
//            $query_sql .= " and u.username like '%{$where['username']}%' ";
            $query_sql .= " and u.username like '{$where['username']}' ";
        }
        if ($where['order_no'] != '') {
            $query_sql .= " and o.order_no='{$where['order_no']}' ";
        }
        if ($where['Issue'] != '') {
            $query_sql .= " and o.Issue='{$where['Issue']}' ";
        }
        if ($where['rg_type'] != 0) {
            $query_sql .= " and o.reg_type = {$where['rg_type']} ";
//            $query_sql .= " and u.reg_type = {$where['rg_type']} ";
        } else {
            $query_sql .= " and o.reg_type not in(0,8,9,11)";
//            $query_sql .= " and u.reg_type not in(0,8,9,11)";
        }
        if ($where['way'] != '') {
            if($where['way'] == "组合")
            {
                $query_sql .= " and o.way in('大双','大单','小双','小单') ";
            }
            else if($where['way'] == "极值")
            {
                $query_sql .= " and o.way in('极大','极小') ";
            }
            else if($where['way'] == "单点")
            {
                $query_sql .= " and o.way in('0','1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27') ";
            }
            else if($where['way'] == "冠亚")
            {
                $query_sql .= " and o.way like '冠亚\_%\_%' ";
            }
            else if(in_array($where['way'],['三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中','五不中','六不中','七不中','八不中','九不中','十不中','二尾连中','三尾连中','四尾连中','二尾连不中','三尾连不中','四尾连不中']) ){
                $query_sql .= " and o.way like '".$where['way']."\_%' ";
            }
            else
            {
                $query_sql .= " and o.way='{$where['way']}' ";
            }

        }
        if ($where['award_state'] != '') {
            if ($where['award_state'] == 3) { //撤单
                $query_sql .= " and o.state = 1 ";
            }elseif($where['award_state'] == 4){
                $query_sql .= " and o.award_state=3 and o.state = 0 ";
            } else {
                $query_sql .= " and o.award_state={$where['award_state']} and o.state = 0 ";
            }
        }
        if ($where['s_time'] != '') {
            $time = strtotime($where['s_time']);
            $query_sql .= " and o.addtime > {$time} ";
            //$query_sql .= " and oa.open_time > {$time} ";
        }
        if ($where['e_time'] != '') {
            $time = strtotime($where['e_time']);
            $query_sql .= " and o.addtime < {$time} ";
            //$query_sql .= " and oa.open_time < {$time} ";
        }
        if ($where['room'] != '') {
            $query_sql .= " and o.room_no = {$where['room']} ";
        }
        $noSql = "select sum(money) as noOpen from un_orders o left join un_user u on u.id = o.user_id where o.state = 0 and o.award_state = 0".$query_sql;
        $yeSql = "select sum(money) as yeOpen from un_orders o left join un_user u on u.id = o.user_id where o.state = 0 and o.award_state != 0".$query_sql;
        $cancelSql = "select sum(money) as cancel from un_orders o left join un_user u on u.id = o.user_id where o.state = 1".$query_sql;
        $betSql = "select sum(money) as bet from un_orders o left join un_user u on u.id = o.user_id where o.state = 0".$query_sql;
        $bonusSql = "select sum(award) as bonus from un_orders o left join un_user u on u.id = o.user_id where o.state = 0 ".$query_sql;
        $rt1 = $this->db->getone($noSql);
        $rt2 = $this->db->getone($yeSql);
        $rt3 = $this->db->getone($cancelSql);
        $rt4 = $this->db->getone($betSql);
        $rt5 = $this->db->getone($bonusSql);
        return array("noOpen" => $rt1['noOpen'], "yeOpen" => $rt2['yeOpen'], "cancel" => $rt3['cancel'], "bet" => $rt4['bet'], "bonus" => $rt5['bonus'], "gain" => ($rt4['bet'] - $rt5['bonus']));

    }
     */
      
                    
}
