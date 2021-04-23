<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/12/23 10:30
 * @description 魔宝云支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class MoBaoYunPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://cashier.fengyinchess.com/cgi-bin/netpayment/pay_gate.cgi';               //扫码正式（线上）调用接口
    public $bank_url = 'http://cashier.fengyinchess.com/cgi-bin/netpayment/pay_gate.cgi';  //第三方网银接口
    public $payName = '魔宝云';   //接口名称
    public $arrWap = [9,10,11];   //wap、h5支付
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 244050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '魔宝云',    //支付方式名称
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
            $this->retArr['code'] = 244001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 244001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }
        
        $post_data = array(
            'apiName'      => in_array($config['payType'][$data['pay_type']]['payStr'], $this->arrWap) ? "WAP_PAY_B2C" : "WEB_PAY_B2C",         // 商户APINMAE，WEB渠道一般支付
            'apiVersion'   => "1.0.0.1",             // 商户API版本
            'platformID'   => $config['partnerId'],  // 商户在支付系统的平台号
            'merchNo'      => $config['merchantID'], // 支付系统分配给商户的账号
            'orderNo'      => $orderInfo,            //商户支付订单号,每次访问生成的唯一订单标识
            'tradeDate'    => date("Ymd"),           //商户订单日期
            'amt'          => number_format($data['money'], 2, '.', ''),  //金额,精确到两位小数
            //'merchUrl'     => "https://".$_SERVER['HTTP_HOST'],  //商户通知地址
            'merchUrl'     => "https://".$_SERVER['HTTP_HOST']."/rechargeNotify.php",  //商户通知地址
            //'merchUrl'     => "https://".$_SERVER['HTTP_HOST']."/api/recharge/rechargeNotify/payment_id/" . $data['payment_id'],  //商户通知地址
            'customerIP'   => ip(),
            'merchParam'   => 'mobaoyun_' . $data['payment_id'],
            'tradeSummary' => "会员充值"
        );

/*
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
*/
        //var_dump($post_data);
        $post_data['signMsg'] = $this->getSign($post_data, $config['merchantKey']);
        $post_data['bankCode'] = !empty($data['bank_code']) ? $data['bank_code'] : '';
        
        $post_data['choosePayType'] = $config['payType'][$data['pay_type']]['payStr'];
        
        //print_r($post_data);
        //type =2返回html跳转页面数
        $retData =  [
            'type'  => $config['payType'][$data['pay_type']]['request_type'],
            'modes' => $data['pay_model'],
            'html'  => $this->httpHtml($post_data, $this->bank_url)
        ];
        
        $this->retArr['code'] = 0;
        $this->retArr['data']  = $retData;
        
        return $this->retArr;
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
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 244020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if (!isset($data['apiName']) || $data['apiName'] != 'PAY_RESULT_NOTIFY' || !isset($data['orderStatus']) ||  $data['orderStatus'] != 1) {
            $this->orderInfo['code'] = 244021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }
        
        if ($data['notifyType'] != 1) {
            header("Location: https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $data['orderNo']); //支付返回页面跳转
        
            exit;
        }

        //防止错传
        $data['merchNo'] = $config['merchantID'];
        
        $retSign = $this->getSign($data, $config['merchantKey']);
        if(strtoupper($retSign) != $data['signMsg']){
            $this->orderInfo['code'] = 244022;
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
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    public function httpHtml($post_data, $url)
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

        //var_dump($html);
        return $html;
    }
    
    public function getSign($data, $key)
    {
        $signature = '';
        
        if ($data['apiName'] == 'WAP_PAY_B2C' || $data['apiName'] == 'WEB_PAY_B2C') {
            $signature = sprintf(
                "apiName=%s&apiVersion=%s&platformID=%s&merchNo=%s&orderNo=%s&tradeDate=%s&amt=%s&merchUrl=%s&merchParam=%s&tradeSummary=%s&customerIP=%s",
                $data['apiName'], $data['apiVersion'], $data['platformID'], $data['merchNo'], $data['orderNo'], $data['tradeDate'], $data['amt'], $data['merchUrl'],
                $data['merchParam'], $data['tradeSummary'],$data['customerIP']);
        } elseif ($data['apiName'] == 'PAY_RESULT_NOTIFY') {
            $signature = sprintf(
                "apiName=%s&notifyTime=%s&tradeAmt=%s&merchNo=%s&merchParam=%s&orderNo=%s&tradeDate=%s&accNo=%s&accDate=%s&orderStatus=%s",
                $data['apiName'], $data['notifyTime'], $data['tradeAmt'], $data['merchNo'], $data['merchParam'], $data['orderNo'], $data['tradeDate'], $data['accNo'], $data['accDate'], $data['orderStatus']
            );
        }

        $sign = MD5($signature . $key);

        return $sign;
    }

}
