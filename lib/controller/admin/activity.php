<?php

/**
 * @copyright			(C) 2013 CHENGHUITONG.COM
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'pages.php';
include S_CORE . 'class' . DS . 'page.php';

class ActivityAction extends Action {
    private $activity;
    private $page_cnt;
    public $event_num;
    public $event_num_1;
    public function __construct() {
        parent::__construct();

        $this->activity = D('admin/activity');

        //获取每页展示多少数据
        $redis = initCacheRedis();
        $page_cfg = $redis->hGetAll("Config:100009"); //获取每页展示多少数据
        $this->page_cnt = $page_cfg['value']?$page_cfg['value']:20;
        deinitCacheRedis($redis);
        $this->event_num = $this->db->getone("select event_num from #@_activity where state = 1 and activity_type = 2")['event_num'];
        $this->event_num_1 = $this->db->getone("select event_num from #@_activity where state = 1 and activity_type = 1")['event_num'];
        $this->event_num_2 = $this->db->getone("select event_num from #@_activity where state = 1 and activity_type = 3")['event_num'];
    }

    //薄饼列表
    public function boBinList(){
        $where = ['activity_type'=>1];
        $count = $this->activity->getCount("un_activity",$where);
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=boBinList", $where);
        $show = $page->show();
        $filed = 'id,title,start_time,end_time,state,value,event_num';
        $order = 'event_num desc';
        $limit = $page->offer.",".$pageSize;
        $data = $this->activity->getListNew($filed, $where, $order, $limit, "un_activity");
        foreach ($data as $key=>$val){
            $data[$key]['start_time'] = date("Y-m-d H:i:s",$val['start_time']);
            $data[$key]['end_time'] = date("Y-m-d H:i:s",$val['end_time']);
            $data[$key]['event_num'] = "第 ".$val['event_num']." 期";
            $data[$key]['value'] = json_decode($val['value'],true);
        }
        include template('boBin-list');
    }

    //开启停止活动提交方法
    public function editStopOrStartAct(){
        $id = $_REQUEST['id'];
        $state = $_REQUEST['state'];
        $activity_type = $_REQUEST['activity_type'];
        $this->activity->admin_id = $this->admin['userid'];
        $arr = $this->activity->stopOrStart($id, $state, $activity_type);

        //活动中心逻辑里，博饼活动的 act_type 值为3，双旦活动的 act_type 值为4，九宫格活动的 act_type 值为5，福袋活动的 act_type 值为6，刮刮乐活动的 act_type 值为7，两个值均为固定值
        if ($activity_type == '1') {
            $act_type = 3;
        } elseif ($activity_type == '2') {
            $act_type = 4;
        } elseif ($activity_type == '3') {
            $act_type = 5;
        } elseif ($activity_type == '4') {
            $act_type = 6;
        } elseif ($activity_type == '5') {
            $act_type = 7;
        }
        D('Actcenter')->updateActIsUnderway($act_type);
        echo json_encode($arr);
    }



    //删除活动方法
    public function boBinDel(){
        $id = $_REQUEST['id'];
        $actInfo = $this->db->getone("select * from un_activity where id = $id");
        if(!$actInfo) {
            exit(json_encode(['code' => -1, 'msg' => '活动不存在']));
        }

        $rows = $this->db->delete("un_activity", ['id'=>$id]);
        if($rows > 0){
            $log_remark = "删除".$this->activity->actType[$actInfo['activity_type']]."--活动名称:".$actInfo['title'].'--期数:'.$actInfo['event_num'];
            admin_operation_log($this->admin['userid'], 120, $log_remark);

            $arr['code'] = "0";
            $arr['msg'] = "操作成功";
        } else {
            $arr['code'] = "-1";
            $arr['msg'] = "操作失败";
        }
        echo json_encode($arr);

    }

    //配置管理页面
    public function boBinConfEdit(){
        $list = $this->db->getone("select value from un_config where nid = 'bo_bin'")['value'];
        $list = json_decode($list,true);
        include template('boBin-conf-edit');
    }

    //博饼配置管理提交方法
    public function boBinConfAct(){
        $data = [
            'isUserName'=>$_REQUEST['isUserName'],
            'isTime'=>$_REQUEST['isTime']
        ];
        $row = $this->activity->editBoBinConf($data);
        echo json_encode($row);
        exit;
    }

    /**
     * 博饼中奖记录
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-25 11:10
     */
    public function winList(){
        $where = "a.activity_type = 1";
        $event_num = $_REQUEST['event_num'];
        $username = $_REQUEST['username'];
        $prize_type = $_REQUEST['prize_type'];
        if($event_num > 0){
            $where.= " and a.event_num = {$event_num}";
        }
        if(!empty($username)){
            $where.= " and a.username = '".$username."'";
        }

        if($prize_type != 0){
            $where.= " and a.prize_type = '{$prize_type}'";
        }

        $reg_type = intval($_REQUEST['reg_type']);
        if ($reg_type == 1) {
            $where .= " and u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where .= " and u.reg_type = 11 ";
        }

        if(!empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time']) ){
            $start_time = strtotime($_REQUEST['start_time']." 00:00:00");
            $end_time = strtotime($_REQUEST['end_time']." 23:59:59");
            $where.= " and a.add_time between {$start_time} and {$end_time}";
        }
        $countSql = "select count(a.id) count from #@_activity_prize as a left join un_user as u on a.user_id = u.id where $where";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=winList", ['event_num'=>$event_num,'username'=>$username,'start_time'=>$_REQUEST['start_time'],'end_time'=>$_REQUEST['end_time']]);
        $show = $page->show();
        $filed = '*';
        $order = 'integral desc';
        $limit = $page->offer.",".$pageSize;
//        $data = $this->activity->getListNew($filed, $where, $order, $limit, "un_activity_prize");
        $sql = "select a.id,a.user_id,a.activity_id,a.username,a.prize_type,a.prize_project,a.prize_name,a.event_num,a.add_time,a.last_updatetime,a.giving_status,a.remark,a.send_people_id,a.send_people_name from un_activity_prize as a left join un_user as u on a.user_id = u.id where ".$where. " limit ".$limit;
        $data = $this->db->getall($sql);
        foreach ($data as $key=>$val){
            $data[$key]['add_time'] = date("Y-m-d H:i:s",$val['add_time']);
        }

        $redis = initCacheRedis();
        $value = json_decode($redis->hGet("Config:"."bo_bin","value"),true);
        deinitCacheRedis($redis);
        for ($a = 1; $a < $value['max_event_num']; $a++) {
            $eventNumInfo[] = $a;
        }

        //当前页派送元宝数据
        $prize_money_arr = array_column($data, 'prize_money');
        $sum_current_page = array_sum($prize_money_arr);

        $sum_all = $this->activity->countAwardSum($where);

        include template('boBin-win-list');
    }

    /**
     * 抽奖记录
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-25 11:10
     */
    public function getDrawRecord(){
        $event_num = $_REQUEST['event_num'];
        $activity_id = $_REQUEST['activity_id'];
        $uid = $_REQUEST['user_id'];
        $where = [
            'activity_type'=>1,
            'event_num'=>$event_num,
            'activity_id'=>$activity_id,
            'user_id'=>$uid
        ];
        $count = $this->activity->getCount("un_activity_log",$where);
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=getDrawRecord", $where);
        $show = $page->show();
        $filed = 'id,event_num,prize_name,prize_value,add_time';
        $order = 'add_time desc';
        $limit = $page->offer.",".$pageSize;
        $data = $this->activity->getListNew($filed, $where, $order, $limit, "un_activity_log");
        foreach ($data as $key=>$val){
            $data[$key]['add_time'] = date("Y-m-d",$val['add_time']);
        }
        include template('getDrawRecord');
    }

    /**
     * 派奖
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-25 11:10
     */
    public function sendPrize(){
        $id = $_REQUEST['id'];
        $arr = $this->activity->sendPrize($id);
        echo json_encode($arr);
    }

    /**
     * 圣诞活动列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-25 11:10
     */
    public function christmasList(){
        $where = ['activity_type'=>2];
        $count = $this->activity->getCount("un_activity",$where);
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=christmasList", $where);
        $show = $page->show();
        $filed = 'id,title,start_time,end_time,state,value,event_num,add_admin_id';
        $order = 'event_num desc';
        $limit = $page->offer.",".$pageSize;
        $data = $this->activity->getListNew($filed, $where, $order, $limit, "un_activity");
        foreach ($data as $key=>$val){
            $data[$key]['start_time'] = date("Y-m-d H:i:s",$val['start_time']);
            $data[$key]['end_time'] = date("Y-m-d H:i:s",$val['end_time']);
            $data[$key]['event_num'] = $val['event_num'];
            $data[$key]['value'] = json_decode($val['value'],true);
            $data[$key]['admin_name'] = $this->db->getone("select username from #@_admin where userid = {$val['add_admin_id']}")['username'];
        }
        include template('christmas-list');
    }

    /**
     * 圣诞活动添加或编辑方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-25 11:40
     */
    public function activityEditAct(){
        $data = $_REQUEST;
        $data['activity_type'] = $data['type'];
        $type = $data['type'];
        unset($data['type']);
        $data['add_admin_id'] = $this->admin['userid'];
        if ($type == 1) {
            $rows = $this->activity->addBoBinConf($data);
        } elseif ($type == 2) {
            $rows = $this->activity->addChristmasEdit($data);
        } elseif ($type == 3) {
            $rows = $this->activity->addNineEdit($data);
        } elseif ($type == 4) {
            $rows = $this->activity->addLuckyBagEdit($data);
        } elseif ($type == 5) {
            $rows = $this->activity->addScratchEdit($data);
        }
        echo json_encode($rows);
    }



    /**
     * 圣诞活动中奖列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-25 11:40
     */
    public function christmasWin(){

        $redis = initCacheRedis();
        $value = json_decode($redis->hGet("Config:"."christmas_max","value"),true);
        deinitCacheRedis($redis);
        for ($a = 1; $a < $value; $a++) {
            $eventNumInfo[] = $a;
        }

        $where = "a.activity_type = 2";
        $event_num = $_REQUEST['event_num'];
        if($event_num > 0){
            $where.= " and a.event_num = '{$event_num}'";
        } else {
            if (!isset($event_num)) {
                $sql = "SELECT event_num FROM `un_activity` WHERE activity_type = 2 and state = 1";
                $event_num = $this->db->result($sql);
                $event_num = empty($event_num) ? 1 : $event_num ;
                $where.= " and a.event_num = '{$event_num}'";
            }

        }
        $username = $_REQUEST['username'];
        $prize_type = $_REQUEST['prize_type'];

        $reg_type = intval($_REQUEST['reg_type']);
        if ($reg_type == 1) {
            $where .= " and u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where .= " and u.reg_type = 11 ";
        }

        if(!empty($username)){
            $where.= " and a.username = '{$username}'";
        }

        if($prize_type != 0){
            $where.= " and a.prize_type = '{$prize_type}'";
        }
        if(!empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time']) ){
            $start_time = strtotime($_REQUEST['start_time']." 00:00:00");
            $end_time = strtotime($_REQUEST['end_time']." 23:59:59");
            $where.= " and a.add_time between {$start_time} and {$end_time}";
        }
        $countSql = "select count(a.id) count from #@_activity_prize as a left join un_user as u on a.user_id = u.id where $where";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=christmasWin", ['username'=>$username,'event_num'=>$event_num,'prize_type'=>$prize_type,'start_time'=>$_REQUEST['start_time'],'end_time'=>$_REQUEST['end_time']]);
        $show = $page->show();
        $order = " order by a.add_time desc";
        $limit = " limit ".$page->offer.",".$pageSize;
//        $dataSql = "select * from #@_activity_prize where $where $order $limit";
        $sql = "select a.id,a.user_id,a.activity_id,a.username,a.prize_type,a.prize_project,a.prize_name,a.event_num,a.add_time,a.last_updatetime,a.giving_status,a.remark,a.send_people_id,a.send_people_name from un_activity_prize as a left join un_user as u on a.user_id = u.id where ".$where.$order.$limit;
        $data = $this->db->getall($sql);
//        $data = $this->db->getall($dataSql);

        $sum_all = $this->activity->countAwardSum($where);
        //当前页派送元宝数据
        $prize_money_arr = array_column($data, 'prize_money');
        $sum_current_page = array_sum($prize_money_arr);
        include template('christmas-win');
    }

    /**
     * 活动参与详情页面
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-27 15:16
     */
    public function participateUser(){

        $id = $_REQUEST['id'];
        $username = $_REQUEST['username'];
        $activity_type = $_REQUEST['type'];


        $where = "n.activity_type = {$activity_type}";
        if(!empty($username)){
            $where.= " and u.username = '{$username}'";
        }
        if($id != 0){
            $where.= " and n.activity_id = $id";
        }
        $countSql = "select count(n.id) count from #@_activity_num n left join #@_user u on u.id = n.user_id where $where";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=participateUser", ['id'=>$id,'username'=>$username,'type'=>$activity_type]);
        $show = $page->show();
        $limit = "limit ".$page->offer.",".$pageSize;
        $dataSql = "select n.*, u.username, u.group_id, a.end_time, a.state, g.name, a.level_limit, n.activity_type
                    from #@_activity_num n 
                    left join #@_user u on u.id = n.user_id 
                    left join #@_activity a on a.id = n.activity_id 
                    left join #@_user_group g on g.id = u.group_id
                    where $where $limit";
        $data = $this->db->getall($dataSql);

        foreach ($data as $key => $val) {
            $level_limit = json_decode($val['level_limit'],true);
            if(in_array($val['group_id'],$level_limit)){
                $data[$key]['level_limit'] = "<font color='green'>".$val['name']."</font>";
            } else {
                $data[$key]['level_limit'] = "<font color='red'>".$val['name']."</font>";
            }
        }
        include template('participate-user');
    }

    /**
     * 获取抽奖次数详情在某期活动中
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-27 15:16
     */
    public function getNumDetails(){
        $activity_id = $_REQUEST['id'];
        $user_id = $_REQUEST['user_id'];
        $type = $_REQUEST['type'];
        $where = "activity_type = $type and user_id = $user_id and activity_id = $activity_id";
        $countSql = "select count(id) count from #@_activity_num_log where $where";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=getNumDetails", ['id'=>$activity_id,'user_id'=>$user_id, 'type'=>$type]);
        $show = $page->show();
        $order = "order by add_time desc";
        $limit = "limit ".$page->offer.",".$pageSize;
        $dataSql = "select * from #@_activity_num_log where $where $order $limit";
        $data = $this->db->getall($dataSql);
        include template('christmas-num-details');
    }

    /**
     * 飘窗配置列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-2-1 11:48
     */
    public function barrageConfigList(){
        $list = [];
        $countSql = "select count(id) count from #@_person_config where type = 2";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=barrageConfigList",[]);
        $show = $page->show();
        $order = "order by id desc";
        $limit = "limit ".$page->offer.",".$pageSize;
        $dataSql = "select * from #@_person_config where type = 2 $order $limit";
        $data = $this->db->getall($dataSql);
        $redis = initCacheRedis();
        foreach ($data as $key => $val) {
            $value = json_decode($val['value'],true);
            $tmp = [
                'id' => $val['id'],
                'time' => $value['start_time']."-".$value['end_time'],
                'lottery_name' => $redis->hGet("LotteryType:{$value['lottery_type']}",'name'),
                'room_name' => $redis->hMGet("PublicRoom{$value['lottery_type']}:{$value['room_id']}",['title'])['title'],
                'user_num' => count($value['user_info']),
                'send_num' => $value['num'],
                'barrage_money' => $value['barrage_type']['start_money']."-".$value['barrage_type']['end_money'],
                'barrage_type' => $value['barrage_type']['name'],
                'state' => $val['state'],
                'state_name' => $val['state'] == 1 ? "<font color='green'>开启</font>":"<font color='red'>关闭</font>"
            ];
            $list[] = $tmp;
        }

        deinitCacheRedis($redis);

        include template('barrage-config-list');
    }

    /**
     * 中奖飘窗列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-2-1 11:48
     */
    public function barrageList(){
        $redis = initCacheRedis();
        $lottery_list = D('admin/orders')->lottyList();
        //实例化redis
        $where = "where 1=1";
        $search = [];
        $start_time = $_REQUEST['start_time'];
        $end_time = $_REQUEST['end_time'];
        $nickname = $_REQUEST['nickname'];
        $lottery_type = $_REQUEST['lottery_type'];

        if (!empty($start_time) && !empty($end_time)) {
            $where .= " and win_time BETWEEN ".strtotime($start_time." 00:00:00")." AND ".strtotime($end_time." 23:59:59");
        }
        if (!empty($nickname)) {
            $where .= " and nickname = '".$nickname."'";
        }
        if (!empty($lottery_type)) {
            $where .= " and lottery_type = {$lottery_type}";
        }
        $countSql = "select count(id) count from #@_barrage_win {$where}";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=barrageList&start_time={$start_time}&end_time=$end_time&nickname=$nickname&lottery_type=$lottery_type",[]);
        $show = $page->show();
        $order = "order by win_time desc";
        $limit = "limit ".$page->offer.",".$pageSize;
        $dataSql = "select * from #@_barrage_win $where $order $limit";
        $data = $this->db->getall($dataSql);
        foreach ($data as $key => $val) {
            $data[$key]['lottery_name'] = $redis->hGet("LotteryType:{$val['lottery_type']}",'name');
            $data[$key]['win_time'] = date("Y-m-d H:i:s",$val['win_time']);
        }
        deinitCacheRedis($redis);
        include template('barrage-list');
    }

    /**
     * 飘窗设置页面
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-2-1 11:48
     */
    public function barrageSet(){
        $type = trim($_REQUEST['type']);
        $redis = initCacheRedis();
        $config = json_decode($redis->hGet("Config:"."barrage_config","value"),true);
        $lotteryList = $this->db->getall("select lottery_type,way from un_odds_copy");
        $cup_info = $this->db->getall("select lottery_type,way from #@_cup_odds group by way");
        $lotteryList = array_merge($lotteryList,$cup_info);
        //实例化redis
        $lottery_list = [];
        $way_info = [];
        foreach ($lotteryList as $key => $each_info) {
            //彩种标题
            $lottery_list[$each_info['lottery_type']] = $redis->hGet("LotteryType:{$each_info['lottery_type']}",'name');
            if (in_array($each_info['lottery_type'],['2','4','7','8','9'])) {
                $arr = explode('_',$each_info['way']);
                if (in_array($arr[0],['三中二之中三','二中特之中特'])) {
                    unset($lotteryList[$key]);
                }
                if (in_array($arr[0],['三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中', '五不中', '六不中', '七不中', '八不中', '九不中', '十不中','二尾连中', '三尾连中', '四尾连中', '二尾连不中', '三尾连不中', '四尾连不中', '冠亚'])) {
                    $lotteryList[$key]['way'] = $arr[0];
                }
            }
        }
        foreach ($lotteryList as $val) {
            if (!@in_array($val['way'],$way_info[$val['lottery_type']])) {
                $way_info[$val['lottery_type']][] = $val['way'];
            }

        }
        //关闭redis链接
        deinitCacheRedis($redis);
        $id = trim($_REQUEST['id']);
        if (!empty($id)) {
            $data = $this->db->getone("select * from #@_person_config where id = {$id}");
            $data['value'] = json_decode($data['value'],true);
            $list = $this->db->getall("SELECT username,nickname,id,avatar FROM un_user WHERE reg_type = 9 AND id IN(".implode(",",array_column($data['value']['user_info'], 'user_id')).")");

        } else {

            $list = $this->db->getall("SELECT username,nickname,id,avatar FROM un_user WHERE reg_type = 9 AND id NOT IN(SELECT user_id FROM un_role WHERE conf_id IN(SELECT id FROM un_person_config WHERE TYPE = 2))");

        }
        include template('barrage-set');
    }

    /**
     * 飘窗设置方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-2-1 11:48
     */
    public function barrageAct(){
        $type = trim($_REQUEST['type']);
        if ($type == "rule") {
            $data = [
                'nid' => 'barrage_config',
                'value' => json_encode($_REQUEST['config'],JSON_UNESCAPED_UNICODE),
                'name' => '中奖飘窗规则',
            ];
            $rs = $this->activity->barrageSetAct($data);
            echo json_encode($rs);
            exit;

        } elseif ($type == "config") {
            $rs = $this->activity->barrageConfigAct($_REQUEST);
            echo json_encode($rs);
            exit;
        }
    }

    /**
     * 自动飘窗列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-2-1 11:48
     */
    public function barrageAutoList() {
        $id = trim($_REQUEST['id']);
        $where = '';
        if (!empty($id)) {
            $where = "where conf_id = {$id}";
        }
        $list = [];
        $countSql = "select count(id) count from #@_barrage_auto {$where}";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=barrageAutoList&id={$id}",[]);
        $show = $page->show();
        $order = "order by barrage_time desc";
        $limit = "limit ".$page->offer.",".$pageSize;
        $dataSql = "select * from #@_barrage_auto $where $order $limit";
        $data = $this->db->getall($dataSql);
        $redis = initCacheRedis();
        foreach ($data as $key => $val) {
            $user_info = $this->db->getone("select avatar,nickname from #@_user where id = {$val['user_id']}");
            $data[$key]['nickname'] = $user_info['nickname'];
            $data[$key]['avatar'] = $user_info['avatar'];
            $data[$key]['lottery_name'] = $redis->hGet("LotteryType:{$val['lottery_type']}",'name');
            $data[$key]['barrage_time'] = date("Y-m-d H:i:s",$val['barrage_time']);
        }
        deinitCacheRedis($redis);
        include template('barrage-auto-list');
    }

    /**
     * 获取房间玩法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     */
    public function getWayByRoom(){
        $room_id = trim($_REQUEST['room_id']);
        $way = $this->activity->getListNew('way', ['room'=>$room_id], '', '', "#@_odds");
        foreach ($way as $val) {
            $array = explode("_",$val['way']);
            if (in_array($array[0],['三中二','三全中','二全中','二中特','特串','二肖连中','三肖连中','四肖连中','二肖连不中','三肖连不中','四肖连不中','五不中','六不中','七不中','八不中','九不中','十不中','二尾连中','三尾连中','四尾连中','二尾连不中','三尾连不中','四尾连不中'])) {
                $val['way'] = $array[0];
            }
            if (!in_array($array[0],['三中二之中三','二中特之中特'])) {
                $list[$val['way']] = [];
            }
        }
        $list = array_keys($list);
        if (empty($list)){
            $arr['code'] = "-1";
            $arr['msg'] = "房间玩法获取失败";
        } else {
            $arr['code'] = "0";
            $arr['data'] = $list;
            $arr['msg'] = "请求成功";
        }
        echo json_encode($arr,JSON_UNESCAPED_UNICODE);

    }

    /**
     * 九宫格活动列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     */
    public function nineList(){
        $where = ['activity_type'=>3];
        $count = $this->activity->getCount("un_activity",$where);
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=nineList", $where);
        $show = $page->show();
        $filed = 'id,title,start_time,end_time,state,value,event_num,add_admin_id';
        $order = 'event_num desc';
        $limit = $page->offer.",".$pageSize;
        $data = $this->activity->getListNew($filed, $where, $order, $limit, "un_activity");
        foreach ($data as $key=>$val){
            $data[$key]['start_time'] = date("Y-m-d H:i:s",$val['start_time']);
            $data[$key]['end_time'] = date("Y-m-d H:i:s",$val['end_time']);
            $data[$key]['event_num'] = $val['event_num'];
            $data[$key]['value'] = json_decode($val['value'],true);
            $data[$key]['admin_name'] = $this->db->getone("select username from #@_admin where userid = {$val['add_admin_id']}")['username'];
        }
        include template('nine-list');
    }

    /**
     * 添加或编辑页面
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-25 11:40
     */
    public function activityEdit(){
        $groupInfo = $this->db->getall("select id,name from un_user_group where state = 0");
        $id = $_REQUEST['id'];
        $type = $_REQUEST['type'];
        if(!empty($id)){
            $list = $this->db->getone("select * from #@_activity where activity_type = {$type} and id = '{$id}'");
            $list['level_limit'] = json_decode($list['level_limit'],true);
            $list['rules_play'] = json_decode($list['rules_play'],true);
            $list['value'] = json_decode($list['value'],true);
            $list['value']['details'] = str_replace("<br />","\n",$list['value']['details']);
            $list['value']['statement'] = str_replace("<br />","\n",$list['value']['statement']);
            $maxNum = $list['event_num'];
        } else {
            $redis = initCacheRedis();
            if ($type == 1) {
                $value = json_decode($redis->hGet("Config:bo_bin","value"),true);
                $maxNum = $value['max_event_num'];
            } elseif($type == 2) {
                $maxNum = $redis->hGet("Config:christmas_max","value");
            } elseif($type == 3) {
                $maxNum = $redis->hGet("Config:nine_gong_max","value");
            } elseif($type == 4) {
                $maxNum = $redis->hGet("Config:lucky_bag_max","value");
                $maxNum = empty($maxNum) ? 1 : $maxNum ;
            } elseif($type == 5) {
                $maxNum = $redis->hGet("Config:scratch_max","value");
            }
            $maxNum = empty($maxNum) ? 1 : $maxNum ;
            deinitCacheRedis($redis);
        }

        if ($type == 1) {
            include template('boBin-edit');
        } elseif($type == 2) {
            include template('christmas-edit');
        } elseif($type == 3) {
            include template('nine-edit');
        } elseif($type == 4) {
            include template('lucky-bag-edit');
        } elseif($type == 5) {
            include template('scratch-edit');
        }
    }

    /**
     * 复制活动配置页面
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-27 15:16
     */
    public function copyConfig(){
        $id = $_REQUEST['id'];
        $type = $_REQUEST['type'];
        $groupInfo = $this->db->getall("select id,name from un_user_group where state = 0");

        $list = $this->activity->getOneData("select * from un_activity where id = {$id}");
        unset($list['event_num']);
        unset($list['id']);
        $list['state'] = 1;
        $list['level_limit'] = json_decode($list['level_limit'],true);
        $list['rules_play'] = json_decode($list['rules_play'],true);
        $list['value'] = json_decode($list['value'],true);

        if (!empty($list['value']['details'])) {
            $list['value']['details'] = str_replace("<br />","\n",$list['value']['details']);
        }
        if (!empty($list['value']['prize_config'])) {
            foreach ($list['value']['prize_config'] as $key=>$val) {
                $list['value']['prize_config'][$key]['prize_remainder'] = $val['prize_num'];
            }
        }

        if ($type == 1) {
            $redis = initCacheRedis();
            $value = json_decode($redis->hGet("Config:bo_bin","value"),true);
            $maxNum = $value['max_event_num'];
            deinitCacheRedis($redis);
            include template('boBin-edit');
        } elseif($type == 2) {
            $redis = initCacheRedis();
            $maxNum = $redis->hGet("Config:christmas_max","value");
            deinitCacheRedis($redis);
            include template('christmas-edit');
        } elseif($type == 3) {
            $redis = initCacheRedis();
            $maxNum = $redis->hGet("Config:nine_gong_max","value");
            deinitCacheRedis($redis);
            include template('nine-edit');
        } elseif($type == 4) {
            $redis = initCacheRedis();
            $maxNum = $redis->hGet("Config:lucky_bag_max","value");
            deinitCacheRedis($redis);
            include template('lucky-bag-edit');
        } elseif($type == 5) {
            $redis = initCacheRedis();
            $maxNum = $redis->hGet("Config:scratch_max","value");
            deinitCacheRedis($redis);
            include template('scratch-edit');
        }


    }

    /**
     * 调整抽奖次数列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-27 15:16
     */
    public function adjustNumList(){
        $activityId = $_REQUEST['id'];
        $type = $_REQUEST['type'];
        $username = $_REQUEST['username'];
        $where = "activity_type = {$type} and activity_id = $activityId and add_type = 4";
        if(!empty($username)){
            $where.= " and u.username = '{$username}'";
        }
        if(!empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time']) ){
            $start_time = strtotime($_REQUEST['start_time']." 00:00:00");
            $end_time = strtotime($_REQUEST['end_time']." 23:59:59");
            $where.= " and l.add_time between {$start_time} and {$end_time}";
        }
        $countSql = "select count(l.id) count from #@_activity_num_log l left join #@_user u on u.id = l.user_id left join #@_admin a on a.userid = l.operation_id where $where";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=adjustNumList", ['id'=>$activityId,'username'=>$username,'type'=>$type]);
        $show = $page->show();
        $limit = "limit ".$page->offer.",".$pageSize;
        $dataSql = "select l.id,l.event_num,u.username,l.num,l.add_time,a.username as adminname,l.explain,l.type from #@_activity_num_log l left join #@_user u on u.id = l.user_id left join #@_admin a on a.userid = l.operation_id where $where $limit";
        $data = $this->db->getall($dataSql);
        include template('adjust-num-list');
    }

    /**
     * 调整抽奖次数页面
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-27 15:16
     */
    public function adjustNumEdit(){
        $id = $_REQUEST['id'];
        $type = $_REQUEST['type'];
        include template('adjust-num-edit');
    }

    /**
     * 调整抽奖次数操作
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-27 15:16
     */
    public function adjustNineNumAct(){
        $data = $_REQUEST;
        $data['operation_id'] = $this->admin['userid'];
        $data['add_time'] = time();
        $data['add_type'] = 4;
        $row = $this->activity->addNumLog($data);
        echo json_encode($row);
    }

    /**
     * 九宫格活动中奖列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-10-25 11:40
     */
    public function nineWin(){

        $redis = initCacheRedis();
        $value = $redis->hGet("Config:"."nine_gong_max","value");
        deinitCacheRedis($redis);
        for ($a = 1; $a < $value; $a++) {
            $eventNumInfo[] = $a;
        }
        $where = "a.activity_type = 3";
        $event_num = $_REQUEST['event_num'];
        if($event_num > 0){
            $where.= " and a.event_num = '{$event_num}'";
        } else {
            if (!isset($event_num)) {
                $sql = "SELECT event_num FROM `un_activity` WHERE activity_type = 3 and state = 1";
                $event_num = $this->db->result($sql);
                $event_num = empty($event_num) ? 1 : $event_num ;
                $where.= " and a.event_num = '{$event_num}'";
            }
        }

        $username = $_REQUEST['username'];
        $prize_type = $_REQUEST['prize_type'];

        $reg_type = intval($_REQUEST['reg_type']);
        if ($reg_type == 1) {
            $where .= " and u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where .= " and u.reg_type = 11 ";
        }

        if(!empty($username)){
            $where.= " and a.username = '{$username}'";
        }

        if($prize_type != 0){
            $where.= " and a.prize_type = '{$prize_type}'";
        }
        if(!empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time']) ){
            $start_time = strtotime($_REQUEST['start_time']." 00:00:00");
            $end_time = strtotime($_REQUEST['end_time']." 23:59:59");
            $where.= " and a.add_time between {$start_time} and {$end_time}";
        }
        $countSql = "select count(a.id) count from #@_activity_prize as a left join un_user as u on a.user_id = u.id  where $where";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=nineWin", ['username'=>$username,'event_num'=>$event_num,'prize_type'=>$prize_type,'start_time'=>$_REQUEST['start_time'],'end_time'=>$_REQUEST['end_time']]);
        $show = $page->show();
        $order = "order by add_time desc";
        $limit = "limit ".$page->offer.",".$pageSize;
        $dataSql = "select a.* from #@_activity_prize as a left join un_user as u on a.user_id = u.id where $where $order $limit";
        $data = $this->db->getall($dataSql);

        $sum_all = $this->activity->countAwardSum($where);
        //当前页派送元宝数据
        $prize_money_arr = array_column($data, 'prize_money');
        $sum_current_page = array_sum($prize_money_arr);
        include template('nine-win');
    }

    /**
     * @name 平台任务配置页面
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-16 16:20:09
     */
    public function taskConf(){
        $config = [];
        $redis = initCacheRedis();
        $configJson = $redis->hGet('Config:task_config', 'value');
        $config = json_decode($configJson,true);
        deinitCacheRedis($redis);
        if (!empty($config)) {
            foreach ($config as $key=>$val) {
                foreach ($val['config'] as $ke=>$va) {
                    $config[$key]['config'][$ke]['explain'] = str_replace("<br />","\n",$va['explain']);
                }
            }
        }



        include template('task-config');

    }

    /**
     * @name 平台任务配置提交方法
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-17 11:39:50
     */
    public function taskConfAct(){
        $data = [
            "type" =>$_REQUEST['type'],
            "config"=> $_REQUEST['config'],
            'total' => $_REQUEST['total'],
        ];
        $rows = $this->activity->taskConfAct($data);
        echo json_encode($rows);
    }

    /**
     * @name 平台任务奖励列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-04-19 18:59:57
     */
    public function taskList() {
        $username = trim($_REQUEST['username']);
        $type = trim($_REQUEST['type']);
        $state = trim($_REQUEST['state']);
        $complete_start_time = trim($_REQUEST['complete_start_time']);
        $complete_end_time = trim($_REQUEST['complete_end_time']);
        $receive_start_time = trim($_REQUEST['receive_start_time']);
        $receive_end_time = trim($_REQUEST['receive_end_time']);
        $where = 'where 1=1';
        if (!empty($username) && isset($username)) {
            $where .= " and username = '{$username}'";
        }
        if (!empty($state) && isset($state)) {
            $where .= " and state = {$state}";
        }
        if (!empty($type) && isset($type)) {
            $where .= " and type = {$type}";
        }
        if (!empty($complete_start_time) && !empty($complete_end_time)) {
            $complete_start_time = strtotime($complete_start_time);
            $complete_end_time = strtotime($complete_end_time) + 86399;
            $where .= " and complete_time between {$complete_start_time} and {$complete_end_time}";
        }
        if (!empty($receive_start_time) && !empty($receive_end_time)) {
            $receive_start_time = strtotime($receive_start_time);
            $receive_end_time = strtotime($receive_end_time) + 86399;
            $where .= " and receive_time between {$receive_start_time} and {$receive_end_time}";
        }
        $countSql = "select count(id) count from #@_task_prize $where";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=taskList", ['username'=>$username,'type'=>$type,'state'=>$state,'complete_start_time'=>$_REQUEST['complete_start_time'],'complete_end_time'=>$_REQUEST['complete_end_time'],'receive_start_time'=>$_REQUEST['receive_start_time'],'receive_end_time'=>$_REQUEST['receive_end_time']]);
        $show = $page->show();
        $order = "order by id desc";
        $limit = "limit ".$page->offer.",".$pageSize;
        $dataSql = "select * from #@_task_prize $where $order $limit";

        $data = $this->db->getall($dataSql);
        $pageTotal['complete_total'] = 0;
        $pageTotal['invalid_time_total'] = 0;
        $pageTotal['receive_total'] = 0;
        foreach ($data as $val) {
            if ($val['state'] == 1) {
                $pageTotal['complete_total'] += $val['money'];
            } elseif($val['state'] == 2){
                $pageTotal['receive_total'] += $val['money'];
            } elseif ($val['state'] == 3) {
                $pageTotal['invalid_time_total'] += $val['money'];
            }
        }
        $total = $this->activity->taskTotal($where);
        include template('task-list');
    }

    /**
     * 平台任务限制
     * @copyright gpgao
     * @date 2018-06-20 17:41:16
     */
    public function taskLimit(){
        $config = [];
        $redis = initCacheRedis();
        $configJson = $redis->hGet('Config:task_limit', 'value');
        $config = json_decode($configJson,true);
        deinitCacheRedis($redis);
        include template('task-limit');
    }

    /**
     * 平台任务限制提交方法
     * @copyright gpgao
     * @date 2018-06-20 18:00:48
     */
    public function taskLimitAct(){
        $data['nid'] = 'task_limit';
        $data['value'] = encode(['recharge'=>$_REQUEST['recharge'],'betting'=>$_REQUEST['betting']]);
        $data['name'] = "平台任务限制";
        $data['desc'] = "满足其中一条即可参与活动";
        $rs = $this->activity->barrageSetAct($data);
        echo json_encode($rs);
    }


    /**
     * 活动背景图设置页面
     * @copyright gpgao
     * @date 2018-06-06 15:13:32
     * @param type int 1:大转盘  2:九宫格 3:福袋  4：刮刮乐
     */
    public function backgroundConfig(){
        $type = trim($_GET['type']);
        $redis = initCacheRedis();
        $configJson = $redis->hGet('Config:back_ground_config', 'value');
        $config = json_decode($configJson,true);
        deinitCacheRedis($redis);
        $back_type = "";
        if (!empty($config)) {
            foreach ($config as $val) {
                if ($val['activity_type'] == $type ) {
                    $back_type = $val['back_type'];
                }
            }
        }

        include template('activity-background-config');
    }

    /**
     * 活动背景图设置方法
     * @copyright gpgao
     * @date 2018-06-06 15:13:32
     */
    public function backConfAct(){
        $data = [
            'activity_type'=>$_REQUEST['activity_type'],
            'back_type'=>$_REQUEST['back_type']
        ];

        if (!empty($data['activity_type']) && !empty($data['back_type'])) {
            $res = $this->activity->backConfAct($data);
            if ($res > 0 || $res !== false) {
                $rs = [
                    'code'=>0,
                    'msg'=>"操作成功"
                ];
            } else {
                $rs = [
                    'code'=>-1,
                    'msg'=>"操作失败"
                ];
            }
        } else {
            $rs = [
                'code'=>-1,
                'msg'=>"非法请求，缺少必要参数"
            ];

        }
        echo json_encode($rs);

    }

    /**
     * 福袋格活动列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     */
    public function luckyBagList(){
        $where = ['activity_type'=>4];
        $count = $this->activity->getCount("un_activity",$where);
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=luckyBagList", $where);
        $show = $page->show();
        $filed = 'id,title,start_time,end_time,state,value,event_num,add_admin_id';
        $order = 'event_num desc';
        $limit = $page->offer.",".$pageSize;
        $data = $this->activity->getListNew($filed, $where, $order, $limit, "un_activity");
        foreach ($data as $key=>$val){
            $data[$key]['start_time'] = date("Y-m-d H:i:s",$val['start_time']);
            $data[$key]['end_time'] = date("Y-m-d H:i:s",$val['end_time']);
            $data[$key]['event_num'] = $val['event_num'];
            $data[$key]['value'] = json_decode($val['value'],true);
            $data[$key]['admin_name'] = $this->db->getone("select username from #@_admin where userid = {$val['add_admin_id']}")['username'];
        }
        include template('lucky-bag-list');
    }

    /**
     * 福袋格活动中奖列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     */
    public function luckyBagWin(){

        $redis = initCacheRedis();
        $value = $redis->hGet("Config:"."lucky_bag_max","value");
        deinitCacheRedis($redis);
        for ($a = 1; $a < $value; $a++) {
            $eventNumInfo[] = $a;
        }
        $where = "a.activity_type = 4";
        $event_num = $_REQUEST['event_num'];
        if($event_num > 0){
            $where.= " and a.event_num = '{$event_num}'";
        } else {
            if (!isset($event_num)) {
                $sql = "SELECT event_num FROM `un_activity` WHERE activity_type = 4 and state = 1";
                $event_num = $this->db->result($sql);
                $event_num = empty($event_num) ? 1 : $event_num ;
                $where.= " and a.event_num = '{$event_num}'";
            }
        }

        $username = $_REQUEST['username'];
        $prize_type = $_REQUEST['prize_type'];

        if(!empty($username)){
            $where.= " and a.username = '{$username}'";
        }

        if($prize_type != 0){
            $where.= " and a.prize_type = '{$prize_type}'";
        }
        $reg_type = intval($_REQUEST['reg_type']);
        if ($reg_type == 1) {
            $where .= " and u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where .= " and u.reg_type = 11 ";
        }
        if(!empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time']) ){
            $start_time = strtotime($_REQUEST['start_time']." 00:00:00");
            $end_time = strtotime($_REQUEST['end_time']." 23:59:59");
            $where.= " and a.add_time between {$start_time} and {$end_time}";
        }
        $countSql = "select count(a.id) count from #@_activity_prize as a left join un_user as u on a.user_id = u.id  where $where";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=luckyBagWin", ['username'=>$username,'event_num'=>$event_num,'prize_type'=>$prize_type,'start_time'=>$_REQUEST['start_time'],'end_time'=>$_REQUEST['end_time']]);
        $show = $page->show();
        $order = "order by a.add_time desc";
        $limit = " limit ".$page->offer.",".$pageSize;
        $dataSql = "select a.* from #@_activity_prize as a left join un_user as u on a.user_id = u.id where $where $order $limit";
        $data = $this->db->getall($dataSql);

        $sum_all = $this->activity->countAwardSum($where);
        //当前页派送元宝数据
        $prize_money_arr = array_column($data, 'prize_money');
        $sum_current_page = array_sum($prize_money_arr);

        include template('lucky-bag-win');
    }

    /**
     * 刮刮乐活动列表
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2017-11-06 11:55
     */
    public function scratchList(){
        $where = ['activity_type'=>5];
        $count = $this->activity->getCount("un_activity",$where);
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=scratchList", $where);
        $show = $page->show();
        $filed = 'id,title,start_time,end_time,state,value,event_num,add_admin_id';
        $order = 'event_num desc';
        $limit = $page->offer.",".$pageSize;
        $data = $this->activity->getListNew($filed, $where, $order, $limit, "un_activity");
        foreach ($data as $key=>$val){
            $data[$key]['start_time'] = date("Y-m-d H:i:s",$val['start_time']);
            $data[$key]['end_time'] = date("Y-m-d H:i:s",$val['end_time']);
            $data[$key]['event_num'] = $val['event_num'];
            $data[$key]['value'] = json_decode($val['value'],true);
            $data[$key]['admin_name'] = $this->db->getone("select username from #@_admin where userid = {$val['add_admin_id']}")['username'];
        }
        include template('scratch-list');
    }

    public function scratchWin(){

        $redis = initCacheRedis();
        $value = $redis->hGet("Config:"."scratch_max","value");
        deinitCacheRedis($redis);
        for ($a = 1; $a < $value; $a++) {
            $eventNumInfo[] = $a;
        }
        $where = "a.activity_type = 5";
        $event_num = $_REQUEST['event_num'];
        $reg_type = intval($_REQUEST['reg_type']);
        if ($reg_type == 1) {
            $where .= " and u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where .= " and u.reg_type = 11 ";
        }

        if($event_num > 0){
            $where.= " and a.event_num = '{$event_num}'";
        } else {
            if (!isset($event_num)) {
                $sql = "SELECT event_num FROM `un_activity` WHERE activity_type = 4 and state = 1";
                $event_num = $this->db->result($sql);
                $event_num = empty($event_num) ? 1 : $event_num ;
                $where.= " and a.event_num = '{$event_num}'";
            }
        }

        $username = $_REQUEST['username'];
        $prize_type = $_REQUEST['prize_type'];

        if(!empty($username)){
            $where.= " and a.username = '{$username}'";
        }

        if($prize_type != 0){
            $where.= " and a.prize_type = '{$prize_type}'";
        }
        if(!empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time']) ){
            $start_time = strtotime($_REQUEST['start_time']." 00:00:00");
            $end_time = strtotime($_REQUEST['end_time']." 23:59:59");
            $where.= " and a.add_time between {$start_time} and {$end_time}";
        }
        $countSql = "select count(a.id) count from #@_activity_prize as a left join un_user as u on a.user_id = u.id where $where";
        $count = $this->db->getone($countSql)['count'];
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=scratchWin", ['username'=>$username,'event_num'=>$event_num,'prize_type'=>$prize_type,'start_time'=>$_REQUEST['start_time'],'end_time'=>$_REQUEST['end_time']]);
        $show = $page->show();
        $order = "order by add_time desc";
        $limit = "limit ".$page->offer.",".$pageSize;
        $dataSql = "select a.* from #@_activity_prize as a left join un_user as u on a.user_id = u.id  where $where $order $limit";
        $data = $this->db->getall($dataSql);

        $sum_all = $this->activity->countAwardSum($where);
        //当前页派送元宝数据
        $prize_money_arr = array_column($data, 'prize_money');
        $sum_current_page = array_sum($prize_money_arr);

        include template('scratch-win');
    }

    public function onlineHandsel(){
        @$type = $_POST["type"];
        @$username = $_POST["username"];
        @$start_time = strtotime($_POST["start_time"]);
        @$end_time = strtotime($_POST["end_time"]);
        @$quick = $_POST["quick"];
        if($quick != "0"&&$quick !=""){
            switch ($quick){
                case 1:
                    $start_time = strtotime(date("Y-m-d",strtotime("0 day")));
                    $end_time = $start_time + 86399;
                    break;
                case 2:
                    $start_time = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $end_time = $start_time + 86399;
                    break;
                case 3:
                    $start_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $end_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $start_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $start_time = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
        }
        $where = " 1=1 ";
        if(isset($type)&&$type!="") $where .= " AND o.type = '$type'";
        if(isset($username)&&$username!="") $where .= " AND o.username = '$username'";
        if(isset($end_time)&&$end_time>0){
            $end_time+= 86399;
            $where .= " AND o.create_time < '$end_time'";
        }
        if(isset($start_time)&&$start_time>0) $where .= " AND o.create_time > '$start_time'";
        $reg_type = intval($_REQUEST['reg_type']);
        if ($reg_type == 1) {
            $where .= " and u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where .= " and u.reg_type = 11 ";
        }
       
        $countSql = "select count(o.id) count from un_online_handsel as o LEFT join un_user as u on o.user_id = u.id where $where";
        $count = $this->db->result($countSql);
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=onlineHandsel", ['username'=>$username,'type'=>$type,'quick'=>$quick,'start_time'=>$start_time,'end_time'=>$end_time]);
        $show = $page->show();
        $order = "order by o.create_time desc";
        $limit = "limit ".$page->offer.",".$pageSize;

        $sql = 'SELECT PC.id,PC.name FROM un_payment_config PC RIGHT JOIN '
            . '(SELECT D.id FROM un_dictionary D LEFT JOIN un_dictionary_class DC ON D.classid=DC.id WHERE DC.id = 13) F'
            . ' ON F.id=PC.type';
        $type_list = O('model')->db->getall($sql, 'id');

        $type_arr = columnIdName($type_list);
        $sql = "select o.* from un_online_handsel as o LEFT join un_user as u on o.user_id = u.id where $where $order $limit";

        $list = $this->db->getall($sql);
        $current_money = 0;
        $current_feedback_money = 0;
        foreach ($list as $k=>$i){
            $current_money += $i["money"];
            $current_feedback_money += $i["handsel"];
            $list[$k]["create_time"] = date("Y-m-d H:i:s",$i['create_time']);
            @$list[$k]["type"] = $type_arr[$i["type"]];
        }

        if(isset($start_time)&&$start_time!=0) $start_time = date("Y-m-d",$start_time);
        if(isset($end_time)&&$end_time!=0&&$end_time!=86399) $end_time = date("Y-m-d",$end_time);
        elseif ($end_time==86399) $end_time = "";
        $auto_online_handsel = $this->db->result("select value from un_config where nid = 'auto_online_handsel'");
        $sql = "select sum(money),sum(handsel) from un_online_handsel as o LEFT join un_user as u on o.user_id = u.id";
        $ress = $this->db->getone($sql);
        $total_money = $ress["sum(money)"];
        $total_feedback_money = $ress["sum(handsel)"];

        $conf = $this->db->result('select value from un_config where nid="list_total_conf"');
        if($conf) $conf = explode(',',$conf);
        else $conf = [];
        $roleid = $this->admin["roleid"];
        $check = in_array($roleid,$conf);
        include template('online_handsel');
    }

    public function offlineHandsel(){
        @$type = $_POST["type"];
        @$way = $_POST["way"];
        @$username = $_POST["username"];
        @$start_time = strtotime($_POST["start_time"]);
        @$end_time = strtotime($_POST["end_time"]);
        @$quick = $_POST["quick"];
        if($quick != "0"&&$quick !=""){
            switch ($quick){
                case 1:
                    $start_time = strtotime(date("Y-m-d",strtotime("0 day")));
                    $end_time = $start_time + 86399;
                    break;
                case 2:
                    $start_time = strtotime(date("Y-m-d",strtotime("-1 day")));
                    $end_time = $start_time + 86399;
                    break;
                case 3:
                    $start_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600)));
                    $end_time = strtotime(date('Y-m-d',(time()-((date('w')==0?7:date('w'))-1)*24*3600))) + 6*86400 + 86399;
                    break;
                case 4:
                    $start_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')));
                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-'.date('t', time()).' 00:00:00'))) + 86399;
                    break;
                case 5:
                    $start_time = strtotime(date('Y-m-d',strtotime('-1 month', strtotime(date('Y-m', time()).'-01 00:00:00'))));
                    $end_time = strtotime(date('Y-m-d',strtotime(date('Y-m', time()).'-01 00:00:00')-86399));
                    break;
            }
        }
        $where = " 1=1 ";
        if(isset($way)&&$way!="") $where .= " AND o.way = '$way'";
        if(isset($type)&&$type!="") $where .= " AND o.type = '$type'";
        if(isset($username)&&$username!="") $where .= " AND o.username = '$username'";
        if(isset($end_time)&&$end_time>0){
            $end_time+= 86399;
            $where .= " AND o.create_time < '$end_time'";
        }
        if(isset($start_time)&&$start_time>0) $where .= " AND o.create_time > '$start_time'";
        $reg_type = intval($_REQUEST['reg_type']);
        if ($reg_type == 1) {
            $where .= " and u.reg_type NOT IN (0,8,9,11)";
        } elseif ($reg_type == 2) {
            $where .= " and u.reg_type = 11 ";
        }
        $countSql = "select count(o.id) count from un_offline_handsel as o LEFT join un_user as u on o.user_id = u.id where $where";
        $count = $this->db->result($countSql);
        $pageSize = $this->page_cnt;
        $page = new page($count, $pageSize, "?m=admin&c=activity&a=offlineHandsel", ['username'=>$username,'type'=>$type,'quick'=>$quick,'start_time'=>$start_time,'end_time'=>$end_time]);
        $show = $page->show();
        $order = "order by o.create_time desc";
        $limit = "limit ".$page->offer.",".$pageSize;

        $sql = "select o.* from un_offline_handsel as o LEFT join un_user as u on o.user_id = u.id where $where $order $limit";
        $list = $this->db->getall($sql);
        $auto_offline_handsel = $this->db->result("select value from un_config where nid = 'auto_offline_handsel'");
        //初始化redis
        $redis = initCacheRedis();
        $LTrade = $redis->lRange('DictionaryIds10', 0, -1);
        $type_list = [];
        foreach ($LTrade as $v){
            $res = $redis->hMGet("Dictionary10:" . $v, array('id', 'name'));
            $type_list[] = $res;
        }
        //关闭redis链接
        deinitCacheRedis($redis);
        $type_arr = columnIdName($type_list);
        $sql = "SELECT `id`, `name` FROM `un_dictionary` WHERE classid in (1,14,15,17,18,19,20)"; //1-银行信息
        $way_list = $this->db->getall($sql);
        $way_arr = columnIdName($way_list);
        $current_money = 0;
        $current_feedback_money = 0;
        foreach ($list as $k=>$i){
            $current_money += $i["money"];
            $current_feedback_money += $i["handsel"];
            $list[$k]["create_time"] = date("Y-m-d H:i:s",$i['create_time']);
            @$list[$k]["type"] = $type_arr[$i["type"]];
            @$list[$k]["way"] = $way_arr[$i["way"]];
        }

        if(isset($start_time)&&$start_time!=0) $start_time = date("Y-m-d",$start_time);
        if(isset($end_time)&&$end_time!=0&&$end_time!=86399) $end_time = date("Y-m-d",$end_time);
        elseif ($end_time==86399) $end_time = "";

        $sql = "select sum(money),sum(handsel) from un_offline_handsel  as o LEFT join un_user as u on o.user_id = u.id where".$where;
        $ress = $this->db->getone($sql);
        $total_money = $ress["sum(money)"];
        $total_feedback_money = $ress["sum(handsel)"];
        $conf = $this->db->result('select value from un_config where nid="list_total_conf"');
        if($conf) $conf = explode(',',$conf);
        else $conf = [];
        $roleid = $this->admin["roleid"];
        $check = in_array($roleid,$conf);
        include template('offline_handsel');
    }

    public function setAutoOnlineHandsel(){
        @$set = $_POST['set'];
        $auto_online_handsel = $this->db->result("select value from un_config where nid = 'auto_online_handsel'");
        if(empty($auto_online_handsel)){
            $data = [];
            $data["value"] = $set;
            $data["nid"] = "auto_online_handsel";
            $data["desc"] = "是否自动彩金到账";
            $data["name"] = "是否自动彩金到账";
            $this->db->insert('un_config',$data);
        }
        $data = [];
        $data["value"] = $set;
        $where = [];
        $where["nid"] = "auto_online_handsel";
        $this->db->update('un_config',$data,$where);
        echo "ok";
    }

    public function setOnlineHandselStatus(){
        @$status = $_POST["status"];
        @$id = $_POST["id"];

        if($status==1){
            $order = $this->db->getone("select user_id,handsel,order_id from un_online_handsel where id = $id");
            $where=[];
            $user_id = $order["user_id"];
            $where['user_id'] = $order["user_id"];
            $user_money = $this->db->result("select money from un_account where user_id = ".$order["user_id"]);
            $data = [];
            $data["money"] = "+=".$order['handsel'];
            D('account')->save($data, $where);

            $where=[];
            $where['id'] = $id;
            $data = [];
            $data["status"] = 1;
            $this->db->update('un_online_handsel',$data,$where);

            $acount_log['user_id'] = $user_id;
            $acount_log['order_num'] = $order['order_id'];
            $acount_log['type'] = 1071;
            $acount_log['money'] = $order['handsel'];
            $acount_log['use_money'] = $user_money + $order['handsel'];
            $acount_log['remark'] = '用户id为:' . $where['user_id'] . ' 充值送彩金:' . $order['handsel'] . '成功';
            $acount_log['verify'] = 0;
            $acount_log['addtime'] = time();
            $acount_log['addip'] = ip();
            D('accountlog')->aadAccountLog($acount_log);
        }else{
            $where=[];
            $where['id'] = $id;
            $data = [];
            $data["status"] = 2;
            $this->db->update('un_online_handsel',$data,$where);
        }
    }

    public function setAutoOfflineHandsel(){
        @$set = $_POST['set'];
        $auto_online_handsel = $this->db->result("select value from un_config where nid = 'auto_offline_handsel'");
        if(empty($auto_online_handsel)){
            $data = [];
            $data["value"] = $set;
            $data["nid"] = "auto_offline_handsel";
            $data["desc"] = "是否自动彩金到账";
            $data["name"] = "是否自动彩金到账";
            $this->db->insert('un_config',$data);
        }
        $data = [];
        $data["value"] = $set;
        $where = [];
        $where["nid"] = "auto_offline_handsel";
        $this->db->update('un_config',$data,$where);
        echo "ok";
    }

    public function setOfflineHandselStatus(){
        @$status = $_POST["status"];
        @$id = $_POST["id"];

        if($status==1){
            $order = $this->db->getone("select user_id,handsel,order_id from un_offline_handsel where id = $id");
            $where=[];
            $user_id = $order["user_id"];
            $where['user_id'] = $order["user_id"];
            $user_money = $this->db->result("select money from un_account where user_id = ".$order["user_id"]);
            $data = [];
            $data["money"] = "+=".$order['handsel'];
            D('account')->save($data, $where);

            $where=[];
            $where['id'] = $id;
            $data = [];
            $data["status"] = 1;
            $this->db->update('un_offline_handsel',$data,$where);

            $acount_log['user_id'] = $user_id;
            $acount_log['order_num'] = $order['order_id'];
            $acount_log['type'] = 1071;
            $acount_log['money'] = $order['handsel'];
            $acount_log['use_money'] = $user_money + $order['handsel'];
            $acount_log['remark'] = '用户id为:' . $where['user_id'] . ' 充值送彩金:' . $order['handsel'] . '成功';
            $acount_log['verify'] = 0;
            $acount_log['addtime'] = time();
            $acount_log['addip'] = ip();
            D('accountlog')->aadAccountLog($acount_log);
        }else{
            $where=[];
            $where['id'] = $id;
            $data = [];
            $data["status"] = 2;
            $this->db->update('un_offline_handsel',$data,$where);
        }
    }

    function setAutoAccount(){
        $auto_online_handsel = $this->db->result("select value from un_config where nid = 'auto_online_handsel'");
        include template('set_auto_account');
    }

    function setAutoAccountOff(){
        $auto_offline_handsel = $this->db->result("select value from un_config where nid = 'auto_offline_handsel'");
        include template('set_auto_account_off');
    }
}
