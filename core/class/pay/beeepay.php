<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description beeepay支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class BeeePay extends PayInfo
{
    //请求接口Url
    public $url = 'https://www.xinmapay.com:7301/jhpayment';               //扫码测试调用接口
    //public $url = 'https://gateway.beeepay.com/gateway/payment';               //扫码正式（线上）调用接口
    public $bank_url = 'https://pay.huayinpay.com/gateway?input_charset=UTF-8';  //第三方网银接口
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 212050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => 'beeePay支付',       //支付方式名称
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
            $this->retArr['code'] = 212000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 212001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
            
            return $this->retArr;
        }

        $post_data = array(
            'messageid' => (string)$config['payType'][$data['pay_type']]['messageId'],
            'branch_id' => $config['merchantID'],   //合作方编号,代理商ID
            'out_trade_no' => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
            'total_fee'    => (int)(number_format($data['money'], 2, '.', '') * 100),  //金额,精确到两位小数(没有两位小数会出错）
            'pay_type' => (string)$config['payType'][$data['pay_type']]['payStr'],  //支付方式：微信，支付宝支付，QQ钱包，网银
            'prod_name'   => '会员充值',   //商品主题
            'prod_desc'   => '会员充值',   //商品描述
            'nonce_str'  => getRandomString(32),                  //用户IP,发起支付请求客户端的 ip
            'attach_content' => '',
            //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
            //'back_notify_url' =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id']
            'back_notify_url' =>  "https://".$_SERVER['HTTP_HOST']
        );

        if ($data['pay_type'] == 'wy') {
            //网银支付调用
            //参数名称：支付类型取值如下（必须小写，多选时请用逗号隔开）b2c(网银支付),weixin（微信扫码）,alipay_scan（支付宝扫码）,tenpay_scan（qq钱包扫码）
            $post_data['pay_type'] = 'b2c';
            //网银interface_version = 3.0
            $post_data['interface_version'] = 'V3.0';
            //参数名称：参数编码字符集,取值：UTF-8、GBK(必须大写)
            $post_data['input_charset'] = 'UTF-8';
            //参数名称：页面跳转同步通知地址
            
            
            $post_data['return_url'] =  "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo;
            //签名数据连接成字符串
/*
            
            $signStr = toUrlParams($post_data);
            //初始化商户私钥
            $merchant_private_key = openssl_get_privatekey($config['merchantPrivateKey']);
            //RSA-S签名
            openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);
            $post_data['sign'] = base64_encode($sign_info);
            $post_data['sign_type'] = 'RSA-S';  //不需要签名

            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'html'  => $this->httpHtml($post_data, $this->bank_url),
                'modes' => $data['pay_model']
            ];
*/
            $this->retArr['code'] = 0;
           //$this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            //非网银支付，微信、支付宝、QQ钱包支付
            //签名数据连接成字符串
            $post_data['sign'] = getSigned($post_data, ['key' => $config['merchantKey']], [], '&', 1, 1);
            var_dump($post_data);
    
            //curl接口
            $curlData = $this->httpPost($post_data, $this->url);
            var_dump($curlData);

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 200002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($curlData, true));

                return $this->retArr;
            }
            
            if (!isset($curlData['data']['resultCode']) || $curlData['data']['resultCode'] != '00') {
                $this->retArr['code'] = 212006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($curlData['data'], true));
            
                return $this->retArr;
            }
            
            if (!isset($curlData['data']['resCode']) || $curlData['data']['resCode'] != '00') {
                $this->retArr['code'] = 212006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($curlData['data'], true));
            
                return $this->retArr;
            }

            //签名
            $sgin = getSigned($curlData['data'], ['key' => $config['merchantKey']], ['sign'], '&', 1, 1);
            var_dump($sgin);
            if($curlData['data'] != $sgin){
                $this->retArr['code'] = 200007;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付返回数据成功，但验证签名失败！' . print_r($curlData['data'], true));
            
                return $this->retArr;
            }

            payLog('payinfo.log', '支付接口调用成功，' . print_r($curlData['data'], true));

            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $orderInfo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 200008;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($curlData['data'], true));

                return $this->retArr;
            }

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $curlData['data']['payUrl']);
            session::set('pay_url', '');
            //type =1 返回二维码数据
            $retData =  [
                'type'     => $config['payType'][$data['pay_type']]['request_type'],
                'code_url' => $curlData['data']['payUrl'],
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data'] = $retData;
            
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
        parse_str($postData['data'], $data);
        $payment_id = $postData['payment_id'];

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 200020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）华银支付异步通知,获取数据库配置错误！'  . print_r($data, true));
            
            return $this->orderInfo;
        }
        
        if(array_key_exists('error_code', $data) || !isset($data['trade_status']) || $data['trade_status'] != 'SUCCESS') {
            $this->orderInfo['code'] = 200021;
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '华银支付异步通知：订单支付失败，' . print_r($data, true));
    
            return $this->orderInfo;
        }

        //防止错调
        $data['merchant_code'] = $config['merchantID'];

        //验签
        $retSignStr = toUrlParams($data, ['sign', 'sign_type']);
        $dinpay_public_key = openssl_get_publickey($config['platformPublicKey']);
        $flag = openssl_verify($retSignStr,base64_decode($data['sign']),$dinpay_public_key,OPENSSL_ALGO_MD5);
        if(!$flag){
            $this->orderInfo['code'] = 200022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）华银支付异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $data['order_no'];
        $this->orderInfo['amount']    = $data['order_amount'];
        $this->orderInfo['serial_no'] = $data['bank_seq_no'];
        
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response=curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        //$res=simplexml_load_string($response);
        //$res = (array)simplexml_load_string($response);

        return array('code' => $httpCode, 'data' => $response);
    }

    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    function httpHtml($post_data, $url)
    {
        
        $html = '<html>';
        $html = '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="">';
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
         $data['name'] = 'beeepay支付';
         $data['scanType'] = 0;
         $data['merchantID'] = '10000012345688888888';
         $data['merchantKey'] = '2609DF8D75ADE1B91F66AF054C071E84';
         //商户私钥
         /*
         $data['merchantPrivateKey'] = '-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCUK0KcN6/C2SwN
/2HeP49zOlV2GuVM4q8Gky7X84/yCnwvYWI2oZFDNLycZ32IsaKxV/WXvQco9gUA
Vai28XEN+Fn4CSnh5puIIAM8wIeaUU+iEv/NOW+RcaDVrBul/O+nFuRkJcDx8U1z
UdV620l9ulS4I9CGI0neNHfirztLBWcsfGk11nkIL/NPL3f9dbHwI/q2VafaxOtn
+cewdKWyUd9ph7gesSZj4VoWF/PKdwkX98LqQTUofbKVpaX51EmR8EHxDBbJZp9V
DvT+KRoqJzCldMO8QiVzcMUvN2TKV+0/lIo8SX+EiAzgID4xogQYchQOMLcNJEu8
vrH30FYPAgMBAAECggEAEjxozFVGOpMECwz9fJ8SBrqNPiX9RsM3i3Wd9FIzyzRj
KGmx7stf14esFwvdtW83eOA8h9pqAS6WWv4v76Qzp+aDHDX0g6sgRVa7T0Ta67FK
PcZc2WNSSfJUOzgdhwZkcIouveyvnJd4UtMllSNcHi9KsgcmaPv64XBPuQScJZVq
tVijzIbDqCd6pMW7igCBqFHRuLgjJWQMvVsXi0tV8+P51NNL85177ZGLO8JTHVTK
LemErr25Dxq0p082H4ImBVSykBLxDH3BNon5LD4SzmE9vbBQrfGFsOAKQk2ltl7n
EpBYqYqwYgIm64Sq1BXRsKqJHz9TYtjoCfYm5/l2wQKBgQDFakqt9V5t5wjXFiF/
GQdPfjTHgVALX5UiopJkSKVt6zUjPmkNa28kXd686/RZEkIPAIGNe0NgUlhAax0n
++q9CQ Vb4tyCsGx4i8OrLF8jJJTMTIv8CzVNgo57EaeQnv5j05YDuAmJ0/hHxM5
4LG8ELjmTJBbeA4Iwc2onj8K9UQKBgQDAI7bukTFjdZqMYKbgdlyfOqnBcMHzJC 
+nNgQlcLtPamAn3MET+n8F7PQt6alB/ZbvuQTB9j8npZgnxXuwHYPPtZUEwOMuTK
9Sgx13tL2eA3nCY8MQPKDRhGlUxMLtPe3FjQU3DDyZDjxVJRueAZ+94l etWtIZd
eofxAYeruGFXwKBgH7b2PWEkZPKPTIKNKg56yq4DS6O+GL2nx1Mnwn2bOf/l3v4Z
QWMnjUeZT292p1KUEzXpGjIZvmEsNVkf63sAmJLY7gyRkVtHacxGSHsN46buUq3f
dUPVsdiODD5nVYf9ZUsqF/naam6HvfvjkZHN2fWVIUrUc6FLqn34KVfimKhAoGAA
jUm1+zhJWRnhy9hG3kgrU+uPaO/Br0mswQi3g9Ch7IQMsUNjt408Wt8jr59jF2Oi
2iTzmq25Qy5B9P0DNz587wBX1GcCp8k8IzDHOn9t8AQeRROXHRl4KJl12x/VNx+S
+PH6I8CbzvDo7LOc21PWY4tNbeybEp3iy/kZhvaSX8CgYAui+1Z4DjvnnvVp/O+P
hTGXPtjtJyOMkgYRWFx9eo/Rv42btia/+86SNc8kpsQtMjXT0F8RobC+Mev9fE7B
vukr0w2smgzzm5JO3J76QJD8NaJ53g1fyZEatqUueIJ/rNM0ISU3cp1yZMD7xGsV
Qp+I+uIwkLnxnAJV/A/SM2X8Q==
-----END PRIVATE KEY-----
         ';
         //商户公钥
         $data['merchantPublicKey'] = '-----BEGIN PUBLIC KEY-----
-----END PUBLIC KEY-----';
         //华银公钥
         $data['platformPublicKey'] ='-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAv4c8dMBZnCAqp4gySVT5
XFQ/IpWDNemK+V5HQpJnmmVDpha/AOuHAqciJMaVkxR30jeI+bD0iqZY6vBvuqS5
DRSyOKraP+CndansG6vgVuIukDsZ0Mo1xXLQJUHEG8v+qfdgAdtARLsc5oMqaS4+
zVbmw7b08XT1+lD8g9GDDjN/4aZRpyexMTc5i5gchrOh8zJy24mYEG87nbc+GOvD
Z31v3TJRDz2UzribHBrfDRIYhaIXrwKQ/qyC9wwIxcd0d2qUBuj4UIPpFq3OhXHj
zyvidaL7pqT3BGn9WN+VF0n0/odJhh71DnGJG1IQf5QvFAIU00USFfYitVCSqOYT
RwIDAQAB
-----END PUBLIC KEY-----';
*/
         $data['payType']['wx']['name'] = '微信支付';
         $data['payType']['wx']['payStr'] = 10;
         $data['payType']['wx']['messageId'] = 200001;
         $data['payType']['wx']['request_type'] = 1;
         $data['payType']['ali']['name'] = '支付宝支付';
         $data['payType']['ali']['payStr'] = 20;
         $data['payType']['ali']['messageId'] = 200001;
         $data['payType']['ali']['request_type'] = 1;
         $data['payType']['qq']['name'] = 'QQ钱包支付';
         $data['payType']['qq']['payStr'] = 50;
         $data['payType']['qq']['messageId'] = 200001;
         $data['payType']['qq']['request_type'] = 1;
         $data['payType']['jd']['name'] = '京东钱包支付';
         $data['payType']['jd']['payStr'] = 40;
         $data['payType']['jd']['messageId'] = 200001;
         $data['payType']['jd']['request_type'] = 1;
         $data['payType']['wy']['name'] = '网银支付';
         $data['payType']['wy']['payStr'] = 30;
         $data['payType']['wy']['messageId'] = 200002;
         $data['payType']['wy']['request_type'] = 2;
         $data['payType']['wy']['bank_id'] = '';
        
         $serData = serialize($data);
         D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}