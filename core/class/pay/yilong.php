<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class yilong extends PayInfo
{
    //请求接口Url

    public $url = 'http://alipay.elongpay.com/service/alipay3receiv
e.aspx';               //扫码正式（线上）调用接口
    public $wy_url = 'https://elongpay.com/trade/creat';
    public $payName = '亿龙支付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 277000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'success',  //回调处理成功时，返回接口字符串
        'bank_name' => '亿龙支付',    //支付方式名称
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
            $this->retArr['code'] = 277071;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 277072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];
       $reqtype = $config['payType'][$data['pay_type']]['request_type'];
        $reqtype=2?$reqtype=0:$reqtype=1;
        $post_data = array(
            'goods_name'      => 'recharge',
            'version'      => '1.0.0',
            'request_time'      => date("Y-m-d H:i:s",time()),
            'appid'      => $config['merchantID'],
            'out_trade_no'      => $orderInfo,
            'total_amount'      => number_format($data['money'],2,'.',''),
            'channel_code'      => $payStr,
            'device'      => strval($reqtype),
            'return_url'      => "https://".$callbackurl,
//            'callbackUrl'      => "https://".$callbackurl,
//            'notify_url'      => "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'notify_url'      => "https://".$callbackurl."/rechargeNotify.php",
        );
        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        $post_data['sign_type'] = "MD5";

        payLog('yilong.txt',print_r($post_data,true).$this->url);


        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {

            $curlData = $this->httpPost($post_data, $this->url);
            payLog('yilong.txt',print_r($curlData,true));
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 277073;
                $this->retArr['msg']  = '支付调用失败！';
                payLog('yilong.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

                return $this->retArr;
            }
            $returnData = json_decode($curlData['data'],true);
//        payLog('yilong.txt', print_r($returnData, true).'----113---');

            if ($returnData['status'] != "0000") {
                $this->retArr['code'] = 277074;
                $this->retArr['msg']  = '支付接口调用失败';
                payLog('yilong.txt', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($returnData, true));

                return $this->retArr;
            }
            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnData['url']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $returnData['url'],
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
//            payLog('yilong.txt',$url. "   ====137");
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

        payLog('yilong.txt',print_r($postData['data'],true).'----157--');
        parse_str($postData['data'],$data);
        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);

        $config = unserialize($postData['config']);
        payLog('yilong.txt',print_r($data,true).'----160--');

        if (!is_array($config)) {
            $this->orderInfo['code'] = 277020;
            payLog('yilong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['status'] != "1000") {
            $this->orderInfo['code'] = 277021;
            payLog('yilong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
//        $data['merchantNo'] = $config['merchantID'];
        $sign = $data['sign'];
        unset($data['sign']);

        $retSign = $this->getSign($data, $config['merchantKey']);
        payLog('yilong.txt',$retSign.'----195--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 277022;
            payLog('yilong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['out_trade_no'];  //商户订单号
        $this->orderInfo['amount']    = $data['total_amount'];
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
        $html .= '<meta http-equiv="Content-Type" content-type="application/json; charset=UTF-8" >';
        $html .= '</head>';
        $html .= '<body onLoad="document.dinpayForm.submit();">';
        $html .= '<form id="payFrom" name="dinpayForm" method="post" action="' . $url . '">';
        foreach ($post_data as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        }
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';

//        payLog('yilong.txt',print_r($html,true). "  ==220== ");
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        payLog('yilong.txt',print_r($response,true). "+++251");
//        $response = json_decode(json_encode(simplexml_load_string($response)),true);

        return array('code' => $httpCode, 'data' => $response);
    }

    public function getSign($data, $key)
    {
        $sign_str = '';
        ksort($data);
        foreach ($data as $k => $v) {
            if ($v == '') {
                continue;
            }
            $sign_str .= $k . "=".$v."&";
        }
//        $sign_str = sprintf("notifyUrl=%s&orderIp=%s&orderPrice=%s&orderTime=%s&outTradeNo=%s&payKey=%s&productName=%s&productType=%s&remark=%s&returnUrl=%s",
//            $data['notifyUrl'],$data['orderIp'],$data['orderPrice'],$data['orderTime'],$data['outTradeNo'],$data['payKey'],$data['productName'],$data['productType'],$data['remark'],$data['returnUrl']);
        $sign_str .= "key=".$key;

        return strtoupper(md5($sign_str));
    }


}
