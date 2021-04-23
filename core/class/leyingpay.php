<?php
/**
 * Created by PhpStorm.
 * User: HCHT-KF20
 * Date: 2017/2/10
 * Time: 15:04
 */
class leyingpay
{
    var $submitUrl = "https://www.funpay.com/website/pay.htm";


    function prepareSign($post_data)
    {
        $sign = '';
        foreach($post_data as $k=>$v){
            $sign .= $k .'='.$v.'&';
        }
        return rtrim($sign,'&');
    }

    public function sign($post_data,$merchantKey)
    {
        $sign = $this->prepareSign($post_data);
        return md5($sign."&pkey=".$merchantKey);

    }
    function postHtml($post_data,$sign)
    {
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<title>Sina28</title>";
        $html .= "</head>";
        $html .= "<body onLoad='document.leyingpay.submit();'>";
        $html .= "<form name='leyingpay' action='".$this->submitUrl."' method='post'>";
        $html .= '<input type="hidden" name="version" value="'.$post_data['version'].'">';
        $html .= '<input type="hidden" name="serialID" value="'.$post_data['serialID'].'">';
        $html .= '<input type="hidden" name="submitTime" value="'.$post_data['submitTime'].'">';
        $html .= '<input type="hidden" name="failureTime" value="'.$post_data['failureTime'].'">';
        $html .= '<input type="hidden" name="customerIP" value="'.$post_data['customerIP'].'">';
        $html .= '<input type="hidden" name="orderDetails" value="'.$post_data['orderDetails'].'">';
        $html .= '<input type="hidden" name="totalAmount" value="'.$post_data['totalAmount'].'">';
        $html .= '<input type="hidden" name="type" value="'.$post_data['type'].'">';
        $html .= '<input type="hidden" name="buyerMarked" value="'.$post_data['buyerMarked'].'">';
        $html .= '<input type="hidden" name="payType" value="'.$post_data['payType'].'">';
        $html .= '<input type="hidden" name="orgCode" value="'.$post_data['orgCode'].'">';
        $html .= '<input type="hidden" name="currencyCode" value="'.$post_data['currencyCode'].'">';
        $html .= '<input type="hidden" name="directFlag" value="'.$post_data['directFlag'].'">';
        $html .= '<input type="hidden" name="borrowingMarked" value="'.$post_data['borrowingMarked'].'">';
        $html .= ' <input type="hidden" name="couponFlag" value="'.$post_data['couponFlag'].'">';
        $html .= '<input type="hidden" name="platformID" value="'.$post_data['platformID'].'">';
        $html .= '<input type="hidden" name="returnUrl" value="'.$post_data['returnUrl'].'">';
        $html .= '<input type="hidden" name="noticeUrl" value="'.$post_data['noticeUrl'].'">';
        $html .= '<input type="hidden" name="partnerID" value="'.$post_data['partnerID'].'">';
        $html .= '<input type="hidden" name="remark" value="'.$post_data['remark'].'">';
        $html .= '<input type="hidden" name="charset" value="'.$post_data['charset'].'">';
        $html .= '<input type="hidden" name="signType" value="'.$post_data['signType'].'">';
        $html .= '<input type="hidden" name="signMsg" value="'.$sign.'">';
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
        return $html;
    }
}