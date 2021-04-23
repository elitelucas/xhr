<?php

/**
 *	Author: Kevin
 * 	CreateDate: 2017/10/18 14:25
 *  description: 彩付通支付
 */
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class CaiFuTongPay extends PayInfo
{
    //请求接口Url
    public $url = 'https://c.bshunlan.com/order/create';  //部署
    public $retArr = [
        'code' => 1,
        'msg' => '',
        'data' => []
    ];
    public $orderInfo = [       //异步验签结果返回格式
        'code' => 1,
        'bank_num' => 210050,  //银行站内编号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => 0,
        'ret_success' => 'success',
        'bank_name' => '彩付通支付',
        'serial_no' => ''  //流水号
    ];

    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 获取支付信息
     * {@inheritDoc}
     * @see PayInfo::doPay()
     */
    public function doPay($data)
    {
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 210000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 210001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];

        $post_data = array(
            'payment'    => $payStr,                  //固定值
            'merchno'  => $config['merchantID'],  //商户ID
            'out_trade_no'  => $orderInfo,             //商户支付订单号,每次访问生成的唯一订单标识
            'subject'    => 'recharge',                  //固定值,交易币种
            'total_amount'    => number_format($data['money'], 2, '.', ''),  //单位:元，精确到分
            //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
//            'notify_url' =>  "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'notify_url' =>  "https://".$callbackurl,
//            'redirect_url'   => "https://".$callbackurl."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo
            'redirect_url'   => "https://".$callbackurl."/rechargeNotify.php"
        );

        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        $post_data = json_encode($post_data,JSON_UNESCAPED_UNICODE);

        payLog('caifut.txt',print_r($post_data,true).'---841--');
        $curlData = $this->httpPost($post_data, $this->url);
        payLog('caifut.txt',print_r($curlData,true).'---84--');
        //接口调用成功与否
        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 275073;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('caifut.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

            return $this->retArr;
        }
        $returnData = json_decode($curlData['data'],true);
        payLog('caifut.txt', print_r($returnData, true).'----113---');
        if ($returnData['pay_url'] == '' || $returnData['pay_url'] != 0) {
            $this->retArr['code'] = 275074;
            $this->retArr['msg']  = '支付接口调用失败';
            payLog('caifut.txt', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($returnData, true));

            return $this->retArr;
        }


        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {


            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnData['pay_url']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $returnData['pay_url'],
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            //type =2返回html跳转页面数
//            $url = urldecode($returnData['payURL']);
//            payLog('caifut.txt',$url. "   ====137");
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml(array(), $returnData['pay_url'])
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        }
    }

    /**
     * 支付回调方法
     * {@inheritDoc}
     * @see PayInfo::doPaycallBack()
     */
    public function doPaycallBack($postData)
    {
        payLog('caifut.txt',print_r($postData,true). "===140");
        parse_str($postData['data'],$data);
        
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 210010;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）彩付通支付异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if ($data['status'] != 1) {
            $this->orderInfo['code'] = 210011;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）彩付通支付异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        //验签
        $data['merchno']    = $config['merchantID'];
        $sign = $data['sign'];
        unset($data['sign']);
        $retSign = $this->getSign($data, $config['merchantKey']);
        if($retSign != $sign){
            $this->orderInfo['code'] = 210012;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . '彩付通支付返回数据成功，但验证签名失败！' . print_r($data, true));
        
            return $this->orderInfo;
        }
            
        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $data['out_trade_no'];
        $this->orderInfo['amount']    = $data['total_amount'];
        $this->orderInfo['serial_no'] = $data['trade_no'];

        return $this->orderInfo;
    }


    public function getSign($data,$key)
    {
        $sign_str = '';
        ksort($data);
        foreach ($data as $k => $v) {
            $sign_str = $sign_str . $k. '=' . $v . '&';
        }
        $sign_str .= $key;
        return strtoupper(md5($sign_str));
    }
        
    /**
     * post请求数据
     * @param 字符串  $url  post请求url
     * @param array $postData post请求数据
     * @return mixed[]
     */
    function httpPost($postData,$url)
    {
        payLog('caifut.txt',$url. "===202");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_HEADER, true);
        //类型为json
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return array('code' => $httpCode, 'data' => $response);
    }


}