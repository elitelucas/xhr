<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class ruiChengPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://rc.1ds9.cn/apisubmit';

    public $payName = '瑞成';   //接口名称

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
        'bank_name' => '瑞成',    //支付方式名称
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
        $data['user_id'] = 'ruicheng_'.$data['payment_id'].'_'.$data['user_id'];
        //生成订单

        $orderInfo = $this->makeOrder($data);

        if (!$orderInfo) {
            $this->retArr['code'] = 277071;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('yifa.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 277072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('yifa.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        $post_data = array(
            'version' => '1.0',
            'customerid' => $config['merchantID'],
            'sdorderno' => $orderInfo,
            'total_fee'      => number_format($data['money'],2,'.',''),
            'paytype'      => $payStr,
            'notifyurl'      => "https://".$callbackurl."/rechargeNotify.php",
            'returnurl'      => "https://".$callbackurl."/beeePayOk.php",
            'remark'      => $data['user_id'],
        );

        $post_data['sign'] = $this->getSign($post_data, $config['merchantPrivateKey']);

        payLog('ruicheng.txt',print_r($post_data,true).'----post_data');

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {

            return;
            //type =2返回html跳转页面数
            $curlData = $this->httpPost($post_data, $this->url);
            payLog('ruicheng.txt',print_r($curlData,true).'----curl_return');

            $resData = json_decode($curlData['data'], true);

            if($curlData['code'] != 200) {
                $this->retArr['code'] = 277074;
                $this->retArr['msg']  = $resData['message'];
                return $this->retArr;
            }

            $resData['shop_id'] = $config['merchantID'];
            $resData['user_id'] = $data['user_id'];
            $checkSign = $this->getCheckSign($resData, $config['merchantPrivateKey']);
            if($checkSign !== $resData['sign']) {
                $this->retArr['code'] = 277074;
                $this->retArr['msg']  = '验签错误!';
                return $this->retArr;
            }

            $retData =  [
                'type'     => 1,
                'code_url' => $resData['qrcode_url'],
                'pay_url'  => $resData['pay_url'],
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {

            $payHtml = $this->httpHtml($post_data, $this->url);
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
        payLog('ruicheng.txt',print_r($postData,true).'----157--');

        parse_str($postData['data'],$data);
        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);

        $config = unserialize($postData['config']);
        payLog('ruicheng.txt',print_r($data,true).'----160--');

        if (!is_array($config)) {
            $this->orderInfo['code'] = 277020;
            payLog('ruicheng.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
            return $this->orderInfo;
        }

        if ($data['status'] != 1) {
            $this->orderInfo['code'] = 277021;
            payLog('ruicheng.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));
            return $this->orderInfo;
        }

        $data['customerid'] = $config['merchantID'];
        $checkSign = $this->getNotifyCheckSign($data, $config['merchantPrivateKey']);
        payLog('ruicheng.txt',$checkSign.'----195--');
        if($checkSign !== $data['sign']) {
            $this->orderInfo['code'] = 277022;
            payLog('ruicheng.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['sdorderno'];  //商户订单号
        $this->orderInfo['amount']    = $data['total_fee'];
        $this->orderInfo['serial_no'] = $data['sdpayno'];  //平台订单号
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
        $data = urldecode(json_encode($data));

        payLog('yifa.txt',print_r($data,true).'----post_data_json');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8','Content-Length:' . strlen($data)));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        payLog('yifa.txt',print_r($response,true).'----curl_response');
//        payLog('paytong.txt',print_r($response,true). "+++251");
//        $response = json_decode(json_encode(simplexml_load_string($response)),true);

        return array('code' => $httpCode, 'data' => $response);
    }

    private function getNotifyCheckSign($data, $key) {
        $arr = ['customerid', 'status', 'sdpayno', 'sdorderno', 'total_fee', 'paytype'];
        $s = '';
        foreach ($arr as  $k) {
            $s .= $k.'='.$data[$k].'&';
        }
        $s .= $key;
        return md5($s);
    }

    private function getCheckSign($data, $key) {
        $arr = ['customerid', 'status', 'sdpayno', 'sdorderno', 'total_fee', 'paytype'];
        $s = '';
        foreach ($arr as  $k) {
            $s .= $k.'='.$data[$k].'&';
        }
        $s .= $key;
        return md5($s);
    }

    public function getSign($data, $key)
    {
        $arr = ['version', 'customerid', 'total_fee', 'sdorderno', 'notifyurl', 'returnurl'];
        $s = '';
        foreach ($arr as  $k) {
            $s .= $k.'='.$data[$k].'&';
        }
        $s .= $key;
        return md5($s);
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
