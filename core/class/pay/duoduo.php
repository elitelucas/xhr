<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 多多支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class DuoDuoPay extends PayInfo
{
    //请求接口Url
    public $url = 'http://pay.9stpay.com/cgi-bin/netpayment/pay_gate.cgi';       //扫码正式（线上）调用接口
    public $bank_url = 'http://pay.9stpay.com/cgi-bin/netpayment/pay_gate.cgi';  //第三方网银接口
    public $payName = '多多支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 221050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '多多支付',       //支付方式名称
        'serial_no' => ''            //第三方回调返回的第三方支付订单号（支付流水号）
    ];

    /**
     * 构成函数
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 调用第三方支付接口，获取支付信息
     * @param array $data 前端返回信息，payment_id，支付类型ID，config，支付类型配置信息
     * {@inheritDoc}
     * @see PayInfo::doPay()
     * @return $this->$retArr;
     */
    public function doPay($data)
    {
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 221001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 221001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'apiName'   => $config['payType'][$data['pay_type']]['payStr'],
            'apiVersion'=> '1.0.0.0',
            'platformID' => $config['partnerId'],                   //合作方编号,代理商ID
            'merchNo'   => $config['merchantID'],  //商户ID
            'orderNo'   => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
            'tradeDate' => date('Ymd'),
            'amt'       => number_format($data['money'], 2, '.', ''),  //金额,精确到两位小数(没有两位小数会出错）
            'merchUrl'  =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'merchParam'   => 'recharge',
            'tradeSummary' => 'recharge'  //商品主题
        );
        
        if ($data['pay_type'] == 'wy') {
            $str_to_sign = $this->prepareSign($post_data, 1);
            //var_dump($str_to_sign);
            $sign = $this->sign($str_to_sign, $config['merchantKey']);
            $post_data['signMsg'] = $sign;
            $post_data['bankCode'] = '';
            $post_data['choosePayType'] = 1;   //1.网银, 2.一键支付,3非银行支付,4 支付宝扫描,5微信扫码,6:qq扫码支付,10：储蓄卡快捷
            //var_dump($post_data);
            
            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $this->bank_url)
            ];
            
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            $str_to_sign = $this->prepareSign($post_data, 1);
            //var_dump($str_to_sign);
            $sign = $this->sign($str_to_sign, $config['merchantKey']);
            $post_data['signMsg'] = $sign;
            $post_data['overTime'] = '';
            $post_data['customerIP'] = ip();
            //var_dump($post_data);

            //curl接口
            $curlData = $this->httpPost($post_data, $this->url);
            //var_dump($curlData);

           //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 219002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($curlData, true));

                return $this->retArr;
            }
            
            $retData = json_decode($curlData['data'], true);

            if (!isset($retData['respData']['respCode']) || $retData['respData']['respCode'] != '00') {
                $this->retArr['code'] = 219003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($curlData['data'], true));
            
                return $this->retArr;
            }
            
            $retData['respData']['codeUrl'] = base64_decode($retData['respData']['codeUrl']);
            
            //验签
            $retStr = 'respCode=' . $retData['respData']['respCode'] . '&respDesc=' . $retData['respData']['respCode'] . '&codeUrl=' . $retData['respData']['respCode'] . $config['merchantKey'];
            $retSign = md5($retStr);

            if($retData['signMsg'] != $retSign){
                $this->retArr['code'] = 219004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($curlData['data'], true));
            
                return $this->retArr;
            }

            $retOderNo = $orderInfo;
            $retOderPayNo = 'duoduopay' . date('YmdHis');
            $retOderPayQrcodrUrl = isset($retData['respData']['codeUrl']) ? $retData['respData']['codeUrl'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty($retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 219005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据错误，' . print_r($retData, true));
            
                return $this->retArr;
            }

            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 219006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($curlData['data'], true));

                return $this->retArr;
            }
            
            D('accountRecharge')->save(['remark' => $retOderPayNo], ['order_sn' => $retOderNo]);

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $retOderPayQrcodrUrl);
            session::set('pay_url', '');
            //type =1 返回二维码数据
            $ret =  [
                'type'     => $config['payType'][$data['pay_type']]['request_type'],
                'code_url' => $retOderPayQrcodrUrl,
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data'] = $ret;
            
            return $this->retArr;
        }
    }

    /**
     * 支付回调处理
     * @param array $postData data：回调返回的数据，payment_Id：支付类型ID
     * {@inheritDoc}
     * @see PayInfo::doPaycallBack()
     * @return array $this->$retArr;
     */
    public function doPaycallBack($postData)
    {
        //处理post回调数据
        parse_str($postData['data'], $data);
        $payment_id = $postData['payment_id'];

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 221020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if ($data['notifyType'] != 1) {
           // header("Location: https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $data['orderNo']); //支付返回页面跳转
        
            exit;
        }

        if (!isset($data['orderStatus']) || $data['orderStatus'] != 1 || !isset($data['apiName']) || $data['apiName'] != 'PAY_RESULT_NOTIFY') {
            $this->orderInfo['code'] = 221021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchNo'] = $config['merchantID'];
        $str_to_sign = $this->prepareSign($data);
        $retSign = $this->sign($str_to_sign, $config['merchantKey']);
        if($retSign != $data['signMsg']){
            $this->orderInfo['code'] = 221022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['orderNo'];
        $this->orderInfo['amount']    = $data['tradeAmt'];
        $this->orderInfo['serial_no'] = $data['accNo'];

        return $this->orderInfo;
    }

    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function httpPost($data, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        //var_dump($this->prepareVerify($response));

        $response = simplexml_load_string($response);

        return array('code' => $httpCode, 'data' => json_encode($response));
    }

    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    function httpHtml($post_data, $url)
    {
        $html = '<html>';
        $html = '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="' . $url . '">';
        foreach ($post_data as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        } 
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';
       
        return $html;
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
    public function prepareSign($data, $type = 0) {
        //if($data['apiName'] == 'WEB_PAY_B2C') {
        if($type == 1) {
            $result = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&orderNo=%s&tradeDate=%s&amt=%s&merchUrl=%s&merchParam=%s&tradeSummary=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['orderNo'], $data['tradeDate'], $data['amt'], $data['merchUrl'], $data['merchParam'], $data['tradeSummary']
                );
            return $result;
        } else {
            $result = sprintf(
                "apiName=%s&notifyTime=%s&tradeAmt=%s&merchNo=%s&merchParam=%s&orderNo=%s&tradeDate=%s&accNo=%s&accDate=%s&orderStatus=%s",
                $data['apiName'], $data['notifyTime'], $data['tradeAmt'], $data['merchNo'], $data['merchParam'], $data['orderNo'], $data['tradeDate'], $data['accNo'], $data['accDate'], $data['orderStatus']
                );
            return $result;
        }
    }
    
    /**
     * @name	生成签名
     * @param	sourceData
     * @return	签名数据
     */
    public function sign($data, $key) {
        $signature = MD5($data . $key);
        return $signature;
    }
    
    public function prepareVerify($result) {
        preg_match('{<respData>(.*?)</respData>}', $result, $match);
        $srcData = $match[1];
        preg_match('{<signMsg>(.*?)</signMsg>}', $result, $match);
        $signature = $match[1];
        $signature = str_replace('%2B', '+', $signature);
        return array($srcData, $signature);
    }
}
