<?php
/**
 * Created by PhpStorm.
 * User: HCHT-KF20
 * Date: 2017/2/10
 * Time: 18:20
 */
class beeepay
{
    var $partner_id;
    var $partner_key;

    //PTY_ONLINE_PAY       为单笔订单支付接口；
    //PTY_TRADE_QUERY      为单笔订单查询接口；
    //PTY_PAYMENT_TO_CARD  为提现到银行卡接口；
    //PTY_PAYMENT_QUERY    为提现订单查询接口；
    //PTY_ACCOUNT_BALANCE  为账户余额查询接口；
    var $service_name = "PTY_ONLINE_PAY";
    var $input_charset = "UTF-8";
    var $version = "V3.5.0";
    var $sign_type = "MD5";
    var $server_url = "https://pay.beeepay.com/payment/gateway";


    //支付宝或微信支付
    function buildRequestCurl($postData)
    {
        $data['partner_id'] = $postData['partner_id'];
        $data['service_name'] = $this->service_name;
        $data['input_charset'] = $this->input_charset;
        $data['version'] = $this->version;
        $data['out_trade_no'] = $postData['out_trade_no'];
        $data['order_amount'] = $postData['order_amount'];
        $data['order_time'] = $postData['order_time'];
        $data['return_url'] = $postData['return_url'];
        $data['notify_url'] = $postData['notify_url'];
        $data['pay_type'] = $postData['pay_type'];
        $data['bank_code'] = $postData['bank_code'];
        $data['summary'] = $postData['summary'];

        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $data['summary']))
        {
            $post_data['summary'] = iconv("GB2312","UTF-8", $data['summary']);
        }
        //数据签名
        $sign_str = $this->buildRequestMySign($data,$postData['partner_key']);
        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $data['extend_param']))
        {
            $data['extend_param'] = iconv("GB2312","UTF-8", $data['extend_param']);
        }
        $data['extend_param'] = $postData['extend_param'];
        //发起请求
        $data['sign'] = $sign_str;
        $data['sign_type'] = $this->sign_type;
        return $this->buildRequests($data);
    }

    //网银支付
    function buildRequestFrom($postData)
    {
        $data['partner_id'] = $postData['partner_id'];
        $data['service_name'] = $this->service_name;
        $data['input_charset'] = $this->input_charset;
        $data['version'] = $this->version;
        $data['out_trade_no'] = $postData['out_trade_no'];
        $data['order_amount'] = $postData['order_amount'];
        $data['order_time'] = $postData['order_time'];
        $data['return_url'] = $postData['return_url'];
        $data['notify_url'] = $postData['notify_url'];
        $data['pay_type'] = $postData['pay_type'];
        $data['summary'] = $postData['summary'];
        $data['bank_code'] = $postData['bank_code'];
	
        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $data['summary']))
        {
            $post_data['summary'] = iconv("GB2312","UTF-8", $data['summary']);
        }

        //数据签名
        $sign_str = $this->buildRequestMySign($data,$postData['partner_key']);
        $data['extend_param'] = $postData['extend_param'];
        $data['show_cashier'] = $postData['show_cashier'];
        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $data['extend_param']))
        {
            $data['extend_param'] = iconv("GB2312","UTF-8", $data['extend_param']);
        }
        //发起请求
        $data['sign'] = $sign_str;
        $data['sign_type'] = $this->sign_type;
        return $this->buildRequest($data);
    }

    /*
     * 生成请求签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    function buildRequestMySign($post_data,$partner_key)
    {
        ksort($post_data);
        $a = '';
        foreach($post_data as $key=>$val)
        {
            if(!empty($val))
            {
                $a=$a.$key."=".$val."&";
            }
        }
        $c = $a.'key'.'='.$partner_key;
        return strtoupper(md5($c));
    }

    /*
    * 建立请求
    * @param $para_temp 请求参数数组
    */
    function buildRequest($data)
    {
        $sHtml = "<form id='beeepay' name='beeepay' action='".$this->server_url."' method='post'>";
        while (list ($key, $val) = each ($data)) {
            $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }
        $sHtml.= "<input style='display: none;' type='submit' value='立即支付'/>";
        $sHtml.= "</form>";
        $sHtml.= "<script>window.onload = function(){document.getElementById('beeepay').submit();}</script>";
        return $sHtml;
    }

    /*
 * 建立请求
 * @param $para_temp 请求参数数组
 */
    function buildRequests($data)
    {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$this->server_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response=curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    function verifyNotify($post_data,$sign,$merchantKey)
    {
        $sign_str = $this->buildRequestMySign($post_data,$merchantKey);
        if($sign_str == $sign)
        {
            return true;
        }
        else
        {
            return false;
        }

    }
}