<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class MingJie extends PayInfo
{
    //请求接口Url

    public $url = 'http://39.105.8.4:9803/api/pay.action';               //扫码正式（线上）调用接口
    public $h5_url = 'http://39.105.8.4:9803/api/pay.action';
    public $payName = '明捷支付';   //接口名称


    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 275000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '明捷支付',    //支付方式名称
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
            $this->retArr['code'] = 275071;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 275072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];


        $post_data = array(
            'version'      => 'V3.0.0.0',
            'merchNo'      => $config['merchantID'],
            'netwayCode'      => $payStr,
            'randomNum'      => $this->getRandomStr(8),
            'orderNum'      => $orderInfo,
            'amount'      => number_format($data['money'],2,'.','') *100,
            'goodsName'      => 'recharge',
            'charset'      => 'UTF-8',
//            'pay_notifyurl'          => "https://".$callbackurl."/rechargeNotify.php",
            'callBackUrl'          => "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'callBackViewUrl'      => "https://".$callbackurl."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,
        );
        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        $json = json_encode($post_data,JSON_UNESCAPED_UNICODE);
        $dataStr = $this->encode_pay($json,$config['merchantPublicKey']);

        payLog('mingjie.txt',print_r($post_data,true));
        $param = 'data=' . urlencode($dataStr) . '&merchNo=' . $config['merchantID'] . '&version=V3.0.0.0';
        $curlData = $this->httpPost($param, $this->url);
        payLog('mingjie.txt',print_r($curlData,true).'---84--');
        //接口调用成功与否
        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 275073;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('mingjie.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

            return $this->retArr;
        }
        $returnData = json_decode($curlData['data'],true);

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {



            payLog('mingjie.txt', print_r($returnData, true).'----113---');
            if ($returnData['stateCode'] != '00') {
                $this->retArr['code'] = 275074;
                $this->retArr['msg']  = '支付接口调用失败';
                payLog('mingjie.txt', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($returnData, true));

                return $this->retArr;
            }

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnData['qrcodeUrl']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $returnData['qrcodeUrl'],
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
//            payLog('mingjie.txt',$url. "   ====137");
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $this->url)
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

        payLog('mingjie.txt',print_r($postData,true).'----157--');
        parse_str($postData['data'],$dataRequest);
        $data = $dataRequest['data'];
        $data = urldecode($data);

        $config = unserialize($postData['config']);
        $data = $this->retDecode($data,$config['merchantPrivateKey']);

        payLog('mingjie.txt',print_r($data,true).'----160--');

        if (!is_array($config)) {
            $this->orderInfo['code'] = 275020;
            payLog('mingjie.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['payStateCode'] != "00") {
            $this->orderInfo['code'] = 275021;
            payLog('mingjie.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchNo'] = $config['merchantID'];
        $sign = $data['sign'];
        unset($data['sign']);
        $retSign = $this->getSign($data, $config['merchantKey']);
        payLog('mingjie.txt',$retSign.'----195--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 275022;
            payLog('mingjie.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }


        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['orderNum'];  //商户订单号
        $this->orderInfo['amount']    = $data['amount'] / 100;
        $this->orderInfo['serial_no'] = $data['orderNum'];  //平台订单号

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

//        payLog('mingjie.txt',print_r($html,true). "  ==220== ");
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
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        payLog('mingjie.txt',print_r($response,true). "+++251".$url);
//        $response = json_decode(json_encode(simplexml_load_string($response)),true);

        return array('code' => $httpCode, 'data' => $response);
    }

    public function getSign($data, $key)
    {
        $sign_str = '';
        ksort($data);
        if (version_compare(PHP_VERSION,'5.4.0','<')){
            $str = json_encode($data);
            $str = preg_replace_callback("#\\\u([0-9a-f]{4})#i","replace_unicode_escape_sequence",$str);
            $sign_str = stripslashes($str);
        }else{
            $sign_str = json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        $sign_str .= $key;
        payLog('mingjie.txt',$sign_str. "+++++++271");

        return strtoupper(md5($sign_str));
    }

    public function encode_pay($data,$key)
    {
        $pu_key = openssl_pkey_get_public($key);
        $encryptData = '';
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $pu_key);
            $crypto = $crypto . $encryptData;
        }

        $crypto = base64_encode($crypto);
        return $crypto;
    }

    public function retDecode($data,$key)
    {
        $pr_key = openssl_get_privatekey($key);
        $data = base64_decode($data);

        payLog("mingjie.txt",print_r($pr_key,true). "---296---".print_r($data,true));
        $crypto = '';
        foreach (str_split($data, 128) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $pr_key);
            $crypto .= $decryptData;
        }

        payLog('mingjie.txt',print_r($crypto,true). "===305");
        $crypto = json_decode($crypto,true);
        payLog('mingjie.txt',print_r($crypto,true). "===308");
        return $crypto;
    }


}
