<?php
/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2018/1/5 10:00
 * @description 仁信支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class RenXinPay extends PayInfo
{
    //请求接口Url
    //public $url = '';               //扫码测试调用接口
    public $url = '';               //扫码正式（线上）调用接口
    public $bank_url = 'http://dpos.rxpay88.com/Online/GateWay';  //第三方网银接口
    public $payName = '仁信支付';   //接口名称

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
        'ret_success' => 'ok',  //回调处理成功时，返回接口字符串
        'bank_name' => '仁信支付',       //支付方式名称
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
            'version'     => '3.0',                   //版本号
            'method'      => 'Rx.online.pay',         //接口名称
            'partner'     => $config['merchantID'],  //商户ID
            'banktype'    => $config['payType'][$data['pay_type']]['payStr'],  //银行类型，具体参考附录1,default为跳转到仁信接口进行选择支付
            'paymoney'    => number_format($data['money'], 2, '.', ''),        //金额,精确到两位小数(没有两位小数会出错）
            'ordernumber' => $orderInfo,             //商户支付订单号,每次访问生成的唯一订单标识
            'callbackurl' =>  "https://".$_SERVER['HTTP_HOST']."/rechargeNotify.php"
        );

        $post_data['sign'] = $this->getSigned($post_data, $config['merchantKey']);
        $post_data['hrefbackurl'] = "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo;
        //$post_data['hrefbackurl'] = "https://".$_SERVER['HTTP_HOST'];
        $post_data['goodsname'] = 'recharge';
        $post_data['attach'] =  'renxin_' . $data['payment_id'];
        $post_data['isshow'] = 1;

        payLog('renxin.txt',print_r($post_data,true) . ' --92- ');
        //$curlData = $this->httpPost($post_data, $this->bank_url);
        //var_dump($curlData);

        //type =2返回html跳转页面数
        $data = $this->httpHtml($post_data,$this->bank_url);
        $params = base64_encode($data);
        $retData =  [
            'type'  => $config['payType'][$data['pay_type']]['request_type'],
            'modes' => $data['pay_model'],
//            'html'  => $this->httpHtml($post_data, $this->bank_url)
            'html'  => header("location: http://47.106.193.253/renxinpay.php?params=$data")

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
        $payment_id = $postData['payment_id'];
        //处理get回调数据
        $getData = $_REQUEST;

        $data = array(
            'partner'     => trim($getData['partner']),
            'ordernumber' => trim($getData['ordernumber']),
            'orderstatus' => trim($getData['orderstatus']),
            'paymoney'    => trim($getData['paymoney']),
            'sysnumber'   => trim($getData['sysnumber']),
            'attach'      => trim($getData['attach']),
            'sign'        => trim($getData['sign'])
        );

        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 244020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if (!isset($data['orderstatus']) || $data['orderstatus'] != 1) {
            $this->orderInfo['code'] = 244021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['partner'] = $config['merchantID'];
        $signStr = 'partner=' . $data['partner'] . '&ordernumber=' . $data['ordernumber'] . '&orderstatus=' . $data['orderstatus'] . '&paymoney=' . $data['paymoney'] . $config['merchantKey'];
        $retSign = strtolower(md5($signStr));
        //var_dump($retSign);
        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 244022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['ordernumber'];
        $this->orderInfo['amount']    = $data['paymoney'];
        $this->orderInfo['serial_no'] = $data['sysnumber'];

        return $this->orderInfo;
    }



    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    public function httpPost($data, $url)
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


        return $html;
    }

    public function getSigned($data, $key)
    {
        $signSource = sprintf("version=%s&method=%s&partner=%s&banktype=%s&paymoney=%s&ordernumber=%s&callbackurl=%s%s", $data['version'],$data['method'],$data['partner'], $data['banktype'], $data['paymoney'], $data['ordernumber'], $data['callbackurl'], $key);
        //$sign = md5($signSource); //32位小写MD5签名值，UTF-8编码
        $sign = strtolower(md5($signSource)); //32位小写MD5签名值，UTF-8编码
        //var_dump($signSource);

        return $sign;
    }

    /**
     * 支付初始数据配置数据库
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
        $data['name'] = $this->payName;
        $data['merchantID'] = 'mi1707270001';
        $data['merchantKey'] = 'f26448bb839740a99d154a93ae4758de';
        $data['partnerId'] = 'ORG_1495786664861';
        $data['payType']['wx']['name'] = '微信支付';
        $data['payType']['wx']['payStr'] = 'WXPAY';
        $data['payType']['wx']['request_type'] = 1;      //request_type： 1，获取二维码，2，跳转html
        $data['payType']['ali']['name'] = '支付宝支付';
        $data['payType']['ali']['payStr'] = 'ALIPAY';
        $data['payType']['ali']['request_type'] = 1;
        $data['payType']['qq']['name'] = 'QQ钱包支付';
        $data['payType']['qq']['payStr'] = '70000201';
        $data['payType']['qq']['request_type'] = 1;
        $data['payType']['wy']['name'] = '网银支付';
        $data['payType']['wy']['payStr'] = 'ALIPAY';
        $data['payType']['wy']['request_type'] = 1;  //request_type： 1，获取二维码字符串，2，跳转html页面
        $data['payType']['wy']['bank_id'] = '';

        $serData = serialize($data);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}