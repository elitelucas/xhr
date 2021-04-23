<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

/**
 * 问路接口类
 */
class AskloadAction extends Action
{
    public $small       = [];
    public $big         = [];
    public $odd         = [];
    public $even        = [];
    public $top_big     = [];
    public $top_small   = [];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 问路28类
     * 2018-04-19
     */
    public function createLoad28()
    {

        // //验证token
        // $this->checkAuth();

        $this->small = range(0, 13);
        $this->big = range(14, 27);
        $this->odd = range(1, 27, 2);
        $this->even = range(0, 26, 2);

        $this->top_big = range(22, 27);
        $this->top_small = range(0, 5);

        //判断 ask_type 值： 1为大小问路，2为单双问路，不传默认取大小问路
        $ask_type = intval($_REQUEST['ask_type']) ? : 1;

        //大小问路
        if ($ask_type == '1') {
            $compare_arr = $this->small;
        }
        //单双问路
        else {
            $compare_arr = $this->odd;
        }

        $today_begin_time = strtotime('today 00:00:00');

        //彩种
        $lottery_type = intval($_REQUEST['lottery_type']) ? : 1;
        $query_sql = "SELECT open_result AS lottery_number FROM un_open_award
            WHERE lottery_type = {$lottery_type} AND open_time >= {$today_begin_time}";
        $data = $this->db->getAll($query_sql);

        $datas = [];
        foreach($data as $v){

            //判断极大极小，并统计
            if (in_array($v['lottery_number'], $this->top_big)) {
                $top_value = '2';
            } elseif (in_array($v['lottery_number'], $this->top_small)) {
                $top_value = '1';
            } else {
                $top_value = '0';
            }

            if (in_array($v['lottery_number'], $compare_arr)) {
                $datas[] = ['blue', $top_value];
            }
            else {
                $datas[] = ['red', $top_value];
            }
        }
        //创建问路数据对象
        $final_data = $this->_make_data($datas);

        $rt_data = [
            'kai-jiang' => $data,
            'data' => $final_data,
        ];

        ErrorCode::successResponse($rt_data);
    }

    /**
     * 问路PK10类
     * 2018-04-19
     */
    public function createLoadPK10()
    {

        // //验证token
        // $this->checkAuth();

        //判断 pos_key 值： 0为冠亚和问路，1为第1名问路，2为第2名问路，以此类推，不传默认取冠亚和问路
        $pos_key = intval($_REQUEST['pos_key']) ? : 0;

        //冠亚和的大小单双数组
        if ($pos_key == '0') {
            $this->small = range(3, 11);
            $this->big = range(12, 19);
            $this->odd = range(3, 19, 2);
            $this->even = range(4, 18, 2);
        }
        //赛车位置的大小单双数组
        else {
            $this->small = range(1, 5);
            $this->big = range(6, 10);
            $this->odd = range(1, 9, 2);
            $this->even = range(2, 10, 2);
        }

        //判断 ask_type 值： 1为大小问路，2为单双问路，不传默认取大小问路
        $ask_type = intval($_REQUEST['ask_type']) ? : 1;

        //大小问路
        if ($ask_type == '1') {
            $compare_arr = $this->small;
        }
        //单双问路
        else {
            $compare_arr = $this->odd;
        }

        $today_begin_datetime = date('Y-m-d 00:00:00');

        //彩种
        $lottery_type = intval($_REQUEST['lottery_type']) ? : 2;

        //北京PK10 和 急速赛车
        if ($lottery_type == 2 || $lottery_type == 9) {
            $query_sql = "SELECT kaijianghaoma AS lottery_number FROM un_bjpk10
                WHERE lottery_type = {$lottery_type} AND kaijiangshijian >= '{$today_begin_datetime}'";
        }
        //幸运飞艇
        elseif ($lottery_type == 4) {
            $query_sql = "SELECT kaijianghaoma AS lottery_number FROM un_xyft
                WHERE kaijiangshijian >= '{$today_begin_datetime}'";
        }
        //分分PK10
        elseif ($lottery_type == 14) {
            $today_begin_datetime = strtotime($today_begin_datetime);
            $query_sql = "SELECT lottery_result AS lottery_number FROM un_ffpk10
                WHERE lottery_time >= '{$today_begin_datetime}'";
        }
        else {
            $err_code = 500005;
            $err_msg = 'lottery_type error';
            ErrorCode::errorResponse($err_code, $err_msg);
        }

        $data = $this->db->getAll($query_sql);

        $datas = [];
        foreach ($data as $k => $v) {
            $tmp_number_arr = explode(',', $v['lottery_number']);

            //将数组元素转换为整形
            $tmp_number_arr = array_map('intval', $tmp_number_arr);

            //将冠亚和值添加到数组的开头
            array_unshift($tmp_number_arr, ($tmp_number_arr[0] + $tmp_number_arr[1]));

            //按前端传的 pos_key 值，来取相对应的问路数据
            if (in_array($tmp_number_arr[$pos_key], $compare_arr)) {
                $datas[] = ['blue'];
            }
            else {
                $datas[] = ['red'];
            }
        }

        //创建问路数据对象
        $final_data = $this->_make_data($datas);

        $rt_data = [
            'data' => $final_data,
        ];

        ErrorCode::successResponse($rt_data);
    }

