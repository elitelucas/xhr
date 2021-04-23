<?php
/**
 * Created by PhpStorm.
 * User: HCHT-KF20
 * Date: 2017/2/16
 * Time: 10:36
 */
class XunHuiBao
{
    var $requestUrl = "http://pay.x6pay.com:8082/posp-api/passivePay";

    /*
     * 生成请求签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    function buildRequestMySign($post_data,$merchantKey)
    {
        ksort($post_data);
        $a = '';
        foreach($post_data as $x=>$x_value)
        {
            $a=$a.$x."=".iconv('UTF-8', 'GB2312',$x_value)."&";
        }
        $b = md5($a.$merchantKey);
        $c = $a.'signature'.'='.$b;
        return $c;
    }
    /*
     * 验证返回的签名结果
     * @param $para_sort 已排序要签名的数组
     * return 签名结果字符串
     */
    function verifyRequestMySign($post_data,$merchantKey)
    {
        ksort($post_data);
        $a='';
        foreach($post_data as $x=>$x_value)
        {
            if($x_value != ''){
                $a=$a.$x."=".$x_value."&";
            }
        }
        $b = md5($a.$merchantKey);

        return strtoupper($b);
    }


    /*
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp 请求参数数组
     */
    function buildRequest($sign,$url)
    {
        $ch=curl_init((string)$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);//使用curl_setopt获取页面内容或提交数据，有时候希望返回的内容作为变量存储，而不是直接输出，这时候希望返回的内容作为变量
        curl_setopt($ch,CURLOPT_TIMEOUT,30);//30秒超时限制
        curl_setopt($ch,CURLOPT_HEADER,false);//将文件头输出直接可见。
        curl_setopt($ch,CURLOPT_POST,true);//设置这个选项为一个零非值，这个post是普通的application/x-www-from-urlencoded类型，多数被HTTP表调用。
        curl_setopt($ch,CURLOPT_POSTFIELDS,$sign);//post操作的所有数据的字符串。
        $data = curl_exec($ch);//抓取URL并把他传递给浏览器
        curl_close($ch);//释放资源
        return iconv('GB2312', 'UTF-8', $data);
    }

}