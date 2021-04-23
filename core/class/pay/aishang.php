<?php
/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2018/128/3 16:55
 * @description 艾尚支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class AiShangPay extends PayInfo
{
    //请求接口Url
    //public $url = 'http://api.jghye.top/trade/handle';               //扫码测试调用接口
    public $url = 'http://api.jghye.top/domiam/trade/handle';               //扫码正式（线上）调用接口
    public $bank_url = 'http://api.jghye.top/domiam/trade/handle';  //第三方网银接口
    public $payName = '艾尚支付';     //支付类型名称
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 236050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '艾尚支付',    //支付方式名称
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
            $this->retArr['code'] = 200000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 200001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
            
            return $this->retArr;
        }

        $post_data = array(
            'version'     => '1.0.0',       //接口版本，默认值
            'platformNo'  => $config['partnerId'],       //机构平台编码
            'channelNo'   => $config['merchantPayKey'],  //支付渠道编码
            'merNo'       => $config['merchantID'],       //商户编码
            'tranType'    => $config['payType'][$data['pay_type']]['payStr'],         //接口版本，默认值
            'orderAmount' => (number_format($data['money'], 2, '.', '') * 100),  //金额,单位分
            'subject'     => 'recharge',   //商品主题
            'desc'        => '',
            'merOrderNo'  => $config['merchantID'],   //机构平台编码
            //'frontUrl'    => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo,
            //'backUrl'     =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'],
            'frontUrl'    => "https://".$_SERVER['HTTP_HOST'],
            'backUrl'     =>  "https://".$_SERVER['HTTP_HOST'],
            'tradeRate'   => 0,  //交易手续费率,0.3%上送0.3
            'drawFee'     =>  0   //支付手续费,以元为单位
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

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            //非网银支付，微信、支付宝、QQ钱包支付
            
            $signStr = $this->toUrlParams($post_data);  //签名数据连接成字符串
            var_dump($signStr);
           
            $merchant_private_key = openssl_get_privatekey($config['merchantPrivateKey']);   //初始化商户私钥
            
            openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);  //RSA-S签名
            $post_data['signature'] = base64_encode($sign_info);
            var_dump($post_data);
            
            //curl接口
            $curlData = $this->httpPost($post_data, $this->url);
            var_dump($curlData);

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 200002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($curlData, true));

                return $this->retArr;
            }
            
            if(array_key_exists('error_code', $curlData['data'])) {
                $this->retArr['code'] = 201004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口获取二维码失败，' . print_r($curlData['data'], true));
        
                return $this->retArr;
            }

            if (empty($curlData['data']['resp_code']) || $curlData['data']['resp_code'] != 'SUCCESS') {
                $this->retArr['code'] = 200005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($curlData['data'], true));
                
                return $this->retArr;
            }

            if (!isset($curlData['data']['result_code']) || $curlData['data']['result_code'] != 0) {
                $this->retArr['code'] = 201006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($curlData['data'], true));
            
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
                $this->retArr['code'] = 200007;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($curlData['data'], true));
            
                return $this->retArr;
            }

            payLog('payinfo.log', '支付接口调用成功，' . print_r($curlData['data'], true));

            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $curlData['data']['order_no'], 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 200008;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($curlData['data'], true));

                return $this->retArr;
            }

            D('accountRecharge')->save(['remark' => $curlData['data']['trade_no']], ['order_sn' => $curlData['data']['order_no']]);

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $curlData['data']['qrcode']);
            session::set('pay_url', '');
            //type =1 返回二维码数据
            $retData =  [
                'type'     => $config['payType'][$data['pay_type']]['request_type'],
                'code_url' => $curlData['data']['qrcode'],
                'pay_url'  => '',
                'order_no' => $curlData['data']['order_no'],
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
        $data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 200020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
            
            return $this->orderInfo;
        }
        
        if(!isset($data['respCode']) || $data['respCode'] != 10000 || !isset($data['status']) || $data['status'] != 1) {
            $this->orderInfo['code'] = 200021;
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '异步通知：订单支付失败，' . print_r($data, true));
    
            return $this->orderInfo;
        }

        //防止错调
        $data['merchant_code'] = $config['merchantID'];

        //验签
        $retSignStr = toUrlParams($data, ['signature']);
        $dinpay_public_key = openssl_get_publickey($config['platformPublicKey']);
        $flag = openssl_verify($retSignStr,base64_decode($data['sign']),$dinpay_public_key,OPENSSL_ALGO_MD5);
        if(!$flag){
            $this->orderInfo['code'] = 200022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

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
        
        $response = curl_exec($ch);
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
    
    /**
     * 格式化参数格式化成url参数
     * @param $data array 生成url需要的一维键值对数据
     * @param $unData array data中排除键名在undata中的键值对签名
     * @return string 生成url
     */
    function toUrlParams($data)
    {
        $buff = "";
    
        ksort($data);
        foreach ($data as $k => $v)
        {
            if ($v !== "") {
                $buff .= $k . "=" . $v . "&";
            }
        }
    
        $buff = trim($buff, "&");
    
        return $buff;
    }
}