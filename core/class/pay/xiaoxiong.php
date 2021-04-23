<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class XiaoXiong extends PayInfo
{
    //请求接口Url

    public $url = 'https://api.shaimeixiong.com/api/receive?type=form';               //扫码正式（线上）调用接口
    public $h5_url = 'https://api.shaimeixiong.com/api/receive?type=form';
    public $payName = '小熊支付';   //接口名称


    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 275000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => '200',  //回调处理成功时，返回接口字符串
        'bank_name' => '小熊支付',    //支付方式名称
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
            $this->retArr['code'] = 275071;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 275072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];


        $post_data = array(
            'type'      => 'form',
            'merchantId'      => $config['merchantID'],
            'paytype'      => $payStr,
            'timestamp'      => date("Ymyhis",time()),
            'merchantOrderId'      => $orderInfo,
            'money'      => (int)$data['money'] ,
            'goodsName'      => 'recharge',
            'merchantUid'      => '99',
//            'pay_notifyurl'          => "https://".$callbackurl."/rechargeNotify.php",
            'notifyURL'          => "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'returnURL'      => "https://".$callbackurl."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,
        );
        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);

        $retData =  [
            'type'  => $config['payType'][$data['pay_type']]['request_type'],
            'modes' => $data['pay_model'],
            'html'  => $this->httpHtml($post_data, $this->url)
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

        payLog('xiaoxiong.txt',print_r($postData,true).'----157--');
        parse_str($postData['data'],$data);

        $config = unserialize($postData['config']);

        payLog('xiaoxiong.txt',print_r($data,true).'----160--');

        if (!is_array($config)) {
            $this->orderInfo['code'] = 275020;
            payLog('xiaoxiong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $sign = $data['sign'];
        $retSign = $this->getSign($data, $config['merchantKey']);
        payLog('xiaoxiong.txt',$retSign.'----195--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 275022;
            payLog('xiaoxiong.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if (bccomp($data['money'],$data['payAmount'],6) != 0) {
            $this->changeAmount($data['merchantOrderNo'],$data['payAmount']);
        }


        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['merchantOrderNo'];  //商户订单号
        $this->orderInfo['amount']    = $data['payAmount'] / 100;
        $this->orderInfo['serial_no'] = $data['orderNo'];  //平台订单号

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

//        payLog('xiaoxiong.txt',print_r($html,true). "  ==220== ");
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
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        payLog('xiaoxiong.txt',print_r($response,true). "+++251".$url);
//        $response = json_decode(json_encode(simplexml_load_string($response)),true);

        return array('code' => $httpCode, 'data' => $response);
    }

    public function getSign($data, $key)
    {
        $sign_str = '';
        if($data['sign']){
            $sign_str = sprintf("%s&%s&%s&%s&%s",
                $data['orderNo'],$data['merchantOrderNo'],$data['money'],$data['payAmount'],$key);
        }else {
            $sign_str = sprintf("%s&%s&%s&%s&%s&%s&%s",
                $data['money'],$data['merchantId'],$data['notifyURL'],$data['returnURL'],$data['merchantOrderId'],$data['timestamp'],$key);
        }
        payLog('xiaoxiong.txt',$sign_str. "+++++++271");

        return strtolower(md5($sign_str));
    }



}
