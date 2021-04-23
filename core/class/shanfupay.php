<?php
/**
 * Created by PhpStorm.
 * User: HCHT-KF20
 * Date: 2017/2/20
 * Time: 15:55
 */
class ShanFuPay
{
    var $interfaceVersion = "4.0";
    var $payUrl = "http://gw.3yzf.com/v4.aspx";
    var $keyType = "1";
    var $noticeType = "1";
    var $mark = "|";
    var $mark1 = '~|~';


    /**
     * 生成签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    function buildRequestMysign($post_data,$merchantID,$merchantKey)
    {
        return md5($merchantID.$this->mark.$post_data['PayID'].$this->mark.$post_data['TradeDate'].$this->mark.$post_data['TransID'].$this->mark.$post_data['OrderMoney'].$this->mark.$post_data['PageUrl'].$this->mark.$post_data['ReturnUrl'].$this->mark.$this->noticeType.$this->mark.$merchantKey);
    }

    /**
     * 验证生成签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    function verifySignature($post_data,$merchantKey)
    {
        return strtolower(md5(( "MemberID=" + $post_data['MemberID'] + "~|~"+ "TerminalID=" + $post_data['TerminalID'] + "~|~"+ "TransID=" + $post_data['TransID'] + "~|~" + "Result=" + $post_data['Result'] + "~|~" + "ResultDesc=" + $post_data['ResultDesc'] + "~|~" + "FactMoney=" + $post_data['FactMoney'] + "~|~" + "AdditionalInfo=" + $post_data['AdditionalInfo'] + "~|~" + "SuccTime=" + $post_data['SuccTime'] + "~|~" + "Md5Sign=" + $merchantKey)));
    }

    function postHtml($post_data,$merchantId,$terminalID)
    {
        $html =  "<html>";
        $html .= "<body onLoad='document.form1.submit()'>";
        $html .= "<form id='form1' name='form1' action='".$this->payUrl."' method='post'>";
        $html .= "<input type='hidden' name='MemberID' value='".$merchantId."' />";
        $html .= "<input type='hidden' name='TerminalID' value='".$terminalID."' />";
        $html .= "<input type='hidden' name='InterfaceVersion' value='".$this->interfaceVersion."' />";
        $html .= "<input type='hidden' name='KeyType' value='".$this->keyType."' />";
        $html .= "<input type='hidden' name='PayID' value='".$post_data['PayID']."' />";
        $html .= "<input type='hidden' name='TradeDate' value='".$post_data['TradeDate']."' />";
        $html .= "<input type='hidden' name='TransID' value='".$post_data['TransID']."'>";
        $html .= "<input type='hidden' name='OrderMoney' value='".$post_data['OrderMoney']."'>";
        $html .= "<input type='hidden' name='ProductName' value='".$post_data['ProductName']."'>";
        $html .= "<input type='hidden' name='PageUrl'	value='".$post_data['PageUrl']."'>";
        $html .= "<input type='hidden' name='ReturnUrl'	value='".$post_data['ReturnUrl']."'>";
        $html .= "<input type='hidden' name='Signature'	value='".$post_data['Signature']."'>";
        $html .= "<input type='hidden' name='NoticeType'	value='".$this->noticeType."'>";
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
        return $html;
    }


}