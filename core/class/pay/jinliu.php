<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class JinLiuPay extends PayInfo
{
    //请求接口Url
    public $url = 'http://kuaifuvip.com/GateWay/ReceiveBank.aspx';       //扫码正式（线上）调用接口
    public $bank_url = 'http://kuaifuvip.com/GateWay/ReceiveBank.aspx';  //第三方网银接口
    public $payName = '金流支付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 226050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'success',  //回调处理成功时，返回接口字符串
        'bank_name' => '金流支付',       //支付方式名称
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
            $this->retArr['code'] = 264001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 264001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'p0_Cmd'    => 'Buy',
            'p1_MerId'    => $config['merchantID'],
            'p2_Order'    => $orderInfo,
            'p3_Amt'    => number_format($data['money'], 2, '.', ''),
            'p4_Cur'    => 'CNY',
            'p8_Url'    => "https://".$_SERVER['HTTP_HOST'] . '/rechargeNotify.php',
            'p9_SAF'    => '0',
            'pa_MP'    => 'jinliu_'. $data['payment_id'],
            'pd_FrpId'    => $config['payType'][$data['pay_type']]['payStr'],
            'pr_NeedResponse'    => '1',
        );

        $post_data['hmac'] = $this->getSign($post_data, $config['merchantKey']);
        payLog('jinliu.txt',print_r($post_data,true).' --87--');

        $retData =  [
            'type'  => $config['payType'][$data['pay_type']]['request_type'],
            'modes' => $data['pay_model'],
            'html'  => $this->httpHtml($post_data, $this->bank_url)
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
        payLog('jinliu.txt',print_r($postData,true).'----149--');
        parse_str($postData['data'],$data);
        payLog('jinliu.txt',print_r($postData,true).'----150--');
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 264005;
            payLog('jinliu.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($postData, true));

            return $this->orderInfo;
        }

        $sign = $data['hmac'];
        $data = array(
            'p1_MerId' => $config['merchantID'],
            'r0_Cmd' => $data['r0_Cmd'],
            'r1_Code' => $data['r1_Code'],
            'r2_TrxId' => $data['r2_TrxId'],
            'r3_Amt' => $data['r3_Amt'],
            'r4_Cur' => $data['r4_Cur'],
            'r5_Pid' => $data['r5_Pid'],
            'r6_Order' => $data['r6_Order'],
            'r7_Uid' => $data['r7_Uid'],
            'r8_MP' => $data['r8_MP'],
            'r9_BType' => $data['r9_BType'],
        );

        if ($data['r1_Code'] != 1 ) {
            $this->orderInfo['code'] = 264005;
            payLog('jinliu.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,返回状态码错误！'  . print_r($postData, true));

            return $this->orderInfo;
        }

        $retSign = $this->getSign($data, $config['merchantKey']);
        payLog('jinliu.txt',$retSign.'  ---173--  '.$sign );
        if($retSign != $sign){
            $this->orderInfo['code'] = 264007;
            payLog('jinliu.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;

        $this->orderInfo['order_no']  = $data['r6_Order'];
        $this->orderInfo['amount']    = $data['r3_Amt'];
        $this->orderInfo['serial_no'] = $data['r2_TrxId'];

        return $this->orderInfo;
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

        return $html;
    }

    /**
     * 生成签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    public function getSign($data, $key)
    {
        $sign_str  = '';
        $data = array_filter($data);
        ksort($data);
        foreach($data as $k => $v) {
            $sign_str .=  $v;
        }
//        $sign_str .= $key;

        payLog('jinliu.txt', $sign_str. '   --242');
        return $this->HmacMd5($sign_str,$key);
    }



    /**
     * 验证生成签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    public function HmacMd5($data,$key)
    {
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