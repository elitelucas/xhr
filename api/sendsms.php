<?php

/**
 * 发送信息API
 * 传了id号时马上发送这一条，没传是按排列发送一条；
 *
 */
define('S_ROOT', substr(dirname(__FILE__), 0, -3));
require S_ROOT . 'core' . DIRECTORY_SEPARATOR . 'base.php';
!defined('IN_SNYNI') && die('Access Denied!');
//防止超时
set_time_limit(0);
$config = array('smsid' => 'SDK-ZQ-JL-0434', 'smspwd' => '888888.close');

if (!isset($_GET['id'])) {
    O('cookie', '', 0);
    cookie::set('sendsms', 1, 60); //用户每1分钟调用本程序
    $lockfile = S_CACHE . 'sendsms.lock';
    @$filemtime = filemtime($lockfile);

    if (SYS_TIME - $filemtime < 5)
        exit();
    touch($lockfile);
    $db = getconn();
    $sql = $db->c_sql(array('type' => 1, 'status' => 0), 'id,send_to,content', '#@_sendmsg', 1, 'id ASC');
    $data = $db->getone($sql);
    if (empty($data))
        exit("0");
    $str = sendsms_1($data['send_to'], $data['content'], $config);
    if ($str) {
        $db->update('#@_sendmsg', array('status' => 1, 'posttime' => SYS_TIME), array('id' => $data['id']));
    }
} else {
    $db = getconn();
    $sql = $db->c_sql(array('id' => intval($_GET['id']), 'type' => 1, 'status' => 0), 'id,send_to,content', '#@_sendmsg');
    $data = $db->getone($sql);
    if (empty($data))
        exit("0");
    $str = sendsms_1($data['send_to'], $data['content'], $config);
    if ($str) {
        $db->update('#@_sendmsg', array('status' => 1, 'posttime' => SYS_TIME), array('id' => $data['id']));
        exit("1");
    } else {
        exit("0");
    }
}

function sendsms($mobile, $content, $config) {
    if (empty($mobile) || empty($content)) {
        return false;
    }
    $smsid = $config['smsid'];
    $smspwd = $config['smspwd'];
    $content = rawurlencode($content);
    $dxres = file_get_contents("http://124.172.250.160/WebService.asmx/mt?Sn=$smsid&Pwd=$smspwd&mobile=$mobile&content=$content");
    $p = xml_parser_create();
    xml_parse_into_struct($p, $dxres, $vals);
    if ($vals[0]['value'] == 0) {
        return true;
    } else {
        return false;
    }
}

function sendsms_1($mobile, $content,$config='') {
    if (empty($mobile) || empty($content)) {
        return false;
    }
    $smsid = 'chtjr';
    $smspwd = 'cht2410web.close';
    $content = rawurlencode($content."【诚汇通】");
    $dxres = file_get_contents("http://121.199.50.122:8888/sms.aspx?action=send&userid=452&account=$smsid&password=$smspwd&mobile=$mobile&content=$content&sendTime=&extno=");
    $p = xml_parser_create();
    xml_parse_into_struct($p, $dxres, $vals);
    if ($vals[1]['value'] == 'Success') {
        return true;
    } else {
        return false;
    }
}

