<?php
/**
 *
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'page.php';

class TtflAction extends Action {

    private $model;

    public function __construct() {
        parent::__construct();
        $this->model = D('admin/ttfl');
    }

    //订单列表
    public function ttfl() {
        $where = $_REQUEST;
        if ($_REQUEST['username'] != "") {
            $uesr_id = $this->model->userId(array("username" => $_REQUEST['username']));
            $where['user_id'] = $uesr_id;
        }

        $reg_type = intval($_REQUEST['reg_type']);
        if ($reg_type == 1) {
            $where['reg_type']= " and u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where['reg_type']= " and u.reg_type = 11 ";
        }else{
            unset($where['reg_type']);
        }

        $pagesize = 20;
        $listSum = $this->model->cntTtfl($where);
        $listCnt = $listSum['cnt'];
        $url = '?m=admin&c=orders&a=order';
        $page = new page($listCnt, $pagesize, '?m=admin&c=ttfl&a=ttfl', $where);
        $show = $page->show();

        $where['page_start'] = $page->offer;
        $where['page_size'] = $pagesize;
        $list = $this->model->ttfl($where);

        $pageSum = array(
            'cz' => 0, //当前页充值交易金额
            'get' => 0//当前页领取金额
        );
        foreach ($list as $key => $value) {
            $pageSum['cz'] += $value['cz_money'];
            $pageSum['get'] += $value['get_money'];
            $list[$key]['get_time'] = date('Y-m-d H:i:s', $value['get_time']);
            if (empty($value['get_time'])) {
                $list[$key]['get_time'] = "-";
            }
        }

        include template('list-ttfl');
    }

    //发送返利
    public function send() {
        $id = $_REQUEST['id'];
        
        $flag   = 'adminTtflSend' . $id;  //操作redis加锁key值
        //操作加锁,如果有人在操作，加锁失败
        if (preventSupervene($flag, 5)) {
            echo json_encode(array('rt' => 0, 'msg' => '操作失败，操作被锁定，请稍后重试！'));
        
            return;
        }
        
        $info = $this->model->info(array("id" => $id));
        $un_account = $this->model->unAccount($info['user_id']);
        $addtime = date("Y-m-d H:i:s");
        
        //判断是否已经发放
        if ($info['status'] != 1) {
            echo json_encode(array("rt" => 0, 'msg' => '奖励已发放，请勿重复操作！'));
            
            return;
        }

        //资金日志
        $logs = array(
            'user_id' => $info['user_id'],
            'order_num' => $info['order_sum'],
            'type' => 18, //返利-字典表
            'money' => $info['get_money'],
            'use_money' => $un_account['money'] + $info['get_money'],
            'remark' => "{$addtime}天天返利{$info['get_money']}元",
            'addtime' => time(),
            'addip' => ip()
        );
        $log = array(
            "id" => $id,
            "user_id" => $info['user_id'],
            "add_money" => $info['get_money']
        );
        //事物提交数据       
        $rt = $this->model->beginTrans($logs, $log,$this->admin['username']);
        echo json_encode(array("rt" => $rt, 'msg' => '操作成功！！'));
    }
    
    //取消发放
    public function sendNo(){
        $rt = $this->model->sendNo($_REQUEST['id']);
        echo json_encode(array("rt" => $rt));
    }

    //一键返利
    public function sendAll()
    {
        $flag = 'adminttflsendAll';

        //操作加锁,设置30分钟后失效
        if (!superveneLock($flag, 1800, 1)) { 
            echo json_encode(array('rt' => 0, 'msg' => '操作失败，操作被锁定，请稍后重试！'));

            return;
        }

        $ids = explode(",", $_REQUEST['id']);
        foreach ($ids as $id) {
            $info = $this->db->getone("select * from un_ttfl_log where id={$id} and status = 1");
            if (empty($info)) {
                continue;
            }
            //$info = $this->model->info(array("id" => $id));
            $un_account = $this->model->unAccount($info['user_id']);
            $addtime = date("Y-m-d H:i:s");
            
            //判断是否已经发放
            if ($info['status'] != 1) continue;

            //资金日志
            $logs = array(
                'user_id' => $info['user_id'],
                'order_num' => $info['order_sum'],
                'type' => 18, //返利-字典表
                'money' => $info['get_money'],
                'use_money' => $un_account['money'] + $info['get_money'],
                'remark' => "{$addtime}天天返利{$info['get_money']}元",
                'addtime' => time(),
                'addip' => ip()
            );
            $log = array(
                "id" => $id,
                "user_id" => $info['user_id'],
                "add_money" => $info['get_money']
            );
            //事物提交数据       
            $rt = $this->model->beginTrans($logs, $log,$this->admin['username']);
        }
        
        //操作解锁 ,60秒后失效
        if (!superveneLock($flag, 30, 0)) {
            echo json_encode(array('rt' => 1, 'msg' => '返水操作成功，但该操作被锁定，4小时后自动解锁！'));
        
            return;
        }
        
        echo json_encode(array("rt" => 1, 'msg'=>'操作完成'));
    }

}
