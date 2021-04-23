<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class MingFuPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://mingweiguanwang.xyz/trans/gateway/tran/genQrCode';                 //扫码测试调用接口

    public $bank_url = '';  //第三方网银接口
    public $h5_url = 'http://mingweiguanwang.xyz/trans/gateway/tran/h5Pay';  //第三方H5接口

    public $payName = '明付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 243000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => '000000',  //回调处理成功时，返回接口字符串
        'bank_name' => '明付',    //支付方式名称
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
            $this->retArr['code'] = 243001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 243002;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        $post_data = array(
            'merCode'    => strval(8620180315105559),
            'tranNo'    => $orderInfo,
            'tranAmt'    => strval($data['money'] * 100),
            'settleType'    => "0",
            'collectWay'    => $payStr,
            'tranTime'    => strval(date("Ymdhis",time())),
            'expireTime'    => "120",
            'noticeUrl'    => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,
            'subject'    => "recharge",
            'orderDesc'    => "recharge",
//            'frontUrl'    => "https://".$_SERVER['HTTP_HOST'],
        );


        $post_data['sign'] = $this->getSign($post_data, 'X26LCBURAMYQDJO6');

//        var_dump($post_data);
        payLog('mingf.txt',print_r($post_data,true).'---92--');



        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {
            $post_data = json_encode($post_data);
            $curlData = $this->httpPost($post_data, $this->h5_url);
            payLog('mingf.txt',print_r($curlData,true).'---93--');

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 242003;
                $this->retArr['msg']  = '支付调用失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

                return $this->retArr;
            }
            $returnInfo = json_decode($curlData['data'],true);
            payLog('mingf.txt',print_r($returnInfo,true).'---124--');

            if ($returnInfo['respCode'] != 2) {
                $this->retArr['code'] = 243004;
                $this->retArr['msg']  = $returnInfo['respMsg'];
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($returnInfo, true));
                return $this->retArr;
            }


            //用于安全验证返回url是否非法
            session::set('qrcode_url', $returnInfo['respCode']);
            session::set('pay_url', '');
            $retData =  [
                'type'     => 1,
                'code_url' => $returnInfo['respCode'],
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;

        } else {
            $retData =  [
                'type'  => 2,
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data,$this->h5_url)
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
        payLog('mingf.txt',print_r($postData,true).'----157--');
        parse_str($postData['data'],$data);
        payLog('mingf.txt',print_r($data,true).'----149--');
        $payment_id = $postData['payment_id'];
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 243005;
            payLog('mingf.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if ($data['status'] != 1) {
            $this->orderInfo['code'] = 243006;
            payLog('mingf.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merId'] = $config['merchantID'];
        $retSign = $this->getSign($data, $config['merchantKey']);
        $retSign = md5($retSign.$config['merchantKey']);
        if(strtoupper($retSign) != $data['sign']){
            $this->orderInfo['code'] = 243007;
            payLog('mingf.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['tradeNo'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['opeNo'];

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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array('code' => $httpCode, 'data' => $response);
    }

    public function getSign($data, $key)
    {
      ksort($data);
      $sign = '';
      foreach ($data as $k => $v) {
          $sign .= $k ."=" . $v . "&";
      }
      $sign = rtrim($sign,'&');
      return md5($sign.$key , false);
    }

}
