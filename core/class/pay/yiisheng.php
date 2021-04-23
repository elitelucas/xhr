<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/12/25 10:00
 * @description 闪付支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class YiiShengPay extends PayInfo
{
    //请求接口Url
    public $url = 'http://api.mposbank.com/tdcctp/alipay/direct_pay.tran';       //扫码正式（线上）调用接口
    public $h5_url = 'http://api.mposbank.com/tdcctp/alipay/wap_pay.tran';  //第三方网银接口
    public $payName = '谊盛支付';   //接口名称

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 265000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'success',  //回调处理成功时，返回接口字符串
        'bank_name' => '谊盛支付',       //支付方式名称
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
            $this->retArr['code'] = 265001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 265002;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));

            return $this->retArr;
        }

        $post_data = array(
            'merchant_order_no'    => $orderInfo,
            'merchant_no'    => strval($config['merchantID']),
            'callback_url'    => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id'],
            'order_smt_time'    => strval(time()),
            'order_type'    => '02',
            'trade_amount'    => strval(number_format($data['money'], 2, '.', '') * 100),
            'goods_name'    => 'recharge',
            'goods_type'    => '02',
            'trade_desc'    => 'yiisheng',
            'sign_type'    => '01',
            'return_url'   => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,
        );

        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        payLog('yiisheng.txt',print_r($post_data,true).' --87--');

        if ($config['payType'][$data['pay_type']]['payEntrance'] == 4) {
            $url = $this->url;
        } else {
            $url = $this->h5_url;
        }

        $curlData = $this->httpPost($post_data,$url);
//        payLog('yiisheng.txt',print_r($curlData,true).' --89--');

        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 265003;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('yiisheng.txt', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true).'--101--');

            return $this->retArr;
        }
        $returnInfo = json_decode($curlData['data'],true);


        if ($returnInfo['code'] != '608') {
            $this->retArr['code'] = 265004;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($returnInfo, true));
            return $this->retArr;
        }
        $body = $returnInfo['body'];
        $sign = $body['sign'];
        unset($body['sign']);
        if ($this->verifySign($body,$sign, $config['merchantKey'])) {
            $retData =  [
                'type'  => 2,
                'modes' => $data['pay_model'],
                'html'  => $this->httpHtml(array(), $body['params'])
            ];

            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            $this->retArr['code'] = 265004;
            $this->retArr['msg']  = '支付调用失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '付接口调用失败，' . print_r($returnInfo, true));
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
        payLog('yiisheng.txt',print_r($postData,true).'----149--');
        parse_str($postData['data'],$data);
        payLog('yiisheng.txt',print_r($postData,true).'----150--');
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 265005;
            payLog('yiisheng.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($postData, true));

            return $this->orderInfo;
        }

        $sign = $data['sign'];
        unset($data['sign']);

        if ($data['status'] != 'Success' && $data['rist'] != "NoRisk") {
            $this->orderInfo['code'] = 265005;
            payLog('yiisheng.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,返回状态码错误！'  . print_r($postData, true));

            return $this->orderInfo;
        }


        $retSign = $this->getSign($data, $config['merchantKey']);
//        payLog('yiisheng.txt',$retSign.'---173--');
        if($retSign != $sign){
            $this->orderInfo['code'] = 265007;
            payLog('yiisheng.txt', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;

        $this->orderInfo['order_no']  = $data['merchant_order_no'];
        $this->orderInfo['amount']    = $data['amount'] /100;
        $this->orderInfo['serial_no'] = $data['prd_ord_no'];

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

        $data = array_filter($data);
        ksort($data);
        $sign_str = json_encode($data,320);
        $sign_str = $key.$sign_str.$key;

        payLog('yiisheng.txt', $sign_str. '   --242');
        return md5($sign_str);
    }

    public function verifySign($data, $sign,$key)
    {
        ksort($data);
        $sign_str = json_encode($data,320);
        $sign_str = md5($key.$sign_str.$key);
        if ($sign_str == $sign) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * 验证生成签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    public function retSign($post_data,$merchantKey)
    {
        return strtolower(md5(("MemberID=" . $post_data['MemberID'] . "~|~" . "TerminalID=" . $post_data['TerminalID'] . "~|~" . "TransID=" . $post_data['TransID'] . "~|~" . "Result=" . $post_data['Result'] . "~|~" . "ResultDesc=" . $post_data['ResultDesc'] . "~|~" . "FactMoney=" . $post_data['FactMoney'] . "~|~" . "AdditionalInfo=" . $post_data['AdditionalInfo'] . "~|~" . "SuccTime=" . $post_data['SuccTime'] . "~|~" . "Md5Sign=" . $merchantKey)));
    }
}