    /**
     * 问路时时彩类
     * 2018-04-19
     */
    public function createLoadSSC()
    {

        // //验证token
        // $this->checkAuth();

        //判断 pos_key 值： 0为总和值问路，1为第1球问路，2为第2球问路，以此类推，不传默认取总和值问路
        $pos_key = intval($_REQUEST['pos_key']) ? : 0;

        //总和值的大小单双数组
        if ($pos_key == '0') {
            $this->small = range(0, 22);
            $this->big = range(23, 45);
            $this->odd = range(1, 45, 2);
            $this->even = range(0, 44, 2);
        }
        //5个位置的大小单双数组
        else {
            $this->small = range(0, 4);
            $this->big = range(5, 9);
            $this->odd = range(1, 9, 2);
            $this->even = range(0, 8, 2);
        }

        //判断 ask_type 值： 1为大小问路，2为单双问路，不传默认取大小问路
        $ask_type = intval($_REQUEST['ask_type']) ? : 1;

        //大小问路
        if ($ask_type == '1') {
            $compare_arr = $this->small;
        }
        //单双问路
        else {
            $compare_arr = $this->odd;
        }

        $today_begin_time = strtotime('today 00:00:00');

        //彩种
        $lottery_type = intval($_REQUEST['lottery_type']) ? : 5;

        //时时彩 、三分彩 、分分彩
        $query_sql = "SELECT lottery_result AS lottery_number FROM un_ssc
            WHERE lottery_type = {$lottery_type} AND lottery_time >= {$today_begin_time}";

        $data = $this->db->getAll($query_sql);

        $datas = [];

        foreach ($data as $k => $v) {
            $tmp_number_arr = explode(',', $v['lottery_number']);

            //将数组元素转换为整形
            $tmp_number_arr = array_map('intval', $tmp_number_arr);

            //将总和值添加到数组的开头
            array_unshift($tmp_number_arr, array_sum($tmp_number_arr));

            //按前端传的 pos_key 值，来取相对应的问路数据
            if (in_array($tmp_number_arr[$pos_key], $compare_arr)) {
                $datas[] = ['blue'];
            }
            else {
                $datas[] = ['red'];
            }
        }

        //创建问路数据对象
        $final_data = $this->_make_data($datas);

        $rt_data = [
            'data' => $final_data,
        ];

        ErrorCode::successResponse($rt_data);
    }

