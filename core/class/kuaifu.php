<?php

class kuaifu {

    private $hkkKey;                    //支付密匙
    private $serverUrl = "http://pay888.hkkpay.com/cgi-bin/netpayment/pay_gate.cgi";                 //支付地址
    private $appid;                     //平台号
    private $merchNo;                   //商户号
    private $apiName = 'WEB_PAY_B2C';   // 商户APINMAE，WEB渠道一般支付
    private $apiVersion = "1.0.0.0";    // 商户API版本


    //支付操作
    function pay($payData)
    {
        // 请求数据赋值
        $data = "";
        //支付方式
        $data['choosePayType'] = $payData['choosePayType'];
        $data['apiName'] = $this->apiName;
        $data['apiVersion'] = $this->apiVersion;
        $data['platformID'] = $payData['merchantID'];
        $data['merchNo'] = $payData['merchantName'];
        $data['merchUrl'] = $payData['merchUrl'];
        $data['orderNo'] = $payData["orderNo"];
        $data['tradeDate'] = $payData['tradeDate'];
        $data['amt'] = $payData["amt"];
        $data['merchParam'] = $payData["merchParam"];
        $data['tradeSummary'] = $payData["tradeSummary"];

        // 对含有中文的参数进行UTF-8编码，将中文转换为UTF-8
        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $data['merchUrl']))
        {
            $data['merchUrl'] = iconv("GBK","UTF-8", $data['merchUrl']);
        }
        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $data['merchParam']))
        {

            $data['merchParam'] = iconv("GBK","UTF-8", $data['merchParam']);
        }

        if(!preg_match("/[\xe0-\xef][\x80-\xbf]{2}/", $data['tradeSummary']))
        {
            $data['tradeSummary'] = iconv("GBK","UTF-8", $data['tradeSummary']);
        }

        // 准备待签名数据
        $str_to_sign = $this->prepareSign($data);
        // 数据签名
        $sign = $this->sign($str_to_sign);
        $data['signMsg'] = $sign;
        // 生成表单数据
        $buildForm = $this->buildForm($data, $this->serverUrl);
        return $buildForm;
    }

    /**
     * 回调
     */
    public function callback($inputs)
    {
        // 请求数据赋值
        $data = "";
        $data['apiName'] = $inputs["apiName"];
        // 通知时间
        $data['notifyTime'] = $inputs["notifyTime"];
        // 支付金额(单位元，显示用)
        $data['tradeAmt'] = $inputs["tradeAmt"];
        // 商户号
        $data['merchNo'] = $inputs["merchNo"];
        // 商户参数，支付平台返回商户上传的参数，可以为空
        $data['merchParam'] = $inputs["merchParam"];
        // 商户订单号
        $data['orderNo'] = $inputs["orderNo"];
        // 商户订单日期
        $data['tradeDate'] = $inputs["tradeDate"];
        // 快付支付订单号
        $data['accNo'] = $inputs["accNo"];
        // 快付支付账务日期
        $data['accDate'] = $inputs["accDate"];
        // 订单状态，0-未支付，1-支付成功，2-失败，4-部分退款，5-退款，9-退款处理中
        $data['orderStatus'] = $inputs["orderStatus"];
        // 签名数据
        $data['signMsg'] = $inputs["signMsg"];

        //print_r( $data);
        // 初始化
//        $cHkkPay = new HkkPay($hkk_key, $hkkpay_gateway);
        // 准备准备验签数据
        $str_to_sign = $this->prepareSign($data);
        // 验证签名
        $resultVerify = $this->verify($str_to_sign, $data['signMsg']);
        //var_dump($data);
        if ($resultVerify)
        {
            if ($data['orderStatus'] == '0')
            {

            }
//                echo "未处理[".$data['orderStatus']."]";
            else if ($data['orderStatus'] == '1')// 需更新商户系统订单状态
            {
                $data = M('pay_log')->where(array('orderid'=>$inputs['orderNo']))->find();
                if( $data['state'] !=3 )
                {
                    $res = M('pay_log')->where(array('orderid'=>$inputs["orderNo"]))->save(array('state'=>3));
                }else {
                    $res = true;
                }
            }
//                echo "成功[".$data['orderStatus']."]";
            else if ($data['orderStatus'] == '2')// 需更新商户系统订单状态
            {

            }
//                echo "失败[".$data['orderStatus']."]";
            else if ($data['orderStatus'] == '4')// 需更新商户系统订单状态
            {

            }
//                echo "部分退货[".$data['orderStatus']."]";
            else if ($data['orderStatus'] == '5')// 需更新商户系统订单状态
            {

            }
//                echo "全部退货[".$data['orderStatus']."]";
            else if ($data['orderStatus'] == '9')// 需更新商户系统订单状态
            {

            }
//                echo "退款处理中[".$data['orderStatus']."]";
            else if ($data['orderStatus'] == '11')
            {

            }
//                echo "订单过期[".$data['orderStatus']."]";
            else
            {

            }
//                echo "其他[".$data['orderStatus']."]";

            if ('1' == $_REQUEST["notifyType"]) {

                if($res)
                {
                    echo "SUCCESS";
                }

                return 1;
            }
            /*商户需要在此处判定通知中的订单状态做后续处理*/
            /*由于页面跳转同步通知和异步通知均发到当前页面，所以此处还需要判定商户自己系统中的订单状态，避免重复处理。*/

            return 2;
        }
        else
        {
            // 签名验证失败
            echo "验证签名失败";
            return false;
        }
    }

    /**
     * 创建表单
     * @data		表单内容
     * @gateway 支付网关地址
     */
    function buildForm($data, $gateway) {
        $sHtml = "<form id='hkkpaysubmit' name='hkkpaysubmit' action='".$gateway."' method='post'>";
        while (list ($key, $val) = each ($data)) {
            $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }
        $sHtml.= "<input style='display: none;' type='submit' value='立即支付'/>";
        $sHtml.= "</form>";
        $sHtml.= "<script>window.onload = function(){document.getElementById('hkkpaysubmit').submit();}</script>";
        //$sHtml.= "<script>document.forms['hkkpaysubmit'].submit();</script>";

        return $sHtml;
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
     */
    function prepareSign($data) {
        if($data['apiName'] == 'MOBO_TRAN_QUERY') {
            $result = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&orderNo=%s&tradeDate=%s&amt=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['orderNo'], $data['tradeDate'], $data['amt']
            );
            return $result;
            #} else if (($data['apiName'] == 'WEB_PAY_B2C') || ($data['apiName'] == 'WAP_PAY_B2C')) {
        } else if ($data['apiName'] == 'WEB_PAY_B2C') {
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
    function sign($data) {
        $signature = MD5($data.$this->hkkKey);
        return $signature;
    }

    /*
     * @name	准备带有签名的request字符串
     * @desc	merge signature and request data
     * @param	request字符串
     * @param	签名数据
     * @return
     */
    function prepareRequest($string, $signature) {
        return $string.'&signMsg='.$signature;
    }

    /*
     * @name	请求接口
     * @desc	request api
     * @param	curl,sock
     */
    function request($data, $method='curl') {
        # TODO:	当前只有curl方式，以后支持fsocket等方式
        $curl = curl_init();
        $curlData = array();
        $curlData[CURLOPT_POST] = true;
        $curlData[CURLOPT_URL] = $this->serverUrl;
        $curlData[CURLOPT_RETURNTRANSFER] = true;
        $curlData[CURLOPT_TIMEOUT] = 120;
        #CURLOPT_FOLLOWLOCATION
        $curlData[CURLOPT_POSTFIELDS] = $data;
        curl_setopt_array($curl, $curlData);

        curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt ($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $result = curl_exec($curl);

        if (!$result)
        {
            var_dump(curl_error($curl));
        }
        curl_close($curl);
        //echo $result;
        return $result;
    }

    /*
     * @name	准备获取验签数据
     * @desc	extract signature and string to verify from response result
     */
    function prepareVerify($result) {
        preg_match('{<respData>(.*?)</respData>}', $result, $match);
        $srcData = $match[0];
        preg_match('{<signMsg>(.*?)</signMsg>}', $result, $match);
        $signature = $match[1];
        $signature = str_replace('%2B', '+', $signature);
        return array($srcData, $signature);
    }

    /*
     * @name	验证签名
     * @param	signData 签名数据
     * @param	sourceData 原数据
     * @return
     */
    function verify($data, $signature) {
        $mySign = $this->sign($data);
        if (strcasecmp($mySign, $signature) == 0) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * @name 摩宝查询请求交易
     * @desc
     */
    function hkkpayTranQuery($data) {
        $str_to_sign = $this->prepareSign($data);
        $sign = $this->sign($str_to_sign);
        $to_request = $this->prepareRequest($str_to_sign, $sign);
        $result = $this->request($to_request);

        $to_verify = $this->prepareVerify($result);

        if ($this->verify($to_verify[0], $to_verify[1]) ) {
            return $result;
        } else{
            //echo "verify error";
            return false;
        }
    }

    /*
     * @name	摩宝退款请求交易
     * @desc
     */
    function hkkpayTranReturn($data) {
        $str_to_sign = $this->prepareSign($data);
        $sign = $this->sign($str_to_sign);
        $to_requset = $this->prepareRequest($str_to_sign, $sign);
        $result = $this->request($to_requset);
        $to_verify = $this->prepareVerify($result);
        if ($this->verify($to_verify[0], $to_verify[1]) ) {
            return $result;
        } else {
            return false;
        }
    }

    /*
     * @name	组装请求的交易数据
     * @desc
     */
    function getTradeMsg($data) {
        if($data['tradeSummary']){
            $data['tradeSummary'] = urlencode($data['tradeSummary']);
        }
        return $this->prepareSign($data);
    }
    /*
     * @name	快付支付请求交易
     * @desc
     */
    function hkkpayOrder($data) {
        $str_to_sign = $this->prepareSign($data);
        $sign = $this->sign($str_to_sign);
        $sign = urlencode($sign);
        $to_request = $this->prepareRequest($this->getTradeMsg($data), $sign);
        $url = $this->serverUrl . '?' . $to_request;
        if($data['bankCode']){
            $url = $url . '&bankCode='.$data['bankCode'];
        }
        $this->redirect($url);
    }



    function test()
    {
        return 'ceshichengg';
    }










}
