<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 16:06
 * desc: 线下充值
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class RechargeAction extends Action {

    /**
     * 数据表
     */
    private $model;
    private $model1;

    public function __construct() {
        parent::__construct();
        $this->model = D('accountRecharge');
        $this->model1 = D('sysmessagelog');
    }

    /**
     * 充值方式列表（线上和线下）
     * @return html
     */
    public function index()
    {
        $this->checkAuth();  //验证token
        
        $model = D('recharge');
        
        $chargeData = $model->getRechargeListInfo($this->userId); //获取充值限额、用户类型、层级和层级状态
        

        //游客没有获取充值列表权限
        if($chargeData['reg_type'] == 8)
        {
            header("location:?m=web&c=account&a=index");
        }

        $offlineList = $model->getOfflineList($this->userId, $chargeData);  //获取线下充值列表
        
        $onlineList  = $model->getOnlineList($this->userId, $chargeData);   //获取线上充值列表
        
        $JumpUrl = $this->getUrl();

        include template('wallet/recharge');
    }
    
    
    /**
     * 提交支付(线上和线下)统一接口
     * @ money 充值金额
     * @ payment_id 支付平台的标识（nid）
     * @ remark 充值备注
     * @ type 优惠方式
     * @ childType 支付方式标识
     */
    public function recharge()
    {
        $data = [];  //接口参数

        try{
            //验证用户登录
            $this->checkAuth();

            //获取接口数据
            $data['user_id']      = $this->userId;                    //用户ID
            $data['payment_id']   = trim($_REQUEST['payment_id']);    //支付id对应数据库中un_payment_config中的id
            $data['method']       = trim($_REQUEST['method']);        //客户端模式：mobile（移动端）和pc（电脑端）
            $data['channel_type'] = trim($_REQUEST['channel_type']);  //支付类型方式
            $data['money']        = trim($_REQUEST['money']);         //充值金额
            $data['bank_code']    = trim($_REQUEST['bank_code']);     //网银充值时，选择的银行代码
            $data['code']         = trim($_REQUEST['extra_code']);    //线下支付附加码

            show_log(var_export($data, true));
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
            payLog('payerror.log', '获取支付信息失败：' . print_r($e, true));
    
            ErrorCode::errorResponse(200010, 'Failed to obtain payment information');
        }
    }
    
    /**
     * 线上充值输入金额页面
     */
    public function  rechargeOnlineMoney()
    {
        $prompt = [];
        $this->checkAuth();  //验证token

        $channel_type = trim($_REQUEST['channel_type']);  //支付方式（如：微信扫码，微信WAP）
        $payment_id = trim($_REQUEST['payment_id']);      //接收参数
        if (empty($payment_id) || !is_numeric($payment_id) ||(int)$payment_id != $payment_id) {
            return '提交的参数错误！';
        }
    
        //查询充值最低限额
        //初始化redis
        $redis = initCacheRedis();
        $Config= $redis -> HMGet("Config:recharge",array('value'));

        //快捷充值
        $re = $redis->hget('Config:quick_cash_set','value');
        $cashing = decode($re);
        sort($cashing);

        //关闭redis链接
        deinitCacheRedis($redis);
    
        //获取充值渠道信息
        $paymentInfoArr = D('paymentconfig')->getOneCoupon('type, nid, name, config, prompt', array('id' => $payment_id));
        if (empty($paymentInfoArr)) {
            ErrorCode::errorResponse(200020, 'Payment method does not exist');
        }

        $configArr = unserialize($paymentInfoArr['config']);
        //获取银行列表
        if ($paymentInfoArr['type'] == 75 && !empty($configArr['payType'][$channel_type]['bank_id'])) {
            $bank_info = $this->db->getall("select `id`, `name`, `" . $paymentInfoArr['nid'] . "` as bank_code from `un_bank_info` where `status` = 1 and " . $paymentInfoArr['nid'] . " != '' and `id` in (" . $configArr['payType'][$channel_type]['bank_id'] . ") order by `sort` asc");
        }
        
        $payTypeName = $paymentInfoArr['name'];
        $user_id = $this->userId;
        $avatar = session::get('avatar');
        $nickname = session::get('nickname');
        
        //处理温馨提示信息
        if (!empty($paymentInfoArr['prompt'])) {
            $prompt = explode('|', $paymentInfoArr['prompt']);
        }
        include template('wallet/rechargeOnLineMoney');
    }
    
    /**
     * 线下充值扫码页面
     */
    public function rechargeOfflineMoney()
    {
        $min_recharge = 0;    //每次充值最小金额限制
        $max_recharge = 0;    //每次充值最大金额限制
        $prompt = [];         //温馨提示信息
        
        $this->checkAuth();   //验证token
        
        $payment_id = trim($_REQUEST['payment_id']);   //接收支付类型参数
        if (empty($payment_id) || !is_numeric($payment_id)) {
            return '提交的参数错误！';
        }

        //初始化redis
        $redis = initCacheRedis();
        //充值下限
        $config= $redis -> HMGet("Config:recharge",array('value'));
        $lower_limit = $config['value'];

        //快捷充值
        $re = $redis->hget('Config:quick_cash_set','value');
        $cashing = decode($re);
        sort($cashing);

        //关闭redis链接
        deinitCacheRedis($redis);
        
        $payInfo = D('paymentconfig')->getOneCoupon('id,name,config,logo,bank_link, balance, upper_limit,bank_id, min_recharge, max_recharge, prompt', array('id' => $payment_id));
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
        
        $payType = $this->db->getone('SELECT `classid` FROM `un_dictionary` WHERE `id` = ' . $payInfo['bank_id']);

        $configArr = unserialize($payInfo['config']);
        
        $user_id = $this->userId;
        $avatar = session::get('avatar');
        $nickname = session::get('nickname');
        $payTypeName = $payInfo['name'];

        if (!empty($payInfo['prompt'])) {
            $prompt = explode('|', $payInfo['prompt']);
        }
        
        //$code = $this->userId . $this->getRandomString(6);  //获取附加码
        
        include template('wallet/rechargeOfflineMoney');
    }
    
    /**
     * 线下充值扫码页面
     */
    public function rechargeOffline()
    {
        $prompt = [];         //温馨提示信息
        
        $this->checkAuth();   //验证token
        
        $order_sn = trim($_REQUEST['order_sn']);
        $orderDetail = $this->model->getChargeDetail($order_sn, $this->userId);

        if (empty($orderDetail)) {
            return '充值订单不存在！';
        }
        
        
        if ($orderDetail['status'] != 3) {
            return '该充值订单已处理或在处理中，请勿重复充值！';
        }
    
        $payType = $this->db->getone('SELECT `classid` FROM `un_dictionary` WHERE `id` = ' . $orderDetail['bank_id']);
    
        $configArr = unserialize($orderDetail['config']);
    
        $orderDetail['account_name'] = $configArr['account_name'];
        $orderDetail['account']      = $configArr['account'];
        $orderDetail['branch']       = $configArr['branch'];
        $orderDetail['code']         = $configArr['code'];

        if (!empty($payInfo['prompt'])) {
            $prompt = explode('|', $orderDetail['prompt']);
        }
    
        $code = $orderDetail['remark'];  //获取附加码

        include template('wallet/rechargeOffline');
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
        $pay_url_type = session::get('pay_url_type');
        session::set('pay_url_type', '');
    
        //获取充值渠道信息
        $paymentInfoArr = D('paymentconfig')->getOneCoupon('type, config, nid', array('id' => $payment_id));
        if (empty($paymentInfoArr)) {
            ErrorCode::errorResponse(200021, 'Payment method does not exist');
        }
    
        $configArr = unserialize($paymentInfoArr['config']);
    
        include template('wallet/rechargeQrcode');
    }
    
    //check支付订单状态
    public function checkOrder()
    {
        //验证token
        $this->checkAuth();
        //接收参数
        $order_sn = $_REQUEST['order_sn'];
        $rows = $this->model->osDetail($order_sn, $this->userId);
        if(empty($rows))
        {
            $arr['code'] = -1;
            $arr['msg'] = "Recharge order number does not exist";
            $arr['info'] = "";
        }
        else
        {
            $arr['code'] = 0;
            $arr['msg'] = "Get data successfully";
            $arr['info'] = $rows;
        }

        jsonReturn($arr);
    }
    
    
    /**
     * 线下充值确认生成提示音
     * @return web
     */
    public function setRechargeMusic()
    {
        $ret = [];
        //接收参数
        $order_sn   = trim($_REQUEST['order_sn']);    //支付订单号
        
        $redis = initCacheRedis();
        $recharge_time= $redis->HMGet("Config:recharge_time",array('value'));
        deinitCacheRedis($redis);

        $orderData   = D('accountrecharge')->getOneCoupon('id, status, money, user_id,payment_id', array('order_sn' => $order_sn));
        if (empty($orderData)) {
            $ret['code'] = 0;
            $ret['msg'] = "The recharge order number is abnormal, please contact customer service!";
            
            jsonReturn($ret);
        }

        $flag = "user_recharge:" . $orderData['user_id'];
        superveneLock($flag, $recharge_time['value'], 1);  //防止高频操作

        if ($orderData['status'] != 3) {
            $ret['code'] = 0;
            $ret['msg'] = "The recharge order is being processed!";
            
            jsonReturn($ret);
        }
        
        $ret_status   = D('accountrecharge')->setRechargeStatus($order_sn, 0);
        if (!$ret_status) {
            $ret['code'] = 0;
            $ret['msg'] = "Recharge confirmation is abnormal, please contact customer service!";
            
            jsonReturn($ret);
        }
    
        //判断充值提示音是否重复
        $music_tips = D('user')->getMusicTips($orderData['id'], '2,5');
        if (!empty($music_tips)) {
            $ret['code'] = 1;
            $ret['msg'] = "The recharge order has been submitted, please do not repeat the operation!";
            
            jsonReturn($ret);
        }

        //添加后台提示信息
        $map = array();
        $map['id'] = $orderData['id'];
        $map['user_id'] = $orderData['user_id'];
        $map['money'] = $orderData['money'];
        $map['type'] = 1;
        D('user')->setRechargeMusic($map);

        jsonReturn(['code' => 1, 'msg' => 'The recharge submission is successful!']);
    }
    
    /**
     * 线上、线下充值成功跳转页面
     * @return web
     */
    public function rechargeOk()
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
            $res['name']   = '线下' . $paymentData['name'] . '转账';
            $res['status'] = 1; //提交订单成功（并不等于充值成功）
        } else {           //线上充值
            $res['name'] = '线上' . $paymentData['name'] . '充值';
        }
    
        include template('wallet/rechargeStatus');
    }

    /**
     * 线上、线下充值成功跳转页面
     * @return web
     */
    public function rechargeStatus()
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
        } else {           //线上充值
            $res['name'] = '线上' . $paymentData['name'] . '充值';
        }
    
        include template('wallet/rechargeStatus');
    }

    /**
     * 充值记录
     * @method get /index.php?m=api&c=recharge&a=rechargeList&token=b5062b58d2433d1983a5cea888597eb6&payment_nid=1&money=1&extra_code=1
     * @param
     * @return
     */
    //public function rechargeList() {
    public function rechargeRecordList() {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'page'));
        //验证token
        $this->checkAuth();
    
        $redis = initCacheRedis();
        $page_cfg = $redis->hGetAll("Config:100009"); //获取每页展示多少数据
        $pageCnt = $page_cfg['value'];
    
        $userId = $this->userId;
        $list = $this->model->rechargeList($userId, $_REQUEST['page'], $pageCnt);
    
        $data = array();
        $data['list'] = $list;
    
        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($data);
    }
    
    /**
     * 充值订单详情
     */
    public function rechargeOrderDetail() {
        //验证token
        $this->checkAuth();
    
        $recharge_id = $_REQUEST['recharge_id'];
        $info = $this->model->detail($recharge_id, $this->userId);
        if (empty($info)) {
            ErrorCode::errorResponse(200004, 'The deposit ID does not exist');
        }
    
        $data = array(
            'order_sn' => $info['order_sn'],
            'addtime' => date("Y-m-d H:i:s", $info['addtime']),
            'type' => $info['name'],
            'state' => $info['status'],
            'extra_code' => $info['remark'],
            'account' => $info['account']
        );
    
        include template('wallet/rechargeDatails');
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
     * 支付信息跳转页面（专用）
     * @ array post 网银签名支付信息
     */
    public function rechargeJump()
    {
        $post_data = $_REQUEST;  //支付数据数组
        
        $html = '<html>';
        $html .= '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="' . $post_data['action_url'] . '">';
        
        foreach ($post_data as $kp => $vp) {
            if ($kp != 'action_url') {
                $html .= '<input type="hidden" name="' . $kp . '" value="' . $vp . '" />';
            }
        }

        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';

        echo $html;
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
