<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/18
 * Time: 18:09
 * desc; 充值记录表
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class AccountRechargeModel extends CommonModel {

    protected $table = '#@_account_recharge';
    protected $table1 = '#@_payment_config';

    /**
     * 根据充值ID获取充值详情
     */
    public function detail($recharge_id, $userId) {
        $info = $this->db->getall("select a.order_sn,a.addtime,a.status,a.remark,p.name,b.account from " . $this->table . " as a left join #@_payment_config as p "
                . " on a.payment_id = p.id left join #@_user_bank as b on a.user_id = b.user_id  AND b.state = 1 where a.id = $recharge_id and a.user_id = $userId");
        return $info[0];
    }

    /**
     * 根据用户ID获取用户充值记录
     * userId:用户ID
     * page:页码
     * pageCnt:每页展示数据
     */
    public function rechargeList($userId, $page, $pageCnt) {
        if($page<1){
            $page = 1;
        }
        $start = ($page - 1) * $pageCnt;
        $info = $this->db->getall("select r.id,r.addtime,r.money,r.status,p.name from " . $this->table . " as r"
                . " left join ".$this->table1." as p on r.payment_id = p.id where r.user_id = $userId limit $start,$pageCnt");
        foreach ($info as $key => $value) {
            $info[$key]['addtime'] = date('Y-m-d H:i:s', $value['addtime']);
        }
        return $info;
    }

    /**
     * 根据充值单号获取充值详情
     */
    public function osDetail($recharge_id, $userId) {
        $info = $this->db->getall("select a.money,p.name,b.account,a.status from " . $this->table . " as a left join #@_payment_config as p "
                . " on a.payment_id = p.id left join #@_user_bank as b on a.user_id = b.user_id where a.order_sn = '$recharge_id' and a.user_id = $userId");
        return $info[0];
    }
    
    /**
     * 根据充值单号获取充值详情
     */
    public function getChargeDetail($order_id, $userId) {
        $orderDetail = $this->db->getone("select o.id, o.payment_id, o.status, o.money, o.remark, pc.name as pay_name, pc.bank_id, pc.config, pc.prompt, pc.bank_link from " . $this->table . " as o 
                                        left join #@_payment_config as pc on o.payment_id = pc.id 
                                        where o.order_sn = '" .$order_id . "' and o.user_id = " . $userId);
        
        return $orderDetail;
    }

    /**
     * 获取是否是首充
     * @return int
     */
    public function getIsFirstRecharge($uid)
    {
        $sql = "SELECT COUNT(id) FROM `un_account_recharge` WHERE `user_id` = '{$uid}' AND `status` = '1' ";
        $cnt = $this->db->result($sql);
        return $cnt;
    }
    
    /**
     * 修改充值订单状态
     */
    public function setRechargeStatus($order_id, $status)
    {
        $time = time();
        $sql = "UPDATE `un_account_recharge` SET status = {$status},addtime = $time WHERE order_sn = '{$order_id}'";
        $cnt = $this->db->exec($sql);
        return $cnt;
    }
}
