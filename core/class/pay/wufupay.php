<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class WuFuPay extends PayInfo
{
    //请求接口Url

    public $url = 'https://pay.8331vip.com/uniThirdPay';               //扫码正式（线上）调用接口
    public $h5_url = 'https://pay.8331vip.com/uniThirdPay';
    public $payName = '五福支付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 276000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '五福支付',    //支付方式名称
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
            $this->retArr['code'] = 276071;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 276072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = explode('.',$config['payType'][$data['pay_type']]['payStr'])[1];
        $tranType = explode('.',$config['payType'][$data['pay_type']]['payStr'])[0];


        $post_data = array(
            'svcName'      => $payStr,
            'merId'      => $config['merchantID'],
            'merchOrderId'      => $orderInfo,
            'tranType'      => $tranType,
            'pName'      => 'recharge',
            'amt'      => number_format($data['money'],2,'.','') * 100,
            'retUrl'      => "https://".$callbackurl. "/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,
            'notifyUrl'      => "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'showCashier'      => 1,
        );
        $post_data['md5value'] = $this->getSign($post_data, $config['merchantKey']);

        payLog('wufu.txt',print_r($post_data,true). "===89");

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {

            $curlData = $this->httpPost($post_data, $this->url);
            payLog('wufu.txt',print_r($curlData,true).'---84--');
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 276073;
                $this->retArr['msg']  = '支付调用失败！';
                payLog('wufu.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

                return $this->retArr;
            }
            $returnData = $curlData['data'];
            payLog('wufu.txt', print_r($returnData, true).'----113---');
            if (!empty($returnData['result_code'])) {
                $this->retArr['code'] = 276074;
                $this->retArr['msg']  = '支付接口调用失败';
                payLog('wufu.txt', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($returnData, true));

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

        payLog('wufu.txt',print_r($postData,true).'----157--');
        parse_str($postData['data'],$data);
        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);

        $config = unserialize($postData['config']);
        payLog('wufu.txt',print_r($data,true).'----160--');

        if (!is_array($config)) {
            $this->orderInfo['code'] = 276020;
            payLog('wufu.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['status'] != '0') {
            $this->orderInfo['code'] = 276021;
            payLog('wufu.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }


        //防止错传
//        $data['merId'] = $config['merchantID'];
        $sign = $data['md5value'];
        unset($data['md5value']);

        $retSign = $this->getSign($data, $config['merchantKey']);
        payLog('wufu.txt',$retSign.'----195--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 276022;
            payLog('wufu.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }


        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['merchOrderId'];  //商户订单号
        $this->orderInfo['amount']    = $data['amt'] /100;
        $this->orderInfo['serial_no'] = $data['orderId'];  //平台订单号

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

//        payLog('wufu.txt',print_r($html,true). "  ==220== ");
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
        payLog('wufu.txt',print_r($response,true). "+++251");
//        $response = json_decode(json_encode(simplexml_load_string($response)),true);

        return array('code' => $httpCode, 'data' => $response);
    }

    public function getSign($data, $key)
    {
        $sign_str = '';
        ksort($data);
//        array_filter($data);
        foreach ($data as $k => $v) {
            if ($v === '') {
                continue;
            }
            $sign_str = $sign_str .  $v;
        }
        $sign_str .= $key;
        payLog('wufu.txt',$sign_str. "+++++++271");

        return strtoupper(md5($sign_str));
    }


}
