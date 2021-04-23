<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'center' . DS . 'action.php');
include S_CORE . 'class' . DS . 'pages.php';

class issueAction extends Action{

    //文件目录路径（暂时放在项目根目录）
    public $file_path = '';

    //每年的过年日期map
    public $year_last_day;

    public $lottery_type_obj = [
        //幸运28
        '1' => [
            'first_issue_time' => '09:05:00',
            'lottery_file_name' => 'xy28_qihao.json',
            'name_text' => '幸运28',
            'lottery_txt' => 'xy28',
        ],

        //北京PK10
        '2' => [
            'first_issue_time' => '09:30:40',
            'lottery_file_name' => 'bjpk10_qihao.json',
            'name_text' => '北京PK10',
            'lottery_txt' => 'bjpk10',
        ],

        //加拿大28
        '3' => [
            'first_issue_time' => '',
            'lottery_file_name' => 'jnd28_qihao.json',
            'name_text' => '加拿大28',
            'lottery_txt' => 'jnd28',
        ],

        //幸运飞艇
        '4' => [
            'first_issue_time' => '13:08:45',
            'lottery_file_name' => 'xyft_qihao.json',
            'name_text' => '幸运飞艇',
            'lottery_txt' => 'xyft',
        ],

        //重庆时时彩
        '5' => [
            'first_issue_time' => '00:30:20',
            'lottery_file_name' => 'cqssc_qihao.json',
            'name_text' => '重庆时时彩',
            'lottery_txt' => 'cqssc',
        ],

        //三分彩
        '6' => [
            'first_issue_time' => '00:03:00',
            'lottery_file_name' => 'sfc_qihao.json',
            'name_text' => '三分彩',
            'lottery_txt' => 'sfc',
        ],

        //急速六合彩
        '8' => [
            'first_issue_time' => '00:05:00',
            'lottery_file_name' => 'jslhc_qihao.json',
            'name_text' => '急速六合彩',
            'lottery_txt' => 'jslhc',
        ],

        //急速赛车
        '9' => [
            'first_issue_time' => '00:03:00',
            'lottery_file_name' => 'jssc_qihao.json',
            'name_text' => '急速赛车',
            'lottery_txt' => 'jssc',
        ],

        //牛牛
        '10' => [
            'first_issue_time' => '00:05:00',
            'lottery_file_name' => 'nn_qihao.json',
            'name_text' => '牛牛',
            'lottery_txt' => 'nn',
        ],

        //分分彩
        '11' => [
            'first_issue_time' => '00:01:00',
            'lottery_file_name' => 'ffc_qihao.json',
            'name_text' => '分分彩',
            'lottery_txt' => 'ffc',
        ],

        //骰宝
        '13' => [
            'first_issue_time' => '00:05:00',
            'lottery_file_name' => 'sb_qihao.json',
            'name_text' => '骰宝',
            'lottery_txt' => 'sb',
        ],

        //分分PK10
        '14' => [
            'first_issue_time' => '00:01:00',
            'lottery_file_name' => 'ffpk10_qihao.json',
            'name_text' => '分分PK10',
            'lottery_txt' => 'ffpk10',
        ],
    ];

    public function __construct() {
        parent::__construct();

        if (!$this->checkAuth()) {
            $this->retArr['data'][] = $_REQUEST;
            $this->returnCurl();
            return;
        }

        //初始化每年的除夕，过年前一天
        $this->year_last_day = [
            '2020' => ['2020-01-24','2021-02-11'],
            '2021' => ['2021-02-11','2022-01-31'],
            '2022' => ['2022-01-31','2023-01-21'],
            '2023' => ['2023-01-21','2024-02-09'],
            '2024' => ['2024-02-09','2025-01-28'],
            '2025' => ['2025-01-28','2026-02-16'],
        ];

        $this->file_path = "json_files/issue/";

    }

