<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 16:06
 * desc: 线下充值
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'pay.php');

class RechargeAction extends Action {

    /**
     * 数据表
     */
    private $model;
    private $model2;

    public function __construct() {
        parent::__construct();
        $this->model = D('accountRecharge');
        $this->model2 = D('user');
    }

    /**
     * 充值方式列表（线上和线下）
     * @method get /index.php?m=api&c=recharge&a=offlineIndex&token=b5062b58d2433d1983a5cea888597eb6
     * @return
     */
    public function offlineIndex()
    {
        $this->checkInput($_REQUEST, array('token'));  //验证参数

        $this->checkAuth();  //验证token

        $model = D('recharge');
        
        $chargeData = $model->getRechargeListInfo($this->userId); //获取充值限额、用户类型、层级和层级状态
        
        $offlineList = $model->getOfflineList($this->userId, $chargeData);  //获取线下充值列表

        $onlineList  = $model->getOnlineList($this->userId, $chargeData);   //获取线上充值列表

        $redis = initCacheRedis();
        //快捷充值
        $re = $redis->hget('Config:quick_cash_set','value');
        $cashing = decode($re);
        sort($cashing);
        $handsel = $this->db->result("select value from un_config where nid = 'handsel_set'");
        $data = array(
            'quick_btn'       => $cashing,
            'list'       => $offlineList,
            'list2'      => $onlineList,
            'recharge'   => $chargeData['lower_limit'],
            'online_handsel'=>$handsel
        );

        ErrorCode::successResponse($data);
    }

    /**
     * 保存线下支付数据
     * @method POST /index.php?m=api&c=recharge&a=rechargeOffline&token=b5062b58d2433d1983a5cea888597eb6&nid=1&money=1&extra_code=1
     * @param
     * @return
     */
    public function rechargeOffline()
    {
        $data = [];  //接口参数
        
        $this->checkInput($_REQUEST, array('token', 'id', 'money'), 'all');  //验证参数
        $this->checkAuth();  //验证token
        
        try{
            //获取接口数据
            $data['user_id']      = $this->userId;                    //用户ID
            $data['payment_id']   = trim($_REQUEST['id']);            //支付id对应数据库中un_payment_config中的id
            $data['money']        = trim($_REQUEST['money']);         //充值金额
        
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
     * 线下充值确认生成提示音
     * @return web
     */
    public function setRechargeMusic()
    {
        //接收参数
        $order_sn   = trim($_REQUEST['order_sn']);    //支付订单号
        
        $this->checkAuth();  //验证token
        
        if (empty($order_sn)) {
            ErrorCode::errorResponse(201001, 'Deposit order number is abnormal!');
        }
    
        $orderData   = D('accountrecharge')->getOneCoupon('id, status, money, payment_id', array('order_sn' => $order_sn));
        if (empty($orderData)) {
            ErrorCode::errorResponse(201001, 'The deposit order number is abnormal, please contact customer service!');
        }
        
        $redis = initCacheRedis();
        $recharge_time= $redis->HMGet("Config:recharge_time",array('value'));
        deinitCacheRedis($redis);
        $flag = "user_recharge:" . $this->userId;
        superveneLock($flag, $recharge_time['value'], 1);  //防止高频操作
        
        if ($orderData['status'] != 3) {
            ErrorCode::errorResponse(201001, 'The deposit order is being processed!');
        }
        
        $ret_status   = D('accountrecharge')->setRechargeStatus($order_sn, 0);
        if (!$ret_status) {
            ErrorCode::errorResponse(201001, 'Deposit confirmation is abnormal, please contact customer service!');
        }

        //判断提示音是否重复
        $music_tips = D('user')->getMusicTips($orderData['id'], '2,5');
        if (!empty($music_tips)) {
            ErrorCode::successResponse();
        }
    
        //添加后台提示信息
        $map = array();
        $map['id'] = $orderData['id'];
        $map['user_id'] = $this->userId;
        $map['money'] = $orderData['money'];
        $map['type'] = 1;
        D('user')->setRechargeMusic($map);

        ErrorCode::successResponse();
    }
    
    /**
     * 线上充值
     */

    public function getHash(){
    
        $payer_account = trim($_REQUEST['PAYER_ACCOUNT']);
        $amount = trim($_REQUEST['PAYMENT_AMOUNT']);
        $unit = "USD";
        $payment_id = uniqid();
        $payeer_account = "chenqiao0820@gmail.com";
        $API_KEY = "b7711a997d086515a56d512684e195bb";
        $hash = md5($payer_account.":".$amount.":".$unit.":".$payeer_account.":".$API_KEY);
        $url = 'https://api.epay.com/paymentApi/merPayment';
        $data = array(
            "PAYER_ACCOUNT" => $payer_account,
            "PAYEE_NAME" => "365GAME",
            "PAYMENT_AMOUNT" => $amount,
            "PAYMENT_ID" => $payment_id,
            "PAYMENT_UNITS" => $unit,
            "FORCED_PAYEE_ACCOUNT" => $payeer_account,
            "V2_HASH" => $hash
        );

        // use key 'http' even if you send the request to https://...
        $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ),
        );
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $result = json_decode($result, true);
        $result['user_id'] = $this->userId;
        $result['payer_account'] = $payer_account;
        $result['payeer_account'] = $payeer_account;
        if($result['RETURN_CODE'] == '0000'){
            $pay_result = D('payadmin')->onlinePay($result);
        }
        ErrorCode::successResponse($result);

    }

