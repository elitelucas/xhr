<?php

/**
 * 报表模型
 * Date: 2018-01-20
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

/**
 * 报表数据模型类
 * 2018-01-20
 */
class ReportingModel extends CommonModel
{
    // protected $redpacket_table = 'un_redpacket';
    // protected $gain_log_table = 'un_redpacket_gain_log';

    /**
     * 根据交易类型返回数据不存在补0 
     * @param $data array 交易记录
     * @param $type array 交易类型
     * @return array
     */
    public function get_arr_diff($data=array(),$type=array()){
//        $tradeType = array();
//        $trade = array();
//        foreach ($data as $v){
//            $tradeType[] = $v['type'];
//            $trade[$v['type']]['money'] = $v['total_money'];
//            $trade[$v['type']]['cnt']  = $v['cnt'];
//        }
//        //无记录的返回默认值
//        $diff = array_diff($type,$tradeType);
//        if(!empty($diff)){
//            foreach ($diff as $v){
//                $trade[$v]['money'] = "0.00";
//                $trade[$v]['cnt']  = "0";
//            }
//        }
        $trade = array();
        foreach ($type as $v){
            $trade[$v]['money'] = "0.00";
            $trade[$v]['cnt']  = "0";
        }

        foreach ($data as $v){
            $trade[$v['type']]['money'] = $v['total_money'];
            $trade[$v['type']]['cnt']  = $v['cnt'];
        }

        return $trade;
    }

    public function getTradeLogB($start_time,$end_time){
        $list = [];
        $sql = "select L.type, L.money from #@_account_log L where L.addtime between {$start_time} and {$end_time} and reg_type NOT IN(0,8,9,11)";
        // $sql = "select L.type, L.money from #@_account_log L where L.addtime >= {$start_time} and L.addtime <= {$end_time} and reg_type NOT IN(0,8,9,11)";
        $typeInfo = [];
        $orders = $this->db->getall($sql);
        lg('debug_sql.txt', var_export(['sql'=>$sql,'s'=>$start_time,'e'=>$end_time,'count'=>count($orders),'orders'=>$orders],true));
        // echo '<!--', print_r($orders, true), '-->';
        foreach ($orders as $val) {
            if(!in_array($val['type'],$typeInfo)){
                $typeInfo[] = $val['type'];
                $list[$val['type']]['type'] = $val['type'];
            }
        }
        foreach ($orders as $key=>$val) {
            foreach ($list as $ke=>$va) {
                if($val['type'] == $ke){
//                    if($ke == 32){
//                        if($val['money'] > 0){
//                            $list['10']['type'] = 10;
//                            $list['10']['total_money'] += $val['money'];
//                            $list['10']['cnt'] += 1;
//                        } else {
//                            $list['11']['type'] = 11;
//                            $list['11']['total_money'] += abs($val['money']);
//                            $list['11']['cnt'] += 1;
//                        }
//                    } else {
                        $list[$ke]['total_money'] += $val['money'];
                        $list[$ke]['cnt'] += 1;
//                    }

                }
            }
        }
//        unset($list['32']);
        return $list;
    }

    //投注订单统计
    public function orderStatistics()
    {
        $start_time = 0;
        $end_time = 0;
        $now_time = time();
        $day_time = strtotime(date('Y-m-d'));
        $deal_time = $day_time + 7200;
    
        if ($now_time < $deal_time) {
            return ['code' => 1, 'msg' => 'Orders from the previous day can be processed only after 2 o\'clock every day'];
        }
    
        $sql = "select max(create_time) as times from un_orders_day";
        $create_time = $this->db->getone($sql);
        if (empty($create_time['times'])) {
            $sql = "select min(addtime) as starttime from un_orders";
            $create_time = $this->db->getone($sql);
            if (empty($create_time['starttime'])) {
                return ['code' => 1, 'msg' => 'The data is wrong'];
            }
            $create_time['times'] = strtotime(date('Y-m-d',$create_time['starttime'])) - 1;
        }
        $start_time = $create_time['times'];
        $end_time   = $start_time + 86401;
    
        while ($end_time <= $day_time) {
            $sql = "SELECT
            IFNULL(COUNT(id),0) AS order_count,
            IFNULL(SUM(IF((state = 0),1,0)),0) AS betting_count,
            IFNULL(SUM(IF((state = 0),money,0)),0) AS betting_sum,
            IFNULL(SUM(award),0) AS award_sum,
            IFNULL(SUM(IF((state = 1),money,0)),0) AS cancel_sum
            FROM un_orders WHERE reg_type NOT IN (0, 8,9,11) AND `addtime` > {$start_time} AND `addtime` < {$end_time}";
    
            $orderSum = $this->db->getone($sql);
    
            if (empty($orderSum)) continue;
    
            $orderSum['create_time'] = $end_time - 1;
    
            $ret = $this->db->insert('un_orders_day', $orderSum);
            if (!$ret) {
                return ['code' => 0, 'msg' => 'Data processing error in the betting order form, please click to continue processing'];
            }
            $start_time += 86400;
            $end_time   += 86400;
        }
    
        return ['code' => 0, 'msg' => 'Data processing of betting order form completed'];
    }
    
