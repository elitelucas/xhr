<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class xinBei extends PayInfo
{
    //请求接口Url

    public $url = 'http://43.249.80.192/anpay/pay';               //扫码正式（线上）调用接口
//    public $wy_url = 'http://gateway.paytong.com/pt_v3_quick/pay';
//    public $payName = 'xinbei支付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 270000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => '000000',  //回调处理成功时，返回接口字符串
        'bank_name' => '新呗支付',    //支付方式名称
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
        lg('xinbei_pay', '================== pay start =============================');

        //生成订单
        $orderInfo = $this->makeOrder($data);

        if (!$orderInfo) {
            $this->retArr['code'] = 277071;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('xinbei.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 277072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('xinbei.txt', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }
        $payStr = $config['payType'][$data['pay_type']]['payStr'];

        //==========================================================================================

        $post_data = array(
            'mid'      => $config['merchantID'],
            'orderNo'      => $orderInfo,
            'product_name'      => 'CZ',
            'body'      => 'XB_CZ',
            'amount'      => $data['money'],
            'notifyUrl'      => "https://".$callbackurl."/rechargeNotify.php?payment_id=" . $data['payment_id'],
//            'notifyUrl'      => "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'pz_userId'      => $data['user_id'],
            'mch_create_ip'      => ip(),
            'returnUrl'      => "https://".$callbackurl."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,
        );
        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {
            $curlData = $this->httpPost($post_data, $this->url);
            payLog('xinbei.txt', print_r($curlData, true).'----113---');

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 277073;
                $this->retArr['msg']  = '支付调用失败！';

                return $this->retArr;
            }
            $returnData = json_decode($curlData['data'],true);

            if ($returnData['resultCode'] != "0000") {
                $this->retArr['code'] = 277074;
                $this->retArr['msg']  = '支付接口调用失败';

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
//            $url = urldecode($returnData['payURL']);
//            payLog('paytong.txt',$url. "   ====137");
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml($post_data, $this->url)
            ];

            payLog('xinbei.txt', print_r($retData, true).'----116---');
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

        payLog('xinbei.txt', print_r($postData, true).'----117---');

//        $data = $postData['data'];
        $data = [];
        parse_str($postData['data'],$data);

        $config = unserialize($postData['config']);

        if (!is_array($config)) {
            $this->orderInfo['code'] = 277020;

            return $this->orderInfo;
        }

        if ($data['code'] != "SUCCEED" && $data['msg'] != 'OK') {
            $this->orderInfo['code'] = 277021;
            return $this->orderInfo;
        }

        //防止错传
        $data['merchantNo'] = $config['merchantID'];
        $sign = $data['sign'];
        unset($data['sign']);


        $retSign = $this->getSign($data, $config['merchantKey']);

        payLog('paytong.txt',$retSign.'----195--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 277022;

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['orderNo'];  //商户订单号
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['orderTime'];  //平台订单号

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
        $html .= '<form id="payFrom" name="dinpayForm" method="get" action="' . $url . '">';
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
        $mid = $data['mid'];
        $orderNo = $data['orderNo'];
        $amount = $data['amount'];
        return md5(md5($key).$mid.$orderNo.$amount);
    }


}
