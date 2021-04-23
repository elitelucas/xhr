<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 金付卡支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class JinFuKaPay extends PayInfo
{
    //请求接口Url
    public $url     = 'https://www.goldenpay88.com/gateway/orderPay';   //扫码正式（线上）调用接口
    public $bank_url = 'https://www.goldenpay88.com/gateway/orderPay';  //第三方网银接口
    public $payName = '金付卡支付';   //支付类型名称
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 248050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '金付卡支付',  //支付方式名称
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
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 248000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('jinfuka.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 248001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('jinfuka.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
            
            return $this->retArr;
        }

        $post_data = array(
            'merId'         => $config['merchantID'],  //商户号,
            'version'       => '1.0.9',                
            'terId'         => $config['terminalID'],  //端口号
            'businessOrdid' => $orderInfo,            //商户支付订单号,每次访问生成的唯一订单标识
            'orderName'     => 'Recharge',           
            'tradeMoney'    => (number_format($data['money'], 2, '.', '') * 100),  //金额,单位：分
            'payType'       => $config['payType'][$data['pay_type']]['payStr'],    //支付方式：微信，支付宝支付，QQ钱包，网银
            'appSence'      => '1002',       //应用场景 默认 PC 1001,1001 PC,1002 H5
            'asynURL'       =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'syncURL'       =>  "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo
        );
        
        $postJson = json_encode($post_data,JSON_UNESCAPED_UNICODE);
        $split = str_split($postJson, 64);
        foreach ($split as $part) {
            openssl_public_encrypt($part,$partialData,$config['platformPublicKey']); //服务器公钥加密
            $t = strlen($partialData);
            $encParam_encrypted .= $partialData;
        }
        
        $post_data['encParam'] = base64_encode(($encParam_encrypted));//加密的业务参数
        openssl_sign($encParam_encrypted, $sign_info, $config['merchantPrivateKey']);
        $post_data['sign'] = base64_encode($sign_info);//加密业务参数的签名

        //type =2返回html跳转页面数
        $retData =  [
            'type'  => $config['payType'][$data['pay_type']]['request_type'],
            'html'  => $this->httpHtml($post_data, $this->bank_url),
            'modes' => $data['pay_model']
        ];

        $this->retArr['code'] = 0;
        $this->retArr['data']  = $retData;

        return $this->retArr;
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
        parse_str($postData['data'], $data);
        $payment_id = $postData['payment_id'];

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 248020;
            payLog('jinfuka.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
            
            return $this->orderInfo;
        }
        
        $flag = openssl_verify(base64_decode($data['encParam']),base64_decode($data['sign']),$config['platformPublicKey']);
        if(!$flag){
            $this->orderInfo['code'] = 248022;
            payLog('jinfuka.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        $jsonData = $this->decrypt($data['encParam'], $config['merchantPrivateKey']);
        $resData = json_decode($jsonData, true);
        
        //支付完成后跳转页面
        if ($resData['notifyType'] != '1001') {
            // header("Location: https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $data['orderNo']); //支付返回页面跳转
        
            exit;
        }

        if(!isset($resData['order_state']) || $resData['order_state'] != '1003') {
            $this->orderInfo['code'] = 248021;
            payLog('jinfuka.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '异步通知：订单支付失败，' . print_r($resData, true));
    
            return $this->orderInfo;
        }

        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $resData['orderId'];
        $this->orderInfo['amount']    = ($resData['money'] / 100);
        $this->orderInfo['serial_no'] = $resData['payOrderId'];

        return $this->orderInfo;
    }

    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function httpPost($postdata, $url)
    {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded;',
            'charset=utf-8;'
        ));

        $response=curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        //$res=simplexml_load_string($response);
        $res = (array)simplexml_load_string($response);

        return array('code' => $httpCode, 'data' => (array)$res['response']);
    }

    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    function httpHtml($post_data, $url)
    {
        $html =  "<html>";
        $html .= "<head>";
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= "<title>会员充值</title>";
        $html .= "</head>";
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="' . $url . '">';
        foreach ($post_data as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '"  value="' . $value.'"/>';
        }
        
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
        
        return $html;
    }
    
    //rsa解密
    /**
     * 
     * @param string $data RSA加密数据
     * @param $privateKey  商户私钥
     * @return bool|array 解密数据
     */
    public function decrypt($data, $privateKey)
    {
        $ret = '';
        
        $priKey = openssl_get_privatekey($privateKey);
        $data = base64_decode($data);
        $split = str_split($data, 128);
        
        foreach($split as $k => $v){
            openssl_private_decrypt($v, $decrypted, $priKey);
            $ret .=  $decrypted;
        }
    
        return $ret;
    }
}