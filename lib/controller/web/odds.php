<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 13:34
 * desc: 获取玩法和赔率
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class OddsAction extends Action {


    public function __construct() {
        parent::__construct();
//        $this->model = D('order');
    }

    /**
     *
     * 进房间时获取房间赔率
     *
     */
    public function getOdds(){
        //验证token
        $this->checkAuth();
        $room_id = $_REQUEST['room_id'];

//        $ways = D('odds')->getOdds($room_id);
        $redis  = initCacheRedis();
        $lottery_type = $redis->hMGet('allroom:'.$room_id, ['lottery_type','match_id']);

        if ($lottery_type['lottery_type'] == 12) {
            $odds_info = decode($redis->hGet("fb_odds",$lottery_type['match_id']));
            if (!empty($odds_info)) {
                foreach ($odds_info as $val) {
                    if ($val['state'] == 0) {
                        if (in_array($val['way'], ['全场单双_单', '全场单双_双'])) {
                            $waysArr['panel_1'][] = $val;
                        }
                        if (in_array($val['way'], ['半场让球_A', '半场让球_B'])) {
                            $waysArr['panel_2']['半场让球'][] = $val;
                        }
                        if (in_array($val['way'], ['半场大小_大', '半场大小_小'])) {
                            $waysArr['panel_2']['半场大小'][] = $val;
                        }
                        if (in_array($val['way'], ['全场让球_A', '全场让球_B'])) {
                            $waysArr['panel_3']['全场让球'][] = $val;
                        }
                        if (in_array($val['way'], ['全场大小_大', '全场大小_小'])) {
                            $waysArr['panel_3']['全场大小'][] = $val;
                        }
                        if (in_array($val['way'], ['加时让球_A', '加时让球_B'])) {
                            $waysArr['panel_4']['加时让球'][] = $val;
                        }
                        if (in_array($val['way'], ['加时大小_大', '加时大小_小'])) {
                            $waysArr['panel_4']['加时大小'][] = $val;
                        }
                        if (in_array($val['way'], ['点球让球_A', '点球让球_B'])) {
                            $waysArr['panel_5']['点球让球'][] = $val;
                        }
                        if (in_array($val['way'], ['点球大小_大', '点球大小_小'])) {
                            $waysArr['panel_5']['点球大小'][] = $val;
                        }

                        $a = explode("_", $val['way']);
                        if (in_array($a[0], ['半场入球'])) {
                            $waysArr['panel_6'][$a[0]][] = $val;
                        }
                        if (in_array($a[0], ['全场入球'])) {
                            $waysArr['panel_6'][$a[0]][] = $val;
                        }
                        if (in_array($a[0], ['半/全场'])) {
                            $waysArr['panel_7'][] = $val;
                        }
                        if (in_array($a[0], ['全场比分'])) {
                            $waysArr['panel_8'][] = $val;
                        }
                        if (in_array($a[0], ['半场'])) {
                            $waysArr['panel_9']["半场赛果"][] = $val;
                        }
                        if (in_array($a[0], ['全场'])) {
                            $waysArr['panel_9']["全场赛果"][] = $val;
                        }
                    }
                }
//                array_multisort(array_column($waysArr['panel_2']['半场让球'], 'sort'), SORT_DESC, $waysArr['panel_2']['半场让球']);


                if (!empty($waysArr)){
                    foreach ($waysArr as $key => &$way_value) {
                        if ($key == "panel_1" || $key == "panel_7" || $key == "panel_8") {
                            $sort_arr = array_column($waysArr[$key], 'sort');
                            array_multisort($sort_arr, SORT_ASC, $waysArr[$key]);
                        } else {
                            foreach ($way_value as &$value) {
                                $sort_arr = array_column($value, 'sort');
                                array_multisort($sort_arr, SORT_ASC, $value);
                            }
                        }
                    }
                }
                $data = array('code'=>0,'msg'=>'sccess','data' => $waysArr);
            } else {
                $data = array('code'=>1,'msg'=>'No data!');
            }
            echo encode($data);
            return false;
        }
        deinitCacheRedis($redis);

        $ways = D('odds')->getOdds($room_id);
        if(!empty($ways)){
//            $data = array('code'=>0,'msg'=>'sccess','data'=>decode($ways)); //这里要改回去
            $waysArr = decode($ways);
            $waysArr['panel_3'] = empty($waysArr['panel_3']) ? (object)array() : $waysArr['panel_3'];
//            $waysArr['panel_3'][''] = empty($waysArr['panel_3']) ? (object)array() : $waysArr['panel_3'];
            $waysArr['panel_4'] = empty($waysArr['panel_4']) ? (object)array() : $waysArr['panel_4'];

            if(in_array($lottery_type['lottery_type'],array(2,4))){
                $waysArr['panel_5'] = empty($waysArr['panel_5']) ? array() : $waysArr['panel_5'];
            }else{
                $waysArr['panel_5'] = empty($waysArr['panel_5']) ? (object)array() : $waysArr['panel_5'];
            }
            $waysArr['panel_6'] = empty($waysArr['panel_6']) ? (object)array() : $waysArr['panel_6'];
            $data = array('code'=>0,'msg'=>'sccess','data'=>$waysArr);
            echo encode($data);
        }else{
            $data = array('code'=>1,'msg'=>'No data!');
            echo encode($data);
        }
        return false;
    }

}
