<?php
/**
 * 加/解密(生成的加密字符串是一致的)(加解密的key必须一致)
 * @param $string 加解密字符串
 * @param $operation 操作方法 D解密 E加密
 * @param string $key 加解密key(加解密的key必须一致)
 * @return bool|mixed|string
 */
function authcode($string, $operation){
	$key=C("en_key");
    $key=md5($key); 
    $key_length=strlen($key);
    $string=$operation=='D'?base64_decode($string):substr(md5($string.$key),0,8).$string;
    $string_length=strlen($string);
    $rndkey=$box=array();
    $result='';
    for($i=0;$i<=255;$i++){
        $rndkey[$i]=ord($key[$i%$key_length]);
        $box[$i]=$i;
    }
    for($j=$i=0;$i<256;$i++){
        $j=($j+$box[$i]+$rndkey[$i])%256;
        $tmp=$box[$i];
        $box[$i]=$box[$j];
        $box[$j]=$tmp;
    }
    for($a=$j=$i=0;$i<$string_length;$i++){
        $a=($a+1)%256;
        $j=($j+$box[$a])%256;
        $tmp=$box[$a];
        $box[$a]=$box[$j];
        $box[$j]=$tmp;
        $result.=chr(ord($string[$i])^($box[($box[$a]+$box[$j])%256]));
    }
    if($operation=='D'){
        if(substr($result,0,8)==substr(md5(substr($result,8).$key),0,8)){
            return substr($result,8);
        }else{
            return'';
        }
    }else{
        return str_replace('=','',base64_encode($result));
    }
}

/*加密*/
function encrypt($string){
    return $string;
	return authcode($string,"E");
}

/*解密*/
function decrypt($string){
    return $string;
	$destring=authcode($string,"D");
	return !empty($destring)?$destring:$string;
}
?>