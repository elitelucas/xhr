<?php

/**
 * Created by PhpStorm.
 * User: wangrui
 * Date: 2016/11/18
 * Time: 21:05
 * desc: 账户信息表
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class AccountModel extends CommonModel {

    protected $table = '#@_account';
    protected $table1 = '#@_account_recharge';
    protected $table2 = '#@_account_cash';
    protected $table3 = '#@_orders';
    protected $table4 = '#@_payment_config';
    protected $table5 = '#@_account_log';
    protected $table6 = '#@_config';
    protected $table7 = '#@_dictionary';

    /**
     * 获取
     * 充值记录   un_account_recharge
     * 提现现记录   un_account_cash
     * 彩票开奖记录  (派奖、投注、撤单)  un_orders
     * @param type $group(搜索条件)
     */
    public function getBills($group) {
        $sql = "SELECT L.id, L.order_num,L.type,L.money,L.addtime FROM `un_account_log` AS L WHERE 1=1";
        if (!empty($group['start_time'])) {
            $time = strtotime($group['start_time']);
            $sql .= " AND L.addtime > $time";
        }
        if (!empty($group['end_time'])) {
            $time = strtotime($group['end_time']." 23:59:59");
            $sql .= " AND L.addtime < $time";
        }
        if (!empty($group['type'])) {
            $sql .= " AND L.type IN({$group['type']})";
        }

        //排序
        $sql .= " AND `user_id` = {$group['userId']} ORDER BY L.addtime DESC ";

        $start = ($group['page'] - 1) * $group['pageCnt'];
        $sql .=  " limit $start,{$group['pageCnt']}";
        $rt = $this->db->getall($sql);
        //dump($sql);
        return $rt;
    }
    
    /**
     * 获取充值总数
     * 充值记录   un_account_recharge
     * 提现现记录   un_account_cash
     * 彩票开奖记录  (派奖、投注、撤单)  un_orders
     * @param type $group(搜索条件)
     */
    public function getBillsCnt($group) {
        $sql = "SELECT count(id) as count FROM `un_account_log` AS L WHERE 1=1";
        if (!empty($group['start_time'])) {
            $time = strtotime($group['start_time']);
            $sql .= " AND L.addtime > $time";
        }
        if (!empty($group['end_time'])) {
            $time = strtotime($group['end_time']." 23:59:59");
            $sql .= " AND L.addtime < $time";
        }
        if (!empty($group['type'])) {
            $sql .= " AND L.type IN({$group['type']})";
        }
    
        //排序
        $sql .= " AND `user_id` = {$group['userId']}";
        
        $rt = $this->db->getone($sql);
        //dump($sql);
        return $rt['count'];
    }

    /**
     * 每页展示个数
     */
    public function pageCnt() {
        $rt = $this->db->getone("select value from " . $this->table6 . " where nid='100009'");
        return $rt['value'];
    }

    /**
     * 交易类型
     */
    public function tranType() {
        $rt = $this->db->getall("select * from " . $this->table7 . " where classid='2' order by 'value'");
        $array = array();
        foreach ($rt as $value) {
            $array[$value['id']] = $value['name'];
        }
        return $array;
    }

    /**
     * 每个类型对应 + -
     */
    public function moneyType($type) {
        $list = array(
            12 => 1,
            13 => 2,
            10 => 1,
            11 => 2,
            14 => 1,
            18 => 1,
            19 => 1,
            20 => 1,
            21 => 1,
            25 => 2,
            48 => 2,
            51 => 1,
            66 => 1
        );
        return $list[$type];
    }

}
