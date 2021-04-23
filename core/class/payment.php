<?php

/**
 * 支付操作控制器
 */

class Payment {
    private $db;
    public function __construct()
    {
        $this->db = getconn();
        $this->table = "un_payment_config";
    }
    /**
     * 爱益支付
     */
    public function ayPay($type,$orderSn,$money,$payment_id) {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $data = array(
            'service' => $type,
            'version' => '1.0',
            'charset' => 'UTF-8',
            'sign_type' => 'MD5',
            'mch_id' => $oRechargeArr['mch_id'],
            'out_trade_no' => $orderSn,
            'device_info' => '',
            'body' => '聊天室充值',
            'sub_openid' => '',
            'attach' => '',
            'total_fee' => $money,
            'notify_url' => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=iyNotify",
            'callback_url' => "https://".$_SERVER['HTTP_HOST']."/web/recharge-rechargeOk.html",
            'time_start' => '',
            'time_expire' => '',
            'goods_tag' => '',
            'auth_code' => '',
            'nonce_str' => time(),
        );

        //实例化爱益支付类
        $iyibank = O('iyibank', '', 1);


        //向爱益提交请求
        $result = $iyibank->payment($data,$oRechargeArr['iy_secret']);
        $res = xmlToArray($result);

        //校验签名
        if ($iyibank->verifySign($res,$oRechargeArr['iy_secret']) === 0) { //签名失败
            ErrorCode::errorResponse(7001, '签名失败');
        };

        if ($res['status'] != 0) {
            ErrorCode::errorResponse(7002, $res['message']);
        }
        if ($res['result_code'] != 0) {
            ErrorCode::errorResponse(7002, $res['err_msg']);
        }

        return $res;
    }

    /**
     * 讯汇宝支付
     */
    public function xhbPay($type,$orderSn,$money,$payment_id) {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        //实例化讯汇宝支付类
        $xunHuiBaoModel = O('xunhuibao', '', 1);
        $post_data = array(
            'merchno' => $oRechargeArr['merchantNum'],
            'amount' => $money,
            'traceno' => $orderSn,
            'payType' => $type,
            'notifyUrl'=> "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=xunHuiBaoCallBack",
            'goodsName'=>'Recharge',
            'remark'=> $payment_id
        );
        $sign = $xunHuiBaoModel->buildRequestMySign($post_data,$oRechargeArr['merchantKey']);
        $rows = $xunHuiBaoModel->buildRequest($sign,$xunHuiBaoModel->requestUrl);
        $arr = json_decode($rows, true);
        //print_r($arr);
        if($arr['respCode'] != "00")
        {
            ErrorCode::errorResponse(7002, $arr['message']);
        }
        return $arr;
    }

