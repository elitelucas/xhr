<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class BaiShengPay extends PayInfo
{
    //请求接口Url

    public $url = 'https://ebank.baishengpay.com/Payment/Gateway';               //扫码正式（线上）调用接口
    public $bank_url = 'https://ebank.baishengpay.com/Payment/Gateway';  //第三方网银接口
    public $payName = '百盛';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 239000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '百盛支付',    //支付方式名称
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
        payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        if (!$orderInfo) {
            $this->retArr['code'] = 239001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 239002;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'MerchantId'      => $config['merchantID'], // 支付系统分配给商户的账号
            'Timestamp'      => date("Y-m-d H:i:s"),
            'PaymentTypeCode'      => $config['payType'][$data['pay_type']]['payStr'],
            'OutPaymentNo'      => $orderInfo,
            'PaymentAmount'      => $data['money']*100,
            'NotifyUrl'      => "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],  //商户通知地址
            'PassbackParams'      => "recharge",
        );
        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        payLog('baisheng.txt',print_r($post_data,true).'----80--');

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {
            $curlData = $this->httpPost($post_data, $this->url);
            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 239003;
                $this->retArr['msg']  = '付支付接口调用失败';
                payLog('baisheng.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，--89--' . print_r($curlData, true).'--101--');

                return $this->retArr;
            }

            $retData = json_decode($curlData['data'], true);
            if ($retData['Code'] != 200) {
                $this->retArr['code'] = 239004;
                $this->retArr['msg']  = $retData['Message'];
                payLog('baisheng.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，--98--' . print_r($retData, true).'--94--');

                return $this->retArr;
            }

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $retData['QrCodeUrl']);
            session::set('pay_url', $retData['QrCodeUrl']);

            $ret =  [
                'type'     => 1,
                'code_url' => $retData['QrCodeUrl'],
                'pay_url'  => $retData['QrCodeUrl'],
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data'] = $ret;

            return $this->retArr;
        } elseif ($config['payType'][$data['pay_type']]['request_type'] == 2) {
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
        //处理post回调数据
        payLog('baisheng.txt',print_r($postData,true).'----157--');
        parse_str($postData['data'],$data);
        payLog('baisheng.txt',print_r($data,true).'----149--');


//        parse_str($postData['data'], $data);
//        $payment_id = $postData['payment_id'];
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 239005;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['Code'] != 200) {
            $this->orderInfo['code'] = 239006;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['MerchantId'] = $config['merchantID'];

        $retSign = $this->getSign($data, $config['merchantKey']);
        payLog('baisheng.txt',$retSign.'----179--');
        if(strtoupper($retSign) != $data['Sign']){
            $this->orderInfo['code'] = 239007;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['OutPaymentNo'];
        $this->orderInfo['amount']    = $data['PaymentAmount'] / 100;
        $this->orderInfo['serial_no'] = $data['PaymentNo'];

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

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//        payLog('baisheng.txt',$response.'---195--');
        return array('code' => $httpCode, 'data' => trim($response));
    }

    public function getSign($data, $key)
    {
        $signature = '';

        if (!$data['Code']) {
            $signature = sprintf(
                "MerchantId=%s&NotifyUrl=%s&OutPaymentNo=%s&PassbackParams=%s&PaymentAmount=%s&PaymentTypeCode=%s&Timestamp=%s",
                $data['MerchantId'], $data['NotifyUrl'], $data['OutPaymentNo'], $data['PassbackParams'], $data['PaymentAmount'], $data['PaymentTypeCode'], $data['Timestamp']);
        } else {
            $signature = sprintf(
                "Code=%s&MerchantId=%s&OutPaymentNo=%s&PassbackParams=%s&PaymentAmount=%s&PaymentFee=%s&PaymentNo=%s&PaymentState=%s",
                $data['Code'], $data['MerchantId'], $data['OutPaymentNo'], $data['PassbackParams'], $data['PaymentAmount'], $data['PaymentFee'], $data['PaymentNo'], $data['PaymentState']);
        }

        $sign = MD5($signature . $key);

        return $sign;
    }

}
