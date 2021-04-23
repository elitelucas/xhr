<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class HuiYinPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://api.huiyin-pay.com/orderpay/pay';                 //扫码测试调用接口

    public $bank_url = 'http://api.huiyin-pay.com/orderpay/pay';  //第三方网银接口

    public $payName = '汇银';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 257000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '汇银',    //支付方式名称
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
            $this->retArr['code'] = 257001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 257002;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        $post_data = array(
            'merchantcode'      => $config['merchantID'],        //商户ID
            'type'              => $data['childType'],          //通道类型
            'amount'            => number_format($data['money'], 2, '.', ''),  //金额
            'orderid'           => $orderInfo,  //订单号
            'callbackurl'    => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,
            'notifyurl'    => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],

        );

        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        $retData =  [
            'type'  => 2,
            'modes' => $data['pay_model'],
            'html'  => $this->httpHtml($post_data,$this->url)
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
        payLog('huiyin.txt',print_r($postData,true).'----149--');
//        parse_str($postData['data'],$data);
        $data = json_decode($postData['data'],true);
        payLog('huiyin.txt',print_r($data,true).'----149--');
        $payment_id = $postData['payment_id'];
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 257005;
            payLog('huiyin.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }
        if ($data['status'] != 0) {
            $this->orderInfo['code'] = 257008;
            payLog('huiyin.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }


        //防止错传
//        $data['merchantcode'] = $config['merchantID'];
        $sign = $data['sign'];
        unset($data['sign']);

        $retSign = $this->callBackSign($data, $config['merchantKey']);
        payLog('huiyin.txt',$retSign.'---173--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 257007;
            payLog('huiyin.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['orderid'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['platformorderid'];

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
//        foreach ($post_data as $key => $value) {
//            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
//        }
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';

        //var_dump($html);
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        $response = simplexml_load_string($response);
        return array('code' => $httpCode, 'data' => $response);
    }

    public function getSign($data, $key)
    {
        $sign = "amount=". $data['amount'] ."&merchantcode=". $data['merchantcode'] ."&notifyurl=". $data['notifyurl'].
            "&orderid=". $data['orderid'] ."&type=". $data['type']."&key=". $key;
        //签名
        $sign = strtoupper(md5($sign));

        return $sign;
    }

    protected function callBackSign($data, $merchantKey)
    {
        #待签名源串及格式如下：
        #preSignStr=“amount ={}merchantcode ={}orderid ={}paytime={}status ={} key={}”;
        #其中，key为商户秘钥。
        #注意参数之间不加“&”符号
        #签名结果如下：
        #sign=MD5(preSignStr) .ToUpper();
        $amount = number_format($data['amount'], 2, '.', '');
        $merchantcode = $data['merchantcode'];
        $orderid = $data['orderid'];
        $paytime = $data['paytime'];
        $status = $data['status'];
        $str = "amount=".$amount."merchantcode=".$merchantcode."orderid=".$orderid."paytime="
            .$paytime."status=".$status."key=$merchantKey";
        $sign = strtoupper(md5($str));
        return $sign;
    }

}
