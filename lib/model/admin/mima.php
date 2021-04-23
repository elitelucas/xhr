<?php
include_cache(S_PAGE . 'model' . DS . 'admin' . DS. 'drawalinfo.php');
include_cache(S_ROOT . 'core'. DS.  'class' . DS . 'PHPExcel' . DS. 'Exception.php');

class mima extends drawalinfo
{
//    public $url = "https://tlt.allinpay.com/aipg/ProcessServlet"; //请求接口
    public $url = "https://113.108.182.3/debugaipg/ProcessServlet"; //请求接口
    public $urla = "https://113.108.182.3/debugaipg/ProcessServlet"; //请求接口

    private $paySuccessCode = ['0000', '4000'];          //  代付成功code
    private $payUnknownCode = ['2000','2001','2003','2005','2007','2008','0003','0014', '1108'];          //  代付未知状态  需要轮询

    public $returnInfo = [
        'code' => 1,                 //10：数据获取成功，1失败  2 处理中
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'msg' => 0,            //接口返回信息
        'drawal_name' => '米曼代付',    //支付方式名称
        'serial_no' => '',            //第三方回调返回的第三方支付订单号（支付流水号）
        'account_cash_id' => '',            //提现表ID
        'user_id' => '',
        'nid' => 'mi_man_withdraw',
    ];


    //账户信息查询
    public function queryAccountInfo($data) {
        $postData = array(
            'INFO' => array(
                'TRX_CODE' => "300000",
                'VERSION' => "04",
                'DATA_TYPE' => "2",
                'LEVEL' => "9",
                'MERCHANT_ID' => strval($data['config']['merchantID']),
                'USER_NAME' => strval($data['config']['merchantID']) . "04",
                'USER_PASS' => strval($data['config']['password']),
                'REQ_SN' => $data['config']['merchantID']."_".$data['order_sn'],
            ),
            'ACQUERYREQ' => array(
                'ACCTNO' => '',
//                'ACCTNO' => strval($data['config']['merchantID']) . "000",
            )
        );
        $postData = $this->turnXml($postData,$data['config']['privateKey'],$data['config']['password']);
        $postData = str_replace("TRANS_DETAIL2", "TRANS_DETAIL",$postData);
        payLog('mima.txt',print_r($postData,true). "queryAccountInfo_postData");
        $return = $this->curlPost($postData,$this->url);
        if ($return['code'] == 200) {
            $return = $this->retSign($return['data'],$data['config']['publicKey']);
            return $return;
        }
        echo 'error';
        dump($return);
        die;
    }

