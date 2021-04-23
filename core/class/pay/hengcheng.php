<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 艾付支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class hengChengPay extends PayInfo
{
    //请求接口Url
    //public $url = 'http://pay.ifeepay.com/gateway/pay.jsp';               //扫码测试调用接口
    public $url = 'http://cz.bwt668.com:29981/api/v1.0/convenience_pay';               //扫码正式（线上）调用接口
    public $payName = '恒诚支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 222050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '恒诚支付',       //支付方式名称
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
            $this->retArr['code'] = 222001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 222001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'request_id' => time().rand(1000,9999),
            'merchant_no' => $config['merchantID'],       //商户ID
            'payment' => $config['payType'][$data['pay_type']]['payStr'],
            'total_fee' => number_format($data['money'], 2, '.', '') * 100,
            'order_ip' => ip(),
//            'order_ip' => '61.141.64.188',
            'out_order_number'    => $orderInfo,
            'order_title'    => 'recharge_title',
            'order_desc'    => 'recharge_desc',
        );

        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        payLog('hengcheng.txt',print_r($post_data,true).'----post_data');

        $curlData = $this->httpPost($post_data, $this->url);
        payLog('hengcheng.txt',print_r($curlData,true).'----curl_return');

        $body = $curlData['data'];
        $result=json_decode($body,true);
        if($result['status']==='000000'){
            $signResp = $result['sign'];
            unset($result['sign']);
            $checkSign = $this->getSign($result, $config['merchantKey']);

            if($signResp != $checkSign){
                $this->retArr['code'] = 277080;
                $this->retArr['msg']  = '签名验证失败';

                $result['sign'] = $signResp;
                $result['checkSign'] = $checkSign;
                payLog('hengcheng.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '验签失败，' . print_r($result, true));
                return $this->retArr;
            }

            if($this->isUrl($result['content'])) {
                $formHtml = $this->httpHtml([], $result['content']);
            }else {
                $formHtml = $result['content'];
            }

            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $formHtml
            ];
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;
            return $this->retArr;

        }
        $this->retArr['code'] = 277079;
        $this->retArr['msg']  = $result['msg'];
        return $this->retArr;
    }

    public function getSign($data, $md5key)
    {
        ksort($data);
        $md5str = "";
        foreach ($data as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = md5($md5str . "key=" . $md5key);
        return $sign;
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
        payLog('hengcheng.txt',print_r($postData,true).'----157--');

        parse_str($postData['data'],$data);

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 277020;
            payLog('hengcheng.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
            return $this->orderInfo;
        }

        if ($data['status'] != 'SUCCESS') {
            $this->orderInfo['code'] = 277021;
            payLog('hengcheng.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));
            return $this->orderInfo;
        }

        ksort($data);
        $signResp=$data['sign'];
        unset($data['sign']);
        $sign = $this->getSign($data, $config['merchantKey']);
        if($sign != $signResp) {
            $this->orderInfo['code'] = 277022;
            payLog('hengcheng.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['out_order_number'];  //商户订单号
        $this->orderInfo['amount']    = number_format($data['total_fee'] / 100,2,'.','');
        return $this->orderInfo;
    }
        
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $post_data 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function httpPost($post_data, $url)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post_data, true));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        //$response=simplexml_load_string($response);
        return array('code' => $httpCode, 'data' => trim($response));
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
        $html .= '</head>';
        $html .= '<body onLoad="document.API.submit();">';
        $html .= '<form name="API" method="post" action="' . $url . '">';
        foreach ($post_data as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        }
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';

//        payLog('paytong.txt',print_r($html,true). "  ==220== ");
        //var_dump($html);
        return $html;
    }

    function isUrl($str) {
        if (filter_var ($str, FILTER_VALIDATE_URL )) {
            return true;
        } else {
            return false;
        }

    }
}