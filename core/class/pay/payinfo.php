<?php

/**
 *	Author: Martin
 * 	CreateDate: 2017/08/24 16:40
 *  description: 支付信息模块
 */

abstract class PayInfo
{
    public $db;
    private $table = "un_payment_config";
    protected $tableTTflCfg = 'un_ttfl_cfg';
    protected $tableTTflLog = 'un_ttfl_log';
    protected $table2 = '#@_account_recharge';

    public function __construct()
    {
        $this->db = getconn();
    }
    
    // 支付
    public abstract function doPay($data);

    //回调
    public abstract function doPaycallBack($data);

    // 生成订单
    public function makeOrder($data)
    {
        //生成随机订单号
        $orderSn = "CZ" . $this->orderSn();

        $createTime = time();

        $sql = "SELECT type,name,bank_id FROM un_payment_config WHERE `id` = {$data['payment_id']}";
        $pay_config = O('model')->db->getOne($sql);
        //生成订单
        $orderData = array(
            'order_sn'  => $orderSn,
            'payment_id' => $data['payment_id'],
            'pay_type'  => $pay_config['type'],
            'bank_id'   => $pay_config['bank_id'],
            'bank_name' => $pay_config['name'],
            'user_id'   => $data['user_id'],
            'money'     => $data['money'],
            'addtime'   => SYS_TIME,
            'addip'     => ip()
        );
        
        //生成订单
        $result = D('accountRecharge')->add($orderData);
        if (!$result) {
            return false;
        }
        
        return $orderSn;
    }

    /**
     * 生成随机订单号
     * @return $orderSn string
     */
    private function orderSn($length = 3) {
        /*$yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
         $orderSn = $yCode[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
         return $orderSn;*/
        $min = pow(10 , ($length - 1));
        $max = pow(10, $length) - 1;
        return date('YmdHis',time()).mt_rand($min, $max);  //当前时间加上3位随机数
    }
    
    /**
     * 随机生成字符串(默认16)
     * @return string 生成的字符串
     */
    function getRandomStr($num = 16)
    {
    
        $str = "";
        $str_str = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_str) - 1;
        for ($i = 0; $i < $num; $i++) {
            $str .= $str_str[mt_rand(0, $max)];
        }
        return $str;
    }

    /**
     *实际支付金额与支付前输入金额不同
     * 或者回调时实际支付金额与支付前输入金额不同
     *更改充值金额
     */


    function changeAmount($ordersn,$money)
    {
        $result = D('accountRecharge')->save(['money'=>$money],['order_sn'=>$ordersn]);
    }
}