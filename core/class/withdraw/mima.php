<?php

class mima
{
    public $url = "http://113.108.182.3:8083/aipg/ProcessServlet"; //请求接口
    public $bOptimize = true;

    public $returnInfo = [
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'bank_name' => '曼巴代付',    //支付方式名称
        'serial_no' => '',            //第三方回调返回的第三方支付订单号（支付流水号）
        'account_cash_id' => '',            //提现表ID
        'user_id' => ''            //提现表ID
    ];

    public function doWithdraw($data)
    {
//        payLog('mima.txt',print_r($data,true));
        $this->returnInfo['account_cash_id'] = $data['accountCashId'];
        $this->returnInfo['user_id'] = $data['user_id'];
        $this->returnInfo['payment_id'] = $data['payment_id'];
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
                'BUSINESS_CODE' => '09400',
                'MERCHANT_ID' => strval($data['config']['merchantID']),
                'SUBMIT_TIME' => strval(date("YmdHis",time())),
//                'ACCOUNT_NO' => strval($data['account']),
                'ACCOUNT_NO' => '6227001447170048826',
                'ACCOUNT_NAME' => $data['name'],
                'ACCOUNT_PROP' => '0',
                'AMOUNT' => strval($data['money'] * 100),
            )
        );

        $postData = $this->turnXml($postData,$data['config']['privateKey'],$data['config']['password']);

//        payLog('mima.txt',print_r($postData,true). "+++33");

        $postData = str_replace("TRANS_DETAIL2", "TRANS_DETAIL",$postData);
        $return = $this->curlPost($postData,$this->url);
        $return = $this->retSign($return['data'],$data['config']['publicKey']);
        $order_no = explode('_',$return['INFO']['REQ_SN'])[1];
        $this->returnInfo['order_no'] = $order_no;
        $this->returnInfo['serial_no'] = $return['INFO']['REQ_SN'];
        $this->returnInfo['amount'] = $data['money'];
        if ($return['INFO']['RET_CODE'] == "0000") {
            $this->returnInfo['code'] = 0;
        }

        payLog('mima.txt',print_r($this->returnInfo,true) . "+++25");
        return($this->returnInfo);



    }

    public function turnXml($postData,$key,$password,$rootNodeName = 'AIPG', &$xml=null)
    {
//        payLog('mima.txt',$key. print_r($postData,true));
        $xmlSignSrc = mb_convert_encoding(str_replace('<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0" encoding="GBK"?>', $this->toXml($postData, $rootNodeName, $xml)), 'GBK', 'UTF-8');
        $xmlSignSrc=str_replace("TRANS_DETAIL2", "TRANS_DETAIL",$xmlSignSrc);
//        payLog('mima.txt',print_r($xmlSignSrc,true));
        $pKeyId = openssl_pkey_get_private($key, $password);
//        payLog('mima.txt',$pKeyId. "+++47");
        openssl_sign($xmlSignSrc, $signature, $pKeyId);
        openssl_free_key($pKeyId);

        $postData['INFO']['SIGNED_MSG'] = bin2hex($signature);
//        payLog()
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
        payLog('mima.txt', $pubKeyId . "++++80++++" . $flag);
        openssl_free_key($pubKeyId);
        //echo '<br/>'+$flag;
        if ($flag) {
//            echo '<br/>Verified: <font color=green>Passed</font>.';
payLog('mima.txt',print_r($xmlResponseSrc,true). "++++85+++");

            // 变成数组，做自己相关业务逻辑
            $xmlResponse = mb_convert_encoding(str_replace('<?xml version="1.0" encoding="GBK"?>', '<?xml version="1.0" encoding="UTF-8"?>', $xmlResponseSrc), 'UTF-8', 'GBK');

//            $results = $this->parseString($xmlResponse, TRUE);
            $results = $response = json_decode(json_encode(simplexml_load_string($xmlResponse)),true);
            payLog('mima.txt',print_r($results,true). "++++91+++");
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
        curl_setopt($ch, CURLOPT_USERAGENT, 'cURL API Utility/1.0 (compatible;)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return array('code' => $httpCode, 'data' => $response);
    }

}
