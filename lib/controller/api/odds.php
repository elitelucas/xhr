<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 13:34
 * desc: 获取玩法和赔率
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class OddsAction extends Action {



    /**
     * 刷新缓存
     * @method POST
     * @param $action string 方法名 刷新全部 all
     * @param $param string 参数  刷新全部 all
     * @return array
     */
    public function refreshRedis($action, $param) {
        if (empty($action) || empty($param)) {
            return array('status' => 100002, 'data' => " 缺少刷新参数");
        }

        $arr=array();
        $param = array(
            'pass' => C('pass'),
            'action' => $action,
            'param' => $param
        );
        //组装URL
        foreach (C('home_arr') as $k=>$v){
            $url  =  $v."/index.php?m=api&c=initCache&a=index";
            lg('do_init_cache','url'.$url);
            $arr[$k]=http_post_json($url,$param);
        }
        return $arr;
    }

    /**
     *
     * 进房间时获取房间赔率
     *
     */
    public function getOdds(){
        //验证token
        $this->checkAuth();

        //验证参数
//        $this->checkInput($_REQUEST, array('token','type','id'));
        $room_id = $_REQUEST['room_id'];
        $redis  = initCacheRedis();
        $lottery_type = $redis->hMGet('allroom:'.$room_id, ['lottery_type','match_id']);
        $waysArr = [];
        if ($lottery_type['lottery_type'] == 12) {
            $odds_info = decode($redis->hGet("fb_odds",$lottery_type['match_id']));
            if (!empty($odds_info)) {
                foreach ($odds_info as $val) {
                    if ($val['state'] == 0) {
                        if (in_array($val['way'],['全场单双_单','全场单双_双'])) {
                            $waysArr['panel_1'][] = $val;
                        }
                        if (in_array($val['way'],['半场让球_A','半场让球_B'])) {
                            $waysArr['panel_2']['半场让球'][] = $val;
                        }
                        if (in_array($val['way'],['半场大小_大','半场大小_小'])) {
                            $waysArr['panel_2']['半场大小'][] = $val;
                        }
                        if (in_array($val['way'],['全场让球_A','全场让球_B'])) {
                            $waysArr['panel_3']['全场让球'][] = $val;
                        }
                        if (in_array($val['way'],['全场大小_大','全场大小_小'])) {
                            $waysArr['panel_3']['全场大小'][] = $val;
                        }
                        if (in_array($val['way'],['加时让球_A','加时让球_B'])) {
                            $waysArr['panel_4']['加时让球'][] = $val;
                        }
                        if (in_array($val['way'],['加时大小_大','加时大小_小'])) {
                            $waysArr['panel_4']['加时大小'][] = $val;
                        }
                        if (in_array($val['way'],['点球让球_A','点球让球_B'])) {
                            $waysArr['panel_5']['点球让球'][] = $val;
                        }
                        if (in_array($val['way'],['点球大小_大','点球大小_小'])) {
                            $waysArr['panel_5']['点球大小'][] = $val;
                        }
                        $a = explode("_",$val['way']);
                        if (in_array($a[0],['半场入球'])) {
                            $waysArr['panel_6'][$a[0]][] = $val;
                        }
                        if (in_array($a[0],['全场入球'])) {
                            $waysArr['panel_6'][$a[0]][] = $val;
                        }
                        if (in_array($a[0],['半/全场'])) {
                            $waysArr['panel_7'][] = $val;
                        }
                        if (in_array($a[0],['全场比分'])) {
                            $waysArr['panel_8'][] = $val;
                        }
                        if (in_array($a[0],['半场'])) {
                            $waysArr['panel_9']["半场赛果"][] = $val;
                        }
                        if (in_array($a[0],['全场'])) {
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
            return;
        }
        deinitCacheRedis($redis);
        $ways = D('odds')->getOdds($room_id);
        if(!empty($ways)){
//            $data = array('code'=>0,'msg'=>'sccess','data'=>decode($ways)); //这里要改回去
            $waysArr = decode($ways);
            $waysArr['panel_3'] = empty($waysArr['panel_3']) ? array() : $waysArr['panel_3'];
            $waysArr['panel_4'] = empty($waysArr['panel_4']) ? array() : $waysArr['panel_4'];

            if(in_array($lottery_type['lottery_type'],array(2,4))){
                $waysArr['panel_5'] = empty($waysArr['panel_5']) ? array() : $waysArr['panel_5'];
            }else{
                $waysArr['panel_5'] = empty($waysArr['panel_5']) ? array() : $waysArr['panel_5'];
            }
            $waysArr['panel_6'] = empty($waysArr['panel_6']) ? array() : $waysArr['panel_6'];
            $data = array('code'=>0,'msg'=>'sccess','data'=>$waysArr);
            echo encode($data);
        }else{
            $data = array('code'=>1,'msg'=>'No data!');
            echo encode($data);
        }
        return false;
    }

    /**
     * 获取世界杯大小赔率接口
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-05-07 16:10:48
     */
    public function getCupDataDX(){
        $data = $this->getCupData();
        if($data){
            $this->saveCupOdds($data);
        }
    }

    /**
     * 获取赛程接口
     * @copyright 2018-05-14 16:46:34
     * @return void
     */
    public function getCupAgainst(){
        $get_info = file_get_contents('php://input','r');
        lg("word_cup_against","开始接受赛程数据::{$get_info}");
        $data = decode($get_info);
        foreach ($data as $val) {
            $post_data = [
                'event_name' => $val['event_name'],
                'match_date' => $val['match_time'],
                'team_1_name' => $val['teamone_name'],
                'team_2_name' => $val['teamtwo_name'],
                'add_time' => time(),
                'match_id' => $val['match_id'],
                'match_state' => $val['match_flag']
            ];

            $sql = "select id from #@_cup_against where match_id = {$val['match_id']}";
            $list = $this->db->getone($sql);
            if (empty($list)) {
                $this->db->insert("#@_cup_against",$post_data);
            } else {
                $this->db->update("#@_cup_against",$post_data,['match_id'=>$val['match_id']]);
            }
        }

    }

    /**
     * 保存世界杯赔率数据
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-05-10 17:11:15
     * @param $data
     */
    public function saveCupOdds($data){
        $sql = "select id,way,odds,handicap,is_auto,match_id from #@_cup_odds where match_id = {$data['match_id']}";
        $list = $this->db->getall($sql);
        if (empty($list)) {
            $post_data_1 = [
                '半场让球_A' =>[
                    'lottery_type' => 12,
                    'way' => '半场让球_A',
                    'odds' => $data['lst_home_team_rang_odds'],
                    'handicap' => $data['lst_home_team_rang_pk'],
                    'sort' => 3,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场让球_B' =>[
                    'lottery_type' => 12,
                    'way' => '半场让球_B',
                    'odds' => $data['lst_visiting_team_rang_odds'],
                    'handicap' => $data['lst_visiting_team_rang_pk'],
                    'sort' => 4,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场大小_大' =>[
                    'lottery_type' => 12,
                    'way' => '半场大小_大',
                    'odds' => $data['lst_big_odds'],
                    'handicap' => $data['lst_big_pk'],
                    'sort' => 5,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场大小_小' =>[
                    'lottery_type' => 12,
                    'way' => '半场大小_小',
                    'odds' => $data['lst_small_odds'],
                    'handicap' => $data['lst_small_pk'],
                    'sort' => 6,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场让球_A' =>[
                    'lottery_type' => 12,
                    'way' => '全场让球_A',
                    'odds' => $data['home_team_rang_odds'],
                    'handicap' => $data['home_team_rang_pk'],
                    'sort' => 7,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场让球_B' =>[
                    'lottery_type' => 12,
                    'way' => '全场让球_B',
                    'odds' => $data['visiting_team_rang_odds'],
                    'handicap' => $data['visiting_team_rang_pk'],
                    'sort' => 8,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场大小_大' =>[
                    'lottery_type' => 12,
                    'way' => '全场大小_大',
                    'odds' => $data['big_odds'],
                    'handicap' => $data['big_pk'],
                    'sort' => 9,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场大小_小' =>[
                    'lottery_type' => 12,
                    'way' => '全场大小_小',
                    'odds' => $data['small_odds'],
                    'handicap' => $data['small_pk'],
                    'sort' => 10,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场单双_单' =>[
                    'lottery_type' => 12,
                    'way' => '全场单双_单',
                    'odds' => $data['single_odds'],
                    'sort' => 1,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场单双_双' =>[
                    'lottery_type' => 12,
                    'way' => '全场单双_双',
                    'odds' => $data['double_odds'],
                    'sort' => 2,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '加时让球_A' =>[
                    'lottery_type' => 12,
                    'way' => '加时让球_A',
                    'odds' => $data['home_team_overtime_rang_odds'],
                    'handicap' => $data['home_team_overtime_rang_pk'],
                    'sort' => 11,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '加时让球_B' =>[
                    'lottery_type' => 12,
                    'way' => '加时让球_B',
                    'odds' => $data['visiting_team_overtime_rang_odds'],
                    'handicap' => $data['visiting_team_overtime_rang_pk'],
                    'sort' => 12,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '加时大小_大' =>[
                    'lottery_type' => 12,
                    'way' => '加时大小_大',
                    'odds' => $data['overtime_big_odds'],
                    'handicap' => $data['overtime_big_pk'],
                    'sort' => 13,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '加时大小_小' =>[
                    'lottery_type' => 12,
                    'way' => '加时大小_小',
                    'odds' => $data['overtime_small_odds'],
                    'handicap' => $data['overtime_small_pk'],
                    'sort' => 14,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '点球让球_A' =>[
                    'lottery_type' => 12,
                    'way' => '点球让球_A',
                    'odds' => $data['home_team_penalty_rang_odds'],
                    'handicap' => $data['home_team_penalty_rang_pk'],
                    'sort' => 15,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '点球让球_B' =>[
                    'lottery_type' => 12,
                    'way' => '点球让球_B',
                    'odds' => $data['visiting_team_penalty_rang_odds'],
                    'handicap' => $data['visiting_team_penalty_rang_pk'],
                    'sort' => 16,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '点球大小_大' =>[
                    'lottery_type' => 12,
                    'way' => '点球大小_大',
                    'odds' => $data['penalty_big_odds'],
                    'handicap' => $data['penalty_big_pk'],
                    'sort' => 17,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '点球大小_小' =>[
                    'lottery_type' => 12,
                    'way' => '点球大小_小',
                    'odds' => $data['penalty_small_odds'],
                    'handicap' => $data['penalty_small_pk'],
                    'sort' => 18,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场入球_0' =>[
                    'lottery_type' => 12,
                    'way' => '半场入球_0',
                    'odds' => $data['lst_total_balls']['1st_total_balls_0'],
                    'handicap' => "",
                    'sort' => 19,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场入球_1' =>[
                    'lottery_type' => 12,
                    'way' => '半场入球_1',
                    'odds' => $data['lst_total_balls']['1st_total_balls_1'],
                    'handicap' => "",
                    'sort' => 20,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场入球_2' =>[
                    'lottery_type' => 12,
                    'way' => '半场入球_2',
                    'odds' => $data['lst_total_balls']['1st_total_balls_2'],
                    'handicap' => "",
                    'sort' => 21,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场入球_3或以上' =>[
                    'lottery_type' => 12,
                    'way' => '半场入球_3或以上',
                    'odds' => $data['lst_total_balls']['1st_total_balls_3'],
                    'handicap' => "",
                    'sort' => 22,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场入球_0~1' =>[
                    'lottery_type' => 12,
                    'way' => '全场入球_0~1',
                    'odds' => $data['total_balls']['total_balls_0_1'],
                    'handicap' => "",
                    'sort' => 23,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场入球_2~3' =>[
                    'lottery_type' => 12,
                    'way' => '全场入球_2~3',
                    'odds' => $data['total_balls']['total_balls_2_3'],
                    'handicap' => "",
                    'sort' => 24,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场入球_4~6' =>[
                    'lottery_type' => 12,
                    'way' => '全场入球_4~6',
                    'odds' => $data['total_balls']['total_balls_4_6'],
                    'handicap' => "",
                    'sort' => 25,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场入球_7或以上' =>[
                    'lottery_type' => 12,
                    'way' => '全场入球_7或以上',
                    'odds' => $data['total_balls']['total_balls_7'],
                    'handicap' => "",
                    'sort' => 26,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半/全场_主主' =>[
                    'lottery_type' => 12,
                    'way' => '半/全场_主主',
                    'odds' => $data['bqc']['bqc_zz'],
                    'handicap' => "",
                    'sort' => 27,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半/全场_主和' =>[
                    'lottery_type' => 12,
                    'way' => '半/全场_主和',
                    'odds' => $data['bqc']['bqc_zh'],
                    'handicap' => "",
                    'sort' => 28,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半/全场_主客' =>[
                    'lottery_type' => 12,
                    'way' => '半/全场_主客',
                    'odds' => $data['bqc']['bqc_zk'],
                    'handicap' => "",
                    'sort' => 29,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半/全场_和主' =>[
                    'lottery_type' => 12,
                    'way' => '半/全场_和主',
                    'odds' => $data['bqc']['bqc_hz'],
                    'handicap' => "",
                    'sort' => 30,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半/全场_和和' =>[
                    'lottery_type' => 12,
                    'way' => '半/全场_和和',
                    'odds' => $data['bqc']['bqc_hh'],
                    'handicap' => "",
                    'sort' => 31,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半/全场_和客' =>[
                    'lottery_type' => 12,
                    'way' => '半/全场_和客',
                    'odds' => $data['bqc']['bqc_hk'],
                    'handicap' => "",
                    'sort' => 32,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半/全场_客主' =>[
                    'lottery_type' => 12,
                    'way' => '半/全场_客主',
                    'odds' => $data['bqc']['bqc_kz'],
                    'handicap' => "",
                    'sort' => 33,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半/全场_客和' =>[
                    'lottery_type' => 12,
                    'way' => '半/全场_客和',
                    'odds' => $data['bqc']['bqc_kh'],
                    'handicap' => "",
                    'sort' => 34,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半/全场_客客' =>[
                    'lottery_type' => 12,
                    'way' => '半/全场_客客',
                    'odds' => $data['bqc']['bqc_kk'],
                    'handicap' => "",
                    'sort' => 35,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_1-0' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_1-0',
                    'odds' => $data['bd']['bd_1_0'],
                    'handicap' => "",
                    'sort' => 36,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_0-1' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_0-1',
                    'odds' => $data['bd']['bd_0_1'],
                    'handicap' => "",
                    'sort' => 37,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_2-0' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_2-0',
                    'odds' => $data['bd']['bd_2_0'],
                    'handicap' => "",
                    'sort' => 37,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_0-2' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_0-2',
                    'odds' => $data['bd']['bd_0_2'],
                    'handicap' => "",
                    'sort' => 37,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_3-0' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_3-0',
                    'odds' => $data['bd']['bd_3_0'],
                    'handicap' => "",
                    'sort' => 38,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_0-3' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_0-3',
                    'odds' => $data['bd']['bd_0_3'],
                    'handicap' => "",
                    'sort' => 39,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_2-1' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_2-1',
                    'odds' => $data['bd']['bd_2_1'],
                    'handicap' => "",
                    'sort' => 40,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_1-2' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_1-2',
                    'odds' => $data['bd']['bd_1_2'],
                    'handicap' => "",
                    'sort' => 40,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_3-1' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_3-1',
                    'odds' => $data['bd']['bd_3_1'],
                    'handicap' => "",
                    'sort' => 41,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_1-3' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_1-3',
                    'odds' => $data['bd']['bd_1_3'],
                    'handicap' => "",
                    'sort' => 41,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_3-2' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_3-2',
                    'odds' => $data['bd']['bd_3_2'],
                    'handicap' => "",
                    'sort' => 42,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_2-3' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_2-3',
                    'odds' => $data['bd']['bd_2_3'],
                    'handicap' => "",
                    'sort' => 43,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_4-0' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_4-0',
                    'odds' => $data['bd']['bd_4_0'],
                    'handicap' => "",
                    'sort' => 45,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_0-4' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_0-4',
                    'odds' => $data['bd']['bd_0_4'],
                    'handicap' => "",
                    'sort' => 46,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_4-1' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_4-1',
                    'odds' => $data['bd']['bd_4_1'],
                    'handicap' => "",
                    'sort' => 47,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_1-4' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_1-4',
                    'odds' => $data['bd']['bd_1_4'],
                    'handicap' => "",
                    'sort' => 48,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_4-2' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_4-2',
                    'odds' => $data['bd']['bd_4_2'],
                    'handicap' => "",
                    'sort' => 49,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_2-4' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_2-4',
                    'odds' => $data['bd']['bd_2_4'],
                    'handicap' => "",
                    'sort' => 50,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_4-3' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_4-3',
                    'odds' => $data['bd']['bd_4_3'],
                    'handicap' => "",
                    'sort' => 51,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_3-4' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_3-4',
                    'odds' => $data['bd']['bd_3_4'],
                    'handicap' => "",
                    'sort' => 51,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_0-0' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_0-0',
                    'odds' => $data['bd']['bd_0_0'],
                    'handicap' => "",
                    'sort' => 51,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_1-1' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_1-1',
                    'odds' => $data['bd']['bd_1_1'],
                    'handicap' => "",
                    'sort' => 51,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_2-2' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_2-2',
                    'odds' => $data['bd']['bd_2_2'],
                    'handicap' => "",
                    'sort' => 51,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_3-3' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_3-3',
                    'odds' => $data['bd']['bd_3_3'],
                    'handicap' => "",
                    'sort' => 51,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_4-4' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_4-4',
                    'odds' => $data['bd']['bd_4_4'],
                    'handicap' => "",
                    'sort' => 51,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场比分_其他' =>[
                    'lottery_type' => 12,
                    'way' => '全场比分_其他',
                    'odds' => $data['bd']['bd_other'],
                    'handicap' => "",
                    'sort' => 51,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场_A胜' =>[
                    'lottery_type' => 12,
                    'way' => '半场_A胜',
                    'odds' => $data['lst_dy']['1'],
                    'handicap' => "",
                    'sort' => 52,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场_B胜' =>[
                    'lottery_type' => 12,
                    'way' => '半场_B胜',
                    'odds' => $data['lst_dy']['2'],
                    'handicap' => "",
                    'sort' => 53,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场_平局' =>[
                    'lottery_type' => 12,
                    'way' => '半场_平局',
                    'odds' => $data['lst_dy']['x'],
                    'handicap' => "",
                    'sort' => 54,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场_A胜' =>[
                    'lottery_type' => 12,
                    'way' => '全场_A胜',
                    'odds' => $data['dy']['1'],
                    'handicap' => "",
                    'sort' => 55,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场_B胜' =>[
                    'lottery_type' => 12,
                    'way' => '全场_B胜',
                    'odds' => $data['dy']['2'],
                    'handicap' => "",
                    'sort' => 56,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场_平局' =>[
                    'lottery_type' => 12,
                    'way' => '全场_平局',
                    'odds' => $data['dy']['x'],
                    'handicap' => "",
                    'sort' => 57,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场让球_A1' =>[
                    'lottery_type' => 12,
                    'way' => '半场让球_A1',
                    'odds' => $data['lst_home_team_rang_odds_1'],
                    'handicap' => $data['lst_home_team_rang_pk_1'],
                    'sort' => 58,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场让球_B1' =>[
                    'lottery_type' => 12,
                    'way' => '半场让球_B1',
                    'odds' => $data['lst_visiting_team_rang_odds_1'],
                    'handicap' => $data['lst_visiting_team_rang_pk_1'],
                    'sort' => 59,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场大小_大1' =>[
                    'lottery_type' => 12,
                    'way' => '半场大小_大1',
                    'odds' => $data['lst_big_odds_1'],
                    'handicap' => $data['lst_big_pk_1'],
                    'sort' => 60,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '半场大小_小1' =>[
                    'lottery_type' => 12,
                    'way' => '半场大小_小1',
                    'odds' => $data['lst_small_odds_1'],
                    'handicap' => $data['lst_small_pk_1'],
                    'sort' => 61,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场让球_A1' =>[
                    'lottery_type' => 12,
                    'way' => '全场让球_A1',
                    'odds' => $data['home_team_rang_odds_1'],
                    'handicap' => $data['home_team_rang_pk_1'],
                    'sort' => 62,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场让球_B1' =>[
                    'lottery_type' => 12,
                    'way' => '全场让球_B1',
                    'odds' => $data['visiting_team_rang_odds_1'],
                    'handicap' => $data['visiting_team_rang_pk_1'],
                    'sort' => 63,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场大小_大1' =>[
                    'lottery_type' => 12,
                    'way' => '全场大小_大1',
                    'odds' => $data['big_odds_1'],
                    'handicap' => $data['big_pk_1'],
                    'sort' => 64,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],
                '全场大小_小1' =>[
                    'lottery_type' => 12,
                    'way' => '全场大小_小1',
                    'odds' => $data['small_odds_1'],
                    'handicap' => $data['small_pk_1'],
                    'sort' => 65,
                    'type' => 2,
                    'match_id' => $data['match_id']
                ],

            ];
            foreach ($post_data_1 as $val) {
                $this->db->insert("#@_cup_odds",$val);
                $this->db->insert("#@_cup_odds_log",['way' => $val['way'], 'lottery_type' => 12, 'odds' => $val['odds'], 'handicap' => $val['handicap'], 'add_time' => time(), 'match_id' => $data['match_id']]);
            }

        } else {
            $check = false;
            foreach ($list as $val) {
                $uptime = time();
                if ($val['way'] == "半场让球_A" && ($val['odds'] != $data['lst_home_team_rang_odds'] || $val['handicap'] != $data['lst_home_team_rang_pk'] ) && $val['is_auto'] == 0) {
//                    lg("odds_debug","当前玩法/赔率/盘口/场次ID::【{$val['way']}/{$val['odds']}/{$val['handicap']}/{$val['match_id']}】->推过来信息::lst_home_team_rang_odds/lst_home_team_rang_pk【{$data['lst_home_team_rang_odds']}/{$data['lst_home_team_rang_pk']}】");
                    $res = $this->db->update("#@_cup_odds", ['odds' => $data['lst_home_team_rang_odds'],'handicap' => $data['lst_home_team_rang_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场让球_A', 'lottery_type' => 12, 'odds' => $data['lst_home_team_rang_odds'], 'handicap' => $data['lst_home_team_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场让球_B" && ($val['odds'] != $data['lst_visiting_team_rang_odds'] || $val['handicap'] != $data['lst_visiting_team_rang_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_visiting_team_rang_odds'],'handicap' => $data['lst_visiting_team_rang_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场让球_B', 'lottery_type' => 12, 'odds' => $data['lst_visiting_team_rang_odds'], 'handicap' => $data['lst_visiting_team_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场大小_大" && ($val['odds'] != $data['lst_big_odds'] || $val['handicap'] != $data['lst_big_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_big_odds'],'handicap' => $data['lst_big_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场大小_大', 'lottery_type' => 12, 'odds' => $data['lst_big_odds'], 'handicap' => $data['lst_big_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场大小_小" && ($val['odds'] != $data['lst_small_odds'] || $val['handicap'] != $data['lst_small_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_small_odds'],'handicap' => $data['lst_small_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场大小_小', 'lottery_type' => 12, 'odds' => $data['lst_small_odds'], 'handicap' => $data['lst_small_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场让球_A" && ($val['odds'] != $data['home_team_rang_odds'] || $val['handicap'] != $data['home_team_rang_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['home_team_rang_odds'],'handicap' => $data['home_team_rang_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场让球_A', 'lottery_type' => 12, 'odds' => $data['home_team_rang_odds'], 'handicap' => $data['home_team_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场让球_B" && ($val['odds'] != $data['visiting_team_rang_odds'] || $val['handicap'] != $data['visiting_team_rang_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['visiting_team_rang_odds'],'handicap' => $data['visiting_team_rang_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场让球_B', 'lottery_type' => 12, 'odds' => $data['visiting_team_rang_odds'], 'handicap' => $data['visiting_team_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场大小_大" && ($val['odds'] != $data['big_odds'] || $val['handicap'] != $data['big_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['big_odds'],'handicap' => $data['big_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场大小_大', 'lottery_type' => 12, 'odds' => $data['big_odds'], 'handicap' => $data['big_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场大小_小" && ($val['odds'] != $data['small_odds'] || $val['handicap'] != $data['small_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['small_odds'],'handicap' => $data['small_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场大小_小', 'lottery_type' => 12, 'odds' => $data['small_odds'], 'handicap' => $data['small_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场单双_单" && $val['odds'] != $data['single_odds'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['single_odds'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场单双_单', 'lottery_type' => 12, 'odds' => $data['single_odds'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场单双_双" && $val['odds'] != $data['double_odds'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['double_odds'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场单双_双', 'lottery_type' => 12, 'odds' => $data['double_odds'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "加时让球_A" && ($val['odds'] != $data['home_team_overtime_rang_odds'] || $val['handicap'] != $data['home_team_overtime_rang_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['home_team_overtime_rang_odds'],'handicap' => $data['home_team_overtime_rang_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '加时让球_A', 'lottery_type' => 12, 'odds' => $data['home_team_overtime_rang_odds'], 'handicap' => $data['home_team_overtime_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "加时让球_B" && ($val['odds'] != $data['visiting_team_overtime_rang_odds'] || $val['handicap'] != $data['visiting_team_overtime_rang_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['visiting_team_overtime_rang_odds'],'handicap' => $data['visiting_team_overtime_rang_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '加时让球_B', 'lottery_type' => 12, 'odds' => $data['visiting_team_overtime_rang_odds'], 'handicap' => $data['visiting_team_overtime_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "加时大小_大" && ($val['odds'] != $data['overtime_big_odds'] || $val['handicap'] != $data['overtime_big_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['overtime_big_odds'],'handicap' => $data['overtime_big_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '加时大小_大', 'lottery_type' => 12, 'odds' => $data['overtime_big_odds'], 'handicap' => $data['overtime_big_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "加时大小_小" && ($val['odds'] != $data['overtime_small_odds'] || $val['handicap'] != $data['overtime_small_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['overtime_small_odds'],'handicap' => $data['overtime_small_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '加时大小_小', 'lottery_type' => 12, 'odds' => $data['overtime_small_odds'], 'handicap' => $data['overtime_small_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "点球让球_A" && ($val['odds'] != $data['home_team_penalty_rang_odds'] || $val['handicap'] != $data['home_team_penalty_rang_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['home_team_penalty_rang_odds'],'handicap' => $data['home_team_penalty_rang_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '点球让球_A', 'lottery_type' => 12, 'odds' => $data['home_team_penalty_rang_odds'], 'handicap' => $data['home_team_penalty_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "点球让球_B" && ($val['odds'] != $data['visiting_team_penalty_rang_odds'] || $val['handicap'] != $data['visiting_team_penalty_rang_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['visiting_team_penalty_rang_odds'],'handicap' => $data['visiting_team_penalty_rang_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '点球让球_B', 'lottery_type' => 12, 'odds' => $data['visiting_team_penalty_rang_odds'], 'handicap' => $data['visiting_team_penalty_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "点球大小_大" && ($val['odds'] != $data['penalty_big_odds'] || $val['handicap'] != $data['penalty_big_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['penalty_big_odds'],'handicap' => $data['penalty_big_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '点球大小_大', 'lottery_type' => 12, 'odds' => $data['penalty_big_odds'], 'handicap' => $data['penalty_big_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "点球大小_小" && ($val['odds'] != $data['penalty_small_odds'] || $val['handicap'] != $data['penalty_small_pk']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['penalty_small_odds'],'handicap' => $data['penalty_small_pk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '点球大小_小', 'lottery_type' => 12, 'odds' => $data['penalty_small_odds'], 'handicap' => $data['penalty_small_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场入球_0" && $val['odds'] != $data['lst_total_balls']['1st_total_balls_0'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_total_balls']['1st_total_balls_0'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场入球_0', 'lottery_type' => 12, 'odds' => $data['lst_total_balls']['1st_total_balls_0'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场入球_1" && $val['odds'] != $data['lst_total_balls']['1st_total_balls_1'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_total_balls']['1st_total_balls_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场入球_1', 'lottery_type' => 12, 'odds' => $data['lst_total_balls']['1st_total_balls_1'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场入球_2" && $val['odds'] != $data['lst_total_balls']['1st_total_balls_2'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_total_balls']['1st_total_balls_2'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场入球_2', 'lottery_type' => 12, 'odds' => $data['lst_total_balls']['1st_total_balls_2'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场入球_3或以上" && $val['odds'] != $data['lst_total_balls']['1st_total_balls_3'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_total_balls']['1st_total_balls_3'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场入球_3或以上', 'lottery_type' => 12, 'odds' => $data['lst_total_balls']['1st_total_balls_3'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场入球_0~1" && $val['odds'] != $data['total_balls']['total_balls_0_1'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['total_balls']['total_balls_0_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场入球_0~1', 'lottery_type' => 12, 'odds' => $data['total_balls']['total_balls_0_1'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场入球_2~3" && $val['odds'] != $data['total_balls']['total_balls_2_3'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['total_balls']['total_balls_2_3'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场入球_2~3', 'lottery_type' => 12, 'odds' => $data['total_balls']['total_balls_2_3'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场入球_4~6" && $val['odds'] != $data['total_balls']['total_balls_4_6'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['total_balls']['total_balls_4_6'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场入球_4~6', 'lottery_type' => 12, 'odds' => $data['total_balls']['total_balls_4_6'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场入球_7或以上" && $val['odds'] != $data['total_balls']['total_balls_7'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['total_balls']['total_balls_7'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场入球_7或以上', 'lottery_type' => 12, 'odds' => $data['total_balls']['total_balls_7'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半/全场_主主" && $val['odds'] != $data['bqc']['bqc_zz'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bqc']['bqc_zz'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半/全场_主主', 'lottery_type' => 12, 'odds' => $data['bqc']['bqc_zz'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半/全场_主和" && $val['odds'] != $data['bqc']['bqc_zh'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bqc']['bqc_zh'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半/全场_主和', 'lottery_type' => 12, 'odds' => $data['bqc']['bqc_zh'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半/全场_主客" && $val['odds'] != $data['bqc']['bqc_zk'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bqc']['bqc_zk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半/全场_主客', 'lottery_type' => 12, 'odds' => $data['bqc']['bqc_zk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半/全场_和主" && $val['odds'] != $data['bqc']['bqc_hz'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bqc']['bqc_hz'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半/全场_和主', 'lottery_type' => 12, 'odds' => $data['bqc']['bqc_hz'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半/全场_和和" && $val['odds'] != $data['bqc']['bqc_hh'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bqc']['bqc_hh'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半/全场_和和', 'lottery_type' => 12, 'odds' => $data['bqc']['bqc_hh'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半/全场_和客" && $val['odds'] != $data['bqc']['bqc_hk'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bqc']['bqc_hk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半/全场_和客', 'lottery_type' => 12, 'odds' => $data['bqc']['bqc_hk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半/全场_客主" && $val['odds'] != $data['bqc']['bqc_kz'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bqc']['bqc_kz'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半/全场_客主', 'lottery_type' => 12, 'odds' => $data['bqc']['bqc_kz'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半/全场_客和" && $val['odds'] != $data['bqc']['bqc_kh'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bqc']['bqc_kh'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半/全场_客和', 'lottery_type' => 12, 'odds' => $data['bqc']['bqc_kh'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半/全场_客客" && $val['odds'] != $data['bqc']['bqc_kk'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bqc']['bqc_kk'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半/全场_客客', 'lottery_type' => 12, 'odds' => $data['bqc']['bqc_kk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_1-0" && $val['odds'] != $data['bd']['bd_1_0'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_1_0'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_1-0', 'lottery_type' => 12, 'odds' => $data['bd']['bd_1_0'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_0-1" && $val['odds'] != $data['bd']['bd_0_1'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_0_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_0-1', 'lottery_type' => 12, 'odds' => $data['bd']['bd_0_1'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_2-0" && $val['odds'] != $data['bd']['bd_2_0'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_2_0'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_2-0', 'lottery_type' => 12, 'odds' => $data['bd']['bd_2_0'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_0-2" && $val['odds'] != $data['bd']['bd_0_2'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_0_2'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_0-2', 'lottery_type' => 12, 'odds' => $data['bd']['bd_0_2'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_3-0" && $val['odds'] != $data['bd']['bd_3_0'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_3_0'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_3-0', 'lottery_type' => 12, 'odds' => $data['bd']['bd_3_0'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_0-3" && $val['odds'] != $data['bd']['bd_0_3'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_0_3'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_0-3', 'lottery_type' => 12, 'odds' => $data['bd']['bd_0_3'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_2-1" && $val['odds'] != $data['bd']['bd_2_1'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_2_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_2-1', 'lottery_type' => 12, 'odds' => $data['bd']['bd_2_1'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_1-2" && $val['odds'] != $data['bd']['bd_1_2'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_1_2'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_1-2', 'lottery_type' => 12, 'odds' => $data['bd']['bd_1_2'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_3-1" && $val['odds'] != $data['bd']['bd_3_1'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_3_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_3-1', 'lottery_type' => 12, 'odds' => $data['bd']['bd_3_1'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_1-3" && $val['odds'] != $data['bd']['bd_1_3'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_1_3'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_1-3', 'lottery_type' => 12, 'odds' => $data['bd']['bd_1_3'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_3-2" && $val['odds'] != $data['bd']['bd_3_2'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_3_2'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_3-2', 'lottery_type' => 12, 'odds' => $data['bd']['bd_3_2'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_2-3" && $val['odds'] != $data['bd']['bd_2_3'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_2_3'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_2-3', 'lottery_type' => 12, 'odds' => $data['bd']['bd_2_3'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_4-0" && $val['odds'] != $data['bd']['bd_4_0'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_4_0'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_4-0', 'lottery_type' => 12, 'odds' => $data['bd']['bd_4_0'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_0-4" && $val['odds'] != $data['bd']['bd_0_4'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_0_4'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_0-4', 'lottery_type' => 12, 'odds' => $data['bd']['bd_0_4'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_4-1" && $val['odds'] != $data['bd']['bd_4_1'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_4_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_4-1', 'lottery_type' => 12, 'odds' => $data['bd']['bd_4_1'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_1-4" && $val['odds'] != $data['bd']['bd_1_4'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_1_4'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_1-4', 'lottery_type' => 12, 'odds' => $data['bd']['bd_1_4'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_4-2" && $val['odds'] != $data['bd']['bd_4_2'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_4_2'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_4-2', 'lottery_type' => 12, 'odds' => $data['bd']['bd_4_2'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_2-4" && $val['odds'] != $data['bd']['bd_4_2'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_2_4'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_2-4', 'lottery_type' => 12, 'odds' => $data['bd']['bd_2_4'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_4-3" && $val['odds'] != $data['bd']['bd_4_3'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_4_3'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_4-3', 'lottery_type' => 12, 'odds' => $data['bd']['bd_4_3'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_3-4" && $val['odds'] != $data['bd']['bd_3_4'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_3_4'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_3-4', 'lottery_type' => 12, 'odds' => $data['bd']['bd_3_4'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_0-0" && $val['odds'] != $data['bd']['bd_0_0'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_0_0'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_0-0', 'lottery_type' => 12, 'odds' => $data['bd']['bd_0_0'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_1-1" && $val['odds'] != $data['bd']['bd_1_1'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_1_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_1-1', 'lottery_type' => 12, 'odds' => $data['bd']['bd_1_1'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_2-2" && $val['odds'] != $data['bd']['bd_2_2'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_2_2'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_2-2', 'lottery_type' => 12, 'odds' => $data['bd']['bd_2_2'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_3-3" && $val['odds'] != $data['bd']['bd_3_3'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_3_3'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_3-3', 'lottery_type' => 12, 'odds' => $data['bd']['bd_3_3'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_4-4" && $val['odds'] != $data['bd']['bd_4_4'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_4_4'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_4-4', 'lottery_type' => 12, 'odds' => $data['bd']['bd_4_4'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场比分_其他" && $val['odds'] != $data['bd']['bd_other'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['bd']['bd_other'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场比分_其他', 'lottery_type' => 12, 'odds' => $data['bd']['bd_other'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场_A胜" && $val['odds'] != $data['lst_dy']['1'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_dy']['1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场_A胜', 'lottery_type' => 12, 'odds' => $data['lst_dy']['1'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场_B胜" && $val['odds'] != $data['lst_dy']['2'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_dy']['2'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场_B胜', 'lottery_type' => 12, 'odds' => $data['lst_dy']['2'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场_平局" && $val['odds'] != $data['lst_dy']['x'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_dy']['x'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场_平局', 'lottery_type' => 12, 'odds' => $data['lst_dy']['x'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场_A胜" && $val['odds'] != $data['dy']['1'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['dy']['1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场_A胜', 'lottery_type' => 12, 'odds' => $data['dy']['1'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场_B胜" && $val['odds'] != $data['dy']['2'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['dy']['2'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场_B胜', 'lottery_type' => 12, 'odds' => $data['dy']['2'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场_平局" && $val['odds'] != $data['dy']['x'] && $val['is_auto'] == 0 ) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['dy']['x'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场_平局', 'lottery_type' => 12, 'odds' => $data['dy']['x'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场让球_A1" && ($val['odds'] != $data['lst_home_team_rang_odds_1'] || $val['handicap'] != $data['lst_home_team_rang_pk_1'] ) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_home_team_rang_odds_1'],'handicap' => $data['lst_home_team_rang_pk_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场让球_A', 'lottery_type' => 12, 'odds' => $data['lst_home_team_rang_odds'], 'handicap' => $data['lst_home_team_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场让球_B1" && ($val['odds'] != $data['lst_visiting_team_rang_odds_1'] || $val['handicap'] != $data['lst_visiting_team_rang_pk_1']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_visiting_team_rang_odds_1'],'handicap' => $data['lst_visiting_team_rang_pk_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场让球_B', 'lottery_type' => 12, 'odds' => $data['lst_visiting_team_rang_odds'], 'handicap' => $data['lst_visiting_team_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场大小_大1" && ($val['odds'] != $data['lst_big_odds_1'] || $val['handicap'] != $data['lst_big_pk_1']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_big_odds_1'],'handicap' => $data['lst_big_pk_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场大小_大', 'lottery_type' => 12, 'odds' => $data['lst_big_odds'], 'handicap' => $data['lst_big_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "半场大小_小1" && ($val['odds'] != $data['lst_small_odds_1'] || $val['handicap'] != $data['lst_small_pk_1']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['lst_small_odds_1'],'handicap' => $data['lst_small_pk_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '半场大小_小', 'lottery_type' => 12, 'odds' => $data['lst_small_odds'], 'handicap' => $data['lst_small_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场让球_A1" && ($val['odds'] != $data['home_team_rang_odds_1'] || $val['handicap'] != $data['home_team_rang_pk_1']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['home_team_rang_odds_1'],'handicap' => $data['home_team_rang_pk_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场让球_A', 'lottery_type' => 12, 'odds' => $data['home_team_rang_odds'], 'handicap' => $data['home_team_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场让球_B1" && ($val['odds'] != $data['visiting_team_rang_odds_1'] || $val['handicap'] != $data['visiting_team_rang_pk_1']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['visiting_team_rang_odds_1'],'handicap' => $data['visiting_team_rang_pk_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场让球_B', 'lottery_type' => 12, 'odds' => $data['visiting_team_rang_odds'], 'handicap' => $data['visiting_team_rang_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场大小_大1" && ($val['odds'] != $data['big_odds_1'] || $val['handicap'] != $data['big_pk_1']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['big_odds_1'],'handicap' => $data['big_pk_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场大小_大', 'lottery_type' => 12, 'odds' => $data['big_odds'], 'handicap' => $data['big_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }

                if ($val['way'] == "全场大小_小1" && ($val['odds'] != $data['small_odds_1'] || $val['handicap'] != $data['small_pk_1']) && $val['is_auto'] == 0) {
                    $this->db->update("#@_cup_odds", ['odds' => $data['small_odds_1'],'handicap' => $data['small_pk_1'],'update_time'=>$uptime], ['id'=> $val['id']]);
//                    $this->db->insert("#@_cup_odds_log",['way' => '全场大小_小', 'lottery_type' => 12, 'odds' => $data['small_odds'], 'handicap' => $data['small_pk'], 'add_time' => time(), 'match_id' => $data['match_id']]);
                    $check = true;
                }



            }
            if ($check) {
                $this->refreshRedis("fb_odds", "all");
                $room_id = $this->db->result("select r.id from un_cup_odds o join un_room r on o.match_id = r.match_id where o.match_id={$data['match_id']}");
                if (!empty($room_id)) {
                    $redis = initCacheRedis();
                    $against = $redis->hGet("fb_against", $data['match_id']);
                    $against = decode($against)[0];
                    deinitCacheRedis($redis);

                    $data_1=array( //调用双活接口
                        'type'=>'update_odds',
                        'id'=> $room_id,
                        'json'=>encode(array('commandid' => 3024,'room_id'=>$room_id,'against' => $against)),
                    );
                    send_home_data($data_1);
                }
            }
        }
    }

    /**
     * 获取世界杯赔率
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-05-07 16:10:48
     */
    public function getCupData(){ //只是简单的接收数据
        $get_info = file_get_contents('php://input','r');
        lg("word_cup","赔率开始接受数据::{$get_info}");
        $data = decode($get_info);
        lg("word_cup",var_export(array('$data'=>$data),1));
        //监测是否有数据进来
        $redis = initCacheRedis();
        $redis->hsetnx('fb_uptime_all',$data['match_id'],1);  //存开奖数据
        $str = 'fb_uptime:'.$data['match_id'];
        if($redis->set($str,1)){
            $redis->expire($str,60);
        }
        deinitCacheRedis($redis);
        if($data['code']==1){
            $room_id = $this->db->result("select r.id from un_cup_odds o join un_room r on o.match_id = r.match_id where o.match_id={$data['match_id']}");

            if (!empty($room_id)) {

                $kstr = 'org_cup_input_data:'.$data['match_id'];
                $last_data = $redis->get($kstr); //上一次的数据

                $data_1=array( //调用双活接口
                    'type'=>'update_against',
                    'id'=> $room_id,
                    'json'=>encode(array('commandid' => 3031,'room_id' => $room_id,'data' => decode($last_data))),
                );
                lg('word_cup','--code==1-更新数据给前端--'.var_export(array('$data_1'=>$data_1),1));
//                send_home_data($data_1);

                $redis = initCacheRedis();
                $against = $redis->hGet("fb_against", $data['match_id']);
                $against = decode($against)[0];
                deinitCacheRedis($redis);

                $data_2=array( //调用双活接口
                    'type'=>'update_odds',
                    'id'=> $room_id,
                    'json'=>encode(array('commandid' => 3024,'room_id'=>$room_id,'against' => $against)),
                );
                lg('word_cup','--code==1-更新赛事状态给前端---'.var_export(array('$room_id'=>$room_id,'$data_2'=>$data_2),1));
//                send_home_data($data_2);
            }

            $data['isSStop'] = 1;
            lg("word_cup_hear",var_export(array(
                '心跳数据',
                '$get_info'=>$get_info,
                '$data'=>$data,
            ),1));
            return $data;
        }
        ksort($data);

        //防止并发
        $redis = initCacheRedis();
        //改成字str存储
        $kstr = 'org_cup_input_data:'.$data['match_id'];
        $last_data = $redis->get($kstr); //上一次的数据
        $foot_ball_match_ids = $redis->lRange('foot_ball_match_ids',0,-1);
        lg('word_cup_log',var_export(array(
            '$last_data'=>$last_data,
            'encode($data)'=>encode($data),
        ),1));
        if($last_data == encode($data)){
            deinitCacheRedis($redis);
            $data['isSStop'] = 1;
            return $data;
        }else{
            $redis->set($kstr,encode($data));
//            $redis->expire($kstr,1200); //设置30秒的有效期
            deinitCacheRedis($redis);
        }

        if(in_array($data['match_id'],$foot_ball_match_ids)){ //当前赛事ID是否开启房间
            lg('foot_ball_match_ids','有用的数据'.var_export(array('$data[\'match_id\']'=>$data['match_id']),1));
            lg('word_cup_d_test','待开时更新比分'.var_export(array('$data[\'match_id\']'=>$data['match_id'],'$data[\'match_score\']'=>$data['match_score'],'$data[\'match_end_state\']'=>$data['match_end_state']),1));
        }else{
            lg('foot_ball_match_ids','没用的数据'.var_export(array('$data[\'match_id\']'=>$data['match_id']),1));
            $data['isSStop'] = 1;
            return false;
        }

        lg('word_cup_d','待开时更新比分'.var_export(array('($data[\'match_end_state\'] == 0)'=>($data['match_end_state'] == 0),'$data'=>$data),1));
        if ($data['match_end_state'] == 0) { //测试代码
            $sql = "UPDATE `un_cup_against` SET match_score='0:0',match_state=0 WHERE match_id={$data['match_id']} AND (match_score!='0:0' OR match_state<>0)";
            $re = $this->db->query($sql);
            lg('word_cup_d','待开时更新SQL'.var_export(array('$re'=>$re,'$sql'=>$sql),1));
            if($re){
                $this->refreshRedis("fb_against", "all");

                $room_id = $this->db->result("select r.id from un_cup_odds o join un_room r on o.match_id = r.match_id where o.match_id={$data['match_id']}");

                if (!empty($room_id)) {
                    $data_1=array( //调用双活接口
                        'type'=>'update_against',
                        'id'=> $room_id,
                        'json'=>encode(array('commandid' => 3031,'room_id' => $room_id,'data' => $data)),
                    );
                    send_home_data($data_1);

                    $redis = initCacheRedis();
                    $against = $redis->hGet("fb_against", $data['match_id']);
                    $against = decode($against)[0];
                    deinitCacheRedis($redis);

                    $data_2=array( //调用双活接口
                        'type'=>'update_odds',
                        'id'=> $room_id,
                        'json'=>encode(array('commandid' => 3024,'room_id'=>$room_id,'against' => $against)),
                    );
                    send_home_data($data_2);
                }
            }
        } //测试代码


        //监测是否重新的数据进来
        $nowTime = time();
        //查上一次接收数据的时间
        $redis = initCacheRedis();
        $lastTime = $redis->hget('fb_input_time',$data['match_id'])?:time();
//        lg('fb_input_time',var_export(array('$lastTime'=>$lastTime),1));
        if($nowTime - $lastTime > 20){ //超过20秒后有新数据进来
            $redis->hset('fb_input_recont',$data['match_id'],1); //重新接收成功
        }else{
            $redis->hset('fb_input_time',$data['match_id'],$nowTime);  //存开奖数据
        }

        //查出当前比赛状态
        $against = decode($redis->hGet("fb_against",$data['match_id']));
        $current_state = $against[0]['match_state'];

        deinitCacheRedis($redis);

        $room_id = $this->db->result("select r.id from un_cup_odds o join un_room r on o.match_id = r.match_id where o.match_id={$data['match_id']}");

        if (!empty($room_id)) {
            //防止过来的数据状态为空或者为0
            if(($data['match_state']==2 || $data['match_field']=='全场结束') && in_array($data['match_end_state'],array(3,5,7))){ //防止全场不派奖
                $data['match_end_state'] = $data['match_end_state']+1;
            }

            if(($data['match_state']==2 || $data['match_field']=='全场结束') && in_array($current_state,array(3,5,7))){ //防止全场不派奖
                $data['match_end_state'] = $current_state+1;
            }

            //状态大于等于库时才推
            if($data['match_end_state'] >= $current_state){
                $data_1=array( //调用双活接口
                    'type'=>'update_against',
                    'id'=> $room_id,
                    'json'=>encode(array('commandid' => 3031,'room_id' => $room_id,'data' => $data)),
                );
                lg('word_cup_d','---更新数据给前端--'.var_export(array('$data_1'=>$data_1),1));
                send_home_data($data_1);
            }
        }
        return $data;
    }

    /**
     * 获取世界杯滚球赔率接口
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-05-07 16:10:48
     */
    public function getCupDataRoll(){
        $data = $this->getCupData();
        lg('word_cup','--数据入库后得到的数据--'.var_export(array('$data'=>encode($data)),1));
        if($data==false){
            return false;
        }
        if($data['isSStop']==1){
            return false;
        }
        $this->saveCupOdds($data);
        $check = false;
        $sql = "select * from #@_cup_against where match_id = {$data['match_id']}";
        $list = $this->db->getone($sql);
        $rows =0;

        //赛事状态，这里要改成大于库里的值
        if ($list['match_state'] < $data['match_end_state']) {
            lg("word_cup","赛事状态变更({$data['match_id']})->数据库赛程变更之前详细信息::".encode($list));
            $rows = $this->db->update('#@_cup_against',['match_state' => $data['match_end_state']],['match_id'=>$data['match_id']]);
            lg("word_cup","更新赛事状态({$data['match_id']})->更新结果::".var_export($rows,true)."->执行sql语句::".$this->db->_sql());
            $check = true;
        }

        //实时比分
        if ($list['match_score'] != $data['match_score']) {
            //这里的比分要做一下判断
//            $lArr = explode(':',$list['match_score']);
//            $dArr = explode(':',$data['match_score']);
//            lg("word_cup",var_export(array('比分变动判断','$lArr'=>$lArr,'$dArr'=>$dArr),1)); //比分变动记录时间
//            if($dArr[0]>=$lArr[0] && $dArr[1]>=$lArr[1]){
                $uptime = time();
                lg("word_cup","赛事实时比分变化[时间:]({$data['match_id']})->数据库赛程变更之前详细信息::".encode($list).var_export(array('比分变动时间$uptime'=>$uptime),1)); //比分变动记录时间
                $rs = $this->db->update('#@_cup_against',['match_score' => $data['match_score'],'update_time'=>$uptime],['match_id'=>$data['match_id']]);
                lg("word_cup","更新赛程信息({$data['match_id']})->更新结果::".var_export($rs,true)."->执行sql语句::".$this->db->_sql());
                $check = true;
//            }
        }

        //比赛各阶段结束比分
        if ($data['match_end_state'] == 2) {

            lg("word_cup","上半场结束({$data['match_id']})->数据库赛程变更之前详细信息::".encode($list));
            if ($list['first_result'] != $data['match_score']) {
                $rows = $this->db->update('#@_cup_against',['first_result' => $data['match_score']],['match_id'=>$data['match_id']]);
                lg("word_cup","上半场结束更新赛程信息({$data['match_id']})->更新结果::".var_export($rows,true)."->执行sql语句::".$this->db->_sql());

                $check = true;
            }
//            $type=$data['match_end_state'];

        } elseif ($data['match_end_state'] == 4)  {

            lg("word_cup","下半场结束({$data['match_id']})->数据库赛程变更之前详细信息::".encode($list));
            if ($list['second_result'] != $data['match_score']) {
                $rows = $this->db->update('#@_cup_against',['second_result' => $data['match_score'],'all_match_score'=>$data['match_score']],['match_id'=>$data['match_id']]); //记录全场比分，给加时用
                lg("word_cup","下半场结束更新赛程信息({$data['match_id']})->更新结果::".var_export($rows,true)."->执行sql语句::".$this->db->_sql());
                $check = true;
            }
//            $type=2;

        } elseif ($data['match_end_state'] == 6) {

            lg("word_cup","加时结束({$data['match_id']})->数据库赛程变更之前详细信息::".encode($list));
            if ($list['overtime_result'] != $data['match_score']) {
                $rows = $this->db->update('#@_cup_against',['overtime_result' => $data['match_score'],'overtime_match_score'=>$data['match_score']],['match_id'=>$data['match_id']]);
                lg("word_cup","加时赛结束更新赛程信息({$data['match_id']})->更新结果::".var_export($rows,true)."->执行sql语句::".$this->db->_sql());
                $check = true;
            }
//            $type=3;

        } elseif ($data['match_end_state'] == 8) {

            lg("word_cup","点球结束({$data['match_id']})->数据库赛程变更之前详细信息::".encode($list));
            if ($list['penalty_result'] != $data['match_score']) {
                $rows = $this->db->update('#@_cup_against',['penalty_result' => $data['match_score']],['match_id'=>$data['match_id']]);
                lg("word_cup","点球结束更新赛程信息({$data['match_id']})->更新结果::".var_export($rows,true)."->执行sql语句::".$this->db->_sql());
                $check = true;
            }
        }
        $type=$data['match_end_state'];

        if ($check) {
            $this->refreshRedis("fb_against", "all");
            $sql = "select r.id from un_cup_against a join un_room r on a.match_id = r.match_id where a.match_id={$data['match_id']}";
            $room_id = $this->db->result($sql);
            lg('word_cup','---调用双活接口---'.var_export(array('$room_id'=>$room_id,'$sql'=>$sql),1));
            if (!empty($room_id)) {

                $redis = initCacheRedis();
                $against = $redis->hGet("fb_against", $data['match_id']);
                $against = decode($against)[0];
                deinitCacheRedis($redis);

                $data_2=array( //调用双活接口
                    'type'=>'update_odds',
                    'id'=> $room_id,
                    'json'=>encode(array('commandid' => 3024,'room_id'=>$room_id,'against' => $against)),
                );
                lg('word_cup','---更新赛事状态给前端---'.var_export(array('$room_id'=>$room_id,'$sql'=>$sql),1));
                send_home_data($data_2);
            }
        }

        $redis = initCacheRedis();
        $val = $redis->hget("fb_against", $data['match_id']);
        $roomInfo = decode($val)[0];
        deinitCacheRedis($redis);

        lg('word_cup','---派彩逻辑前-异常判断后--'.var_export(array('$data[\'match_state\']'=>$data['match_state'],'$type'=>$type,'$data[\'match_end_state\']'=>$data['match_end_state'],'$data[\'match_field\']'=>$data['match_field'],'$data[\'match_id\']'=>$data['match_id'],'$list[\'match_state\']'=>$list['match_state']),1));

//        $type = $data['match_end_state'];
        //这里触发派彩逻辑
        //ToDo 改这里，如果4没推过来，就给个4,如推过来的还是不行，就用这里的代码
        if(($data['match_state']==2 || $data['match_field']=='全场结束') && in_array($data['match_end_state'],array(3,5,7))){ //防止全场不派奖
            $type = $data['match_end_state']+1;
        }

        if(($data['match_state']==2 || $data['match_field']=='全场结束') && in_array($list['match_state'],array(3,5,7))){ //防止全场不派奖
            $type = $list['match_state']+1;
        }

        lg('word_cup','---派彩逻辑前-异常判断后--'.var_export(array('$data[\'match_state\']'=>$data['match_state'],'$type'=>$type,'$data[\'match_end_state\']'=>$data['match_end_state'],'$data[\'match_field\']'=>$data['match_field'],'$data[\'match_id\']'=>$data['match_id'],'$list[\'match_state\']'=>$list['match_state']),1));

        //只要主状态结束就派所有订单
        if($data['match_state']==2){

            foreach (array(4,6,8) as $type){

                if (in_array($type ,array(2))) {

                    //防止刷单
                    lg('word_cup_d','接收到的所有参数::'.encode($_REQUEST).',implode::'.implode(':',$data));
                    $redis = initCacheRedis();
                    $co_str = 'lottery_football:'.$data['match_id'].':'.$type;
                    lg('word_cup_d','组装key,$co_str---->::'.$co_str.',查看是否生效::'.$redis->get($co_str));
                    if($redis->setnx($co_str,1)){ //如果存在就组装key写不进去
                        lg('word_cup_d','进行设置超时时间');
                        $redis->expire($co_str,150); //设置它的超时
                        lg('word_cup_d','超时时间::'.$redis->ttl($co_str));
                        deinitCacheRedis($redis);
                    }else{
                        lg('word_cup_d','并发操作::,类型::'.$type);
                        deinitCacheRedis($redis);
                        return false;
                    }

                    lg('word_cup','进入派彩逻辑');
                    $sql = "SELECT r.id,r.lottery_type,a.second_result,a.first_result,a.overtime_result,a.penalty_result FROM un_cup_against a JOIN un_room r ON a.match_id = r.match_id WHERE a.match_id={$data['match_id']}";
                    $res = $this->db->getone($sql);
                    lg('word_cup','派彩逻辑'.var_export(array('$res'=>$res,'$sql'=>$sql,'$data[\'match_id\']'=>$data['match_id']),1));
                    if (!empty($res)) {
                        $lottery_type=$res['lottery_type'];
                        $room_id = $res['id'];

                        lg('word_cup','派彩逻辑'.var_export(array('$type'=>$type),1));

                        //ToDo 结束时间 要取变量
                        $time = time();
                        //shell派奖 ===============
                        $tdata = array(
                            'status'=>1,
                            'uid'=>0,
                            'bi_feng'=>$data['match_score'],
                            'room_id'=>$room_id,
                            'type'=>$type, //场子类型
                            'time'=>$time, //结束时间
                        );
                        lg('word_cup','派彩逻辑'.var_export(array('$tdata'=>$tdata),1));
                        $redis = initCacheRedis();
                        $redis->hsetnx('pc_lottery_type:'.$lottery_type,$data['match_id'],encode($tdata));  //存开奖数据
                        lg('word_cup','派彩逻辑'.var_export(array('从Redis取出来的数据'=>$redis->hgetall('pc_lottery_type:'.$lottery_type)),1));
                        deinitCacheRedis($redis);
                    }
                }

                sleep(5);
            }
        }
    }
}