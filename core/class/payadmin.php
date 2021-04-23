<?php

/**
 *    Author: Martin
 *    CreateDate: 2017/08/24 15:29
 *    description: 支付管理模块
 */
class PayAdmin
{
    //支付
    public function doPay($data)
    {

        //验证输入参数
        if (!is_array($data) || empty($data['payment_id']) || !is_numeric($data['payment_id'])) {
            return ['code' => -1, 'data' => [200011, '输入参数错误']];
        }
        //输入金额格式判断
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $data['money']) || ($data['money'] == 0
            || $data['money'] == '0.0' || $data['money'] == '0.00')) {
                return ['code' => -1, 'data' => [200012, '输入的资金有误']];
        }
    
            
        //获取充值方式类型（在线还是线下）
        $paymentInfo = D('paymentconfig')->getOneCoupon('type,nid', array('id' => $data['payment_id']));
        if (empty($paymentInfo)) {
            return ['code' => -1, 'data' => [200013, '支付银行类型不存在']];
        }

        if(in_array($paymentInfo['type'], array(67, 68, 75, 214, 215))) {
            $data['nid'] = $paymentInfo['nid'];

            //如果是在线支付，调用onlinePay
            $result = $this->onlinePay($data);
        }else if(in_array($paymentInfo['type'], array(35, 36, 37, 202, 211, 213))){
    
            //如果是线下支付，调用offlinePay
            $result = $this->offlinePay($data);
        }else {
            
            return ['code' => -1, 'data' => [200014, '支付方式错误（系统支持线上和线下）']];
        }

        return $result;
    }
    
    /*
     * 第三方回调
     * @data 数组
     */
    public function doPaycallBack($data)
    {
    
        //验证url参数payment_id
        if (!is_array($data) || empty($data['payment_id']) || !is_numeric($data['payment_id'])) {
            return 'ERROR';
        }
        //获取nid
        $nidData = D('paymentconfig')->getOneCoupon('nid,config', array('id' => $data['payment_id']));
        if(empty($nidData)){
            lg('payerror', 'payment_id = ' . $data['payment_id'] .'的回调的nid错误');
            return;
        }
        
        $data['config'] = $nidData['config'];

        //实例化工厂类
        $factory = O('pay/payfactory', '', 1);
        $payment = $factory->getInterface($nidData['nid']);

        return $payment->doPaycallBack($data);
    }
    
    /*
     * 在线支付
     * @ nid 支付平台商标识
     */
    
    public function onlinePay($payData)
    {
        //验证输入参数
        if (!is_array($payData) || empty($payData['nid'])) {
            return ['code' => -1, 'data' => [200015, '在线支付参数错误']];
        }

        //实例化工厂类
        $factory = O('pay/payfactory', '', 1);
        $payment = $factory->getInterface($payData['nid']);
//        payLog('jy.txt',print_r($payment,true).'---91--'.$payData['nid']);
        return $payment->doPay($payData);
    }

    /**
     * 线下支付
     * @method get /index.php?m=api&c=recharge&a=rechargeOffline&token=b5062b58d2433d1983a5cea888597eb6&payment_nid=1&money=1&extra_code=1
     * @param
     * @return
     */
    public function offlinePay($data)
    {
        //验证token
        $this->checkAuth();
    
        //初始化redis
        $redis = initCacheRedis();
        $recharge_time= $redis -> HMGet("Config:recharge_time",array('value'));
    
        $time = $redis -> ttl("user_recharge:" . $data['user_id']);
        if($time >= 0){
            ErrorCode::errorResponse(100025, '为了避免重复提交，'.$recharge_time['value'].' 秒时间内只能提交一次充值，距离下次充值剩余 '.$time.' 秒时间');
        }
    
        $payIds = $redis->lRange("paymentConfigIds", 0, -1);
        if(!in_array($data['payment_id'],$payIds)){
            ErrorCode::errorResponse(100025, '支付方式不存在');
        }
    
        //$res = $redis->hGetAll("paymentConfig:" . $pid);
        $Config= $redis -> HMGet("Config:recharge",array('value'));
        $res['lower_limit'] = $Config['value'];
    
        if($data['money']<$res['lower_limit']){
            ErrorCode::errorResponse(100026, '支付金额不小于 '.$res['lower_limit']);
        }
    
        $sql = "SELECT remark FROM `un_account_recharge` WHERE BINARY `remark` = '{$data['code']}'";
        $remark = O("model")->db->result($sql);
        if($remark){
            ErrorCode::errorResponse(100027, '该附加码已生成过订单,请不要重复提交,需要再次充值请重新进入该页面 ');
        }
        //生成随机订单号
        $orderSn = "CZ" . $this->orderSn();
        $orderData = array(
            'order_sn' => $orderSn,
            'payment_id' => $data['payment_id'],
            'user_id' => $data['user_id'],
            'money' => $data['money'],
            'remark' => $data['code'],
            'addtime' => SYS_TIME
        );
    
        //生成订单
        $res = $this->model->add($orderData);
        if (!$res) {
            ErrorCode::errorResponse(100016, '生成订单失败');
        }
        $redis -> hMset("user_recharge:".$this->userId,array('now_time',SYS_TIME));
        //线下充值提示音入库操作
        $redis -> expire("user_recharge:".$this->userId,$recharge_time['value']);
        //关闭redis链接
        deinitCacheRedis($redis);
        //添加后台提示信息
        $map = array();
        $map['id'] = $res;
        $map['user_id'] = $data['user_id'];
        $map['money'] = $data['money'];
        $map['type'] = 1;
        D('user')->setRechargeMusic($map);

        ErrorCode::successResponse(array('order_sn' => $orderSn));
    }
    
    
   
   /* 
    //生成订单
    public function makeOrder($data)
    {

        //生成随机订单号
        $orderSn = "CZ" . $this->orderSn();

        $createTime = time();

        //查库判断是否首充
        $status = D('account_recharge')->where([ 'user_id' => $data['user_id'],'status' =>1 ])->find();
        if(is_null($status)){
            $is_first = 1;
        }
        else{
            $is_first = 0;
        }


        //判断线上线下充值
        $payment = D('payment_config')->field('type,nid,behind_config,qrcode')->where(['id' => $data['payment_id']])->find();
//        $nid = $payment['nid'];
//        $type2= $payment['type'];
//        $qrcode = $payment['qrcode'];

        $behind_config = json_decode($payment['behind_config'],true);
        $minMoney = $behind_config['charge_lowerlimit'];
        $maxMoney = $behind_config['charge_toplimit'];
        $charge_status = $behind_config['charge_status'];

        //如果开启充值限额
        if($charge_status == 1)
        {
            if($minMoney>0){
                //如果充值金额小于后台配置充值金额下限，抛出异常
                if($data['money'] < $minMoney){
                    throw new \Exception(ErrorCode::PAY_MIN_MONEY_ERROR_MSG,ErrorCode::PAY_MIN_MONEY_ERROR);
                }
            }
            if($maxMoney>0){
                //如果充值金额大于后台配置充值金额上限，抛出异常
                if($data['money'] > $maxMoney){
                    throw new \Exception(ErrorCode::PAY_MAX_MONEY_ERROR_MSG,ErrorCode::PAY_MAX_MONEY_ERROR);
                }
            }
        }


        $reward = 0;
        $fee = 0;


        $addData = array(
            'payment_id' => $data['payment_id'],
            'money' => $data['money'],
            'remark' => $data['remark'],
            'order_sn' => $orderNumber,
            'addtime' => $createTime,
            'user_id' => $data['user_id'],
            'status' => '0',
            'addip' => get_client_ip(),
            'fee' => $data['money']*$fee,
            'reward' => $data['money']*$reward,
            'is_first' => $is_first,
            'activity_type' => $data['type']?:0
        );

        $status = M('account_recharge')->add($addData);
        if($status > 0){
            return $orderNumber;
        }
        else{
            throw new \Exception(ErrorCode::MAKE_ORDER_FAILS_ERROR_MSG,ErrorCode::MAKE_ORDER_FAILS_ERROR);
        }

    }
    */


    

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
     * 检查支付数据完整性
     * @param $input  提交上来的支付数据字段
     * @param $need   需要检验的字段
     * @param $verify 需要检验字段的值不能为空或不能为0
     */
    public function checkPayInput($input, $need, $verify=array())
    {
        if (empty($need) || !is_array($need)){
            return;
        }
    
        if(empty($input) || !is_array($input)){
            ErrorCode::errorResponse(ErrorCode::SHORT_PARAMS,'缺少参数：' . implode('，', $input));
        }
    
        foreach ($need as $v)
        {
            if(!array_key_exists($v, $input))
            {
                ErrorCode::errorResponse(ErrorCode::SHORT_PARAMS,'缺少参数：' . $v);
            }
            if(($verify == 'all') || in_array($v,$verify)){
                $temp = trim($input[$v]);
                if(empty($temp) && $input[$v] != '0')
                {
                    ErrorCode::errorResponse(100011,'参数不能为空：' . $v);
                }
            }
        }
    }

}