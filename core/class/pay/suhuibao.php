<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/10/18 16:06
 * @description 速汇宝支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class SuHuiBaoPay extends PayInfo
{
    //请求接口Url
    //public $url = 'https://api.zfbill.net/gateway/api/scanpay';  //扫码调用测试接口
    public $url = 'https://api.zfbill.net/gateway/api/scanpay';  //扫码调用正式（线上）接口
    public $bank_url = 'https://pay.zfbill.net/gateway?input_charset=UTF-8';  //网银支付接口
    
    //返回数据格式
    public $retArr = [          //支付信息返回数字
            'code' => 0,
            'msg' => '',
            'data' => []
        ];
    public $orderInfo = [       //异步验签结果返回格式
        'code' => 1,
        'bank_num' => 209050,   //银行区分号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => 0,
        'ret_success' => 'SUCCESS',
        'bank_name' => '速汇宝支付',
        'serial_no' => ''        //流水号
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取线上支付信息
     * {@inheritDoc}
     * @see PayInfo::doPay()
     */
    public function doPay($data)
    {
        //首次生成payment_config表中的config信息
        //$this->setBaseConfig($data['payment_id']);
        
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 209000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 209001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
            
            return $this->retArr;
        }

        $post_data = array(
            'merchant_code' => $config['merchantID'],   //合作方编号,代理商ID
            'interface_version'  => 'V3.1',       //接口版本，固定值：V3.1(必须大写)
            'order_no'   => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
            'order_time' => date('Y-m-d H:i:s'),  //商户订单时间
            'order_amount'    => number_format($data['money'], 2, '.', ''),  //金额,精确到两位小数(没有两位小数会出错）
            'service_type' => $config['payType'][$data['pay_type']]['payStr'],  //支付方式：微信，支付宝支付，QQ钱包，网银
            'product_name'   => '会员充值',   //商品主题
            'client_ip'  => ip(),                  //用户IP,发起支付请求客户端的 ip
            //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
            'notify_url' =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id']
        );

        //网银支付调用
        if ($data['pay_type'] == 'wy') {
            //参数名称：支付类型取值如下（必须小写，多选时请用逗号隔开）b2c(网银支付),weixin（微信扫码）,alipay_scan（支付宝扫码）,qq_scan（qq钱包扫码）
            $post_data['pay_type'] = 'b2c';
            //网银interface_version = 3.0
            $post_data['interface_version'] = 'V3.0';
            //参数名称：参数编码字符集,取值：UTF-8、GBK(必须大写)
            $post_data['input_charset'] = 'UTF-8';
            //参数名称：页面跳转同步通知地址
            $post_data['return_url'] =  "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo;
            //签名数据连接成字符串
            $signStr = toUrlParams($post_data);
            //初始化商户私钥
            $merchant_private_key = openssl_get_privatekey($config['merchantPrivateKey']);
            //RSA-S签名
            openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);
            $post_data['sign'] = base64_encode($sign_info);
            $post_data['sign_type'] = 'RSA-S';  //不需要签名

            //type =2返回html跳转页面数
            $retData =  [
                'type' => $config['payType'][$data['pay_type']]['request_type'],
                'html' => $this->httpHtml($post_data),
                'modes' => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        }else {
        
            //非网银支付，微信、支付宝、QQ钱包支付
            //签名数据连接成字符串
            $signStr = toUrlParams($post_data);
            //初始化商户私钥
            $merchant_private_key = openssl_get_privatekey($config['merchantPrivateKey']);
            //RSA-S签名
            openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);
            $post_data['sign'] = base64_encode($sign_info);
            $post_data['sign_type'] = 'RSA-S';  //不需要签名
    
            //curl接口
            $curlData = $this->httpPostJson($this->url, $post_data);
            var_dump($curlData);
            
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 209002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '速汇宝支付接口调用失败，' . print_r($curlData, true));

                return $this->retArr;
            }
            
            if(array_key_exists('error_code', $curlData['data'])) {
                if ($curlData['data']['error_code'] == 'GET_QRCODE_FAILED') {
                    $this->retArr['code'] = 209003;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '速汇宝支付接口获取二维码失败，' . print_r($curlData['data'], true));
            
                    return $this->retArr;
                }else {
                    $this->retArr['code'] = 209004;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '速汇宝支付接口获取二维码失败，' . print_r($curlData['data'], true));
            
                    return $this->retArr;
                }
            }elseif (!isset($curlData['data']['resp_code']) || $curlData['data']['resp_code'] != 'SUCCESS') {
                $this->retArr['code'] = 209005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '速汇宝支付接口调用失败，参数异常或者验签失败' . print_r($curlData['data'], true));
                
                return $this->retArr;
            }else {
                if (!isset($curlData['data']['result_code']) || $curlData['data']['result_code'] != 0) {
                    $this->retArr['code'] = 209006;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '速汇宝支付接口调用失败，' . print_r($curlData['data'], true));
                
                    return $this->retArr;
                }
                //错调
                $curlData['data']['merchant_code'] = $config['merchantID'];
                //签名数据连接成字符串
                $retSignStr = toUrlParams($curlData['data'],['sign', 'sign_type']);
                //验签
                $dinpay_public_key = openssl_get_publickey($config['platformPublicKey']);
                $flag = openssl_verify($retSignStr,base64_decode($curlData['data']['sign']),$dinpay_public_key,OPENSSL_ALGO_MD5);
                if(!$flag){
                    $this->retArr['code'] = 209007;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '速汇宝支付返回数据成功，但验证签名失败！' . print_r($curlData['data'], true));
                
                    return $this->retArr;
                }

                payLog('payinfo.log', '华银支付接口调用成功，' . print_r($curlData['data'], true));
                $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $curlData['data']['order_no'], 'status' => 0));
                if (empty($result)) {
                    $this->retArr['code'] = 209008;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '速汇宝支付返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($curlData['data'], true));
    
                    return $this->retArr;
                }
    
                D('accountRecharge')->save(['remark' => $curlData['data']['trade_no']], ['order_sn' => $curlData['data']['order_no']]);

                //用于安全验证返回url是否非法
                session::set('qrcode_url', $curlData['data']['qrcode']);
                session::set('pay_url', '');
                //type =1 返回二维码数据
                $retData =  [
                    'type' => $config['payType'][$data['pay_type']]['request_type'],
                    'code_url' => $curlData['data']['qrcode'],
                    'pay_url' => '',
                    'order_no' => $curlData['data']['order_no'],
                    'modes' => $data['pay_model']
                ];

                $this->retArr['code'] = 0;
                $this->retArr['data']  = $retData;
                
                return $this->retArr;
            }
        }
    }

    /**
     * 支付回调方法
     * {@inheritDoc}
     * @see PayInfo::doPaycallBack()
     */
    public function doPaycallBack($postData)
    {
        //处理post回调数据
        parse_str($postData['data'], $data);
        $payment_id = $postData['payment_id'];
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 201008;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）速汇宝支付异步通知,获取数据库配置错误！'  . print_r($data, true));
            
            return $this->orderInfo;
        }
        
        if (!isset($data['notify_type']) || $data['notify_type'] != 'offline_notify') {
            $this->orderInfo['code'] = 209009;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . '速汇宝支付异步通知类型错误，' . print_r($data, true));
            
            return $this->orderInfo;
        }

        if (!isset($data['trade_status']) || $data['trade_status'] != 'SUCCESS') {
            $this->orderInfo['code'] = 201009;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . '速汇宝支付异步通知：订单支付失败，' . print_r($data, true));

            return $this->orderInfo;
        }

        //验签
        $data['merchant_code'] = $config['merchantID'];
        $retSignStr = toUrlParams($data, ['sign', 'sign_type']);
        $dinpay_public_key = openssl_get_publickey($config['platformPublicKey']);
        $flag = openssl_verify($retSignStr,base64_decode($data['sign']),$dinpay_public_key,OPENSSL_ALGO_MD5);

        if(!$flag){
            $this->orderInfo['code'] = 201010;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）速汇宝支付异步通知,验签失败！' . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $data['order_no'];
        $this->orderInfo['amount']    = $data['order_amount'];
        $this->orderInfo['serial_no'] = $data['trade_no'];
        
        return $this->orderInfo;
    }

    //post数据
    /**
     * post提交数据
     * @param string $url  提交接口url
     * @param array $postdata 提交数组数据
     * @return array[]|mixed[] 返回接口数据
     */
    function httpPostJson($url, $postdata)
    {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response=curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        //$res=simplexml_load_string($response);
        $res = (array)simplexml_load_string($response);

        return array('code' => $httpCode, 'data' => (array)$res['response']);
    }

    /**
     * 生成订单表
     * @param array $post_data  订单提交数据
     * @return string
     */
    function httpHtml($post_data)
    {
        
        $html = '<html>';
        $html = '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="' . $this->bank_url . '">';
        $html .= '<input type="hidden" name="sign"		  value="' . $post_data['sign'] . '" />';
        $html .= '<input type="hidden" name="merchant_code" value="' . $post_data['merchant_code'] . '" />';
        $html .= '<input type="hidden" name="order_no"      value="' . $post_data['order_no'] . '"/>';
        $html .= '<input type="hidden" name="order_amount"  value="' . $post_data['order_amount'] . '"/>';
        $html .= '<input type="hidden" name="service_type"  value="' . $post_data['service_type'] . '"/>';
        $html .= '<input type="hidden" name="notify_url"    value="' . $post_data['notify_url'] . '">';
        $html .= '<input type="hidden" name="interface_version" value="' . $post_data['interface_version'] . '"/>';
        $html .= '<input type="hidden" name="sign_type"     value="' . $post_data['sign_type'] . '"/>';
        $html .= '<input type="hidden" name="order_time"    value="' . $post_data['order_time'] . '"/>';
        $html .= '<input type="hidden" name="product_name"  value="' . $post_data['product_name'] . '"/>';
        $html .= '<input Type="hidden" Name="client_ip"     value="' . $post_data['client_ip'] . '"/>';
        $html .= '<input type="hidden" name="input_charset" value="' . $post_data['input_charset'] . '"/>';
        $html .= '<input Type="hidden" Name="pay_type"  value="' . $post_data['pay_type'] . '"/>';
        $html .= '<input Type="hidden" Name="return_url"    value="' . $post_data['return_url'] . '"/>';
        $html .= '<input Type="hidden" Name="extend_param"  value="' . $post_data['extend_param'] . '"/>';
        $html .= '<input Type="hidden" Name="extra_return_param" value="' . $post_data['extra_return_param'] . '"/>';
        $html .= '<input type="hidden" name="bank_code"  value=""/>';
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';
       
        return $html;
    }
    
    /**
     * 支付初始数据配置数据库 
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
         $data1['name'] = '速汇宝支付';
         $data1['scanType'] = 0;
         $data1['merchantID'] = '1111110166';
         //商户私钥
         $data1['merchantPrivateKey'] = '-----BEGIN PRIVATE KEY-----
MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBALf/+xHa1fDTCsLY
PJLHy80aWq3djuV1T34sEsjp7UpLmV9zmOVMYXsoFNKQIcEzei4QdaqnVknzmIl7
n1oXmAgHaSUF3qHjCttscDZcTWyrbXKSNr8arHv8hGJrfNB/Ea/+oSTIY7H5cAtW
g6VmoPCHvqjafW8/UP60PdqYewrtAgMBAAECgYEAofXhsyK0RKoPg9jA4NabLuuu
u/IU8ScklMQIuO8oHsiStXFUOSnVeImcYofaHmzIdDmqyU9IZgnUz9eQOcYg3Bot
UdUPcGgoqAqDVtmftqjmldP6F6urFpXBazqBrrfJVIgLyNw4PGK6/EmdQxBEtqqg
XppRv/ZVZzZPkwObEuECQQDenAam9eAuJYveHtAthkusutsVG5E3gJiXhRhoAqiS
QC9mXLTgaWV7zJyA5zYPMvh6IviX/7H+Bqp14lT9wctFAkEA05ljSYShWTCFThtJ
xJ2d8zq6xCjBgETAdhiH85O/VrdKpwITV/6psByUKp42IdqMJwOaBgnnct8iDK/T
AJLniQJABdo+RodyVGRCUB2pRXkhZjInbl+iKr5jxKAIKzveqLGtTViknL3IoD+Z
4b2yayXg6H0g4gYj7NTKCH1h1KYSrQJBALbgbcg/YbeU0NF1kibk1ns9+ebJFpvG
T9SBVRZ2TjsjBNkcWR2HEp8LxB6lSEGwActCOJ8Zdjh4kpQGbcWkMYkCQAXBTFiy
yImO+sfCccVuDSsWS+9jrc5KadHGIvhfoRjIj2VuUKzJ+mXbmXuXnOYmsAefjnMC
I6gGtaqkzl527tw=
-----END PRIVATE KEY-----';
         //商户公钥
         $data1['merchantPublicKey'] = '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC3//sR2tXw0wrC2DySx8vNGlqt
3Y7ldU9+LBLI6e1KS5lfc5jlTGF7KBTSkCHBM3ouEHWqp1ZJ85iJe59aF5gIB2kl
Bd6h4wrbbHA2XE1sq21ykja/Gqx7/IRia3zQfxGv/qEkyGOx+XALVoOlZqDwh76o
2n1vP1D+tD3amHsK7QIDAQAB
-----END PUBLIC KEY-----';
         //华银公钥
         $data1['platformPublicKey'] ='-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCivWCKDtp0ZqrPxsjY7xmEmR+c
Ud5a56D/OjHjNXO7tQn1/KFl9au1D5Q9qaLFjS0QsR7uK0S/tBIIulrG6IfqljN/
OjfIw41vYkV/cTL4TScFtORCj/WzAvBckjmOSEreGZ7UcNjYVtKSe5+t9W+0UC5t
GeEHE4mDmIKEYjONOwIDAQAB
-----END PUBLIC KEY-----';
         $data1['payType']['wx']['name'] = '微信支付';
         $data1['payType']['wx']['payStr'] = 'weixin_scan';
         $data1['payType']['wx']['request_type'] = 1;
         $data1['payType']['ali']['name'] = '支付宝支付';
         $data1['payType']['ali']['payStr'] = 'alipay_scan';
         $data1['payType']['ali']['request_type'] = 1;
         $data1['payType']['qq']['name'] = 'QQ钱包支付';
         $data1['payType']['qq']['payStr'] = 'qq_scan';
         $data1['payType']['qq']['request_type'] = 1;
         $data1['payType']['wy']['name'] = '华银网银支付';
         $data1['payType']['wy']['payStr'] = 'direct_pay';
         $data1['payType']['wy']['request_type'] = 2;
        
         $serData = serialize($data1);
         D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}