    /**
     * 问路百人牛牛类
     * 2018-04-19
     */
    public function createLoadNN()
    {

        // //验证token
        // $this->checkAuth();

        //判断 pos_key 值： 0为总和值问路，1为第1张牌问路，2为第2张牌问路，以此类推，不传默认取总和值问路
        $pos_key = intval($_REQUEST['pos_key']) ? : 0;

        //总和值的大小单双数组
        if ($pos_key == '0') {
            $this->small = range(6, 34);    //4个A，一个2，最小值为 1 * 4 + 2 = 6
            $this->big = range(35, 64);     //4个K，一个Q，最大值为 13 * 4 + 12 = 64
            $this->odd = range(7, 63, 2);
            $this->even = range(6, 64, 2);
        }
        //5个位置的大小单双数组
        else {
            $this->small = range(1, 6);
            $this->big = range(7, 13);
            $this->odd = range(1, 13, 2);
            $this->even = range(2, 12, 2);
        }

        //判断 ask_type 值： 1为大小问路，2为单双问路，不传默认取大小问路
        $ask_type = intval($_REQUEST['ask_type']) ? : 1;

        //大小问路
        if ($ask_type == '1') {
            $compare_arr = $this->small;
        }
        //单双问路
        else {
            $compare_arr = $this->odd;
        }

        $today_begin_time = strtotime('today 00:00:00');

        //彩种
        $lottery_type = intval($_REQUEST['lottery_type']) ? : 10;

        //牛牛
        $query_sql = "SELECT lottery_result AS lottery_number FROM un_nn
            WHERE lottery_type = {$lottery_type} AND lottery_time >= {$today_begin_time}";

        $data = $this->db->getAll($query_sql);

        $datas = [];

        //牛牛开奖结果数据模式
        $info_mode = 1;

        foreach ($data as $k => $v) {

            $tmp_lottery_info = getShengNiuNiu($v['lottery_number'], $info_mode);

            //判断获胜方数组键，用于取获胜一方的牌面信息
            if ($tmp_lottery_info['sheng'] == '蓝方胜') {
                $win_key = 'blue';
            } else {
                $win_key = 'red';
            }

            $tmp_number_arr = $tmp_lottery_info[$win_key]['pai'];

            //将5张牌总和值添加到数组的开头
            array_unshift($tmp_number_arr, array_sum($tmp_number_arr));

            //按前端传的 pos_key 值，来取相对应的问路数据
            if (in_array($tmp_number_arr[$pos_key], $compare_arr)) {
                $datas[] = ['blue'];
            }
            else {
                $datas[] = ['red'];
            }
        }

        //创建问路数据对象
        $final_data = $this->_make_data($datas);

        $rt_data = [
            'data' => $final_data,
        ];

        ErrorCode::successResponse($rt_data);
    }


    /**
     * 问路六合彩类
     * 2018-04-20
     */
    public function createLoadLHC()
    {

        // //验证token
        // $this->checkAuth();

        //判断 pos_key 值： 0为总和值问路，1为第1球问路，2为第2球问路，以此类推，不传默认取总和值问路
        $pos_key = intval($_REQUEST['pos_key']) ? : 0;

        //总和值的大小单双数组
        if ($pos_key == '0') {
            $this->small = range(28, 174);     //1+2+3+4+5+6+7，最小值为 28
            $this->big = range(175, 322);      //49+48+47+46+45+44+43，最大值为 322
            $this->odd = range(29, 321, 2);
            $this->even = range(28, 322, 2);
        }
        //7个位置的大小单双数组
        else {
            $this->small = range(1, 24);
            $this->big = range(25, 49);
            $this->odd = range(1, 49, 2);
            $this->even = range(2, 48, 2);
        }

        //判断 ask_type 值： 1为大小问路，2为单双问路，不传默认取大小问路
        $ask_type = intval($_REQUEST['ask_type']) ? : 1;

        //大小问路
        if ($ask_type == '1') {
            $compare_arr = $this->small;
        }
        //单双问路
        else {
            $compare_arr = $this->odd;
        }


        //彩种，不传则默认为急速六合彩，lottery_type为8
        $lottery_type = intval($_REQUEST['lottery_type']) ? : 8;

        if ($lottery_type == 7) {
            //香港六合彩为低频彩种，取当前年份一年的数据
            $this_year_begin_time = strtotime(date('Y') . '-01-01 00:00:00');
            $query_sql = "SELECT lottery_result AS lottery_number FROM un_lhc
                WHERE lottery_type = {$lottery_type} AND lottery_time >= {$this_year_begin_time}";
        }
        else {
            //急速六合彩，取当天一天的数据
            $today_begin_time = strtotime('today 00:00:00');
            $query_sql = "SELECT lottery_result AS lottery_number FROM un_lhc
                WHERE lottery_type = {$lottery_type} AND lottery_time >= {$today_begin_time}";
        }

        $data = $this->db->getAll($query_sql);

        $datas = [];

        foreach ($data as $k => $v) {

            $tmp_number_arr = explode(',', $v['lottery_number']);

            //将数组元素转换为整形
            $tmp_number_arr = array_map('intval', $tmp_number_arr);

            //将6个正码和1个特码的总和值添加到数组的开头
            array_unshift($tmp_number_arr, array_sum($tmp_number_arr));

            //按前端传的 pos_key 值，来取相对应的问路数据
            if (in_array($tmp_number_arr[$pos_key], $compare_arr)) {
                $datas[] = ['blue'];
            }
            else {
                $datas[] = ['red'];
            }
        }

        //创建问路数据对象
        $final_data = $this->_make_data($datas);

        $rt_data = [
            'data' => $final_data,
        ];

        ErrorCode::successResponse($rt_data);
    }

