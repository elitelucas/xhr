<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/15 15:06
 * @description 充值支付核心接口
 */
//include_cache(S_CORE. "class". DS. 'withdraw' . DS . 'withdrawfactory.php');

class PayAdminModel
{
    public $db;
    private $table = "un_payment_config";
    protected $tableTTflCfg = 'un_ttfl_cfg';
    protected $tableTTflLog = 'un_ttfl_log';
    protected $table2 = '#@_account_recharge';

    //返回获取的支付信息
    public $retArr = [
        'code' => 0,
        'msg' => '',
        'data' => []
    ];
    
    public function __construct()
    {
        $this->db = getconn();
    }

    /**
     * 获取支付信息
     * @param array $data 
     * @return array $this->retArr
     */
    public function doPay($data)
    {
        //验证输入参数
        if (!is_array($data) || empty($data['payment_id']) || !is_numeric($data['payment_id'])) {
            $this->retArr['code'] = 200100;
            $this->retArr['msg']  = 'Input parameter error';
            
            return $this->retArr;
        }
        //输入金额格式判断
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $data['money']) || ($data['money'] == 0
            || $data['money'] == '0.0' || $data['money'] == '0.00')) {
            $this->retArr['code'] = 200101;
            $this->retArr['msg']  = 'The format of the entered funds is incorrect';
            
            return $this->retArr;
        }
            
        //获取充值方式类型（线上or线下）
        $paymentInfo = D('paymentconfig')->getOneCoupon('type,nid, config', array('id' => $data['payment_id']));
        if (empty($paymentInfo)) {
            $this->retArr['code'] = 200102;
            $this->retArr['msg']  = 'The payment method does not exist!';

            return $this->retArr;
        }

        $data['config'] = $paymentInfo['config'];
        if (in_array($paymentInfo['type'], array(67, 68, 75, 139, 214, 215))) {
            //线上支付
            $data['nid'] = $paymentInfo['nid'];

            if ($paymentInfo['type'] == 67) {
                //线上微信支付
                $data['pay_type'] = $data['channel_type'];
                $data['pay_model'] = 1;
            }elseif ($paymentInfo['type'] == 68) {
                //线上支付宝支付
                $data['pay_type'] = $data['channel_type'];
                $data['pay_model'] = 2;
            }elseif ($paymentInfo['type'] == 139) {
                //线上QQ支付
                $data['pay_type'] = $data['channel_type'];
                $data['pay_model'] = 3;
            }elseif ($paymentInfo['type'] == 214) {
                //线上网银//线上银联钱包支付
                $data['pay_type'] = $data['channel_type'];
                $data['pay_model'] = 6;
            }elseif ($paymentInfo['type'] == 215) {
                //线上京东钱包支付
                $data['pay_type'] = $data['channel_type'];
                $data['pay_model'] = 7;
            }else {
                //线上网银
                $data['pay_type'] = $data['channel_type'];
                $data['pay_model'] = 4;
            }

            //调用onlinePay
            $result = $this->onlinePay($data);
        } elseif (in_array($paymentInfo['type'], array(35, 36, 37, 125, 202, 211, 213))) {

            //如果是线下支付，调用offlinePay
            $result = $this->offlinePay($data);
        } else {
            $this->retArr['code'] = 200103;
            $this->retArr['msg']  = '支付方式错误（系统支持线上和线下）';
            
            return $this->retArr;
        }
        
        return $result;
    }

    /**提现免审核调用第三方代付
     *
     **/
    public function autoWithdraw($data)
    {
        dump(45444);
        $factory = O('withdraw/withdrawfactory', '', 1);
        dump($factory);dump(333);
        $factory = new withdrawfactory();
        $data['nid'] = "mi_man_withdraw";
        $withdraw = $factory->getInterface($data['nid']);

        $result =  $withdraw->doWithdraw($data);
        dump($result);exit;
        $a = $this->dealAccountCash($result);
        return $a;
    }

    /**自动提现后处理提现表数据
     *$data  第三方代付返回数据
     */

    public function dealAccountCash($data)
    {
        $id = $data['account_cash_id'];
        if ($data['code']) {
            $insertData = [
                'verifytime' => time(),
                'status' => 5,
                'payment_id' => $data['payment_id'],
                'verifyremark' => json_encode(array('status'=>0,'remark'=>array('admin'=>$data['bank_name'],'remark'=>"Automatic withdrawal failed")),JSON_UNESCAPED_UNICODE)
            ];
            D('accountCash')->save($insertData,['id'=>$id]);
            return false;
        }

        $insertData = [
            'status' => 4,
            'is_read' =>1,
            'verifytime' => time(),
            'payment_id' => $data['payment_id'],
            'verifyremark' => json_encode(array('status'=>4,'remark'=>array('admin'=>$data['bank_name'],'remark'=>"Automatic withdrawal is successful")),JSON_UNESCAPED_UNICODE)

        ];

        $result = D('accountCash')->save($insertData,['id'=>$id]);
//        payLog('tixian.txt',D('accountCash')->lastsql . "===154" . D('accountCash')->getLastSql());
        $account = D('account')->getOneCoupon("money_freeze",['user_id' =>$data['user_id']]);
        $aaa = $this->db->update('#@_account', array('money_freeze' => $account['money_freeze'] - $data['money'] ), array('user_id' => $data['user_id']));

//        payLog('a.txt',print_r($aaa,true). "++++141+++". print_r($insertData,true) . print_r($result). "++++222++");
        if ($result) {
            return true;
        } else {
            return false;
        }


    }
    
    /**
     * 第三方回调处理
     * @param $data array 数组
     */
    public function doPaycallBack($data)
    {
        //验证url参数payment_id
        if (!is_array($data) || empty($data['payment_id']) || !is_numeric($data['payment_id'])) {
            return 0;
        }
        //获取nid
        $nidData = D('paymentconfig')->getOneCoupon('nid,config', array('id' => $data['payment_id']));
        if(empty($nidData)){
            payLog('payerror.log', '回调的nid错误,不存在充值方式或充值方式配置错误,' . print_r($data, true));
            return 0;
        }
        
        $data['config'] = $nidData['config'];

        //实例化工厂类
        $factory = O('pay/payfactory', '', 1);
        $payment = $factory->getInterface($nidData['nid']);

        $orderData = $payment->doPaycallBack($data);
        if (!isset($orderData['code']) || $orderData['code'] != 0) {
            if (!isset($orderData['ret_error'])) {
                return 0;
            }else {
                return $orderData['ret_error'];
            }
        }

        return $this->dealOrder($orderData);
    }
    
    /**
     * 在线支付
     */
    public function onlinePay($payData)
    {    
        //生成订单
        $res = D('accountrecharge')->add($payData);
        return $res;
        //验证输入参数
        // if (!is_array($payData) || empty($payData['nid'])) {
        //     return ['code' => 200110, 'msg' => 'Online payment parameter error'];
        // }
        //实例化工厂类
        // $factory = O('pay/payfactory', '', 1);
//        var_dump($payData['nid']);exit;
        // $payment = $factory->getInterface($payData['nid']);
        // return $payment->doPay($payData);
    }

    /**
     * 线下支付
     * @method get /index.php?m=api&c=recharge&a=rechargeOffline&token=b5062b58d2433d1983a5cea888597eb6&payment_nid=1&money=1&extra_code=1
     * @param
     * @return
     */
    public function offlinePay($data)
    {
        $min_recharge = 0;    //每次充值最小金额限制
        $max_recharge = 0;    //每次充值最大金额限制
        
        $redis = initCacheRedis();  //初始化redis
        $recharge_time= $redis->HMGet("Config:recharge_time",array('value'));
        $config= $redis->HMGet("Config:recharge",array('value'));
        $lower_limit  = $config['value'];
        
        $flag = "user_recharge:" . $data['user_id'];
        if(!superveneLock($flag, $recharge_time['value'], 2)){
            $time = $redis->ttl($flag);
            ErrorCode::errorResponse(100016, 'In order to avoid repeated submissions, only one deposit can be submitted within '.$recharge_time['value'].' seconds, and there is '.$time.' seconds left before the next deposit');
        }

        //$time = $redis->ttl("user_recharge:" . $data['user_id']);
        //if($time >= 0){
        //    ErrorCode::errorResponse(200120, '为了避免重复提交，'.$recharge_time['value'].' 秒时间内只能提交一次充值，距离下次充值剩余 '.$time.' 秒时间');
        //}
        
        //充值限额判断（不能低于0，不能超出银行卡的最高限额）
        $sql = "SELECT type,name,min_recharge, max_recharge, upper_limit, balance, bank_id FROM un_payment_config WHERE `id` = {$data['payment_id']}";
        $pay_config = O('model')->db->getOne($sql);
        if(empty($pay_config)){
            ErrorCode::errorResponse(200121, 'Payment method does not exist');
        }

        if ($pay_config['min_recharge'] == '0.00' && $pay_config['max_recharge'] == '0.00') {
            //$max_recharge = $pay_config['upper_limit'] - $pay_config['balance'];
            $max_recharge = ($pay_config['upper_limit'] - $pay_config['balance']) > $pay_config['min_recharge'] ? ($pay_config['upper_limit'] - $pay_config['balance']) : -1;
        } elseif ($pay_config['min_recharge'] != '0.00' && $pay_config['max_recharge'] == '0.00') {
            $min_recharge = $pay_config['min_recharge'];
            $max_recharge = $pay_config['upper_limit'] - $pay_config['balance'];
        } elseif (($pay_config['upper_limit'] - $pay_config['balance']) < $pay_config['min_recharge']) {
            $max_recharge = -1;
        } elseif ($pay_config['max_recharge'] > ($pay_config['upper_limit'] - $pay_config['balance'])) {
            $min_recharge = $pay_config['min_recharge'];
            $max_recharge = $pay_config['upper_limit'] - $pay_config['balance'];
        } else {
            $min_recharge = $pay_config['min_recharge'];
            $max_recharge = $pay_config['max_recharge'];
        }
        
        if ($max_recharge != -1 && $max_recharge < $lower_limit) {
            $max_recharge = -1;
        }
        
        if ($min_recharge < $lower_limit) {
            $min_recharge = $lower_limit;
        }

        if($data['money'] < $min_recharge){
            ErrorCode::errorResponse(200123, 'The deposit amount is less than the minimum amount:' . $min_recharge);
        }
        
        if ($max_recharge < 0) {
            ErrorCode::errorResponse(200124, 'This deposit method is full, please go back and choose another deposit method to deposit!');
        }
        
        if($data['money'] > $max_recharge){
            ErrorCode::errorResponse(200125, 'The deposit amount is greater than the maximum limit:' . $max_recharge);
        }
        
        $sql = "SELECT remark FROM `un_account_recharge` WHERE BINARY `remark` = '{$data['code']}'";
        $remark = O("model")->db->result($sql);
        if($remark){
            ErrorCode::errorResponse(200126, 'The additional code has already generated an order, please do not submit it repeatedly, please re-enter this page if you need to deposit again');
        }
        //生成随机订单号
        $orderSn = "CZ" . $this->orderSn();
        
        //移动端时，设置附加码
        $data['code'] = $data['user_id'] . $this->getRandomString(6);  //获取附加码


        $orderData = array(
            'order_sn' => $orderSn,
            'payment_id' => $data['payment_id'],
            'pay_type' => $pay_config['type'],
            'bank_id' => $pay_config['bank_id'],
            'bank_name' => $pay_config['name'],
            'user_id' => $data['user_id'],
            'money' => $data['money'],
            'status' => 3,
            'remark' => $data['code'],
            'addip' => ip(),
            'addtime' => SYS_TIME
        );
    
        //生成订单
        $res = D('accountrecharge')->add($orderData);
        if (!$res) {
            ErrorCode::errorResponse(200127, 'Failed to generate order');
        }
        //$redis->hMset("user_recharge:" . $data['user_id'], array('now_time', SYS_TIME));
        //线下充值提示音入库操作
        //$redis->expire("user_recharge:" . $data['user_id'], $recharge_time['value']);

        //关闭redis链接
        deinitCacheRedis($redis);

        //添加后台提示信息
        /*
        $map = array();
        $map['id'] = $res;
        $map['user_id'] = $data['user_id'];
        $map['money'] = $data['money'];
        $map['type'] = 1;
        D('user')->setRechargeMusic($map);
        */

        ErrorCode::successResponse(array('order_sn' => $orderSn, 'code' => $data['code']));
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
     * 生成随机附加码
     * @param $len string
     * @param $chars string
     * @return $str string
     */
    private function getRandomString($len, $chars = null) {
        if (is_null($chars)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        }
        mt_srand(10000000 * (double) microtime());
        for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $lc)];
        }
        return $str;
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
            ErrorCode::errorResponse(ErrorCode::SHORT_PARAMS,'Missing parameters:' . implode('，', $input));
        }
    
        foreach ($need as $v)
        {
            if(!array_key_exists($v, $input))
            {
                ErrorCode::errorResponse(ErrorCode::SHORT_PARAMS,'Missing parameters:' . $v);
            }
            if(($verify == 'all') || in_array($v,$verify)){
                $temp = trim($input[$v]);
                if(empty($temp) && $input[$v] != '0')
                {
                    ErrorCode::errorResponse(100011,'The parameter cannot be empty:' . $v);
                }
            }
        }
    }
    
    
    /**
     * 充值回调验证通过后，充值订单处理
     * @param $code  int 平台充值延签是否通过，0：通过，其他：未通过
     * @param $bank_num  int 平台码，不同平台的唯一码
     * @param $order_no string 充值订单号
     * @param $amount  float   实际充值金额
     * @param $serial_no string 平台流水号
     * @param $ret_error string 该平台回调充值失败时返回码
     * @param $ret_success string 该平台回调充值成功时返回码
     */
    public function dealOrder($data)
    {
        $remark = '';
        //var_dump($data);
        //判断验签是否通过
        if (!isset($data['code']) || $data['code'] != 0) {
            payLog('payerror.log', ($data['bank_num'] + 1) . '：' . $data['bank_name'] . '验签返回数据错误！'  . print_r($data, true));
            if (empty($data['ret_error'])) {
                return;
            }else {
                return $data['ret_error'];
            }
        }

        //判断传参是否正确
        if (empty($data['order_no']) || empty($data['amount']) || empty($data['bank_name']) || !isset($data['serial_no'])) {
                payLog('payerror.log', ($data['bank_num'] + 2) . '：' . $data['bank_name'] . '参数缺少！'  . print_r($data, true));
                if (empty($data['ret_error'])) {
                    return;
                }else {
                    return $data['ret_error'];
                }
        }

        $redis_key = 'dealOrder_'.$data['order_no'];
        $redis = initCacheRedis();
        if($redis->get($redis_key)) {
            return 'operating frequency';
        }
        $redis->set($redis_key,1,10);
        deinitCacheRedis($redis);

        //获取订单数据
        $orderData = D('accountRecharge')->getOneCoupon('id, payment_id, user_id, money, remark, status', array('order_sn' => $data['order_no']));
        if (empty($orderData)) {
            payLog('payerror.log', ($data['bank_num'] + 3) . '：' . $data['bank_name'] . '回调返回数据成功,但订单表没有查到相应的订单号！'  . print_r($data, true));

            return $data['ret_error'];
        }

        if ($orderData['status'] == 1) {
            payLog('payerror.log', ($data['bank_num'] + 4) . '：' . $data['bank_name'] . '回调返回数据成功,但订单表订单已经支付过了！'  . print_r($data, true));
            
            return $data['ret_success'];
        }


        if(bccomp($orderData['money'], $data['amount'],6) != 0 ) {
            $remark = $orderData['remark'] . '充值订单金额为:' . $orderData['money'] . '元，实际充值异步通知金额为:' .  $data['amount'] . '元';
            payLog('payerror.log', ($data['bank_num'] + 5) . '：' . $data['bank_name'] . '回调返回数据成功,但充值金额与订单金额不同！'  . print_r($data, true));
        }


        //通过线上第三方支付成功后，判断是否开启后台线上充值确认功能，如果开启，充值成功后需要管理员手动确认充值订单，资金才会到账
        $autoLineRecharge = $this->db->getone("select value from un_config where nid = 'auto_line_recharge'");
        if ($autoLineRecharge['value'] != 1) {
            $remark .= $orderData['remark'] . '【线上充值备注】用户通过第三方成功充值：' . $data['amount'] . '元';
            if (!empty($data['serial_no'])) {
                $remark .= '，其他参数：' . $data['serial_no'];
            }

            $ret = $this->db->update("un_account_recharge", array("remark" => $remark), array("order_sn" => $data['order_no']));

            //添加后台提示信息
            $map = array();
            $map['id'] = $orderData['id'];
            $map['user_id'] = $orderData['user_id'];
            $map['money'] = $data['amount'];
            $map['type'] = 2;
            D('user')->setRechargeMusic($map);
        } else {
            $db = getconn();
            $db->query('BEGIN');
            try {
                //判断用户是否为分享注册首充
                $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $orderData['user_id']));
    
                if ($shareIdArr['share_id'] != 0) {
                    $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $orderData['user_id']));
                    if (empty($rechargeRecord)) { //首充奖励
                        //初始化redis
                        $redis = initCacheRedis();
                        $fsConfig = $redis -> HMGet("Config:cashBack",array('value'));
                        //关闭redis链接
                        deinitCacheRedis($redis);
    
                        $sql = "SELECT * FROM un_account WHERE user_id = '{$shareIdArr['share_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
                        $accountInfo = $db->getone($sql);
                        //扫码返现百分率
                        $cashback_rate = 0;
                        $cashback_list = json_decode($fsConfig['value'],true);
                        foreach ($cashback_list as $k=>$i){
                            if($i["low"]<=$data['amount']&&$data['amount']<=$i["upper"]){
                                $cashback_rate = $i["rate"];
                            }
                        }
                        $cashback_amount = bcdiv(($cashback_rate*$data['amount']),100,2);
                        $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金
    
                        //生成账户流水
                        $logArr = array(
                            'user_id' => $shareIdArr['share_id'],
                            'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                            'type' => 66,
                            'money' => $cashback_amount,
                            'use_money' => $money,
                            'remark' => '用户id为:' . $shareIdArr['share_id'] . ' 分享奖励:' . $cashback_amount,
                            'verify' => 1,
                            'addtime' => SYS_TIME,
                            'addip' => ip(),
                        );
                        //产生充值流水
                        $logId = D('accountlog')->aadAccountLog($logArr);
                        //更新用户账户金额
                        $res = D('account')->save(array('money' => '+=' . $cashback_amount), array('user_id' => $shareIdArr['share_id']));
                    }
                }
    
                //更新订单记录
                $updateOrder = array(
                    'liushui_sn' => $data['serial_no'],
                    'money'      => $data['amount'],
                    'status'     => 1,
                    'verify_userid' => 'admin',
                    'verify_time' => SYS_TIME
                );
    
                //判断是否是首充
                $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($orderData['user_id']);
                if(!$isFirstRecharge){
                    $verify_remark['FirstRecharge'] = "1";
                    $remark .= ' ' . json_encode($verify_remark);
                    $firstRecharge = "该用户为首次充值 ";
                }else{
                    $firstRecharge = "";
                }
    
                if (!empty($remark)) {
                    $updateOrder['verify_remark'] = $remark;
                }
    
                $sql = "SELECT * FROM un_account WHERE user_id = '{$orderData['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
                $accountInfo = $db->getone($sql);
                $res = D('accountrecharge')->save($updateOrder, ['order_sn' => $data['order_no']]);
                if (!$res) {
                    file_put_contents(S_CACHE . 'log/xhb.log', '订单更新失败:' . date("m-d H:i:s"). '--Order number--' . $data['order_no'] . "\n", FILE_APPEND);
                    return $data['ret_error'];
                }
                //充值成功---判断返利
                //$regId = $this->ttflRegId($data['order_no']);
    
                $this->ttfl($orderData['id']);
                $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $orderData['payment_id']));
                $admin_money = bcadd($data['amount'], $xsBalance['balance'], 2); //公司线上充值对应的资金
                $money = bcadd($data['amount'], $accountInfo['money'], 2); //用户的可用资金
                $logArr = array(
                    'user_id' => $orderData['user_id'],
                    'order_num' => $data['order_no'],
                    'type' => 10,
                    'money' => $data['amount'],
                    'use_money' => $money,
                    'admin_money' => $admin_money,
                    'remark' => $firstRecharge.'线上充值' .$data['amount'] . '元',
                    'verify' => 1,
                    'addtime' => SYS_TIME,
                    'addip' => ip(),
                );
                //产生充值流水
                $logId = D('accountlog')->aadAccountLog($logArr);
                if (!$logId) {
                    payLog('payerror.log', ($data['bank_num'] + 6) . '：' . $data['bank_name'] . '充值流水生成失败！'  . print_r($data, true));
    
                    return $data['ret_error'];
                }
                
                //更新用户账户金额
                $res = D('account')->save(array('money' => '+=' . $data['amount']), array('user_id' => $orderData['user_id']));
                if (!$res) {
                    payLog('payerror.log', ($data['bank_num'] + 7) . '：' . $data['bank_name'] . '账户更新失败！'  . print_r($data, true));
    
                    return $data['ret_error'];
                }
    
                //更新对应线上支付的余额
                $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$data['amount']} WHERE id = {$orderData['payment_id']}";
                $db->query($sql);

                //线上充值送彩金
                $percent = $this->db->result("select value from un_config where nid = 'handsel_set'");
                if($percent>0) {
                    $type = $this->db->result("select payment_id from un_account_recharge where order_sn = '".$data['order_no']."'");
                    $username = $this->db->result("select username from un_user where id ='".$orderData['user_id']."'");
                    $handsel = bcdiv(bcmul($data['amount'],$percent,2),100,2);
                    $order_handsel=[];
                    $order_handsel["user_id"] = $orderData['user_id'];
                    $order_handsel["username"] = $username;
                    $order_handsel["order_id"] = $data['order_no'];
                    $order_handsel["type"] = $type;
                    $order_handsel["percent"] = $percent;
                    $order_handsel["money"] = $data['amount'];
                    $order_handsel["handsel"] = $handsel;
                    $order_handsel["create_time"] = time();

                    $auto_online_handsel = $this->db->result("select value from un_config where nid = 'auto_online_handsel'");
                    if($auto_online_handsel == 1) {
                        $order_handsel["status"] = 1;
                        D('account')->save(array('money' => '+=' . $handsel), array('user_id' => $orderData['user_id']));
                        $acount_log['user_id'] = $orderData['user_id'];
                        $acount_log['order_num'] = $data['order_no'];
                        $acount_log['type'] = 1071;
                        $acount_log['money'] = $handsel;
                        $acount_log['use_money'] = $admin_money + $handsel;
                        $acount_log['remark'] = '用户id为:' . $orderData['user_id'] . ' 充值送彩金:' . $handsel . '成功';
                        $acount_log['verify'] = 0;
                        $acount_log['addtime'] = time();
                        $acount_log['addip'] = ip();
                        D('accountlog')->aadAccountLog($acount_log);
                    }else $order_handsel["status"] = 0;
                    $this->db->insert('un_online_handsel',$order_handsel);
                }

                $db->query('COMMIT');

                //充值加荣誉积分
                $start_time = microtime(true);
                exchangeIntegral($data['amount'], $orderData['user_id'], 1);
                $end_time = microtime(true);
                payLog('paysuccess.log', '更新荣誉积分信息(un_user_amount_total)执行时间：' . getRunTime($end_time,$start_time).';订单号:'.$data['order_no']);

            } catch(Exception $ex) {
                payLog('payerror.log', ($data['bank_num'] + 8) . '：' . $data['bank_name'] . '支付回调操作订单时错误！'  . print_r($data, true) . '错误信息：' . $ex);
                $db->query('ROLLBACK');
                return $data['ret_error'];
            }
        }

        payLog('paysuccess.log', $data['bank_name'] . '充值成功！' . 'Flowing water:' . $data['serial_no'] . ',Order number:' . $data['order_no'] . ',success--money：' . $data['amount']);

        return $data['ret_success'];
    }
    
    //天天返利代码
    //------天天返利赠送金额
    public function ttfl($id)
    {
        $this->zeroTTfl($id); //存在第0次天天返利的情况
    
        $info = $this->db->getone("select * from " . $this->table2 . " where id = $id"); //充值表的一条记录(ID)
        $mainCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 1"); //天天返利活动主条件
        $max_money = $mainCfg['max_money']; //返利上限--需要用累计的
        $low_money = $mainCfg['low_money']; //返利下限
    
        $stime = $mainCfg['start_time'];
        $etime = $mainCfg['end_time'];
        $chargeCntObj = $this->db->getall("select count(*) as cnt,sum(money) as sums from " . $this->table2 . " where user_id = {$info['user_id']} and addtime > $stime and addtime < $etime and status = 1");
    
        $chargeCnt = $chargeCntObj[0]['cnt']; //用户在天天返利条件内的充值次数
        $chargeSum = $chargeCntObj[0]['sums']; //用户在天天返利条件内的充值金额
        $branchCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 0 and cz_cnt = {$chargeCnt}"); //天天返利配置条件
        if (empty($branchCfg)) {
            log_to_mysql('未配置天天返利条件--sql:'.$this->db->getLastSql(), 'payadmin_ttfl_user_'.$info['user_id']);
            return;
        }
        log_to_mysql($branchCfg, 'payadmin_ttfl_user_'.$info['user_id'].'_$branchCfg');
    
        //返利金额
        $rtMoney = 0;
        $t1 = date('Y-m-d H:i:s', $stime);
        $t2 = date('Y-m-d H:i:s', $etime);
        $rtNote = "天天返利活动时间：{$t1}-{$t2}  金额上限：{$max_money}  金额下限：{$low_money};<br>"; //返利备注
        $rtNote .= "满足第" . $branchCfg['cz_cnt'] . "充返利条件  会员本次充值金额{$info['money']}元;<br/>";

        log_to_mysql($rtNote, 'payadmin_ttfl_user_'.$info['user_id'].'_$rtNote_1');
    
        //充值返利
        if ($branchCfg['cz_type'] == 1) {
            $rtNote .= "按充值返利->";
            //①按比例返利
            if ($branchCfg['fl_type'] == 1) {
                $time = floor($info['money'] / $branchCfg['cz_money']);
                $rtNote .= "比例返利(每充值{$branchCfg['cz_money']}返利{$branchCfg['rt_money']}元);<br>";
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $time * $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $branchCfg['cz_money'] * ($branchCfg['rt_money'] / 100 ) * $time;
                }
            }
    
            //②按范围返利
            if ($branchCfg['fl_type'] == 2) {
                $range = json_decode($branchCfg['range'], true);
                foreach ($range as $value) {
                    //满足范围条件
                    if ($value['s_money'] < $info['money'] && $value['e_money'] >= $info['money']) {
                        $rtNote .= "范围返利(充值{$value['s_money']}<X<={$value['e_money']}，返利{$value['rt_money']}元);<br>";
                        if ($value['rt_type'] == 1) { //直接返利
                            $rtMoney = $value['rt_money'];
                        } else { //百分比返利
                            $rtMoney = $info['money'] * $value['rt_money'] / 100;
                        }
                        break;
                    }
                }
            }
        }
    
        //充值次数返利
        if ($branchCfg['cz_type'] == 2) {
            $historyReg = $this->ttflHistoryReg($info['user_id']); //历史成功充值次数
            $rtNote .= "按充值次数返利->历史累计充值{$historyReg}次，返利{$branchCfg['rt_money']}元;<br/>";
            if ($historyReg >= $branchCfg['cz_money']) { //满足条件
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $info['money'] * ($branchCfg['rt_money'] / 100 );
                }
            }
        }
    
        //直属充值返利
        if ($branchCfg['cz_type'] == 3) {
            $sonMoney = $this->ttflSumSon($info['user_id'], $stime, $etime);
            $rtNote .= "按直属充值返利->直属充值{$sonMoney}元;<br/>";
    
            //①按比例返利
            if ($branchCfg['fl_type'] == 1) {
                $time = floor($sonMoney / $branchCfg['cz_money']);
                $rtNote .= "->比例返利(每充值{$branchCfg['cz_money']}返利{$branchCfg['rt_money']}元);<br>";
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $time * $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $branchCfg['cz_money'] * ($branchCfg['rt_money'] / 100 ) * $time;
                }
            }
    
            //②按范围返利
            if ($branchCfg['fl_type'] == 2) {
                $range = json_decode($branchCfg['range'], true);
    
                foreach ($range as $value) {
                    //满足范围条件
                    if ($value['s_money'] < $sonMoney && $value['e_money'] >= $sonMoney) {
                        $rtNote .= "->范围返利(充值{$value['s_money']}<X<={$value['e_money']}，返利{$value['rt_money']}元);<br>";
                        if ($value['rt_type'] == 1) { //直接返利
                            $rtMoney = $value['rt_money'];
                        } else { //百分比返利
                            $rtMoney = $sonMoney * $value['rt_money'] / 100;
                        }
                        break;
                    }
                }
            }
        }
    
        //直属充值次数返利
        if ($branchCfg['cz_type'] == 4) {
            $historyReg = $this->ttflCntSon($info['user_id']); //历史成功充值次数
            $rtNote .= "按直属充值次数返利->直属历史累计充值{$historyReg}次，返利{$branchCfg['rt_money']}元;<br/>";
            if ($historyReg >= $branchCfg['cz_money']) { //满足条件
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $info['money'] * ($branchCfg['rt_money'] / 100 );
                }
            }
        }
    
        if ($rtMoney < $low_money) {//不能低于最小返回金额
            $rtNote .= "返利金额{$rtMoney}元小于最小返利金额{$low_money}元，调整返利为{$low_money}元;<br/>";
            $rtMoney = $low_money;
        }
        $flSum = $this->flSum($info['user_id'], $stime, $etime); //会员已经返利的金额
        $rtNote .= "会员活动期间历史返利金额{$flSum}元;";
        if ($rtMoney + $flSum > $max_money) { //超过最大时候  == 取差值
            $rtMoney = $max_money - $flSum;
            $rtNote .= "会员返利金额将超出返利设置最大金额条件{$max_money}元，调整返利为{$rtMoney}元;<br/>";
        }

        log_to_mysql($rtNote, 'payadmin_ttfl_user_'.$info['user_id'].'_$rtNote_2');

        if ($rtMoney == 0) { //不返利  退出
            log_to_mysql('未达到返利条件或返利金额为0!$rtNote:'.$rtNote, 'payadmin_ttfl_user_'.$info['user_id']);
            return;
        } else {
            $rtNote .= "会员最终返利金额{$rtMoney}元";
        }
        $rtId = $info['user_id'];
        $addtime = time();
    
        $ttflLog = array(
            "cz_money" => $info['money'],
            "get_money" => $rtMoney,
            "order_sum" => $info['order_sn'],
            "user_id" => $rtId,
            "remark" => $rtNote,
            "addtime" => $addtime
        );
        $this->db->insert($this->tableTTflLog, $ttflLog);
    }
    
    //第0次返利赠送金额
    public function zeroTTfl($id) {
        $rt = $this->db->getone("select * from un_ttfl_cfg where main = 0 and cz_cnt = 0");
        if (empty($rt)) { //没有设置0次返利金额
            return;
        }
        $now = time();
        $rts = $this->db->getone("select * from un_ttfl_cfg where main = 1 and start_time < {$now} and end_time > {$now}");
        if (empty($rts)) { //当前充值时间不在天天返利活动日期内
            return;
        }
    
        $info = $this->db->getone("select * from " . $this->table2 . " where id = $id"); //充值表的一条记录(ID)
        $mainCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 1"); //天天返利活动主条件
        $max_money = $mainCfg['max_money']; //返利上限--需要用累计的
        $low_money = $mainCfg['low_money']; //返利下限
    
        $stime = $mainCfg['start_time'];
        $etime = $mainCfg['end_time'];
        if($etime < time() || $stime > time()){
            return;
        }
        $branchCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 0 and cz_cnt = 0"); //天天返利配置条件
        if (empty($branchCfg)) {
            log_to_mysql('未配置天天返利条件--sql:'.$this->db->getLastSql(), 'payadmin_zeroTTfl_user_id_'.$info['user_id']);
            return;
        }

        log_to_mysql($branchCfg, 'zeroTTfl_user_id_'.$info['user_id'].'_$branchCfg');

        //返利金额
        $rtMoney = 0;
        $t1 = date('Y-m-d H:i:s', $stime);
        $t2 = date('Y-m-d H:i:s', $etime);
        $rtNote = "天天返利活动时间：{$t1}-{$t2}  金额上限：{$max_money}  金额下限：{$low_money};<br>"; //返利备注
        $rtNote .= "满足第0充返利条件  会员本次充值金额{$info['money']}元;<br/>";

        log_to_mysql($rtNote, 'payadmin_zeroTTfl_user_id_'.$info['user_id'].'_$rtNote_1');
    
        //充值返利
        if ($branchCfg['cz_type'] == 1) {
            $rtNote .= "按充值返利->";
            //①按比例返利
            if ($branchCfg['fl_type'] == 1) {
                $time = floor($info['money'] / $branchCfg['cz_money']);
                $rtNote .= "比例返利(每充值{$branchCfg['cz_money']}返利{$branchCfg['rt_money']}元);<br>";
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $time * $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $branchCfg['cz_money'] * ($branchCfg['rt_money'] / 100 ) * $time;
                }
            }
    
            //②按范围返利
            if ($branchCfg['fl_type'] == 2) {
                $range = json_decode($branchCfg['range'], true);
                foreach ($range as $value) {
                    //满足范围条件
                    if ($value['s_money'] < $info['money'] && $value['e_money'] >= $info['money']) {
                        $rtNote .= "范围返利(充值{$value['s_money']}<X<={$value['e_money']}，返利{$value['rt_money']}元);<br>";
                        if ($value['rt_type'] == 1) { //直接返利
                            $rtMoney = $value['rt_money'];
                        } else { //百分比返利
                            $rtMoney = $info['money'] * $value['rt_money'] / 100;
                        }
                        break;
                    }
                }
            }
        }
    
        //充值次数返利
        if ($branchCfg['cz_type'] == 2) {
            $historyReg = $this->ttflHistoryReg($info['user_id']); //历史成功充值次数
            $rtNote .= "按充值次数返利->历史累计充值{$historyReg}次，返利{$branchCfg['rt_money']}元;<br/>";
            if ($historyReg >= $branchCfg['cz_money']) { //满足条件
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $info['money'] * ($branchCfg['rt_money'] / 100 );
                }
            }
        }
    
        //直属充值返利
        if ($branchCfg['cz_type'] == 3) {
            $sonMoney = $this->ttflSumSon($info['user_id'], $stime, $etime);
            $rtNote .= "按直属充值返利->直属充值{$sonMoney}元;<br/>";
    
            //①按比例返利
            if ($branchCfg['fl_type'] == 1) {
                $time = floor($sonMoney / $branchCfg['cz_money']);
                $rtNote .= "->比例返利(每充值{$branchCfg['cz_money']}返利{$branchCfg['rt_money']}元);<br>";
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $time * $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $branchCfg['cz_money'] * ($branchCfg['rt_money'] / 100 ) * $time;
                }
            }
    
            //②按范围返利
            if ($branchCfg['fl_type'] == 2) {
                $range = json_decode($branchCfg['range'], true);
    
                foreach ($range as $value) {
                    //满足范围条件
                    if ($value['s_money'] < $sonMoney && $value['e_money'] >= $sonMoney) {
                        $rtNote .= "->范围返利(充值{$value['s_money']}<X<={$value['e_money']}，返利{$value['rt_money']}元);<br>";
                        if ($value['rt_type'] == 1) { //直接返利
                            $rtMoney = $value['rt_money'];
                        } else { //百分比返利
                            $rtMoney = $sonMoney * $value['rt_money'] / 100;
                        }
                        break;
                    }
                }
            }
        }
    
        //直属充值次数返利
        if ($branchCfg['cz_type'] == 4) {
            $historyReg = $this->ttflCntSon($info['user_id']); //历史成功充值次数
            $rtNote .= "按直属充值次数返利->直属历史累计充值{$historyReg}次，返利{$branchCfg['rt_money']}元;<br/>";
            if ($historyReg >= $branchCfg['cz_money']) { //满足条件
                if ($branchCfg['rt_type'] == 1) { //直接返利
                    $rtMoney = $branchCfg['rt_money'];
                } else { //百分比返利
                    $rtMoney = $info['money'] * ($branchCfg['rt_money'] / 100 );
                }
            }
        }
    
        if ($rtMoney < $low_money) {//不能低于最小返回金额
            $rtNote .= "返利金额{$rtMoney}元小于最小返利金额{$low_money}元，调整返利为{$low_money}元;<br/>";
            $rtMoney = $low_money;
        }
        $flSum = $this->flSum($info['user_id'], $stime, $etime); //会员已经返利的金额
        $rtNote .= "会员活动期间历史返利金额{$flSum}元;";
        if ($rtMoney + $flSum > $max_money) { //超过最大时候  == 取差值
            $rtMoney = $max_money - $flSum;
            $rtNote .= "会员返利金额将超出返利设置最大金额条件{$max_money}元，调整返利为{$rtMoney}元;<br/>";
        }

        log_to_mysql($rtNote, 'payadmin_zeroTTfl_user_id_'.$info['user_id'].'_$rtNote_2');

        if ($rtMoney == 0) { //不返利  退出
            log_to_mysql('未达到返利条件或返利金额为0!$rtNote:'.$rtNote, 'payadmin_zeroTTfl_user_id_'.$info['user_id']);
            return;
        } else {
            $rtNote .= "会员最终返利金额{$rtMoney}元";
        }
        $rtId = $info['user_id'];
        $addtime = time();
    
        $ttflLog = array(
            "cz_money" => $info['money'],
            "get_money" => $rtMoney,
            "order_sum" => $info['order_sn'],
            "user_id" => $rtId,
            "remark" => $rtNote,
            "addtime" => $addtime
        );
        $this->db->insert($this->tableTTflLog, $ttflLog);
    }
    
    //会员历史累计充值次数
    public function ttflHistoryReg($user_id) {
        $rt = $this->db->getone("select count(*) as cnt from un_account_recharge where user_id = {$user_id} and status = 1");
        return $rt['cnt'];
    }
    
    //会员已经返利金额
    public function flSum($uId, $stime, $etime) {
        $rt = $this->db->getone("select sum(get_money) as sum from un_ttfl_log where addtime >= $stime and addtime <= $etime and user_id = {$uId}");
        return empty($rt['sum']) ? 0 : $rt['sum'];
    }
    
    //直属充值金额
    public function ttflSumSon($uId, $stime, $etime) {
        $rt = $this->db->getone("select sum(money) as sum from un_account_recharge where addtime > $stime and addtime < $etime and status = 1 and user_id in (select id from un_user where parent_id = $uId)");
        return $rt['sum'];
    }
    
    //直属充值次数
    public function ttflCntSon($uId) {
        $rt = $this->db->getone("select count(id) as cnt from un_account_recharge where status = 1 and user_id in (select id from un_user where parent_id = $uId)");
        return $rt['cnt'];
    }
    
    //获取充值表记录id  根据订单号
    public function ttflRegId($order_no){
        $rt = $this->db->getone("select id from un_account_recharge where order_sn='{$order_no}'");
        return $rt['id'];
    }

}