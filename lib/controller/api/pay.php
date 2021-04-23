<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/14 10:06
 * @description api支付接口
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class PayAction extends Action
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 提交支付
     * @ money 充值金额
     * @ token 用户token
     * @ payment_id 支付平台的标识（nid）
     * @ remark 充值备注
     * @ type 优惠方式
     * @ childType 支付方式标识
     */
    public function doPay()
    {
        //获取接口数据
        $data = [];

        try{
            //验证token
            $this->checkAuth();

            if (isset($_REQUEST['type'])) {
                //旧支付接口数据
                $data['payment_id']   = trim(isset($_REQUEST['type']) ? $_REQUEST['type'] : '');
            }else {
               // 新支付接口数据
                $data['payment_id']   = trim(isset($_REQUEST['payment_id']) ? $_REQUEST['payment_id'] : ''); //支付id对应数据库中un_payment_config中的id
            }
            
            $data['user_id']      = $this->userId; //用户ID
            $data['pay_type']     = trim(isset($_REQUEST['pay_type']) ? $_REQUEST['pay_type'] : '');          //支付类型，1：线上支付，2：线下支付
            $data['channel_type'] = trim(isset($_REQUEST['channel_type']) ? $_REQUEST['channel_type'] : '');  //渠道类型，旧支付需要
            $data['money']        = trim(isset($_REQUEST['money']) ? $_REQUEST['money'] : '');                //充值金额，整数或两位以内小数
            $data['method']       = trim(isset($_REQUEST['method']) ? $_REQUEST['method'] : '');             //客户端类型：mobile（移动）和 pc(电脑）
            $data['bank_code']    = trim(isset($_REQUEST['bank_code']) ? $_REQUEST['bank_code'] : '');        //网银充值时，选择的银行代码
            $data['code']         = trim(isset($_REQUEST['extra_code']) ? $_REQUEST['extra_code'] : '');      //线下支付附加码
            $data['wap']          = 'wap';      //临时使用WAP支付跳转标志
            //var_dump($data);

            //调用统一支付类
            $result = D('payadmin')->doPay($data);

            if (!is_array($result) || $result['code'] == 0) {
                
                if ($result['data']['type'] == 2) {
                    //app中，web支付页面直接输出回html
                    echo $result['data']['html'];
                    exit;
                }else {
                    ErrorCode::successResponse($result['data']);
                }
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
     * 支付平台回调
     * 每个支付平台返回的参数都不太一样，根据payment_id确定是哪个支付平台
     */
    public function doPaycallBack()
    {
        try{
            $data['data'] = file_get_contents("php://input");
            $data['payment_id']   = trim(isset($_GET['payment_id']) ? $_GET['payment_id'] : 0);
            
            D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 4]);
            //处理url不能带参数的数据（/分割路由和数据键值对的处理）
            if (empty($data['payment_id'])) {
                $urlPathData = explode('?', ltrim($_SERVER['REQUEST_URI'], '/'), 2);
                $urlData = explode('/', $urlPathData[0]);
                
                if (count($urlData) > 4) {
                    $count_param = intval((count($urlData) - 3) / 2);
                    if ($count_param > 0) {
                        $i = 1;
                        while ($i <= $count_param) {
                            $key = $this->safe_deal($urlData[$i + 2]);
                            $val = $this->safe_deal($urlData[$i + 3]);
                            $data[$key] = $val;
                            $i++;
                        }
                    } else {
                        payLog('payerror.log', '/传参错误，回调处理失败:urlData:' . print_r($urlData, true) . '回调数据：' . print_r($_REQUEST,true));
                        
                        exit(0);
                    }
                }else {
                    payLog('payerror.log', '/传参错误，回调处理失败:urlData:' . print_r($urlData, true) . '回调数据：' . print_r($_REQUEST, true));
                        
                    exit(0);
                }
            }

            payLog('dopaycallback.log', '异步充值通知数据：' . print_r($data, true));
            //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 4]);

            $result = D('payadmin')->doPaycallBack($data);
        }catch(\Exception $e){
            payLog('payerror.log', '回调处理失败:' . $e);
            
            exit(0);
        }

        exit($result);
    }
    
    /**
     * 线上充值成功跳转页面
     * @return web
     */
    public function payOk()
    {
        //接收参数
        $order_sn   = trim(isset($_REQUEST['order_sn']) ? $_REQUEST['order_sn'] : '');     //支付订单号
        $type       = trim(isset($_REQUEST['type']) ? $_REQUEST['type'] : '');             //支付类型：1,线上支付，2，线下支付
        $extra_code = trim(isset($_REQUEST['extra_code']) ? $_REQUEST['extra_code'] : ''); //线下支付的附加码
        $bank_link  = trim(isset($_REQUEST['bank_link']) ? $_REQUEST['bank_link'] : '');   //线下支持跳转银行链接
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
     * 充值成功
     * @return web
     */
    public function rechargeOk() {
        //验证token
        $this->checkAuth();
    
        //接收参数
        $order_sn   = trim(isset($_REQUEST['order_sn']) ? $_REQUEST['order_sn'] : '');   //支付订单号
        $type       = trim(isset($_REQUEST['type']) ? $_REQUEST['type'] : '');       //支付类型：1,线上支付，2，线下支付
        $extra_code = trim(isset($_REQUEST['extra_code']) ? $_REQUEST['extra_code'] : ''); //线下支付的附加码
        $bank_link  = trim(isset($_REQUEST['bank_link']) ? $_REQUEST['bank_link'] : '');   //线下支持跳转银行链接
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
     * 线上充值金额设置
     */
    public function  rechargeOnlineMoney()
    {
        //验证token
        $this->checkAuth();

        //接收参数
        $payment_id = trim(isset($_REQUEST['payment_id']) ? $_REQUEST['payment_id'] : 0);

        //查询充值最低限额
        //初始化redis
        $redis = initCacheRedis();
        $Config= $redis -> HMGet("Config:recharge",array('value'));
        //关闭redis链接
        deinitCacheRedis($redis);
    
        //获取充值渠道信息
        $paymentInfoArr = D('paymentconfig')->getOneCoupon('type, nid, config', array('id' => $payment_id));
        if (empty($paymentInfoArr)) {
           ErrorCode::errorResponse(200021, 'Payment method does not exist');
        }
        
        $configArr = unserialize($paymentInfoArr['config']);
        $channel = $configArr['channel_type'];
        $user_id = $this->userId;
        $avatar = session::get('avatar');
        $nickname = session::get('nickname');
    
        include template('wallet/onLineRechargeMoney');
    }
    
    
    
    
    /**
     * 支付二维码生成
     */
    public function payQrcode() {
        //验证token
        $this->checkAuth();

        //接收生成二维码数据
        $qrcode_url = trim(isset($_REQUEST['qrcode_url']) ? $_REQUEST['qrcode_url'] : '');
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
     * 线上扫码充值页码
     */
    public function  rechargeQrcode()
    {
        //验证token
        $this->checkAuth();

        //接收参数
        $payment_id = trim(isset($_REQUEST['payment_id']) ? $_REQUEST['payment_id'] : 0);
        $money      = trim(isset($_REQUEST['money']) ? $_REQUEST['money'] : 0);
        $order_no   = trim(isset($_REQUEST['order_sn']) ? $_REQUEST['order_sn'] : '');
        $qrcode_url = session::get('qrcode_url');
        $pay_url    = session::get('pay_url');
    
        //获取充值渠道信息
        $paymentInfoArr = D('paymentconfig')->getOneCoupon('type, config, nid', array('id' => $payment_id));
        if (empty($paymentInfoArr)) {
            ErrorCode::errorResponse(200021, 'Payment method does not exist');
        }
        
        $configArr = unserialize($paymentInfoArr['config']);
            
        include template('wallet/rechargeQrcode');
    }
    
    /**
     * 线上充值
     */
    public function rechargeOnline()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('type', 'money', 'channel_type','pay_type'));
        //验证token
        $this->checkAuth();
        //接收参数
        $payment_id = trim($_REQUEST['type']);//支付id对应数据库中un_payment_config中的id
        $pay_type = trim($_REQUEST['pay_type']);//支付类型
        $channel_type = trim($_REQUEST['channel_type']);//渠道类型
        $money = trim($_REQUEST['money']);//充值金额
        if ($payment_id < 117) {
            //生成随机订单号
            $orderSn = "CZ" . $this->orderSn();
            //生成订单
            $data = array(
                'order_sn' => $orderSn,
                'payment_id' => $payment_id,
                'user_id' => $this->userId,
                'money' => $money,
                'addtime' => SYS_TIME,
                'addip' => ip(),
            );
            $result = $this->model->add($data);
            if (!$result) {
                ErrorCode::errorResponse(100016, 'Failed to generate order');
            }
    
            //添加后台提示信息
            //        $fRechare = $this->model->getOneCoupon('COUNT(1) as num', array('user_id' => $this->userId));
            //        $type = $fRechare['num'] > 1 ? 'recharge_msg' : 'fr_msg';
            //        addMsgCue($type, array('user_id' => $this->userId, 'money' => $money, 'type'=>'Online'));
    
    
            $modes = "";
            //发起支付请求
            if ($channel_type == 1) {//爱益支付
                if($pay_type == 'cibweixin')
                {
                    $modes = 1;
                }
                elseif($pay_type == 'cibalipay')
                {
                    $modes = 2;
                }
                $res = $this->ayRechargeOnline($pay_type, $orderSn, $money, $payment_id);
                ErrorCode::successResponse(array('code_url' => $res['code_url'], 'pay_url' => $res['pay_url'], 'order_no' => $orderSn, 'modes'=>$modes));
            } elseif($channel_type == 2) {//迅汇宝
                if($pay_type == 1)
                {
                    $modes = 2;
                }
                elseif($pay_type == 2)
                {
                    $modes = 1;
                }
                $res = $this->xhbRechargeOnline($pay_type, $orderSn, $money, $payment_id);
                ErrorCode::successResponse(array('code_url' => $res['code_url'], 'pay_url' => $res['pay_url'], 'order_no' => $orderSn,'modes'=>$modes));
            } elseif($channel_type == 3) {//易宝
                O('payment')->ybPay($orderSn, $money, $payment_id);
            }elseif($channel_type == 5) {//闪付
                O('payment')->sfPay($pay_type, $orderSn, $money, $payment_id);
            }elseif($channel_type == 6) {//乐盈
                O('payment')->lyPay($orderSn, $money, $payment_id);
            }elseif($channel_type == 7) {//多得宝
                $res = O('payment')->ddbPay($orderSn, $money, $payment_id);
                if($pay_type == 'weixin_scan')
                {
                    $modes = 1;
                }
                elseif($pay_type == 'alipay_scan')
                {
                    $modes = 2;
                }
                ErrorCode::successResponse(array('code_url' => $res['qrcode'], 'pay_url' => $res['pay_url'], 'order_no' => $orderSn,'modes'=>$modes));
            }elseif($channel_type == 8) {//快付
                O('payment')->kfPay($pay_type, $orderSn, $money, $payment_id);
            }elseif($channel_type == 9) {//beeepay支付
                if($pay_type == 'WXPAY')
                {
                    $modes = 1;
                }
                elseif($pay_type == 'ALIPAY')
                {
                    $modes = 2;
                }
                if($pay_type == "BANK_PAY")
                {
                    O('payment')->bPayWanApp($pay_type, $orderSn, $money, $payment_id);
                }
                else
                {
                    $paymentInfoArr = D('paymentconfig')->getOneCoupon('*', array('id' => $payment_id));
                    $configArr = unserialize($paymentInfoArr['config']);
                    if($configArr['type'] == 0)
                    {
                        $res = O('payment')->bpByWeChatAndAlipayApp($pay_type, $orderSn, $money, $payment_id);
                        ErrorCode::successResponse(array('code_url' => $res['payment_online_response']['qrcode_url'], 'pay_url' => $res['pay_url'], 'order_no' => $orderSn,'modes'=>$modes));
                    }
                    else
                    {
                        O('payment')->bPayWanApp($pay_type, $orderSn, $money, $payment_id);
                    }
                }
            }elseif($channel_type == 10){//智付支付
                header("location:http://xinxing44.top/zfzf.php?money=".$money."&payment_id=".$payment_id."&user_id=".$this->userId."&host=".$_SERVER['HTTP_HOST']."&type=app&platform=1");
    
                //O('payment')->zfPayApp($pay_type, $orderSn, $money, $payment_id);
            }elseif($channel_type == 11){//智付支付
                O('payment')->nfPay($pay_type, $orderSn, $money, $payment_id);
            }
        }else {
            //新支付接口,所有新支付接口都是调用这个接口
            (new PayAction())->doPay();
        }
    }
    
    /**
     * 安全处理函数
     * 处理/url带参数的数据
     */
    private function safe_deal($str) {
        return str_replace(array('/', '.'), '', $str);
    }
}
