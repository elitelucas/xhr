<?php

/**
 * @copyright			(C) 2020 Chan
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'admin' . DS . 'action.php');
include S_CORE . 'class' . DS . 'pages.php';
include S_CORE . 'class' . DS . 'page.php';
class RankAction extends Action {

    public function __construct() {
        parent::__construct();
        
    }

    //菜单管理
    public function menu() {
        include template('menu','new');
    }

    //菜单列表
    public function menuList() {
        $menulist = $this->db->getall("select *  from un_menu order by id   ");
        jsonReturn(['code' => 0,'msg'=>'', 'count' => count($menulist),'data'=>$menulist]);
    }

    //修改菜单
    public function editMenu() {
        if(!empty($_POST)){
            $id = getParame('id', 1, '', 'int');
            $data['name'] = getParame('name', 1, '', 'str');
            $data['parentid'] = getParame('parentid', 0, '', 'int');
            $data['m'] = $_POST['m'];
            $data['c'] = $_POST['c'];
            $data['a'] = $_POST['a'];
            $data['data'] = getParame('data', 0, '', 'str');
            $data['listorder'] = getParame('listorder', 0, '', 'int');
            $data['display'] = getParame('display', 1, '', 'int');
            $r = $this->db->update('un_menu', $data, 'id='.$id);
            if($r)  jsonReturn(['status' => 0, 'ret_msg' => '更新成功']);
            jsonReturn(['status' => 1, 'ret_msg' => '更新失败']);

        }
    }

    //删除菜单
    public function delMenu() {
        $id = getParame('id', 1, '', 'int');

        $sql = "delete from un_menu where id = $id order  by listorder";
        $r = $this->db->query($sql);
        if($r)  jsonReturn(['status' => 0, 'ret_msg' => '删除成功']);
        jsonReturn(['status' => 1, 'ret_msg' => '删除失败']);
    }

	//假人投注榜单
	public function betRank() {

        $page = getParame('page', 0, 1, 'int');
        $count = D('betrank')->getDummyBetRankCount();
        $pageSize = 20;
        $pageObj = new page($count, $pageSize,"",$_REQUEST);
        $show = $pageObj->show();

		$res = D('betrank')->dummyBetRankList($page);
		$rankList = $res['data'];
		foreach($rankList as $key=>&$rank) {
		    $rank['rank'] = ($page-1) * $pageSize + $key + 1;
		    unset($rank);
        }

		include template('bet_rank');
	}
	
	public function addBetRank() {
        if(getParame('is_post', 0)) {
            $ids = getParame('ids', 1, '', 'is_array');
            $money = getParame('money', 1, '', 'is_array');
            $moneyArr = json_decode(str_replace("\\","",$money['data']),true);
            $betData = [];
            foreach($ids as $user) {
                $betData[] = [
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'bet_money' => rand($moneyArr['start_money'], $moneyArr['end_money']),
                ];
                if(count($betData) >= 500) {
                    $this->db->insert('un_bet_rank', $betData);
                    $betData = [];
                }
            }
            $this->db->insert('un_bet_rank', $betData);
            jsonReturn(['status' => 0, 'ret_msg' => '添加成功']);
        }
        //假人列表
        $sql = "select uu.id,uu.nickname,uu.username,ur.user_id from un_user uu LEFT JOIN un_bet_rank ur ON uu.id = ur.user_id WHERE reg_type = 11 AND ur.id IS NULL";
        $dummyList = $this->db->getall($sql);

		include template('add_bet_rank');
	}

	public function delBetRank() {
        $id = getParame('id', 1, '', 'int');

        $sql = "delete from un_bet_rank where id = $id";
        $r = $this->db->query($sql);
        if($r)  jsonReturn(['status' => 0, 'ret_msg' => '删除成功']);
        jsonReturn(['status' => 1, 'ret_msg' => '删除失败']);
    }

    public function editBetRank() {

        $id = getParame('id', 1, '', 'int');
        if(getParame('is_post', 0)) {
            $bet_money = getParame('money', 1, '', 'int');
            $r = $this->db->update('un_bet_rank', ['bet_money' => $bet_money], 'id='.$id);
            if($r)  jsonReturn(['status' => 0, 'ret_msg' => '更新成功']);
            jsonReturn(['status' => 1, 'ret_msg' => '更新失败']);
        }

        $infos = D('betrank')->getRankInfo('id = '.$id);
        include template('edit_bet_rank');
    }
	
	//假人投注规则列表
	public function dummyBetRuleList() {
        $p = getParame('page', 0, 1, 'int');
        $roomList = $this->db->getall("select id,title,low_yb,max_yb from un_room where passwd = ''");
        $count = $this->db->result("select COUNT(id) from un_person_config where type = 3");

        $pageSize = 20;
        $page = new page($count, $pageSize,"",$_REQUEST);
        $show = $page->show();
        $data = $this->db->getall("select * from un_person_config where type = 3 order by id desc limit $page->offer,$pageSize");
        //实例化redis
        $redis = initCacheRedis();
        foreach($data as $key=>$value) {
            $config = json_decode($value['value'],true);//获取房间配置信息
            unset($data[$key]['value']);
            $data[$key]['time'] = $config['startTime'].":00－".$config['endTime'].":00";//时间段
            $data[$key]['conut'] = count($config['ids']);//人数
            $data[$key]['num'] = $config['num'];//每人投注数
            $data[$key]['money'] = $config['money'];//投注金额
            $data[$key]['lottery_type'] = $config['lottery_type'];//彩种

            foreach($roomList as $val) {
                if($val['id'] == $config['room']) {
                    $data[$key]['title'] = $val['title'];//房间
                }
            }

            switch ($config['num']['type']) {
                case 1:
                    $betNum = $config['num']['data']/count($config['ids']);
                    foreach($config['ids'] as $val) {
                        $betNumByU[$val['id']] = $betNum;
                    }
                    break;
                case 2:
                    $betNum = $config['num']['data'];
                    foreach($config['ids'] as $val) {
                        $betNumByU[$val['id']] = $betNum;
                    }
                    break;
                case 3:
                    foreach($config['num']['data'] as $keys=>$val) {
                        $betNumByU[$val['id']] = $val['num'];
                    }
                    break;
            }

            switch ($config['money']['type']) {
                case 1:
                    foreach($config['ids'] as $val) {
                        $betMoneyLimitByU[$val['id']] = [
                            'money_start' => $config['money']['data']['start_money'],
                            'money_end' => $config['money']['data']['end_money'],
                        ];
                    }
                    break;
                case 2:
                    foreach($config['money']['data'] as $keys=>$val) {
                        $betMoneyLimitByU[$val['id']] = [
                            'money_start' => $val['money_start'],
                            'money_end' => $val['money_end'],
                        ];
                    }
                    break;
            }

            $list = $config['ids'];
            foreach($list as &$v) {
                $v['num'] = $betNumByU[$v['id']];
                $v['money'] = $betMoneyLimitByU[$v['id']]['money_start']."-".$betMoneyLimitByU[$v['id']]['money_end'];
                unset($v);
            }

            $url=url('admin','rank',"dummyBetRuleList",array('page'=>$p));
            $data[$key]['list'] = $list;

            //彩种标题
            $lottery_title = $redis->hGet("LotteryType:{$config['lottery_type']}",'name');
            $data[$key]['lottery_title'] = $lottery_title;
        }

        //关闭redis链接
        deinitCacheRedis($redis);
        include template('dummyBetRuleList');
    }

    public function setDummyBetRule() {
        $id = $_GET['id'];
        $roomList = $this->db->getall("select id,title,low_yb,max_yb,lottery_type,lower from un_room where passwd = ''");

        $optDummyNum = 0;
        if(!empty($id))
        {
            $row = $this->db->getone("select value,state from un_person_config where id = {$id}");
            $config = json_decode($row['value'],true);

            $config['status'] = $row['state'];
            foreach($config['ids'] as $val)
            {
                $optDummyNum++;
                $idInfo[]=$val['id'];
            }
            $ids = implode(",",$idInfo);
            unset($config['ids']);
            $config['ids']['room'] = $config['room'];
            $config['ids']['data'] = $this->db->getall("select a.id,a.username,a.nickname,b.money,a.avatar from un_user a left join un_account b on a.id = b.user_id where a.id in(".$ids.")");
        }

        //实例化redis
        $redis = initCacheRedis();
        foreach ($roomList as &$each_info) {
            //彩种标题
            $lottery_title = $redis->hGet("LotteryType:{$each_info['lottery_type']}",'name');
            $each_info['lottery_title'] = $lottery_title;
        }

        //关闭redis链接
        deinitCacheRedis($redis);

        include template('setDummyBetRule');
    }

    public function saveDummyBetRule() {
        $tmp = $_POST;
        $tmp['type'] = 3;
        $arr = D('admin/role')->addDummyConf($tmp);
        echo json_encode($arr);
    }

    //符合条件的假人列表
    public function getDummyList() {
        $roomId = getParame('room_id');

        $list = $this->db->getall("select id,username,nickname,avatar from un_user where reg_type = 11");      //假人列表
		
        //排除已添加的假人  同一个假人不能加入同一个房间
        $sql = "SELECT * FROM `un_person_config` WHERE REPLACE(JSON_EXTRACT(`value`, '$.room'), '\"', '') = $roomId AND type = 3";//
        $res = $this->db->getall($sql);
        $roomDummy = [];
        foreach($res as $r) {
            $valueArr = json_decode($r['value'], 1);
            $uids = array_column($valueArr['ids'], 'id');
            $roomDummy = array_merge($roomDummy, $uids);
        }
        $roomDummy = array_unique($roomDummy);
        $rlist = [];
        foreach($list as $v) {
            if(!in_array($v['id'], $roomDummy)) {
                $rlist[] = $v;
            }
        }

        $arr['code'] = 0;
        $arr['msg'] = "获取成功";
        $arr['list'] = $rlist;
        echo json_encode($arr,JSON_UNESCAPED_SLASHES);
    }
}