    public function jnd28Issue(){

        $jnd28data = file_get_contents(S_ROOT . 'jnd28_qihao.json');

        //获取最新开奖期号
        $trend_model = D('Trend');
        $rt_data = $trend_model->getMoreLottery28(3,1,date('Y-m-d'))['data']['list'];
        if(!empty($jnd28data)){
            $jnd28data = json_decode($jnd28data,1);
            $jnd28data = json_decode($jnd28data['txt'],1)['list'];
            $type = 0;
            foreach ($jnd28data as $key=>$item){
                if($rt_data[0]['lottery_id'] == $jnd28data[$key]['issue']){
                    $type = 1;
                    break;
                }
            }
            if($type == 1){
                die('已经生成了');
            }
        }

        $begin_create_issue  = $rt_data[0]['lottery_id'];
        $begin_create_time = date('Y-m-d H:i:s',$rt_data[0]['lottery_date']);
        $ret = curl_get_content(C('app_home').'/?m=center&c=issue&a=handCreateIssue&lottery_type=3&begin_create_time='.$begin_create_time.'&begin_create_issue='.$begin_create_issue.'&sign=7758258');
        print_r($ret);
    }

    public function handCreateIssue() {
        $lottery_type = $this->getParame('lottery_type', 1, '', 'int', '彩种为空或格式错误！');

        $lottery_dir_name = $this->file_path . date('Y') . '/' . $this->lottery_type_obj[$lottery_type]['lottery_txt'] . '/';
        $lottery_dir_name2 = $this->file_path . (date('Y') + 1) . '/' . $this->lottery_type_obj[$lottery_type]['lottery_txt'] . '/';

        $begin_create_time = $this->getParame('begin_create_time',1,'','str','开奖时间为空或格式错误');
        $begin_create_issue = $this->getParame('begin_create_issue',1,'','checkLongNum','开奖期号为空或格式错误');
        //判断路径是否存在，若不在，则生成
        if (! is_dir($lottery_dir_name) || ! is_dir($lottery_dir_name2)) {
            mkdirs(S_ROOT . $lottery_dir_name);
            mkdirs(S_ROOT . $lottery_dir_name2);
        }

        //生成参数数组
        $arg_arr = [
            'lottery_type' => $lottery_type,
            'begin_create_time' => $begin_create_time,
            'begin_create_issue' => $begin_create_issue,
            'add_days' => '',
        ];

        //时间
        $now_time = time();
        $this_year = date('Y');
        if ($now_time < strtotime($this->year_last_day[$this_year][0] . ' 23:59:59' )) {
            $end_date = $this->year_last_day[$this_year][0];
        } else {
            $end_date = $this->year_last_day[$this_year][1];
        }

        //加拿大28只生成一天期号
        if ($lottery_type == 3) {
            $diff_day = 1;
            //其余生成一年
        }else{
            $diff_day = (strtotime($end_date) - $now_time) / 86400;
            $diff_day = ceil($diff_day) + 1;
        }

        $reset_reids_issue_arr = [];
        $push_data = [];

        $minus_days_timestamp = 0;

        //幸运飞艇、3分彩、急速六合彩、急速赛车、牛牛、分分彩、欢乐骰宝、分分PK10的最后一期与生成日跨了一天，兼容做法如下：
        if (in_array($lottery_type, ['4', '6', '8', '9', '10','11','13','14'])) {
            $minus_days_timestamp = 86400;
        }

        for ($i = 1; $i <= $diff_day; $i++) {
            //创建期号数据
            $new_issue_arr = $this->createIssue($arg_arr);

            //期号数量
            $arr_length = count($new_issue_arr);

            //当天的数据，需要写入到文件中
            if ($i == 1) {
                $reset_reids_issue_arr = $new_issue_arr;

                $tmp_push_data = [];
                $tmp_push_data['txt'] = json_encode([
                    'list' => $new_issue_arr,
                    'visit_time' => $now_time,
                    'length' => $arr_length,
                ]);
                $tmp_issue_data = json_encode($tmp_push_data);

                @file_put_contents('./' . $this->lottery_type_obj[$lottery_type]['lottery_file_name'], $tmp_issue_data);

            }

            $end_issue_item = end($new_issue_arr);

            //这是兼容幸运飞艇、重庆时时彩的写法，幸运飞艇、重庆时时彩最后一期和生成期号时跨了1天
            $day_timestamp = $end_issue_item['date'] - $minus_days_timestamp;


            //将新期号写入的文件
            $push_file = $this->file_path . date('Y', $day_timestamp) . '/' . $this->lottery_type_obj[$lottery_type]['lottery_txt'] . '/' . date('Y_m_d@', $day_timestamp) . $this->lottery_type_obj[$lottery_type]['lottery_file_name'];

            //将重置期号的数据重新写入json文件
            $push_data['txt'] = json_encode([
                'list' => $new_issue_arr,
                'visit_time' => $now_time,
                'length' => $arr_length,
            ]);
            $new_issue_data = json_encode($push_data);
            $len = @file_put_contents($push_file, $new_issue_data);

            //新的一期，需要重置的数值
            $arg_arr['add_days'] = " +{$i} days";
            $arg_arr['begin_create_time'] = $this->lottery_type_obj[$lottery_type]['first_issue_time'];

            //幸运飞艇和重庆时时彩的第一期字串为"年月日001"
            if (in_array($lottery_type, ['4','5'])) {
                $arg_arr['begin_create_issue'] = date('Ymd', strtotime('today ' . $arg_arr['add_days'])) . '001';
            }

            //幸运28和加拿大28和北京pk10
            elseif (in_array($lottery_type, ['1', '2', '3'])) {
                $arg_arr['begin_create_issue'] = $end_issue_item['issue'] + 1;
            }

            //自主彩种1：三分彩
            elseif ($lottery_type == '6') {
                $arg_arr['begin_create_issue'] = date('Ymd', strtotime('today ' . $arg_arr['add_days'])) . '01001';
            }

            //自主彩种3：急速六合彩
            elseif ($lottery_type == '8') {
                $arg_arr['begin_create_issue'] = date('Ymd', strtotime('today ' . $arg_arr['add_days'])) . '03001';
            }

            //自主彩种2：急速赛车
            elseif ($lottery_type == '9') {
                $arg_arr['begin_create_issue'] = date('Ymd', strtotime('today ' . $arg_arr['add_days'])) . '02001';
            }

            //自主彩种4：牛牛
            elseif ($lottery_type == '10') {
                $arg_arr['begin_create_issue'] = date('Ymd', strtotime('today ' . $arg_arr['add_days'])) . '04001';
            }

            //自主彩种5：分分彩
            elseif ($lottery_type == '11') {
                $arg_arr['begin_create_issue'] = date('Ymd', strtotime('today ' . $arg_arr['add_days'])) . '050001';
            }

            //自主彩种6：骰宝
            elseif ($lottery_type == '13') {
                $arg_arr['begin_create_issue'] = date('Ymd', strtotime('today ' . $arg_arr['add_days'])) . '06001';
            }

            //自主彩种7：分分PK10
            elseif ($lottery_type == '14') {
                $arg_arr['begin_create_issue'] = date('Ymd', strtotime('today ' . $arg_arr['add_days'])) . '070001';
            }
        }
        //重写redis
        $redis = initCacheRedis();
        // $first_key = 'QiHaoFirst' . $lottery_type;
        $last_key = 'QiHaoLast' . $lottery_type;
        $qihao_key = 'QiHaoIds' . $lottery_type;
        // $redis->del($first_key);
        $redis->del($last_key);
        $redis->del($qihao_key);

        //最后一期
        $last = json_encode(end($reset_reids_issue_arr));
        $redis->set($last_key,$last);
        //第一期
        // $first = json_encode(reset($reset_reids_issue_arr));
        // $redis->set($first_key,$first);

        //一天的期号
        foreach ($reset_reids_issue_arr as $v2){
            $key = json_encode($v2);
            //将对应的键存入队列中
            $redis->RPUSH($qihao_key, $key);
        }
        deinitCacheRedis($redis);
        $this->retArr['msg'] = 'success';
        $this->returnCurl();
    }


