<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_once dirname(__FILE__) . "/httpsqs_client.php";
/**
 * 分为3个队列
 * A 主线队列
 * B 重试队列
 * C 死信队列
 */
define('QUEUE_EMAIL_A', 'SEND_EMAIL_A');
define('QUEUE_EMAIL_B', 'SEND_EMAIL_B');
define('QUEUE_EMAIL_C', 'SEND_EMAIL_C');
define('QUEUE_SMS_A', 'SEND_SMS_A');
define('QUEUE_SMS_B', 'SEND_SMS_B');
define('QUEUE_SMS_C', 'SEND_SMS_C');
/**
 * 六月发财季活动 
 * 活动一：送现金不手软（即投标奖励在原有奖励的基础上，增加下述奖励）
 * 活动页面跳转至投标页面，活动标有特殊标识
 */
define('QUEUE_BORROW_INFO', 'BORROW_INFO');

/**
 * Description of SqsQueue
 * 官方文档：http://blog.zyan.cc/httpsqs/
 * 查看队列的状态：http://192.168.1.4:1218/?name=SEND_EMAIL_A&opt=status
 * 查看指定队列位置点的内容：http://192.168.1.4:1218/?charset=utf-8&name=SEND_EMAIL_A&opt=view&pos=1&auth=
 * 
 * 运行守护进程
 * 转到E:\work\newp2p\cron\SqsQueue.php目录
 * 执行 
 * php SqsQueue.php sendEmailA&
 * php SqsQueue.php sendEmailB&
 * php SqsQueue.php sendSMSA&
 * php SqsQueue.php sendSMSB&
 * @author Administrator
 */
class SqsQueue {

    //put your code here
    private $host = '192.168.1.4';
    private $port = '1218';
    private $auth = '';
    private $charset = 'utf-8';
    private $httpsqs = null;
    private $try_times = 3; //最大尝试的次数
    private $email_error = ''; //发送邮件失败的错误信息
    private $sms_error = ''; //发送短信失败的错误信息

    public function __construct() {
        $this->httpsqs = new httpsqs($this->host, $this->port, $this->auth, $this->charset);
    }

    /**
     * 处理发送邮件主线队列
     */
    public function sendEmailA() {
        while (true) {
            $result = $this->httpsqs->gets(QUEUE_EMAIL_A);
            $pos = $result["pos"]; //当前队列消息的读取位置点  
            $data = $result["data"]; //当前队列消息的内容  
            if ($data != "HTTPSQS_GET_END" && $data != "HTTPSQS_ERROR") {
                //...去做应用操作...
                $data = json_decode($data, TRUE);

                $send_rs = $this->sendmail($data['send_to'], $data['subject'], $data['content'], $data['attachment']);
                if (!$send_rs) {//发送邮件失败 
                    //加入到重试队列
                    $queue_data = $data;
                    $queue_data['fail_times'] = 1; //并且设置重试次数
                    $queue_data['error_msg'] = $this->email_error . '|';
                    $this->httpsqs->put(QUEUE_EMAIL_B, json_encode($queue_data));
                }
            } else {
                sleep(1); //暂停1秒钟后，再次循环  
            }
        }
    }

    /**
     * 处理发送邮件重试队列
     */
    public function sendEmailB() {
        while (true) {
            $result = $this->httpsqs->gets(QUEUE_EMAIL_B);
            $pos = $result["pos"]; //当前队列消息的读取位置点  
            $data = $result["data"]; //当前队列消息的内容  
            if ($data != "HTTPSQS_GET_END" && $data != "HTTPSQS_ERROR") {
                //...去做应用操作...
                $data = json_decode($data, TRUE);

                $send_rs = $this->sendmail($data['send_to'], $data['subject'], $data['content'], $data['attachment']);
                if (!$send_rs) {//发送邮件失败
                    //加入到重试队列
                    $queue_data = $data;
                    $queue_data['fail_times'] ++; //并且设置重试次数
                    $queue_data['error_msg'] .= $this->email_error . '|';
                    if ($queue_data['fail_times'] >= $this->try_times) {//超过最大次数就不再重试直接加入队列C
                        $this->httpsqs->put(QUEUE_EMAIL_C, json_encode($queue_data));
                    } else {//未达到最大的重试次数就再次加入到队列
                        $this->httpsqs->put(QUEUE_EMAIL_B, json_encode($queue_data));
                    }
                }
            } else {
                sleep(10); //暂停10秒钟后，再次循环  
            }
        }
    }

