<?php
/**
 * Created by PhpStorm.
 * User: HCHT-KF20
 * Date: 2017/2/10
 * Time: 15:04
 */
class yeepay
{
    /*
 * @Description 易宝支付产品通用接口范例
 * @V3.0
 * @Author rui.xin
 */

    //产品通用接口正式请求地址
    var $reqURL_onLine = "https://www.yeepay.com/app-merchant-proxy/node";
    //产品通用接口测试请求地址
    //var $reqURL_onLine = "http://tech.yeepay.com:8080/robot/debug.action";

    //业务类型
    //支付请求，固定值"Buy" .
    var $p0_Cmd = "Buy";
    var $p4_Cur = "CNY";
    var $pr_NeedResponse = "1";

    //送货地址
    //为"1": 需要用户将送货地址留在易宝支付系统;为"0": 不需要，默认为 "0".
    var $p9_SAF = "0";
    var $logName	= "./Runtime/PayLog/payLog.log";



    #签名函数生成签名串
    function getReqHmacString($data,$merchantID,$merchantKey)
    {
        global $p0_Cmd;
        global $p9_SAF;

        #进行签名处理，一定按照文档中标明的签名顺序进行
        $sbOld = "";
        #加入业务类型
        $sbOld = $sbOld.$this->p0_Cmd;
        #加入商户编号
        $sbOld = $sbOld.$merchantID;
        #加入商户订单号
        $sbOld = $sbOld.$data['p2_Order'];
        #加入支付金额
        $sbOld = $sbOld.$data['p3_Amt'];
        #加入交易币种
        $sbOld = $sbOld.$this->p4_Cur;
        #加入商品名称
        $sbOld = $sbOld.$data['p5_Pid'];
        #加入商品分类
        $sbOld = $sbOld.$data['p6_Pcat'];
        #加入商品描述
        $sbOld = $sbOld.$data['p7_Pdesc'];
        #加入商户接收支付成功数据的地址
        $sbOld = $sbOld.$data['p8_Url'];
        #加入送货地址标识
        $sbOld = $sbOld.$this->p9_SAF;
        #加入商户扩展信息
        $sbOld = $sbOld.$data['pa_MP'];
        #加入支付通道编码
        $sbOld = $sbOld.$data['pd_FrpId'];
        #加入是否需要应答机制
        $sbOld = $sbOld.$this->pr_NeedResponse;

        return $this->HmacMd5($sbOld,$merchantKey);

    }

    function getCallbackHmacString($r0_Cmd,$r1_Code,$r2_TrxId,$r3_Amt,$r4_Cur,$r5_Pid,$r6_Order,$r7_Uid,$r8_MP,$r9_BType,$merchantID,$merchantKey)
    {
        #取得加密前的字符串
        $sbOld = "";
        #加入商家ID
        $sbOld = $sbOld.$merchantID;
        #加入消息类型
        $sbOld = $sbOld.$r0_Cmd;
        #加入业务返回码
        $sbOld = $sbOld.$r1_Code;
        #加入交易ID
        $sbOld = $sbOld.$r2_TrxId;
        #加入交易金额
        $sbOld = $sbOld.$r3_Amt;
        #加入货币单位
        $sbOld = $sbOld.$r4_Cur;
        #加入产品Id
        $sbOld = $sbOld.$r5_Pid;
        #加入订单ID
        $sbOld = $sbOld.$r6_Order;
        #加入用户ID
        $sbOld = $sbOld.$r7_Uid;
        #加入商家扩展信息
        $sbOld = $sbOld.$r8_MP;
        #加入交易结果返回类型
        $sbOld = $sbOld.$r9_BType;

        return $this->HmacMd5($sbOld,$merchantKey);

    }

    function CheckHmac($data,$merchantID,$merchantKey)
    {
        if($data['hmac'] == $this->getCallbackHmacString($data['r0_Cmd'],$data['r1_Code'],$data['r2_TrxId'],$data['r3_Amt'],$data['r4_Cur'],$data['r5_Pid'],$data['r6_Order'],$data['r7_Uid'],$data['r8_MP'],$data['r9_BType'],$merchantID,$merchantKey))
            return true;
        else
            return false;
    }


    function HmacMd5($data,$key)
    {
        // RFC 2104 HMAC implementation for php.
        // Creates an md5 HMAC.
        // Eliminates the need to install mhash to compute a HMAC
        // Hacked by Lance Rushing(NOTE: Hacked means written)

        //需要配置环境支持iconv，否则中文参数不能正常处理
        $key = iconv("GB2312","UTF-8",$key);
        $data = iconv("GB2312","UTF-8",$data);

        $b = 64; // byte length for md5
        if (strlen($key) > $b) {
            $key = pack("H*",md5($key));
        }
        $key = str_pad($key, $b, chr(0x00));
        $ipad = str_pad('', $b, chr(0x36));
        $opad = str_pad('', $b, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;

        return md5($k_opad . pack("H*",md5($k_ipad . $data)));
    }

    function logstr($orderid,$str,$hmac)
    {
        $james=fopen($this->logName,"a+");
        fwrite($james,"\r\n".date("Y-m-d H:i:s")."|orderid[".$orderid."]|str[".$str."]|hmac[".$hmac."]\r\n");
        fclose($james);
    }

    function postHtml($post_data,$merchantID)
    {
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<title>Sina28</title>";
        $html .= "</head>";
        $html .= "<body onLoad='document.yeepay.submit();'>";
        $html .= "<form name='yeepay' action='".$this->reqURL_onLine."' method='post'>";
        $html .= "<input type='hidden' name='p0_Cmd' value='".$this->p0_Cmd."' />";
        $html .= "<input type='hidden' name='p1_MerId' value='".$merchantID."' />";
        $html .= "<input type='hidden' name='p2_Order' value='".$post_data['p2_Order']."' />";
        $html .= "<input type='hidden' name='p3_Amt' value='".$post_data['p3_Amt']."' />";
        $html .= "<input type='hidden' name='p4_Cur' value='".$this->p4_Cur."' />";
        $html .= "<input type='hidden' name='p5_Pid' value='".$post_data['p5_Pid']."' />";
//        $html .= "<input type='hidden' name='p6_Pcat'	value='".$p6_Pcat."'>";
//        $html .= "<input type='hidden' name='p7_Pdesc' value='".$p7_Pdesc."'>";
        $html .= "<input type='hidden' name='p8_Url' value='".$post_data['p8_Url']."'>";
        $html .= "<input type='hidden' name='p9_SAF' value='".$this->p9_SAF."'>";
//        $html .= "<input type='hidden' name='pa_MP' value='".$pa_MP."'>";
        $html .= "<input type='hidden' name='pd_FrpId' value='".$post_data['pd_FrpId']."'>";
        $html .= "<input type='hidden' name='pr_NeedResponse' value='".$this->pr_NeedResponse."'>";
        $html .= "<input type='hidden' name='hmac'	value='".$post_data['hmac']."'>";
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
        return $html;
    }
}