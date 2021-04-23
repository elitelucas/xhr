<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'page.php';

class MailAction extends Action {

    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = D('admin/mail');
    }

    //公告推荐
    public function recom() {
        $id = (int)trim($_REQUEST['id']);
        $recom = (int)trim($_REQUEST['recom']);
        if (!in_array($recom, [1, 2])) {
            echo json_encode(["rt" => false]);exit;
        }
        $info = $this->db->getone("select * from un_message where id = $id");
        if(!$info){
            echo json_encode(["rt" => false]);exit;
        }

        $rt = $this->model->recom(["recom" => $recom], ["id" => $id]);
        if($rt) {
            $recomZh = $recom == 1?'取消推荐公告':'推荐公告';
            $log_remarks = "公告设置:".$recomZh."--公告主题：".$info['title'];
            admin_operation_log($this->admin['userid'], 140, $log_remarks, $id);
        }
        echo json_encode(["rt" => $rt]);
    }

    public function mail_lst() {
        $data = $_REQUEST;
        $arr = [
            'username' => $data['username'],
            'title'    => $data['title'],
            'content'  => $data['content'],
            'min_time' => $data['min_time'],
            'max_time' => $data['max_time'],
            'type'     => 1
        ];

        $mail = D('admin/mail');
        $count = $mail->mail_sousuoCount($arr);
        $pagesize = 20;
        $url = '?m=admin&c=mail&a=mail_lst';
        $page = new page($count, $pagesize, $url, $arr);
        $show = $page->show();
        $msg = $mail->mail_sousuo($arr, $page->offer, $pagesize);

        include template('mail_lst');
    }

    //公告发布
    public function mail_send() {
        $id = trim($_REQUEST['id']);

        $expired_time = '';
        if (!empty($id)) {
            $mail = D('admin/mail');
            $list = $mail->getMessageForId($id);
            $expired_time = date('Y-m-d', $list['expired_time']);
        }
        include template('mail_send');
    }

    //按用户查询
    public function user_search() {
        $data = $_REQUEST;
        $arr = [
            'username' => $data['username'],
            'link'     => $data['link']
        ];

        $mail = D('admin/mail');
        $result = $mail->user_search($arr);
        echo json_encode($result);
    }

    //发送信息or公告
    public function send_msg() {
        $data = $_REQUEST;
        $data['id'] = empty($data['id']) ? $data['id'] : "|" . $data['id'];
        $arr = [
            'title'         => $data['title'],
            'content'       => $data['contents'],
            'recom'         => $data['recom'],
            'is_popup'      => $data['is_popup'],
            'expired_time'  => strtotime($data['expired_time']),
            'type'          => $data['type'],
            'touser_id'     => $data['id'],
            'user_id'       => $this->admin['userid'],
        ];

        $mail = D('admin/mail');
        $jpush = D('config')->getOne('JPush_config');
        $conf = json_decode($jpush['value'], true);
        if ($data['type'] == 1) {
            $res = push_send_all($data['title'], $data['contents'], $conf, $data['type']);

            //如果是弹窗公告，则覆盖之前存在的弹窗公告
            if ($data['is_popup'] == '1') {
                $hasPopupAnnouncement = $mail->hasPopupAnnouncement();
                if ($hasPopupAnnouncement !== false) {
                    $mail->updateIsPopup($hasPopupAnnouncement);
                }
            }

        } elseif ($data['type'] == 2) {
            $res = push_send($data['title'], $data['contents'], $data['id'], $conf, $data['type']);
        }
        $result = $mail->send_msg($arr);
        // $this->refreshRedis("all", "all");
        $this->refreshRedis('sysMessage', 'all');
        if ($result) {
            $log_remarks = "发送公告:".$data['title'];
            admin_operation_log($this->admin['userid'], 140, $log_remarks);

            $ss = [
                'state' => 1,
                'msg'   => '发送成功！'
            ];
            echo json_encode($ss);
        } else {
            $ss = [
                'state' => 0,
                'msg' => '发送失败！'
            ];
            echo json_encode($ss);
        }
    }

    //检查是否有已弹窗公告
    public function checkHasPopupAnnouncement()
    {
        $mail = D('admin/mail');
        $hasPopupAnnouncement = $mail->hasPopupAnnouncement();
        if ($hasPopupAnnouncement !== false) {
            $rt = [
                'state' => 0,
                'msg' => '当前已有弹窗公告，是否替换为当前弹窗公告？',
            ];
            echo json_encode($rt);
            return false;
        }

        $rt = [
            'state' => 1,
            'msg' => '',
        ];
        echo json_encode($rt);
    }


    //查看收件人
    public function view() {
        $ids = $_REQUEST['id'];
        $mail = D('admin/mail');
        $result = $mail->view($ids);
        echo json_encode($result);
    }

    //发送给所有人
    public function send_anyone() {
        $mail = D('admin/mail');
        $result = $mail->send_anyone();
        echo json_encode($result);
    }

    //根据用户组查询或代理等级查询
    public function agent_search() {
        $data = $_REQUEST;
        $arr = [
            'group' => $data['group'],
            'agent' => $data['agent'],
        ];
        $mail = D('admin/mail');
        $result = $mail->agent_search($arr);
        echo json_encode($result);
    }

    //删除用户的消息
    public function del() {
        $id = $_REQUEST['id'];
        $mail = D('admin/mail');
        $res = $mail->del($id);
        if ($res) {
            $arr = [
                'state' => 1
            ];
            echo json_encode($arr);
        } else {
            $arr = [
                'state' => 0
            ];
            echo json_encode($arr);
        }
    }

    //信息
    public function mail_msg() {
        $data = $_REQUEST;
        $arr = [
            'username' => $data['username'],
            'title'    => $data['title'],
            'content'  => $data['content'],
            'min_time' => $data['min_time'],
            'max_time' => $data['max_time'],
            'type'     => 2
        ];

        $mail = D('admin/mail');
        $count = $mail->mail_sousuoCount($arr);
        $pagesize = 20;
        $url = '?m=admin&c=mail&a=mail_msg';
        $page = new page(($count%$pagesize), $pagesize, $url, $arr);
        $show = $page->show();
        $msg = $mail->mail_sousuo($arr, $page->offer, $pagesize);

        include template('mail_msg');
    }

    //站内的信息发送
    public function mail_send_info() {
        $mail = D('admin/mail');
        $agent = $mail->getAgent();
        $group = $mail->getGroup();
        include template('mail_send_info');
    }

}

?>