    public function globalPay(){

        $amount = trim($_REQUEST['order_amount']);

        $amount = $amount*35;
        
        $data = array(
            "mer_no" => "gm761100000029671",
            "mer_order_no" => uniqid(),
            "pname" => trim($_REQUEST['pname']),
            "pemail" => trim($_REQUEST['pemail']),
            "phone" => trim($_REQUEST['phone']),
            "order_amount" => $amount,
            "countryCode" => "THA",
            "ccy_no" => "THB",
            "busi_code" => trim($_REQUEST['busi_code']),
            "notifyUrl" => "https://365ga.me/?m=api&c=recharge&a=paySuccess",
            "goods" => "365GAME",
            "accNo" => trim($_REQUEST['accNo']),
            "bankCode" => trim($_REQUEST['bankCode']),
            "timeout_express" => "30m"
        );
        $sign = '';
        if($data['accNo']!=''){
            $sign = 'accNo='.$data['accNo'].'&';
        }
        if($data['bankCode']!=''){
            $sign .= 'bankCode='.$data['bankCode'].'&';
        }
        $sign .= 'busi_code='.$data['busi_code'].'&ccy_no='.$data['ccy_no'].'&countryCode='.$data['countryCode'].'&';
        $sign .= 'goods='.$data['goods'].'&mer_no='.$data['mer_no'].'&mer_order_no='.$data['mer_order_no'].'&';
        $sign .= 'notifyUrl='.$data['notifyUrl'].'&order_amount='.$data['order_amount'].'&pemail='.$data['pemail'].'&';
        $sign .= 'phone='.$data['phone'].'&pname='.$data['pname'].'&timeout_express='.$data['timeout_express'].'&';
        $sign .= 'key=2E6B438B7E083F173886B5A71B7C6610';
        $data['sign'] = md5($sign);
        $url = 'http://zvfdh.yudrsu.com/ty/orderPay';

        // use key 'http' even if you send the request to https://...
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //这个是重点
        curl_setopt($ch, CURLOPT_TIMEOUT,3); //防止超时卡顿
        curl_setopt($ch, CURLOPT_POST, true); //POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'X-CC-Api-Key: d0c1b1be-d46c-47b5-a727-4cea12b7288e', 'X-CC-Version: 2018-03-22'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        ErrorCode::successResponse(json_decode($response, true));

    }

