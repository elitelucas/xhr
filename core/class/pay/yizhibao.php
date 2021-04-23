<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class yiZhiBaoPay extends PayInfo
{
    //请求接口Url

    public $url = 'https://gateway.pay898.com/GateWay/ReceiveBank.aspx';
//    public $url = 'https://gateway.pay898.com/pay/ImgCodePage.aspx';
    public $payName = '易支宝';   //接口名称

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
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '易支宝',    //支付方式名称
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
            payLog('yizhibao.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 277072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('yizhibao.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];

        $post_data = array(
            'p0_Cmd' => 'Buy',
            'p1_MerId' => $config['merchantID'],
            'p2_Order'      => $orderInfo,
            'p3_Amt'      => number_format($data['money'],2,'.',''),
            'p4_Cur'      => 'CNY',
            'p5_Pid'      => 'productname',
            'p6_Pcat'      => 'producttype',
            'p7_Pdesc'      => 'productdesc',
            'p8_Url'      => "https://".$callbackurl."/rechargeNotify.php",
            'p9_SAF'      => 0,
            'pa_MP'      => 'yizhibao_'.$data['payment_id'],
            'pd_FrpId'      => 'alipaywap',
            'pr_NeedResponse'      => 1,
        );

        $post_data['hmac'] = $this->getSign($post_data, $config['merchantPrivateKey']);

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {
            return;         //暂无

            $curlData = $this->httpPost($post_data, $this->url);
            payLog('paytong.txt',print_r($curlData,true));
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 277073;
                $this->retArr['msg']  = '支付调用失败！';
                payLog('paytong.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

                return $this->retArr;
            }
            $returnData = json_decode($curlData['data'],true);
//        payLog('paytong.txt', print_r($returnData, true).'----113---');

            if ($returnData['resultCode'] != "0000") {
                $this->retArr['code'] = 277074;
                $this->retArr['msg']  = '支付接口调用失败';
                payLog('paytong.txt', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($returnData, true));

                return $this->retArr;
            }
            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnData['payMsg']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $returnData['payMsg'],
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            //type =2返回html跳转页面数

            $payHtml = $this->httpHtml($post_data, $this->url);

            payLog('yizhibao.txt',$payHtml. "   ====137");
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $payHtml
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

        payLog('yizhibao.txt',print_r($postData,true).'----157--');

        parse_str($postData['data'],$data);
        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);

        $config = unserialize($postData['config']);
        payLog('yizhibao.txt',print_r($data,true).'----160--');

        if (!is_array($config)) {
            $this->orderInfo['code'] = 277020;
            payLog('yizhibao.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['r1_Code'] != 1) {
            $this->orderInfo['code'] = 277021;
            payLog('yizhibao.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));
            return $this->orderInfo;
        }

        $sign = $data['hmac'];
        unset($data['hmac']);           //签名不参与
        unset($data['rp_PayDate']);     //支付时间不参与签名

        $data['r3_Amt'] = number_format($data['r3_Amt'],2,'.','');

        $retSign = $this->getSign($data, $config['merchantPrivateKey']);

        payLog('yizhibao.txt',$retSign.'----195--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 277022;
            payLog('yizhibao.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['r6_Order'];  //商户订单号
        $this->orderInfo['amount']    = $data['r3_Amt'];
        $this->orderInfo['serial_no'] = $data['r2_TrxId'];  //平台订单号

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
        $html .= '</head>';
        $html .= '<body onLoad="document.API.submit();">';
        $html .= '<form name="API" method="post" action="' . $url . '">';
        foreach ($post_data as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        }
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';

//        payLog('paytong.txt',print_r($html,true). "  ==220== ");
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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        payLog('paytong.txt',print_r($response,true). "+++251");
//        $response = json_decode(json_encode(simplexml_load_string($response)),true);

        return array('code' => $httpCode, 'data' => $response);
    }

    public function getSign($data, $key)
    {
        $sbOld = implode('', $data);
//        $sbOld = "";
//        $sbOld .= $data['p0_Cmd'];
//        $sbOld .= $data['p1_MerId'];
//        $sbOld .= $data['p2_Order'];
//        $sbOld .= $data['p3_Amt'];
//        $sbOld .= $data['p4_Cur'];
//        $sbOld .= $data['p5_Pid'];
//        $sbOld .= $data['p6_Pcat'];
//        $sbOld .= $data['p7_Pdesc'];
//        $sbOld .= $data['p8_Url'];
//        $sbOld .= $data['p9_SAF'];
//        $sbOld .= $data['pa_MP'];
//        $sbOld .= $data['pd_FrpId'];
//        $sbOld .= $data['pr_NeedResponse'];
        return $this->HmacMd5($sbOld, $key);

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
        $sign_str .= "paySecret=".$key;
        payLog('paytong.txt',$sign_str. "+++++++271");

        return strtoupper(md5($sign_str));
    }


    public function HmacMd5($data,$key)
    {
// RFC 2104 HMAC implementation for php.
// Creates an md5 HMAC.
// Eliminates the need to install mhash to compute a HMAC
// Hacked by Lance Rushing(NOTE: Hacked means written)

//需要配置环境支持iconv，否则中文参数不能正常处理
        $key = iconv("GB2312","UTF-8",$key);
        $data = iconv("GB2312","UTF-8",$data);

        $b = 64; // byte length for md5
        if (strlen($key) > $b) {
            $key = pack("H*",md5($key));
        }
        $key = str_pad($key, $b, chr(0x00));
        $ipad = str_pad('', $b, chr(0x36));
        $opad = str_pad('', $b, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;

        return md5($k_opad . pack("H*",md5($k_ipad . $data)));
    }

}
