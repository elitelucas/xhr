<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class CalculateIssueAction extends Action{


    //文件目录路径（暂时放在项目根目录）
    public $file_path = '';

    public function __construct() {
        parent::__construct();
    }

    /**
     * 各个彩种对应的彩种id
     *         1  幸运28      
     *         2  北京PK10    
     *         3  加拿大28    
     *         4  幸运飞艇    
     * 定时接口，供crontab任务调用
     * /?m=api&c=calculateissue&a=do_compare&lottery_type=1
     * @param number $lottery_type 彩种id
     */
    public function do_compare () {
        $lottery_type = intval($_REQUEST['lottery_type']);
        if (! $lottery_type) {
            echo json_encode(['code' => 1001, 'msg' => 'lost lottery type' ]);
            exit;
        }
        $sql = '';
        switch ($lottery_type) {
            //幸运28和加拿大28
            case 1:
            case 3:
                $sql = "SELECT issue FROM un_open_award WHERE lottery_type = {$lottery_type} ORDER BY issue DESC LIMIT 1 ";
                break;
            //北京pk10
            case 2:
                $sql = 'SELECT qihao AS issue FROM un_bjpk10 ORDER BY qihao DESC LIMIT 1 ';
                break;
            //幸运飞艇
            case 4:
                $sql = 'SELECT qihao AS issue FROM un_xyft ORDER BY qihao DESC LIMIT 1 ';
                break;
        }
        $award_data = $this->db->getone($sql);
        $last_issue = $award_data['issue'];

        $result = $this->cal_issue_time($last_issue, $lottery_type);
        lg('issue_do_compare.txt', var_export(['lottery_type'=>$lottery_type, 'sql'=>$sql, 'award_data'=>$award_data, 'cal_issue_time_result' => $result], true) . "\n");
        echo json_encode($result);
        exit;
    }

    /**
     * 幸运28、北京pk10每天8点检测
     * 幸运飞艇每天12点检测
     * 以上时间点，这几个彩种都结束了一天的最后一期
     */
    public function cal_issue_time ($last_issue = null, $lottery_type = null, $current_date = 'today') {

        if ($lottery_type == '3') {
            return [
                'code' => 104,
                'msg' =>  'jnd28 do not deal',
            ];
        }

        if ($current_date === 'today') {
            $current_date = date('Y-m-d');
        }

        $lottery_type_obj = [
            //幸运28
            '1' => [
                'first_issue_hour' => '9',
                'first_issue_min' => '05',
                'last_issue_hour' => '23',
                'last_issue_min' => '55',
                'interval' => '300',
                'lottery_file_name' => 'xy28_qihao.json',
                'issue_count' => '179',
            ],

            //北京PK10
            '2' => [
                'first_issue_hour' => '9',
                'first_issue_min' => '07',
                'last_issue_hour' => '23',
                'last_issue_min' => '57',
                'interval' => '300',
                'lottery_file_name' => 'bjpk10_qihao.json',
                'issue_count' => '179',
            ],

            //加拿大28
            '3' => [
                'first_issue_hour' => '20',
                'first_issue_min' => '00',
                'last_issue_hour' => '19',
                'last_issue_min' => '00',
                'interval' => '210',
                'lottery_file_name' => 'jnd28_qihao.json',
                'issue_count' => '375',
            ],

            //幸运飞艇
            '4' => [
                'first_issue_hour' => '13',
                'first_issue_min' => '09',
                'last_issue_hour' => '04',
                'last_issue_min' => '04',
                'interval' => '300',
                'lottery_file_name' => 'xyft_qihao.json',
                'issue_count' => '180',
            ],
        ];

        //正在使用的期号文件
        $using_file = $this->file_path . $lottery_type_obj[$lottery_type]['lottery_file_name'];

        //文件不存在则中断后续逻辑
        if (! file_exists($using_file)) {
            return [
                'code' => 101,
                'msg' =>  $using_file . ' is not exists',
            ];
        }

        //原始文件内容
        $file_data = @file_get_contents($using_file);
        $file_json_arr = json_decode($file_data, true);

        //从文本中读取的原始期号对比数据
        $compare_push_arr = json_decode($file_json_arr['txt'], true)['list'];

        //取当天日期
        $this_date = date('Y-m-d');

        //加拿大和幸运飞艇的最后一期在隔天，需要加一天
        if ($lottery_type == '3' || $lottery_type == '4') {
            $time_str_tail = ' +1 days';
        } else {
            $time_str_tail = '';
        }


        //首期开奖时间戳
        $lottery_btime = strtotime("{$this_date} {$lottery_type_obj[$lottery_type]['first_issue_hour']}:{$lottery_type_obj[$lottery_type]['first_issue_min']}:00");

        //最后一期开奖时间戳
        $lottery_etime = strtotime("{$this_date} {$lottery_type_obj[$lottery_type]['last_issue_hour']}:{$lottery_type_obj[$lottery_type]['last_issue_min']}:00 {$time_str_tail}");

        // $last_issue = 844066;       //xy28模拟期号
        // $last_issue = 638638;       //北京pk10模拟期号

        $issue_time_arr = [];
        $interval_time = $lottery_type_obj[$lottery_type]['interval'];

        //幸运飞艇(lottery_type:4)的期号前面有年月日，不是纯粹的累加1，年月日位会变动
        if ($lottery_type == '4') {
            $tmp_xyft_issue = floatval(date('Ymd') . '001');
            while ($lottery_btime <= $lottery_etime) {
                $issue_time_arr[] = [
                    'issue' => ($tmp_xyft_issue++) . '' ,
                    'date' => $lottery_btime,
                ];
                $lottery_btime += $interval_time;
            }
        }
        //加拿大28处理
        elseif ($lottery_type == '3') {
            $last_issue = $compare_push_arr[0]['last_issue'];
            $lottery_btime = $compare_push_arr[0]['date'];
            while ($lottery_btime <= $lottery_etime) {
                $issue_time_arr[] = [
                    'issue' => ($last_issue++) . '' ,
                    'date' => $lottery_btime,
                ];
                $lottery_btime += $interval_time;
            }
        }
        //除了幸运飞艇、加拿大28以外的其他彩种处理
        else {
            while ($lottery_btime <= $lottery_etime) {
                $issue_time_arr[] = [
                    'issue' => (++$last_issue) . '' ,
                    'date' => $lottery_btime,
                ];
                $lottery_btime += $interval_time;
            }
        }

        //覆盖之前先备份原有文件
        lg('before_write_date', $file_data);

        //覆盖推奖平台的期号时间
        $cover_issue_time_arr = [];
        foreach ($issue_time_arr as $issue_k => $issue_v) {
            $cover_issue_time_arr[$issue_k] = [
                //期号取推奖平台推送的期号，时间取本地计算的时间
                'issue' => $compare_push_arr[$issue_k]['issue'],
                'date' => $issue_v['date'],
            ];
        }
        $file_json_arr['txt'] = json_encode(['list'=>$cover_issue_time_arr]);
        @file_put_contents($using_file , json_encode($file_json_arr));


        //写入自己算期号的文件中
        $file_json_arr['txt'] = json_encode(['list'=>$issue_time_arr]);
        @file_put_contents($using_file . '.compare' , json_encode($file_json_arr));

        if ($compare_push_arr != $issue_time_arr) {

            //如果文件修改时间不是当天，则判定为没有成功推送期号到平台上
            if (time() - filemtime($using_file) > 10 * 3600 ) {
                //添加提示音，类别为'1'
                $add_music_return = $this->add_music_tips($lottery_type, '1');

                //当没有推送期号时，则备份原来文件
                // $tmp_file_name = $using_file . '.' . date('Y_m_d') . '.bak';
                lg('bak_outdate_push_issue[' . $lottery_type_obj[$lottery_type]['lottery_file_name'] . '].txt', $file_data);

                //使用自己生成的期号替换原来期号文件
                @file_put_contents($using_file, json_encode($file_json_arr));

                return [
                    'code' => 102,
                    'msg' =>  $using_file . ' not modify since yesterday',
                    'add_music_return' => $add_music_return,
                ];
            } else {
                //添加提示音，类别为'2'
                $add_music_return = $this->add_music_tips($lottery_type, '2');

                return [
                    'code' => 103,
                    'msg' =>  $using_file . ' != CREATE_FILE',
                    'add_music_return' => $add_music_return,
                ];
            }
        }

        return [
            'code' => 0,
            'msg' =>  $using_file . ' == CREATE_FILE',
        ];
    }

    /**
     * 提示音入库
     * @param number $lottery_type 彩种类型
     * @param number $tips_type 消息提示类型
     */
    public function add_music_tips ($lottery_type = null, $tips_type = 2) {
        if ($_REQUEST['lottery_type']) {
            $lottery_type = $_REQUEST['lottery_type'];
        }
        if ($_REQUEST['tips_type']) {
            $tips_type = $_REQUEST['tips_type'];
        }

        $tips_type_obj = [
            '1' => [
                'tip' => 'It is detected that the prize-grabbing platform has not pushed the issue number',
                'msg' => 'The list of lottery numbers on the day is not pushed',
            ],
            '2' => [
                'tip' => 'It is detected that the platform calculation issue number is different from the prize-grabbing platform issue number',
                'msg' => 'The list of lottery numbers on the day is different',
            ],
        ];
        $data = [
            'record_id' => $lottery_type . '_push_award_fail',
            'type' => 3,
            'tip' =>  $tips_type_obj[$tips_type]['tip'],
            'url' => '?m=admin&c=compareissue&a=list_diff_issue&lottery_type=' . $lottery_type,
            'time' => time(),
            'uids' => '',
            'msg' => $tips_type_obj[$tips_type]['msg'],
            'remark' => date('Y-m-d H:i:s') . 'Prompt type: ' . $tips_type,
        ];
        $last_id = $this->db->insert('un_music_tips', $data);

        //返回json
        if ($last_id) {
            return
                [
                    'code' => 0,
                    'id' => $last_id,
                    'msg' => 'insert success',
                ]
            ;
        } else {
            return
                [
                    'code' => 5000,
                    'id' => 0,
                    'msg' => 'insert fail',
                ]
            ;
        }
    }


}