<?php

/**
 * 邮件发送函数
 * @copyright			(C) 2011 snyni.com
 * @lastmodify			2011-11-13  by snyni
 */
//写运行日志

define('CHARSET', 'utf-8');

function runlog($file, $log, $halt = 0) {
    global $_SGLOBAL, $_SERVER;

    $nowurl = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : ($_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME']);
    $log = gmdate('Y-m-d H:i:s', SYS_TIME) . "\t$type\t" . ip() . "\t{$nowurl}\t" . str_replace(array("\r", "\n"), array(' ', ' '), trim($log)) . "\n";
    $yearmonth = gmdate('Ym', SYS_TIME);
    $logdir = S_CACHE . 'log' . DS;
    if (!is_dir($logdir))
        mkdir($logdir, 0777);
    $logfile = $logdir . $yearmonth . '_' . $file . '.php';
    if (@filesize($logfile) > 2048000) {
        $dir = opendir($logdir);
        $length = strlen($file);
        $maxid = $id = 0;
        while ($entry = readdir($dir)) {
            if (strexists($entry, $yearmonth . '_' . $file)) {
                $id = intval(substr($entry, $length + 8, -4));
                $id > $maxid && $maxid = $id;
            }
        }
        closedir($dir);
        $logfilebak = $logdir . $yearmonth . '_' . $file . '_' . ($maxid + 1) . '.php';
        @rename($logfile, $logfilebak);
    }
    if ($fp = @fopen($logfile, 'a')) {
        @flock($fp, 2);
        fwrite($fp, "<?PHP exit;?>\t" . str_replace(array('<?', '?>', "\r", "\n"), '', $log) . "\n");
        fclose($fp);
    }
    if ($halt)
        exit();
}

/**
 * 发送邮件
 * @param $toemail 收件人email
 * @param $subject 邮件主题
 * @param $message 正文
 * @param $from 发件人
 * @param $cfg 邮件配置信息
 */
function sendmail($toemail, $subject, $message, $from = '', $cfg = array()) {
    $sitename = L('sys.gbname');
    if ($cfg && is_array($cfg)) {
        $adminemail = $cfg['from'];
        $mail = $cfg;
        $mail_type = $cfg['mail_type']; //邮件发送模式
    } else {
        $adminemail = C('mail_from');
        $mail_type = 1; //邮件发送模式
        $mail = Array(
            'mailsend' => 2,
            'maildelimiter' => 1,
            'mailusername' => 1,
            'server' => C('mail_server'),
            'port' => C('mail_port'),
            'auth' => 1,
            'from' => C('mail_from'),
            'auth_username' => C('mail_user'),
            'auth_password' => C('mail_password')
        );
    }

    //邮件头的分隔符
    $maildelimiter = $mail['maildelimiter'] == 1 ? "\r\n" : ($mail['maildelimiter'] == 2 ? "\r" : "\n");
    //收件人地址中包含用户名
    $mailusername = isset($mail['mailusername']) ? $mail['mailusername'] : 1;
    //端口
    $mail['port'] = $mail['port'] ? $mail['port'] : 25;
    $mail['mailsend'] = $mail['mailsend'] ? $mail['mailsend'] : 1;

    //发信者
    $email_from = $from == '' ? '=?' . CHARSET . '?B?' . base64_encode($sitename) . "?= <" . $adminemail . ">" : (preg_match('/^(.+?) \<(.+?)\>$/', $from, $mats) ? '=?' . CHARSET . '?B?' . base64_encode($mats[1]) . "?= <$mats[2]>" : $from);

    $email_to = preg_match('/^(.+?) \<(.+?)\>$/', $toemail, $mats) ? ($mailusername ? '=?' . CHARSET . '?B?' . base64_encode($mats[1]) . "?= <$mats[2]>" : $mats[2]) : $toemail;
    ;

    $email_subject = '=?' . CHARSET . '?B?' . base64_encode(preg_replace("/[\r|\n]/", '', '[' . $sitename . '] ' . $subject)) . '?=';
    $email_message = chunk_split(base64_encode(str_replace("\n", "\r\n", str_replace("\r", "\n", str_replace("\r\n", "\n", str_replace("\n\r", "\r", $message))))));

    $headers = "From: $email_from{$maildelimiter}X-Priority: 3{$maildelimiter}X-Mailer: SNYNI {$maildelimiter}MIME-Version: 1.0{$maildelimiter}Content-type: text/html; charset=" . CHARSET . "{$maildelimiter}Content-Transfer-Encoding: base64{$maildelimiter}";

    //mail 发送模式
    if ($mail_type == 0) {
        if (function_exists('mail') && @mail($email_to, $email_subject, $email_message, $headers)) {
            return true;
        }
        return false;
    } else {
        if (!$fp = fsockopen($mail['server'], $mail['port'], $errno, $errstr, 30)) {
            runlog('SMTP', "($mail[server]:$mail[port]) CONNECT - Unable to connect to the SMTP server", 0);
            return false;
        }
        stream_set_blocking($fp, true);

        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != '220') {
            runlog('SMTP', "$mail[server]:$mail[port] CONNECT - $lastmessage", 0);
            return false;
        }

        fputs($fp, ($mail['auth'] ? 'EHLO' : 'HELO') . " snyni\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 220 && substr($lastmessage, 0, 3) != 250) {
            runlog('SMTP', "($mail[server]:$mail[port]) HELO/EHLO - $lastmessage", 0);
            return false;
        }

        while (1) {
            if (substr($lastmessage, 3, 1) != '-' || empty($lastmessage)) {
                break;
            }
            $lastmessage = fgets($fp, 512);
        }

        if ($mail['auth']) {
            fputs($fp, "AUTH LOGIN\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 334) {
                runlog('SMTP', "($mail[server]:$mail[port]) AUTH LOGIN - $lastmessage", 0);
                return false;
            }

            fputs($fp, base64_encode($mail['auth_username']) . "\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 334) {
                runlog('SMTP', "($mail[server]:$mail[port]) USERNAME - $lastmessage", 0);
                return false;
            }

            fputs($fp, base64_encode($mail['auth_password']) . "\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 235) {
                runlog('SMTP', "($mail[server]:$mail[port]) PASSWORD - $lastmessage", 0);
                return false;
            }

            $email_from = $mail['from'];
        }

        fputs($fp, "MAIL FROM: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_from) . ">\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 250) {
            fputs($fp, "MAIL FROM: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $email_from) . ">\r\n");
            $lastmessage = fgets($fp, 512);
            if (substr($lastmessage, 0, 3) != 250) {
                runlog('SMTP', "($mail[server]:$mail[port]) MAIL FROM - $lastmessage", 0);
                return false;
            }
        }

        fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $toemail) . ">\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 250) {
            fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $toemail) . ">\r\n");
            $lastmessage = fgets($fp, 512);
            runlog('SMTP', "($mail[server]:$mail[port]) RCPT TO - $lastmessage", 0);
            return false;
        }

        fputs($fp, "DATA\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 354) {
            runlog('SMTP', "($mail[server]:$mail[port]) DATA - $lastmessage", 0);
            return false;
        }

        $headers .= 'Message-ID: <' . gmdate('YmdHs') . '.' . substr(md5($email_message . microtime()), 0, 6) . rand(100000, 999999) . '@' . $_SERVER['HTTP_HOST'] . ">{$maildelimiter}";

        fputs($fp, "Date: " . gmdate('r') . "\r\n");
        fputs($fp, "To: " . $email_to . "\r\n");
        fputs($fp, "Subject: " . $email_subject . "\r\n");
        fputs($fp, $headers . "\r\n");
        fputs($fp, "\r\n\r\n");
        fputs($fp, "$email_message\r\n.\r\n");
        $lastmessage = fgets($fp, 512);
        if (substr($lastmessage, 0, 3) != 250) {
            runlog('SMTP', "($mail[server]:$mail[port]) END - $lastmessage", 0);
        }
        fputs($fp, "QUIT\r\n");
        return true;
    }
}

?>