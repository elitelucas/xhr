<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class WechatPay extends PayInfo
{
    //请求接口Url

    public $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';                 //扫码测试调用接口

    public $bank_url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';  //第三方网银接口

    public $payName = '微信支付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 262000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => '<xml>
  <return_code><![CDATA[SUCCESS]]></return_code>
  <return_msg><![CDATA[OK]]></return_msg>
</xml>',  //回调处理成功时，返回接口字符串
        'bank_name' => '微信支付',    //支付方式名称
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
            $this->retArr['code'] = 262001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('wechat.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 262002;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('wechat.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        $post_data = array(
            'appid'    => $config['appid'],
            'mch_id'    => $config['merchantID'],
            'nonce_str'    => $orderInfo,
            'body'    => "recharge",
            'attach'    => "xml_". $data['payment_id'],
            'out_trade_no'    => $orderInfo,
            'spbill_create_ip'    => $_SERVER['REMOTE_ADDR'],
            'notify_url'    => "https://".$callbackurl."/rechargeNotify.php",
            'trade_type'    => $payStr,
            'total_fee'    => number_format($data['money'], 2, '.', '')*100,
            'product_id'    => $orderInfo,
        );


//        if ($data['entrance'] == "Android") {
//            $post_data['scene_info'] = "{".
//                "    \"h5_info\": ".
//                    "{".
//                        "\"type\" : \"IOS\"," .
//                        "\"app_name\" : \"7天乐\"," .
//                        "\"package_name\" : \"alksfoe\"," .
//
//                    "}" .
//                "}";
//        } else {
//            $post_data['scene_info'] = "{".
//                "    \"h5_info\": ".
//                    "{".
//                    "\"type\" : \"IOS\"," .
//                    "\"app_name\" : \"7天乐\"," .
//                    "\"package_name\" : \"alksfoe\"," .
//
//                    "}" .
//                "}";
//        }

        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        $post_data = arrayToXml($post_data);
        payLog('wechat.txt',print_r($post_data,true) );
//        $this->
        $curlData = $this->httpPost($post_data, $this->url, $config['merchantID']);
        payLog('wechat.txt',print_r($curlData,true) . ' --96--');

        //接口调用成功与否
        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 262003;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('wechat.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

            return $this->retArr;
        }
        $returnInfo = $curlData['data'];
//        payLog('wechat.txt',print_r($returnInfo,true).'---97--');
        if ($returnInfo['return_code'] != 'SUCCESS' && $returnInfo['result_code'] != "SUCCESS") {
            $this->retArr['code'] = 262004;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('wechat.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($returnInfo, true));
            return $this->retArr;
        }
        $retSign = $returnInfo['sign'];
        $sign = $this->getSign($returnInfo,$config['merchantKey']);
        if ($sign != $retSign ) {
            $this->retArr['code'] = 262009;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('wechat.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($returnInfo, true));
            return $this->retArr;
        }

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnInfo['code_url']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $returnInfo['code_url'],
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;

        } elseif ($config['payType'][$data['pay_type']]['request_type'] == 2) {

            $retData =  [
                'type'  => 2,
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml(array(),$returnInfo['mweb_url'])
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
        payLog('wechat.txt',print_r($postData,true).'----149--');
        parse_str($postData['data'],$data);
        payLog('wechat.txt',print_r($data,true).'----149--');
        $payment_id = $postData['payment_id'];
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 262005;
            payLog('wechat.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }
        if ($data['return_code'] != 'SUCCESS' && $data['result_code'] != "SUCCESS") {
            $this->orderInfo['code'] = 262008;
            payLog('wechat.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }


        //防止错传
        $data['mch_id'] = $config['merchantID'];
        $sign = $data['sign'];

        $retSign = $this->getSign($data, $config['merchantKey']);
        payLog('wechat.txt',$retSign.'---173--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 262007;
            payLog('wechat.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['out_trade_no'];
        $this->orderInfo['amount']    = $data['total_fee'] /100;
        $this->orderInfo['serial_no'] = $data['transaction_id'];

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
    public function httpPost($data, $url ,$merchantId)
    {
        $ch = curl_init();
        $curlVersion = curl_version();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch,CURLOPT_USERAGENT,  "WXPaySDK/3.0.9 (".PHP_OS.") PHP/".PHP_VERSION." CURL/"  .$curlVersion['version']." ".$merchantId);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        payLog('wechat.txt',print_r(simplexml_load_string($response),true) . "99999");
//        libxml_disable_entity_loader(true);
        $response = json_decode(json_encode(simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
//        $response = simplexml_load_string($response);
        return array('code' => $httpCode, 'data' => $response);
    }


    public function getSign($data, $key)
    {
        $sign_str  = '';
        unset($data['sign']);
//        $data = array_filter($data);
        ksort($data);
       foreach($data as $k => $v) {
           if ($v === '') {
               continue;
           }
           $sign_str .= $k . "=" . $data[$k] . "&";
       }
       $sign_str .= "key=".$key;
        $strMd5 = md5($sign_str);

        return strtoupper($strMd5);
    }

}
