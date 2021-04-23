<?php


include_cache(S_CORE . 'class' . DS . 'pay' . DS . 'payinfo' . '.php');

class zhifupingfangPay extends PayInfo
{
    //请求接口Url

    public $url = 'http://gateway.zfpfpay.com/simple-web-gateway/scan/apply';
    public $payName = '智付平方';   //接口名称
    private $logFile = 'zhifupingfang.txt';

    private $host;
    private $port;
    private $path;
    private $method;
    private $postdata;
    private $cookies = [];
    private $referer;
    private $accept = 'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*';
    private $accept_encoding = 'gzip';
    private $accept_language = 'en-us';
    private $user_agent = 'Incutio HttpClient v0.9';
    private $timeout = 30;
    private $use_gzip = true;
    private $persist_cookies = true;
    private $persist_referers = true;
    private $handle_redirects = true;
    private $max_redirects = 5;
    private $headers_only = false;
    private $username;
    private $password;
    private $status;
    private $headers = array();
    private $content = '';
    private $errormsg;
    private $redirect_count = 0;
    private $cookie_host = '';

    //获取支付返回数据格式
    public $retArr = [               //支付信息返回格式
        'code' => 1,             //0:数据获取成功，其他数字，数据获取失败
        'msg' => '',             //返回的提示信息
        'data' => []             //返回数据
    ];

    //回调处理返回数据格式
    public $orderInfo = [            //异步验签结果返回格式
        'code' => 1,                 //0：数据获取成功，其他数字，数据获取失败
        'bank_num' => 277000,        //银行区分号（不同支付的前三位不同）
        'order_no' => '',            //后台数据库支付订单号
        'amount' => 0,               //支付金额
        'ret_error' => 0,            //回调处理失败时，返回接口字符串
        'ret_success' => 'SUCCESS',  //回调处理成功时，返回接口字符串
        'bank_name' => '智付平方',    //支付方式名称
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
            $this->retArr['code'] = 277071;
            $this->retArr['msg']  = '支付订单生成失败';
            payLog($this->logFile, '（' . $this->retArr['code'] . '）' . $this->payName . '订单生成失败，' . print_r($data, true));
            return $this->retArr;
        }

        //获取配置支付信息
        $config = unserialize($data['config']);

        $callbackurl = $config['callbackurl']?$config['callbackurl']:$_SERVER['HTTP_HOST'];

        if (empty($data['pay_type']) || empty($config['payType'][$data['pay_type']])) {
            $this->retArr['code'] = 277072;
            $this->retArr['msg']  = '支付类型不存在';
            payLog($this->logFile, '（' . $this->retArr['code'] . '）' . $this->payName . '银行类型不存在，' . print_r($data, true));
            return $this->retArr;
        }

        $payStr = $config['payType'][$data['pay_type']]['payStr'];
        $post_data = array(
            'merchantNo' => $config['merchantID'],
            'orderPrice' => number_format($data['money'],2,'.',''),
            'outOrderNo'      => $orderInfo,
            'tradeType'      => $payStr,
            'tradeTime'      => date('YmdHis'),
            'goodsName'      => 'zhifupingfang_recharge',
//            'tradeIp'      => ip(),
            'tradeIp'      => '183.15.179.49',
            'returnUrl'      => "https://".$callbackurl."/beeePayOk.php",
            'notifyUrl'      => "https://".$callbackurl."/rechargeNotify.php",
            'remark'      => "zhifupingfang_".$data['payment_id'],
        );

        $post_data['sign'] = $this->getSign($post_data, $config['merchantKey']);
        payLog($this->logFile,print_r($post_data,true).'----post_data');

        //type =2返回html跳转页面数
        $curlData = $this->httpPost($post_data, $this->url);
        payLog($this->logFile,print_r($curlData,true).'----curl_return');

        $resData = json_decode($curlData['data'], true);
        if($resData['resultCode'] != '0000') {
            $this->retArr['code'] = 277074;
            $this->retArr['msg']  = $resData['errMsg'];
            return $this->retArr;
        }

