<?php

/**
 *	Author: Kevin
 * 	CreateDate: 2017/09/25 10:55
 *  description: 中银支付
 */
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class ZhongYinPay extends PayInfo
{
    //请求接口Url
    //public $serve_url = 'http://www.payzbank.com/Pay_Index.html';  //测试
    public $server_url = 'http://www.payzbank.com/Pay_Index.html';  //部署
    public $bank_url = 'http://pay.yunshi44.top'; //网银支付接口
    
    public $pay_bankcode = "WXZF";   //银行编码
    private $pay_tradetype = "";    //固定为空
    public $retArr = [
        'code' => 0,
        'msg' => '',
        'data' => []
    ];
    public $orderInfo = [       //异步验签结果返回格式
        'code' => 0,
        'bank_num' => 207050,  //银行区分号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => '',
        'ret_success' => 'ok',
        'bank_name' => '中银在线',
        'serial_no' => '123'  //流水号
    ];

    
    public function __construct()
    {
        parent::__construct();
    }

    public function doPay($data)
    {
        $pay_url = $this->server_url;
        //首次生成payment_config表中的config信息
        //$this->setBaseConfig($data['payment_id']);

        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 207000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
        
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 207001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
        
            return $this->retArr;
        }
        
        if ($data['pay_type'] == 'wy') {
           $pay_url = $this->bank_url;
        }
        
        $payData = [
            "pay_memberid" => $config['merchantID'],
            'pay_orderid' => $orderInfo,//商户订单号
            'pay_applydate' => date("Y-m-d H:i:s"),//商户订单日期
            "pay_bankcode" => $config['payType'][$data['pay_type']]['payStr'],
            'pay_notifyurl' =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'], //回调地址
            "pay_callbackurl" => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo, //回跳地址
            'pay_amount'=>$data['money']
        
        ];
 
        //生成签名
        $payData["pay_md5sign"] = getSigned($payData, ['key' => $config['merchantKey']], [], '&', 1, 1);
        $payData["pay_tradetype"] = $this->pay_tradetype;
        $payData["pay_tongdao"] = $config['payType'][$data['pay_type']]['payStr'];
        $payData["pay_productname"] = '会员充值';
        //type =2返回html跳转页面数
        $retData =  ['type' => $config['payType'][$data['pay_type']]['request_type'], 'html' => $this->httpHtml($pay_url, $payData)];
        
        $this->retArr['code'] = 0;
        $this->retArr['data']  = $retData;
        
        return $this->retArr;
    }

    //支付回调方法
    public function doPaycallBack($postData)
    {
        //处理post回调数据
        parse_str($postData['data'], $data);
        //$data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 207002;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）闪云付支付异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        //防止错传
        //$data['merchNo']    = $config['merchantID'];

        $retSign = getSigned($data, ['key' => $config['merchantKey']], ['sign'], '&', 1, 1);
        //echo $sign;
        
        if($data['sign'] != $retSign){
            $this->orderInfo['code'] = 207003;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）华银支付异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        if ($data['returncode'] != '00') {
            $this->orderInfo['code'] = 207004;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）华银支付异步通知：返回信息不是充值成功信息，出现错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        $this->orderInfo['order_no']  = $data['orderid'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['ksPayOrderId'];
        
        return $this->orderInfo;

    }

    public function verifyNotify($post_data,$Md5key,$oldsign){
        ksort($post_data);
        reset($post_data);
        $md5str = "";
        foreach ($post_data as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $Md5key));
        if ($sign == $oldsign) {
            return true;
        }else{
            return false;
        }

    }

    /**
     * @name	生成签名
     * @param	sourceData
     * @return	签名数据
     */
    function sign($md5str,$Md5key) {
        $sign = strtoupper(md5($md5str . "key=" . $Md5key));

        return $sign;
    }



    /**
     * 创建表单
     * @data		表单内容
     * @gateway 支付网关地址
     */
    function httpHtml($url, $data) {
        $sHtml  =  "<html>";
        $sHtml .= '<body onLoad="document.dinpayForm.submit();">';
        $sHtml .= "<form id='payFrom' name='dinpayForm' action='" . $url . "' method='post'>";
        $sHtml .= "<input type='hidden' name='pay_amount' value='".$data['pay_amount']."' />";
        $sHtml .= "<input type='hidden' name='pay_applydate' value='".$data['pay_applydate']."' />";
        $sHtml .= "<input type='hidden' name='pay_bankcode' value='".$data['pay_bankcode']."' />";
        $sHtml .= "<input type='hidden' name='pay_callbackurl' value='".$data['pay_callbackurl']."' />";
        $sHtml .= "<input type='hidden' name='pay_memberid' value='".$data['pay_memberid']."' />";
        $sHtml .= "<input type='hidden' name='pay_notifyurl' value='".$data['pay_notifyurl']."' />";
        $sHtml .= "<input type='hidden' name='pay_orderid' value='".$data['pay_orderid']."' />";
        $sHtml .= "<input type='hidden' name='pay_md5sign' value='".$data['pay_md5sign']."' />";
        $sHtml .= "<input type='hidden' name='pay_tradetype' value='".$data['pay_tradetype']."' />";
        $sHtml .= "<input type='hidden' name='pay_tongdao' value='".$data['pay_tongdao']."' />";
        $sHtml .= "<input type='hidden' name='pay_productname' value='".$data['pay_productname']."' />";
        $sHtml .= "</form>";
        $sHtml .= "</body>";
        $sHtml .= '</html>';
        return $sHtml;
    }

    //支付初始配置
    public function setBaseConfig($payment_id)
    {
        $data1['name'] = '中银在线';
        $data1['merchantID'] = '10009';
        $data1['merchantKey'] = 'aEjjy1CayRQ23zHggtPKFmHK6LT59n';
        $data1['payType']['wx']['name'] = '微信支付';
        $data1['payType']['wx']['payStr'] = 'WXZF';
        $data1['payType']['wx']['payChanel'] = 'Dpwx';
        $data1['payType']['wx']['payTradeType'] = '900021';
        $data1['payType']['wx']['request_type'] = 2;
        $data1['payType']['ali']['name'] = '支付宝支付';
        $data1['payType']['ali']['payStr'] = 'ALIPAY';
        $data1['payType']['wx']['payChanel'] = 'Dpali';
        $data1['payType']['ali']['payTradeType'] = '900022';
        $data1['payType']['ali']['request_type'] = 2;
        $data1['payType']['wy']['name'] = '网银支付';
        $data1['payType']['wy']['payStr'] = 'BANKZF';
        $data1['payType']['wy']['payChanel'] = 'Dpbank';
        $data1['payType']['wy']['payTradeType'] = '';
        $data1['payType']['wy']['request_type'] = 2;
    
        $serData = serialize($data1);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }



}