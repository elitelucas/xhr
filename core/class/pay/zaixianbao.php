<?php

/**
 * Created by Kevin.
 * @author kevin
 * @copyright HCHT 2017/11/10 17:06
 * @description 在线宝支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class ZaiXianBaoPay extends PayInfo
{
    //请求接口Url
    public $url = 'http://p.1wpay.com/a/passivePay';               //扫码测试调用接口
    //public $url = 'https://api.huayinpay.com/gateway/api/scanpay';               //扫码正式（线上）调用接口
    public $bank_url = 'https://pay.huayinpay.com/gateway?input_charset=UTF-8';  //第三方网银接口
    
    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
            'code' => 0,             //0:数据获取成功，其他数字，数据获取失败
            'msg' => '',             //返回的提示信息 
            'data' => []             //返回数据
        ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 213050,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '在线宝支付',       //支付方式名称
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
        //首次生成payment_config表中的config信息
        $this->setBaseConfig($data['payment_id']);
    
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 213000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 213001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'merchno'   => $config['merchantID'],
            'amount'    => number_format($data['money'], 2, '.', ''),
            'traceno'   => $orderInfo,
            'payType'   => $config['payType'][$data['pay_type']]['payStr'],
            'goodsName' => '会员充值',
            'notifyUrl' => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id']
        );

        $post_data['goodsName'] = iconv('UTF-8', 'GB2312', $post_data['goodsName']);
        
        $post_data['signature'] = getSigned($post_data, ['key' => $config['merchantKey']], [], '&', 0, 1);
        
        //curl接口
        $curlData = $this->httpPost($post_data, $this->url);
        $curlData['data'] = iconv('GB2312', 'UTF-8', $curlData['data']);
        var_dump($curlData);

        //接口调用成功与否
        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 202002;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($curlData, true));
        
            return $this->retArr;
        }
        
        $retData = json_decode($curlData['data'], true);
        if (!isset($retData['resCode']) || $retData['resCode'] != '00') {
            $this->retArr['code'] = 200003;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付接口调用失败，' . print_r($retData, true));
            
            return $this->retArr;
        }
/*
        $retSign = getSigned($retData, ['key' => $config['merchantKey']], ['sign']);
        if($retSign != $retData['sign']){
            $this->retArr['code'] = 200004;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付返回数据成功，但验证签名失败！' . print_r($retData, true));
        
            return $this->retArr;
        }
*/
        
        $retOderNo = isset($retData['traceno']) ? $retData['traceno'] : '';
        $retOderPayNo = isset($retData['refno']) ? $retData['refno'] : '';
        $retOderPayQrcodrUrl = isset($retData['barCode']) ? $retData['barCode'] : '';
        if (empty($retOderNo) || empty($retOderPayNo) || empty(retOderPayQrcodrUrl)) {
            $this->retArr['code'] = 200005;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '返回数据错误，' . print_r($retData, true));
            
            return $this->retArr;
        }

        $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
        if (empty($result)) {
            $this->retArr['code'] = 200006;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
            
            return $this->retArr;
        }

        D('accountRecharge')->save(['remark' => $retOderPayNo], ['order_sn' => $retOderNo]);

        //用于安全验证返回url是否非法
        session::set('qrcode_url', $retOderPayQrcodrUrl);
        session::set('pay_url', '');
        //type =1 返回二维码数据 2，返回html整页数据
        $ret =  [
            'type'     => $config['payType'][$data['pay_type']]['request_type'],
            'code_url' => $retOderPayQrcodrUrl,
            'pay_url'  => '',
            'order_no' => $retOderNo,
            'modes'    => $data['pay_model']
        ];

        $this->retArr['code'] = 0;
        $this->retArr['data'] = $ret;

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
        $data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        //var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 200020;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知,获取数据库配置错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $retData = array(
            //'partnerId'   => $config['partnerId'],  //合作方编号,代理商ID
            //'merchId'     => $config['merchantID'],  //商户ID
            'merchId'     => isset($data['merchId']) ? $data['merchId'] : '' ,  //商户ID
            'amount'      => isset($data['amount']) ? $data['amount'] : '',
            'platOrderNo' => isset($data['platOrderNo']) ? $data['platOrderNo'] : '',
            'merOrderNo'  => isset($data['merOrderNo']) ? $data['merOrderNo'] : '',
            'resCode'     => isset($data['resCode']) ? $data['resCode'] : '',
            //'completeTime' => isset($data['completeTime']) ? $data['completeTime'] : '',
            //'receiveTime' => isset($data['receiveTime']) ? $data['receiveTime'] : '',
            //'signType' => 'MD5',
            'signType'    => isset($data['signType']) ? $data['signType'] : ''
        );
        if (!isset($data['resCode']) || $data['resCode'] != '0000' || !isset($data['resMsg']) ||  $data['resMsg'] != '支付成功') {
            $this->orderInfo['code'] = 200021;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));

            return $this->orderInfo;
        }

        //防止错传
        $data['merchId']    = $config['merchantID'];
        //$data['signType']   = 'MD5';
        //$data['resCode'] = 'S01000000';
        //var_dump($data);
        //$retSign = getSigned($data, ['key' => $config['merchantKey']], ['signInfo', 'receiveTime', 'resMsg', 'completeTime', 'partnerId', 'signType']);
        $retSign = getSigned($data, ['key' => $config['merchantKey']], ['signInfo', 'receiveTime', 'completeTime', 'resMsg']);
        //var_dump($retSign);
        if($retSign != $data['signInfo']){
            $this->orderInfo['code'] = 200022;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）支付异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['merOrderNo'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['platOrderNo'];

        return $this->orderInfo;
    }
    
    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    function httpPost($postData, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return array('code' => $httpCode, 'data' => $response);
    }

    /**
     * 提交表单数据
     * @param array $post_data 表单提交数据
     * @param string $url 表单提交接口
     * @return string
     */
    function postHtml($post_data, $url)
    {
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<title>会员充值</title>";
        $html .= "</head>";
        $html .= "<body onLoad='document.form1.submit()'>";
        $html .= "<form id='payFrom' name='form1' action='" . $url . "' method='post'>";
        $html .= "<input type='hidden' name='MemberID' value='" . $post_data['MemberID']."' />";
        $html .= "<input type='hidden' name='TerminalID' value='".$post_data['TerminalID']."' />";
        $html .= "<input type='hidden' name='InterfaceVersion' value='".$this->interfaceVersion."' />";
        $html .= "<input type='hidden' name='KeyType' value='".$this->keyType."' />";
        $html .= "<input type='hidden' name='PayID' value='".$post_data['PayID']."' />";
        $html .= "<input type='hidden' name='TradeDate' value='".$post_data['TradeDate']."' />";
        $html .= "<input type='hidden' name='TransID' value='".$post_data['TransID']."'>";
        $html .= "<input type='hidden' name='OrderMoney' value='".$post_data['OrderMoney']."'>";
        $html .= "<input type='hidden' name='ProductName' value='".$post_data['ProductName']."'>";
        $html .= "<input type='hidden' name='PageUrl'	value='".$post_data['PageUrl']."'>";
        $html .= "<input type='hidden' name='ReturnUrl'	value='".$post_data['ReturnUrl']."'>";
        $html .= "<input type='hidden' name='Signature'	value='".$post_data['Signature']."'>";
        $html .= "<input type='hidden' name='NoticeType'	value='".$this->noticeType."'>";
        $html .= "</form>";
        $html .= "</body>";
        $html .= '</html>';
        return $html;
    }
    
    
    /**
     * 支付初始数据配置数据库
     * @param int $payment_id 支付类型ID
     */
    public function setBaseConfig($payment_id)
    {
        $data['name'] = '在线宝支付';
        $data['merchantID'] = '666130458140001';
        $data['merchantKey'] = '9E7241E79D8ADF6B6AFFF6B2B952EABB';
        $data['payType']['wx']['name'] = '微信支付';
        $data['payType']['wx']['payStr'] = 2;
        $data['payType']['wx']['request_type'] = 1;
        $data['payType']['ali']['name'] = '支付宝支付';
        $data['payType']['ali']['payStr'] = 1;
        $data['payType']['ali']['request_type'] = 1;
        $data['payType']['qq']['name'] = 'QQ钱包支付';
        $data['payType']['qq']['payStr'] = 4;
        $data['payType']['qq']['request_type'] = 1;
        
        $serData = serialize($data);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}