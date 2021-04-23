<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/9/17 17:06
 * @description 新码支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class XinMaPay extends PayInfo
{
    //请求接口Url
    public $url = 'https://www.xinmapay.com:7301/jhpayment';       //扫码正式（线上）调用接口
    public $bank_url = 'https://www.xinmapay.com:7301/jhpayment';  //第三方网银接口
    public $payName = '新码支付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 219050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '新码支付',//支付方式名称
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
            $this->retArr['code'] = 219000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 219001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'messageid' => (string)$config['payType'][$data['pay_type']]['messageId'],
            'branch_id' => $config['merchantID'],   //合作方编号,代理商ID
            'out_trade_no' => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
            'total_fee'    => (int)(number_format($data['money'], 2, '.', '') * 100),  //金额,精确到两位小数(没有两位小数会出错）
            'pay_type' => (string)$config['payType'][$data['pay_type']]['payStr'],  //支付方式：微信，支付宝支付，QQ钱包，网银
            'prod_name'   => 'recharge',   //商品主题
            'prod_desc'   => 'recharge',   //商品描述
            'nonce_str'  => getRandomString(32),                  //用户IP,发起支付请求客户端的 ip
            //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
            'back_notify_url' =>  "https://".$callbackurl."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id']
            //'back_notify_url' =>  "https://".$_SERVER['HTTP_HOST']
        );
        payLog('xinma.txt',print_r($post_data,true));

        if ($data['pay_type'] == 'wy') {
            //网银支付调用
            $post_data['bank_code'] = $data['bank_code'];
            $post_data['bank_flag'] = "0"; ////0:固定为私借记卡，私贷记卡:1不考虑(简化设置)
            $post_data['front_notify_url'] =  "https://".$callbackurl."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo;
            //$post_data['front_notify_url'] =  "https://".$_SERVER['HTTP_HOST'];
            //签名数据连接成字符串
            $post_data['sign'] = getSigned($post_data, ['key' => $config['merchantKey']], [], '&', 1, 1);

            //curl接口
            $curlData = $this->httpPost($post_data, $this->url);

            //type =2返回html跳转页面数
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'html'  => $curlData['data'],
                'modes' => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            //非网银支付，微信、支付宝、QQ钱包支付
            //签名数据连接成字符串
            $post_data['sign'] = getSigned($post_data, ['key' => $config['merchantKey']], [], '&', 1, 1);

            //curl接口
            $curlData = $this->httpPost($post_data, $this->url);
            //var_dump($curlData);

            //接口调用成功与否
            if ($curlData['code'] != 200) {
                $this->retArr['code'] = 219002;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($curlData, true));

                return $this->retArr;
            }

            $retData = json_decode($curlData['data'], true);
            payLog('xinma.txt',print_r($retData,true). "===134");
            if (!isset($retData['resultCode']) || $retData['resultCode'] != '00' || !isset($retData['resCode']) || $retData['resCode'] != '00') {
                $this->retArr['code'] = 219003;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '接口调用失败，' . print_r($curlData['data'], true));

                return $this->retArr;
            }

            //签名
            $sgin = getSigned($retData, ['key' => $config['merchantKey']], ['sign'], '&', 1, 1);
            if($retData['sign'] != $sgin){
                $this->retArr['code'] = 219004;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功，但验证签名失败！' . print_r($curlData['data'], true));

                return $this->retArr;
            }

            $retOderNo = $orderInfo;
            $retOderPayNo = isset($retData['orderNo']) ? $retData['orderNo'] : 'xinmapay' . date('YmdHis');
            $retOderPayQrcodrUrl = isset($retData['payUrl']) ? $retData['payUrl'] : '';
            if (empty($retOderNo) || empty($retOderPayNo) || empty(retOderPayQrcodrUrl)) {
                $this->retArr['code'] = 219005;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据错误，' . print_r($retData, true));

                return $this->retArr;
            }

            $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
            if (empty($result)) {
                $this->retArr['code'] = 219006;
                $this->retArr['msg']  = '支付二维码生成失败！';
                payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($curlData['data'], true));

                return $this->retArr;
            }

            D('accountRecharge')->save(['remark' => $retOderPayNo], ['order_sn' => $retOderNo]);

            //用于安全验证返回url是否非法
            session::set('qrcode_url', $retOderPayQrcodrUrl);
            session::set('pay_url', '');
            //type =1 返回二维码数据
            $ret =  [
                'type'     => $config['payType'][$data['pay_type']]['request_type'],
                'code_url' => $retOderPayQrcodrUrl,
                'pay_url'  => '',
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data'] = $ret;

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
        $data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 219020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知,获取数据库配置错误！'  . print_r($data, true));

            return $this->orderInfo;
        }
        if (!isset($data['resultCode']) || $data['resultCode'] != '00' || !isset($data['resCode']) || $data['resCode'] != '00') {
            $this->orderInfo['code'] = 219021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知：返回信息不是充值成功的信息！'  . print_r($data, true));

            return $this->orderInfo;
        }

        if (!isset($data['status']) || $data['status'] != '02' ) {
            $this->orderInfo['code'] = 219022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知：支付失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错调
        $data['branchId'] = $config['merchantID'];

        //签名
        $sgin = getSigned($data, ['key' => $config['merchantKey']], ['sign'], '&', 1, 1);
        if($data['sign'] != $sgin){
             $this->orderInfo['code'] = 219023;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $data['outTradeNo'];
        $this->orderInfo['amount']    = number_format(($data['orderAmt'] / 100), 2, '.', '');
        $this->orderInfo['serial_no'] = $data['orderNo'];

        return $this->orderInfo;
    }

    /**
     * 递归多维数组，进行urlencode
     * @param $array
     * @return mixed
     */
    function urlencode_array($array) {
        foreach($array as $k => $v) {
            if(is_array($v)) {
                $array[$k] = $this->urlencode_array($v);
            } else {
                $array[$k] = urlencode($v);
            }
        }
        return $array;
    }

    public function htmlPost($post_data, $url) {

        $data_string = json_encode($post_data);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS,$data_string);
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在

        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
            );

        $response=curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        //$res=simplexml_load_string($response);
        //$res = (array)simplexml_load_string($response);

        return array('code' => $httpCode, 'data' => $response);

        /*
        if (curl_errno($curl)) {
            echo 'Errno'.curl_error($curl);//捕抓异常
        }
        curl_close($curl);
        return $result;
        */

    }

    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function httpPost($postdata, $url)
    {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response=curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        //$res=simplexml_load_string($response);
        //$res = (array)simplexml_load_string($response);

        return array('code' => $httpCode, 'data' => $response);
    }

    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    function httpHtml($post_data, $url)
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
}