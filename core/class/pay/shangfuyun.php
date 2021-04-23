<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class ShangFuYunPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://gate.xxxxpay.com/cooperate/gateway.cgi';                 //扫码测试调用接口

    public $bank_url = 'http://gate.xxxxpay.com/cooperate/gateway.cgi';  //第三方网银接口

    public $payName = '尚付云';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 250000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '尚付云',    //支付方式名称
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
            $this->retArr['code'] = 250001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 250002;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        $post_data = array(
            'service'    => $config['payType'][$data['pay_type']]['typeStr'],
            'version'    => "1.0.0.0",
            'merId'    => $config['merchantID'],
            'typeId'    => $payStr,
            'tradeNo'    => $orderInfo,
            'tradeDate'    => date("Ymd",time()),
            'amount'    =>number_format($data['money'], 2, '.', ''),
            'notifyUrl'    => "https://".$_SERVER['HTTP_HOST']."/rechargeNotify.php",
            'summary'    => "YUN",
            'extra'    => $data['payment_id'],
            'expireTime'    => 180,
            'clientIp'    => ip(),
        );

        $signMsg = $this->getSign($post_data, $config['merchantKey']);

        $post_data['sign'] = md5($signMsg.$config['merchantKey']);
        payLog('shangfuy.txt',print_r($post_data,true).'---92--');

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {
            $post_data = $signMsg."&sign=".$post_data['sign'];
            $curlData = $this->httpPost($post_data, $this->url);
            payLog('shangfuy.txt',print_r($curlData,true).'---93--');

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 242003;
                $this->retArr['msg']  = '支付调用失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

                return $this->retArr;
            }
            $returnInfo = $curlData['data'];

            if ($returnInfo['detail']['code'] != 00) {
                $this->retArr['code'] = 243004;
                $this->retArr['msg']  = $returnInfo['msg'];
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($returnInfo, true));
                return $this->retArr;
            }

            $returnInfo['detail']['qrCode'] = base64_decode($returnInfo['detail']['qrCode']);
            payLog('shangfuy.txt',print_r($returnInfo,true).'---97--');

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnInfo['detail']['qrCode']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $returnInfo['detail']['qrCode'],
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];
            payLog('shangfuy.txt',print_r($retData,true).'---123--');
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;

        } else {
            $retData =  [
                'type'  => 2,
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data,$this->url)
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

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
        payLog('shangfuy.txt',print_r($postData,true).'----157--');
        parse_str($postData['data'],$data);
        payLog('shangfuy.txt',print_r($data,true).'----149--');
        $payment_id = $postData['payment_id'];
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 243005;
            payLog('shangfuy.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['status'] != 1) {
            $this->orderInfo['code'] = 243006;
            payLog('shangfuy.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merId'] = $config['merchantID'];
        $retSign = $this->getSign($data, $config['merchantKey']);
        if($retSign != $data['sign']){
            $this->orderInfo['code'] = 243007;
            payLog('shangfuy.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['tradeNo'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['opeNo'];

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
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt ($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response = xmlToArray($response);
        return array('code' => $httpCode, 'data' => $response);
    }

    public function getSign($data, $key)
    {
        //1网银支付
        if($data['service'] == 'TRADE.B2C') {
            $result = sprintf(
                "service=%s&version=%s&merId=%s&tradeNo=%s&tradeDate=%s&amount=%s&notifyUrl=%s&extra=%s&summary=%s&expireTime=%s&clientIp=%s&bankId=%s",
                $data['service'],
                $data['version'],
                $data['merId'],
                $data['tradeNo'],
                $data['tradeDate'],
                $data['amount'],
                $data['notifyUrl'],
                $data['extra'],
                $data['summary'],
                $data['expireTime'],
                $data['clientIp'],
                $data['bankId']
            );


            return $result;
            //2扫码支付
        }else if($data['service'] == 'TRADE.SCANPAY'){
            $result = sprintf(
                "service=%s&version=%s&merId=%s&typeId=%s&tradeNo=%s&tradeDate=%s&amount=%s&notifyUrl=%s&extra=%s&summary=%s&expireTime=%s&clientIp=%s",
                $data['service'],
                $data['version'],
                $data['merId'],
                $data['typeId'],
                $data['tradeNo'],
                $data['tradeDate'],
                $data['amount'],
                $data['notifyUrl'],
                $data['extra'],
                $data['summary'],
                $data['expireTime'],
                $data['clientIp']


            );
            return $result;
            //3支付订单查询
        }else if($data['service'] == 'TRADE.H5PAY'){
            $result = sprintf(
                "service=%s&version=%s&merId=%s&typeId=%s&tradeNo=%s&tradeDate=%s&amount=%s&notifyUrl=%s&extra=%s&summary=%s&expireTime=%s&clientIp=%s",
                $data['service'],
                $data['version'],
                $data['merId'],
                $data['typeId'],
                $data['tradeNo'],
                $data['tradeDate'],
                $data['amount'],
                $data['notifyUrl'],
                $data['extra'],
                $data['summary'],
                $data['expireTime'],
                $data['clientIp']


            );
            return $result;
            //快捷支付
        }else if($data['service'] == 'TRADE.NOTIFY') {
            $result = sprintf(
                "service=%s&merId=%s&tradeNo=%s&tradeDate=%s&opeNo=%s&opeDate=%s&amount=%s&status=%s&extra=%s&payTime=%s",
                $data['service'],
                $data['merId'],
                $data['tradeNo'],
                $data['tradeDate'],
                $data['opeNo'],
                $data['opeDate'],
                $data['amount'],
                $data['status'],
                $data['extra'],
                $data['payTime']
            );
            return $result;
        }
    }

}
