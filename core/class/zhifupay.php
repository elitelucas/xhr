<?php
/**
 * Created by PhpStorm.
 * User: HCHT-KF20
 * Date: 2017/2/10
 * Time: 18:20
 */
class zhifupay
{
    var $config;
    var $service_type = "direct_pay";//业务类型
    var $interface_version = "V3.0";//接口版本
    var $sign_type = "RSA-S";//签名加密方式
    var $input_charset = "UTF-8";//网站编码
    var $merchant_code;//商家号
    var $merchant_key;//商家私钥
    var $merchant_public_key;//智付公钥
    
    function postHtml($data)
    {
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<meta http-equiv='Content-Type' content='text/html'; charset='utf-8'>";
        $html .= "</head>";
        $html .= "<body onLoad='document.dinpayForm.submit();'>";
        $html .= "<form name='dinpayForm' action='https://pay.dinpay.com/gateway?input_charset=".$this->input_charset."' method='post'>";
        $html .= "<input type='hidden' name='sign' value='".$data['sign']."' />";
        $html .= "<input type='hidden' name='merchant_code' value='".$data['merchant_code']."' />";
        $html .= "<input type='hidden' name='order_no' value='".$data['order_no']."' />";
        $html .= "<input type='hidden' name='order_amount' value='".$data['order_amount']."' />";
        $html .= "<input type='hidden' name='service_type' value='".$this->service_type."' />";
        $html .= "<input type='hidden' name='interface_version' value='".$this->interface_version."' />";
        $html .= "<input type='hidden' name='sign_type'	value='".$this->sign_type."'>";
        $html .= "<input type='hidden' name='order_time' value='".$data['order_time']."'>";
        $html .= "<input type='hidden' name='pay_type' value='".$data['pay_type']."'>";
        $html .= "<input type='hidden' name='product_name' value='".$data['product_name']."'>";
        $html .= "<input type='hidden' name='notify_url' value='".$data['notify_url']."'>";
        $html .= "<input type='hidden' name='return_url' value='".$data['return_url']."'>";
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
        return $html;
    }

    /*
    **
    ** 签名顺序按照参数名a到z的顺序排序，若遇到相同首字母，则看第二个字母，以此类推，同时将商家支付密钥key放在最后参与签名，
    ** 组成规则如下：
    ** 参数名1=参数值1&参数名2=参数值2&……&参数名n=参数值n&key=key值
    **/
    function signMd5($data,$merchant_key)
    {
        $signSrc= "";
        $signSrc = $signSrc."input_charset=".$this->input_charset."&";
        $signSrc = $signSrc."interface_version=".$this->interface_version."&";
        $signSrc = $signSrc."merchant_code=".$data['merchant_code']."&";
        $signSrc = $signSrc."notify_url=".$data['notify_url']."&";
        $signSrc = $signSrc."order_amount=".$data['order_amount']."&";
        $signSrc = $signSrc."order_no=".$data['order_no']."&";
        $signSrc = $signSrc."order_time=".$data['order_time']."&";
        if($data['pay_type'] != "")
        {
            $signSrc = $signSrc."pay_type=".$data['pay_type']."&";
        }
        $signSrc = $signSrc."product_name=".$data['product_name']."&";
        $signSrc = $signSrc."return_url=".$data['return_url']."&";
        $signSrc = $signSrc."service_type=".$this->service_type;
        //service不同固定值：alipay_scan 或 weixin_scan或 zhb_scan或 qq_scan
        //$signSrc = $signSrc."service_type=".$this->service_type;
        $signSrc = $signSrc."service_type=".$data['service_type'];

        $key = openssl_pkey_get_private($merchant_key);
        openssl_sign($signSrc,$sign_info,$key,OPENSSL_ALGO_MD5);
        $sign = base64_encode($sign_info);
        return $sign;
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
        $flag = openssl_verify($signStr,$data['dinpaySign'],$key,OPENSSL_ALGO_MD5);
        return $flag;
    }
    
    public function curlPost($postData) {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,"https://api.dinpay.com/gateway/api/scanpay");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response=curl_exec($ch);
        
        //$res=simplexml_load_string($response);
        curl_close($ch);
        
        echo $response;
    }
}