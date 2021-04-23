<?php
/**
 * Created by PhpStorm.
 * User: HCHT-KF20
 * Date: 2017/2/18
 * Time: 17:43
 */
class mobaopay
{
    var $merchantKey;
    var $serverUrl = "https://9pay.9payonline.com/cgi-bin/netpayment/pay_gate.cgi";

    function __construct()
    {
        $this->merchantKey = "6ce752defc7b3dea4dc7c4862630783c";
    }


    /**
     * @name	准备签名/验签字符串
     * @desc prepare urlencode data
     * @mobaopay_tran_query
     * #apiName,apiVersion,platformID,merchNo,orderNo,tradeDate,amt
     * #@mobaopay_tran_return
     * #apiName,apiVersion,platformID,merchNo,orderNo,tradeDate,amt,tradeSummary
     * #@web_pay_b2c,wap_pay_b2c
     * #apiName,apiVersion,platformID,merchNo,orderNo,tradeDate,amt,merchUrl,merchParam,tradeSummary
     * #@pay_result_notify
     * #apiName,notifyTime,tradeAmt,merchNo,merchParam,orderNo,tradeDate,accNo,accDate,orderStatus
    choosePayType
     */
    public function prepareSign($data) {
        if($data['apiName'] == 'MOBO_TRAN_QUERY') {
            $result = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&orderNo=%s&tradeDate=%s&amt=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['orderNo'], $data['tradeDate'], $data['amt']
            );
            return $result;
        } else if ($data['apiName'] == 'AUTO_SETT_QUERY') {
            $result = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&startDate=%s&endDate=%s&startIndex=%s&endIndex=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['startDate'], $data['endDate'], $data['startIndex'],$data['endIndex']
            );
            return $result;
        } else if ((($data['apiName'] == 'WEB_PAY_B2C') ||($data['apiName'] == 'WAP_PAY_B2C'))&& ($data['apiVersion'] == '1.0.0.0')) {
            $result = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&orderNo=%s&tradeDate=%s&amt=%s&merchUrl=%s&merchParam=%s&tradeSummary=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['orderNo'], $data['tradeDate'], $data['amt'], $data['merchUrl'], $data['merchParam'], $data['tradeSummary']
            );
            return $result;
        } else if ($data['apiName'] == 'MOBO_USER_WEB_PAY') {
            $result = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&userNo=%s&accNo=%s&orderNo=%s&tradeDate=%s&amt=%s&merchUrl=%s&merchParam=%s&tradeSummary=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['userNo'], $data['accNo'], $data['orderNo'], $data['tradeDate'], $data['amt'], $data['merchUrl'], $data['merchParam'], $data['tradeSummary']
            );
            return $result;
        } else if ($data['apiName'] == 'MOBO_TRAN_RETURN') {
            $result = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&orderNo=%s&tradeDate=%s&amt=%s&tradeSummary=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['orderNo'], $data['tradeDate'], $data['amt'], $data['tradeSummary']
            );
            return $result;
        } else if ($data['apiName'] == 'PAY_RESULT_NOTIFY') {
            $result = sprintf(
                "apiName=%s&notifyTime=%s&tradeAmt=%s&merchNo=%s&merchParam=%s&orderNo=%s&tradeDate=%s&accNo=%s&accDate=%s&orderStatus=%s",
                $data['apiName'], $data['notifyTime'], $data['tradeAmt'], $data['merchNo'], $data['merchParam'], $data['orderNo'], $data['tradeDate'], $data['accNo'], $data['accDate'], $data['orderStatus']
            );
            return $result;
        }else if ((($data['apiName'] == 'WEB_PAY_B2C') ||($data['apiName'] == 'WAP_PAY_B2C')) && ($data['apiVersion'] == '1.0.0.1')) {
            $result = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&orderNo=%s&tradeDate=%s&amt=%s&merchUrl=%s&merchParam=%s&tradeSummary=%s&customerIP=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['orderNo'], $data['tradeDate'], $data['amt'], $data['merchUrl'], $data['merchParam'], $data['tradeSummary'],$data['customerIP']
            );
            return $result;
        }else if ($data['apiName'] == 'SINGLE_ENTRUST_SETT') {
            $result = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&orderNo=%s&tradeDate=%s&merchUrl=%s&merchParam=%s&bankAccNo=%s&bankAccName=%s&bankCode=%s&bankName=%s&Amt=%s&tradeSummary=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['orderNo'], $data['tradeDate'], $data['merchUrl'], $data['merchParam'], $data['bankAccNo'], $data['bankAccName'],$data['bankCode'], $data['bankName'],$data['Amt'], $data['tradeSummary']
            );
            return $result;
        }

        $array = array();
        foreach ($data as $key=>$value) {
            array_push($array, $key.'='.$value);
        }
        return implode($array, '&');
    }

    /**
     * @name	生成签名
     * @param	sourceData
     * @return	签名数据
     */
    public function sign($data,$merchantKey) {
        $signature = MD5($data.$merchantKey);
        return $signature;
    }

    /**
     * 创建表单
     * @data		表单内容
     * @gateway 支付网关地址
     */
    function buildForm($data) {
        $sHtml = "<form id='mobaopaysubmit' name='mobaopaysubmit' action='".$this->serverUrl."' method='post'>";
        while (list ($key, $val) = each ($data)) {
            $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }
        $sHtml.= "</form>";
        $sHtml.= "<script>document.forms['mobaopaysubmit'].submit();</script>";

        return $sHtml;
    }

    /*$getIp=$_SERVER["REMOTE_ADDR"];
     * @name	获取客服端ip
     * @desc
     */
    public function getIp(){

        if(!empty($_SERVER['HTTP_CLIENT_IP'])){
            return $_SERVER['HTTP_CLIENT_IP'];
        }elseif(!empty($_SERVER['HTTP_X_FORVARDED_FOR'])){
            return $_SERVER['HTTP_X_FORVARDED_FOR'];
        }elseif(!empty($_SERVER['REMOTE_ADDR'])){
            return $_SERVER['REMOTE_ADDR'];
        }else{
            return "未知IP";
        }

    }

    /*
     * @name	验证签名
     * @param	signData 签名数据
     * @param	sourceData 原数据
     * @return
     */
    public function verify($data, $signature,$merchantKey) {
        $mySign = $this->sign($data,$merchantKey);
        if (strcasecmp($mySign, $signature) == 0) {
            return true;
        } else {
            return false;
        }
    }


}