<?php

//Hady
include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class BaiFuPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://defray.948pay.com:8188/api/smPay.action';               //扫码正式（线上）调用接口
    public $bank_url = 'http://defray.948pay.com:8188/api/smPay.action';  //第三方网银接口
    public $payName = '百富';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 240000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => '000000',  //回调处理成功时，返回接口字符串
        'bank_name' => '百富支付',    //支付方式名称
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
        payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        if (!$orderInfo) {
            $this->retArr['code'] = 240001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 240002;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'merchantNo'      => $config['merchantID'], // 支付系统分配给商户的账号
            'netwayCode'      => $config['payType'][$data['pay_type']]['payStr'],
            'randomNum'      => (string) rand(1000,9999),
            'orderNum'      => $orderInfo,
            'payAmount'      => $data['money']*100 . '',
            'goodsName'      => 'recharge',
            'callBackUrl'      => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id'] ,  //商户通知地址
            'frontBackUrl'      => "https://".$_SERVER['HTTP_HOST'],  //商户通知地址
            'requestIP'      => ip(),
        );
        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        $post_data['sign'] = strtoupper($post_data['sign']);
        ksort($post_data);
        $post_data = json_encode($post_data);
        $post_data = str_rePlace("\\/", "/", $post_data);
        payLog('baifu.txt',$post_data.'----84---');
        $post_data = array('paramData'=>$post_data);
        payLog('baifu.txt',print_r($post_data,true).'----89---');

        $curlData = $this->httpPost($post_data, $this->url);
        //接口调用成功与否
        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 240003;
            $this->retArr['msg']  = '付支付接口调用失败';
            payLog('baifu.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，--89--' . print_r($curlData, true).'--101--');

            return $this->retArr;
        }

        $retData = json_decode($curlData['data'], true);
        payLog('baifu.txt',print_r($retData,true).'--96--');
        if ($retData['resultCode'] == 99) {
            $this->retArr['code'] = 240004;
            $this->retArr['msg']  = $retData['resultMsg'];
            payLog('baifu.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，--98--' . print_r($retData, true).'--94--');

            return $this->retArr;
        }

        if ($retData['merchantNo'] != $config['merchantID']) {
            $this->retArr['code'] = 240005;
            $this->retArr['msg']  = $retData['resultMsg'];
            payLog('baifu.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，--114--' . print_r($retData, true));

            return $this->retArr;
        }

        //用于安全验证返回url是否非法
        session::set('qrcode_url', $retData['CodeUrl']);
        session::set('pay_url', $retData['CodeUrl']);

        $ret =  [
            'type'     => 1,
            'code_url' => $retData['CodeUrl'],
            'pay_url'  => $retData['CodeUrl'],
            'order_no' => $orderInfo,
            'modes'    => $data['pay_model']
        ];

        $this->retArr['code'] = 0;
        $this->retArr['data'] = $ret;

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
        $payData = explode('=', $postData['data']);       //从商户传输的参数中获取回传参数
        payLog('baifu.txt',print_r($payData,true).'----161---');
        $data = json_decode($payData[1],true);

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 240006;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['resultCode'] != 00) {
            $this->orderInfo['code'] = 240007;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchantNo'] = $config['merchantID'];
        $sign = $data['sign'];
        unset($data['sign']);
        $retSign = $this->getSign($data, $config['merchantKey']);
        payLog('baifu.txt',$retSign.'----191---');
        if(strtoupper($retSign) != $sign){
            $this->orderInfo['code'] = 240008;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['orderNum'];
        $this->orderInfo['amount']    = $data['payAmount'] / 100;
        $this->orderInfo['serial_no'] = $data['orderNum'];

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

//        var_dump($html);exit;
        return $html;
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setoPt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setoPt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (comPatible; MSIE 5.01; Windows NT 5.0)');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        payLog('baifu.txt',$response.'---195--');
        return array('code' => $httpCode, 'data' => trim($response));
    }

    public function getSign($data, $key)
    {

        ksort($data);
        $signature = json_encode($data);
        $signature = str_rePlace("\\/", "/", $signature);

        $sign = MD5($signature . $key);


        return $sign;
    }

}