    /**
     * 计算并创建期号和对应时间的数组
     * @param array $arg_arr 生成期号的条件数组，包含的键有:lottery_type, begin_create_time, begin_create_issue
     * @return array 包含期号对应时间的二维数组
     * 2017-10-24
     */
    public function createIssue(array $arg_arr = [])
    {
        $lottery_type = $arg_arr['lottery_type'];
        $begin_create_time = $arg_arr['begin_create_time'];
        $begin_create_issue = $arg_arr['begin_create_issue'];

        //传入的时间（上述期号对应的时间）
        $begin_time = strtotime('today ' . $begin_create_time . $add_days);
        //传入的期号

        $issue_str = $begin_create_issue;

        //分别处理加拿大28和幸运飞艇跨天期号生成的情况
        $day_str = '';
        if ($lottery_type == '3') {
            if ($begin_time > strtotime('today 19:00:00')) {
                $day_str = ' +1 days ';
            }
            //加拿大彩种不生成一整年的期号，只生成一天
            //$add_days = '';
        }
        //幸运飞艇
        elseif ($lottery_type == '4') {
            if ($begin_time > strtotime('today 04:03:45')) {
                $day_str = ' +1 days ';
            }
        }
        //自主彩种
        elseif (in_array($lottery_type, ['6', '8', '9', '10','11','13','14'])) {
            $day_str = ' +1 days ';
        }

        $switch_obj = [
            //幸运28
            '1' => [
                'last_timestamp' => strtotime('today 23:55:00 ' . $add_days),
                'space_time' => 300,
            ],
            //北京pk10
            '2' => [
                'last_timestamp' => strtotime('today 23:50:40 ' . $add_days),
                'space_time' => 1200,
            ],
            //加拿大28
            '3' => [
                'last_timestamp' => strtotime('today 20:00:00 ' . $day_str . $add_days),
                'space_time' => 210,
            ],
            //幸运飞艇
            '4' => [
                'last_timestamp' => strtotime('today 04:03:45 ' . $day_str . $add_days),
                'space_time' => 300,
            ],
            //重庆时时彩
            '5' => [
                'last_timestamp' => strtotime('today 23:50:45 ' . $day_str . $add_days),
                'space_time' => 1200,
            ],
            //三分彩
            '6' => [
                'last_timestamp' => strtotime('today 00:00:00 ' . $day_str . $add_days),
                'space_time' => 180,
            ],
            //急速六合彩
            '8' => [
                'last_timestamp' => strtotime('today 00:00:00 ' . $day_str . $add_days),
                'space_time' => 300,
            ],
            //急速赛车
            '9' => [
                'last_timestamp' => strtotime('today 00:00:00 ' . $day_str . $add_days),
                'space_time' => 180,
            ],
            //牛牛
            '10' => [
                'last_timestamp' => strtotime('today 00:00:00 ' . $day_str . $add_days),
                'space_time' => 300,
            ],

            //分分彩
            '11' => [
                'last_timestamp' => strtotime('today 00:00:00 ' . $day_str . $add_days),
                'space_time' => 60,
            ],

            //骰宝
            '13' => [
                'last_timestamp' => strtotime('today 00:00:00 ' . $day_str . $add_days),
                'space_time' => 300,
            ],
            //分分PK10
            '14' => [
                'last_timestamp' => strtotime('today 00:00:00 ' . $day_str . $add_days),
                'space_time' => 60,
            ],
        ];

        //缓存期号数组
        $new_issue_arr = [];

        //根据彩种取截止的最后一期时间
        $last_timestamp = $switch_obj[$lottery_type]['last_timestamp'];

        //彩种间隔时间
        $space_time = $switch_obj[$lottery_type]['space_time'];

        //重庆时时彩--计算并生成期号时间数组
        if ($lottery_type == '5') {
            while ($begin_time <= $last_timestamp) {
                $tmp_hour = date('H', $begin_time);
                $new_issue_arr[] = [
                    'issue' => $issue_str++ . '',
                    'date' => $begin_time,
                    '__ymd__' => date('Y-m-d H:i:s', $begin_time),
                ];
                //超过59期，则从001开始重新计算
                if (substr($issue_str, -3) > 59) {
                    $issue_str = date('Ymd', $begin_time) . '001';
                }

                //3点时，间隔时间加4个小时，直接跨度到7点30分45秒
                if ($tmp_hour == 3) {
                    $space_time += 4 * 3600 + 25;

                    //剩余时间段，间隔时间为20分钟
                } else {
                    $space_time = 1200;
                }
                $begin_time += $space_time;
            }
        }

        //其他彩种--计算并生成期号时间数组
        else {
            while ($begin_time <= $last_timestamp) {
                $new_issue_arr[] = [
                    'issue' => $issue_str++ . '',
                    'date' => $begin_time,
                    '__ymd__' => date('Y-m-d H:i:s', $begin_time),
                ];
                $begin_time += $space_time;
            }
        }
        return $new_issue_arr;
    }
}
