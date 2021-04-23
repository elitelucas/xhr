<?php

/**
 * 爱益聚合支付
 */

class Iyibank {

    private $iypay_url = 'https://www.iyibank.com/pay/gateway';

    /**
     * 生成签名
     */
    private function sign($msgData,$iy_secret) {
        ksort($msgData); //进行排序
        $buff = "";
        foreach ($msgData as $x=>$x_value) {
            if($x != "sign" &&  $x_value != ""&& !is_array($x_value)){
                $buff .= $x . "=" .  $x_value . "&";
            }
        }
        return strtoupper(md5($buff."key=" . $iy_secret));
    }

    /**
     * 支付（post提交xml数据）
     */
    public function payment($msgData,$iy_secret) {
        $msgData['sign'] = $this->sign($msgData,$iy_secret);

        $src = "<xml>";
        foreach ($msgData as $x=>$x_value){
            $src .="<".$x .">".  $x_value . "</" .$x .">";
        }
        $src .="</xml>";

        return $this->request($src, $this->iypay_url);
    }

    /**
	 * 以post方式提交xml到对应的接口url
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	private function request($xml, $url){
        $header[] = "Content-type: text/xml";        //定义content-type为xml,注意是数组
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            print curl_error($ch);
        }
        curl_close($ch);
        return $response;
	}

    /**
     * 校验签名
     * @param type $msgData
     * @return int
     */
    public function verifySign($msgData,$iy_secret) {
        ksort($msgData); //对数组进行排序
        $postbuff = "";
        foreach ($msgData as $x=>$x_value){
            if($x != "sign" &&  $x_value != ""&& !is_array($x_value)){
                $postbuff .= $x . "=" .  $x_value . "&";
            }
        }
        $preEncodeStr = strtoupper(md5($postbuff."key=" . $iy_secret));

        if(strtoupper($preEncodeStr) == strtoupper($msgData['sign'])){
            return 1; //成功
        }else{
            return 0; //失败
        }
    }

}
