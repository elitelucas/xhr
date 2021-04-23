<?php
/**
 * @author kevin
 * @copyright HCHT 2017/12/6 10:00
 * @description 星和易通支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class XingHeYiPay extends PayInfo
{
    //请求接口Url
    //public $url = 'http://gate.starspay.com/cooperate/gateway.cgi';               //扫码测试调用接口
    public $url      = 'http://gate.starspay.com/cooperate/gateway.cgi';  //扫码正式（线上）调用接口
    public $bank_url = 'http://gate.starspay.com/cooperate/gateway.cgi';  //第三方网银接口
    public $payName = '星和易通支付';     //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 215050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '星和易通支付',       //支付方式名称
        'serial_no' => ''            //第三方回调返回的第三方支付订单号（支付流水号）
    ];

    /**
     * 构成函数
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 调用第三方支付接口，获取支付信息
     * @param array $data 前端返回信息，payment_id，支付类型ID，config，支付类型配置信息
     * {@inheritDoc}
     * @see PayInfo::doPay()
     * @return $this->$retArr;
     */
    public function doPay($data)
    {
        //首次生成payment_config表中的config信息
        //$this->setBaseConfig($data['payment_id']);

        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 215000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 215001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '的支付类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }
        
        $post_data = [
            'version'   => "1.0.0.0",//接口版本
            'merId'     => $config['merchantID'],//商户账号
            'tradeNo'   => $orderInfo,//商户订单号
            'tradeDate' => date("Ymd"),//交易日期
            'amount'    => number_format($data['money'], 2, '.', ''),//订单金额
            'summary'   => "recharge",//交易摘要
            'clientIp'  => ip(),//客户端IP
            'extra'     => '',
            'expireTime' => '',
            //'notifyUrl' => "https://".$_SERVER['HTTP_HOST']
            'notifyUrl' => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'] //支付结果通知地址
        ];
        
        if ($data['pay_type'] == 'wy') {
            //网银支付
            $post_data['service'] = "TRADE.B2C"; //接口名字
            //$post_data['bankId'] = $data['bank_code']; //银行简码
            $post_data['bankId'] = ''; //跳转到支付平台选择银行
            $postStr = $this->getString($post_data);
            $post_data['sign'] = md5($postStr . $config['merchantKey']);
        
            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $this->bank_url)
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        }else {
            //非网银支付，微信、支付宝、QQ钱包支付
            $post_data['service'] = "TRADE.SCANPAY";//接口名字
            $post_data['typeId']  = $config['payType'][$data['pay_type']]['payStr'];//类型ID  1；支付宝 2；微信 3；QQ钱包
            $postStr = $this->getString($post_data);
            $post_data['sign'] = md5($postStr . $config['merchantKey']);

            //curl接口
            $curlData = $this->httpPost($post_data, $this->url);
            var_dump($curlData);
    
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 215002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            
            $retData = json_decode($curlData['data'], true);
            var_dump($retData);
            
            if (!isset($retData['message']['detail']['code']) || $retData['message']['detail']['code'] != '00') {
                $this->retArr['code'] = 215003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $retSign = getSigned($retData['message']['detail'], ['key' => $config['merchantKey']]);
            if($retSign != $retData['message']['sign']){
                $this->retArr['code'] = 215004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            $retOderNo = $orderInfo;
            $retOderPayNo = 'xingheyitongpay:' . date('YmdHis');
            $retOderPayQrcodrUrl = isset($retData['message']['detail']['qrCode']) ? $retData['message']['detail']['qrCode'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty(retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 215005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据错误，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 215006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
                
                return $this->retArr;
            }

            D('accountRecharge')->save(['remark' => $retOderPayNo], ['order_sn' => $retOderNo]);
    
            //用于安全验证返回url是否非法
            session::set('qrcode_url', $retOderPayQrcodrUrl);
            session::set('pay_url', '');
            //type =1 返回二维码数据 2，返回html整页数据
            $ret =  [
                'type'     => $config['payType'][$data['pay_type']]['request_type'],
                'code_url' => $retOderPayQrcodrUrl,
                'pay_url'  => '',
                'order_no' => $retOderNo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data'] = $ret;
    
            return $this->retArr;
        }
    }

    /**
     * 支付回调处理
     * @param array $postData data：回调返回的数据，payment_Id：支付类型ID
     * {@inheritDoc}
     * @see PayInfo::doPaycallBack()
     * @return array $this->$retArr;
     */
    public function doPaycallBack($postData)
    {
        //处理post回调数据
        //$data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 215020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($postData, true));
        
            return $this->orderInfo;
        }

        $data = [
            'service'   => $_REQUEST["service"],//接口名字
            'merId'     => $_REQUEST["merId"],//商户账号
            'tradeNo'   => $_REQUEST["tradeNo"],//商户订单号
            'tradeDate' => $_REQUEST["tradeDate"],//交易日期
            'amount'    => $_REQUEST["amount"],//订单金额
            'opeNo'     => $_REQUEST["opeNo"],//交易摘要
            'opeDate'   => $_REQUEST["opeDate"],//客户端IP
            'status'    => $_REQUEST["status"],
            'payTime'   => $_REQUEST["payTime"],
            'sign'      => $_REQUEST["sign"],
            'extra'     => $_REQUEST["extra"],
            
        ];
        
        $notifyType = $_REQUEST["notifyType"]; //0-前端页面通知，商户系统需要给客户展示购物成功的页面。1-后台服务器，商户需返回数据。
        if (empty($notifyType) || $notifyType != 1) {
            Header("Location: https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $data['tradeNo']);
            exit;
        }

        if (!isset($data['status']) || $data['status'] != 1) {
            $this->orderInfo['code'] = 215021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merId']    = $config['merchantID'];
        $postStr = $this->getString($data);
        $retSign = md5($postStr . $config['merchantKey']);
        //var_dump($retSign);
        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 215022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['tradeNo'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['payTime'];

        return $this->orderInfo;
    }
        
       
    public function getString($data)
    {
        //1网银支付
        if($data['service'] == 'TRADE.B2C') {
            $result = sprintf(
                "service=%s&version=%s&merId=%s&tradeNo=%s&tradeDate=%s&amount=%s&notifyUrl=%s&extra=%s&summary=%s&expireTime=%s&clientIp=%s&bankId=%s",
                $data['service'],
                $data['version'],
                $data['merId'],
                $data['tradeNo'],
                $data['tradeDate'],
                $data['amount'],
                $data['notifyUrl'],
                $data['extra'],
                $data['summary'],
                $data['expireTime'],
                $data['clientIp'],
                $data['bankId']
                );
    
            return $result;
            //2扫码支付
        }else if($data['service'] == 'TRADE.SCANPAY'){
            $result = sprintf(
                "service=%s&version=%s&merId=%s&typeId=%s&tradeNo=%s&tradeDate=%s&amount=%s&notifyUrl=%s&extra=&summary=%s&expireTime=&clientIp=%s",
                $data['service'],
                $data['version'],
                $data['merId'],
                $data['typeId'],
                $data['tradeNo'],
                $data['tradeDate'],
                $data['amount'],
                $data['notifyUrl'],
                //urlencode($data['notifyUrl']),
                $data['summary'],
                $data['clientIp']
                );
    
            return $result;
        //7回调
        }else if($data['service'] == 'TRADE.NOTIFY'){
            $result = sprintf(
                "service=%s&merId=%s&tradeNo=%s&tradeDate=%s&opeNo=%s&opeDate=%s&amount=%s&status=%s&extra=%s&payTime=%s",
                $data['service'],
                $data['merId'],
                $data['tradeNo'],
                $data['tradeDate'],
                $data['opeNo'],
                $data['opeDate'],
                $data['amount'],
                $data['status'],
                $data['extra'],
                $data['payTime']
                );
            return $result;
            //h5支付
        }else if($data['service'] == 'TRADE.H5PAY'){
            $result = sprintf(
                "service=%s&version=%s&merId=%s&typeId=%s&tradeNo=%s&tradeDate=%s&amount=%s&notifyUrl=%s&extra=%s&summary=%s&expireTime=%s&clientIp=%s",
                $data['service'],
                $data['version'],
                $data['merId'],
                $data['typeId'],
                $data['tradeNo'],
                $data['tradeDate'],
                $data['amount'],
                $data['notifyUrl'],
                $data['extra'],
                $data['summary'],
                $data['expireTime'],
                $data['clientIp']
                );
            return $result;
        }

    }
    
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    public function httpPost($data, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        var_dump($res);
        $response = simplexml_load_string($res);
        return array('code' => $httpCode, 'data' => $response);
    }

    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    /**
     * 创建表单
     * @data		表单内容
     * @gateway 支付网关地址
     */
    function httpHtml($post_data, $url) {
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<title>会员充值</title>";
        $html .= "</head>";
        $html .= "<body onLoad='document.form1.submit()'>";
        $html .= "<form id='payFrom' name='form1' action='" . $url . "' method='post'>";
        foreach ($post_data as $key => $val) {
            $html.= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
    
        return $html;
    }
    
    /**
     * 支付初始数据配置数据库
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
        $data['name'] = '星和易通支付';
        $data['merchantID'] = '2017090835010200';
        $data['merchantKey'] = '827efbc0b1ac2a3dfa7ece870e69e00d';
        $data['payType']['wx']['name'] = '微信支付';
        $data['payType']['wx']['payStr'] = 2;
        $data['payType']['wx']['request_type'] = 1;
        $data['payType']['ali']['name'] = '支付宝支付';
        $data['payType']['ali']['payStr'] = 1;
        $data['payType']['ali']['request_type'] = 1;
        $data['payType']['qq']['name'] = 'QQ钱包支付';
        $data['payType']['qq']['payStr'] = 3;
        $data['payType']['qq']['request_type'] = 1;
        $data['payType']['wy']['name'] = '网银支付';
        $data['payType']['wy']['payStr'] = '';
        $data['payType']['wy']['request_type'] =2;
        $data['payType']['wy']['bank_id'] = '';
        
        $serData = serialize($data);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}