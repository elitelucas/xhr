<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/12/25 10:00
 * @description 闪付支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class ShanFuPay extends PayInfo
{
    //请求接口Url
    public $url = 'http://gw.3yzf.com/v4.aspx';       //扫码正式（线上）调用接口
    public $bank_url = 'http://gw.3yzf.com/v4.aspx';  //第三方网银接口
    public $payName = '闪付支付';   //接口名称
    
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
        'ret_success' => 'OK',  //回调处理成功时，返回接口字符串
        'bank_name' => '闪付支付',       //支付方式名称
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
            $this->retArr['code'] = 200001;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 200001;
            $this->retArr['msg']  = '支付类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'MemberID'    => $config['merchantID'],
            'TerminalID'  => $config['terminalID'],
            'InterfaceVersion' => '4.0',
            'KeyType'     => 1,
            'PayID'       => $config['payType'][$data['pay_type']]['payStr'],//支付方式
            'TradeDate'   => date("YmdHis"),   //交易时间
            'TransID'     => $orderInfo,       //商户订单号
            'OrderMoney'  => number_format($data['money'], 2, '.', '') * 100,//订单金额
            'ProductName' => urlencode('会员充值'),//产品名称
            'NoticeType'  => 1,
            'PageUrl'     => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=recharge&a=rechargeOk&order_sn=" . $orderInfo,//通知商户页面端地址
            'ReturnUrl'   => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $data['payment_id']//服务器底层通知地址
        );
        
        $post_data['Md5Sign'] = $this->getSign($post_data, $config['merchantKey']);
        //var_dump($post_data);
    
        //type =2返回html跳转页面数
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
        $data = array(
            "MemberID"   => trim($_REQUEST['MemberID']),    //商户号
            "TerminalID" => trim($_REQUEST['TerminalID']),  //商户终端号
            "TransID"    => trim($_REQUEST['TransID']),     //流水号
            "Result"     => trim($_REQUEST['Result']),      //支付结果
            "ResultDesc" => trim($_REQUEST['ResultDesc']),  //支付结果描述
            "FactMoney"  => trim($_REQUEST['FactMoney']),   //实际成功金额
            "AdditionalInfo" => trim($_REQUEST['AdditionalInfo']),  //订单附加消息
            "SuccTime"   => trim($_REQUEST['SuccTime']),    //支付完成时间
            "Md5Sign"    => trim($_REQUEST['Md5Sign'])
        );

        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 200020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        if (!isset($data['Result']) || $data['Result'] != 1 || !isset($data['ResultDesc']) ||  $data['ResultDesc'] != '01') {
            $this->orderInfo['code'] = 200021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['MemberID']   = $config['merchantID'];
        $data['TerminalID'] = $config['terminalID'];

        $retSign = $this->retSign($data, $config['merchantKey']);
        if($retSign != $data['Md5Sign']){
            $this->orderInfo['code'] = 200022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));

            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['TransID'];
        $this->orderInfo['amount']    = ($data['FactMoney'] / 100);
        $this->orderInfo['serial_no'] = $data['SuccTime'];

        return $this->orderInfo;
    }
        
        
    
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    public function httpPostJson($jsonStr, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Content-Type: application/json;charset=utf-8',
              'Content-Length: ' . strlen($jsonStr)
            )
        );

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$response=simplexml_load_string($response);
        return array('code' => $httpCode, 'data' => trim($response));
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
    public function getSign($post_data, $merchantKey)
    {
        return md5($post_data['MemberID'] . '|' . $post_data['PayID'] . '|' . $post_data['TradeDate'] . '|' . $post_data['TransID'] . '|' . $post_data['OrderMoney'] . '|' . $post_data['PageUrl'] . '|' . $post_data['ReturnUrl'] . '|' . $post_data['NoticeType'] . '|' . $merchantKey);
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