    //线上、线下充值统计
    public function rechargeStatistics()
    {
        $start_time = 0;
        $end_time = 0;
        $now_time = time();
        $day_time = strtotime(date('Y-m-d'));
        $deal_time = $day_time + 7200;
    
        if ($now_time < $deal_time) {
            return ['code' => 1, 'msg' => 'Orders from the previous day can be processed only after 2 o\'clock every day'];
        }
    
        $sql = "select max(create_time) as times from un_account_recharge_day";
        $create_time = $this->db->getone($sql);
        if (empty($create_time['times'])) {
            $sql = "select min(addtime) as starttime from un_account_recharge";
            $create_time = $this->db->getone($sql);
            if (empty($create_time['starttime'])) {
                return ['code' => 1, 'msg' => 'The data is wrong'];
            }
            $create_time['times'] = strtotime(date('Y-m-d',$create_time['starttime'])) - 1;
        }
    
        $start_time = $create_time['times'];
        $end_time   = $start_time + 86401;
    
        while ($end_time <= $day_time) {
            $sql = "SELECT
            IFNULL(COUNT(ar.id),0) AS recharge_count,
            IFNULL(SUM(IF((ar.status = 1),IF((ar.pay_type = 67 OR ar.pay_type = 68 OR ar.pay_type = 75 OR ar.pay_type = 139 OR ar.pay_type = 214 OR ar.pay_type = 215),1,0),0)),0) AS online_count,
            IFNULL(SUM(IF((ar.status = 0),IF((ar.pay_type = 67 OR ar.pay_type = 68 OR ar.pay_type = 75 OR ar.pay_type = 139 OR ar.pay_type = 214 OR ar.pay_type = 215),1,0),0)),0) AS online_pending_count,
            IFNULL(SUM(IF((ar.status = 2),IF((ar.pay_type = 67 OR ar.pay_type = 68 OR ar.pay_type = 75 OR ar.pay_type = 139 OR ar.pay_type = 214 OR ar.pay_type = 215),1,0),0)),0) AS online_reject_count,
            IFNULL(SUM(IF((ar.status = 1),IF((ar.pay_type = 67 OR ar.pay_type = 68 OR ar.pay_type = 75 OR ar.pay_type = 139 OR ar.pay_type = 214 OR ar.pay_type = 215),ar.money,0),0)),0) AS online_sum,
            IFNULL(SUM(IF((ar.status = 0),IF((ar.pay_type = 67 OR ar.pay_type = 68 OR ar.pay_type = 75 OR ar.pay_type = 139 OR ar.pay_type = 214 OR ar.pay_type = 215),ar.money,0),0)),0) AS online_pending_sum,
            IFNULL(SUM(IF((ar.status = 2),IF((ar.pay_type = 67 OR ar.pay_type = 68 OR ar.pay_type = 75 OR ar.pay_type = 139 OR ar.pay_type = 214 OR ar.pay_type = 215),ar.money,0),0)),0) AS online_reject_sum,
            IFNULL(SUM(IF((ar.status = 1),IF((ar.pay_type = 35 OR ar.pay_type = 36 OR ar.pay_type = 37 OR ar.pay_type = 125 OR ar.pay_type = 202 OR ar.pay_type = 211 OR ar.pay_type = 213),1,0),0)),0) AS offline_count,
            IFNULL(SUM(IF((ar.status = 0),IF((ar.pay_type = 35 OR ar.pay_type = 36 OR ar.pay_type = 37 OR ar.pay_type = 125 OR ar.pay_type = 202 OR ar.pay_type = 211 OR ar.pay_type = 213),1,0),0)),0) AS offline_pending_count,
            IFNULL(SUM(IF((ar.status = 2),IF((ar.pay_type = 35 OR ar.pay_type = 36 OR ar.pay_type = 37 OR ar.pay_type = 125 OR ar.pay_type = 202 OR ar.pay_type = 211 OR ar.pay_type = 213),1,0),0)),0) AS offline_reject_count,
            IFNULL(SUM(IF((ar.status = 1),IF((ar.pay_type = 35 OR ar.pay_type = 36 OR ar.pay_type = 37 OR ar.pay_type = 125 OR ar.pay_type = 202 OR ar.pay_type = 211 OR ar.pay_type = 213),ar.money,0),0)),0) AS offline_sum,
            IFNULL(SUM(IF((ar.status = 0),IF((ar.pay_type = 35 OR ar.pay_type = 36 OR ar.pay_type = 37 OR ar.pay_type = 125 OR ar.pay_type = 202 OR ar.pay_type = 211 OR ar.pay_type = 213),ar.money,0),0)),0) AS offline_pending_sum,
            IFNULL(SUM(IF((ar.status = 2),IF((ar.pay_type = 35 OR ar.pay_type = 36 OR ar.pay_type = 37 OR ar.pay_type = 125 OR ar.pay_type = 202 OR ar.pay_type = 211 OR ar.pay_type = 213),ar.money,0),0)),0) AS offline_reject_sum
            FROM un_account_recharge AS ar LEFT JOIN un_user AS u ON ar.user_id = u.id WHERE u.reg_type NOT IN(8,9,11) AND ar.`addtime` > {$start_time} AND ar.`addtime` < {$end_time}";
    
            $rechargeSum = $this->db->getone($sql);
    
            if (empty($rechargeSum)) continue;
    
            $rechargeSum['create_time'] = $end_time - 1;
    
            $ret = $this->db->insert('un_account_recharge_day', $rechargeSum);
            if (!$ret) {
                return ['code' => 0, 'msg' => 'Data processing error in the recharge order form, please click to continue processing'];
            }
            $start_time += 86400;
            $end_time   += 86400;
        }
    
        return ['code' => 0, 'msg' => 'Data processing of the recharge order form is completed'];
    }
    
