<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class IyzfAction extends Action {
    private $table;
    protected $tableTTflCfg = 'un_ttfl_cfg';
    protected $tableTTflLog = 'un_ttfl_log';  
    protected $table2 = '#@_account_recharge';
    
    public function __construct()
    {
        parent::__construct();
        $this->table = "un_payment_config";
    }
    /**
     * 爱益聚合支付充值回调
     */
    public function iyNotify() {
        $input = file_get_contents("php://input"); //接收POST数据
        //将xml转化为数组
        $postarr = xmlToArray($input);
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s").var_export($postarr,true)."\n", FILE_APPEND);
        //实例化爱益支付类
        $iyibank = O('iyibank', '', 1);

        //获取配置信息
        $sql = "select config from $this->table where id = (select payment_id from un_account_recharge where order_sn = '".$postarr['out_trade_no']."')";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        //校验签名
        if ($iyibank->verifySign($postarr,$oRechargeArr['iy_secret']) === 0) { //签名失败
            echo "aiyi signature verification failed";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $postarr['orderid'] . '--Order number--' . $postarr['out_trade_no'] . '--ay signature verification failed--' . "\n", FILE_APPEND);
            exit;
        };

        $paymentArr = array('1' => '微信', '2' => '支付宝');

        if($postarr['result_code'] != "0") {
            echo "Payment failure";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $postarr['orderid'] . '--Order number--' . $postarr['out_trade_no'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }

        $where = array('order_sn' => $postarr['out_trade_no']);
        //检测订单是否已 处理
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            echo "success";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $postarr['orderid'] . '--Order number--' . $postarr['out_trade_no'] . '--success--money：' . $postarr['total_fee'] . "\n", FILE_APPEND);
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));
            if ($shareIdArr['share_id'] != 0) {
               $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                       if($i["low"]<=$postarr['total_fee']&&$postarr['total_fee']<=$i["upper"]){
                           $cashback_rate = $i["rate"];
                       }
                   }
                   $cashback_amount = bcdiv(($cashback_rate*$postarr['total_fee']),100,2);
                   $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
               }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $postarr['orderid'],
                'money' => $postarr['total_fee'],
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            if (!$res) {
                throw new Exception('订单更新失败!');
            }
            //充值成功---判断返利
            $regId = $this->ttflRegId($where['order_sn']);
            $this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($postarr['total_fee'], $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($postarr['total_fee'], $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $postarr['out_trade_no'],
                'type' => 10,
                'money' => $postarr['total_fee'],
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' online ' . $paymentArr[$rechargeInfo['payment_id']] . 'Deposit' .$postarr['total_fee'] . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                throw new Exception('充值流水生成失败!');
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                throw new Exception('账户更新失败!');
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$postarr['total_fee']} WHERE id = {$rechargeInfo['payment_id']}";
            $db->query($sql);

            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $postarr['orderid'] . '--Order number--' . $postarr['out_trade_no'] . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            echo 'Payment failure';
            exit;
        }

        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $postarr['orderid'] . '--Order number--' . $postarr['out_trade_no'] . '--success--money：' . $postarr['total_fee'] . "\n", FILE_APPEND);
        echo "success";
    }

    /**
     * 讯汇宝支付回调
     */
    public function xunHuiBaoCallBack() {
        //实例化讯汇宝支付类
        $xunHuiBaoModel = O('xunhuibao', '', 1);
        $postarr = array(
            "transDate" => $_REQUEST['transDate'],
            "transTime" =>$_REQUEST['transTime'],
            "merchno" =>$_REQUEST['merchno'],
            "merchName" =>$_REQUEST['merchName'],
            "openId"=>$_REQUEST['openId'],
            "amount" =>$_REQUEST['amount'],
            "traceno" => $_REQUEST['traceno'],
            "payType" =>$_REQUEST['payType'],
            "orderno" =>$_REQUEST['orderno'],
//            "channelOrderno" => $_POST['channelOrderno'],
//            "channelTraceno" =>$_POST['channelTraceno'],
            "status" =>$_REQUEST['status']
        );
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s")."xhb---".var_export($postarr,true)."\n", FILE_APPEND);
        //获取配置信息
        $sql = "select config from $this->table where id = (select payment_id from un_account_recharge where order_sn = '".$postarr['traceno']."')";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $b = $xunHuiBaoModel->verifyRequestMySign($postarr,$oRechargeArr['merchantKey']);

        //校验签名
        if ($b == $_POST['signature']) { //签名失败
            echo "Signature verification failed";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $postarr['orderno'] . '--Order number--' . $postarr['traceno'] . '--xhb signature verification failed--' . "\n", FILE_APPEND);
            exit;
        };

        $paymentArr = array('1' => '支付宝', '2' => '微信');


        if($postarr['status'] != "1") {
            echo "Payment failure";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $postarr['orderno'] . '--Order number--' . $postarr['traceno'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }

        $where = array('order_sn' => $postarr['traceno']);
        //检测订单是否已处理
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            echo "success";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $postarr['orderno'] . '--Order number--' . $postarr['traceno'] . '--success--money：' . $postarr['amount'] . "\n", FILE_APPEND);
            header("Location: http://www.sina28.com/index.php?m=web&c=account&a=billsWeb");
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));

            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                        if($i["low"]<=$postarr['amount']&&$postarr['amount']<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$postarr['amount']),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $postarr['orderno'],
                'money' => $postarr['amount'],
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
                    file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '4' . "\n", FILE_APPEND);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '5' . "\n", FILE_APPEND);
                throw new Exception('订单更新失败!');
            }
			//充值成功---判断返利
			$regId = $this->ttflRegId($where['order_sn']);
			$this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($postarr['amount'], $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($postarr['amount'], $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $postarr['traceno'],
                'type' => 10,
                'money' => $postarr['amount'],
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' online ' . $paymentArr[$rechargeInfo['payment_id']] . 'Deposit' .$postarr['amount'] . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                throw new Exception('充值流水生成失败!');
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                throw new Exception('账户更新失败!');
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$postarr['amount']} WHERE id = {$rechargeInfo['payment_id']}";
            $db->query($sql);

            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $postarr['orderno'] . '--Order number--' . $postarr['traceno'] . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            echo 'Payment failure';
            exit;
        }
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $postarr['orderno'] . '--Order number--' . $postarr['traceno'] . '--success--money：' . $postarr['amount'] . "\n", FILE_APPEND);
        echo "success";
        header("Location: http://www.sina28.com/index.php?m=web&c=account&a=billsWeb");
    }

    /*
     * 易宝支付回调
     * */
    public function yeePayCallBack()
    {
        $yeePayModel = O('yeepay', '', 1);
        $data['r0_Cmd']		= $_REQUEST['r0_Cmd'];//业务类型
        $data['r1_Code']	= $_REQUEST['r1_Code'];//支付结果 固定值 “1”, 代表支付成功.
        $data['r2_TrxId']	= $_REQUEST['r2_TrxId'];//易宝支付交易流水号
        $data['r3_Amt']		= $_REQUEST['r3_Amt'];// 支付金额
        $data['r4_Cur']		= $_REQUEST['r4_Cur'];//交易币种
        $data['r5_Pid']		= $_REQUEST['r5_Pid'];//商品名称
        $data['r6_Order']	= $_REQUEST['r6_Order'];//商户订单号
        $data['r7_Uid']		= $_REQUEST['r7_Uid'];//易宝支付会员ID
        $data['r8_MP']		= $_REQUEST['r8_MP'];//商户扩展信息
        $data['r9_BType']	= $_REQUEST['r9_BType'];//交易结果返回类型
        $data['hmac']		= $_REQUEST['hmac'];//签名数据
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s").var_export($data,true)."\n", FILE_APPEND);
        //获取配置信息
        $sql = "select config from $this->table where id = (select payment_id from un_account_recharge where order_sn = '".$data['r6_Order']."')";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $bRet = $yeePayModel->CheckHmac($data,$oRechargeArr['merchantID'],$oRechargeArr['merchantKey']);
        if($bRet === false)
        {
            echo "Signature verification failed";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $data['r2_TrxId'] . '--Order number--' . $data['r6_Order'] . '--yb signature verification failed--' . "\n", FILE_APPEND);
            exit;
        }
        if($data['r1_Code'] != "1") {
            echo "Payment failure";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $data['r2_TrxId'] . '--Order number--' . $data['r6_Order'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }

        $where = array('order_sn' => $data['r6_Order']);
        //检测订单是否已处理
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            echo "success";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $data['r2_TrxId'] . '--Order number--' . $data['r6_Order'] . '--success--money：' . $data['r3_Amt'] . "\n", FILE_APPEND);
            header("Location: http://www.sina28.com/index.php?m=web&c=account&a=billsWeb");
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));

            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                        if($i["low"]<= $data['r3_Amt']&& $data['r3_Amt']<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate* $data['r3_Amt']),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $data['r2_TrxId'],
                'money' => $data['r3_Amt'],
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '4' . "\n", FILE_APPEND);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '5' . "\n", FILE_APPEND);
                throw new Exception('订单更新失败!');
            }
			//充值成功---判断返利
			$regId = $this->ttflRegId($where['order_sn']);
			$this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($data['r3_Amt'], $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($data['r3_Amt'], $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $data['r6_Order'],
                'type' => 10,
                'money' => $data['r3_Amt'],
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' Online deposit ' .$data['r3_Amt'] . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                throw new Exception('充值流水生成失败!');
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                throw new Exception('账户更新失败!');
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$data['r3_Amt']} WHERE id = {$rechargeInfo['payment_id']}";
            $db->query($sql);

            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $data['r2_TrxId'] . '--Order number--' . $data['r6_Order'] . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            echo 'Payment failure';
            exit;
        }
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $data['r2_TrxId'] . '--Order number--' . $data['r6_Order'] . '--success--money：' . $data['r3_Amt'] . "\n", FILE_APPEND);
        echo "success";
        header("Location: http://www.sina28.com/index.php?m=web&c=account&a=billsWeb");
    }

    /*
     *魔宝支付回调
     * */
    public function moBaoPayCallBack()
    {
        $moBaoPayModel = O('mobaopay', '', 1);
        $callBack['apiName'] = $_REQUEST["apiName"];
        $callBack['notifyTime'] = $_REQUEST["notifyTime"];        // 通知时间
        $callBack['tradeAmt'] = $_REQUEST["tradeAmt"];        // 支付金额(单位元，显示用)
        $callBack['merchNo'] = $_REQUEST["merchNo"];        // 商户号
        $callBack['merchParam'] = $_REQUEST["merchParam"];        // 商户参数，支付平台返回商户上传的参数，可以为空
        $callBack['orderNo'] = $_REQUEST["orderNo"];        // 商户订单号
        $callBack['tradeDate'] = $_REQUEST["tradeDate"];        // 商户订单日期
        $callBack['accNo'] = $_REQUEST["accNo"];        // 支付系统订单号
        $callBack['accDate'] = $_REQUEST["accDate"];        // 支付系统账务日期
        $callBack['orderStatus'] = $_REQUEST["orderStatus"]; // 订单状态，0-未支付，1-支付成功，2-失败，4-部分退款，5-退款，9-退款处理中
        $callBack['signMsg'] = $_REQUEST["signMsg"];  // 签名数据
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s").var_export($callBack,true)."\n", FILE_APPEND);
        $sql = "select config from $this->table where id = (select payment_id from un_account_recharge where order_sn = '".$callBack['orderNo']."')";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $str_to_sign = $moBaoPayModel->prepareSign($callBack);	// 准备准备验签数据
        $resultVerify = $moBaoPayModel->verify($str_to_sign, $callBack['signMsg'],$oRechargeArr['merchantKey']);        // 验证签名

        if (!$resultVerify)
        {
            echo "Signature verification failed";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $callBack['accNo'] . '--Order number--' . $callBack['orderNo'] . '--mb signature verification failed--' . "\n", FILE_APPEND);
            exit;
        }
        if($callBack['orderStatus'] != "1") {
            echo "Payment failure";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $callBack['accNo'] . '--Order number--' .$callBack['orderNo']  .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }
        $paymentArr = array('4' => '支付宝', '5' => '微信', '1'=>'网银');
        $where = array('order_sn' => $callBack['orderNo']);
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            echo "success";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $callBack['accNo'] . '--订单号--' . $callBack['orderNo'] . '--success--money：' . $callBack['tradeAmt'] . "\n", FILE_APPEND);
            header("Location: http://www.sina28.com/index.php?m=web&c=account&a=billsWeb");
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));

            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                        if($i["low"]<=$callBack['tradeAmt']&&$callBack['tradeAmt']<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$callBack['tradeAmt']),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $callBack['accNo'],
                'money' => $callBack['tradeAmt'],
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '4' . "\n", FILE_APPEND);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '5' . "\n", FILE_APPEND);
                throw new Exception('订单更新失败!');
            }
			//充值成功---判断返利
			$regId = $this->ttflRegId($where['order_sn']);
			$this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($callBack['tradeAmt'], $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($callBack['tradeAmt'], $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $callBack['orderNo'],
                'type' => 10,
                'money' => $callBack['tradeAmt'],
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' online ' . $paymentArr[$rechargeInfo['payment_id']] . 'Deposit' .$callBack['tradeAmt'] . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                throw new Exception('充值流水生成失败!');
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                throw new Exception('账户更新失败!');
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$callBack['tradeAmt']} WHERE id = {$rechargeInfo['payment_id']}";
            $db->query($sql);

            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $callBack['accNo'] . '--Order number--' . $callBack['orderNo'] . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            echo 'Payment failure';
            exit;
        }
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $callBack['accNo'] . '--Order number--' . $callBack['orderNo'] . '--success--money：' . $callBack['tradeAmt'] . "\n", FILE_APPEND);
        echo true;
        header("Location: http://www.sina28.com/index.php?m=web&c=account&a=billsWeb");

    }

    /*
     * 闪付支付回调
     * */
    public function shanFuPayCallBack()
    {
        $shanFuPayModel = O('shanfupay', '', 1);
        $post_data = array(
            "MemberID"=>$_REQUEST['MemberID'],//商户号
            "TerminalID"=>$_REQUEST['TerminalID'],//商户终端号
            "TransID"=>$_REQUEST['TransID'],//流水号
            "Result"=>$_REQUEST['Result'],//支付结果
            "ResultDesc"=>$_REQUEST['ResultDesc'],//支付结果描述
            "FactMoney"=>$_REQUEST['FactMoney']/100,//实际成功金额
            "AdditionalInfo"=>$_REQUEST['AdditionalInfo'],//订单附加消息
            "SuccTime"=>$_REQUEST['SuccTime']//支付完成时间
        );
        
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s").var_export($post_data,true)."\n", FILE_APPEND);

        //获取配置信息
        $sql = "select config from $this->table where id = (select payment_id from un_account_recharge where order_sn = '".$post_data['TransID']."')";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $WaitSign = $shanFuPayModel->verifySignature($post_data,$oRechargeArr['merchantKey']);
        $Md5Sign = $_REQUEST['Md5Sign'];//md5签名

        //校验签名
        if ($Md5Sign == $WaitSign) { //签名失败
            echo "Signature verification failed";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['TransID'] . '--Order number--' . $post_data['TransID'] . '--sf signature verification failed--' . "\n", FILE_APPEND);
            exit;
        };
        if($post_data['Result'] != "1") {
            echo "Payment failure";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['TransID'] . '--Order number--' . $post_data['TransID'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }

        $where = array('order_sn' => $post_data['TransID']);

        //检测订单是否已处理
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            echo "success";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $post_data['TransID'] . '--Order number--' . $post_data['TransID'] . '--success--money：' . $post_data['FactMoney'] . "\n", FILE_APPEND);
            header("Location: ");
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));

            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                        if($i["low"]<=$post_data['FactMoney']&&$post_data['FactMoney']<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$post_data['FactMoney']),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $post_data['TransID'],
                'money' => $post_data['FactMoney'],
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '4' . "\n", FILE_APPEND);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '5' . "\n", FILE_APPEND);
                throw new Exception('订单更新失败!');
            }
			//充值成功---判断返利
			$regId = $this->ttflRegId($where['order_sn']);
			$this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($post_data['FactMoney'], $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($post_data['FactMoney'], $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $post_data['TransID'],
                'type' => 10,
                'money' => $post_data['FactMoney'],
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' Online deposit ' .$post_data['FactMoney'] . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                throw new Exception('充值流水生成失败!');
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                throw new Exception('账户更新失败!');
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$post_data['FactMoney']} WHERE id = {$rechargeInfo['payment_id']}";
            $db->query($sql);

            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['TransID'] . '--Order number--' . $post_data['TransID'] . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            echo 'Payment failure';
            exit;
        }

        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['TransID'] . '--Order number--' . $post_data['TransID'] . '--success--money：' . $post_data['FactMoney'] . "\n", FILE_APPEND);
        echo "ok";
        header("Location: http://" . $_SERVER['HTTP_HOST']. "/index.php?m=web&c=account&a=billsWeb");

    }

    /*
   * 乐盈支付回调
   * */
    public function leYingPayCallBack()
    {
        $post_data = [
            'orderID' => $_REQUEST["orderID"],
            'resultCode' => $_REQUEST["resultCode"],
            'stateCode' => $_REQUEST["stateCode"],
            'orderAmount' => $_REQUEST["orderAmount"],
            'payAmount' => $_REQUEST["payAmount"],
            'acquiringTime' => $_REQUEST["acquiringTime"],
            'completeTime' => $_REQUEST["completeTime"],
            'orderNo' => $_REQUEST["orderNo"],
            'partnerID' => $_REQUEST["partnerID"],
            'remark' => $_REQUEST["remark"],
            'charset' => $_REQUEST["charset"],
            'signType' => $_REQUEST["signType"],
        ];
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s").var_export($post_data,true)."\n", FILE_APPEND);

        $signMsg = $_REQUEST["signMsg"];
        $payAmount = $_REQUEST["payAmount"]/100;

        //获取配置信息
        $sql = "select config from $this->table where id = (select payment_id from un_account_recharge where order_sn = '".$post_data['orderID']."')";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $leYingPayModel = O('leyingpay', '', 1);
        $sign = $leYingPayModel->sign($post_data,$oRechargeArr['merchantKey']);

        //效验签名
        if($signMsg != $sign){
            echo "Signature verification failed";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['orderNo'] . '--Order number--' . $post_data['orderID'] . '--ly signature verification failed--' . "\n", FILE_APPEND);
            exit;
        }

        //效验支付是否成功
        if($post_data['stateCode'] != "2") {
            echo "Payment failure";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['orderNo'] . '--Order number--' . $post_data['orderID'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }

         $where = array('order_sn' => $post_data['orderID']);


        //检测订单是否已处理
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            echo "success";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $post_data['orderNo'] . '--Order number--' . $post_data['orderID'] . '--success--money：' . $post_data['FactMoney'] . "\n", FILE_APPEND);
            header("Location: ");
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));

            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                        if($i["low"]<=$post_data["payAmount"]&&$post_data["payAmount"]<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$post_data["payAmount"]),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $post_data['orderID'],
                'money' => $payAmount,
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '4' . "\n", FILE_APPEND);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '5' . "\n", FILE_APPEND);
                throw new Exception('订单更新失败!');
            }
			//充值成功---判断返利
			$regId = $this->ttflRegId($where['order_sn']);
			$this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($payAmount, $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($payAmount, $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $post_data['orderID'],
                'type' => 10,
                'money' => $payAmount,
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' Online deposit ' .$payAmount . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                throw new Exception('充值流水生成失败!');
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                throw new Exception('账户更新失败!');
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$payAmount} WHERE id = {$rechargeInfo['payment_id']}";
            $db->query($sql);

            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['orderNo'] . '--Order number--' . $post_data['orderID'] . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            echo 'Payment failure';
            exit;
        }

        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['orderNo'] . '--Order number--' . $post_data['orderID'] . '--success--money：' . $post_data['FactMoney'] . "\n", FILE_APPEND);
        exit(200);

    }


    /*
     * 多得宝回调
     */
    public function duoDeBaoPayCallBack()
    {
        $post_data = [
            'merchant_code'=>$_POST["merchant_code"],
            'notify_type'=>$_POST["notify_type"],
            'notify_id'=>$_POST["notify_id"],
            'interface_version'=>$_POST["interface_version"],
            'sign_type'=>$_POST["sign_type"],
            'DD4Sign'=>base64_decode($_POST["sgin"]),
            'order_no'=>$_POST["order_no"],
            'order_time'=>$_POST["order_time"],
            'order_amount'=>$_POST["order_amount"],
            'extra_return_param'=>$_POST["extra_return_param"],
            'trade_no'=>$_POST["trade_no"],
            'trade_time'=>$_POST["trade_time"],
            'trade_status'=>$_POST["trade_status"],
            'bank_seq_no'=>$_POST["bank_seq_no"],
        ];
        D('accountrecharge')->save(['verify_remark' => print_r($post_data, true)], ['id' => 4]);
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s").var_export($post_data,true)."\n", FILE_APPEND);
        //获取配置信息
        $sql = "select config from $this->table where id = (select payment_id from un_account_recharge where order_sn = '".$post_data['order_no']."')";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $duoDeBaoModel = O('duodebao', '', 1);
        $sign = $duoDeBaoModel->verifyNotify($post_data,$oRechargeArr['merchantPublicKey']);
        D('accountrecharge')->save(['verify_remark' => $sign], ['id' => 7]);
        //效验签名
        if(!$sign){
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['trade_no'] . '--Order number--' . $post_data['order_no'] . '--duodebao signature verification failed--' . "\n", FILE_APPEND);
            exit;
        }

        //效验支付是否成功
        if($post_data['trade_status'] != "SUCCESS") {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['trade_no'] . '--Order number--' . $post_data['order_no'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }

        $where = array('order_sn' => $post_data['order_no']);

        //检测订单是否已处理
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $post_data['trade_no'] . '--Order number--' . $post_data['order_no'] . '--success--money：' . $post_data['order_amount'] . "\n", FILE_APPEND);
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));
            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                        if($i["low"]<=$post_data['order_amount']&&$post_data['order_amount']<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$post_data['order_amount']),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金
                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $post_data['trade_no'],
                'money' => $post_data['order_amount'],
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );
            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '4' . "\n", FILE_APPEND);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '5' . "\n", FILE_APPEND);
                throw new Exception('订单更新失败!');
            }
            //充值成功---判断返利
            $regId = $this->ttflRegId($where['order_sn']);
            $this->ttfl($regId);
            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($post_data['order_amount'], $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($post_data['order_amount'], $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $post_data['order_no'],
                'type' => 10,
                'money' => $post_data['order_amount'],
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' Online deposit ' .$post_data['order_amount'] . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                throw new Exception('充值流水生成失败!');
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                throw new Exception('账户更新失败!');
            }
            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$post_data['order_amount']} WHERE id = {$rechargeInfo['payment_id']}";
            $db->query($sql);
            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['trade_no'] . '--Order number--' . $post_data['order_no'] . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            exit;
        }

        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['trade_no'] . '--Order number--' . $post_data['order_no'] . '--success--money：' . $post_data['order_amount'] . "\n", FILE_APPEND);
        exit("SUCCESS");
    }

    /*
     * 快付支付回调
     */
    public function kuaiFuPayCallBack()
    {
        $post_data = [
            'apiName' => $_POST["apiName"],
            'notifyTime' => $_POST["notifyTime"],// 通知时间
            'tradeAmt' => $_POST["tradeAmt"],// 支付金额(单位元，显示用)
            'merchNo' => $_POST["merchNo"],// 商户号
            'merchParam' => $_POST["merchParam"],// 商户参数，支付平台返回商户上传的参数，可以为空
            'orderNo' => $_POST["orderNo"],   // 商户订单号
            'tradeDate' => $_POST["tradeDate"],  // 商户订单日期
            'accNo' => $_POST["accNo"],// 快付支付订单号
            'accDate' => $_POST["accDate"],   // 快付支付账务日期
            'orderStatus' => $_POST["orderStatus"],// 订单状态，0-未支付，1-支付成功，2-失败，4-部分退款，5-退款，9-退款处理中
        ];
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s").var_export($post_data,true)."\n", FILE_APPEND);
        $signMsg = $_POST["signMsg"];
        $kuaifuPayModel = O('kuaifu', '', 1);
        $str_to_sign = $kuaifuPayModel->prepareSign($post_data);
        $resultVerify = $kuaifuPayModel->verify($str_to_sign, $signMsg);

        //效验签名
        if(!$resultVerify){
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['accNo'] . '--Order number--' . $post_data['orderNo'] . '--kuaifu signature verification failed--' . "\n", FILE_APPEND);
            exit;
        }

        //效验支付是否成功
        if($post_data['orderStatus'] == "1") {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['accNo'] . '--Order number--' . $post_data['orderNo'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }

        $where = array('order_sn' => $post_data['orderNo']);

        //检测订单是否已处理
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $post_data['accNo'] . '--Order number--' . $post_data['orderNo'] . '--success--money：' . $post_data['tradeAmt'] . "\n", FILE_APPEND);
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));

            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                        if($i["low"]<=$post_data['tradeAmt']&&$post_data['tradeAmt']<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$post_data['tradeAmt']),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $post_data['accNo'],
                'money' => $post_data['tradeAmt'],
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '4' . "\n", FILE_APPEND);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '5' . "\n", FILE_APPEND);
                throw new Exception('订单更新失败!');
            }
            //充值成功---判断返利
            $regId = $this->ttflRegId($where['order_sn']);
            $this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($post_data['tradeAmt'], $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($post_data['tradeAmt'], $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $post_data['orderNo'],
                'type' => 10,
                'money' => $post_data['tradeAmt'],
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' Online deposit ' .$post_data['tradeAmt'] . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                throw new Exception('充值流水生成失败!');
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                throw new Exception('账户更新失败!');
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$post_data['tradeAmt']} WHERE id = {$rechargeInfo['payment_id']}";
            $db->query($sql);
            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['accNo'] . '--Order number--' . $post_data['orderNo'] . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            exit;
        }

        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['accNo'] . '--Order number--' . $post_data['orderNo'] . '--success--money：' . $post_data['tradeAmt'] . "\n", FILE_APPEND);
        exit("SUCCESS");
    }

    /*
     * beeepay回调
     */
    public function beeePayCallBack()
    {
        $post_data = [
            'resp_code' => $_POST['resp_code'],//取值为"RESPONSE_SUCCESS"表示请求成功, 其他处理码请参考返回说明
            'resp_desc' => $_POST['resp_desc'],//返回码对应的描述信息
            'notify_type' => $_POST['notify_type'],//通知类型：async_notify=后端异步通知 或 rsync_notify=前台同步通知
            'out_trade_no' => $_POST['out_trade_no'],//商户订单号: 原值返回.
            'order_sn' => $_POST['order_sn'],//Beeepay平台订单号.
            'order_amount' => $_POST['order_amount'],//	订单金额: 消费者支付订单的总金额，一笔订单一个，以元为单位.
            'order_time' => $_POST['order_time'],//商家订单时间: 订单产生时间格式为yyyy-MM-dd HH:mm:ss，时区为GMT+8，例如：2015-01-01 12:00:00.
            'trade_time' => $_POST['trade_time'],//Beeepay平台订单交易时间: 订单产生时间格式为yyyy-MM-dd HH:mm:ss，时区为GMT+8，例如：2015-01-01 12:00:00.
            'trade_status' => $_POST['trade_status'],//交易状态：TRADE_FAILURE=失败、TRADE_SUCCESS=成功
            'partner_id' => $_POST['partner_id'],//商户合作ID
        ];
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s").var_export($post_data,true)."\n", FILE_APPEND);

        //获取配置信息
        $sql = "select config from $this->table where id = (select payment_id from un_account_recharge where order_sn = '".$post_data['out_trade_no']."')";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $sign = $_POST['sign'];
        $beeePayModel = O('beeepay', '', 1);
        $resultVerify = $beeePayModel->verifyNotify($post_data,$sign,$oRechargeArr['merchantKey']);

        //效验签名
        if(!$resultVerify){
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['order_sn'] . '--Order number--' . $post_data['out_trade_no'] . '--beeepay signature verification failed--' . "\n", FILE_APPEND);
            exit;
        }

        if($post_data['resp_code'] != "RESPONSE_SUCCESS")
        {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['order_sn'] . '--Order number--' . $post_data['out_trade_no'] . '--beeepay ' . $post_data['resp_desc'] . '--' . "\n", FILE_APPEND);
            exit;
        }

        //效验支付是否成功
        if($post_data['trade_status'] != "TRADE_SUCCESS") {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['order_sn'] . '--Order number--' . $post_data['out_trade_no'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }

        $where = array('order_sn' => $post_data['out_trade_no']);

        //检测订单是否已处理
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        file_put_contents(S_CACHE . 'log/xhb.log', var_export($rechargeInfo,true)."\n", FILE_APPEND);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $post_data['order_sn'] . '--Order number--' . $post_data['out_trade_no'] . '--success--money：' . $post_data['tradeAmt'] . "\n", FILE_APPEND);
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));

            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                        if($i["low"]<=$post_data['order_amount']&&$post_data['order_amount']<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$post_data['order_amount']),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $post_data['order_sn'],
                'money' => $post_data['order_amount'],
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). ' Order update failed!' . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }
            //充值成功---判断返利
            $regId = $this->ttflRegId($where['order_sn']);
            $this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($post_data['order_amount'], $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($post_data['order_amount'], $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $post_data['out_trade_no'],
                'type' => 10,
                'money' => $post_data['order_amount'],
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' Online deposit ' .$post_data['order_amount'] . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );

            //判断流水号是否一样
            if ($rechargeInfo['liushui_sn'] == $post_data['order_sn']) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-1repeat Flowing water--' . $post_data['order_sn'] . '--Order number--' . $post_data['out_trade_no'] . '--success--money：' . $post_data['tradeAmt'] . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). ' Recharge flow failed to generate!' . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). ' RAccount update failed!' . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$post_data['order_amount']} WHERE id = {$rechargeInfo['payment_id']}";
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). " {$sql}\n", FILE_APPEND);
            $db->query($sql);
            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['order_sn'] . '--Order number--' . $post_data['out_trade_no'] . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            exit;
        }

        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['order_sn'] . '--Order number--' . $post_data['out_trade_no'] . '--success--money：' . $post_data['order_amount'] . "\n", FILE_APPEND);
        exit("SUCCESS");
    }

    /*
     * 智付支付回调
     */
    public function zfPayCallBack()
    {
        $post_data = array(
            'merchant_code'=>$_POST["merchant_code"],//商户ID
            'interface_version' => $_POST["interface_version"],//接口版本
            'sign_type' => $_POST["sign_type"],	//签名方式
            'dinpaySign' => base64_decode($_POST["sign"]),//签名
            'notify_type' => $_POST["notify_type"],//通知类型
            'notify_id' => $_POST["notify_id"],//通知校验ID
            'order_no' => $_POST["order_no"],//商家订单号
            'order_time' => $_POST["order_time"],//商家订单时间
            'order_amount' => $_POST["order_amount"],//商家订单金额
            'trade_status' => $_POST["trade_status"],//交易状态 SUCCESS 成功  FAILED 失败
            'trade_time' => $_POST["trade_time"],//智付交易时间
            'trade_no' => $_POST["trade_no"],//智付交易定单号
            'bank_seq_no' => $_POST["bank_seq_no"],//银行交易流水号
            'extra_return_param' => $_POST["extra_return_param"],//回传参数
        );
        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s").var_export($post_data,true)."\n", FILE_APPEND);

        //获取配置信息
        $sql = "select config from $this->table where id = (select payment_id from un_account_recharge where order_sn = '".$post_data['order_no']."')";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $zhiFuModel = O('zhifupay', '', 1);
        $sign = $zhiFuModel->verifyNotify($post_data,$oRechargeArr['merchantPublicKey']);

        //效验签名
        if(!$sign){
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['trade_no'] . '--Order number--' . $post_data['order_no'] . '--zhiFuPay signature verification failed--' . "\n", FILE_APPEND);
            exit;
        }

        //效验支付是否成功
        if($post_data['trade_status'] != "SUCCESS") {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['trade_no'] . '--Order number--' . $post_data['order_no'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }
        $this->orderCallBack($post_data['order_no'],$post_data['trade_no'],$post_data['order_amount'],"zfzf");
    }

    /*
     * 牛付支付回调
     */
    public function nfPayCallBack()
    {
        $post_data = array(
            'code'=>$_REQUEST["result"],
            'order_no' => $_REQUEST["orderId"],//交易定单号
            'amount' => $_REQUEST["amount"],//交易定单号
        );

        file_put_contents(S_CACHE . 'log/nf.log', date("m-d H:i:s").var_export($_REQUEST,true)."\n", FILE_APPEND);

        //获取配置信息
        $sql = "select * from un_account_recharge where order_sn = '".$post_data['order_no']."'";
        $orderInfo = $this->db->getone($sql);

        $liushui_sn = $orderInfo['liushui_sn'];
        $order_sn = $_REQUEST["orderId"];
        $order_amount = $_REQUEST["amount"];
        //效验支付是否成功
        $a = '';
        foreach($post_data as $x=>$x_value)
        {
            if($x_value != ""){
                $a=$a.$x."=".$x_value."&";
            }
        }
        $b = md5($a.$this->merchantKey);
        if($post_data['code'] != 'S'&& $orderInfo/*  && $b==$_REQUEST['md5'] */) {
            file_put_contents(S_CACHE . 'log/nf.log', date("m-d H:i:s"). '--Flowing water--' . $post_data['trade_no'] . '--Order number--' . $post_data['order_no'] .'--Payment failure--' . "\n", FILE_APPEND);
            exit;
        }
        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $orderInfo['user_id']));

            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $orderInfo['user_id']));
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
                        if($i["low"]<=$post_data['amount']&&$post_data['amount']<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$post_data['amount']),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $orderInfo['liushui_sn'],
                'money' => $post_data['amount'],
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($orderInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $where = array('order_sn' => $order_sn);
            $sql = "SELECT * FROM un_account WHERE user_id = '{$orderInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). ' Order update failed!' . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }
            //充值成功---判断返利
            $regId = $this->ttflRegId($where['order_sn']);
            $this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $orderInfo['payment_id']));
            $admin_money = bcadd($order_amount, $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($order_amount, $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $orderInfo['user_id'],
                'order_num' => $order_sn,
                'type' => 10,
                'money' => $order_amount,
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' Online deposit ' .$order_amount . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );

            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                file_put_contents(S_CACHE . 'log/nf.log', date("m-d H:i:s"). ' Recharge flow failed to generate!' . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $orderInfo['user_id']));
            if (!$res) {
                file_put_contents(S_CACHE . 'log/nf.log', date("m-d H:i:s"). ' RAccount update failed!' . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$order_amount} WHERE id = {$orderInfo['payment_id']}";
            $db->query($sql);
            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/nf.log', date("m-d H:i:s"). '--Flowing water--' . $liushui_sn . '--Order number--' . $order_sn . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            exit;
        }
    }

    /*
     * 回调订单处理
     * $order_sn 商户订单号
     * $order_amount 充值金额
     * $liushui_sn 第三方订单号
     * $nid  支付nid
     */
    public function orderCallBack($order_sn,$liushui_sn,$order_amount,$nid)
    {
        $where = array('order_sn' => $order_sn);
        //检测订单是否已处理
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,user_id,status,liushui_sn', $where);
        file_put_contents(S_CACHE . 'log/xhb.log', var_export($rechargeInfo,true)."\n", FILE_APPEND);
        if ($rechargeInfo['status'] != '0' || !empty($rechargeInfo['liushui_sn'])) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-repeat Flowing water--' . $liushui_sn . '--Order number--' . $order_sn . "\n", FILE_APPEND);
            exit;
        }

        $db = getconn();
        $db->query('BEGIN');
        try {
            //判断用户是否为分享注册首充
            $shareIdArr = D('user')->getOneCoupon('share_id', array('id' => $rechargeInfo['user_id']));

            if ($shareIdArr['share_id'] != 0) {
                $rechargeRecord = D('accountrecharge')->getOneCoupon('id', array('status' => 1, 'user_id' => $rechargeInfo['user_id']));
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
                        if($i["low"]<=$order_amount&&$order_amount<=$i["upper"]){
                            $cashback_rate = $i["rate"];
                        }
                    }
                    $cashback_amount = bcdiv(($cashback_rate*$order_amount),100,2);
                    $money = bcadd($cashback_amount, $accountInfo['money'], 2); //用户的可用资金

                    //生成账户流水
                    $logArr = array(
                        'user_id' => $shareIdArr['share_id'],
                        'order_num' => "JL" . date("YmdHis") . rand(100, 999),
                        'type' => 66,
                        'money' => $cashback_amount,
                        'use_money' => $money,
                        'remark' => 'User id: ' . $shareIdArr['share_id'] . ' Sharing rewards:' . $cashback_amount,
                        'verify' => 1,
                        'addtime' => SYS_TIME,
                        'addip' => ip(),
                    );
                    //产生充值流水
                    $logId = D('accountlog')->aadAccountLog($logArr);
                    //更新用户账户金额
                    $res = D('account')->save(array('money' => $money), array('user_id' => $shareIdArr['share_id']));
                }
            }

            //更新订单记录
            $data = array(
                'liushui_sn' => $liushui_sn,
                'money' => $order_amount,
                'status' => 1,
                'verify_userid' => 'admin',
                'verify_time' => SYS_TIME,
            );

            //判断是否是首充
            $isFirstRecharge = D('accountRecharge')->getIsFirstRecharge($rechargeInfo['user_id']);
            if(!$isFirstRecharge){
                $verify_remark['FirstRecharge'] = "1";
                $data['verify_remark'] = json_encode($verify_remark);
                $firstRecharge = "该用户为首次充值 ";
            }else{
                $firstRecharge = "";
            }

            $sql = "SELECT * FROM un_account WHERE user_id = '{$rechargeInfo['user_id']}' LIMIT 1 FOR UPDATE"; //进行行锁
            $accountInfo = $db->getone($sql);
            $res = D('accountrecharge')->save($data, $where);
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). ' Order update failed!' . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }
            //充值成功---判断返利
            $regId = $this->ttflRegId($where['order_sn']);
            $this->ttfl($regId);

            $xsBalance = D('paymentconfig')->getOneCoupon('balance', array('id' => $rechargeInfo['payment_id']));
            $admin_money = bcadd($order_amount, $xsBalance['balance'], 2); //公司线上充值对应的资金
            $money = bcadd($order_amount, $accountInfo['money'], 2); //用户的可用资金
            $logArr = array(
                'user_id' => $rechargeInfo['user_id'],
                'order_num' => $order_sn,
                'type' => 10,
                'money' => $order_amount,
                'use_money' => $money,
                'admin_money' => $admin_money,
                'remark' => $firstRecharge.' Online deposit ' .$order_amount . 'USD',
                'verify' => 1,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );

            //判断流水号是否一样
            if ($rechargeInfo['liushui_sn'] == $liushui_sn) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '-1repeat Flowing water--' . $liushui_sn . '--Order number--' . $order_sn . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }
            //产生充值流水
            $logId = D('accountlog')->aadAccountLog($logArr);
            if (!$logId) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). ' Recharge flow failed to generate!' . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }

            //更新用户账户金额
            $res = D('account')->save(array('money' => $money), array('user_id' => $rechargeInfo['user_id']));
            if (!$res) {
                file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). ' RAccount update failed!' . "\n", FILE_APPEND);
                $db->query('ROLLBACK');
                exit;
            }

            //更新对应线上支付的余额
            $sql = "UPDATE `un_payment_config` SET `balance` =balance + {$order_amount} WHERE id = {$rechargeInfo['payment_id']}";
            $db->query($sql);
            $db->query('COMMIT');
        } catch (Exception $ex) {
            file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $liushui_sn . '--Order number--' . $order_sn . '--' . $ex->getMessage() . "\n", FILE_APPEND);
            $db->query('ROLLBACK');
            exit;
        }

        file_put_contents(S_CACHE . 'log/xhb.log', date("m-d H:i:s"). '--Flowing water--' . $liushui_sn . '--Order number--' . $order_sn . '--success--money：' . $order_amount . "\n", FILE_APPEND);
        if($nid == "zfzf")
        {
            exit("SUCCESS");
        }

    }


	//天天返利代码
	//------天天返利赠送金额
    public function ttfl($id) {
        $this->zeroTTfl($id); //存在第0次天天返利的情况 

        $info = $this->db->getone("select * from " . $this->table2 . " where id = $id"); //充值表的一条记录(ID)
        $mainCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 1"); //天天返利活动主条件
        $max_money = $mainCfg['max_money']; //返利上限--需要用累计的
        $low_money = $mainCfg['low_money']; //返利下限

        $stime = $mainCfg['start_time'];
        $etime = $mainCfg['end_time'] + 86399;
        $chargeCntObj = $this->db->getall("select count(*) as cnt,sum(money) as sums from " . $this->table2 . " where user_id = {$info['user_id']} and addtime > $stime and addtime < $etime and status = 1");

        $chargeCnt = $chargeCntObj[0]['cnt']; //用户在天天返利条件内的充值次数
        $chargeSum = $chargeCntObj[0]['sums']; //用户在天天返利条件内的充值金额
        $branchCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 0 and cz_cnt = {$chargeCnt}"); //天天返利配置条件
        if (empty($branchCfg)) {
            return;
        }

        //返利金额
        $rtMoney = 0;
        $t1 = date('Y-m-d H:i:s', $stime);
        $t2 = date('Y-m-d H:i:s', $etime);
        $rtNote = "天天返利活动时间：{$t1}-{$t2}  金额上限：{$max_money}  金额下限：{$low_money};<br>"; //返利备注
        $rtNote .= "满足第" . $branchCfg['cz_cnt'] . "充返利条件  会员本次充值金额{$info['money']}元;<br/>";

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
        if ($rtMoney == 0) { //不返利  退出
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
        $etime = $mainCfg['end_time'] + 86399;
        if($etime < time() || $stime > time()){
            return;
        }
        $branchCfg = $this->db->getone("select * from " . $this->tableTTflCfg . " where nid = 100005 and main = 0 and cz_cnt = 0"); //天天返利配置条件
        if (empty($branchCfg)) {
            return;
        }

        //返利金额
        $rtMoney = 0;
        $t1 = date('Y-m-d H:i:s', $stime);
        $t2 = date('Y-m-d H:i:s', $etime);
        $rtNote = "天天返利活动时间：{$t1}-{$t2}  金额上限：{$max_money}  金额下限：{$low_money};<br>"; //返利备注
        $rtNote .= "满足第0充返利条件  会员本次充值金额{$info['money']}元;<br/>";

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
        if ($rtMoney == 0) { //不返利  退出
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

