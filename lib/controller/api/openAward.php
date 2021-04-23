<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/17
 * Time: 13:34
 * desc: 开奖结果 开奖走势
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class OpenAwardAction extends Action
{
    /**
     * 数据表
     */
    private $model;
    private $model2;

    public function __construct()
    {
        parent::__construct();
        $this->model = D('openAward');
        $this->model2 = D('openAwardTrend');
    }

    /**
     * 开奖结果
     * @method get /index.php?m = api&c = openAward&a = dataList&token = b5062b58d2433d1983a5cea888597eb6&date = 1&lottery_type = 2&page = 0
     * @param date int 时间戳
     * @param lottery_type int 彩票类型
     * @param page int 分页
     * @return
     */
    public function dataList()
    {
        //验证参数
        //$this->checkInput($_REQUEST, array('token', 'date', 'lottery_type', 'page'), all);
        $this->checkInput($_REQUEST, array('date', 'lottery_type', 'page'), all);
        $date = trim($_REQUEST['date']);
        $lottery_type = trim($_REQUEST['lottery_type']);
        $page = trim($_REQUEST['page']);
        $page = empty($page) ? 1 : $page;

        //验证token
        //$this->checkAuth();

        //初始化redis
        $redis = initCacheRedis();
        //验证游戏类型
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        if ($lottery_type == 0) {
            $lottery_type = $LotteryTypeIds['0'];
        }

        //获取游戏信息
        $gameInfo = array();
        foreach ($LotteryTypeIds as $v) {
            if($v!=12){
                $gameInfo[] = $redis->hGetAll("LotteryType:" . $v);
            }
        }

        //获取查询条数
        $GameConfig = $redis->hGetAll("Config:100007");
        $num = $GameConfig['value'] ? $GameConfig['value'] : 50;
        $start = ($page - 1) * $num;
        $limit = "$start,$num";

        if (in_array($lottery_type, ['1','3'])) {

            //获取开奖结果
            if (empty($date)) {
                $start_time = strtotime(date("Y-m-d 00:00:00"));
                $end_time = strtotime(date("Y-m-d 23:59:59"));
            } else {
                $start_time = strtotime($date." 00:00:00");
                $end_time = strtotime($date." 23:59:59");
            }
            $where = "`state` IN (0,1) AND `lottery_type` = {$lottery_type} AND `open_time` BETWEEN ({$start_time}) AND ({$end_time})";
            $list = $this->getOpenAward($where, $limit);

            //标记大小单双
            foreach ($list as $k => $v) {

                $temp = array();
                $temp[] = mb_substr($v['spare_2'], 0, 1, 'utf-8');
                $temp[] = mb_substr($v['spare_2'], 1, 1, 'utf-8');
                $temp[] = mb_substr($v['spare_2'], 0, 2, 'utf-8');
                $temp[] = mb_substr($v['spare_2'], 2, NULL, 'utf-8');
                foreach ($temp as $key => $val) {
                    //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红
                    if ($val == "大" || $val == "单" || $val == "大单" || $val == "大双" || $val == "极大") {
                        $temp[$key] = $val . "-#2371ff";
                    } elseif ($val == "小" || $val == "双" || $val == "小单" || $val == "小双" || $val == "极小") {
                        $temp[$key] = $val . "-#f30700";
                    }
                }
                $list[$k]['spare_2'] = $temp;
                $list[$k]['open_result'] = strlen($v['open_result']) == 1 ? '0' . $v['open_result'] : $v['open_result'];
                $list[$k]['open_time'] = date('Y-m-d H:i:s', $v['open_time']);
            }

        } elseif (in_array($lottery_type, ['5','6','11'])) {

            if (empty($date)) {
                $start_time = strtotime(date("Y-m-d 00:00:00"));
                $end_time = strtotime(date("Y-m-d 23:59:59"));
            } else {
                $start_time = strtotime($date." 00:00:00");
                $start_time = $start_time+1;
                $end_time = strtotime($date." 23:59:59");
                $end_time = $end_time+1;
            }
            $sql = "SELECT id,issue,lottery_result AS open_result,lottery_time AS open_time FROM `un_ssc` WHERE `lottery_type`={$lottery_type} AND `lottery_time` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) ORDER BY `issue` DESC LIMIT {$limit}";
            $res = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                $spare_2 = D('workerman')->kaijiang_result_ssc($v['open_result']);
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'][] = str_replace('总和','',$spare_2[20]);
                $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[21]);
                $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[22]);
                $list[$k]['open_result'] = $v['open_result'];
                $list[$k]['open_time'] = date('m-d H:i',$v['open_time']);
            }
        } elseif (in_array($lottery_type, [13])) {

            if (empty($date)) {
                $start_time = strtotime(date("Y-m-d 00:00:00"));
                $end_time = strtotime(date("Y-m-d 23:59:59"));
            } else {
                $start_time = strtotime($date." 00:00:00");
                $start_time = $start_time+1;
                $end_time = strtotime($date." 23:59:59");
                $end_time = $end_time+1;
            }
            $sql = "SELECT id,issue,lottery_result AS open_result,lottery_time AS open_time FROM `un_sb` WHERE `lottery_type`={$lottery_type} AND `lottery_time` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) ORDER BY `issue` DESC LIMIT {$limit}";
            $res = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                $check = "";
                $spare_2 = D('workerman')->kaijiang_result_sb($v['open_result']);
                $list[$k]['issue'] = $v['issue'];
                foreach ($spare_2 as $value_2) {
                    if (in_array($value_2,['豹子_1','豹子_6'])) {
                        $check = str_replace("_","",$value_2);
                    }
                }
                if (!empty($check)) {
                    $list[$k]['spare_2'][] = $check;
                } else {
                    $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[12]);
                    $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[13]);
                    $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[14]);
                }

                $list[$k]['open_result'] = $v['open_result'];
                $list[$k]['open_time'] = date('m-d H:i',$v['open_time']);
            }

        } elseif (in_array($lottery_type, ['7','8'])) {

            if ($lottery_type == 8) {
                if (empty($date)) {
                    $start_time = strtotime(date("Y-m-d 00:00:00"));
                    $end_time = strtotime(date("Y-m-d 23:59:59"));
                } else {
                    $start_time = strtotime($date." 00:00:00");
                    $start_time = $start_time+1;
                    $end_time = strtotime($date." 23:59:59");
                    $end_time = $end_time+1;
                }
            } else {
                if (!empty($date)) {
                    $arr = explode("-",$date);
                    $start_time = strtotime(date($arr[0]."-1-1 00:00:00"));
                    $end_time = strtotime(date($arr[0]."-12-31 23:59:59"));
                } else {
                    $start_time = strtotime(date("Y-1-1 00:00:00"));
                    $end_time = strtotime(date("Y-12-31 23:59:59"));
                }
            }
            $sql = "SELECT id, issue, lottery_result AS open_result, lottery_time AS open_time FROM `un_lhc` WHERE `lottery_time` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) AND `lottery_type` = {$lottery_type} ORDER BY `issue` DESC LIMIT {$limit}";
            $res  = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                //$spare_2 = D('workerman')->kaijiang_result_lhc($v['open_result']);
                $open_result = explode(",",$v['open_result']);
                foreach ($open_result as $val) {
                    $list[$k]['spare_2'][] = getLhcShengxiao($val, time());
                }
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['open_result'] = $v['open_result'];
                $list[$k]['open_time'] = date('m-d H:i',$v['open_time']);
            }

        } elseif (in_array($lottery_type, ['2','9'])) {

            if (empty($date)) {
                $start_time = date("Y-m-d H:i:s",strtotime(date("Y-m-d")));
                $end_time = date("Y-m-d",strtotime(date("Y-m-d")))." 23:59:59";
            } else {
                $start_time = $date." 00:00:01";
                $end_time = date('Y-m-d H:i:s', (strtotime($date." 23:59:59")+1));
            }
            $sql = "SELECT id,qihao AS issue,kaijianghaoma AS open_result,kaijiangshijian AS open_time FROM `un_bjpk10` WHERE `kaijiangshijian` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) AND `lottery_type` = {$lottery_type} ORDER BY `qihao` DESC LIMIT {$limit}";
            $res = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
                $strleng = mb_strlen($spare_2[4]) - 1;
                $temp = array();
                $temp[] = str_replace('冠亚','',$spare_2[0]);
                $temp[] = str_replace('冠亚','',$spare_2[1]);
                //$temp[] = $spare_2[4];
                $temp[] = $spare_2[5];
                foreach ($temp as $key => $val) {
                    //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红
                    if ($val == "大" || $val == "单" || $val == "大单" || $val == "大双" || $val == "龙") {
                        $temp[$key] = $val . "-#2371ff";
                    } elseif ($val == "小" || $val == "双" || $val == "小单" || $val == "小双" || $val == "虎") {
                        $temp[$key] = $val . "-#f30700";
                    }
                }
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'] = $temp;
                $list[$k]['spare_1'] = $spare_2['6'];
//                        $strleng1 = mb_strlen($v['open_result']);
//                        $start = strpos($v['open_result'], ",");
//                        $list[$k]['open_result'] = mb_substr($v['open_result'], $start, $strleng1, 'utf-8');
                $list[$k]['open_result'] = $v['open_result'];
                $list[$k]['open_time'] = $v['open_time'];
            }
        } elseif (in_array($lottery_type, ['14'])) {
            if (empty($date)) {
                $start_time = strtotime(date("Y-m-d 00:00:00"));
                $end_time = strtotime(date("Y-m-d 23:59:59"));
            } else {
                $start_time = strtotime($date." 00:00:00");
                $start_time++;
                $end_time = strtotime($date." 23:59:59");
                $end_time++;
            }
            $sql = "SELECT id,issue,lottery_result AS open_result,lottery_time AS open_time FROM `un_ffpk10` WHERE `lottery_type`={$lottery_type} AND `lottery_time` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) ORDER BY `issue` DESC LIMIT {$limit}";
            $res = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
                $strleng = mb_strlen($spare_2[4]) - 1;
                $temp = array();
                $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');
                $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');
                //$temp[] = $spare_2[4];
                $temp[] = $spare_2[5];
                foreach ($temp as $key => $val) {
                    //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红
                    if ($val == "大" || $val == "单" || $val == "大单" || $val == "大双" || $val == "龙") {
                        $temp[$key] = $val . "-#2371ff";
                    } elseif ($val == "小" || $val == "双" || $val == "小单" || $val == "小双" || $val == "虎") {
                        $temp[$key] = $val . "-#f30700";
                    }
                }
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'] = $temp;
                $list[$k]['spare_1'] = $spare_2['6'];
                $list[$k]['open_result'] = $v['open_result'];
                $list[$k]['open_time'] = date('Y-m-d H:i',$v['open_time']);
            }
        } elseif (in_array($lottery_type, ['4'])) {

                if (empty($date)) {
                    $start_time = date("Y-m-d H:i:s",strtotime(date("Y-m-d")));
                    $end_time = date("Y-m-d",strtotime(date("Y-m-d")))." 23:59:59";
                } else {
                    $start_time = $date." 00:00:00";
                    $end_time = $date." 23:59:59";
                }
                $sql = "SELECT id,qihao AS issue,kaijianghaoma AS open_result,kaijiangshijian AS open_time FROM `un_xyft` WHERE `kaijiangshijian` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) ORDER BY `qihao` DESC LIMIT {$limit}";
                $res = O("model")->db->getall($sql);
                $list = array();
                foreach ($res as $k => $v) {
//                        dump($v);
                    $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
                    $strleng = mb_strlen($spare_2[4]) - 1;
                    $temp = array();
                    $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');
                    $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');
                    //$temp[] = $spare_2[4];
                    $temp[] = $spare_2[5];
                    foreach ($temp as $key => $val) {
                        //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红
                        if ($val == "大" || $val == "单" || $val == "大单" || $val == "大双" || $val == "龙") {
                            $temp[$key] = $val . "-#2371ff";
                        } elseif ($val == "小" || $val == "双" || $val == "小单" || $val == "小双" || $val == "虎") {
                            $temp[$key] = $val . "-#f30700";
                        }
                    }
                    $list[$k]['issue'] = $v['issue'];
                    $list[$k]['spare_2'] = $temp;
                    $list[$k]['spare_1'] = $spare_2['6'];
//                        $strleng1 = mb_strlen($v['open_result']);
//                        $start = strpos($v['open_result'], ",");
//                        $list[$k]['open_result'] = mb_substr($v['open_result'], $start, $strleng1, 'utf-8');
                    $list[$k]['open_result'] = $v['open_result'];
                    $list[$k]['open_time'] = $v['open_time'];
                }

        } elseif (in_array($lottery_type, ['10'])) {

            if (empty($date)) {
                $start_time = strtotime(date("Y-m-d 00:00:00"));
                $end_time = strtotime(date("Y-m-d 23:59:59"));
            } else {
                $start_time = strtotime($date." 00:00:00");
                $start_time++;
                $end_time = strtotime($date." 23:59:59");
                $end_time++;
            }
            $sql = "SELECT id,issue,lottery_result AS open_result,lottery_time AS open_time FROM `un_nn` WHERE `lottery_type`={$lottery_type} AND `lottery_time` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) ORDER BY `issue` DESC LIMIT {$limit}";
            $res = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                $open_result = explode(",",$v['open_result']);
                $spare_1 = checkNiuNiu([$open_result[0],$open_result[1],$open_result[2],$open_result[3],$open_result[4]]);
                $spare_1['win'] = 0;
                $spare_2 = checkNiuNiu([$open_result[5],$open_result[6],$open_result[7],$open_result[8],$open_result[9]]);
                $spare_2['win'] = 0;
                if ($spare_1['lottery_niu_num'] > $spare_2['lottery_niu_num']) {
                    $spare_1['win'] = 1;
                } elseif($spare_1['lottery_niu_num'] == $spare_2['lottery_niu_num']) {
                    if ($spare_1['lottery_max_num'] > $spare_2['lottery_max_num']) {
                        $spare_1['win'] = 1;
                    } else {
                        $spare_2['win'] = 1;
                    }
                } else {
                    $spare_2['win'] = 1;
                }
                unset($spare_1['hua'],$spare_1['hua_str'],$spare_1['pai'],$spare_1['pai_str'],$spare_1['lottery_max_num'],$spare_1['lottery_pai'],$spare_1['lottery_lh'],$spare_1['lottery_gp'],$spare_1['lottery_sum'],$spare_1['lottery_dx'],$spare_1['lottery_ds'],$spare_1['lottery_dxds'],$spare_1['lottery_niu_num']);
                unset($spare_2['hua'],$spare_2['hua_str'],$spare_2['pai'],$spare_2['pai_str'],$spare_2['lottery_max_num'],$spare_2['lottery_pai'],$spare_2['lottery_lh'],$spare_2['lottery_gp'],$spare_2['lottery_sum'],$spare_2['lottery_dx'],$spare_2['lottery_ds'],$spare_2['lottery_dxds'],$spare_2['lottery_niu_num']);
                $list[$k]['blue'] = $spare_1;
                $list[$k]['red'] = $spare_2;
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['open_time'] = date('m-d H:i',$v['open_time']);
            }

        }
        $data = array(
            'date' => $start_time,
            'list' => $list,
            'gameInfo' => $gameInfo
        );

        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($data);
    }


    /**
     * 开奖结果
     * @method get /index.php?m = api&c = openAward&a = roomDataList&lottery_type = 2&token = ajhdke3qb0rh5pk21vlp4rl3q4
     * @param date int 时间戳
     * @param lottery_type int 彩票类型
     * @param page int 分页
     * @return
     */
    public function roomDataList()
    {
        //域名跨域问题
        header("Access-Control-Allow-Origin: *");
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'lottery_type'));
        $lottery_type = trim($_REQUEST['lottery_type']);

        //验证token
        $this->checkAuth();

        //查询条数
        $limit = "0,10";
        if (in_array($lottery_type, array('1', '3'))) {
            //获取开奖结果
            $where = array(
                'lottery_type' => $lottery_type
            );
            $where[] = "`state` IN (0,1)";
            $list = $this->getOpenAward($where, $limit);

            //标记大小单双
            foreach ($list as $k => $v) {
                $temp = array();
                $temp[] = mb_substr($v['spare_2'], 0, 1, 'utf-8');
                $temp[] = mb_substr($v['spare_2'], 1, 1, 'utf-8');
                $temp[] = mb_substr($v['spare_2'], 2, NULL, 'utf-8');
                $list[$k]['spare_2'] = $temp;
                $list[$k]['open_result'] = strlen($v['open_result']) == 1 ? '0' . $v['open_result'] : $v['open_result'];
                $list[$k]['open_time'] = date('m-d H:i:s', $v['open_time']);
            }
        }else if (in_array($lottery_type, array('5', '6', '11'))) {
            $sql = "SELECT id, issue,lottery_result AS open_result,FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM `un_ssc` WHERE `status` IN (0,1) AND lottery_type={$lottery_type} ORDER BY `issue` DESC LIMIT {$limit}";
            $res = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                $spare_2 = D('workerman')->kaijiang_result_ssc($v['open_result']);
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'][] = str_replace('总和','',$spare_2[15]);
                $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[16]);
                $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[17]);
                $list[$k]['open_result'] = $v['open_result'];
                $list[$k]['open_time'] = $v['open_time'];
            }
        }else if (in_array($lottery_type, array('13'))) {
            $sql = "SELECT id, issue,lottery_result AS open_result,FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM `un_sb` WHERE `status` IN (0,1) AND lottery_type={$lottery_type} ORDER BY `issue` DESC LIMIT {$limit}";
            $res = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                $spare_2 = D('workerman')->kaijiang_result_sb($v['open_result']);
                $list[$k]['issue'] = $v['issue'];
                $check = '';
                foreach ($spare_2 as $value_2) {
                    if (in_array($value_2,['豹子_1','豹子_6'])) {
                        $check = str_replace("_","",$value_2);
                    }
                }
                if (!empty($check)) {
                    $list[$k]['spare_2'][] = $check;
                }else{
                    $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[12]);
                    $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[13]);
                    $list[$k]['spare_2'][] = str_replace('总和_','',$spare_2[14]);
                }
                $list[$k]['open_result'] = $v['open_result'];
                $list[$k]['open_time'] = $v['open_time'];
            }

        }else if (in_array($lottery_type, array(7,8))) { //六合彩
            $sql = "SELECT id, issue,lottery_result AS open_result,FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM `un_lhc` WHERE `status` IN (0,1) AND lottery_type={$lottery_type} ORDER BY `issue` DESC LIMIT 0,6";
            $res = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                $list[$k]['issue'] = $v['issue'];
                $tmp_open_result_arr = explode(',',$v['open_result']);
                foreach ($tmp_open_result_arr as $vv){
                    $list[$k]['spare_2'][] = getLhcShengxiao($vv); //生肖
                }
                $list[$k]['open_result'] = $v['open_result'];
                $list[$k]['open_time'] = $v['open_time'];
                //特码对象，包含特码的生肖和号码
                $list[$k]['te_ma_obj'] = [
                    'title' => end($list[$k]['spare_2']),
                    'num' => end($tmp_open_result_arr),
                ];
            }
        } else {
            switch ($lottery_type) {
                case 2:
                case 14:
                    if($lottery_type==2){
                        $sql = "SELECT id,qihao AS issue,kaijianghaoma AS open_result,kaijiangshijian AS open_time FROM `un_bjpk10` WHERE `status` IN (0,1) and lottery_type={$lottery_type} ORDER BY `qihao` DESC LIMIT {$limit}";
                    }else{
                        $sql = "SELECT id, issue,lottery_result AS open_result,FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM `un_ffpk10` WHERE `status` IN (0,1) AND lottery_type={$lottery_type} ORDER BY `issue` DESC LIMIT {$limit}";
                    }
                    $res = O("model")->db->getall($sql);
                    $list = array();
                    foreach ($res as $k => $v) {
                        $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
                        $list[$k]['issue'] = $v['issue'];
                        //pk10和幸运飞艇数据类型更改为数组
                        $list[$k]['spare_2'] = [$spare_2['4']];
                        $list[$k]['open_result'] = $v['open_result'];
                        $list[$k]['open_time'] = $v['open_time'];
                    }
                    break;
                case 4:
                    $sql = "SELECT id,qihao AS issue,kaijianghaoma AS open_result,kaijiangshijian AS open_time FROM `un_xyft` WHERE `status` IN (0,1) ORDER BY `qihao` DESC LIMIT {$limit}";
                    $res = O("model")->db->getall($sql);
                    $list = array();
                    foreach ($res as $k => $v) {
                        $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
                        $list[$k]['issue'] = $v['issue'];
                        $list[$k]['spare_2'] = [$spare_2['4']];
                        $list[$k]['open_result'] = $v['open_result'];
                        $list[$k]['open_time'] = $v['open_time'];
                    }
                    break;
                case 9:
                    $sql = "SELECT id,qihao AS issue,kaijianghaoma AS open_result,kaijiangshijian AS open_time FROM `un_bjpk10` WHERE `status` IN (0,1) and lottery_type={$lottery_type} ORDER BY `qihao` DESC LIMIT {$limit}";
                    $res = O("model")->db->getall($sql);
                    $list = array();
                    foreach ($res as $k => $v) {
                        $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
                        $list[$k]['issue'] = $v['issue'];
                        //pk10和幸运飞艇数据类型更改为数组
                        $list[$k]['spare_2'] = [$spare_2['4']];
                        $list[$k]['open_result'] = $v['open_result'];
                        $list[$k]['open_time'] = $v['open_time'];
                    }
                    break;
                case 10:
                    $sql = "SELECT id, issue,lottery_result AS open_result,FROM_UNIXTIME(lottery_time, '%Y-%m-%d %H:%i:%S') AS open_time FROM `un_nn` WHERE `status` IN (0,1) AND lottery_type={$lottery_type} ORDER BY `issue` DESC LIMIT 0,5";
                    $res = O("model")->db->getall($sql);
                    $list = array();
                    foreach ($res as $k => $v) {
                        $list[$k]['issue'] = $v['issue'];
                        $spare_2 = getShengNiuNiu($v['open_result'],1);
                        $list[$k]['vs']= array(
                            'red'=>array('pai'=>explode(',',$spare_2['red']['lottery_pai']),'niu'=>$spare_2['red']['lottery_niu']),
                            'blue'=>array('pai'=>explode(',',$spare_2['blue']['lottery_pai']),'niu'=>$spare_2['blue']['lottery_niu']),
                        );
                        $list[$k]['open_result'] = '('.$spare_2['sheng'].','.($spare_2['sheng']=='红方胜'?$spare_2['red']['lottery_niu']:$spare_2['blue']['lottery_niu']).')';
                        $list[$k]['open_result_another'] = $spare_2['sheng'].','.($spare_2['sheng']=='红方胜'?$spare_2['red']['lottery_niu']:$spare_2['blue']['lottery_niu']);
                        $list[$k]['open_time'] = $v['open_time'];

                        //胜负方
                        if ($spare_2['sheng'] == '红方胜') {
                            $list[$k]['who_win'] = 1;
                            $list[$k]['win_pai'] = explode(',',$spare_2['red']['lottery_pai']);
                        } else {
                            $list[$k]['who_win'] = 0;
                            $list[$k]['win_pai'] = explode(',',$spare_2['blue']['lottery_pai']);
                        }
                    }
                    break;
            }
        }

        ErrorCode::successResponse(array('list' => $list));
    }

    /**
     * 获取北京PK10近期10条数据
     */
    public function getTenData()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token'));
        //验证token
        $this->checkAuth();

        $res = $this->db->getall('select id,qihao,kaijianghaoma,kaijiangshijian from un_bjpk10 where lottery_type=2 order by id desc limit 10');

        foreach ($res as $k => $v) {
            $list[$k]['id'] = $v['id'];
            $list[$k]['issue'] = $v['qihao'];
            $list[$k]['open_no'] = $v['kaijianghaoma'];
            $list[$k]['open_time'] = date('m-d H:i:s', strtotime($v['kaijiangshijian']));
        }

        ErrorCode::successResponse(array('list' => $list));
    }


    /**
     * 开奖走势
     * @method get /index.php?m = api&c = openAward&a = trendList&token = b5062b58d2433d1983a5cea888597eb6&date = 1&lottery_type = 2&page = 0
     * @param date int 时间戳
     * @param lottery_type int 彩票类型
     * @param page int 分页
     * @return
     */
    public function trendList()
    {
        
        //验证参数
        //$this->checkInput($_REQUEST, array('token', 'date', 'lottery_type', 'page'));
        $this->checkInput($_REQUEST, array('date', 'lottery_type', 'page'));
        $date = trim($_REQUEST['date']);
        $lottery_type = trim($_REQUEST['lottery_type']);
        $page = trim($_REQUEST['page']);
        $page = empty($page) ? 1 : $page;

        //验证token
        //$this->checkAuth();

        //初始化redis
        $redis = initCacheRedis();
        //验证游戏类型
        $LotteryTypeIds = $redis->lRange("LotteryTypeIds", 0, -1);
        if ($lottery_type == 0) {
            $lottery_type = $LotteryTypeIds['0'];
        }

        //获取游戏信息
        $gameInfo = array();
        foreach ($LotteryTypeIds as $v) {
            if($v!=12){
                $gameInfo[] = $redis->hGetAll("LotteryType:" . $v);
            }
        }

        //获取查询条数
        $GameConfig = $redis->hGetAll("Config:100008");
        $num = $GameConfig['value'] ? $GameConfig['value'] : 50;
        $start = ($page - 1) * $num;
        $limit = "$start,$num";
        if (in_array($lottery_type, ['1','3'])) {

            //获取开奖结果
            if (empty($date)) {
                $start_time = strtotime(date("Y-m-d 00:00:00"));
                $end_time = strtotime(date("Y-m-d 23:59:59"));
            } else {
                $start_time = strtotime($date." 00:00:00");
                $end_time = strtotime($date." 23:59:59");
            }
            $where = "`state` IN (0,1) AND `lottery_type` = {$lottery_type} AND `open_time` BETWEEN ({$start_time}) AND ({$end_time})";
            $list = $this->getOpenAward($where, $limit);

            //定义大小单双玩法数组
            $typeArr = array('大', '小', '单', '双', '大单', '大双', '小单', '小双');

            //开奖间隔
            foreach ($list as $k => $v) {
                $tempArr = array();
                //大小单双分隔
                $type = mb_substr($v['spare_2'], 0, 1, 'utf-8');
                $type2 = mb_substr($v['spare_2'], 1, 1, 'utf-8');
                $type3 = mb_substr($v['spare_2'], 0, 2, 'utf-8');
                $type4 = mb_substr($v['spare_2'], 2, NULL, 'utf-8');
                $temp = array($type, $type2, $type3, $type4);
                //循环匹配
                foreach ($typeArr as $v2) {
                    $tempArr[] = in_array($v2, $temp) ? $v2 : '';
                }

                if ($k == 0 && $list[$k]['spare_3'] == "") { //过滤掉没有走势的数据
                    unset($list[$k]);
                    continue;
                }
                foreach ($tempArr as $key => $val) {
                    //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红
                    if ($val == "大" || $val == "单" || $val == "大单" || $val == "大双") {
                        $tempArr[$key] = $val . "-#175AAE";
                    } elseif ($val == "小" || $val == "双" || $val == "小单" || $val == "小双") {
                        $tempArr[$key] = $val . "-#DC5D55";
                    }
                }

                $list[$k]['spare_2'] = $tempArr;
                $list[$k]['open_result'] = strlen($v['open_result']) == 1 ? '0' . $v['open_result'] : $v['open_result'];
                $list[$k]['spare_3'] = explode(',', $v['spare_3']);
            }

            $list = array_values($list);

        } elseif(in_array($lottery_type, ['5','6','11'])) {

            if (!empty($date)) {
                $start_date = strtotime($date.' 00:00:00');
                $end_date = strtotime($date.' 23:59:59');
            } else {
                $start_date = strtotime(date("Y-m-d 00:00:00"));
                $end_date = strtotime(date("Y-m-d 23:59:59"));
            }
            $sql = "SELECT id, issue, lottery_result AS open_result,lottery_time AS open_time FROM `un_ssc` WHERE `lottery_time` BETWEEN '{$start_date}' AND '{$end_date}' AND `status` IN (0,1) AND `lottery_type` = {$lottery_type} ORDER BY `issue` DESC LIMIT {$limit}";
            $res  = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v){
                $tempArr = [
                    '1' => 0, //总和
                    '2' => "", //大
                    '3' => "", //小
                    '4' => "", //单
                    '5' => "", //双
                    '6' => "", //龙虎和
                ];
                $spare_2 = D('workerman')->kaijiang_result_ssc($v['open_result']);
                foreach ($spare_2 as $val) {
                    if (preg_match("/^总和\d*$/", $val, $arr)) {
                        $tempArr[1] = str_replace("总和"," ", $arr[0]);
                    }
                    if ($val == "总和_大") {
                        $arr = explode("_",$val);
                        $tempArr[2] = $arr[1];
                    }
                    if ($val == "总和_小") {
                        $arr = explode("_",$val);
                        $tempArr[3] = $arr[1];
                    }
                    if ($val == "总和_单") {
                        $arr = explode("_",$val);
                        $tempArr[4] = $arr[1];
                    }
                    if ($val == "总和_双") {
                        $arr = explode("_",$val);
                        $tempArr[5] = $arr[1];
                    }
                    if (in_array($val,['龙','虎','和'])) {
                        $tempArr[6] = $val;
                    }
                }
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'] = array_values($tempArr);
            }
        } elseif(in_array($lottery_type, [13])) {
            if (!empty($date)) {
                $start_date = strtotime($date.' 00:00:00');
                $end_date = strtotime($date.' 23:59:59');
            } else {
                $start_date = strtotime(date("Y-m-d 00:00:00"));
                $end_date = strtotime(date("Y-m-d 23:59:59"));
            }
            $sql = "SELECT id, issue, lottery_result AS open_result,lottery_time AS open_time FROM `un_sb` WHERE `lottery_time` BETWEEN '{$start_date}' AND '{$end_date}' AND `status` IN (0,1) AND `lottery_type` = {$lottery_type} ORDER BY `issue` DESC LIMIT {$limit}";
            $res  = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v){
                $tempArr = [
                    '1' => "", //总和
                    '2' => "", //大
                    '3' => "", //小
                    '4' => "", //单
                    '5' => "", //双
                    '6' => "", //豹子
                ];
                $spare_2 = D('workerman')->kaijiang_result_sb($v['open_result']);
                foreach ($spare_2 as $value) {

                    if ($value == "总和_大") {
                        $tempArr[2] = str_replace("总和_",'',$value);
                    }
                    if ($value == "总和_小") {
                        $tempArr[3] = str_replace("总和_",'',$value);
                    }
                    if ($value == "总和_单") {
                        $tempArr[4] = str_replace("总和_",'',$value);
                    }
                    if ($value == "总和_双") {
                        $tempArr[5] = str_replace("总和_",'',$value);
                    }
                    if ($value == "总和_小") {
                        $tempArr[3] = str_replace("总和_",'',$value);
                    }
                    if (in_array($value,['豹子_1','豹子_2','豹子_3','豹子_4','豹子_5','豹子_6'])) {
                        $tempArr[6] = str_replace("豹子_",'',$value);
                    }

                }
                $tempArr[1] =!empty($tempArr[6])?(string)(3*$tempArr[6]):str_replace("总和_",'',$spare_2[12]);
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'] = array_values($tempArr);
            }
        } elseif(in_array($lottery_type, ['7','8'])) {

            if ($lottery_type == 8) {
                if (empty($date)) {
                    $start_time = strtotime(date("Y-m-d 00:00:00"));
                    $end_time = strtotime(date("Y-m-d 23:59:59"));
                } else {
                    $start_time = strtotime($date." 00:00:00");
                    $end_time = strtotime($date." 23:59:59");
                }
            } else {
                if (!empty($date)) {
                    $arr = explode("-",$date);
                    $start_time = strtotime(date($arr[0]."-1-1 00:00:00"));
                    $end_time = strtotime(date($arr[0]."-12-31 23:59:59"));
                } else {
                    $start_time = strtotime(date("Y-1-1 00:00:00"));
                    $end_time = strtotime(date("Y-12-31 23:59:59"));
                }
            }
            $sql = "SELECT id, issue, lottery_result AS open_result, lottery_time AS open_time FROM `un_lhc` WHERE `lottery_time` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) AND `lottery_type` = {$lottery_type} ORDER BY `issue` DESC LIMIT {$limit}";
            $res  = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v){
                $spare_2 = D('workerman')->kaijiang_result_lhc($v['open_result']);
                $open_result = explode(",",$v['open_result']);
                $tempArr = [
                    'total' => 0, //总和
                    'num' => 0, //特码
                    'zodiac' => "", //特肖
                    'animal' => 0, //家禽还是野兽
                    'wave' => 0, //波色
                    'large' => "", //大
                    'small' => "",//小
                    'single' => "",//单
                    'double' => "",//双
                    'he_large' => "", //合大
                    'he_small' => "",//合小
                    'he_single' => "",//合单
                    'he_double' => "",//合双
                    'he' => ""//合
                ];
                foreach ($open_result as $val) {
                    $tempArr['total'] += $val;
                }
                foreach ($spare_2 as $value) {
                    $result = explode("_",$value);
                    if (in_array($result[0],['特肖'])) {
                        $tempArr['zodiac'] = $result[1];
                    }
                    if (in_array($result[0],['特码A'])) {
                        if (in_array($result[1],range(1,49))) {
                            $tempArr['num'] = $result[1];
                        }
                        if (in_array($result[1],['家禽','野兽'])) {
                            $tempArr['animal'] = $result[1];
                        }
                        if (in_array($result[1],['红波','蓝波','绿波'])) {
                            $tempArr['wave'] = $result[1];
                        }
                        if (in_array($result[1],['大'])) {
                            $tempArr['large'] = $result[1];
                        }
                        if (in_array($result[1],['小'])) {
                            $tempArr['small'] = $result[1];
                        }
                        if (in_array($result[1],['单'])) {
                            $tempArr['single'] = $result[1];
                        }
                        if (in_array($result[1],['双'])) {
                            $tempArr['double'] = $result[1];
                        }
                        if (in_array($result[1],['合大'])) {
                            $tempArr['he_large'] = $result[1];
                        }
                        if (in_array($result[1],['合小'])) {
                            $tempArr['he_small'] = $result[1];
                        }
                        if (in_array($result[1],['合单'])) {
                            $tempArr['he_single'] = $result[1];
                        }
                        if (in_array($result[1],['合双'])) {
                            $tempArr['he_double'] = $result[1];
                        }
                        if (in_array($result[1],['和'])) {
                            $tempArr['he'] = $result[1];
                        }
                    }
                }
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'] = array_values($tempArr);
            }

        } elseif(in_array($lottery_type, ['2','9'])) {
            if (empty($date)) {
                $start_time = date("Y-m-d H:i:s",strtotime(date("Y-m-d")));
                $end_time = date("Y-m-d",strtotime(date("Y-m-d")))." 23:59:59";
            } else {
                $start_time = $date." 00:00:00";
                $end_time = $date." 23:59:59";
            }
            $sql = "SELECT id,qihao AS issue,kaijianghaoma AS open_result,kaijiangshijian AS open_time FROM `un_bjpk10` WHERE `kaijiangshijian` BETWEEN '{$start_time}' AND '{$end_time}' AND `lottery_type` = {$lottery_type} AND `status` IN (0,1) ORDER BY `qihao` DESC LIMIT {$limit}";
            $res = O("model")->db->getall($sql);
            $list = array();
            //定义大小单双玩法数组
            $typeArr = array('大','小','单','双');
            foreach ($res as $k => $v){
                $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
                $strleng = mb_strlen($spare_2[4])-1;
                //定义大小单双玩法数组
                $temp = array();
                $temp[] = str_replace('冠亚','',$spare_2[0]);
                $temp[] = str_replace('冠亚','',$spare_2[1]);

                //循环匹配
                $tempArr = array();
                foreach ($typeArr as $v2) {
                    $tempArr[] = in_array($v2, $temp) ? $v2 : '';
                }
                $tempArr[] = in_array($spare_2[5], array('龙','虎')) ? $spare_2[5] : '';

                foreach($tempArr as $key=>$val)
                {
                    //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红
                    if($val == "大" || $val == "单" || $val == "大单" || $val == "大双" || $val == "龙")
                    {
                        $tempArr[$key] = $val."-#2371ff";
                    }
                    elseif($val == "小" || $val == "双" || $val == "小单" || $val == "小双" || $val == "虎")
                    {
                        $tempArr[$key] = $val."-#f30700";
                    }
                }

                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'] = $tempArr;
//                        $list[$k]['spare_1'] = $spare_2['7'];
                $strleng1 = mb_strlen($spare_2['7']);
                $list[$k]['open_result'] = mb_substr($spare_2['7'],1,$strleng1,'utf-8');
//                        $list[$k]['open_time'] = $v['open_time'];
            }
        } elseif(in_array($lottery_type, ['14'])) {
            if (empty($date)) {
                $start_time = strtotime(date("Y-m-d 00:00:00"));
                $end_time = strtotime(date("Y-m-d 23:59:59"));
            } else {
                $start_time = strtotime($date." 00:00:00");
                $end_time = strtotime($date." 23:59:59");
            }
            $sql = "SELECT id, issue, lottery_result AS open_result,lottery_time AS open_time FROM `un_ffpk10` WHERE `lottery_time` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) AND `lottery_type` = {$lottery_type} ORDER BY `issue` DESC LIMIT {$limit}";

            $res = O("model")->db->getall($sql);
            $list = array();
            //定义大小单双玩法数组
            $typeArr = array('大', '小', '单', '双');
            foreach ($res as $k => $v) {
                $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
                $strleng = mb_strlen($spare_2[4]) - 1;
                //定义大小单双玩法数组
                $temp = array();
                $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');
                $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');

                //循环匹配
                $tempArr = array();
                foreach ($typeArr as $v2) {
                    $tempArr[] = in_array($v2, $temp) ? $v2 : '';
                }
                $tempArr[] = in_array($spare_2[5], array('龙', '虎')) ? $spare_2[5] : '';

                foreach ($tempArr as $key => $val) {
                    //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红
                    if ($val == "大" || $val == "单" || $val == "大单" || $val == "大双" || $val == "龙") {
                        $tempArr[$key] = $val . "-#2371ff";
                    } elseif ($val == "小" || $val == "双" || $val == "小单" || $val == "小双" || $val == "虎") {
                        $tempArr[$key] = $val . "-#f30700";
                    }
                }

                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'] = $tempArr;
                //                        $list[$k]['spare_1'] = $spare_2['7'];
                $strleng1 = mb_strlen($spare_2['7']);
                $list[$k]['open_result'] = mb_substr($spare_2['7'], 1, $strleng1, 'utf-8');
                //                        $list[$k]['open_time'] = $v['open_time'];
            }
        } elseif (in_array($lottery_type, ['4'])) {

                if (empty($date)) {
                    $start_time = date("Y-m-d H:i:s",strtotime(date("Y-m-d")));
                    $end_time = date("Y-m-d",strtotime(date("Y-m-d")))." 23:59:59";
                } else {
                    $start_time = $date." 00:00:00";
                    $end_time = $date." 23:59:59";
                }
                $sql = "SELECT id,qihao AS issue,kaijianghaoma AS open_result,kaijiangshijian AS open_time FROM `un_xyft` WHERE `kaijiangshijian` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) ORDER BY `qihao` DESC LIMIT {$limit}";
                $res = O("model")->db->getall($sql);
                $list = array();
                //定义大小单双玩法数组
                $typeArr = array('大', '小', '单', '双');
                foreach ($res as $k => $v) {
                    $spare_2 = D('workerman')->kaijiang_result($v['open_result']);
                    $strleng = mb_strlen($spare_2[4]) - 1;
                    //定义大小单双玩法数组
                    $temp = array();
                    $temp[] = mb_substr($spare_2[4], 0, 1, 'utf-8');
                    $temp[] = mb_substr($spare_2[4], $strleng, 1, 'utf-8');

                    //循环匹配
                    $tempArr = array();
                    foreach ($typeArr as $v2) {
                        $tempArr[] = in_array($v2, $temp) ? $v2 : '';
                    }
                    $tempArr[] = in_array($spare_2[5], array('龙', '虎')) ? $spare_2[5] : '';

                    foreach ($tempArr as $key => $val) {
                        //大为蓝；小为红；单为蓝；双为红；大单大双为蓝；小单小双为红
                        if ($val == "大" || $val == "单" || $val == "大单" || $val == "大双" || $val == "龙") {
                            $tempArr[$key] = $val . "-#2371ff";
                        } elseif ($val == "小" || $val == "双" || $val == "小单" || $val == "小双" || $val == "虎") {
                            $tempArr[$key] = $val . "-#f30700";
                        }
                    }

                    $list[$k]['issue'] = $v['issue'];
                    $list[$k]['spare_2'] = $tempArr;
                    //                        $list[$k]['spare_1'] = $spare_2['7'];
                    $strleng1 = mb_strlen($spare_2['7']);
                    $list[$k]['open_result'] = mb_substr($spare_2['7'], 1, $strleng1, 'utf-8');
                    //                        $list[$k]['open_time'] = $v['open_time'];
                }

        } elseif (in_array($lottery_type, ['10'])) {

            if (empty($date)) {
                $start_time = strtotime(date("Y-m-d 00:00:00"));
                $end_time = strtotime(date("Y-m-d 23:59:59"));
            } else {
                $start_time = strtotime($date." 00:00:00");
                $end_time = strtotime($date." 23:59:59");
            }
            $sql = "SELECT id, issue, lottery_result AS open_result,lottery_time AS open_time FROM `un_nn` WHERE `lottery_time` BETWEEN '{$start_time}' AND '{$end_time}' AND `status` IN (0,1) AND `lottery_type` = {$lottery_type} ORDER BY `issue` DESC LIMIT {$limit}";
            $res  = O("model")->db->getall($sql);
            $list = array();
            foreach ($res as $k => $v) {
                $open_result = explode(",",$v['open_result']);
                $spare_1 = checkNiuNiu([$open_result[0],$open_result[1],$open_result[2],$open_result[3],$open_result[4]]);//蓝
                $spare_2 = checkNiuNiu([$open_result[5],$open_result[6],$open_result[7],$open_result[8],$open_result[9]]);//红
                if ($spare_1['lottery_niu_num'] > $spare_2['lottery_niu_num']) {
                    $winner = "蓝";
                } elseif($spare_1['lottery_niu_num'] == $spare_2['lottery_niu_num']) {
                    if ($spare_1['lottery_max_num'] > $spare_2['lottery_max_num']) {
                        $winner = "蓝";
                    } else {
                        $winner = "红";
                    }
                } else {
                    $winner = "红";
                }
                $tmp['win'] = $winner;
                if ($winner == "蓝") {
                    $tmp['niu'] = $spare_1['lottery_niu'];//牛几
                    $tmp['gp'] = $spare_1['lottery_gp'] == 1 ? '有' : '无';//公牌
                    $tmp['lh'] = $spare_1['lottery_lh'];//龙虎
                    $tmp['large'] = $spare_1['lottery_sum'] > 35 ? '大' : '';
                    $tmp['small'] = $spare_1['lottery_sum'] < 34 ? '小' : '' ;
                    $tmp['single'] = $spare_1['lottery_sum']%2 != 0 ? '单' : '';
                    $tmp['double'] = $spare_1['lottery_sum']%2 == 0 ? '双' : '';
                    $tmp['he'] = $spare_1['lottery_sum'];//总和
                } else {
                    $tmp['niu'] = $spare_2['lottery_niu'];//牛几
                    $tmp['gp'] = $spare_2['lottery_gp'] == 1 ? '有' : '无';//公牌
                    $tmp['lh'] = $spare_2['lottery_lh'];//龙虎
                    $tmp['large'] = $spare_2['lottery_sum'] > 35 ? '大' : '';
                    $tmp['small'] = $spare_2['lottery_sum'] < 34 ? '小' : '' ;
                    $tmp['single'] = $spare_2['lottery_sum']%2 != 0 ? '单' : '';
                    $tmp['double'] = $spare_2['lottery_sum']%2 == 0 ? '双' : '';
                    $tmp['he'] = $spare_2['lottery_sum'];//总和
                }
                unset($tmp['hua'],$tmp['hua_str'],$tmp['pai'],$tmp['pai_str'],$tmp['lottery_pai_arr'],$tmp['lottery_max_num']);
                $list[$k]['issue'] = $v['issue'];
                $list[$k]['spare_2'] = array_values($tmp);
            }
        }
        $data = array(
            'date' => $start_time,
            'list' => $list,
            'gameInfo' => $gameInfo,
        );

        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse($data);
    }

    /**
     * 投注梭哈
     * * @method get /index.php?m = api&c = openAward&a = getBetting&token = b5062b58d2433d1983a5cea888597eb6
     * @param token string
     * @return mixed
     */
    public function getBetting()
    {
        //验证token
        $this->checkAuth();
        //初始化redis
        $redis = initCacheRedis();

//        $bettingIds = array();
//        $bettingIds = $redis->lRange("DictionaryIds11", 0, -1);
//        //银行列表
//        $betting = array();
//        foreach ($bettingIds as $v) {
//            $res = $redis->hMGet("Dictionary11:" . $v, array('value'));
//            $betting[] = $res['value'];
//        }
        $re = $redis->hget('Config:quick_bet_set','value');
        $betting = decode($re);
		sort($betting);
        $configJson = $redis->HMGet("Config:musicTips", array('value')); //获取提示音配置参数
        $music = json_decode($configJson['value'], true);        //格式化提示音配置参数
        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse(array("list" => $betting, 'music' => $music));
    }


    /**
     * 开奖记录
     * @param $where mixed 条件
     * @param $limit string 条数
     * @return $res array
     */
    protected function getOpenAward($where, $limit = null)
    {
        $filed = 'id, issue, open_time, open_result, open_no, spare_1, spare_2, spare_3, lottery_type';
        $order = 'issue DESC, open_time DESC';
        //显示几条
        if ($limit) {
            $res = $this->model->getList($filed, $where, $order, $limit);
        } else {
            $res = $this->model->getList($filed, $where, $order);
        }

        return $res;
    }

    public  function delQiHaoIds(){
        $id = $_REQUEST['id'];
        $redis = initCacheRedis();
        $idsArr = $redis->lrange('LotteryTypeIds',0,-1);
        if(!in_array($id,$idsArr)){
            dump('非法数据');
            return false;
        }
        $key = 'QiHaoIds'.$id;
        if($redis->exists($key)){
            $re  = $redis->del($key);
            if(!empty($re)){
                dump('清除完成！');
            }
        }else{
            dump($key.'不存在!');
        }
        deinitCacheRedis($redis);
    }

    /**
     * 开奖结果立即购彩接口
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-03-06 15:14
     */
    public function instantlyBetting() {
        //验证token
        $this->checkAuth();

        $lottery_type = trim($_REQUEST['lottery_type']);

        //初始化redis
        $redis = initCacheRedis();

        $index_lottery_list = $redis -> HMGet('Config:index_lottery_list',['value']);
        $lottery_list = json_decode($index_lottery_list['value'], true);
        foreach ($lottery_list as $val) {
            if ($val['lottery_type'] == $lottery_type){
                if ($val['is_show'] == 0) {
                    ErrorCode::errorResponse(-1,'Sorry, the current lottery is not open, please choose another lottery to bet');
                    break;
                }
            }
        }
	
        //实例化Gateway
        $Gateway = O('Gateway');
        $Gateway::$registerAddress = C('Gateway');

        //获取用户信息
        $sql = "select a.money,u.nickname from #@_account as a left join #@_user as u on u.id = a.user_id where a.user_id = '{$this->userId}'";
        $user_info = $this->db->getone($sql);
        if (empty($user_info['nickname'])) {
            ErrorCode::errorResponse(-1,'You have not set a nickname. Set a nickname');
        }

        //判断用户是否被房间限制
        $authorRoom = checkAuthorRoom($this->userId, $lottery_type);
        if (!empty($authorRoom)) {
            ErrorCode::errorResponse(-1,'Sorry, you are not authorized for the current room');
        }

        //获取房间信息
        $room_info = [];
        $PRoomIds = $redis->lRange("PublicRoomIds{$lottery_type}", 0, -1);
        foreach ($PRoomIds as $val) {
            $room = $redis -> hGetAll("PublicRoom{$lottery_type}:{$val}");
            $room_info[$room['id']] = $room;
            $onlineCount = Gateway::getClientCountByGroup($room['id']); //在线总人数
            if ($onlineCount + 1 > $room['max_number']) {
                unset($room_info[$room['id']]);
            }

            if ($user_info['money'] < $room['low_yb'] || $user_info['money'] > $room['max_yb']) {
                unset($room_info[$room['id']]);
            }
            if ($room['status'] == 1) {
                unset($room_info[$room['id']]);
            }
        }
        if (empty($room_info)) {
            ErrorCode::errorResponse(-1,'Sorry, there is no room matching your current lottery type, please choose another lottery type');
        }

        //组装返回参数
        $room_id_arr = array_keys($room_info);
        sort($room_id_arr);
        $room_id = array_shift($room_id_arr);

        $data['lottery_title'] = $redis->hGet("LotteryType:{$lottery_type}",'name');
        $data['lottery_type'] = $lottery_type;
        $data['room_id'] = $room_id;
        $data['title'] = $room_info[$room_id]['title'];
        $data['odds_exp'] = $room_info[$room_id]['odds_exp'];
        $data['lower'] = $room_info[$room_id]['lower'];
        $data['balance'] = $user_info['money'];

        //关闭redis链接
        deinitCacheRedis($redis);
        ErrorCode::successResponse(['ret_msg' => 'Request succeeded', 'data' => $data]);
    }
}