    /**
     * 处理发送短信主线队列
     */
    public function sendSMSA() {
        while (true) {
            $result = $this->httpsqs->gets(QUEUE_SMS_A);
            $pos = $result["pos"]; //当前队列消息的读取位置点  
            $data = $result["data"]; //当前队列消息的内容  
            if ($data != "HTTPSQS_GET_END" && $data != "HTTPSQS_ERROR") {
                //...去做应用操作...
                $data = json_decode($data, TRUE);

                $send_rs = $this->sendsms_1($data['send_to'], $data['content']);
                if (!$send_rs) {//发送短信失败 
                    //加入到重试队列
                    $queue_data = $data;
                    $queue_data['fail_times'] = 1; //并且设置重试次数
                    $queue_data['error_msg'] = $this->sms_error . '|';
                    $this->sms_error = '';
                    $this->httpsqs->put(QUEUE_SMS_B, json_encode($queue_data));
                }
            } else {
                sleep(1); //暂停1秒钟后，再次循环  
            }
        }
    }

    /**
     * 处理发送短信重试队列
     */
    public function sendSMSB() {
        while (true) {
            $result = $this->httpsqs->gets(QUEUE_SMS_B);
            $pos = $result["pos"]; //当前队列消息的读取位置点  
            $data = $result["data"]; //当前队列消息的内容  
            if ($data != "HTTPSQS_GET_END" && $data != "HTTPSQS_ERROR") {
                //...去做应用操作...
                $data = json_decode($data, TRUE);

                $send_rs = $this->sendsms_1($data['send_to'], $data['content']);
                if (!$send_rs) {//发送邮件失败
                    //加入到重试队列
                    $queue_data = $data;
                    $queue_data['fail_times'] ++; //并且设置重试次数
                    $queue_data['error_msg'] .= $this->sms_error . '|';
                    $this->sms_error = '';
                    if ($queue_data['fail_times'] >= $this->try_times) {//超过最大次数就不再重试直接加入队列C
                        $this->httpsqs->put(QUEUE_SMS_C, json_encode($queue_data));
                    } else {//未达到最大的重试次数就再次加入到队列
                        $this->httpsqs->put(QUEUE_SMS_B, json_encode($queue_data));
                    }
                }
            } else {
                sleep(10); //暂停10秒钟后，再次循环  
            }
        }
    }

    /**
     * 发送邮件
     * @param type $address 邮件地址
     * @param type $title   标题
     * @param type $content 内容
     * @param type $file    附件
     * @return boolean
     */
    private function sendmail($address = '', $title = '', $content = '', $file = '') {
        if (empty($address) || empty($title) || empty($content)) {
            return false;
        }
        $sendfrom = array('service@chenghuitong.net', 'service1@chenghuitong.net', 'service2@chenghuitong.net', 'service3@chenghuitong.net', 'service4@chenghuitong.net');
        $username = $sendfrom[array_rand($sendfrom)];
        O('sendmail', '', 0);
        $mail = new PHPMailer(); //建立邮件发送类
        $mail->IsSMTP(); // 使用SMTP方式发送
        $mail->Host = "smtp.exmail.qq.com"; // 您的企业邮局域名
        $mail->SMTPAuth = true; // 启用SMTP验证功能
        $mail->SMTPSecure = 'ssl';
        $mail->Username = $username; // 邮局用户名(请填写完整的email地址)
        $mail->Password = "snyni8786"; // 邮局密码
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
//        var_dump($mail->ErrorInfo);
        if (!$mail->Send())
            return false;
        return true;
    }

