<?php

/**
 * 发送邮件API
 * 传了id号时马上发送这一条，没传是按排列发送一条；
 *
 */
define('S_ROOT', substr(dirname(__FILE__), 0, -3));
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
!defined('IN_SNYNI') && die('Access Denied!');
//防止超时
set_time_limit(0);
if (!isset($_GET['id'])) {
    O('cookie', '', 0);
    cookie::set('sendmail', 1, 60); //用户每1分钟调用本程序
    $lockfile = S_CACHE . 'sendmail.lock';
    @$filemtime = filemtime($lockfile);

    if (SYS_TIME - $filemtime < 5)
        exit();
    touch($lockfile);
    $db = getconn();
    //$sql = $db->c_sql(array('type' => 0, 'status' => 0), 'id,send_to,subject,content,attachment', '#@_sendmsg', 1, 'id ASC');
    $sql = $db->c_sql('type=0 and status!=1 and status<4', 'id,send_to,subject,content,attachment,status', '#@_sendmsg', 1, 'id ASC');
    $data = $db->getone($sql);
    if (empty($data))
        exit("0");
    $str = sendmail($data['send_to'], $data['subject'], $data['content'], $data['attachment']);
    if ($str) {
        $db->update('#@_sendmsg', array('status' => 1, 'posttime' => SYS_TIME), array('id' => $data['id']));
    } else {
    	$times = 1;
    	if($data['status']==0){
    		$times = 2;
    	}
    	$db->update('#@_sendmsg', "status=status+".$times.",posttime='".SYS_TIME."'", array('id' => $data['id']));
        exit("0");
    }
} else {
    $db = getconn();
    $sql = $db->c_sql(array('id' => intval($_GET['id']), 'type' => 0, 'status' => 0), 'id,send_to,subject,content,attachment', '#@_sendmsg');
    $data = $db->getone($sql);
    if (empty($data))
        exit("0");
    $str = sendmail($data['send_to'], $data['subject'], $data['content'], $data['attachment']);

    if ($str) {
        $db->update('#@_sendmsg', array('status' => 1, 'posttime' => SYS_TIME), array('id' => $data['id']));
        exit("1");
    } else {
        exit("0");
    }
}

function sendmail($address = '', $title = '', $content = '', $file = '') {
    if (empty($address) || empty($title) || empty($content)) {
        return false;
    }
    $sendfrom = array('service@chenghuitong.net','service1@chenghuitong.net','service2@chenghuitong.net','service3@chenghuitong.net','service4@chenghuitong.net');
    $username = $sendfrom[array_rand($sendfrom)];
    O('sendmail', '', 0);
    $mail = new PHPMailer(); //建立邮件发送类
    $mail->IsSMTP(); // 使用SMTP方式发送
    $mail->Host = "smtp.exmail.qq.com"; // 您的企业邮局域名
    $mail->SMTPAuth = true; // 启用SMTP验证功能
    $mail->SMTPSecure = 'ssl';
    $mail->Username = $username; // 邮局用户名(请填写完整的email地址)
    $mail->Password = "snyni8786.close"; // 邮局密码
    $mail->Port = 465;
    $mail->From = $username; //邮件发送者email地址
    $mail->FromName = "诚汇通";
    $mail->AddAddress("$address"); //收件人地址，可以替换成任何想要接收邮件的email信箱,格式是AddAddress("收件人email","收件人姓名")
    $mail->IsHTML(true); // set email format to HTML //是否使用HTML格式
    !empty($file) && $mail->AddAttachment($file); // 添加附件

    $mail->Subject = $title; //邮件标题
    $mail->Body = $content; //邮件内容

    /*
      //$mail->AddReplyTo("", "");
      //$mail->AddAttachment("/var/tmp/file.tar.gz"); // 添加附件
      //  $mail->AltBody = "This is the body in plain text for non-HTML mail clients"; //附加信息，可以省略
     */
    if (!$mail->Send())
        return false;
    return true;
}