    /**
     * 牛付支付
     */
    public function nfPay($type,$orderSn,$money,$payment_id) {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $version = 'v1';
        $partnerId = $oRechargeArr['merchantNum'];//'0755202';//商户号，0755000072是测试商户号，调试时要更换商家自己的商户号
        $orderId = $orderSn;//	//订单号
        $goods = 'Recharge';	//商品名称.
        $amount = $money;	//支付金额
        $notifyUrl = "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=nfPayCallBack";	//支付结果异步通知地址
        $payMode = '09';//支付模式 ，扫码：09  微信公众号：10 app支付：11
        $bankId = $type;	//银行编号 WECHAT ALIPAY APPPAY
        $creditType = 3; //允许支付的卡类型
        $key = $oRechargeArr['merchantKey'];//'ced7078a6e783653ca8284bb5a013eaf';

        $signStr = '';
        $signStr = $signStr."version=".$version."&";
        $signStr = $signStr."partnerId=".$partnerId."&";
        $signStr = $signStr."orderId=".$orderId."&";
        $signStr = $signStr."goods=".$goods."&";
        $signStr = $signStr."amount=".$amount."&";
        $signStr = $signStr."expTime=&";
        $signStr = $signStr."notifyUrl=".$notifyUrl."&";
        $signStr = $signStr."pageUrl=&";
        $signStr = $signStr."reserve=&";
        $signStr = $signStr."extendInfo=&";
        $signStr = $signStr."payMode=".$payMode."&";
        $signStr = $signStr."bankId=".$bankId."&";
        $signStr = $signStr."creditType=".$creditType."&";
        $signStr = $signStr."key=".$key;
        $sign = utf8_encode(md5($signStr));

        /*
        /////////////////////////////////初始化参数//////////////////////////////////////
        */
        $postdata = "";
        $postdata = $postdata."version=".$version."&";
        $postdata = $postdata."partnerId=".$partnerId."&";
        $postdata = $postdata."orderId=".$orderId."&";
        $postdata = $postdata."goods=".$goods."&";
        $postdata = $postdata."amount=".$amount."&";
        $postdata = $postdata."notifyUrl=".$notifyUrl."&";
        $postdata = $postdata."payMode=".$payMode."&";
        $postdata = $postdata."bankId=".$bankId."&";
        $postdata = $postdata."creditType=".$creditType."&";
        $postdata = $postdata."sign=".$sign;
        //return $postdata;
        $url = 'https://pay.newpaypay.com/center/proxy/partner/v1/pay.jsp'; //网关地址

        $ch=curl_init((string)$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);//使用curl_setopt获取页面内容或提交数据，有时候希望返回的内容作为变量存储，而不是直接输出，这时候希望返回的内容作为变量
        curl_setopt($ch,CURLOPT_TIMEOUT,30);//30秒超时限制
        curl_setopt($ch,CURLOPT_HEADER,false);//将文件头输出直接可见。
        curl_setopt($ch,CURLOPT_POST,true);//设置这个选项为一个零非值，这个post是普通的application/x-www-from-urlencoded类型，多数被HTTP表调用。
        curl_setopt($ch,CURLOPT_POSTFIELDS,$postdata);//post操作的所有数据的字符串。
        $data = curl_exec($ch);//抓取URL并把他传递给浏览器
        curl_close($ch);//释放资源

        $response = iconv("gbk","utf-8",$data);
        $data = json_decode($response, true);

        if($data['code'] != 'NoCardScanCodePay00')
        {
            ErrorCode::errorResponse(7002, $data['msg']);
        }
        return $data['msg'];//$response;
    }

    /*
     * 易宝支付
     * */
    public function ybPay($orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $yeePayModel = O('yeepay', '', 1);
        $post_data = array(
            'p2_Order'=> $orderSn,
            'p3_Amt'=> $money,
            'p5_Pid'=> "Recharge",
            'p8_Url'=> "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=yeePayCallBack",
        );
        $post_data['hmac'] = $yeePayModel->getReqHmacString($post_data,$oRechargeArr['merchantID'],$oRechargeArr['merchantKey']);
        echo $yeePayModel->postHtml($post_data,$oRechargeArr['merchantID'],$oRechargeArr['merchantKey']);
    }

