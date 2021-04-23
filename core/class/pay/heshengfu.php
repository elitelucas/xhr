<?php

/**
 *	Author: Kevin
 * 	CreateDate: 2017/09/14 15:05
 *  description: 合生富支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class HeShengFuPay extends PayInfo
{
    //请求接口Url
    //public $url = 'https://gray-scp-getway.9fbank.com/scppay/dynQRCodePay';  //测试
    public $url = 'https://scp-getway.9fbank.com/scppay/dynQRCodePay';  //部署
    public $retArr = [
        'code' => 0,
        'msg' => '',
        'data' => []
    ];
    public $orderInfo = [       //异步验签结果返回格式
        'code' => 1,
        'bank_num' => 202050,  //银行站内编号
        'order_no' => '',
        'amount' => 0,
        'ret_error' => 'ERROR',
        'ret_success' => 'SUCCESS',
        'bank_name' => '合生富支付',
        'serial_no' => ''  //流水号
    ];

    public function __construct()
    {
        parent::__construct();
    }
    
    public function doPay($data)
    {
        //首次生成payment_config表中的config信息
        //$this->setBaseConfig($data['payment_id']);
    
        //外部商户号，每次访问生成的唯一标识
        date_default_timezone_set('prc');
        $dateStr = date('Ymdhis',time());
        $accessToken = $dateStr.rand(100,999);
    
        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 202000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));
        
            return $this->retArr;
        }
    
        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 202001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
    
            return $this->retArr;
        }

        $post_data = array(
            'partnerId' => $config['partnerId'],                   //合作方编号,代理商ID
            'merchId'  => $config['merchantID'],  //商户ID
            'accessToken' => $accessToken,            //外部商户号，每次访问生成的唯一标识(用处不知道）
            'signType'  => 'MD5',                  //签名类型,RSA或MD5
            'orderNo'   => $orderInfo,           //商户支付订单号,每次访问生成的唯一订单标识
            'amount'    => number_format($data['money'], 2, '.', ''),  //金额,精确到两位小数(没有两位小数会出错）
            'payMethod' => $config['payType'][$data['pay_type']]['payStr'],  //支付方式：WXPAY微信公众号支付，ALIPAY：支付宝当面付-扫码支付（即支付宝生活号支付）
            'subject'   => '充值',                //商品主题
            'body'      => '会员充值',            //商品内容
            'clientIp'  => ip(),                  //用户IP,发起支付请求客户端的 ip
            //异步通知地址,用于接收订单支付结果，详情参照订单后台通知接口,回调URL上带上支付类型Id
            'notifyUrl' =>  "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id=" . $data['payment_id']
        );
        $post_data['sign'] = getSigned($post_data, ['key' => $config['merchantKey']]);
        
        //curl接口
        $dataJson = json_encode($post_data);
        $curlData = $this->httpPostJson($this->url, $dataJson);
        var_dump($curlData);

        //接口调用成功与否
        if ($curlData['code'] != 200) {
            $this->retArr['code'] = 202002;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '合生富付支付接口调用失败，' . print_r($curlData, true));
        
            return $this->retArr;
        }
        $retData = json_decode($curlData['data'], true);

        if (!isset($retData['resCode']) || $retData['resCode'] != 'S01000000' || !isset($retData['resMsg']) || $retData['resMsg'] != '成功') {
            $this->retArr['code'] = 202003;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '合生富支付接口调用失败，' . print_r($retData, true));
            
            return $this->retArr;
        }
        $retSign = getSigned($retData, ['key' => $config['merchantKey']], ['sign']);

        if($retSign != $retData['sign']){
            $this->retArr['code'] = 202003;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '合生富支付返回数据成功，但验证签名失败！' . print_r($retData, true));
        
            return $this->retArr;
        }
        
        $retOderNo = isset($retData['orderNo']) ? $retData['orderNo'] : '';
        $retOderPayNo = isset($retData['payOrderNo']) ? $retData['payOrderNo'] : '';
        $retOderPayQrcodrUrl = isset($retData['payQrCodeUrl']) ? $retData['payQrCodeUrl'] : '';
        if (empty($retOderNo) || empty($retOderPayNo) || empty(retOderPayQrcodrUrl)) {
            $this->retArr['code'] = 202004;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '合生富返回数据错误，' . print_r($retData, true));
            
            return $this->retArr;
        }

        $result = D('accountRecharge')->getOneCoupon('id', array('order_sn' => $retOderNo, 'status' => 0));
        if (empty($result)) {
            $this->retArr['code'] = 202004;
            $this->retArr['msg']  = '支付二维码生成失败！';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '合生富返回数据成功,但订单表没有查到相应未完成的订单号，' . print_r($retData, true));
            
            return $this->retArr;
        }

        D('accountRecharge')->save(['remark' => $retOderPayNo], ['order_sn' => $retOderNo]);

        //用于安全验证返回url是否非法
        session::set('qrcode_url', $retOderPayQrcodrUrl);
        session::set('pay_url', '');
        //type =1 返回二维码数据 2，返回html整页数据
        $ret =  ['type' => $config['payType'][$data['pay_type']]['request_type'], 'code_url' => $retOderPayQrcodrUrl, 'pay_url' => '', 'order_no' => $retOderNo, 'modes' => $data['pay_model']];
        $this->retArr['code'] = 0;
        $this->retArr['data']  = $ret;

        return $this->retArr;
    }

    //支付回调方法
    public function doPaycallBack($postData)
    {
        $remark = '';
        //处理post回调数据
        $data = json_decode($postData['data'], true);
        $payment_id = $postData['payment_id'];
        var_dump($data);
        $config = unserialize($postData['config']);
        if (!is_array($config)) {
            $this->orderInfo['code'] = 202006;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）合生富支付异步通知,获取数据库配置错误！'  . print_r($data, true));
        
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
            $this->orderInfo['code'] = 202008;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）合生富支付异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }

        //防止错传
        $data['merchId']    = $config['merchantID'];
        //$data['signType']   = 'MD5';
        //$data['resCode'] = 'S01000000';
        //var_dump($data);
        //$retSign = getSigned($data, ['key' => $config['merchantKey']], ['signInfo', 'receiveTime', 'resMsg', 'completeTime', 'partnerId', 'signType']);
        $retSign = getSigned($data, ['key' => $config['merchantKey']], ['signInfo', 'receiveTime', 'completeTime', 'resMsg']);
        var_dump($retSign);
            
        $this->orderInfo['order_no']  = $data['merOrderNo'];
        $this->orderInfo['amount']    = $data['amount'];
        $this->orderInfo['serial_no'] = $data['platOrderNo'];

        return $this->orderInfo;
        }
        
        //var_dump($retData);
        //验证签名
        /*
        $sign = $this->getSigned($retData, ['key' => $config['merchantKey']]);
        echo $sign;
       
        if($sign != $data['signInfo']){
            $this->orderInfo['code'] = 202007;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）华银支付异步通知,验签失败！'  . print_r($data, true));
        
            return $this->orderInfo;
        }
        
        if (!isset($data['resCode']) || !isset($data['resMsg'])|| $data['resCode'] != '0000' || $data['resMsg'] != '支付成功') {
            $this->orderInfo['code'] = 202008;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）合生富支付异步通知：返回信息不是充值成功信息，出现错误！'  . print_r($data, true));
        
            return $this->orderInfo;
        }else {
            $this->orderInfo['order_no']  = $data['merOrderNo'];
            $this->orderInfo['amount']    = $data['amount'];
            $this->orderInfo['serial_no'] = $data['platOrderNo'];
            
            return $this->orderInfo;
        }
        */
    
    //post数据
    function httpPostJson($url, $jsonStr)
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
        return array('code' => $httpCode, 'data' => $response);
    }

    function postHtml($post_data)
    {
        $html =  "<html>";
        $html .= "<head>";
        $html .= "<title>会员充值</title>";
        $html .= "</head>";
        $html .= "<body onLoad='document.form1.submit()'>";
        $html .= "<form id='form1' name='form1' action='".$this->payUrl."' method='post'>";
        $html .= "<input type='hidden' name='MemberID' value='".$post_data['MemberID']."' />";
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
    
    //支付初始配置
    public function setBaseConfig($payment_id)
    {
        $data1['name'] = '合生富';
        $data1['merchantID'] = 'mi1707270001';
        $data1['merchantKey'] = 'f26448bb839740a99d154a93ae4758de';
        $data1['partnerId'] = 'ORG_1495786664861';
        $data1['payType']['wx']['name'] = '微信支付';
        $data1['payType']['wx']['payStr'] = 'WXPAY';
        $data1['payType']['wx']['request_type'] = 1;
        $data1['payType']['ali']['name'] = '支付宝支付';
        $data1['payType']['ali']['payStr'] = 'ALIPAY';
        $data1['payType']['ali']['request_type'] = 1;
        
        $serData = serialize($data1);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }
}