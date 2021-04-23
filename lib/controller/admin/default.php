<?php
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');

class DefaultAction extends Action {

    protected $model;

    public function __construct() {
        parent::__construct();
        $this->model = D('admin/default');

    }

    //更新常用菜单
    public function saveStockMenu() {

        $menu_id = getParame('menu_id', 1, '', 'int');
        $type = getParame('type', 1, '', 'int');
        $menuInfo = $this->model->getMenuById($menu_id);

        if(!$menuInfo)

            jsonReturn(['code' => 400, 'msg' => '栏目ID错误']);

        if(!$menuInfo['m'] || !$menuInfo['a'] || !$menuInfo['c'])

            jsonReturn(['code' => 400, 'msg' => '只能操作最底层栏目']);
            $stock_menu_json = $this->admin['stock_menu'];
            $stock_menu_arr = [];

        if($stock_menu_json)

            $stock_menu_arr = json_decode($stock_menu_json, 1);

        if($type == 1) {            //添加常用菜单

            if(in_array($menu_id, $stock_menu_arr)) jsonReturn(['code' => 200, 'msg' => 'success']);
            $stock_menu_arr[] = $menu_id;

        }else {                 //移除常用菜单

            if(!in_array($menu_id, $stock_menu_arr)) jsonReturn(['code' => 200, 'msg' => 'success']);
            $stock_menu_arr = array_diff($stock_menu_arr, [$menu_id]);

        }

        $stock_menu_json = json_encode(array_values($stock_menu_arr));
        $res = $this->db->update('un_admin', ['stock_menu' => $stock_menu_json], ['userid' => $this->admin['userid']]);

        if($res) {

			$sessionAdmin = Session::get('admin');
			$sessionAdmin['stock_menu'] = $stock_menu_json;
			Session::set('admin', $sessionAdmin);
            jsonReturn(['code' => 200, 'msg' => 'success']);

        }

        jsonReturn(['code' => 400, 'msg' => '操作失败']);

    }

    //主页
    public function index() {

        $user = $this->admin;
        $roleid = $user['roleid'];
        $userid = $user['userid'];
        $authRt = $this->model->userAuth($roleid);
        $menu = $this->model->indexMenu($this->admin['stock_menu']); //前台菜单  所有
        $stock_menu = $this->model->getStockMenu();         //常用菜单
        $stock_menu_id_arr = [];
        if($stock_menu)
            $stock_menu_id_arr = array_column($stock_menu, 'id');

        $auth_list = array();
        foreach ($authRt as $value) {

            if (empty($value['power_config'])) {
                continue;
            }

            $au = json_decode($value['power_config'], true);

            foreach ($au as $v) {
                if (!in_array($v, $auth_list)) {
                    $auth_list[] = $v;
                }
            }

        }

        if ($user['userid'] == 1) { //1 为超级管理员
            $auth_list = $this->model->allAuth();
        }

        include template('index','new');

    }

    //刷新缓存
    public function refresh(){

        $action = $_REQUEST['action'];
        $param = $_REQUEST['param'];
        $res = $this->refreshRedis($action,$param);
        foreach ($res as $k=>$v){
            echo '-------------------------- <b>'.$k.'</b>--------------------------</br>'.$v; //这里是刷接口的，不能注释掉
        }

    }

    //主页左菜单
    public function index_right() {

        include template('tpl-theme','new');

    }

    //房间内在线人数统计
    public function main() {

        $lottery_type = $_REQUEST['lottery_type']?:0;
        $redis = initCacheRedis();
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        //缓存一个hash数组，键为彩种id，值为彩种名称
        $lottery_map = [];
        foreach ($LotteryTypeIds as $lottery_tmp_v) {

            $tmp_lottery_title = $redis->hGet("LotteryType:{$lottery_tmp_v}", 'name');

            $lottery_map[$lottery_tmp_v] = $tmp_lottery_title;

        }
        $total=array();
        foreach (C('home_arr') as $k=>$v){
            if(is_home($k)){  //要陫除后台统计的
                //组装URL
                $url = $v . "/?m=api&c=workerman&a=getRoomOnline";
                $data = array('s' => 'a8fce04d58c1f06f30da6d33c7523abc');
                $total[] = curl_post($url, $data);
            }
        }

        $totals = array();
        $totalPerson = array();
        $i=0;
        foreach ($total as $k=>$v){
            foreach (decode($v) as $kk=>$vv) {
                //这进而判断
                if($lottery_type>0){

                    $rt = $redis->hget("allroom:{$kk}",'lottery_type');
                    if($lottery_type == $rt){
                        if(!in_array($vv,(isset($totals[$kk]['person'])?$totals[$kk]['person']:[]))){
                            $totals[$kk]['person'] = $vv;
                            $i += $vv;
                        }
                    }

                }else{

                    if(!in_array($vv,(isset($totals[$kk]['person'])?$totals[$kk]['person']:[]))){
                        $totals[$kk]['person'] = $vv;
                        $i += $vv;
                    }
                }
            }
        }

        $time = strtotime('today');
        $totalMoney = 0;
        foreach ($totals as $k=>$v){

            $totals[$k]['pids'] = rtrim($totals[$k]['pids'],',');
            //占比
            $totals[$k]['ray'] = bcadd(($v['person']/$i)*100,0,2).'%';
            //统计投注
            $sql = "SELECT SUM(money) FROM un_orders WHERE room_no={$k} AND reg_type not in (0,8,9,11) AND `addtime`>={$time} AND `state`=0";
            $totals[$k]['bet'] = bcadd($this->db->result($sql),0,2);
            $totalMoney += $totals[$k]['bet'];
            //房间彩种名
            $roomInfo = $redis->hmget("allroom:{$k}",array('lottery_type','title'));
            $lotteryName = $redis->hget("LotteryType:{$roomInfo['lottery_type']}","name");
            $totals[$k]['title'] = $lotteryName.'--'.$roomInfo['title'];

        }
        $totalMoney  = bcadd($totalMoney,0,2);
        deinitCacheRedis($redis);
        include template('main');

    }

    //房间详情
    public function getInfo(){

        $data = $_REQUEST;
        $roomid = $data['roomid'];
        $total=array();
        foreach (C('home_arr') as $k=>$v){
            if(is_home($k)){  //要陫除后台统计的

                //组装URL

                $url = $v . "/?m=api&c=workerman&a=getRoomInfo";

                $data = array('s' => 'a8fce04d58c1f06f30da6d33c7523abc','roomid'=>$roomid);

                $total[] = curl_post($url, $data);

            }
        }

        $time = strtotime('today');
        $list = array();
        foreach ($total as $k=>$v){

            foreach(decode($v) as $kk=>$vv){
                //统计投注
                $sql = "SELECT SUM(money) FROM un_orders WHERE room_no={$roomid} AND `addtime`>={$time} AND `state`=0 AND user_id={$vv}";
                $list[$vv]['bet'] = bcadd($this->db->result($sql),0,2);
                $sql = "SELECT username FROM un_user WHERE id={$vv}";
                $list[$vv]['username'] = $this->db->result($sql);
            }

        }
        echo encode(array(

            'code'=>0,

            'list'=>$list,

        ));

    }


}