    /*
     * 魔宝支付
     * */
    public function mbPay($pay_type, $orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $moBaoPayModel = O('mobaopay', '', 1);
        $where = array('order_sn' => $orderSn);
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,addtime', $where);
        //组织请求参数
        $post_data = array(
            'apiName' => "WAP_PAY_B2C",// 商户APINMAE，WEB渠道一般支付
            'apiVersion'=> "1.0.0.1",// 商户API版本
            'platformID'=> $oRechargeArr['merchantID'],// 商户在支付系统的平台号
            'merchNo' => $oRechargeArr['merchantName'],// 支付系统分配给商户的账号
            'merchUrl' => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=moBaoPayCallBack",//商户通知地址
            'choosePayType' => $pay_type,// 银行代码，不传输此参数则跳转支付收银台，选择微信扫码直接跳转到微信付款界面,选择网银支付直接跳转到网银界面
            "orderNo" => $orderSn,//商户订单号
            'tradeDate' => date("Ymd",$rechargeInfo['addtime']),//商户订单日期
            'amt' => $money,//商户交易金额
            'merchParam'=>$payment_id,
            'customerIP' => $moBaoPayModel->getIp(),
            'tradeSummary' => "会员充值"
        );

        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $post_data['merchUrl']))
        {
            $post_data['merchUrl'] = iconv("GBK","UTF-8", $post_data['merchUrl']);
        }

        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $post_data['merchParam']))
        {

            $post_data['merchParam'] = iconv("GBK","UTF-8", $post_data['merchParam']);
        }

        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $post_data['tradeSummary']))
        {
            $post_data['tradeSummary'] = iconv("GBK","UTF-8", $post_data['tradeSummary']);
        }

        $str_to_sign = $moBaoPayModel->prepareSign($post_data);        // 准备待签名数据
        $sign = $moBaoPayModel->sign($str_to_sign,$oRechargeArr['merchantKey']);
        $post_data['signMsg'] = $sign;
        echo $moBaoPayModel->buildForm($post_data);
    }

    /*
     * 闪付支付
     * */
    public function sfPay($pay_type, $orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $shanFuPayModel = O('shanfupay', '', 1);
        if($pay_type == 0)
        {
            $pay_type = "1";
        }
        $where = array('order_sn' => $orderSn);
        $rechargeInfo = D('accountrecharge')->getOneCoupon('payment_id,addtime', $where);
        $post_data = array(
            'TransID'=>$orderSn,//商户订单号
            'PayID'=>$pay_type,//支付方式
            'TradeDate'=>date("YmdHis",$rechargeInfo['addtime']),//交易时间
            'OrderMoney'=>$money*100,//订单金额
            'ProductName'=>urlencode('会员充值'),//产品名称
            'AdditionalInfo'=>urlencode($payment_id),//订单附加消息
            'PageUrl'=>url('web','recharge','index'),//通知商户页面端地址
            'ReturnUrl'=>"https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=shanFuPayCallBack",//服务器底层通知地址
        );
        $post_data['Signature'] = $shanFuPayModel->buildRequestMysign($post_data,$oRechargeArr['merchantID'],$oRechargeArr['merchantKey']);
        echo $shanFuPayModel->postHtml($post_data,$oRechargeArr['merchantID'],$oRechargeArr['terminalID']);
    }

    /*
     * 乐盈支付
     * */
    public function lyPay($orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $money = $money*100;
        $post_data = [
            "version" =>  "1.0",
            "serialID" => $orderSn,
            "submitTime" => date('YmdHis'),
            "failureTime" => date('YmdHis', strtotime('+1 year')),
            "customerIP" => $_SERVER['HTTP_HOST']."[".ip()."]",
            "orderDetails" => $orderSn.",".$money.","."聊天室,聊天室充值,1",
            "totalAmount" => $money,
            "type" => "1000",
            "buyerMarked" => "",
            "payType" => "ALL",
            "orgCode" => "",
            "currencyCode" => 1,
            "directFlag" => 0,
            "borrowingMarked" => 0,
            "couponFlag" => 1,
            "platformID" => "",
            "returnUrl" => "",
            "noticeUrl" => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=leYingPayCallBack",
            "partnerID" => $oRechargeArr['merchantID'],
            "remark" => "聊天室充值",
            "charset" => 1,
            "signType" => 2
        ];

        $leYingPayModel = O('leyingpay', '', 1);
        $sign = $leYingPayModel->sign($post_data,$oRechargeArr['merchantKey']);
        echo $leYingPayModel->postHtml($post_data,$sign);
    }

    /*
     *多得宝支付
     */
    public function ddbPay($orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);

        $duoDeBaoModel = O('duodebao', '', 1);
        $post_data = array(
            'merchant_code'=>$oRechargeArr['merchantID'],
            'service_type' => $oRechargeArr['pay_type'],
            'notify_url' => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=duoDeBaoPayCallBack",
            'interface_version'=>$duoDeBaoModel->interface_version,
            'client_ip'=>'59.148.200.200',
            'sign_type'=>$duoDeBaoModel->sign_type,
            'order_no' => $orderSn,
            'order_time' => date("Y-m-d H:i:s",time()),
            'order_amount' => $money,
            'product_name'=>'会员充值'
        );
        $post_data['sign'] = $duoDeBaoModel->signMd5($post_data,$oRechargeArr['merchantKey']);
        $res = $duoDeBaoModel->postData($post_data);
        if($res['result_code'] == 1)
        {
            if($res['resp_code'] == "FAIL")
            {
                ErrorCode::errorResponse(7002, $res['resp_desc']);
            }
            else if($res['resp_code'] == "SUCCESS")
            {
                ErrorCode::errorResponse(7002, $res['result_desc']);
            }
        }
        return $res;
    }

    /*
     *快付支付
     */
    public function kfPay($pay_type, $orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $kuaifuPayModel = O('kuaifu', '', 1);
        $payData = [
            'merchantID'=>$oRechargeArr['merchantID'],
            'merchantName'=>$oRechargeArr['merchantName'],
            'merchUrl' => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=kuaiFuPayCallBack",//回调地址
            'orderNo' => $orderSn,//商户订单号
            'tradeDate' => date("Ymd",time()),//商户订单日期
            'amt' => $money,//商户交易金额
            'choosePayType' => $pay_type,//支付类型
            'merchParam' => $pay_type,//商户参数
            'tradeSummary' => "会员充值",
        ];
        echo $kuaifuPayModel->pay($payData);
    }

    /*
     * beeepay微信支付宝返回二维码支付
     */
    public function bpByWeChatAndAlipay($pay_type, $orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $beeePayModel = O('beeepay', '', 1);
        $post_data = array(
            'partner_id' => $oRechargeArr['merchantID'],//商户ID
            'partner_key' => $oRechargeArr['merchantKey'],//商户秘钥
            'out_trade_no' => $orderSn,//商户订单号
            'order_amount' => $money,//订单金额
            'order_time' => date("Y-m-d H:i:s",time()),//商家订单时间
            'return_url' => url('web','recharge','index'),//页面跳转同步通知地址
            'notify_url'=> "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=beeePayCallBack",//服务器异步通知地址
            'pay_type'=> $pay_type,//支付类型
            'summary'=> '会员充值',//
        );
        if($pay_type == "ALIPAY")
        {
            $post_data['bank_code'] = "ALIPAY_QRCODE";
        }
        else if($pay_type == "WXPAY")
        {
            $post_data['bank_code'] = "WEIXIN_QRCODE";
        }
        $res = json_decode($beeePayModel->buildRequestCurl($post_data),true);

        if($res['payment_online_response']['resp_code'] != "RESPONSE_SUCCESS")
        {
            jsonReturn(['status'=>-1,'ret_msg'=>$res['payment_online_response']['resp_desc']]);
        }
        return $res;
    }
    /*
     * beeepay跳转第三方支付
     */
    public function bPayWan($pay_type, $orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $beeePayModel = O('beeepay', '', 1);
        $post_data = array(
            'partner_id' => $oRechargeArr['merchantID'],//商户ID
            'partner_key' => $oRechargeArr['merchantKey'],//商户秘钥
            'out_trade_no' => $orderSn,//商户订单号
            'order_amount' => $money,//订单金额
            'order_time' => date("Y-m-d H:i:s",time()),//商家订单时间
            'return_url' => url('web','recharge','index'),//页面跳转同步通知地址
            'notify_url'=> "https://".$_SERVER['HTTP_HOST']."//?m=api&c=iyzf&a=beeePayCallBack",//服务器异步通知地址
            'pay_type'=> $pay_type,//支付类型
            'summary'=> '会员充值',//
            'show_cashier'=> 'YES',//YES 本地显示二维码  NO第三方平台显示二维码
        );
        if($pay_type == "ALIPAY")
        {
            $post_data['bank_code'] = "ALIPAY_QRCODE";
        }
        else if($pay_type == "WXPAY")
        {
            $post_data['bank_code'] = "WEIXIN_QRCODE";
        }
        echo $beeePayModel->buildRequestFrom($post_data);
    }


    /*
    * beeepay微信支付宝返回二维码支付App
    */
    public function bpByWeChatAndAlipayApp($pay_type, $orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $beeePayModel = O('beeepay', '', 1);
        $post_data = array(
            'partner_id' => $oRechargeArr['merchantID'],//商户ID
            'partner_key' => $oRechargeArr['merchantKey'],//商户秘钥
            'out_trade_no' => $orderSn,//商户订单号
            'order_amount' => $money,//订单金额
            'order_time' => date("Y-m-d H:i:s",time()),//商家订单时间
            'return_url' => "https://".$_SERVER['HTTP_HOST']."/beeePayOk.html",//页面跳转同步通知地址
            'notify_url'=> "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=beeePayCallBack",//服务器异步通知地址
            'pay_type'=> $pay_type,//支付类型
            'summary'=> '会员充值',//
        );
        if($pay_type == "ALIPAY")
        {
            $post_data['bank_code'] = "ALIPAY_QRCODE";
        }
        else if($pay_type == "WXPAY")
        {
            $post_data['bank_code'] = "WEIXIN_QRCODE";
        }
        $res = json_decode($beeePayModel->buildRequestCurl($post_data),true);

        if($res['payment_online_response']['resp_code'] != "RESPONSE_SUCCESS")
        {
            jsonReturn(['status'=>-1,'ret_msg'=>$res['payment_online_response']['resp_desc']]);
        }
        return $res;
    }
    /*
     * beeepay跳转第三方支付App
     */
    public function bPayWanApp($pay_type, $orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $beeePayModel = O('beeepay', '', 1);
        $post_data = array(
            'partner_id' => $oRechargeArr['merchantID'],//商户ID
            'partner_key' => $oRechargeArr['merchantKey'],//商户秘钥
            'out_trade_no' => $orderSn,//商户订单号
            'order_amount' => $money,//订单金额
            'order_time' => date("Y-m-d H:i:s",time()),//商家订单时间
            'return_url' => "https://".$_SERVER['HTTP_HOST']."/beeePayOk.html",//页面跳转同步通知地址
            'notify_url'=> "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=beeePayCallBack",//服务器异步通知地址
            'pay_type'=> $pay_type,//支付类型
            'summary'=> '会员充值',//
            'show_cashier'=> 'YES',//YES 本地显示二维码  NO第三方平台显示二维码
        );
        if($pay_type == "ALIPAY")
        {
            $post_data['bank_code'] = "ALIPAY_QRCODE";
        }
        else if($pay_type == "WXPAY")
        {
            $post_data['bank_code'] = "WEIXIN_QRCODE";
        }
        echo $beeePayModel->buildRequestFrom($post_data);
    }

    /*
     * 智付支付
     */
    public function zfPay($pay_type, $orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $zhiFuModel = O('zhifupay', '', 1);
        $post_data = array(
            'notify_url'=> "https://test.dizck.top/?m=api&c=iyzf&a=beeePayCallBack", //异步后台通知地址(必填)
            //'notify_url'=> "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=zfPayCallBack", //异步后台通知地址(必填)
            'order_amount' => $money,//定单金额（必填）
            'order_no' => $orderSn,//商家定单号(必填)
            'order_time' => date("Y-m-d H:i:s",time()),//商家定单时间(必填)
            'product_name' => "会员充值",//商品名称（必填）
            //'return_url' =>  url('web','recharge','index'),
            'return_url' =>  "https://test.dizck.top/beeePayOk.html",
            'merchant_code' => $oRechargeArr['merchantID'],
            'service_type'  => $oRechargeArr['pay_type']
        );
        $post_data['sign'] = $zhiFuModel->signMd5($post_data,$oRechargeArr['merchantKey']);
        //var_dump($post_data);
        //var_dump("text=" . $zhiFuModel->postHtml($post_data) . '"');
        echo $zhiFuModel->postHtml($post_data);
    }

    /*
     * 智付支付App
     */
    public function zfPayApp($pay_type, $orderSn, $money, $payment_id)
    {
        $sql = "select config from $this->table where id = $payment_id";
        $config = $this->db->getone($sql);
        $oRechargeArr = unserialize($config['config']);
        $zhiFuModel = O('zhifupay', '', 1);
        $post_data = array(
            'notify_url'=> "https://".$_SERVER['HTTP_HOST']."/?m=api&c=iyzf&a=zfPayCallBack", //异步后台通知地址(必填)
            //'return_url' =>  "https://".$_SERVER['HTTP_HOST']."/beeePayOk.html",//页面跳转同步通知地址
            'order_amount' => $money,//定单金额（必填）
            'order_no' => $orderSn,//商家定单号(必填)
            'order_time' => date("Y-m-d H:i:s",time()),//商家定单时间(必填)
            'product_name' => "会员充值",//商品名称（必填）
            'interface_version' => 'V3.0',
            'merchant_code' => $oRechargeArr['merchantID'],
            'service_type'  => $oRechargeArr['pay_type']
        );
        $post_data['sign'] = $zhiFuModel->signMd5($post_data,$oRechargeArr['merchantKey']);
        $post_data['sign_type'] = 'RSA-S';
        //echo $zhiFuModel->postHtml($post_data);
        echo $zhiFuModel->curlPost($post_data);
    }
}
