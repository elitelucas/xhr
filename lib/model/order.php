<?php

/**
 * Created by PhpStorm.
 * User: wangrui
 * Date: 2016/11/18
 * Time: 21:05
 * desc: 订单model
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class OrderModel extends CommonModel {

    protected $table = '#@_orders';       //订单表
    protected $table1 = '#@_lottery_type'; //彩票类别表
    protected $table2 = '#@_open_award';   //开奖表
    protected $table4 = '#@_account';      //账户表
    protected $table6 = '#@_config';

    /**
     * 获取投注记录
     * @param type $group(搜索条件)
     */
    public function betList($group) {
        $sql = "select id,issue,lottery_type,room_no,addtime,way,money,award_state,state,award from un_orders where 1=1";

        if (!empty($group['start_time'])) {
            $time = strtotime($group['start_time']);
            $sql .= " and addtime > $time ";
        }
        if (!empty($group['end_time'])) {
            $time = strtotime($group['end_time']." 23:59:59");
            $sql .= " and addtime < $time ";
        }
        $sql .= " AND user_id={$group['userId']}";
        if (!empty($group['status'])) {
            if ($group['status'] == 1) {//status1 => award_state2 已中奖
                $sql .= " and award_state = 2 ";
            } elseif ($group['status'] == 2) {//status2 => award_state1 未中奖
                $sql .= " and award_state = 1 ";
            } elseif ($group['status'] == 3) {//status3 => award_state0 未中奖
                $sql .= " and award_state = 0 and state = 0 ";
            } elseif ($group['status'] == 4) {//status4 => state1 撤单
                $sql .= " and state = 1 ";
            } elseif ($group['status'] == 5) {//status4 => state1 和局
                $sql .= " and award_state = 3 ";
            }
        }
        if (!empty($group['type'])) {
            $sql .= " and lottery_type = {$group['type']} ";
        }

        //排序
        $sql .= " ORDER BY id DESC ";

        //分页数据
        $start = ($group['page'] - 1) * $group['pageCnt'];
        $sql .= " limit $start,{$group['pageCnt']}";
       // echo $sql;
        $list = $this->db->getall($sql);
        return $list;
    }
    
    /**
     * 获取投注总数
     * @param type $group(搜索条件)
     */
    public function betListCnt($group) {
        $sql = "select count(id) as count from un_orders where 1=1";
    
        if (!empty($group['start_time'])) {
            $time = strtotime($group['start_time']);
            $sql .= " and addtime > $time ";
        }
        if (!empty($group['end_time'])) {
            $time = strtotime($group['end_time']." 23:59:59");
            $sql .= " and addtime < $time ";
        }
        $sql .= " AND user_id={$group['userId']}";
        if (!empty($group['status'])) {
            if ($group['status'] == 1) {//status1 => award_state2 已中奖
                $sql .= " and award_state = 2 ";
            } elseif ($group['status'] == 2) {//status2 => award_state1 未中奖
                $sql .= " and award_state = 1 ";
            } elseif ($group['status'] == 3) {//status3 => award_state0 未中奖
                $sql .= " and award_state = 0 and state = 0 ";
            } elseif ($group['status'] == 4) {//status4 => state1 撤单
                $sql .= " and state = 1 ";
            } elseif ($group['status'] == 5) {//status4 => state1 和局
                $sql .= " and award_state = 3 ";
            }
        }
        if (!empty($group['type'])) {
            $sql .= " and lottery_type = {$group['type']} ";
        }

        $cnt = $this->db->getone($sql);
        return $cnt['count'];
    }

    /**
     * 获取投注详情
     * @param id 订单ID
     * @param userId 用户ID
     */
    public function detail($group) {
        $id = $group['id'];
        $user_id = $group['user_id'];
        $sql = "select o.order_no,o.Issue,o.money,a.money as amoney,oa.state from " . $this->table . " as o "
                . " left join " . $this->table4 . " as a on o.user_id = a.user_id "
                . " left join " . $this->table2 . " as oa on o.Issue = oa.issue "
                . " where o.id={$id} and o.user_id={$user_id}";

        $list = $this->db->getall($sql);
        if (empty($list)) {
            return;
        }
        $info = $list[0];
        $data = array();
        $data['order_sn'] = $info['order_no'];
        $data['issue'] = $info['Issue'];
        $data['money'] = $info['money'];
        $data['balance'] = $info['amoney'];
        $data['content'] = '待定';
        $data['result'] = '';
        if ($info['state'] == 0) {
            $data['result'] = '已开奖';
        } elseif ($info['state'] == 1 || $info['state'] == 3) {
            $data['result'] = '待开奖';
        } elseif ($info['state'] == 2) {
            $data['result'] = '未开奖';
        }

        return $data;
    }

    /**
     *
     * @description 获取用户投注金额之和
     * @author king
     * @param where
     * @return string
     */
    public function getUserBetMoneySum($where=" "){
        $sql="SELECT sum(money)AS money_sum  FROM un_orders WHERE {$where} LIMIT 1";
        $money=$this->db->getone($sql);
        return !empty($money['money_sum'])?$money['money_sum']:'0.00';
    }

    /**
     * 将牛牛的数字开奖结果转换为牌面、胜负以及牛数
     * 2018-03-07
     */
    public function getNNLotteryResult($lottery_result)
    {
        $mode = 2;
        $lottery_result_obj = getShengNiuNiu($lottery_result, $mode);

        $new_lottery_result = "{$lottery_result_obj['sheng']} [{$lottery_result_obj['data']['lottery_pai']}] {$lottery_result_obj['data']['lottery_niu']}";

        return $new_lottery_result;
    }
}
