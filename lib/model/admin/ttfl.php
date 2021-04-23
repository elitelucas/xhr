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

class TTflModel extends CommonModel {

    protected $table = '#@_ttfl_log';
    protected $table1 = '#@_user';
    protected $table2 = '#@_account_log';
    protected $table3 = '#@_account';

    //天天返利领取列表
    public function ttfl($where) {
        //$sql = "select log.status,log.id,log.cz_money,log.get_money,u.username,log.get_time,log.oper,log.order_sum,log.remark,AL.addtime from " . $this->table . " as log left join " . $this->table1 . " as u on log.user_id = u.id left join " . $this->table2 . " as AL on log.order_sum = AL.order_num where log.status != 3 and AL.type = 10";
        $sql = "select log.status,log.id,log.cz_money,log.get_money,u.username,log.get_time,log.oper,log.order_sum,log.remark,log.addtime from " . $this->table . " as log left join " . $this->table1 . " as u on log.user_id = u.id where log.status != 3 ";
        if ($where['user_id'] != "") {
            $sql .= " and log.user_id = {$where['user_id']}";
        }
        if ($where['s_time'] != "") {
            $time = strtotime($where['s_time']);
            $sql .= " and log.addtime > {$time}";
        }
        if ($where['e_time'] != "") {
            $time = strtotime($where['e_time']." 23:59:59");
            $sql .= " and log.addtime < {$time}";
        }
        if ($where['reg_type'] != "") {
            $sql .= $where['reg_type'];
        }
        $sql .= " order by status asc,id desc limit {$where['page_start']},{$where['page_size']}";
//        echo $sql;
        return $this->db->getall($sql);
    }

    //天天返利总数
    public function cntTtfl($where) {
        $sql = "select count(log.id) as cnt,sum(log.cz_money) as cz_money,sum(log.get_money) as get_money from " . $this->table . " as log left join un_user as u on log.user_id = u.id  where log.status != 3 ";
        if ($where['user_id'] != "") {
            $sql .= " and log.user_id = {$where['user_id']}";
        }
        if ($where['s_time'] != "") {
            $time = strtotime($where['s_time']);
            $sql .= " and log.addtime > {$time}";
        }
        if ($where['e_time'] != "") {
            $time = strtotime($where['e_time']." 23:59:59");
            $sql .= " and log.addtime < {$time}";
        }

        if ($where['reg_type'] != "") {
            $sql .= $where['reg_type'];
        }

        $rt = $this->db->getone($sql);
        if(empty($rt['cz_money'])){
            $rt['cz_money'] = 0;
        }
        if(empty($rt['get_money'])){
            $rt['get_money'] = 0;
        }
        return $rt;
    }

    public function info($where) {
        return $this->db->getone("select * from " . $this->table . " where id={$where['id']}");
    }

    public function userId($where) {
        $rt = $this->db->getone("select id from " . $this->table1 . " where username='{$where['username']}'");
        if (empty($rt)) {
            return -1;
        }
        return $rt['id'];
    }

    //资金日志添加
    public function addLog($log) {
        //return $this->db->insert($this->table2, $log);
        return $this->aadAccountLog($log);
    }

    //资金表更新
    public function upAccount($log) {
        $sql = "update " . $this->table3 . " set money = money + {$log['add_money']} where user_id = {$log['user_id']}";
        return $this->db->query($sql);
    }

    //状态更新
    public function upStatus($where) {
        $sql = "update " . $this->table . " set status = 2 where id = {$where['id']}";
        return $this->db->query($sql);
    }

    //开启事物
    public function beginTrans($logs, $log,$username) {
        $this->db->query("START TRANSACTION");
//        $rt1 = $this->db->insert($this->table2, $logs);
        $rt1 = $this->aadAccountLog($logs);
        $rt2 = $this->db->query("update " . $this->table3 . " set money = money + {$log['add_money']} where user_id = {$log['user_id']}");
        $rt3 = $this->db->update($this->table, array("status" => 2,"get_time"=>time()), array("id" => $log['id'], 'status' => 1));

        //添加操作人
        $rt4 = $this->db->query("UPDATE `un_ttfl_log` SET remark = CONCAT(remark,',操作人:".$username."') WHERE id={$log['id']}");

        $rt = "";
        if ($rt1 > 0 && $rt2 > 0 && $rt3 > 0 && $rt4 > 0) {
            $this->db->query("COMMIT");
            $rt = 1;
        } else {
            $this->db->query("ROLLBACK");
            $rt = -1;
        }
        return $rt;
    }
    
    //取消发放返利
    public function sendNo($id){
        return $this->db->query("update " . $this->table . " set status = 3 where id = {$id}");
    }
    
    //个人账户信息
    public function unAccount($id){
        return $this->db->getone("select * from " . $this->table3 . " where user_id = {$id}");
    }

}