    public function doWithdraw($data)
    {
//        payLog('mima.txt',print_r($data,true));
        $this->returnInfo['account_cash_id'] = $data['accountCashId'];
        $this->returnInfo['user_id'] = $data['user_id'];
        $this->returnInfo['payment_id'] = $data['payment_id'];
        $this->returnInfo['order_no'] = $data['order_sn'];
        $this->returnInfo['amount'] = $data['money'];
//        $data['order_sn'] = 'cz'.date("Ymdhis",time()).rand(111111,999999);

        $postData = array(
            'INFO' => array(
                'TRX_CODE' => "100014",
                'VERSION' => "03",
                'DATA_TYPE' => "2",
                'LEVEL' => "9",
                'USER_NAME' => strval($data['config']['merchantID']) . "04",
                'USER_PASS' => strval($data['config']['password']),
                'REQ_SN' => $data['config']['merchantID']."_".$data['order_sn'],
            ),
            'TRANS' => array(
                'BUSINESS_CODE' => '09900',
                'MERCHANT_ID' => strval($data['config']['merchantID']),
                'SUBMIT_TIME' => strval(date("YmdHis",time())),
                'ACCOUNT_NO' => strval($data['account']),
//                'ACCOUNT_NO' => '6227001447170048826',
//                'ACCOUNT_NO' => '6203024934580934',
                'ACCOUNT_NAME' => $data['name'],
//                'ACCOUNT_NAME' => $data['name']."000001",
                'ACCOUNT_PROP' => '0',
                'AMOUNT' => strval($data['money'] * 100),
            )
        );


        $postData = $this->turnXml($postData,$data['config']['privateKey'],$data['config']['password']);
        $postData = str_replace("TRANS_DETAIL2", "TRANS_DETAIL",$postData);

        try {
            payLog('mima.txt',print_r($postData,true). "doWithdraw_postData");
            $return = $this->curlPost($postData,$this->url);
            payLog('mima.txt',print_r($return,true). "doWithdraw_return");
            if ($return['code'] == 200) {
                $return = $this->retSign($return['data'],$data['config']['publicKey']);
                payLog('mima.txt',print_r($return,true). "doWithdraw_return_checkSign");

                $this->returnInfo['serial_no'] = $return['INFO']['REQ_SN'];

                $infoCode = $return['INFO']['RET_CODE'];
                $qCode = isset($return['QTRANSRSP']['RET_CODE'])?$return['QTRANSRSP']['RET_CODE']:$infoCode;
                if(in_array($infoCode,$this->paySuccessCode) && in_array($qCode, $this->paySuccessCode)) {          //成功
                    $this->returnInfo['code'] = 10;
                    return $this->returnInfo;
                }

                if(!in_array($infoCode, $this->payUnknownCode) && !in_array($qCode, $this->payUnknownCode)) {           //失败处理
                    $this->returnInfo['code'] = 1;
                    return $this->returnInfo;
                }

                if(in_array($infoCode, $this->payUnknownCode) || $infoCode == 1000) {        //轮询处理
                    $this->returnInfo['code'] = 2;
                    $this->queryOrder($this->returnInfo,true);
                }

//                isset($return['QTRANSRSP']['RET_CODE'])?$code = $return['QTRANSRSP']['RET_CODE']:$code = $return['INFO']['RET_CODE'];
//                $checkCode = ($code == '0000' || $code == '4000' || $code == '2000' || $code == '2001' || $code == '2003' || $code == '2005' || $code == '2007' || $code == '2008' || $code == '1108' || $code == '1000');

            } else {
                $this->queryOrder($this->returnInfo,true);
            }

            payLog('mima.txt',print_r($this->returnInfo,true). "doWithdraw_returnInfo");
            return $this->returnInfo;
        } catch (\Exception $e) {
            payLog('mima.txt',$e. "====91");
            var_dump($e);
            return $e;
        }
    }

    public function turnXml($postData,$key,$password,$rootNodeName = 'AIPG', &$xml=null)
    {
//        payLog('mima.txt',$key. print_r($postData,true));
        $xmlSignSrc = mb_convert_encoding(str_replace('<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0" encoding="GBK"?>', $this->toXml($postData, $rootNodeName, $xml)), 'GBK', 'UTF-8');
        $xmlSignSrc=str_replace("TRANS_DETAIL2", "TRANS_DETAIL",$xmlSignSrc);
//        payLog('mima.txt',print_r($xmlSignSrc,true));
        $pKeyId = openssl_pkey_get_private($key, $password);
        openssl_sign($xmlSignSrc, $signature, $pKeyId);
        openssl_free_key($pKeyId);

        $postData['INFO']['SIGNED_MSG'] = bin2hex($signature);
        $xmlSignPost = $this->toXmlGBK($postData, 'AIPG');
//        payLog('mima.txt',print_r($postData,true) . "+++57");

        return  $xmlSignPost;
    }

    public function retSign($xmlResponse,$pulicKey)
    {
        $signature = '';
        if (preg_match('/<SIGNED_MSG>(.*)<\/SIGNED_MSG>/i', $xmlResponse, $matches)) {
//            payLog('mima.txt', print_r($matches, true) . "+++++70++++");
            $signature = $matches[1];
        }

        $xmlResponseSrc = preg_replace('/<SIGNED_MSG>.*<\/SIGNED_MSG>/i', '', $xmlResponse);
        $xmlResponseSrc1 = mb_convert_encoding(str_replace('<', '&lt;', $xmlResponseSrc), "UTF-8", "GBK");
        $pubKeyId = openssl_get_publickey($pulicKey);
        $flag = (bool)openssl_verify($xmlResponseSrc, hex2bin($signature), $pubKeyId);
        openssl_free_key($pubKeyId);

        //echo '<br/>'+$flag;
        if ($flag) {
//            echo '<br/>Verified: <font color=green>Passed</font>.';
            // 变成数组，做自己相关业务逻辑
            $xmlResponse = str_replace('<?xml version="1.0" encoding="GBK"?>', '<?xml version="1.0" encoding="UTF-8"?>', $xmlResponseSrc);
            payLog('mima.txt',print_r($xmlResponse,true). "checkSign_xml_init_encode");

            $xmlResponse = mb_convert_encoding($xmlResponse, 'UTF-8', 'GBK');
            payLog('mima.txt',print_r($xmlResponse,true). "checkSign_xml_UTF8");

            $results = $response = json_decode(json_encode(simplexml_load_string($xmlResponse)),true);
            payLog('mima.txt',print_r($results,true). "checkSign_arr");
//            echo "<br/><br/><font color=blue>-------------华丽丽的分割线--------------------</font><br/><br/>";
//		    echo $results;
            return $results;
        }
    }

