<?php

!defined('IN_SNYNI') && die('Access Denied!');

class download {

    const ERROR_CONNECT = 600;
    const SEND_USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535.2 (KHTML, like Gecko) Chrome/15.0.874.121';

    public $url, $method, $timeout;
    private $host, $port, $path, $query, $referer;
    private $header;
    private $body;

    public function __construct($url = null, $method = 'GET', $timeout = 60) {
        if (!function_exists('curl_exec')) {
            exit("cURL is not installed.");
        }
        @set_time_limit(0);
        if (!empty($url)) {
            $this->connect($url, $method, $timeout);
        }
        return $this;
    }

    public function connect($url = null, $method = 'GET', $timeout = 0) {
        $this->header = null;
        $this->body = null;
        $this->url = $url;
        $this->method = strtoupper(empty($method) ? 'GET' : $method );
        $this->timeout = empty($timeout) ? 30 : $timeout;
        if (!empty($url)) {
            $this->parseURL($url);
        }
        return $this;
    }

    public function send($params = array()) {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, array(CURLOPT_TIMEOUT => $this->timeout, CURLOPT_HEADER => false, CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => self::SEND_USER_AGENT, CURLOPT_REFERER => $this->referer));
        if ($this->method == 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            if (is_array($params)) {
                $QueryStr = http_build_query($params);
            } else {
                $QueryStr = $params;
            }
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $QueryStr);
        }
        $fp = curl_exec($ch);
        $header = curl_getinfo($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno) {
            $header['http_code'] = $errno;
            $this->header = $header;
            return false;
        }

        if ($this->redirect($header)) {
            return true;
        }
        $this->header = $header;
        $this->body = $fp;
        return true;
    }

    private function redirect($header) {
        if (in_array($this->status($header), array(301, 302))) {
            $this->connect($header['redirect_url'], $this->method, $this->timeout);
            $this->send();
            return true;
        } else {
            return false;
        }
    }

    public function header() {
        return $this->header;
    }

    public function body() {
        return $this->body;
    }

    public function status($header = null) {
        if (empty($header)) {
            $header = $this->header;
        }
        if (isset($header['http_code'])) {
            return $header['http_code'];
        } else {
            return self::ERROR_CONNECT;
        }
    }

    private function parseURL($url) {
        $aUrl = parse_url($url);
        !isset($aUrl ['query']) && $aUrl ['query'] = null;
        $this->host = $aUrl ['host'];
        $this->port = empty($aUrl ['port']) ? 80 : (int) $aUrl ['port'];
        $this->path = empty($aUrl ['path']) ? '/' : (string) $aUrl ['path'];
        $this->query = strlen($aUrl ['query']) > 0 ? '?' . $aUrl ['query'] : null;
        $this->referer = HTTP_REFERER;
    }

    public function geterror($errno = null) {
        if (empty($errno)) {
            $errno = $this->status();
        }
        $error_codes = array(
            1 => 'CURLE_UNSUPPORTED_PROTOCOL',
            2 => 'CURLE_FAILED_INIT',
            3 => 'CURLE_URL_MALFORMAT',
            4 => 'CURLE_URL_MALFORMAT_USER',
            5 => 'CURLE_COULDNT_RESOLVE_PROXY',
            6 => 'CURLE_COULDNT_RESOLVE_HOST',
            7 => 'CURLE_COULDNT_CONNECT',
            8 => 'CURLE_FTP_WEIRD_SERVER_REPLY',
            9 => 'CURLE_REMOTE_ACCESS_DENIED',
            11 => 'CURLE_FTP_WEIRD_PASS_REPLY',
            13 => 'CURLE_FTP_WEIRD_PASV_REPLY',
            14 => 'CURLE_FTP_WEIRD_227_FORMAT',
            15 => 'CURLE_FTP_CANT_GET_HOST',
            17 => 'CURLE_FTP_COULDNT_SET_TYPE',
            18 => 'CURLE_PARTIAL_FILE',
            19 => 'CURLE_FTP_COULDNT_RETR_FILE',
            21 => 'CURLE_QUOTE_ERROR',
            22 => 'CURLE_HTTP_RETURNED_ERROR',
            23 => 'CURLE_WRITE_ERROR',
            25 => 'CURLE_UPLOAD_FAILED',
            26 => 'CURLE_READ_ERROR',
            27 => 'CURLE_OUT_OF_MEMORY',
            28 => 'CURLE_OPERATION_TIMEDOUT',
            30 => 'CURLE_FTP_PORT_FAILED',
            31 => 'CURLE_FTP_COULDNT_USE_REST',
            33 => 'CURLE_RANGE_ERROR',
            34 => 'CURLE_HTTP_POST_ERROR',
            35 => 'CURLE_SSL_CONNECT_ERROR',
            36 => 'CURLE_BAD_DOWNLOAD_RESUME',
            37 => 'CURLE_FILE_COULDNT_READ_FILE',
            38 => 'CURLE_LDAP_CANNOT_BIND',
            39 => 'CURLE_LDAP_SEARCH_FAILED',
            41 => 'CURLE_FUNCTION_NOT_FOUND',
            42 => 'CURLE_ABORTED_BY_CALLBACK',
            43 => 'CURLE_BAD_FUNCTION_ARGUMENT',
            45 => 'CURLE_INTERFACE_FAILED',
            47 => 'CURLE_TOO_MANY_REDIRECTS',
            48 => 'CURLE_UNKNOWN_TELNET_OPTION',
            49 => 'CURLE_TELNET_OPTION_SYNTAX',
            51 => 'CURLE_PEER_FAILED_VERIFICATION',
            52 => 'CURLE_GOT_NOTHING',
            53 => 'CURLE_SSL_ENGINE_NOTFOUND',
            54 => 'CURLE_SSL_ENGINE_SETFAILED',
            55 => 'CURLE_SEND_ERROR',
            56 => 'CURLE_RECV_ERROR',
            58 => 'CURLE_SSL_CERTPROBLEM',
            59 => 'CURLE_SSL_CIPHER',
            60 => 'CURLE_SSL_CACERT',
            61 => 'CURLE_BAD_CONTENT_ENCODING',
            62 => 'CURLE_LDAP_INVALID_URL',
            63 => 'CURLE_FILESIZE_EXCEEDED',
            64 => 'CURLE_USE_SSL_FAILED',
            65 => 'CURLE_SEND_FAIL_REWIND',
            66 => 'CURLE_SSL_ENGINE_INITFAILED',
            67 => 'CURLE_LOGIN_DENIED',
            68 => 'CURLE_TFTP_NOTFOUND',
            69 => 'CURLE_TFTP_PERM',
            70 => 'CURLE_REMOTE_DISK_FULL',
            71 => 'CURLE_TFTP_ILLEGAL',
            72 => 'CURLE_TFTP_UNKNOWNID',
            73 => 'CURLE_REMOTE_FILE_EXISTS',
            74 => 'CURLE_TFTP_NOSUCHUSER',
            75 => 'CURLE_CONV_FAILED',
            76 => 'CURLE_CONV_REQD',
            77 => 'CURLE_SSL_CACERT_BADFILE',
            78 => 'CURLE_REMOTE_FILE_NOT_FOUND',
            79 => 'CURLE_SSH',
            80 => 'CURLE_SSL_SHUTDOWN_FAILED',
            81 => 'CURLE_AGAIN',
            82 => 'CURLE_SSL_CRL_BADFILE',
            83 => 'CURLE_SSL_ISSUER_ERROR',
            84 => 'CURLE_FTP_PRET_FAILED',
            84 => 'CURLE_FTP_PRET_FAILED',
            85 => 'CURLE_RTSP_CSEQ_ERROR',
            86 => 'CURLE_RTSP_SESSION_ERROR',
            87 => 'CURLE_FTP_BAD_FILE_LIST',
            88 => 'CURLE_CHUNK_FAILED');
        return $error_codes[$errno];
    }

}

?>