    //提现表数据统计
    public function drawalStatistics()
    {
        $start_time = 0;
        $end_time = 0;
        $now_time = time();
        $day_time = strtotime(date('Y-m-d'));
        $deal_time = $day_time + 7200;
    
        if ($now_time < $deal_time) {
            return ['code' => 1, 'msg' => 'Orders from the previous day can be processed only after 2 o\'clock every day'];
        }
    
        $sql = "select max(create_time) as times from un_account_cash_day";
        $create_time = $this->db->getone($sql);
        if (empty($create_time['times'])) {
            $sql = "select min(addtime) as starttime from un_account_cash";
            $create_time = $this->db->getone($sql);
            if (empty($create_time['starttime'])) {
                return ['code' => 1, 'msg' => 'The data is wrong'];
            }
            $create_time['times'] = strtotime(date('Y-m-d',$create_time['starttime'])) - 1;
        }
    
        $start_time = $create_time['times'];
        $end_time   = $start_time + 86401;
    
        while ($end_time <= $day_time) {
            $sql = "SELECT
            IFNULL(COUNT(id),0) AS drawal_count,
            IFNULL(SUM(IF((STATUS = 1),1,0)),0) AS success_count,
            IFNULL(SUM(IF((STATUS = 0),1,0)),0) AS pending_count,
            IFNULL(SUM(IF((STATUS = 2),1,0)),0) AS reject_count,
            IFNULL(SUM(IF((STATUS = 3),1,0)),0) AS cancel_count,
            IFNULL(SUM(IF((STATUS = 1),money,0)),0) AS success_sum,
            IFNULL(SUM(IF((STATUS = 0),money,0)),0) AS pending_sum,
            IFNULL(SUM(IF((STATUS = 2),money,0)),0) AS reject_sum,
            IFNULL(SUM(IF((STATUS = 3),money,0)),0) AS cancel_sum
            FROM un_account_cash WHERE `addtime` > {$start_time} AND `addtime` < {$end_time}";
    
            $drawalSum = $this->db->getone($sql);
    
            if (empty($drawalSum)) continue;
    
            $drawalSum['create_time'] = $end_time - 1;
    
            $ret = $this->db->insert('un_account_cash_day', $drawalSum);
            if (!$ret) {
                return ['code' => 0, 'msg' => 'There is an error in the data processing of the withdrawal order form, please click to continue processing'];
            }
            $start_time += 86400;
            $end_time   += 86400;
        }
    
        return ['code' => 0, 'msg' => 'Data processing of the withdrawal order form is completed'];
    }

}
