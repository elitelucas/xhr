<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 小强支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class ChengShangPay extends PayInfo
{
    //请求接口Url
    //public $url = 'http://test.xqiangpay.net/website/pay.htm';       //扫码测试调用接口
    public $url = 'http://www.cszfpay.com:8181/chargebank.aspx';       //扫码正式（线上）调用接口
    public $bank_url = 'http://www.cszfpay.com:8181/chargebank.aspx';       //扫码正式（线上）调用接口
    public $payName = '诚尚支付';   //接口名称
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 271050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '诚尚支付',    //支付方式名称
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
            $this->retArr['code'] = 271001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 271001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'parter'          => $config['merchantID'],  //商户ID
            'value'           => number_format($data['money'], 2, '.', ''),
            'orderid'         => $orderInfo,
            'hrefbackurl'     => "https://".$_SERVER['HTTP_HOST'],
            'callbackurl'     => "https://".$_SERVER['HTTP_HOST'] . "/rechargeNotify",
            'payerIp'         => ip(),
            'attach'          => "payment_id=" . $data['payment_id']
        );
        
        if (!empty($data['bank_code'])) {
            $post_data['type'] = $data['bank_code'];
        }else {
            $post_data['type'] = $config['payType'][$data['pay_type']]['payStr'];
        }
        
        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        
        $retData =  [
            'type'  => 2,
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

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 271020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if (!isset($data['opstate']) ||  $data['opstate'] != 0) {
            $this->orderInfo['code'] = 271021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $str = 'orderid=' . $data['orderid'] . '&opstate=' . $data['opstate'] . '&ovalue=' . $data['ovalue'] . $config['merchantKey'];
        $retSign = MD5($str);
        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 271022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['orderid'];
        $this->orderInfo['amount']    = number_format(($data['ovalue']), 2, '.', '');
        $this->orderInfo['serial_no'] = $data['sysorderid'];

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
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$response=simplexml_load_string($response);
        return array('code' => $httpCode, 'data' => trim($response));
    }

    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    function httpHtml($post_data, $url)
    {
        //$url = $url . '?' . http_build_query($post_data);
        
        $html = '<html>';
        $html = '<head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=gb2312">';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="get" action="' . $url . '">';
        foreach ($post_data as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        } 
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';
       
        return $html;
    }
    
    /**
     * 参数签名
     * @param array $data 签名参数
     * @param string $key MD5秘钥
     * @return string 
     */
    public function getSign($data, $key)
    {
        $sign  = '';
        $str   = '';
        
        $str = 'parter=' . $data['parter'] . '&type=' . $data['type'] . '&value=' . $data['value'] . '&orderid=' . $data['orderid'] . '&callbackurl=' . $data['callbackurl'] . $key;

        $sign = md5($str);

        return $sign;
    } 
}