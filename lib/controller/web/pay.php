<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/14 10:06
 * @description web支付接口
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class PayAction extends Action
{

    /**
     * 提交支付
     * @ money 充值金额
     * @ payment_id 支付平台的标识（nid）
     * @ remark 充值备注
     * @ type 优惠方式
     * @ childType 支付方式标识
     */
    public function doPay()
    {
        $data = [];

        try{
            //验证用户登录
            $this->checkAuth();

            //获取接口数据
            $data = [];
            $data['payment_id']   = trim($_REQUEST['payment_id']);    //支付id对应数据库中un_payment_config中的id
            $data['method']       = trim($_REQUEST['method']);        //客户端模式：mobile（移动端）和pc（电脑端）
            $data['pay_type']     = trim($_REQUEST['pay_type']);      //支付类型
            $data['channel_type'] = trim($_REQUEST['channel_type']);  //渠道类型
            $data['money']        = trim($_REQUEST['money']);         //充值金额
            $data['bank_code']    = trim($_REQUEST['bank_code']);    //网银充值时，选择的银行代码
            $data['code']         = trim($_REQUEST['extra_code']);    //线下支付附加码
            $data['user_id']      = $this->userId;                    //用户ID

            $result = D('payadmin')->doPay($data);
            if (!is_array($result) || $result['code'] == 0) {
                ErrorCode::successResponse($result['data']);
            }else {
                if (empty($result['msg'])) {
                    ErrorCode::errorResponse($result['code'], 'Failed to obtain payment information');
                }else {
                    ErrorCode::errorResponse($result['code'], $result['msg']);
                }
            }
        }catch(\Exception $e) {
            payLog('payerror.log', '获取支付信息失败：' . $e);

            ErrorCode::errorResponse(200000, 'Failed to obtain payment information');
        }
    }

    /**
     *代付功能
     */

    public function autoWithdraw($data)
    {
        $result = D('payadmin')->autoWithdraw($data);
        return  $result;
    }

    /**
     * 支付平台回调统一放在api接口处理其他支付页面不做处理
     */
    public function doPaycallBack()
    {
            exit(0);
    }
    
    /**
     * 线上充值金额设置
     */
    public function  rechargeOnlineMoney()
    {
        //验证token
        $this->checkAuth();

        //接收参数
        $payment_id = trim($_REQUEST['payment_id']);

        //查询充值最低限额
        //初始化redis
        $redis = initCacheRedis();
        $Config= $redis -> HMGet("Config:recharge",array('value'));
        //关闭redis链接
        deinitCacheRedis($redis);
    
        //获取充值渠道信息
        $paymentInfoArr = D('paymentconfig')->getOneCoupon('type, nid, name, config', array('id' => $payment_id));
        if (empty($paymentInfoArr)) {
           ErrorCode::errorResponse(200021, 'Payment method does not exist');
        }
        
        $configArr = unserialize($paymentInfoArr['config']);
        //获取银行列表
        if ($paymentInfoArr['type'] == 75 && !empty($configArr['payType']['wy']['bank_id'])) {
            $bank_code = 'bank_code';
            if (!empty($configArr['payType']['wy']['bank_code'])) {
                $bank_code = $configArr['payType']['wy']['bank_code'];
            }
            $bank_info = $this->db->getall("select `id`, `name`, `" . $bank_code . "` as bank_code from `un_bank_info` where `status` = 1 and `id` in (" . $configArr['payType']['wy']['bank_id'] . ") order by `sort` asc");
        }
        
        $channel = $configArr['channel_type'];
        $payTypeName = $paymentInfoArr['name'];
        $user_id = $this->userId;
        $avatar = session::get('avatar');
        $nickname = session::get('nickname');
    
        include template('wallet/rechargeOnLineMoney');
    }
    
    /**
     * 线下充值扫码页面
     */
    public function rechargeOfflineMoney()
    {
        $min_recharge = 0;    //每次充值最小金额限制
        $max_recharge = 0;    //每次充值最大金额限制
    
        $this->checkAuth();   //验证token
    
        $payment_id = trim($_REQUEST['payment_id']);   //接收支付类型参数
        if (empty($payment_id) || !is_numeric($payment_id) ||(int)$payment_id != $payment_id) {
            return '提交的参数错误！';
        }
        
        //初始化redis
        $redis = initCacheRedis();
        //充值下限
        $config= $redis->HMGet("Config:recharge",array('value'));
        $lower_limit  = $config['value'];
        //关闭redis链接
        deinitCacheRedis($redis);
    
        $payInfo = D('paymentconfig')->getOneCoupon('id,name,config,logo,bank_link, balance, upper_limit, min_recharge, max_recharge', array('id' => $payment_id));
        if (empty($payInfo)) {
            return '支付方式不存在！';
        } else {
            //限制每次充值金额
            if ($payInfo['min_recharge'] == '0.00' && $payInfo['max_recharge'] == '0.00') {
                $payInfo['max_recharge'] = $payInfo['upper_limit'] - $payInfo['balance'];
            } elseif (($payInfo['upper_limit'] - $payInfo['balance']) < $payInfo['min_recharge']) {
                $payInfo['max_recharge'] = -1;
            } elseif ($payInfo['min_recharge'] != '0.00' && $payInfo['max_recharge'] == '0.00') {
                $payInfo['max_recharge'] = $payInfo['upper_limit'] - $payInfo['balance'];
            } elseif ($payInfo['max_recharge'] > ($payInfo['upper_limit'] - $payInfo['balance'])) {
                $payInfo['max_recharge'] = $payInfo['upper_limit'] -$payInfo['balance'];
            }
            
            if ($payInfo['max_recharge'] != -1 && $payInfo['max_recharge'] < $lower_limit) {
                $payInfo['max_recharge'] = -1;
            }
            
            if ($payInfo['min_recharge'] < $lower_limit) {
                $payInfo['min_recharge'] = $lower_limit;
            }
        }
    
        $configArr = unserialize($payInfo['config']);
    
        $payInfo['account_name'] = $configArr['account_name'];
        $payInfo['account']      = $configArr['account'];
        $payInfo['branch']       = $configArr['branch'];
        $payInfo['code']         = $configArr['code'];
    
        $code = $this->userId . $this->getRandomString(6);  //获取附加码
    
        include template('wallet/rechargeOfflineMoney');
    }
    
    /**
     * 线下充值扫码页面
     */
    /*
    public function rechargeOfflineMoney()
    {
        //验证token
        $this->checkAuth();
    
        //接收参数
        $payment_id = trim($_REQUEST['payment_id']);
    
        $payInfo = D('paymentconfig')->getOneCoupon('id,name,config,logo,bank_link', array('id' => $payment_id));
        $configArr = unserialize($payInfo['config']);
        $payInfo['account_name'] = $configArr['account_name'];
        $payInfo['account'] = $configArr['account'];
        $payInfo['branch'] = $configArr['branch'];
        $payInfo['code'] = $configArr['code'];

        //初始化redis
        $redis = initCacheRedis();
        //充值下限
        $Config= $redis->HMGet("Config:recharge",array('value'));
        $payInfo['lower_limit'] = $Config['value'];
    
        $code = $this->userId . getRandomString(6);   //创建附加码
    
        //关闭redis链接
        deinitCacheRedis($redis);
    
        $JumpUrl = $this->getUrl();
        include template('wallet/rechargeOfflineMoney');
    }
    */

    /**
     * 线上充值成功跳转页面
     * @return web
     */
    public function payOk()
    {
        //接收参数
        $order_sn   = trim($_REQUEST['order_sn']);    //支付订单号
        $type       = trim($_REQUEST['type']);        //支付类型：1,线上支付，2，线下支付
        $extra_code = trim($_REQUEST['extra_code']);  //线下支付的附加码
        $bank_link  = trim($_REQUEST['bank_link']);   //线下支持跳转银行链接
        $JumpUrl    = $this->getUrl();

        $orderData   = D('accountrecharge')->getOneCoupon('status, money, payment_id', array('order_sn' => $order_sn));
        if (empty($orderData)) {
            return '充值订单号异常，请联系客服！';
        }

        $paymentData = D('paymentconfig')->getOneCoupon('name', array('id' => $orderData['payment_id']));
        if (empty($paymentData)) {
            return '充值方式异常，请联系客服！';
        }

        $res['tittle'] = $paymentData['name'] . '充值';
        $res['money']  = $orderData['money'];
        $res['status'] = $orderData['status'];
        
        if ($type == 2) {  //线下充值
            $res['name'] = '线下' . $paymentData['name'] . '转账';
        } else {    //线上充值
            $res['name'] = '线上' . $paymentData['name'] . '充值';
        }

        include template('wallet/payOK');
    }
    
    /**
     * 线上充值成功跳转页面
     * @return web
     */
    public function payState()
    {
        //接收参数
        $order_sn   = trim($_REQUEST['order_sn']);    //支付订单号
        $type       = trim($_REQUEST['type']);        //支付类型：1,线上支付，2，线下支付
        $extra_code = trim($_REQUEST['extra_code']);  //线下支付的附加码
        $bank_link  = trim($_REQUEST['bank_link']);   //线下支持跳转银行链接
        $JumpUrl    = $this->getUrl();
    
        $orderData   = D('accountrecharge')->getOneCoupon('status, money, payment_id', array('order_sn' => $order_sn));
        if (empty($orderData)) {
            return '充值订单号异常，请联系客服！';
        }
    
        $paymentData = D('paymentconfig')->getOneCoupon('name', array('id' => $orderData['payment_id']));
        if (empty($paymentData)) {
            return '充值方式异常，请联系客服！';
        }
    
        $res['tittle'] = $paymentData['name'] . '充值';
        $res['money']  = $orderData['money'];
        $res['status'] = $orderData['status'];
    
        if ($type == 2) {  //线下充值
            $res['name'] = '线下' . $paymentData['name'] . '转账';
        } else {    //线上充值
            $res['name'] = '线上' . $paymentData['name'] . '充值';
        }
    
        include template('wallet/payOK');
    }
    
    /**
     * 线上充值成功跳转页面（固定老版本）
     * 
     * @return web
     */
    public function rechargeOk()
    {
        //接收参数
        $order_sn   = trim($_REQUEST['order_sn']);   //支付订单号
        $type       = trim($_REQUEST['type']);       //支付类型：1,线上支付，2，线下支付
        $extra_code = trim($_REQUEST['extra_code']); //线下支付的附加码
        $bank_link  = trim($_REQUEST['bank_link']);   //线下支持跳转银行链接
        $JumpUrl    = $this->getUrl();
    
        $orderData   = D('accountrecharge')->getOneCoupon('status, money, payment_id', array('order_sn' => $order_sn));
        if (empty($orderData)) {
            return '充值订单号异常，请联系客服！';
        }
    
        $paymentData = D('paymentconfig')->getOneCoupon('name', array('id' => $orderData['payment_id']));
        if (empty($paymentData)) {
            return '充值方式异常，请联系客服！';
        }
    
        $res['tittle'] = $paymentData['name'] . '充值';
        $res['money']  = $orderData['money'];
        $res['status'] = $orderData['status'];
    
        if ($type == 2) {  //线下充值
            $res['name'] = '线下' . $paymentData['name'] . '转账';
        } else {    //线上充值
            $res['name'] = '线上' . $paymentData['name'] . '充值';
        }
    
        include template('wallet/payOK');
    }
    
    
    /**
     * 支付二维码生成
     */
    public function payQrcode() {
        //验证token
        $this->checkAuth();

        //接收生成二维码数据
        $qrcode_url = $_REQUEST['qrcode_url'];
        $sess_qrcode_url = session::get('qrcode_url');
        
        if ($qrcode_url != $sess_qrcode_url) {
            session::set('qrcode_url', null);
            $qrcode_url = '付款二维码url非法，请重新进行充值操作！';
        }

        O('phpQRcode');
        $errorCorrectionLevel = "L"; // 纠错级别：L、M、Q、H
        $matrixPointSize = "6"; // 点的大小：1到10
        Qrcode::png($qrcode_url, false, $errorCorrectionLevel, $matrixPointSize);
    }
    
    /**
     * 线上扫码充值页面
     */
    public function  rechargeQrcode()
    {
        //验证token
        $this->checkAuth();
        //接收参数
        $payment_id= trim($_REQUEST['payment_id']);
        $money = trim($_REQUEST['money']);
        $order_no = trim($_REQUEST['order_no']);
        $qrcode_url = session::get('qrcode_url');
        $pay_url = session::get('pay_url');
    
        //获取充值渠道信息
        $paymentInfoArr = D('paymentconfig')->getOneCoupon('type, config, nid', array('id' => $payment_id));
        if (empty($paymentInfoArr)) {
            ErrorCode::errorResponse(200021, 'Payment method does not exist');
        }

        $configArr = unserialize($paymentInfoArr['config']);
            
        include template('wallet/rechargeQrcode');
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
    
}