    public function createCharge(){
        $data = array(
            "name" => "Crypto Payment",
            "description" => "Deposit via crypto payment",
            "pricing_type" => "no_price",
            "redirect_url" => "https://365ga.me/?m=api&c=recharge&a=resolveCharge"
        );

        $url = "https://api.commerce.coinbase.com/charges/";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //这个是重点
        curl_setopt($ch, CURLOPT_TIMEOUT,3); //防止超时卡顿
        curl_setopt($ch, CURLOPT_POST, true); //POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'X-CC-Api-Key: d0c1b1be-d46c-47b5-a727-4cea12b7288e', 'X-CC-Version: 2018-03-22'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        ErrorCode::successResponse(json_decode($response, true));
    }

    public function paySuccess(){
        $orderData = array(
            'order_sn' => $_REQUEST['order_no'],
            'payment_id' => $_REQUEST['mer_order_no'],
            'pay_type' => "Gpay payment",
            'bank_name' => $_REQUEST['busi_code'],
            'user_id' => $this->userId,
            'money' => $_REQUEST['pay_amount'],
            'status' => $_REQUEST['status'],
            'remark' => $_REQUEST['sign'],
            'addip' => ip(),
            'addtime' => $_REQUEST['order_time']
        );

        $pay_result = D('payadmin')->onlinePay($orderData);
    }

    public function resolveCharge(){
        $orderData = array(
            'order_sn' => $_REQUEST['data']['code'],
            'payment_id' => $_REQUEST['data']['payments'][0]['transaction_id'],
            'pay_type' => "Crypto payment - ".$_REQUEST['data']['payments'][0]['network'],
            'bank_name' => "coinbase",
            'user_id' => $this->userId,
            'money' => $_REQUEST['data']['applied_threshold']['amount'],
            'status' => $_REQUEST['data']['payments'][0]['status'],
            'remark' => $_REQUEST['data']['payments'][0]['block']['hash'],
            'addip' => ip(),
            'addtime' => SYS_TIME
        );

        $pay_result = D('payadmin')->onlinePay($orderData);
    }

    public function rechargeOnline()
    {
        $data = [];  //接口参数
        
        $this->checkInput($_REQUEST, array('type', 'money', 'channel_type','pay_type'));  //验证参数
        $this->checkAuth();  //验证token
        
        try{
            //获取接口数据
            $data['user_id']      = $this->userId;                    //用户ID
            $data['payment_id']   = trim($_REQUEST['type']);          //支付type对应数据库中un_payment_config中的id
            $data['method']       = trim($_REQUEST['method']);        //客户端模式：mobile（移动端）和pc（电脑端）
            $data['pay_type']     = trim($_REQUEST['pay_type']);      //支付类型
            $data['channel_type'] = trim($_REQUEST['channel_type']);  //渠道类型
            $data['money']        = trim($_REQUEST['money']);         //充值金额
            $data['bank_code']    = trim($_REQUEST['bank_code']);     //网银充值时，选择的银行代码
            $data['wap']          = 'wap';      //临时使用WAP支付跳转标志
        
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
     * 支付平台回调
     * 每个支付平台返回的参数都不太一样，根据payment_id确定是哪个支付平台
     */
    public function rechargeNotify()
    {
        try{
            payLog('dopaycallback.log', '异步充值通知数据：' . print_r("已发起", true));
            @$data['data'] = file_get_contents("php://input");
            if (empty($data['data'])||$data['data']==" ") {
                @$data['data'] = $_POST;
                payLog('payerror.log', '---159-POST--:urlData:' . print_r($data, true));
                if (empty($data['data'])||$data['data']==" ") {
                    @$data['data'] = $_GET;
                    payLog('payerror.log', '---161-GET--:urlData:' . print_r($data, true));
                }
                if (empty($data['data'])||$data['data']==" ") {
                    @$data['data'] = $_REQUEST;
                    payLog('payerror.log', '---166-REQUEST-:urlData:' . print_r($data, true));
                }
            }

//            $data['data'] = file_get_contents("php://input");

            @$data['payment_id']   = isset($_GET['payment_id']) ? intval($_GET['payment_id']): 0;

            payLog('dopaycallback.log', '异步充值通知数据：' . print_r($data, true));

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
                
                D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);
            }

            $result = D('payadmin')->doPaycallBack($data);

        }catch(\Exception $e){
            payLog('payerror.log', '回调处理失败:' . $e);
    
            exit(0);
        }
        
        exit($result);
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
    
    /**
     * 支付二维码生成
     */
    public function payQrcode() {
        //验证token
        $this->checkAuth();
    
        //接收生成二维码数据
        $qrcode_url = trim(isset($_REQUEST['qrcode_url']) ? $_REQUEST['qrcode_url'] : '');
       
        /*
        $sess_qrcode_url = session::get('qrcode_url');

        if ($qrcode_url != $sess_qrcode_url) {
            session::set('qrcode_url', null);
            $qrcode_url = '付款二维码url非法，请重新进行充值操作！';
        }
        */
    
        O('phpQRcode');
        $errorCorrectionLevel = "L"; // 纠错级别：L、M、Q、H
        $matrixPointSize = "6"; // 点的大小：1到10
        Qrcode::png($qrcode_url, false, $errorCorrectionLevel, $matrixPointSize);
    }

    /**
     * 充值记录
     * @method get /index.php?m=api&c=recharge&a=rechargeList&token=ajhdke3qb0rh5pk21vlp4rl3q4&page=0
     * @param
     * @return
     */
    public function rechargeList() {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'page'));
        //验证token
        $this->checkAuth();

