<?php
/**
 * Created by Kevin.
 * User: Kevin
 * Date: 2018/02/26
 * Time: 14:00
 * desc: 处理线上支付无参数路由支付回调处理方法：通过url调用执行该文件，获取相关支付确认信息后，通过curl转发到带参数路由的api接口
 */

define('S_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
$db = getconn();

$data = [];           //支付回调数据
$payData = '';        //支付回传的支付参数，规定：使用"_"将支付名称与支付ID连接起来，如魔宝云支付：'mobaoyun_123'
$payType = '';        //支付类型
$payment_id = '';     //支付方式ID

//不同的支付方式获取数据的方式可能不同，根据不同的支付，添加不同的获取回调数据的方法，如：$_GET\$_POST\$_REQUEST\（php://input）
$postData = file_get_contents("php://input");  //获取post参数字符串类型数据

//数据处理，不同的支付方式，可能数据处理方式不同
if (!empty($postData)) {
    parse_str($postData, $data);
}else {
    $data = $_REQUEST;
}
payLog('callbackReturn.txt',print_r($data,true).'---26---');

$payment_id = explode('_',$data['sdorderno']);
$payment_id = $payment_id[1];
$sql = "select config from un_payment_config where id =".$payment_id;
payLog('callbackReturn.txt',$sql.'---26---');
$key = $db->result($sql);
payLog('callbackReturn.txt', '--35--' . print_r($data, true));
$key = unserialize($key['config'])['merchantKey'];
$status=$_GET['status'];
$customerid=$_GET['customerid'];
$sdorderno=$_GET['sdorderno'];
$total_fee=$_GET['total_fee'];
$paytype=$_GET['paytype'];
$sdpayno=$_GET['sdpayno'];
$remark=$_GET['remark'];
$sign=$_GET['sign'];

$mysign=md5('customerid='.$customerid.'&status='.$status.'&sdpayno='.$sdpayno.'&sdorderno='.$sdorderno.'&total_fee='.$total_fee.'&paytype='.$paytype.'&'.$key);

if($sign==$mysign){
    if($status=='1'){
        echo '付款成功';
    } else {
        echo '付款错误';
    }
} else {
    echo '验证码错误...';
}

////var_dump($data);
////var_dump($payment_id);
//if (!empty($data) && !empty($payment_id)) {
//
//    payLog('curlRecharge.log', '异步充值url转接参数通知数据：' . print_r($data, true));  //日志，记录转接数据
//
//    $ret = httpPostApi($data, $payment_id);   //异步充值通知转接
//    payLog('callbackReturn.txt',print_r($ret,true).'----73--');
//
//    echo $ret;  //输出结果数据
//}
//
//


/**
 * 充值异步转接接口调用，post数据转发，表单提交数据库格式
 * @param array $payData  需要转发的数据
 * @param int $paymentId  线上支付方式ID
 * @return mixed|number   返回数据
 */
function httpPostApi($payData, $paymentId)
{
    //路由api接口
    $payUrl = "https://".$_SERVER['HTTP_HOST']."/?m=api&c=recharge&a=rechargeNotify&payment_id=" . $paymentId;
    
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL, $payUrl);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response=curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


    curl_close($ch);
    if ($httpCode == 200) {
        return $response;
    } else {
        return 0;
    }
}

/**
 * 支付日志
 * @param  string $fileName 文件名,如：payerror.log或者pay/error.log
 * @param  array $data 数据
 */
function payLog($fileName, $data)
{
    $pathLog = S_ROOT . 'caches' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($pathLog)) {
        if (filesize($pathLog) >= 10000000) {
            $new_file = dirname($pathLog) . '/' . date('Y_m_d_H_i_s').'_'  . basename($pathLog);
            copy($pathLog,$new_file);
            file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $data . "\n");
        }else {
            file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $data . "\n", FILE_APPEND);
        }
    }else {
        file_put_contents($pathLog, '<----' . date('Y-m-d H:i:s').'---->' . $data . "\n", FILE_APPEND);
    }
}
