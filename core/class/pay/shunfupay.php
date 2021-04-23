<?php

/**
 * Created by Bell.
 * @author bell
 * @copyright HCHT 2017/9/17 17:06
 * @description 瞬付支付
 */

include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class ShunFuPay extends PayInfo
{
    private $pay_url            = 'http://trade.595pay.com:8080/api/pay.action';    //请求地址

    /**
     * 构成函数
     */
    public function __construct(){
        parent::__construct();
    }

    /**
     * 调用第三方支付接口，获取支付信息
     * @param array $data 前端返回信息，payment_id，支付类型ID，config，支付类型配置信息
     * {@inheritDoc}
     * @see PayInfo::doPay()
     * @return $this->$retArr;
     */
    public function doPay($data) {
        //首次生成payment_config表中的config信息
        //$this->setBaseConfig($data['payment_id']);

        //生成订单
        $orderInfo = $this->makeOrder($data);
        if (!$orderInfo) {
            $this->retArr['code'] = 212000;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付订单生成失败，' . print_r($data, true));

            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);
        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 212001;
            $this->retArr['msg']  = '支付银行类型不存在';
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付银行类型不存在，' . print_r($data, true));
            return $this->retArr;
        }
        $post_data = [
            'merNo'             => $config['merchantID'], #商户号
            'payNetway'         => $config['payType'][$data['pay_type']]['payStr'],      #支付类型码
            'random'            => (string) rand(1000,9999),#4位随机数    必须是文本型
            'orderNo'           => $orderInfo, #商户订单号
            'amount'            => strval($data['money']*100), #默认分为单位 转换成元需要 * 100   必须是文本型
            'goodsInfo'         => '会员充值',#商品名称
            'callBackUrl'       => "https://".$_SERVER['HTTP_HOST']."/?m=api&c=pay&a=doPaycallBack&payment_id={$data['payment_id']}",#通知回调地址 可以写成固定
            'callBackViewUrl'   => "https://".$_SERVER['HTTP_HOST']."/?m=web&c=pay&a=payOk&order_sn={$orderInfo}&flag=1AA1",  // #前台跳转 可以写成固定  flag参数辨别get返回参数时添加的"?"，处理使用flag标签,
            'clientIP'          => self::GetRemoteIP()  #客户请求IP
        ];
        #设置签名
        $post_data['sign'] = self::array_to_sign($post_data, $config['merchantKey']);

        $post_data = self::json_encodes($post_data);

        #提交订单数据
        $return = self::httpPost($this->pay_url,['data' => $post_data]);
        $row = json_decode($return,true);
        //是否返回成功
        if ($row['resultCode'] != '00') {
            $this->retArr['code'] = 214002;
            $this->retArr['msg']  = $row['resultMsg'];
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '付支付接口调用失败，' . print_r($row, true));
            return $this->retArr;
        }

        #验证返回签名数据
        if (!self::is_sign($row,$config['merchantKey'])) {
            $this->retArr['code'] = 214002;
            $this->retArr['msg']  = $row['resultMsg'];
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '支付接口验签失败' . print_r($row, true));
            return $this->retArr;
        }

        //用于安全验证返回url是否非法
        session::set('qrcode_url', $row['qrcodeInfo']);
        session::set('pay_url', '');
        $ret =  [
            'type'     => $config['payType'][$data['pay_type']]['request_type'],
            'code_url' => $row['qrcodeInfo']?:'',
            'pay_url'  => '',
            'order_no' => $orderInfo,
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
    public function doPaycallBack($data){
        lg('pay.log', '瞬付支付异步通知接收参数::'. print_r($data, true));
        //组织回调参数
        $config = unserialize($data['config']);
        $data = json_decode($data['data'], true);
        $post_data = [
            'merNo' => $data['merNo'], //商户号
            'payNetway' => $data['payNetway'], //支付宝 = ZFB, 微信 = WX   QQ钱包 = QQ
            'orderNo' => $data['orderNo'], //商家订单号
            'amount' => $data['amount'], //金额（单位：分）
            'goodsInfo' => $data['goodsInfo'], //商品名称
            'resultCode' => $data['resultCode'], //resultCode  支付状态，00表示成功
            'payDate' => $data['payDate'], //支付时间，格式：yyyy-MM-dd HH:mm:ss
            'sign' => $data['sign'] //签名
        ];

        //验证数据库配置
        if (!is_array($config)) {
            $this->orderInfo['code'] = 200009;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）瞬付支付异步通知,获取数据库配置错误！'  . print_r($data, true));
            return $this->orderInfo;
        }

        //验签
        if(!self::is_sign($post_data,$config['merchantKey'])) {
            $this->orderInfo['code'] = 200008;
            payLog('payerror.log', '（' . $this->orderInfo['code'] . '）瞬付支付异步通知,验签失败！'  . print_r($data, true));
            return $this->orderInfo;
        }

        $order_info = $this->db->getone("select order_sn,status from #@_account_recharge where order_sn = '{$data["orderNo"]}'");

        //验证订单号是否正确
        if(empty($order_info)){
            $this->orderInfo['code'] = 200010;
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '瞬付支付异步通知：充值订单不存在，' . print_r($data, true));
            return $this->orderInfo;
        }

        //验证订单是否处理过
        if($order_info['status'] != 0){
            $this->orderInfo['code'] = 200011;
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '瞬付支付异步通知：充值订单已经处理过，' . print_r($data, true));
            return $this->orderInfo;
        }

        //验证订单是否支付成功
        if($data['resultCode'] != '00') {
            $this->orderInfo['code'] = 200021;
            payLog('payerror.log', '（' . $this->retArr['code'] . '）' . '瞬付支付异步通知：订单支付失败，' . print_r($data, true));
            return $this->orderInfo;
        }

        $this->orderInfo['code']  = 0;
        $this->orderInfo['order_no']  = $data['orderNo'];
        $this->orderInfo['amount']    = strval($data['amount'] * 100);
