<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/10/24 15:06
 * @description 轻易付支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class QingYiFuPay extends PayInfo
{
    //请求接口Url
    public $url = [
        //'wx'  => 'http://wx.qyfpay.com:90/api/pay.action',               //第三方微信扫码接口
        'wx'  => 'http://139.199.195.194:8080/api/pay.action',
        'ali' => 'http://zfb.qyfpay.com:90/api/pay.action',             //第三方支付宝扫码接口
        'qq'  => 'http://qq.qyfpay.com:90/api/pay.action'               //第三方QQ扫码接口
    ];

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 0,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 211050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '支付',       //支付方式名称
        'serial_no' => ''            //第三方回调返回的第三方支付订单号（支付流水号）
    ];

    /**
     * 构造函数
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
            $this->retArr['code'] = 211000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 211001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'version'         => 'V3.1.0.0',                   //版本号，固定值
            'merNo'           => $config['merchantID'],         //商户ID
            'netway'          => $config['payType'][$data['pay_type']]['payStr'], 
            'random'          => (string)rand(1000,9999),                  //签名类型,RSA或MD5
            'orderNum'        => str_replace('CZ', '', $orderInfo),           //商户支付订单号,每次访问生成的唯一订单标识
            'amount'          => number_format($data['money'], 2, '.', '') * 100,  //金额（单位：分）
            'goodsName'       => 'recharge',          //商品主题
            //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
            //'callBackUrl'     => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
            //'callBackViewUrl' => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo,
            'callBackUrl'     => "https://".$_SERVER['HTTP_HOST'],
            'callBackViewUrl' => "https://".$_SERVER['HTTP_HOST'],
            'charset'         => 'UTF-8'
        );
        
        if ($data['pay_type'] == 'wy') {
            $this->retArr['code'] = 211001;
            $this->retArr['msg']  = '暂不支持网银支付';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '暂不支持网银支付，' . print_r($data, true));
    
            return $this->retArr;
        }else {
            //非网银支付，微信、支付宝、QQ钱包支付
            //签名
            $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
            
            $jsonData = $this->json_encode_ex($post_data);
            $dataStr = $this->encode_pay($jsonData, $config['platformPublicKey']);
            
            $param = 'data=' . urlencode($dataStr) . '&merchNo=' .  $config['merchantID'] . '&version=V3.1.0.0';
            
            /*
            $platform_public_key = openssl_get_publickey($config['platformPublicKey']);
            if ($platform_public_key === false) {
               $this->retArr['code'] = 202002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，平台公钥设置错误，获取失败，' . print_r($data, true));
            
                return $this->retArr;
            }
            
            var_dump($post_data);

            //RSA加密
            $payStr = $this->payEncode(json_encode($post_data), $platform_public_key);

            //返回字符串
            var_dump($payStr);
            $param = 'data=' . urlencode($payStr) . '&merchNo=' . $config['merchantID'] . '&version=V3.1.0.0';
*/
            var_dump($jsonData);
            var_dump($param);
            var_dump($this->url[$data['pay_type']]);
            
            //curl接口
            $curlData = $this->httpPost($param, $this->url[$data['pay_type']]);
            var_dump($curlData);


            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 202002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true));
            
                return $this->retArr;
            }
            
            $retData = json_decode($curlData['data'], true);
            if (!isset($retData['stateCode']) || $retData['stateCode'] != '00') {
                $this->retArr['code'] = 200003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($retData, true));
                
                return $this->retArr;
            }
            
            $retDataSgin = $retData['sgin'];
            unset($retData['sgin']);
            $retSign = $this->getSign($retData, $config['merchantKey']);
            if($retSign != $retDataSgin){
                $this->retArr['code'] = 200004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付返回数据成功，但验证签名失败！' . print_r($retData, true));
            
                return $this->retArr;
            }
            
            $retOderNo = $orderInfo;
            $retOderPayNo = isset($retData['orderNum']) ? $retData['orderNum'] : '';
            $retOderPayQrcodrUrl = isset($retData['qrcodeUrl']) ? $retData['qrcodeUrl'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty(retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 200005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '返回数据错误，' . print_r($retData, true));
                
                return $this->retArr;
            }
    
            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 200006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
                
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
        parse_str($postData['data'], $retData);
        $payment_id = $postData['payment_id'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 200020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知,获取数据库配置错误！'  . print_r($retData, true));
        
            return $this->orderInfo;
        }

        $merchant_private_key = openssl_get_privatekey($config['merchantPrivateKey']);
        $data = json_decode($this->payDecode($retData['data'], $merchant_private_key), true);

        $data['merNo']    = $config['merchantID'];
        $retDataSgin = $data['sgin'];
        unset($data['sgin']);
        $retSign = $this->getSign($data, $config['merchantKey']);

        if($retSign != $retDataSgin){
            $this->orderInfo['code'] = 200022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['merOrderNo'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['platOrderNo'];

        return $this->orderInfo;
    }
    
    /**
     * 生成签名
     * @param array $data 签名的数组
     * @param string $key 商户秘钥
     * @return string
     */
    public function getSign($data,$key){
        ksort($data);
		$sign = strtoupper(md5($this->json_encode_ex($data) . $key));
		return $sign;
    }  
    
    /**
     * json数据
     * @param array $value
     * @return string
     */
    function json_encode_ex($value){
        if (version_compare(PHP_VERSION,'5.4.0','<')){
            $str = json_encode($value);
            $str = preg_replace_callback("#\\\u([0-9a-f]{4})#i","replace_unicode_escape_sequence",$str);
            $str = stripslashes($str);
            return $str;
        }else{
            //return json_encode($value,320);
            return json_encode($value,320);
        }
    }
    
    public function encode_pay($data, $publicKey){#加密
        $pu_key = openssl_pkey_get_public($publicKey);
        if ($pu_key == false){
            echo "打开密钥出错";
            die;
        }
        $encryptData = '';
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $pu_key);
            $crypto = $crypto . $encryptData;
        }
    
        $crypto = base64_encode($crypto);
        return $crypto;
    
    }
    
    /**
     * 字符串RSA加密
     * @param unknown $data
     * @return string 
     */
    /**
     * 字符串RSA加密
     * @param string $data 需要加密的字符串
     * @param string $public_key 平台公钥
     * @return string 
     */
    public function payEncode($data, $public_key)
    {
        $encryptData = '';
        $crypto = '';

        foreach (str_split($data, 117) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $public_key);
            $crypto = $crypto . $encryptData;
        }
    
        $crypto = base64_encode($crypto);

        return $crypto;
    }
    
    /**
     * 字符串RSA解密
     * @param string $data 解密字符串
     * @param string $private_key 商家私钥
     * @return string
     */  
    public function payDecode($data, $private_key){
        $data = base64_decode($data);
        $crypto = '';
        
        foreach (str_split($data, 128) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $private_key);
            $crypto .= $decryptData;
        }

        return $crypto;
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

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return array('code' => $httpCode, 'data' => $response);
    }
    
    /**
     * 支付初始数据配置数据库
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
        $data['name'] = '轻易付支付';
        $data['merchantID'] = 'qyf201705200001';
        $data['merchantKey'] = 'CC279B16613BD32DD7BA2965CC2BC66A';
        //商户私钥
         $data['merchantPrivateKey'] = '-----BEGIN PRIVATE KEY-----
MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAI571YkVYHVb4TvTtJxcVVByWRHF
5se8xDDZZvPA1HlU0tj7bYSdzZ4iluXtj3FKQFQjU4tNgbBaXQMHJQOKRCbOQUhODth1K0FHZtrT
01eVcXfEcsd0m608vhLjx87Rr6wzJjR+gpq4DT0mAQGxf4wHEPBu5GBZ5aIbwak5ODXBAgMBAAEC
gYAbvUIiURYZYwzjj+DOvC8j3U835ZZ7dmWfuQORGw6CnJ/7/F8i/XHlgohsNSbDAJiriMEgErPX
+I+5Ii/zk3yW4xEoqkHrHRZGJTGNP2VgwnF25Nr1mDfslI71DJqdZFnl7ZUQcEP3n/IzzvxNYFQ9
yhYAmxV849QycaNgunZ1AQJBAMXye/QX0aezBdvg0zTzNxTA6SHhfUzvGpVIL+2GVTDlmYsU46d2
nKOVwOEE20QYLfYiLBZMAqwmi/t8Mt9TYNECQQC4RUDGqVSNMi7e1seTljol/qP6HGLe3tujZY/n
mHQr2VYQFLnuF7EyBl41nQ7rX/s+OM9wnDgC/21UJBuvFUHxAkAaBIMyVCckaa1tdyGLpiQpQCnk
YCT+Bbdyw6g5Ch0MbkE+PKKnkjmIbtiJOwAu9RalcVxmGduIERD5Hxv4qpbhAkEAn/GUkRtnRYt6
fXfmEWfDHzmQsUa0VwkPkhtUtkxxAaKK/jhPTqeH6Yj3ewfRbGKKXG7JN9CRGaEGD5Or5+PGsQJB
AJnaN/AJdu+G09DnjsgU1uT8cfwqjztAWaV0ctRWkylOonCgo14/iBqEkDfeDNsda8oEknl42ZX/
UJgJMtPwx/g=
-----END PRIVATE KEY-----
         ';
         //商户公钥
         $data['merchantPublicKey'] = '-----BEGIN PUBLIC KEY-----

-----END PUBLIC KEY-----';
         //轻易付公钥
         $data['platformPublicKey'] ='-----BEGIN PUBLIC KEY-----
oqyd6g9HtTBsP9OQB2U/M0KrfY9Wg7+FoYHu+pVQF/O+tMxn9jwy+J7Bd
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDBteAp6tB90/OG6C6M5
RhgKHbGajBZSumfk1std7oihCo+ZOL70GNdyysjduo5jyMy11Sc2cVrl2
xmw4oN6IPq2GvP3hSTFpOmo8wnIBcsBecQFcWI9bUOsJMXSyBYsHJrLHG
zG+UAcFn/ZAVTWaI/7RRrhpfnWPo65dXpdFmsEQIDAQAB
-----END PUBLIC KEY-----';
        $data['payType']['wx']['name'] = '微信支付';
        $data['payType']['wx']['payStr'] = 'WX';
        $data['payType']['wx']['request_type'] = 1;
        $data['payType']['ali']['name'] = '支付宝支付';
        $data['payType']['ali']['payStr'] = 'ZFB';
        $data['payType']['ali']['request_type'] = 1;
        $data['payType']['qq']['name'] = 'QQ钱包';
        $data['payType']['qq']['payStr'] = 'QQ';
        $data['payType']['qq']['request_type'] = 1;
        
        $serData = serialize($data);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}