    private function sendsms($mobile, $content, $config) {
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

    private function sendsms_1($mobile, $content, $config = '') {
        if (empty($mobile) || empty($content)) {
            return false;
        }
        $smsid = 'chtjr';
        $smspwd = 'cht2410web';
        $content = rawurlencode($content . "【诚汇通】");
        $dxres = file_get_contents("http://121.199.50.122:8888/sms.aspx?action=send&userid=452&account=$smsid&password=$smspwd&mobile=$mobile&content=$content&sendTime=&extno=");
        $p = xml_parser_create();
        xml_parse_into_struct($p, $dxres, $vals);
        if ($vals[1]['value'] == 'Success') {
            return true;
        } else {
            $this->sms_error = isset($vals[3]['value']) ? $vals[3]['value'] : $this->sms_error;
            return false;
        }
    }

    public function test() {
//        return FALSE;
//        $borrow_id = 4915;
//        $borrow = D("Borrow");
//        $borrow_info = $borrow->getBorrow('borrow_type,id,zhuanrangren', array('id' => $borrow_id), '', 1);
//        var_dump($borrow_info);
//        die;
//        var_dump(C("debug_mode"));die;
        //加入队列
//        $queue_data = array(
//            'send_to' => '13823227225',
//            'content' => '测试短信啊',
//        );


        $queue_name = QUEUE_BORROW_INFO;
        $queue_data = array(
            'id' => '4915', //标的ID
        );



//        $queue_name = QUEUE_SMS_A;
        $ret = $this->addToQueue($queue_name, $queue_data);
        var_dump($ret);
        die;


        $queue_data = array(
            'send_to' => 'liangphy@hotmail.com',
            'subject' => '测试邮件',
            'content' => '测试邮件',
            'attachment' => '', //附件
        );
        $data = $queue_data;

        $send_rs = $this->sendmail($data['send_to'], $data['subject'], $data['content'], $data['attachment']);
        die;
        $queue_data = json_encode($queue_data);
        $ret = $this->httpsqs->put(QUEUE_EMAIL_A, $queue_data);
        var_dump($ret);
    }

    /**
     * 添加一条数据到队列 
     * @param type $queue_name 队列的名称
     * @param type $queue_data 队列的数据（数组格式）
     * @return type
     */
    public function addToQueue($queue_name, $queue_data) {
        //加入队列
        $queue_data = is_array($queue_data) ? json_encode($queue_data) : $queue_data;
        $ret = $this->httpsqs->put($queue_name, $queue_data);
        return $ret;
    }

    /**
     * 六月发财季活动需求
     * 活动一：送现金不手软（即投标奖励在原有奖励的基础上，增加下述奖励）
      活动页面跳转至投标页面，活动标有特殊标识
      奖励详情：一月标送奖励投资金额的0.1%
                  三月标奖励投资金额的0.23%
                  六月标奖励投资金额的0.4%
      投标细则：
      1、活动时间：6月10日~6月23日，每天9:30,14:00发布活动标；
      2、活动标针对诚汇通所有用户，1000元起投（系统设置门槛）；
      3、1月标每天总额20W，3月标每天总额50W，6月标每天总额80W，满额不续发，手快有，手慢无哦；
      4、满标复审后，奖励立即到账，并以站内信形式通知用户
      5、小诚温馨提示：活动标不支持自动投标！（即自动投标规则不适用活动标）
     */
    public function sendCash() {
        return FALSE; //用触发器处理
        while (true) {
            $config = require_once S_PAGE . 'config/activity20150610.php';
//            var_dump($config);die;
            $result = $this->httpsqs->gets(QUEUE_BORROW_INFO);
            $pos = $result["pos"]; //当前队列消息的读取位置点  
            $data = $result["data"]; //当前队列消息的内容  
//            var_dump($data);die;
            if ($data != "HTTPSQS_GET_END" && $data != "HTTPSQS_ERROR") {
                //...去做应用操作...
                $data = json_decode($data, TRUE);

                //获取标的ID
                $borrow_id = $data['id'];
                $borrow_id = trim($borrow_id);
//                var_dump($borrow_id);die;
                /**
                 * 满标复审的时候触发一个任务到队列                * 
                 * 
                 * 后台开启一个守护进程
                 * 根据任务发过来的标 进行处理
                 * 读取所有读取这个标的用户
                 * 剔除掉超过金额不够1000的
                 * 剔除自动投标
                 * 发放奖金
                 * 
                 * 1.根据ID读取标的基本信息
                 * 2.根据条件来判断此标是否满足活动条件
                 * 3.读取投此标的所有用户
                 * 4.循环处理给用户送钱发站内信
                 */
                $borrow = D("Borrow");
                $borrow_info = $borrow->getBorrow('borrow_type,id,zhuanrangren', array('id' => $borrow_id), '', 1);
                var_dump($borrow_info);
                die;
            } else {
                var_dump($data);
                die;
//                sleep(10); //暂停10秒钟后，再次循环  
            }
        }
    }

    /**
     * 赠送抽奖机会给用户
     */
    public function sendChance() {
        while (true) {
            $config = require_once S_PAGE . 'config' . DS . 'activity20150610.php';
            $hd_start_time = strtotime($config['start_time']);
            $hd_end_time = strtotime($config['end_time']);
            $debug_file = S_CACHE . 'log' . DS . 'activity' . DS . '20150610' . DS . '' . date("ymd") . '.log'; //日志文件路径
            $today_start_time = date("Y-m-d") . " 00:00:00";
            $today_end_time = date("Y-m-d") . " 23:59:59";
//            var_dump($config);die;
            $result = $this->httpsqs->gets(QUEUE_BORROW_INFO);
            $pos = $result["pos"]; //当前队列消息的读取位置点  
            $data = $result["data"]; //当前队列消息的内容  
//            var_dump($data);die;
            if ($data != "HTTPSQS_GET_END" && $data != "HTTPSQS_ERROR") {
                //...去做应用操作...
                $data = json_decode($data, TRUE);

                //获取标的ID
                $borrow_id = $data['id'];
                $borrow_id = trim($borrow_id);
                $borrow_id = intval($borrow_id);


                //获取标的基本信息并且判断是否符合活动要求
                $borrow = D('Borrow');
                $borrow_info = $borrow->getBorrow('', "B.id='{$borrow_id}'", '', 1);
                if (!isset($borrow_info)) {//获取标信息失败
                    //失败的处理逻辑                    
                    $content = date('Y-m-d H:i:s') . " | ";
                    $content = $content . '获取标信息失败 $borrow_id：' . $borrow_id . ' | __CLASS__:' . __CLASS__ . '|__FUNCTION__:' . __FUNCTION__ . "\r\n";
                    file_force_contents($debug_file, $content, FILE_APPEND);
                    return FALSE;
                }
                //判断该标满足条件 在活动时间内 标的状态是对的
//                if (!($borrow_info['addtime'] >= $hd_start_time and $borrow_info['addtime'] < $hd_end_time and $borrow_info['status'] == $config['chance_borrow_status'])) {
//                    $content = date('Y-m-d H:i:s') . " | ";
//                    $content = $content . '不满足活动条件 $borrow_info：' . var_export($borrow_info, TRUE) . ' | __CLASS__:' . __CLASS__ . '|__FUNCTION__:' . __FUNCTION__ . "\r\n";
//                    file_force_contents($debug_file, $content, FILE_APPEND);
//                    return FALSE;
//                }


                /**
                 * 1.查找当天所有满标复审的标的ID
                 * 2.根据多个ID计算用户的投资金额
                 * 3.根据投资金额计算用户的抽奖机会
                 * 4.根据计算出来的抽奖机会减去今天已经获得的抽奖机会
                 * 5.根据剩下的抽奖机会插入数据库                 * 
                 */
                //根据标ID获取用户投标记录 并且根据投标的金额进行处理
                $borrowtender = D("Borrowtender");
                $ret = $borrowtender->getUserTender();

                $chance_rule = $config['chance_rule'];

                $activity = D('Activity');
                $user = D('User');

                foreach ($ret as $v) {//逐个处理送抽奖机会
                    list($user_id, $total_account_act) = array($v['user_id'], $v['total_account_act']);
                    $total_account_act = floatval($total_account_act);
                    $chance_num = 0; //抽奖机会的次数
                    foreach ($chance_rule as $kk => $vv) {
                        list($min, $max) = $vv;
                        if ($total_account_act >= $min and $total_account_act < $max) {
                            $chance_num = $kk;
                            break;
                        }
                    }
                    if ($chance_num == 0) {//$user
                        continue;
                    }
//                    $user_info = $user->getUser('id,username', "id='{$user_id}'");
                    //获取用户今天获得的抽奖机会
                    $chance_info = $activity->getLotteryChance($user_id, $today_start_time, $today_end_time);
                    $today_has_num = isset($chance_info['total_remain_num']) ? $chance_info['total_remain_num'] : 0;

                    $can_send_num = $chance_num - $today_has_num; //能送的抽奖机会

                    $insert_data = array();
                    for ($i = 1; $i <= $can_send_num; $i++) {
                        $insert_data[] = array(
                            'user_id' => $user_id,
                            'get_date' => date("Y-m-d H:i:s"),
                        );
                    }
//                    var_dump($insert_data);die;
                    $rs = $activity->sendChance($insert_data);
                    if (!$rs) {
                        $content = date('Y-m-d H:i:s') . " | ";
                        $content = $content . '送抽奖机会失败 $insert_data：' . var_export($insert_data, TRUE) . ' | __CLASS__:' . __CLASS__ . '|__FUNCTION__:' . __FUNCTION__ . "\r\n";
                        file_force_contents($debug_file, $content, FILE_APPEND);
                    }
                }
            } else {
                sleep(2); //暂停10秒钟后，再次循环  
            }
        }
    }

}
