<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class XiRongPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://124.232.150.87:11088/webservice/order';                 //扫码测试调用接口

    public $bank_url = 'http://124.232.150.87:11088/webservice/order';  //第三方网银接口

    public $payName = '熙融';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 257000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'success',  //回调处理成功时，返回接口字符串
        'bank_name' => '熙融',    //支付方式名称
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
            $this->retArr['code'] = 257001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 257002;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        $payInfo = explode('_',$payStr);
        $childType = $payInfo[0];
        $code = $payInfo[1];

        $post_data = array(
            'txcode'    => 'F60002',
            'txdate'    => strval(date("Ymd",time())),
            'txtime'    => strval(date("His",time())),
            'version'    => '2.0.0',
            'field003'    => strval($code),
            'field004'    => strval(number_format($data['money'], 2, '.', '') * 100),
            'field031'    => strval($childType),
            'field035'    => $_SERVER['REMOTE_ADDR'],
            'field036'    => "https://".$_SERVER['HTTP_HOST'] ,
            'field041'    => strval($config['partnerId']),
            'field042'    => strval($config['merchantID']),
            'field048'    => $orderInfo,
            'field057'    => 'recharge',
            'field060'    => 'https://'.$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=".$data['payment_id'],
            'field125'    => $config['merchantID'] . rand(10000000,99999999),
        );
        $post_data['field011'] = '000000';

//        if ($childType == "26065") {
//            if ($entrance == 8 || $entrance == 84) {
//                $post_data['field011'] = '000001';
//            } elseif ($entrance == 9) {
//                $post_data['field011'] = '000002';
//            } else {
//                $post_data['field011'] = '000000';
//            }
//        } else {
//            $post_data['field011'] = '000000';
//        }


        $post_data['sign'] = substr($this->getSign($post_data, $config['merchantKey']),0, 16);
        $post_data = json_encode($post_data);
        $post_data = str_replace("\\/",'/',$post_data);

        $curlData = $this->httpPost($post_data, $this->url);
        payLog('xirong.txt',print_r($curlData,true) . ' --96--');
        //接口调用成功与否
        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 257003;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

            return $this->retArr;
        }
        $returnInfo = json_decode($curlData['data'],true);
        payLog('xirong.txt',print_r($returnInfo,true).'---97--');
        if ($returnInfo['field039'] != '00') {
            $this->retArr['code'] = 257004;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($returnInfo, true));
            return $this->retArr;
        }

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {


            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnInfo['field055']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $returnInfo['field055'],
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
                'html'  => $this->httpHtml(array(),$returnInfo['redirectUrl'])
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
        payLog('xirong.txt',print_r($postData,true).'----149--');
        parse_str($postData['data'],$postInfo);
        payLog('xirong.txt',print_r($postInfo,true).'----149--');
        $payment_id = $postData['payment_id'];
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 257005;
            payLog('xirong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($postInfo, true));

            return $this->orderInfo;
        }

        $data = explode('|',$postInfo['field055']);

        if ($data[1] != '00') {
            $this->orderInfo['code'] = 257008;
            payLog('xirong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }


        //防止错传
//        $data['payKey'] = $config['merchantID'];
        $sign = $postInfo['field128'];
        unset($postInfo['field128']);

        $retSign = substr($this->getSign($postInfo, $config['merchantKey']),0,16);
        payLog('xirong.txt',$retSign.'---173--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 257007;
            payLog('xirong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data[0];
        $this->orderInfo['amount']    = $data[3] /100;
        $this->orderInfo['serial_no'] = $data['field062'];

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
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type: application/json; charset=utf-8','Content-Length:' . strlen($data)));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        $response = simplexml_load_string($response);
        return array('code' => $httpCode, 'data' => $response);
    }

    public function getSign($data, $key)
    {
        $sign_str  = '';
        $sign_str = $data['txcode'].$data['txdate'].$data['txtime'].$data['version'];
        unset($data['txcode']);
        unset($data['txdate']);
        unset($data['txtime']);
        unset($data['version']);
        $data = array_filter($data);
        ksort($data);
        foreach($data as $k => $v) {
            $sign_str .= $data[$k];
        }
        $sign_str .= $key;
        payLog('xirong.txt', $sign_str . '---330--');
        $strMd5 = md5($sign_str);

        return strtoupper($strMd5);
    }

}