    /**
     * 问路骰宝类
     * 2018-06-29 update
     */
    public function createLoadSB()
    {

        // //验证token
        // $this->checkAuth();

        //判断 pos_key 值： 0为3骰总和值问路，1为第1骰问路，2为第2骰问路，3为第3骰问路，不传默认取总和值问路
        $pos_key = intval($_REQUEST['pos_key']) ? : 0;

        //总和值的大小单双数组
        if ($pos_key == '0') {
            $this->small = range(3, 10);
            $this->big = range(11, 18);
            $this->odd = range(3, 17, 2);
            $this->even = range(4, 18, 2);
        }
        //单个位置的大小单双数组
        else {
            $this->small = range(0, 3);
            $this->big = range(4, 6);
            $this->odd = range(1, 5, 2);
            $this->even = range(2, 6, 2);
        }

        //判断 ask_type 值： 1为大小问路，2为单双问路，不传默认取大小问路
        $ask_type = intval($_REQUEST['ask_type']) ? : 1;

        //大小问路
        if ($ask_type == '1') {
            $compare_arr = $this->small;
        }
        //单双问路
        else {
            $compare_arr = $this->odd;
        }

        $today_begin_time = strtotime('today 00:00:00');

        //彩种
        $lottery_type = intval($_REQUEST['lottery_type']) ? : 13;

        //时时彩 、三分彩 、分分彩
        $query_sql = "SELECT lottery_result AS lottery_number FROM un_sb
            WHERE lottery_type = {$lottery_type} AND lottery_time >= {$today_begin_time}";

        $data = $this->db->getAll($query_sql);

        $datas = [];

        foreach ($data as $k => $v) {
            $tmp_number_arr = explode(',', $v['lottery_number']);

            //将数组元素转换为整形
            $tmp_number_arr = array_map('intval', $tmp_number_arr);

            //将总和值添加到数组的开头
            array_unshift($tmp_number_arr, array_sum($tmp_number_arr));

            //按前端传的 pos_key 值，来取相对应的问路数据
            if (in_array($tmp_number_arr[$pos_key], $compare_arr)) {
                $datas[] = ['blue'];
            }
            else {
                $datas[] = ['red'];
            }
        }

        //创建问路数据对象
        $final_data = $this->_make_data($datas);

        $rt_data = [
            'data' => $final_data,
        ];

        ErrorCode::successResponse($rt_data);
    }

    /**
     * 创建问路数组数据
     * 2018-04-19
     */
    public function _make_data($datas = [])
    {
        //大路
        $big_load = make_big_load($datas, '');
        $total = count($big_load[0]);

        //缓存转换后的大路数据，蟑螂路、大眼仔、小路、珠盘路，都是基于此数据演变而来的
        $data_for_others = turn_data($big_load, $total - 1);

        //蟑螂路
        $zhang_lang_load = make_zhang_lang_load($data_for_others, '');

        //大眼仔
        $da_yan_zai_load = make_da_yan_zai_load($data_for_others, '');

        //小路
        $small_load = make_small_load($data_for_others, '');

        //珠盘路
        $zhu_pan_load = make_zhu_pan_load($data_for_others, []);

        //将大路数据空字符串的数据，转换成空数组，兼容app端
        foreach ($big_load as $each_load_key => $each_load_data) {
            $big_load[$each_load_key]  = array_map(function ($each_item) {
                //如果为空字符串，则替换为空数组
                if ($each_item == '') {
                    return [];
                }
                //如果不为空字符串，则原样返回
                else {
                    return $each_item;
                }
            }, $each_load_data);
        }

        $final_data = [
            'big_load' => $big_load,
            'small_load' => $small_load,
            'zl_load' => $zhang_lang_load,
            'dyz_load' => $da_yan_zai_load,
            'zp_load' => $zhu_pan_load,
        ];

        return $final_data;
    }

}