        $checkSign = $this->getSign(array_filter($resData), $config['merchantKey']);
        if($checkSign !== $resData['sign']) {
            $this->retArr['code'] = 277074;
            $this->retArr['msg']  = '验签错误!';
            return $this->retArr;
        }

        if ($config['payType'][$data['pay_type']]['request_type'] == 1) {
            return;
            $retData =  [
                'type'     => 1,
                'code_url' => $resData['qrcode_url'],
                'pay_url'  => $resData['pay_url'],
                'order_no' => $orderInfo,
                'modes'    => $data['pay_model']
            ];
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;

            return $this->retArr;
        } else {
            $payHtml = $this->httpHtml([], $resData['payMsg']);
            $retData =  [
                'type'  => $config['payType'][$data['pay_type']]['request_type'],
                'modes' => $data['pay_model'],
                'html'  => $payHtml
            ];
            $this->retArr['code'] = 0;
            $this->retArr['data']  = $retData;
            return $this->retArr;
        }
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
        payLog($this->logFile,print_r($postData,true).'----157--');

        parse_str($postData['data'],$data);
        //D('accountrecharge')->save(['verify_remark' => print_r($data, true)], ['id' => 6]);

        $config = unserialize($postData['config']);
        payLog($this->logFile,print_r($data,true).'----160--');

        if (!is_array($config)) {
            $this->orderInfo['code'] = 277020;
            payLog($this->logFile, '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,获取数据库配置错误！'  . print_r($data, true));
            return $this->orderInfo;
        }

        if ($data['tradeStatus'] != 'SUCCESS') {
            $this->orderInfo['code'] = 277021;
            payLog($this->logFile, '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知：返回信息不是充值成功的信息，出现错误！'  . print_r($data, true));
            return $this->orderInfo;
        }

        $checkSign = $this->getSign($data, $config['merchantKey']);
        payLog($this->logFile,$checkSign.'----195--');
        if($checkSign !== $data['sign']) {
            $this->orderInfo['code'] = 277022;
            payLog($this->logFile, '（' . $this->orderInfo['code'] . '）' . $this->payName . '异步通知,验签失败！'  . print_r($data, true));
            return $this->orderInfo;
        }

        $this->orderInfo['code']      = 0;
        $this->orderInfo['order_no']  = $data['outOrderNo'];  //商户订单号
        $this->orderInfo['amount']    = $data['orderPrice'];
        $this->orderInfo['serial_no'] = $data['tradeNo'];  //平台订单号
        return $this->orderInfo;
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
        $html .= '</head>';
        $html .= '<body onLoad="document.API.submit();">';
        $html .= '<form name="API" method="post" action="' . $url . '">';
        foreach ($post_data as $key => $value) {
            $html .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        }
        $html .= '</form>';
        $html .= '</body>';
        $html .= '</html>';

//        payLog('paytong.txt',print_r($html,true). "  ==220== ");
        //var_dump($html);
        return $html;
    }

    /**
     * 调用第三方接口，提交数据
     * @param string $url 第三方接口url
     * @param array $postdata 提交数据
     * @return array[]|mixed[] 返回数据
     */
    public function httpPost($data, $url)
    {
        $pageContents = HttpClient::quickPost($url, $data);
        if($pageContents) {
            return array('code' => 200, 'data' => $pageContents);
        }else{
            return array('code' => 400, 'data' => '支付请求失败');
        }
    }

    public function getSign($data, $secretKey){
        ksort($data);
        $str = "";
        $i = 0;
        foreach ($data as $key => $val) {
            if($key != "sign" && $key != "secretKey"){
                if($i == 0 ){
                    $str = $str."$key=$val";
                }else {
                    $str = $str."&$key=$val";
                }
                $i++;
            }
        }
        $str = $str."&secretKey=".$secretKey;
        return strtoupper(md5($str));
    }
}


