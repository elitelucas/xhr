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

class UserBankModel extends CommonModel {
    protected $table = '#@_user_bank';
    public function getBindBank($uid)
    {
        $arr = [];
        $redis = initCacheRedis();//初始化redis
        $GameConfig= $redis -> hGetAll("Config:100010");//获取是否可以更改银行卡
        $sql = "select a.*,b.name as bankName from {$this->table} as a left join un_dictionary as b on a.bank = b.id where user_id = {$uid} and state = 1";
        $res = $this->db->getall($sql);
        if(!empty($res)) {
            foreach ($res as &$val) {
                if ($val['bank'] == 1) {
                    $val['is_setBank'] = $GameConfig['value']?$GameConfig['value']:0;
                    $val['branch'] = json_decode($val['branch']);
                    $arr['WeChat'] = $val;
                } else if ($val['bank'] == 2) {
                    $val['is_setBank'] = $GameConfig['value']?$GameConfig['value']:0;
                    $val['branch'] = json_decode($val['branch']);
                    $arr['Alipay'] = $val;
                } else if ($val['bank'] == 124) {
                    $val['is_setBank'] = $GameConfig['value']?$GameConfig['value']:0;
                    $val['branch'] = json_decode($val['branch']);
                    $arr['QQ'] = $val;
                } else {
                    $val['is_setBank'] = $GameConfig['value']?$GameConfig['value']:0;
                    $arr['UnionPay'] = $val;
                }
            }
        }
        deinitCacheRedis($redis);//关闭redis链接
        return $arr;
    }

    public function getBankById($id,$uid)
    {
        $sql = "select a.*,b.name as bankName from {$this->table} as a left join un_dictionary as b on a.bank = b.id where user_id = {$uid} and state = 1 and a.id = {$id}";
        $res = $this->db->getone($sql);
        if($res['bank'] == 1 || $res['bank'] == 2)
        {
            return json_decode($res['branch'],true);
        }
        else
        {
            return $res;
        }
    }

}