    public function parseString( $sXml , $bOptimize = FALSE) {
        $oXml = new XMLReader();
        payLog('mima.txt',print_r($sXml). "++++100+++");
        $this -> bOptimize = (bool) $bOptimize;
        $oXml->XML($sXml);

        // Parse Xml and return result
        return $this->parseXml($oXml);

    }

    protected function parseXml( XMLReader $oXml ) {
        $aAssocXML = null;

        $iDc = -1;
//payLog('mima.txt',print_r());
        while($oXml->read()){
            switch ($oXml->nodeType) {

                case XMLReader::END_ELEMENT:

                    if ($this->bOptimize) {
                        $this->optXml($aAssocXML);
                    }
                    return $aAssocXML;

                case XMLReader::ELEMENT:

                    if(!isset($aAssocXML[$oXml->name])) {
                        if($oXml->hasAttributes) {
                            $aAssocXML[$oXml->name][] = $oXml->isEmptyElement ? '' : $this->parseXML($oXml);
                        } else {
                            if($oXml->isEmptyElement) {
                                $aAssocXML[$oXml->name] = '';
                            } else {
                                $aAssocXML[$oXml->name] = $this->parseXML($oXml);
                            }
                        }
                    } elseif (is_array($aAssocXML[$oXml->name])) {
                        if (!isset($aAssocXML[$oXml->name][0]))
                        {
                            $temp = $aAssocXML[$oXml->name];
                            foreach ($temp as $sKey=>$sValue)
                                unset($aAssocXML[$oXml->name][$sKey]);
                            $aAssocXML[$oXml->name][] = $temp;
                        }

                        if($oXml->hasAttributes) {
                            $aAssocXML[$oXml->name][] = $oXml->isEmptyElement ? '' : $this->parseXML($oXml);
                        } else {
                            if($oXml->isEmptyElement) {
                                $aAssocXML[$oXml->name][] = '';
                            } else {
                                $aAssocXML[$oXml->name][] = $this->parseXML($oXml);
                            }
                        }
                    } else {
                        $mOldVar = $aAssocXML[$oXml->name];
                        $aAssocXML[$oXml->name] = array($mOldVar);
                        if($oXml->hasAttributes) {
                            $aAssocXML[$oXml->name][] = $oXml->isEmptyElement ? '' : $this->parseXML($oXml);
                        } else {
                            if($oXml->isEmptyElement) {
                                $aAssocXML[$oXml->name][] = '';
                            } else {
                                $aAssocXML[$oXml->name][] = $this->parseXML($oXml);
                            }
                        }
                    }

                    if($oXml->hasAttributes) {
                        $mElement =& $aAssocXML[$oXml->name][count($aAssocXML[$oXml->name]) - 1];
                        while($oXml->moveToNextAttribute()) {
                            $mElement[$oXml->name] = $oXml->value;
                        }
                    }
                    break;
                case XMLReader::TEXT:
                case XMLReader::CDATA:

                    $aAssocXML[++$iDc] = $oXml->value;

            }
        }

//        var_dump(222);var_dump($aAssocXML);
        return $aAssocXML;
    }

    public function toXmlGBK($data, $rootNodeName = 'AIPG', &$xml=null)
    {
        return mb_convert_encoding(str_replace('<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0" encoding="GBK"?>', $this->toXml($data, $rootNodeName, $xml)), 'GBK', 'UTF-8');
    }