class HttpClient{
    private $host;
    private $port;
    private $path;
    private $method;
    private $postdata = '';
    private $cookies = array();
    private $referer;
    private $accept = 'text/xml,application/xml,application/xhtml+xml,text/html,text/plain,image/png,image/jpeg,image/gif,*/*';
    private $accept_encoding = 'gzip';
    private $accept_language = 'en-us';
    private $user_agent = 'Incutio HttpClient v0.9';
    private $timeout = 30;
    private $use_gzip = true;
    private $persist_cookies = true;
    private $persist_referers = true;
    private $handle_redirects = true;
    private $max_redirects = 5;
    private $headers_only = false;
    private $username;
    private $password;
    private $status;
    private $headers = array();
    private $content = '';
    private $errormsg;
    private $redirect_count = 0;
    private $cookie_host = '';
    function __construct($host, $port=80) {
        $this->host = $host;
        $this->port = $port;
    }

    function get($path, $data = false) {
        $this->path = $path;
        $this->method = 'GET';
        if ($data) {
            $this->path .= '?'.$this->buildQueryString($data);
        }
        return $this->doRequest();
    }
    function post($path, $data) {
        $this->path = $path;
        $this->method = 'POST';
        $this->postdata = $this->buildQueryString($data);
        return $this->doRequest();
    }
    function buildQueryString($data) {
        $querystring = '';
        if (is_array($data)) {
            // Change data in to postable data
            foreach ($data as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $val2) {
                        $querystring .= urlencode($key).'='.urlencode($val2).'&';
                    }
                } else {
                    $querystring .= urlencode($key).'='.urlencode($val).'&';
                }
            }
            $querystring = substr($querystring, 0, -1); // Eliminate unnecessary &
        } else {
            $querystring = $data;
        }
        return $querystring;
    }
    function doRequest() {
        if (!$fp = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout)) {
            // Set error message
            switch($errno) {
                case -3:
                    $this->errormsg = 'Socket creation failed (-3)';
                case -4:
                    $this->errormsg = 'DNS lookup failure (-4)';
                case -5:
                    $this->errormsg = 'Connection refused or timed out (-5)';
                default:
                    $this->errormsg = 'Connection failed ('.$errno.')';
                    $this->errormsg .= ' '.$errstr;
            }
            return false;
        }
        socket_set_timeout($fp, $this->timeout);
        $request = $this->buildRequest();
        fwrite($fp, $request);
        $this->headers = array();
        $this->content = '';
        $this->errormsg = '';
        $inHeaders = true;
        $atStart = true;
        while (!feof($fp)) {
            $line = fgets($fp, 4096);
            if ($atStart) {
                $atStart = false;
                if (!preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', $line, $m)) {
                    $this->errormsg = "Status code line invalid: ".htmlentities($line);
                    return false;
                }
                $http_version = $m[1]; // not used
                $this->status = $m[2];
                $status_string = $m[3]; // not used
                continue;
            }
            if ($inHeaders) {
                if (trim($line) == '') {
                    $inHeaders = false;
                    if ($this->headers_only) {
                        break; // Skip the rest of the input
                    }
                    continue;
                }
                if (!preg_match('/([^:]+):\\s*(.*)/', $line, $m)) {
                    continue;
                }
                $key = strtolower(trim($m[1]));
                $val = trim($m[2]);
                if (isset($this->headers[$key])) {
                    if (is_array($this->headers[$key])) {
                        $this->headers[$key][] = $val;
                    } else {
                        $this->headers[$key] = array($this->headers[$key], $val);
                    }
                } else {
                    $this->headers[$key] = $val;
                }
                continue;
            }
            $this->content .= $line;
        }
        fclose($fp);
        if (isset($this->headers['content-encoding']) && $this->headers['content-encoding'] == 'gzip') {
            $this->content = substr($this->content, 10); // See http://www.php.net/manual/en/function.gzencode.php
            $this->content = gzinflate($this->content);
        }
        if ($this->persist_cookies && isset($this->headers['set-cookie']) && $this->host == $this->cookie_host) {
            $cookies = $this->headers['set-cookie'];
            if (!is_array($cookies)) {
                $cookies = array($cookies);
            }
            foreach ($cookies as $cookie) {
                if (preg_match('/([^=]+)=([^;]+);/', $cookie, $m)) {
                    $this->cookies[$m[1]] = $m[2];
                }
            }
            $this->cookie_host = $this->host;
        }
        if ($this->persist_referers) {
            $this->referer = $this->getRequestURL();
        }
        if ($this->handle_redirects) {
            if (++$this->redirect_count >= $this->max_redirects) {
                $this->errormsg = 'Number of redirects exceeded maximum ('.$this->max_redirects.')';
                $this->redirect_count = 0;
                return false;
            }
            $location = isset($this->headers['location']) ? $this->headers['location'] : '';
            $uri = isset($this->headers['uri']) ? $this->headers['uri'] : '';
            if ($location || $uri) {
                $url = parse_url($location.$uri);
                return $this->get($url['path']);
            }
        }
        return true;
    }
    function buildRequest() {
        $headers = array();
        $headers[] = "{$this->method} {$this->path} HTTP/1.0"; // Using 1.1 leads to all manner of problems, such as "chunked" encoding
        $headers[] = "Host: {$this->host}";
        $headers[] = "User-Agent: {$this->user_agent}";
        $headers[] = "Accept: {$this->accept}";
        if ($this->use_gzip) {
            $headers[] = "Accept-encoding: {$this->accept_encoding}";
        }
        $headers[] = "Accept-language: {$this->accept_language}";
        if ($this->referer) {
            $headers[] = "Referer: {$this->referer}";
        }
        if ($this->cookies) {
            $cookie = 'Cookie: ';
            foreach ($this->cookies as $key => $value) {
                $cookie .= "$key=$value; ";
            }
            $headers[] = $cookie;
        }
        if ($this->username && $this->password) {
            $headers[] = 'Authorization: BASIC '.base64_encode($this->username.':'.$this->password);
        }
        if ($this->postdata) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Content-Length: '.strlen($this->postdata);
        }
        $request = implode("\r\n", $headers)."\r\n\r\n".$this->postdata;
        return $request;
    }
    function getStatus() {
        return $this->status;
    }
    function getContent() {
        return $this->content;
    }
    function getHeaders() {
        return $this->headers;
    }
    function getHeader($header) {
        $header = strtolower($header);
        if (isset($this->headers[$header])) {
            return $this->headers[$header];
        } else {
            return false;
        }
    }
    function getError() {
        return $this->errormsg;
    }
    function getCookies() {
        return $this->cookies;
    }
    function getRequestURL() {
        $url = 'http://'.$this->host;
        if ($this->port != 80) {
            $url .= ':'.$this->port;
        }
        $url .= $this->path;
        return $url;
    }
    function setUserAgent($string) {
        $this->user_agent = $string;
    }
    function setAuthorization($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }
    function setCookies($array) {
        $this->cookies = $array;
    }
    function useGzip($boolean) {
        $this->use_gzip = $boolean;
    }
    function setPersistCookies($boolean) {
        $this->persist_cookies = $boolean;
    }
    function setPersistReferers($boolean) {
        $this->persist_referers = $boolean;
    }
    function setHandleRedirects($boolean) {
        $this->handle_redirects = $boolean;
    }
    function setMaxRedirects($num) {
        $this->max_redirects = $num;
    }
    function setHeadersOnly($boolean) {
        $this->headers_only = $boolean;
    }

    function quickGet($url) {
        $bits = parse_url($url);
        $host = $bits['host'];
        $port = isset($bits['port']) ? $bits['port'] : 80;
        $path = isset($bits['path']) ? $bits['path'] : '/';
        if (isset($bits['query'])) {
            $path .= '?'.$bits['query'];
        }
        $client = new HttpClient($host, $port);
        if (!$client->get($path)) {
            return false;
        } else {
            return $client->getContent();
        }
    }
    static public function quickPost($url, $data) {
        $bits = parse_url($url);
        $host = $bits['host'];
        $port = isset($bits['port']) ? $bits['port'] : 80;
        $path = isset($bits['path']) ? $bits['path'] : '/';
        $client = new HttpClient($host, $port);
        if (!$client->post($path, $data)) {
            return false;
        } else {
            return $client->getContent();
        }
    }
}