//        $this->orderInfo['serial_no'] = '';

        return $this->orderInfo;
    }

    /**
     * @param array $data       前台发起支付的参数
     * @param string   $Md5key     商户密钥
     * @author wesker
     * @copyright 2017/11/27 11:00
     * @lastModifiedUser wesker
     * @lastModifiedTime 2017/11/27 15:00
     * @deprecate 组装参数，加密
     * @return string
     */
    static function array_to_sign($data, $merchantKey){
        ksort($data);
        return strtoupper(md5(self::json_encodes($data).$merchantKey)); #设置签名
    }

    /**
     * 支付初始数据配置数据库
     * @param int $payment_id 支付类型ID
     */
    static function setBaseConfig($payment_id){
        $data['name'] = '瞬付支付';
        $data['merchantID'] = 'SF171121171735410';//商户号
        $data['merchantKey'] = '59949B7D8177497624E2DBD223930AFE';//MD5秘钥
        $data['payType'] = [
            'wx'=>[
                'name'=>'微信支付',
                'payStr'=>'WX',
                'request_type'=>'1',
            ],
            'ali'=>[
                'name'=>'支付宝支付',
                'payStr'=>'ZFB',
                'request_type'=>'1',
            ],
            'qq'=>[
                'name'=>'QQ钱包支付',
                'payStr'=>'QQ',
                'request_type'=>'1',
            ],
        ];
        $serData = serialize($data);
        D('paymentconfig')->save(['config' => $serData], ['id' => $payment_id]);
    }

    /**
     * @return string
     * @author wesker
     * @copyright 2017/11/24
     * @lastModifiedUser wesker
     * @lastModifiedTime 2017/11/24
     * @deprecate 获取客户端IP
     */
    static function GetRemoteIP(){
        if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown"))
            $ip = getenv("HTTP_CLIENT_IP");
        else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown"))
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        else if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown"))
            $ip = getenv("REMOTE_ADDR");
        else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
            $ip = $_SERVER['REMOTE_ADDR'];
        else
            $ip = "unknown";
        return($ip);
    }

    /**
     * @param array  $row     前台发起支付的参数
     * @param string $signKey  商户密钥
     * @author wesker
     * @copyright 2017/11/21
     * @lastModifiedUser wesker
     * @lastModifiedTime 2017/11/21
     * @deprecate 组装参数，加密,校验
     * @return string
     */
    static function is_sign($row,$signKey){ #效验服务器返回数据
        $r_sign = $row['sign']; #保留签名数据
        $arr = array();
        foreach ($row as $key=>$v){
            if ($key !== 'sign'){ #删除签名
                $arr[$key] = $v;
            }
        }
        ksort($arr);
        $sign = strtoupper(md5(self::json_encodes($arr) . $signKey)); #生成签名
        if ($sign == $r_sign){
            return true;
        }else{
            return false;
        }
    }

    /**
     * @param array  $post_data 发起支付的参数
     * @param string $url       支付网关
     * @author wesker
     * @copyright 2017/11/21
     * @lastModifiedUser wesker
     * @lastModifiedTime 2017/11/21
     * @deprecate 发起支付
     * @return array
     */
    static function httpPost($url, $post_data) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        return $tmpInfo;
    }


    static function json_encodes($input){
        if(is_string($input)){
            $text = $input;
            $text = str_replace('\\', '\\\\', $text);
            $text = str_replace(
                array("\r", "\n", "\t", "\""),
                array('\r', '\n', '\t', '\\"'),
                $text);
            $text = str_replace("\\/", "/", $text);
            return '"' . $text . '"';
        }else if(is_array($input) || is_object($input)){
            $arr = array();
            $is_obj = is_object($input) || (array_keys($input) !== range(0, count($input) - 1));
            foreach($input as $k=>$v){
                if($is_obj){
                    $arr[] = self::json_encodes($k) . ':' . self::json_encodes($v);
                }else{
                    $arr[] = self::json_encodes($v);
                }
            }
            if($is_obj){
                $arr = str_replace("\\/", "/", $arr);
                return '{' . join(',', $arr) . '}';
            }else{
                $arr = str_replace("\\/", "/", $arr);
                return '[' . join(',', $arr) . ']';
            }
        }else{
            $input = str_replace("\\/", "/", $input);
            return $input . '';
        }
    }

    /**
     * 递归多维数组，进行urlencode
     * @param $array
     * @return mixed
     */
    static function urlencode_array($array) {
        foreach($array as $k => $v) {
            if(is_array($v)) {
                $array[$k] = self::urlencode_array($v);
            } else {
                $array[$k] = urlencode($v);
            }
        }
        return $array;
    }
}