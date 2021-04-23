<?php
/**
 *	Author: Kevin
 * 	CreateDate: 2017/09/14 15:05
 *  description: 华银支付
 */
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class HeYiFuPay extends PayInfo
{
    //请求接口Url
    public $server_url = 'http://pay.heyifuu.cn/cgi-bin/netpayment/pay_gate.cgi'; //测试
    //public $server_url = 'http://pay.heyifuu.cn/cgi-bin/netpayment/pay_gate.cgi';   //生产
    public $retArr = [     //dopay()请求返回格式
            'code' => 0,
            'msg' => '',
            'data' => []
        ];
    public $orderInfo = [   //doPaycallBack()请求返回格式 
        'code' => 0,
        'bank_num' => 205050,  //银行区分号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => '',
        'ret_success' => 'SUCCESS',
        'bank_name' => '合易付',
        'serial_no' => ''  //流水号
    ];
    

    public function __construct()
    {
        parent::__construct();
    }
     
    //生成支付
    public function doPay($data)
    {
        //首次生成payment_config表中的config信息
        //$this->setBaseConfig($data['payment_id']);

        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 206000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 201001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'apiName'      => $config['payType'][$data['pay_type']]['payStr'],  //支付方式
            'apiVersion'   => '1.0.0.0',       //接口版本
            'platformID'   => $config['platformID'],   //代理商户号
            'merchNo'      => $config['merchantID'],       //商户号
            //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
            'merchUrl'     =>  "https://".$_SERVER['HTTP_HOST']."/api/pay/doPaycallBacks",
            'merchParam'   => urlencode('{"payment_id":' . $data['payment_id'] . '}'),
            'orderNo'      => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
            'tradeDate'    => date('YmdHis'),  //商户订单时间
            'amt'          => number_format($data['money'], 2, '.', ''),  //金额
            'tradeSummary' => '会员充值'   //商品主题
        );

        //网银支付调用
        if ($data['pay_type'] == 'wy') {

            //参数名称：页面跳转同步通知地址
            //$post_data['pageNotifyUrl'] =  "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo;

            $post_data['signData'] = getSigned($post_data, ['key' => $config['merchantKey']]);
            $post_data['signType'] = 'MD5';  //不需要签名
            $post_data['bankCode'] = '';
            $post_data['choosePayType'] = $config['payType'][$data['pay_type']]['payStr'];
            //var_dump($post_data);

            //type =2返回html跳转页面数
            $retData =  ['type' => $config['payType'][$data['pay_type']]['request_type'], 'html' => $this->httpHtml($this->server_url, $post_data)];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        }else {

            //非网银支付，微信、支付宝、QQ钱包支付
            $post_data['customerIP'] = ip();
            $post_data['signMsg'] = getSigned($post_data, ['key' => $config['merchantKey']]);
            //var_dump($post_data);

            //curl接口
            $dataJson = json_encode($post_data);
            $curlData = $this->httpPostJson($this->url, $dataJson);
            //var_dump($curlData);




            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 202002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '闪云付支付接口调用失败，' . print_r($curlData, true));

                return $this->retArr;
            }
            $retData = $curlData['data'];
            //var_dump($retData);
            if ($retData['respCode'] == '00') {
                $retData['merId'] = $config['merchantID'];
                $retSign = getSigned($retData, ['key' => $config['merchantKey']], ['signMsg']);
                if($retSign != $retData['signMsg']) {
                    $this->retArr['code'] = 202003;
                    $this->retArr['msg']  = '支付二维码生成失败！';
                    payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '闪云付支付返回数据成功，但验证签名失败！' . print_r($retData, true));

                    return $this->retArr;
                }

                D('accountRecharge')->save(['remark' => $retData['respDesc']], ['order_sn' => $orderInfo]);
                //不知道为什么这样做
                session::set('qrcode_url', base64_decode($retData['codeUrl']));
                session::set('pay_url', '');
                //type =1 返回二维码数据
                $ret =  ['type' => $config['payType'][$data['pay_type']]['request_type'], 'code_url' => base64_decode($retData['codeUrl']), 'pay_url' => '', 'order_no' => $orderInfo, 'modes' => $data['pay_model']];
                $this->retArr['code'] = 0;
                $this->retArr['data']  = $ret;

                return $this->retArr;
            }else {
                $this->retArr['code'] = 202005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '闪云付支付接口调用失败，' . print_r($retData, true));

                return $this->retArr;
            }
        }
    }

    //支付回调方法
    public function doPaycallBack($postData)
    {
        //处理post回调数据
        $data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 202006;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）闪云付支付异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }
        
        $data['merchNo'] = $config['merchantID'];

        $retSign = getSigned($data, ['key' => $config['merchantKey']], ['signMsg', 'notifyType']);
        //echo $sign;
         
        if($data['signMsg'] != $retSign){
            $this->orderInfo['code'] = 202007;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）华银支付异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['apiName'] != 'PAY_RESULT_NOTIFY' || $data['orderStatus'] != 1) {
            $this->orderInfo['code'] = 201010;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）华银支付异步通知：返回信息不是充值成功信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['order_no']  = $data['orderNo'];
        $this->orderInfo['amount']    = $data['tradeAmt'];
        $this->orderInfo['serial_no'] = $data['accNo'];

        return $this->orderInfo;
    }

    /**
     * 生成签名
     * @param $data arrary 生成签名需要的数据
     * @param $key arrary 用户密锁,键值对(一对)
     * @return string 生成签名
     */
    public function getSigned($data = [], $key = [])
    {
        $string = $this->ToUrlParams($data);
        if (!empty($key)) {
            foreach ($key as $k => $v) {
                if (!is_array($v)) {
                    $string .= '&' . $k . '=' . $v;
                }
            }
        }

        $string = strtoupper(md5($string));

        return $string;
    }

    /**
     * 格式化参数格式化成url参数
     * @param $data arrary 生成url需要的数据
     * @return string 生成url
     */
    public function ToUrlParams($data)
    {
        $buff = "";

        ksort($data);
        foreach ($data as $k => $v)
        {
            if($k != "sign" && $k != "sign_type" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");

        return $buff;
    }

    //post数据
    function httpPostJson($url, $jsonStr)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($jsonStr)
        )
            );

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $res = (array)simplexml_load_string($response);
        
        return array('code' => $httpCode, 'data' => (array)$res['response']);
    }

    function httpHtml($url, $post_data)
    {
        $html = '<html>';
        $html = '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html = '<form id="payFrom" name="dinpayForm" method="post" action="' . $url . '">';
        $html .= '<input type="hidden" name="apiName"		  value="' . $post_data['apiName'] . '" />';
        $html .= '<input type="hidden" name="merchNo" value="' . $post_data['merchNo'] . '" />';
        $html .= '<input type="hidden" name="apiVersion"      value="' . $post_data['apiVersion'] . '"/>';
        $html .= '<input type="hidden" name="orderNo"  value="' . $post_data['orderNo'] . '"/>';
        $html .= '<input type="hidden" name="tradeDate"  value="' . $post_data['tradeDate'] . '"/>';
        $html .= '<input type="hidden" name="amt"    value="' . $post_data['amt'] . '">';
        $html .= '<input type="hidden" name="tradeSummary" value="' . $post_data['tradeSummary'] . '"/>';
        $html .= '<input type="hidden" name="customerIP"     value="' . $post_data['customerIP'] . '"/>';
        $html .= '<input type="hidden" name="merchUrl"    value="' . $post_data['merchUrl'] . '"/>';
        $html .= '<input type="hidden" name="signData"  value="' . $post_data['signData'] . '"/>';
        $html .= '<input Type="hidden" Name="bankCode"     value="' . $post_data['bankCode'] . '"/>';
        $html .= '<input type="hidden" name="choosePayType" value="' . $post_data['choosePayType'] . '"/>';
        $html .= '<input Type="hidden" Name="platformID"    value="' . $post_data['platformID'] . '"/>';
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';
         
        return $html;
    }

    //支付初始配置
    public function setBaseConfig($payment_id)
    {
        $data1['name'] = '合易付支付';
        $data1['merchantID'] = '2105411505057615';
        $data1['platformID'] = '123';
        $data1['merchantKey'] = 'F02FBC69588AA022';
        $data1['payType']['wx']['name'] = '微信支付';
        $data1['payType']['wx']['payStr'] = 'WECHAT_PAY';
        $data1['payType']['wx']['request_type'] = 1;
        $data1['payType']['ali']['name'] = '支付宝支付';
        $data1['payType']['ali']['payStr'] = 'AL_SCAN_PAY';
        $data1['payType']['ali']['request_type'] = 1;
        $data1['payType']['wy']['name'] = '网银支付';
        $data1['payType']['wy']['payStr'] = 'WEB_PAY_B2C';
        $data1['payType']['wy']['request_type'] = 2;

        $serData = serialize($data1);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}