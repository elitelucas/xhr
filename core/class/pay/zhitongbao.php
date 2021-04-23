<?php
/**
 *	Author: Kevin
 * 	CreateDate: 2017/09/20 10:05
 *  description: 智通宝支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class ZhiTongBaoPay extends PayInfo
{
    //请求接口Url
    var $scan_url               = "https://api.ztbaofu.com/gateway/api/scanpay";  //扫码支付请求地址
    var $h5_url               = "https://api.ztbaofu.com/gateway/api/h5apipay";  //扫码支付请求地址
    public $retArr = [     //dopay()请求返回格式
        'code' => 1,
        'msg' => '',
        'data' => []
    ];
    public $orderInfo = [       //doPaycallBack()异步请求处理结果返回格式
        'code' => 1,
        'bank_num' => 204050,  //回调区分号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => '',
        'ret_success' => 'SUCCESS',
        'bank_name' => '智通宝支付',
        'serial_no' => ''  //流水号
    ];

    public function __construct()
    {
        parent::__construct();
    }

    //支付请求处理
    public function doPay($data)
    {
        //生成订单号
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 204000;
            $this->retArr['msg']  = '支付订单号生成失败';
            payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付订单号生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 204001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'merchant_code' => $config['merchantID'],   //合作方编号,代理商ID
            'interface_version'  => 'V3.1',       //接口版本，固定值：V3.1(必须大写)
            'order_no'   => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
            'order_time' => date('Y-m-d H:i:s'),  //商户订单时间
            'order_amount'    => number_format($data['money'], 2, '.', ''),  //金额,精确到两位小数(没有两位小数会出错）
            'service_type' => $config['payType'][$data['pay_type']]['payStr'],  //支付方式：微信，支付宝支付，QQ钱包，网银
            'product_name'   => 'recharge',   //商品主题
            'extra_return_param'=> "recharge",
            'client_ip'  => ip(),                  //用户IP,发起支付请求客户端的 ip
            //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
            'notify_url' =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id']
        );

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {
            $url = $this->scan_url;

        } else {
            $url = $this->h5_url;
        }

        $signStr = toUrlParams($post_data);
        //初始化商户私钥
        $merchant_private_key = openssl_get_privatekey($config['merchantPrivateKey']);
        //RSA-S签名
        openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);

        $post_data['sign'] = base64_encode($sign_info);
        $post_data['sign_type'] = 'RSA-S';  //不需要签名

        $curlData = $this->httpPostJson($data,$url);
        //接口调用成功与否
        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 204002;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付接口调用失败，' . print_r($curlData, true));

            return $this->retArr;
        }
        if (empty($curlData['data']['resp_code']) || $curlData['data']['resp_code'] != 'SUCCESS') {
            $this->retArr['code'] = 204005;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付接口调用失败，' . print_r($curlData['data'], true));

            return $this->retArr;
        }
        if ($curlData['data']['result_code'] != 0) {
            $this->retArr['code'] = 204003;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付接口获取二维码失败，' . print_r($curlData['data'], true));

            return $this->retArr;
        }

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $curlData['data']['qrcode']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $curlData['data']['qrcode'],
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {

            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml(array(),urldecode($curlData['data']['payURL']))
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;


        }

        die;


        //网银支付调用
        if ($data['pay_type'] == 'wy') {
            //参数名称：支付类型取值
            $post_data['pay_type'] = 'b2c';
            //网银interface_version = 3.0
            $post_data['interface_version'] = 'V3.0';
            //参数名称：参数编码字符集
            $post_data['input_charset'] = 'UTF-8';
            //参数名称：页面跳转同步通知地址
            $post_data['return_url'] =  "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo;
            //签名数据连接成字符串
            $signStr = toUrlParams($post_data);
            //初始化商户私钥
            $merchant_private_key = openssl_get_privatekey($config['merchantPrivateKey']);
            //RSA-S签名
            openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);

            $post_data['sign'] = base64_encode($sign_info);
            $post_data['sign_type'] = 'RSA-S';  //不需要签名

            //type =2返回html跳转页面数
            $retData =  ['type' => $config['payType'][$data['pay_type']]['request_type'], 'html' => $this->httpZtbHtml($post_data), 'modes' => $data['pay_model']];

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
            payLog('zhb.txt',print_r($curlData,true). " ---113");
            //var_dump($curlData);

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 204002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付接口调用失败，' . print_r($curlData, true));

                return $this->retArr;
            }
            if (empty($curlData['data']['resp_code']) || $curlData['data']['resp_code'] != 'SUCCESS') {
                $this->retArr['code'] = 204005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付接口调用失败，' . print_r($curlData['data'], true));

                return $this->retArr;
            }
            if ($curlData['data']['result_code'] != 0) {
                $this->retArr['code'] = 204003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付接口获取二维码失败，' . print_r($curlData['data'], true));

                return $this->retArr;
            }

            //签名数据连接成字符串
            $retSignStr = toUrlParams($curlData['data'],['sign', 'sign_type']);
            //验签
            $dinpay_public_key = openssl_get_publickey($config['platformPublicKey']);
            $flag = openssl_verify($retSignStr,base64_decode($curlData['data']['sign']),$dinpay_public_key,OPENSSL_ALGO_MD5);
            if(!$flag){
                $this->retArr['code'] = 204006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付返回数据成功，但验证签名失败！' . print_r($curlData['data'], true));

                return $this->retArr;
            }

            payLog('payinfo.log', '智通宝支付接口调用成功，' . print_r($curlData['data'], true));

            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $curlData['data']['order_no'], 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 204007;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('zhb.txt', '（' . $this->retArr['code'] . '）' . '智通宝支付返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($curlData['data'], true));

                return $this->retArr;
            }

            D('accountRecharge')->save(['remark' => $curlData['data']['trade_no']], ['order_sn' => $curlData['data']['order_no']]);
            //备份相应数据
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

    //支付回调方法
    public function doPaycallBack($postData)
    {
        //处理post回调数据
        parse_str($postData['data'], $data);
        $payment_id = $postData['payment_id'];
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 204008;
            payLog('zhb.txt', '（' . $this->orderInfo['code'] . '）智通宝支付异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if (!isset($data['trade_status']) || $data['trade_status'] != 'SUCCESS') {
            $this->orderInfo['code'] = 204009;
            payLog('zhb.txt', '（' . $this->orderInfo['code'] . '）智通宝支付异步通知：返回信息不是充值成功信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错调
        $data['merchant_code'] = $config['merchantID'];

        //验签
        $retSignStr = toUrlParams($data,['sign', 'sign_type']);
        $dinpay_public_key = openssl_get_publickey($config['platformPublicKey']);
        $flag = openssl_verify($retSignStr,base64_decode($data['sign']),$dinpay_public_key,OPENSSL_ALGO_MD5);
        if(!$flag){
            $this->orderInfo['code'] = 204010;
            payLog('zhb.txt', '（' . $this->orderInfo['code'] . '）智通宝支付异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }



        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $data['order_no'];
        $this->orderInfo['amount']    = $data['order_amount'];
        $this->orderInfo['serial_no'] = $data['bank_seq_no'];

        return $this->orderInfo;
    }

    //post数据
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

    function httpHtml($post_data)
    {

        $html = '<html>';
        $html .= '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="https://pay.ztbaopay.com/gateway?input_charset=UTF-8">';
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


    //智通宝专用域名页面跳转
    function httpZtbHtml($post_data)
    {

        $html = '<html>';
        $html .= '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="https://' . $_SERVER['HTTP_HOST'] . '/?m=web&c=recharge&a=rechargeJump">';
        $html .= '<input type="hidden" name="action_url"		  value="https://pay.ztbaopay.com/gateway?input_charset=UTF-8" />';
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
}