    public function toXml($data, $rootNodeName = 'data', &$xml=null)
    {
        // turn off compatibility mode as simple xml throws a wobbly if you don't.
        if ( ini_get('zend.ze1_compatibility_mode') == 1 ) ini_set ( 'zend.ze1_compatibility_mode', 0 );
        if ( is_null( $xml ) ) {
            $xml = simplexml_load_string(stripslashes("<?xml version='1.0' encoding='UTF-8'?><$rootNodeName></$rootNodeName>"));
        }

//        payLog('mima.txt',print_r($data,true). "+++72");

        // loop through the data passed in.
        foreach( $data as $key => $value ) {

            // no numeric keys in our xml please!
            $numeric = false;
            if ( is_numeric( $key ) ) {
                $numeric = 1;
                $key = $rootNodeName;
            }

            // delete any char not allowed in XML element names
            $key = preg_replace('/[^a-z0-9\-\_\.\:]/i', '', $key);

            //check to see if there should be an attribute added (expecting to see _id_)
            $attrs = false;

            //if there are attributes in the array (denoted by attr_**) then add as XML attributes
            if ( is_array( $value ) ) {
                foreach($value as $i => $v ) {
                    $attr_start = false;
                    $attr_start = stripos($i, 'attr_');
                    if ($attr_start === 0) {
                        $attrs[substr($i, 5)] = $v; unset($value[$i]);
                    }
                }
            }


            // if there is another array found recursively call this function
            if ( is_array( $value ) ) {
                if ( $this->is_assoc( $value ) || $numeric ) {
                    // older SimpleXMLElement Libraries do not have the addChild Method
                    if (method_exists('SimpleXMLElement','addChild')) {
                        $node = $xml->addChild( $key, null);
                        if ($attrs) {
                            foreach($attrs as $key => $attribute) {
                                $node->addAttribute($key, $attribute);
                            }
                        }
                    }

                }else{
                    $node =$xml;
                }

                // recrusive call.
                if ( $numeric ) $key = 'anon';

                $this->toXml( $value, $key, $node );
            } else {

                // older SimplXMLElement Libraries do not have the addChild Method
                if (method_exists('SimpleXMLElement','addChild')) {
                    $childnode = $xml->addChild( $key, $value);
                    if ($attrs) {
                        foreach($attrs as $key => $attribute) {
                            $childnode->addAttribute($key, $attribute);
                        }
                    }
                }
            }
        }

//        if ($this->bFormatted) {
//            // if you want the XML to be formatted, use the below instead to return the XML
//            $doc = new DOMDocument('1.0');
//            $doc->preserveWhiteSpace = false;
//            @$doc->loadXML( $this->fixCDATA($xml->asXML()) );
//            $doc->formatOutput = true;
//
//            return $doc->saveXML();
//        }

        // pass back as unformatted XML
//        payLog('mima.txt',print_r($xml->asXML(),true). "+++149");
        return $xml->asXML();
    }

    public function is_assoc( $array ) {
        return (is_array($array) && 0 !== count(array_diff_key($array, array_keys(array_keys($array)))));
    }

    public function curlPost($data,$url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'cURL API Utility/1.0 (compatible;)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 18);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $error_no = curl_errno($ch);
        $error_msg = curl_error($ch);

        payLog('mima.txt',print_r([$error_no,$error_msg,$response],true). "curlPost");

        return array('code' => $httpCode, 'data' => $response);
    }