        $redis = initCacheRedis();
        $page_cfg = $redis->hGetAll("Config:100009"); //获取每页展示多少数据
        $pageCnt = $page_cfg['value']?$page_cfg['value']:20;

        $userId = $this->userId;
        $list = $this->model->rechargeList($userId, $_REQUEST['page'], $pageCnt);

        $data = array();
        $data['list'] = $list;

        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($data);
    }

    /**
     * 充值详情
     */
    public function detail() {
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'recharge_id'));
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
        ErrorCode::successResponse($data);
    }

    /**
     * 充值状态信息
     */
    public function getRechargeInfo() {
        //验证参数
        $this->checkInput($_REQUEST, array('order_no'));
        //验证token
        $this->checkAuth();
        $order_no = trim($_REQUEST['order_no']);
        $rechargeInfo = D('accountrecharge')->getOneCoupon('status, money, payment_id', array('order_sn' => $order_no));

        ErrorCode::successResponse(array('state' => $rechargeInfo['status'], 'money' => $rechargeInfo['money']));
    }
    
    /**
     * 网银充值时，充值银行选择列表
     */
    public function getRechargeBankList()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('payment_id'));
        //验证token
        $this->checkAuth();
        $payment_id = trim($_REQUEST['payment_id']);
        $bank_code = 'bank_code';
        
        $paymentData = D('paymentconfig')->getOneCoupon('type, config, nid', array('id' => $payment_id));
        if (empty($paymentData) || $paymentData['type'] != 75) {
            ErrorCode::errorResponse(100026, 'No corresponding bank list exists');
        }
        
        $configArr = unserialize($paymentData['config']);
        
        if ($paymentData['type'] == 75 && !empty($configArr['payType']['wy']['bank_id'])) {
            $bank_info = $this->db->getall("select `id`, `name`, `" . $paymentData['nid'] . "` as bank_code from `un_bank_info` where `status` = 1 and " . $paymentData['nid'] . " != '' and `id` in (" . $configArr['payType']['wy']['bank_id'] . ") order by `sort` asc");
        } else {
            ErrorCode::errorResponse(100026, 'The payment method does not need to choose a specific bank');
        }

        if (empty($bank_info)) {
            ErrorCode::errorResponse(100026, 'Error getting bank list');
        }

        ErrorCode::successResponse(array('bankList' => $bank_info));
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
     * 安全处理函数
     * 处理/url带参数的数据
     */
    private function safe_deal($str) {
        return str_replace(array('/', '.'), '', $str);
    }
}
