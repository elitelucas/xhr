<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/19
 * Time: 11:06
 * desc: 账户资金提现
 */
class AccountCashModel extends CommonModel{
    protected $table = '#@_account_cash';

    /**
     * 获取是否是首提
     * @return int
     */
    public function getIsFirstCash($uid)
    {
        $sql = "SELECT COUNT(id) FROM un_account_cash WHERE user_id = '{$uid}' AND (status = 1 OR status = 4)";
        $cnt = $this->db->result($sql);
        return $cnt;
    }
}