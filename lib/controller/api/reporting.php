<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

/**
 * desc: APP 报表接口类
 */
class ReportingAction extends Action
{
    public function __construct(){
        parent::__construct();
    }

    /**
     * 接口计算本月统计数据
     * 2018-01-20 update
     */
    public function apiCalculateMonthData()
    {
        $program_run_begin_time = microtime(true);

        $reporting_model = D('Reporting');

        //每月1号到当前天，把当月现有天数分割成每5天一个区段
        $range_arr = range(1, date('d'));
        $new_slice_arr = array_chunk($range_arr, 5);

        //缓存分区段查询结果数组
        $trade_log_arr = [];

        foreach ($new_slice_arr as $each_item) {
            $tmp_start_date = $each_item[0];
            $tmp_end_date = end($each_item);
            $tmp_start_time = strtotime(date("Y-m-{$tmp_start_date} 00:00:00"));
            $tmp_end_time = strtotime(date("Y-m-{$tmp_end_date} 23:59:59"));

            $trade_log_arr[] = $reporting_model->getTradeLogB($tmp_start_time, $tmp_end_time);
        }

        // //本月1号为开始时间
        // $start_time = strtotime( date('Y-m-01 00:00:00') );

        // //当前时间为结束时间
        // $end_time = strtotime( date('Y-m-d H:i:s') );

        // $trade_log = $reporting_model->getTradeLogB($start_time, $end_time);

        //将每月分段的统计数据相加，得出总数据
        $new_trade_log = $this->addUpTradeLogArrData($trade_log_arr);

        //交易类型
        $trade = D('account')->getTrade();
        $month_result_data = $reporting_model->get_arr_diff($new_trade_log, $trade['tranTypeIds']);

        lg('dr_chunk_every_month', var_export([
            '月份' => date('m'),
            '每月数据' => $trade_log_arr,
            'new_trade_log' => $new_trade_log,
            'month_result_data' => $month_result_data,
        ], true));

        $json_obj = [
            'data' => $month_result_data,
            'write_time' => time(),
        ];

        $json_data = json_encode($json_obj, JSON_UNESCAPED_UNICODE);


        //将计算结果写入文件
        $file_dir = 'json_files/daily_report/';
        if (! is_dir($file_dir)) {
            mkdirs(S_ROOT . $file_dir);
        }


        try {
            $len = @file_put_contents($file_dir . 'daily_report_result.json', $json_data);
            $program_run_end_time = microtime(true);
            $run_time = $program_run_end_time - $program_run_begin_time;
            lg('dr_month_data_write_done', var_export(['json_data'=>$json_data, 'run_time'=> $run_time], true));
            echo json_encode(['msg'=>"file length:{$len}", 'code'=>0]);
        } catch (Exception $e) {
            $program_run_end_time = microtime(true);
            $run_time = $program_run_end_time - $program_run_begin_time;
            lg('dr_write_error', var_export(['json_data'=>$json_data, 'run_time'=> $run_time], true));
            echo json_encode(['msg'=>'WRITE FILE FAILURE', 'code'=>100401]);
        }
    }

    /**
     * 将分段的月统计数据合并为一个数组，供统计显示使用
     * 2018-01-22
     */
    public function addUpTradeLogArrData($trade_log_arr)
    {
        $tmp_all_arr = [];
        foreach ($trade_log_arr as $each_log) {
            foreach ($each_log as $each_value) {
                $tmp_key = $each_value['type'];
                if ($tmp_all_arr[$tmp_key]['type'] === null) {
                    $tmp_all_arr[$tmp_key]['type'] = $tmp_key;
                }
                if ($tmp_all_arr[$tmp_key]['total_money'] === null) {
                    $tmp_all_arr[$tmp_key]['total_money'] = 0;
                }
                if ($tmp_all_arr[$tmp_key]['cnt'] === null) {
                    $tmp_all_arr[$tmp_key]['cnt'] = 0;
                }

                $tmp_all_arr[$tmp_key]['total_money'] += $each_value['total_money'];
                $tmp_all_arr[$tmp_key]['cnt'] += $each_value['cnt'];
            }
        }

        return $tmp_all_arr;
    }

    
}
