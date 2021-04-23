<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class jiefutongPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://www.jftong5.com/pay';
//    public $url = 'https://gateway.pay898.com/pay/ImgCodePage.aspx';
    public $payName = '捷付通';   //接口名称

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
        'bank_name' => '捷付通',    //支付方式名称
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
            payLog('jiefutong.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 277072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('jiefutong.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        $payCode = $config['payType'][$data['pay_type']]['payCode'];

        $post_data = array(
            'fxid' => $config['merchantID'],
            'fxddh'      => $orderInfo,
            'fxdesc'      => 'desc',
            'fxfee'      => number_format($data['money'],2,'.',''),
            'fxattch'      => 'fxattch',
            'fxnotifyurl'      => "https://".$callbackurl."/rechargeNotify.php",
            'fxbackurl'      => "https://".$_SERVER['HTTP_HOST'],
            'fxpay'      => $payStr,
            'payCode'      => $payCode,
            'fxattch'      => 'jiefutong_'.$data['payment_id'],
            'fxip'      => ip(),
        );

        $post_data['fxsign'] = $this->getSign($post_data, $config['merchantKey']);

        payLog('jiefutong.txt',print_r($post_data,true).'----post_data');

        $curlData = $this->httpPost($post_data, $this->url);
        if(!$curlData || $curlData['code'] != 200) {
            payLog('jiefutong.txt',print_r($curlData,true).'--curl_request_err--');
            $this->retArr['code'] = 222001;
            $this->retArr['msg']  = '请求失败';
            return $this->retArr;
        }
        $resData = json_decode($curlData['data'], true);
        if(!$resData) {         //解码失败
            payLog('jiefutong.txt',print_r($resData,true).'--curl_data_decode_error--');
            $this->retArr['code'] = 222001;
            $this->retArr['msg']  = $resData;
            return $this->retArr;
        }
        payLog('jiefutong.txt',print_r($resData,true).'--curl_data_decode--');
        if($resData['status'] == 1) {
            if ($config['payType'][$data['pay_type']]['request_type'] == 1) {
                $retData =  [
                    'type'     => 1,
                    'code_img' => $resData['payimg'],
                    'code_url' => $resData['payimg'],
                    'pay_url'  => $resData['payurl'],
                    'order_no' => $orderInfo,
                ];
                $this->retArr['code'] = 0;
                $this->retArr['data']  = $retData;

                return $this->retArr;
            }else {
                $payHtml = $this->httpHtml([], $resData['payurl']);
                $retData =  [
                    'type'  => $config['payType'][$data['pay_type']]['request_type'],
                    'modes' => $data['pay_model'],
                    'html'  => $payHtml
                ];
                $this->retArr['code'] = 0;
                $this->retArr['data']  = $retData;
                return $this->retArr;
            }
        }else {
            $this->retArr['code'] = 222001;
            $this->retArr['msg']  = $resData['error'];
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

        payLog('jiefutong.txt',print_r($postData,true).'----157--');

        parse_str($postData['data'],$data);
        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);

        $config = unserialize($postData['config']);
        payLog('jiefutong.txt',print_r($data,true).'----160--');

        if (!is_array($config)) {
            $this->orderInfo['code'] = 277020;
            payLog('jiefutong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['fxstatus'] != 1) {
            $this->orderInfo['code'] = 277021;
            payLog('jiefutong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));
            return $this->orderInfo;
        }

        $sign = $data['fxsign'];

        $retSign = $this->getCallBackSign($data, $config['merchantKey']);

        payLog('jiefutong.txt',$retSign.'----195--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 277022;
            payLog('jiefutong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['fxddh'];  //商户订单号
        $this->orderInfo['amount']    = $data['fxfee'];
        $this->orderInfo['serial_no'] = $data['fxddh'];  //平台订单号

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

        $user_agent = $_SERVER ['HTTP_USER_AGENT'];
        $header = array(
            "User-Agent: $user_agent"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
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
        return md5($data["fxid"] . $data["fxddh"] . $data["fxfee"] . $data["fxnotifyurl"] . $key); //加密
    }

    public function getCallBackSign($data, $key) {
        return md5($data["fxstatus"] . $data["fxid"] . $data["fxddh"] . $data["fxfee"] . $key); //加密
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
