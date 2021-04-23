<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class GuangFuPay extends PayInfo
{
    //请求接口Url

    public $url = 'https://api.chinalightpay.com/gateway/api/scanpay';               //扫码正式（线上）调用接口
    public $bank_url = 'https://api.chinalightpay.com/gateway/api/scanpay';  //第三方网银接口
    public $payName = '光付支付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 267000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'ok',  //回调处理成功时，返回接口字符串
        'bank_name' => '光付支付',    //支付方式名称
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
            $this->retArr['code'] = 267071;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 267072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];


        $post_data = array(
            'merchant_code'      => $config['merchantID'],
            'service_type'      => $payStr,
            'notify_url'      => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'interface_version'      => 'V3.1',
            'client_ip'      => ip(),
            'order_no'      => $orderInfo,
            'order_time'      => date("y-m-d H:i:s",time()),
            'order_amount'      => number_format($data['money'],2,'.',''),
            'product_name'      => "recharge",
        );
        $post_data['sign'] = $this->getSign($post_data, $config['merchantPrivateKey']);
        $post_data['sign_type'] = 'RSA-S';
        payLog('baiqian.txt',print_r($post_data,true).'---89--');

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {
            $curlData = $this->httpPost($post_data, $this->url);


            payLog('baiqian.txt',print_r($curlData,true).'---84--');
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 267073;
                $this->retArr['msg']  = '支付调用失败！';
                payLog('baiqian.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

                return $this->retArr;
            }
            $returnData = json_decode($curlData['data'], true);
            payLog('baiqian.txt', print_r($returnData, true).'----113---');

            if ($returnData['resp_code'] != 'SUCCESS') {
                $this->retArr['code'] = 267074;
                $this->retArr['msg']  = '支付接口调用失败';
                payLog('baiqian.txt', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($returnData, true));

                return $this->retArr;
            }

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnData['qrcode']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $returnData['qrcode'],
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $this->bank_url)
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

        payLog('baiqian.txt',print_r($postData,true).'----157--');
        parse_str($postData['data'],$data);
        payLog('baiqian.txt',print_r($data,true).'----149--');
        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 267020;
            payLog('baiqian.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['trade_status'] != 'SUCCESS') {
            $this->orderInfo['code'] = 267021;
            payLog('baiqian.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchant_code'] = $config['merchantID'];
        $sign_type = $data['sign_type'];
        $sign = $data['sign'];
        unset($data['sign_type']);
        unset($data['sign']);


        $retSign = $this->retSigned($data, $config['merchantKey']);
        payLog('baiqian.txt',$retSign.'----195--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 267022;
            payLog('baiqian.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['order_no'];  //商户订单号
        $this->orderInfo['amount']    = $data['order_amount'];
        $this->orderInfo['serial_no'] = $data['trade_no'];  //平台订单号

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
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
//        curl_setopt($ch, CURLOPT_USERAGENT,
//            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.47 Safari/536.11');// 添加浏览器内核信息，解决403问题

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response = xmlToArray($response);

        return array('code' => $httpCode, 'data' => trim($response));
    }

    public function getSign($data, $key)
    {
        $sign_str = '';
        array_filter($data);
        ksort($data);
        reset($data);
        foreach ($data as $k => $v) {
            $sign_str = $sign_str . $k. '=' . $v . '&';
        }
        $sign_str = rtrim($sign_str,"&");

        $key = openssl_get_privatekey($key);
        openssl_sign($sign_str,$sign_info,$key,OPENSSL_ALGO_MD5);
        $sign = base64_encode($sign_info);
//        payLog('baiqian.txt',print_r($sign_str,true). '   ====263==  ');
        return $sign;
    }


    public function retSigned($data,$key)
    {
        $signature = sprintf(
            "Amount=%s&BillNo=%s&MerNo=%s&Succeed=%s",
            $data['Amount'], $data['BillNo'], $data['MerNo'], $data['Succeed']);
        $signature .= "&".strtoupper(md5($key));
        return strtoupper(md5($signature));
    }


}