    public function queryOrder($data,$channel = false) {
        $config = $this->neededData($data['payment_id']);
        $params = array(
            'INFO' => array(
                'TRX_CODE' => '200004',
                'VERSION' => '03',
                'DATA_TYPE' => '2',
                'MERCHANT_ID' => $config['merchantID'],
                'USER_NAME' => $config['merchantID']."04",
                'USER_PASS' => $config['password'],
                'REQ_SN' => 'ls'.date('Ymdhis',time()).rand(10000,99999),
            ),
            'QTRANSREQ' => array(
                'QUERY_SN' =>$config['merchantID']."_". $data['order_no'],
                'MERCHANT_ID' => $config['merchantID'],
            ),
        );
        $params = $this->turnXml($params,$config['privateKey'],$config['password']);
        $params = str_replace("TRANS_DETAIL2", "TRANS_DETAIL",$params);

        payLog('mima.txt',print_r($params,true). "queryOrder_postData");
        $return = $this->curlPost($params,$this->url);

        $return = $this->retSign($return['data'],$config['publicKey']);
        payLog('mima.txt',print_r($return,true). "queryOrder_return_checkSign");
//        $return['INFO']['RET_CODE'] = '2000';
//        unset($return['QTRANSRSP']['QTDETAIL']['RET_CODE']);
//        $return['INFO']['RET_CODE'] = 1002;
//

        $infoCode = $return['INFO']['RET_CODE'];
        $qCode = isset($return['QTRANSRSP']['QTDETAIL']['RET_CODE'])?$return['QTRANSRSP']['QTDETAIL']['RET_CODE']:$infoCode;
        $this->returnInfo['msg'] = $return['INFO']['ERR_MSG'];
        if(in_array($infoCode, $this->paySuccessCode) && in_array($qCode, $this->paySuccessCode)) {     //成功处理
            $this->returnInfo['code'] = 10;
            return $this->returnInfo;
        }

        //1001  报文解释错       1000报文内容检查错或者处理错  重新发起查询
        //系统处理失败    系统处理失败  无此交易    商户审核不通过     订单不通过受理     不通过复核       返回失败
        if(in_array($infoCode, ['0001','0002','1002','2002','2004','2006'])) {
            $this->returnInfo['code'] = 1;
            return $this->returnInfo;
        }

        if(in_array($infoCode, $this->paySuccessCode) && !in_array($qCode, $this->paySuccessCode)) {            //失败处理
            $this->returnInfo['code'] = 1;
            return $this->returnInfo;
        }

        $code = $return['INFO']['RET_CODE'];
        $checkcode = ($code == "2000" || $code == "2001" || $code == "2003" || $code == "2005" || $code == "2007" || $code == "2008" || $code == "1000" || $code == "1002" || $code == "2002" || $code == "2004" || $code == "2006");
        if ($checkcode) {
            $this->returnInfo['code'] = 2;
            if ($channel) {
                $redis = initCacheRedis();
                $redisinfo = $this->redisInfo($this->returnInfo);
                $redisinfo['checktime'] = time();
                $redisinfo['times'] = '0';
                $redis->hSet("autodrawallist",$this->returnInfo['order_no'],json_encode($redisinfo));
                $aaaa = $redis->hGet("autodrawallist",$this->returnInfo['order_no']);
                deinitCacheRedis($redis);
            } else {
                if ($code == '1002' && $data['times']>5 && (time() - $data['checktime'] > 3000)) {
                    $this->returnInfo['code'] = 1;
                } else {
                    $data['times']= $data['times'] + 1;
                    $data['dealtime'] = time();
                    $redis = initCacheRedis();
                    $redis->hset("autodrawallist",$data['order_no'],json_encode($data));
                    deinitCacheRedis($redis);
                }
            }
        } else {
            $this->returnInfo['code'] = 1;
        }
//        payLog('mima.txt',print_r($this->returnInfo,true). "====428");
        return $this->returnInfo;

    }

