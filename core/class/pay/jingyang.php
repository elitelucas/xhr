<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class JingYangPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://pay.095pay.com/zfapi/order/pay';               //扫码正式（线上）调用接口
    public $bank_url = 'http://pay.095pay.com/zfapi/order/pay';  //第三方网银接口
    public $payName = '金阳支付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 225070,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'ok',  //回调处理成功时，返回接口字符串
        'bank_name' => '金阳支付',    //支付方式名称
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
//        payLog('jingy.txt','---195--');

        $orderInfo = $this->makeOrder($data);

        if (!$orderInfo) {
            $this->retArr['code'] = 225071;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 225072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        $post_data = array(
            'p1_mchtid'      => $config['merchantID'],
            'p2_paytype'      => $payStr,
            'p3_paymoney'      => number_format($data['money'], 2, '.', ''),
            'p4_orderno'      => $orderInfo,
            'p5_callbackurl'      => "https://".$callbackurl."/rechargeNotify.php",
            'p6_notifyurl'      => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn=" . $orderInfo,
            'p7_version'      => "v2.8",
            'p8_signtype'      => 1,
            'p9_attach'      => "jingyang_".$data['payment_id'],
            'p10_appname'      => "jingyang",
            'p11_isshow'      => 0,
            'p12_orderip'      => ip()
        );
        if ($payStr == 'FASTPAY') {
            $post_data['p13_memberid'] = $config['merchantID'];
        }
        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);

        payLog('jingy.txt',print_r($post_data,true)."===91==");

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {

            $curlData = $this->httpPost($post_data, $this->url);

            payLog('jingy.txt',print_r($curlData,true).'---84--');
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 225073;
                $this->retArr['msg']  = '支付调用失败！';
                payLog('jingy.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

                return $this->retArr;
            }
            $returnData = json_decode($curlData['data'], true);
            payLog('jingy.txt', print_r($returnData, true).'----113---');
            $right_return = $returnData['rspMsg'];

            if ($returnData['rspCode'] != 1) {
                $this->retArr['code'] = 225074;
                $this->retArr['msg']  = '支付接口调用失败';
                return $this->retArr;
            }

            if ($returnData['data']['r1_mchtid'] != $config['merchantID']) {
                $this->retArr['code'] = 225075;
                $this->retArr['msg']  = '返回商户错误';
                payLog('jingy.txt', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($returnData, true).'---99---');
                return $this->retArr;
            }


            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnData['data']['r6_qrcode']);
            session::set('pay_url', '');
            session::set('pay_url_type', 2);  //判断url是相应支付的二维码信息，还是二维码图片的连接地址，2：为连接地址，其他值为二维码信息
            //print_r($post_data);
            //type =2返回html跳转页面数
            $retData =  [
                'type'     => 1,
                'code_img' => $returnData['data']['r6_qrcode'],
                'code_url' => $returnData['data']['r6_qrcode'],
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

        payLog('jingy.txt',print_r($postData,true).'----157--');
        parse_str($postData['data'],$data);
        payLog('jingy.txt',print_r($data,true).'----149--');
        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 233020;
            payLog('jingy.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['orderstatus'] != 1) {
            $this->orderInfo['code'] = 233021;
            payLog('jingy.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['partner'] = $config['merchantID'];

        $retSign = $this->retSigned($data, $config['merchantKey']);
        payLog('jingy.txt',$retSign.'----195--');
        if(strtolower($retSign) != $data['sign']){
            $this->orderInfo['code'] = 233022;
            payLog('.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['ordernumber'];  //商户订单号
        $this->orderInfo['amount']    = $data['paymoney'];
        $this->orderInfo['serial_no'] = $data['sysnumber'];  //平台订单号

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
        curl_setopt($ch, CURLOPT_USERAGENT,
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.47 Safari/536.11');// 添加浏览器内核信息，解决403问题

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array('code' => $httpCode, 'data' => trim($response));
    }

    public function getSign($data, $key)
    {
        $signature = '';
        $sign_str = '';
//        ksort($data);
        foreach ($data as $k => $v) {
            $sign_str = $sign_str . $k. '=' . $v . '&';
        }
        $sign_str = rtrim($sign_str,'&');
        $sign_str = $sign_str.$key;
        return MD5($sign_str);
    }

    public function retSigned($data,$key)
    {
        $signature = sprintf(
            "partner=%s&ordernumber=%s&orderstatus=%s&paymoney=%s",
            $data['partner'], $data['ordernumber'], $data['orderstatus'], $data['paymoney']);
        return md5($signature.$key);
    }


}
