<?php
/**
 * Created by PhpStorm.
 * User: HCHT-KF20
 * Date: 2017/2/10
 * Time: 18:20
 */
class duodebao
{
    var $config;
    var $service_type = "direct_pay";//业务类型
    var $interface_version = "V3.1";//接口版本
    var $sign_type = "RSA-S";//签名加密方式
    var $input_charset = "UTF-8";//网站编码
    var $url = "https://api.ddbill.com/gateway/api/scanpay";
    /*
    **
    ** 签名顺序按照参数名a到z的顺序排序，若遇到相同首字母，则看第二个字母，以此类推，同时将商家支付密钥key放在最后参与签名，
    ** 组成规则如下：
    ** 参数名1=参数值1&参数名2=参数值2&……&参数名n=参数值n&key=key值
    **/
    function signMd5($data,$merchant_key)
    {
        $signSrc= "";
        $signSrc = $signSrc."client_ip=".$data['client_ip']."&";
        $signSrc = $signSrc."interface_version=".$data['interface_version']."&";
        $signSrc = $signSrc."merchant_code=".$data['merchant_code']."&";
        $signSrc = $signSrc."notify_url=".$data['notify_url']."&";
        $signSrc = $signSrc."order_amount=".$data['order_amount']."&";
        $signSrc = $signSrc."order_no=".$data['order_no']."&";
        $signSrc = $signSrc."order_time=".$data['order_time']."&";
        $signSrc = $signSrc."product_name=".$data['product_name']."&";
        $signSrc = $signSrc."service_type=".$data['service_type'];

        $key = openssl_pkey_get_private($merchant_key);
        openssl_sign($signSrc,$sign_info,$key,OPENSSL_ALGO_MD5);
        $sign = base64_encode($sign_info);
        return $sign;
    }

    function postData($data)
    {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$this->url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response=curl_exec($ch);
        curl_close($ch);
        $xml = simplexml_load_string($response);
        return (Array)$xml->response;
    }

    function verifyNotify($data,$merchant_public_key)
    {
        //组织订单信息
        $signStr = "";
        if($data['bank_seq_no'] != "") {
            $signStr = $signStr."bank_seq_no=".$data['bank_seq_no']."&";
        }
        if($data['extra_return_param'] != "") {
            $signStr = $signStr."extra_return_param=".$data['extra_return_param']."&";
        }

        $signStr = $signStr."interface_version=".$data['interface_version']."&";
        $signStr = $signStr."merchant_code=".$data['merchant_code']."&";
        $signStr = $signStr."notify_id=".$data['notify_id']."&";
        $signStr = $signStr."notify_type=".$data['notify_type']."&";
        $signStr = $signStr."order_amount=".$data['order_amount']."&";
        $signStr = $signStr."order_no=".$data['order_no']."&";
        $signStr = $signStr."order_time=".$data['order_time']."&";
        $signStr = $signStr."trade_no=".$data['trade_no']."&";
        $signStr = $signStr."trade_status=".$data['trade_status']."&";
        $signStr = $signStr."trade_time=".$data['trade_time'];
        $key = openssl_pkey_get_public($merchant_public_key);
        $flag = openssl_verify($signStr,$data['DD4Sign'],$key,OPENSSL_ALGO_MD5);
        return $flag;
    }
}