    /*

        $data['config'] = [
            'merchantID' => '200393000009128',
            'password' => 111111,
            'privateKey' => '-----BEGIN ENCRYPTED PRIVATE KEY-----
MIIC1DBOBgkqhkiG9w0BBQ0wQTApBgkqhkiG9w0BBQwwHAQI1Yr9FW/y5JECAggA
MAwGCCqGSIb3DQIJBQAwFAYIKoZIhvcNAwcECJYZmNyDXhJxBIICgHB1tLEKENh/
zqo5YKx13UG6pxYopaaMslVM1Y7vgvj2qLcctquy0nvxASJRZyuf3wtoKUOiS0YD
pw50bseu/ihhihKxR+jIX0a6f5RUfaxOa3Iwmh71ovRpCo8xcz7pyA543NppIu2a
6pH2CAGMMmOegtoLHpXlEDN4wd2+LUhuevejsUx0lJ843gpA3Wivc957jqghegKD
7EOxlExF8H/KLEJyevAOl2pU6W2PslzKTC/rQzI02EJSV9D2W2Smew2DPoeptA3C
S+w71aA5zySQzRd3s/mTmfKmolX+KPvvBYHsf34YCoIeBHJAoxKFUzfb/DwacuzC
ErIdKVcRjapUctsQ8+tKzkICTMPLhKDhn1y2HzsLXku4aQtGjk5Vqy2tavwNcyAP
ap24S1cpM8VXwu8l+1FsBtn5glwieF9qjiwdnLe3S/dwHukHXYgQvu+hJjhx9dEr
OKYOZIW/zQ2LElyrmxEBA7As5PfgQuMffAtMILBCBHurrsAIUiqjVMrolfhzcZA7
/T2bgyEjJ4pzLzGa0vK4kJH9Z+iJ+k1yqt1fcuhcmJ9enUWBXeko37s10fifNdN5
45AOvJ5u5BH9gn7R8RGe3qy8UpKsVvxXCIzjR4noDKpI77OHfYTY6AHaUkJqRb7x
5jALTczRlJhl0+SZBZqzTTjGw3uzHIENrW0s/I2NHcqR10r0Ry99tASrQqgJKGlZ
58Uy/ajBJmOvowIS8xwMtywiZqZ+Qyx3gjIodBv3l++z5bDk+JuPXeclA7+M4v6K
uZ3HGnNMR67cJJ3xGvUfD6MFUzNzNX7mOovSxyoaRG7AMYh7PTEvehXw2YM1pxRB
USIPEEyW3zE=
-----END ENCRYPTED PRIVATE KEY-----',
            'publicKey' => '-----BEGIN CERTIFICATE-----
MIIDgzCCAmugAwIBAgIIWEDpssGA1GcwDQYJKoZIhvcNAQEFBQAwPjEUMBIGA1UE
AwwLQ0FAQWxsaW5wYXkxGTAXBgNVBAoMEEFsbGlucGF5IFBheW1lbnQxCzAJBgNV
BAYTAkNOMB4XDTExMTAyMDA5MjI1MFoXDTE5MTAxOTA5MjI1MFowRDEVMBMGA1UE
AwwMYWxsaW5wYXktcGRzMREwDwYDVQQKDAhhbGxpbnBheTELMAkGA1UECAwCU0gx
CzAJBgNVBAYTAkNOMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAiJcr
fF5oWH4CFBgTP9PYlaRyQbG31LA3yvc+w2+yrWWrGtEuw7xJqKKu1KrAjfPpnH+f
/KOm0rH+8Uwd+w3yD7SpT2I7w+XtdMYhzPSdW53O6h3ZYXwnDXNX5ubV6pyjMRnR
24FD+thgirb6K0z9XswmUl4i8cns6jjdfGmliQoKn38RaYjG7KXw1BwgOfW8ghjN
X5K86tIxLT8o2KyyEazlSe2z0FAWgjQWDswLQcb/i3Afr0VirVdryO8QE+c+92nN
eIzQaO1V+Gx8Ddk92U7Ree5LxAOhrV8cyFnFqm8d65rhr1g0OxLZdmhtNXvGDEU8
5G8eS/O1dKVoGpd54QIDAQABo38wfTAdBgNVHQ4EFgQU1W8D338mmHgrzfcFYcct
AVhizvMwDAYDVR0TAQH/BAIwADAfBgNVHSMEGDAWgBSoHV6yIwTm66Q0ap05hT+P
o7Mw1TAOBgNVHQ8BAf8EBAMCBeAwHQYDVR0lBBYwFAYIKwYBBQUHAwIGCCsGAQUF
BwMEMA0GCSqGSIb3DQEBBQUAA4IBAQAOR0HWDiXNi5BSPnNm6xoJBA/AfXDXXjZ8
MCjzVmTMsvGoUcLyGC0wxGnWeVBNpFJrDf/IpB+bQYqF603kU3xdsURYcigMUbUV
RWGRKiTP1Fim11wf531ufIr1uC92anTIcO3XM3nnib/uVkwnsxPTiIxSBAluuKbn
IfPdhrMYXU9mffEZQUNCxYf/VJ8CLBpR6ES33IM/eoMksyG009z9yB0zMeDiaTHy
ySQNSjHZTFuV4mvB9cO4rPzt2AcJOYt4xMpfPqoTnZm7D7MM+ORQqORjjKPCd1x9
TxkSIcvhMlHAGnGDdU8VM/TQ/0NN3BHyK9R3ccOdAc6cYGj344kj
-----END CERTIFICATE-----
',
        ];

     * */
}
