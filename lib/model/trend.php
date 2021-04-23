<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

/**
 * 走势图数据模型
 * 2018-04-12
 */
class TrendModel extends CommonModel
{

    /**
     * 28类走势图
     * 2018-04-12
     */
    public function trendChart28($lottery_type, $page = 1, $day = '')
    {
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $search_time_a = strtotime($day . ' 00:00:00');
        $search_time_b = strtotime($day . ' 23:59:59');

        $where_str = " open_time >= {$search_time_a} AND open_time <= {$search_time_b} AND lottery_type = {$lottery_type} ";
        $field = 'open_result AS numbers, issue AS lottery_id, lottery_type';
        $sql = "SELECT {$field} FROM un_open_award WHERE {$where_str}
            ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";
        $data = $this->db->getAll($sql);

        $lottery_info = [];
        foreach ($data as $each_key => $each_result) {
            $tmp_content_data = explode(',', calculate_tj($each_result['numbers']));

            //转换为文字结果
            $tmp_content_data = $this->formatOpenResultFor28($tmp_content_data);

            //强制转换为整数，意味着去掉前面的"0"字符串
            $each_result['numbers'] = (int)$each_result['numbers'];
            $each_result['numbers'] = sprintf('%02d',$each_result['numbers']);

            //将开奖号码插入到数组开头
            array_unshift($tmp_content_data, $each_result['numbers']);

            $data[$each_key]['numbers'] = (string)$each_result['numbers'];

            $lottery_info[] = [
                'issue' => $each_result['lottery_id'],
                'content' => $tmp_content_data,
            ];
        }

        //统计条数
        $count_sql = "SELECT COUNT(*) FROM un_open_award WHERE {$where_str}";
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => $data,
            'lottery' => $lottery_info,
            'totalPage' => ceil($totalPage),
        ];
        return $rt_data;
    }


    /**
     * 处理28类走势图结果，转换为大小单双、组合、极值
     * 2018-04-12
     */
    public function formatOpenResultFor28($open_result_arr)
    {
        if (! is_array($open_result_arr)) {
            return false;
        }
        $hash_map = [
            0 => '大',
            1 => '小',
            2 => '单',
            3 => '双',
            4 => '大单',
            5 => '大双',
            6 => '小单',
            7 => '小双',
            8 => '极大',
            9 => '极小',
        ];
        foreach ($open_result_arr as $each_key => $each_value) {
            //如果值为"1"，则表示没有命中（参考公用方法 calculate_tj ） ，没有命中的，则将文字结果置为空
            if ($each_value == '1') {
                $hash_map[$each_key] = '';
            }
        }
        return $hash_map;
    }


    /**
     * 28类历史结果
     * 2018-04-12
     */
    public function getMoreLottery28($lottery_type, $page = 1, $day = '')
    {   
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $field = 'issue AS lottery_id, open_time AS lottery_date, spare_1 AS lottery_numbers_tmp, open_result AS lottery_numbers2';

        $search_time_a = strtotime($day . ' 00:00:00');
        $search_time_b = strtotime($day . ' 23:59:59');

        $where_str = " open_time >= {$search_time_a} AND open_time <= {$search_time_b} AND lottery_type = {$lottery_type} ";
        $sql = "SELECT {$field} FROM un_open_award WHERE {$where_str}
            ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";
        $data = $this->db->getAll($sql);

        //拼接数据
        foreach ($data as $each_key => $each_result) {

            //处理 lottery_numbers 字段
            $tmp_lottery_numbers_arr = explode('+', $each_result['lottery_numbers_tmp']);

            //去掉数字字符串前面的"0"字符
            $tmp_lottery_numbers_arr = array_map('intval', $tmp_lottery_numbers_arr);

            $tmp_lottery_numbers_arr[] = array_sum($tmp_lottery_numbers_arr);
            $data[$each_key]['lottery_numbers'] = implode(',', $tmp_lottery_numbers_arr);

            //处理 content 字段
            $tmp_content_data = explode(',', calculate_tj($each_result['lottery_numbers2']));

            //转换为文字结果
            $tmp_content_data = $this->formatOpenResultFor28($tmp_content_data);

            //将开奖号码插入到数组开头
            array_unshift($tmp_content_data, $each_result['lottery_numbers2']);

            $data[$each_key]['content'] = $tmp_content_data;

            //去掉多余变量
            unset($data[$each_key]['lottery_numbers_tmp']);
        }

        //统计条数
        $count_sql = "SELECT COUNT(*) FROM un_open_award WHERE {$where_str}";
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => [
                'list' => $data,
                'totalPage' => ceil($totalPage),
            ]
        ];
        return $rt_data;
    }

    /**
     * 时时彩类开奖走势
     * 2018-04-12
     */
    public function trendChartSSC($lottery_type, $page = 1, $day = '')
    {   
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $field = 'issue AS lottery_id, lottery_type, lottery_result AS numbers';

        $search_time_a = strtotime($day . ' 00:00:00');
        $search_time_b = strtotime($day . ' 23:59:59');

        $where_str = " lottery_time >= {$search_time_a} AND lottery_time <= {$search_time_b} AND lottery_type = {$lottery_type} ";
        $sql = "SELECT {$field} FROM un_ssc WHERE {$where_str}
            ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";
        $data = $this->db->getAll($sql);

        //统计条数
        $count_sql = "SELECT COUNT(*) FROM un_ssc WHERE {$where_str}";
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => $data,
            'totalPage' => ceil($totalPage),
        ];
        return $rt_data;
    }

    /**
     * 时时彩类历史结果
     * 2018-04-12
     */
    public function getMoreLotterySSC($lottery_type, $page = 1, $day = '')
    {   
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $field = 'issue AS lottery_id, lottery_result AS lottery_numbers, lottery_time AS lottery_date';

        $search_time_a = strtotime($day . ' 00:00:00');
        $search_time_b = strtotime($day . ' 23:59:59');

        $where_str = " lottery_time >= {$search_time_a} AND lottery_time <= {$search_time_b} AND lottery_type = {$lottery_type} ";
        $sql = "SELECT {$field} FROM un_ssc WHERE {$where_str}
            ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";
        $data = $this->db->getAll($sql);

        $workerman_model = D('workerman');


        //拼接数据
        foreach ($data as $each_key => $each_result) {

            $tmp_content_data = $workerman_model->kaijiang_result_ssc($each_result['lottery_numbers']);

            $tmp_content_data = array_filter($tmp_content_data, function ($n) {
                return preg_match('%^总和|龙|虎|和%', $n);
            });

            $tmp_content_data = array_map(function ($m) {
                return preg_replace('%总和(?:_)?%', '', $m);
            }, $tmp_content_data);

            $tmp_content_data = array_values($tmp_content_data);

            //格式化处理数据
            $tmp_content_data = $this->formatOpenResultForSSC($tmp_content_data);

            //将总和值插入到数组的开头
            array_unshift($tmp_content_data, array_sum($tmp_content_data));

            $data[$each_key]['content'] = $tmp_content_data;
        }

        //统计条数
        $count_sql = "SELECT COUNT(*) FROM un_ssc WHERE {$where_str}";
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => [
                'list' => $data,
                'totalPage' => ceil($totalPage),
            ]
        ];
        return $rt_data;
    }

    /**
     * 拼接时时彩类开奖历史结果content字段数据
     * 2018-04-12
     */
    public function formatOpenResultForSSC($target_data_arr)
    {
        if (! is_array($target_data_arr)) {
            return false;
        }
        //结果处理数据map表
        $hash_map = [
            0 => '大',
            1 => '小',
            2 => '单',
            3 => '双',
            4 => '龙',
            5 => '虎',
            6 => '和',
        ];

        foreach ($hash_map as $each_key => $each_value) {
            if (! in_array($each_value, $target_data_arr)) {
                $hash_map[$each_key] = '';
            }
        }

        return $hash_map;

    }


    /**
     * PK10类历史结果
     * 2018-04-13
     */
    public function getMoreLotteryPK10($lottery_type, $page = 1, $day = '')
    {   
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $search_time_a = $day . ' 00:00:00';
        $btime = strtotime($day . ' 00:00:00');
        $search_time_b = $day . ' 23:59:59';
        $etime = strtotime($day . ' 23:59:59');

        $field = 'qihao AS lottery_id, kaijianghaoma AS lottery_numbers, kaijiangshijian AS lottery_date';
        switch ($lottery_type) {
            //北京PK10、急速赛车
            case '2':
            case '9':
                $where_str = " kaijiangshijian >= '{$search_time_a}' AND kaijiangshijian <= '{$search_time_b}' AND lottery_type = {$lottery_type} ";
                $sql = "SELECT {$field} FROM un_bjpk10 WHERE {$where_str}
                    ORDER BY qihao DESC LIMIT {$page_start}, {$page_size}";
                $count_sql = "SELECT COUNT(*) FROM un_bjpk10 WHERE {$where_str}";
                break;
            //幸运飞艇
            case '4':
                $where_str = " kaijiangshijian >= '{$search_time_a}' AND kaijiangshijian <= '{$search_time_b}' ";
                $sql = "SELECT {$field} FROM un_xyft WHERE {$where_str}
                    ORDER BY qihao DESC LIMIT {$page_start}, {$page_size}";
                $count_sql = "SELECT COUNT(*) FROM un_xyft WHERE {$where_str}";
                break;
            //分分PK10
            case '14':
                $where_str = " lottery_time >= '{$btime}' AND lottery_time <= '{$etime}' ";
                $sql = "SELECT issue AS lottery_id, lottery_result AS lottery_numbers, lottery_time AS lottery_date FROM un_ffpk10 WHERE {$where_str}
                    ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";
                $count_sql = "SELECT COUNT(*) FROM un_ffpk10 WHERE {$where_str}";
                break;
        }

        $data = $this->db->getAll($sql);

        $workerman_model = D('workerman');


        //拼接数据
        foreach ($data as $each_key => $each_result) {
            $tmp_lottery_numbers_arr = explode(',', $each_result['lottery_numbers']);

            //拼接冠亚开奖信息content字段
            $tmp_content_data = $this->formatOpenResultForPK10($tmp_lottery_numbers_arr);
            $data[$each_key]['content'] = $tmp_content_data;

            //转换成时间戳
            if($lottery_type != 14){
                $data[$each_key]['lottery_date'] = strtotime($each_result['lottery_date']);
            }
        }

        //统计条数
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => [
                'list' => $data,
                'totalPage' => ceil($totalPage),
            ]
        ];
        return $rt_data;
    }

    /**
     * PK10类走势，使用PK10历史开奖结果接口
     * 2018-04-19
     */
    public function trendChartPK10($lottery_type, $page = 1, $day = '')
    {
        return $this->getMoreLotteryPK10($lottery_type, $page, $day);
    }

    /**
     * 拼接PK10类开奖历史结果content字段数据
     * 2018-04-13
     */
    public function formatOpenResultForPK10($lottery_numbers_arr)
    {
        
        if (! is_array($lottery_numbers_arr)) {
            return false;
        }

        $guan_ya_arr = array_slice($lottery_numbers_arr, 0, 2);

        //冠亚和
        $guan_ya_sum = array_sum($guan_ya_arr);

        //符合的结果数组
        $target_data_arr = [];

        //判断冠亚和单双
        if ($guan_ya_sum % 2 == 0) {
            $target_data_arr[] = '双';
        } else {
            $target_data_arr[] = '单';
        }

        //判断冠亚和大小
        if ($guan_ya_sum > 11) {
            $target_data_arr[] = '大';
        } else {
            $target_data_arr[] = '小';
        }

        if ($lottery_numbers_arr[0] > $lottery_numbers_arr[9]) {
            $target_data_arr[] = '龙';
        } else {
            $target_data_arr[] = '虎';
        }

        //结果处理数据map表
        $hash_map = [
            0 => '大',
            1 => '小',
            2 => '单',
            3 => '双',
            4 => '龙',
            5 => '虎',
        ];

        foreach ($hash_map as $each_key => $each_value) {
            if (! in_array($each_value, $target_data_arr)) {
                $hash_map[$each_key] = '';
            }
        }

        //将冠亚和值插入数组的开头位置
        array_unshift($hash_map, $guan_ya_sum);

        return $hash_map;
    }

    /**
     * 六合彩类开奖走势
     * 2018-04-13
     */
    public function trendChartLHC($lottery_type, $page = 1, $day = '')
    {   
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $field = 'issue AS lottery_id, lottery_type, lottery_result AS lottery_numbers, lottery_time AS lottery_date';

        //六合彩默认取一年数据
        if ($lottery_type == '7' && $day == '') {
            $this_year = date('Y');
            $search_time_a = strtotime($this_year . '-01-01 00:00:00');
            $search_time_b = strtotime($this_year . '-12-31 23:59:59');
        } else {
            $search_time_a = strtotime($day . ' 00:00:00');
            $search_time_b = strtotime($day . ' 23:59:59');
        }

        $where_str = " lottery_time >= {$search_time_a} AND lottery_time <= {$search_time_b} AND lottery_type = {$lottery_type} ";
        $sql = "SELECT {$field} FROM un_lhc WHERE {$where_str}
            ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";
        $data = $this->db->getAll($sql);

        //拼接数据
        foreach ($data as $each_key => $each_result) {
            $tmp_numbers_arr = explode(',', $each_result['lottery_numbers']);
            $data[$each_key]['numbers'] = $tmp_numbers_arr;

            //拼接走势图接口的info字段
            $tmp_info = $this->formatOpenResultForLHC($tmp_numbers_arr);

            $data[$each_key]['info'] = $tmp_info;
        }

        //统计条数
        $count_sql = "SELECT COUNT(*) FROM un_lhc WHERE {$where_str}";
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => $data,
            'totalPage' => ceil($totalPage),
        ];
        return $rt_data;
    }

    /**
     * 拼接六合彩类（走势图结果info字段，历史结果content字段）数据
     * 2018-04-13
     */
    public function formatOpenResultForLHC($lottery_numbers_arr)
    {
        
        if (! is_array($lottery_numbers_arr)) {
            return false;
        }
        $result_info = [];

        //总和值
        $lottery_sum = array_sum($lottery_numbers_arr);

        $result_info['sum'] = $lottery_sum;

        //总和大小
        if ($lottery_sum >= 175) {
            $result_info['sumBig'] = '大';
            $result_info['sumSmall'] = '';
        } else {
            $result_info['sumBig'] = '';
            $result_info['sumSmall'] = '小';
        }

        //总和单双
        if ($lottery_sum % 2 == 0) {
            $result_info['sumSingle'] = '';
            $result_info['sumDouble'] = '双';
        } else {
            $result_info['sumSingle'] = '单';
            $result_info['sumDouble'] = '';
        }

        //最后一个号码为特码
        $te_ma = end($lottery_numbers_arr);

        //定义色波（也有称为“波色”的）
        $sebo_obj = [
            '红' => [1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46, ],
            '蓝' => [3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48, ],
            '绿' => [5, 6, 11, 16, 17, 21, 22, 27, 28, 32, 33, 38, 39, 43, 44, 49, ],
        ];
        foreach ($sebo_obj as $sebo_key => $sebo_value) {
            if (in_array($te_ma, $sebo_value)) {
                $result_info['sebo'] = $sebo_key;
                break;
            }
        }

        //特码大小
        if ($te_ma == 49) {
            $result_info['tmHe'] = '和';
            $result_info['tmBig'] = '';
            $result_info['tmSmall'] = '';
        } elseif ($te_ma >= 25) {
            $result_info['tmHe'] = '';
            $result_info['tmBig'] = '大';
            $result_info['tmSmall'] = '';
        } else {
            $result_info['tmHe'] = '';
            $result_info['tmBig'] = '';
            $result_info['tmSmall'] = '小';
        }

        //特码单双
        if ($te_ma == 49) {
            $result_info['tmSingle'] = '';
            $result_info['tmDouble'] = '';
        } elseif ($te_ma % 2 == 0) {
            $result_info['tmSingle'] = '';
            $result_info['tmDouble'] = '双';
        } else {
            $result_info['tmSingle'] = '单';
            $result_info['tmDouble'] = '';
        }

        //特码转换为字符串，并将小于10的，添加0前缀
        $te_ma = ($te_ma < 10) ? '0' . ($te_ma - 0) : '' . $te_ma;

        //特码个位
        $te_ma_ge = substr($te_ma, 0, 1);

        //特码十位
        $te_ma_shi = substr($te_ma, 1);

        //特码合大小
        if ($te_ma == 49) {
            $result_info['tm_sumBig'] = '';
            $result_info['tm_sumSmall'] = '';
        } elseif ($te_ma_ge + $te_ma_shi > 6) {
            $result_info['tm_sumBig'] = '合大';
            $result_info['tm_sumSmall'] = '';
        } else {
            $result_info['tm_sumBig'] = '';
            $result_info['tm_sumSmall'] = '合小';
        }

        //特码合单双
        if ($te_ma == 49) {
            $result_info['tm_sumSingle'] = '';
            $result_info['tm_sumDouble'] = '';
        } elseif (($te_ma_ge + $te_ma_shi) % 2 == 0) {
            $result_info['tm_sumSingle'] = '';
            $result_info['tm_sumDouble'] = '合双';
        } else {
            $result_info['tm_sumSingle'] = '合单';
            $result_info['tm_sumDouble'] = '';
        }

        //家禽 or 野兽
        $jia_qin_arr = ['牛','马','羊','鸡','狗','猪'];
        $te_ma_sheng_xiao = getLhcShengxiao($te_ma);

        if (in_array($te_ma_sheng_xiao, $jia_qin_arr)) {
            $result_info['tmAnimal'] = "【{$te_ma_sheng_xiao}】家禽";
        } else {
            $result_info['tmAnimal'] = "【{$te_ma_sheng_xiao}】野兽";
        }

        return $result_info;
    }


    /**
     * 六合彩类历史结果
     * 2018-04-13
     */
    public function getMoreLotteryLHC($lottery_type, $page = 1, $day = '')
    {   
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $field = 'issue AS lottery_id, lottery_result AS lottery_numbers, lottery_time AS lottery_date';

        //六合彩默认取一年数据
        if ($lottery_type == '7' && $day == '') {
            $this_year = date('Y');
            $search_time_a = strtotime($this_year . '-01-01 00:00:00');
            $search_time_b = strtotime($this_year . '-12-31 23:59:59');
        } else {
            $search_time_a = strtotime($day . ' 00:00:00');
            $search_time_b = strtotime($day . ' 23:59:59');
        }


        $where_str = " lottery_time >= {$search_time_a} AND lottery_time <= {$search_time_b} AND lottery_type = {$lottery_type} ";
        $sql = "SELECT {$field} FROM un_lhc WHERE {$where_str}
            ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";
        $data = $this->db->getAll($sql);


        //拼接数据
        foreach ($data as $each_key => $each_result) {

            $numbers_arr = explode(',', $each_result['lottery_numbers']);

            $format_content_obj = $this->formatHistoryDataForLHC($numbers_arr);

            //将值小于10的 lottery_numbers 字段数据，添加'0'前置字串
            $numbers_arr = array_map(function ($item) {
                $item = intval($item);
                if ($item < 10) {
                    return '0' . $item;
                }
                return $item;
            }, $numbers_arr);
            $data[$each_key]['lottery_numbers'] = implode(',', $numbers_arr);
            
            
            $data[$each_key]['content'] = $format_content_obj;
        }

        //统计条数
        $count_sql = "SELECT COUNT(*) FROM un_lhc WHERE {$where_str}";
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => [
                'list' => $data,
                'totalPage' => ceil($totalPage),
            ]
        ];
        return $rt_data;
    }

    /**
     * 处理六合彩类历史结果content数据
     * 2018-04-13
     */
    public function formatHistoryDataForLHC($numbers_arr)
    {
        if (! is_array($numbers_arr)) {
            return false;
        }
        $format_content_obj = $this->formatOpenResultForLHC($numbers_arr);

        $result_info = [];

        $result_info['all-info'] = $format_content_obj;

        //[下标为0的元素]
        $result_info[] = $format_content_obj['sum'];

        //总和单双判断 [下标为1的元素]
        if ($format_content_obj['sumSingle'] != '') {
            $result_info[] = '总和单';
        } else {
            $result_info[] = '';
        }
        //[下标为2的元素]
        if ($format_content_obj['sumDouble'] != '') {
            $result_info[] = '总和双';
        } else {
            $result_info[] = '';
        }

        //总和大小判断 [下标为3的元素]
        if ($format_content_obj['sumBig'] != '') {
            $result_info[] = '总和大';
        } else {
            $result_info[] = '';
        }
        //[下标为4的元素]
        if ($format_content_obj['sumSmall'] != '') {
            $result_info[] = '总和小';
        } else {
            $result_info[] = '';
        }

        //七色波，暂不处理 [下标为5的元素]
        $result_info[] = '';

        //特码单双判断 [下标为6的元素]
        if ($format_content_obj['tmSingle'] != '') {
            $result_info[] = '单';
        } else {
            $result_info[] = '';
        }
        //[下标为7的元素]
        if ($format_content_obj['tmDouble'] != '') {
            $result_info[] = '双';
        } else {
            $result_info[] = '';
        }

        //特码大小判断 [下标为8的元素]
        if ($format_content_obj['tmBig'] != '') {
            $result_info[] = '大';
        } else {
            $result_info[] = '';
        }
        //[下标为9的元素]
        if ($format_content_obj['tmSmall'] != '') {
            $result_info[] = '小';
        } else {
            $result_info[] = '';
        }

        //用意未知 [下标为10的元素]
        $result_info[] = '';

        //特码合数单双判断 [下标为11的元素]
        if ($format_content_obj['tm_sumSingle'] != '') {
            $result_info[] = '合单';
        } else {
            $result_info[] = '';
        }
        //[下标为12的元素]
        if ($format_content_obj['tm_sumDouble'] != '') {
            $result_info[] = '合双';
        } else {
            $result_info[] = '';
        }

        //特码合数单双判断 [下标为13的元素]
        if ($format_content_obj['tm_sumBig'] != '') {
            $result_info[] = '合大';
        } else {
            $result_info[] = '';
        }
        //[下标为14的元素]
        if ($format_content_obj['tm_sumSmall'] != '') {
            $result_info[] = '合小';
        } else {
            $result_info[] = '';
        }

        //用意未知 [下标为15的元素]
        $result_info[] = '';

        //尾大，暂不处理 [下标为16的元素]
        $result_info[] = '';

        //尾小，暂不处理 [下标为17的元素]
        $result_info[] = '';

        return $result_info;
    }

    /**
     * 牛牛类历史结果
     * 2018-04-18
     */
    public function getMoreLotteryNN($lottery_type = 10, $page = 1, $day = '')
    {
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $field = 'issue AS lottery_id, lottery_result AS lottery_numbers, lottery_time AS lottery_date';

        $search_time_a = strtotime($day . ' 00:00:00');
        $search_time_b = strtotime($day . ' 23:59:59');

        $where_str = " lottery_time >= {$search_time_a} AND lottery_time <= {$search_time_b} AND lottery_type = {$lottery_type} ";
        $sql = "SELECT {$field} FROM un_nn WHERE {$where_str}
            ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";
        $data = $this->db->getAll($sql);


        //拼接数据
        foreach ($data as $each_key => $each_result) {

            $format_content_obj = $this->formatHistoryDataForNN($each_result['lottery_numbers']);

            $data[$each_key]['content'] = $format_content_obj;
            unset($data[$each_key]['lottery_numbers']);
        }

        //统计条数
        $count_sql = "SELECT COUNT(*) FROM un_nn WHERE {$where_str}";
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => [
                'list' => $data,
                'totalPage' => ceil($totalPage),
            ]
        ];
        return $rt_data;
    }

    /**
     * 处理牛牛类历史结果content数据
     * 2018-04-18
     */
    public function formatHistoryDataForNN($lottery_numbers)
    {
        $lottery_numbers_arr = explode(',', $lottery_numbers);
        $blue = array_slice($lottery_numbers_arr, 0, 5);
        $red = array_slice($lottery_numbers_arr, 5, 5);

        $mode = 1;
        $result_obj = getShengNiuNiu($lottery_numbers, $mode);

        $format_content_obj = [];

        //判断胜负
        if ($result_obj['sheng'] == '红方胜') {
            $format_content_obj['blue']['win_str'] = '负';
            $format_content_obj['red']['win_str'] = '胜';
        }
        else {
            $format_content_obj['blue']['win_str'] = '胜';
            $format_content_obj['red']['win_str'] = '负';
        }

        //牛数
        $format_content_obj['blue']['niu'] = $result_obj['blue']['lottery_niu'];
        $format_content_obj['red']['niu'] = $result_obj['red']['lottery_niu'];

        //开奖牌面对应的号码
        $format_content_obj['blue']['numbers_arr'] = $blue;
        $format_content_obj['red']['numbers_arr'] = $red;

        return $format_content_obj;
    }


    public  function getMoreLotterySB($lottery_type, $page = 1, $day = ''){
        return $this->trendChartSB($lottery_type, $page, $day);
    }

    public function trendChartSB($lottery_type, $page = 1, $day = ''){
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $field = 'issue, lottery_result AS numbers,lottery_time';

        $search_time_a = strtotime($day . ' 00:00:00');
        $search_time_b = strtotime($day . ' 23:59:59');

        $where_str = " lottery_time >= {$search_time_a} AND lottery_time <= {$search_time_b} AND lottery_type = {$lottery_type} ";
        $sql = "SELECT {$field} FROM un_sb WHERE {$where_str}
            ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";

        lg('trend_chart_sb',var_export(array(
            '$day'=>$day,
            '$where_str'=>$where_str,
            '$sql'=>$sql,
        ),1));

        $data = $this->db->getAll($sql);

        $mode = 2;
        $list = array();
        foreach ($data as $k => $v){
            $tempArr = [
                '1' => "", //总和
                '2' => "", //大
                '3' => "", //小
                '4' => "", //单
                '5' => "", //双
                '6' => "", //豹子
            ];
            $spare_2 = D('workerman')->kaijiang_result_sb($v['numbers']);
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
            $list[$k]['lottery_date'] = $v['lottery_time'];
            $list[$k]['issue'] = $v['issue'];
            $list[$k]['lottery_numbers'] = $v['numbers'];
            $list[$k]['spare_2'] = array_values($tempArr);
        }

        //统计条数
        $count_sql = "SELECT COUNT(*) FROM un_sb WHERE {$where_str}";
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => [
                'list' => $list,
                'totalPage' => ceil($totalPage),
            ],
            'totalPage' => ceil($totalPage)
        ];
        return $rt_data;
    }


    /**
     * 牛牛类开奖走势
     * 2018-04-18
     */
    public function trendChartNN($lottery_type, $page = 1, $day = '')
    {   
        $page_size = 30;
        $page_start = ($page - 1) * $page_size;

        $field = 'issue AS lottery_id, lottery_result AS numbers';

        $search_time_a = strtotime($day . ' 00:00:00');
        $search_time_b = strtotime($day . ' 23:59:59');

        $where_str = " lottery_time >= {$search_time_a} AND lottery_time <= {$search_time_b} AND lottery_type = {$lottery_type} ";
        $sql = "SELECT {$field} FROM un_nn WHERE {$where_str}
            ORDER BY issue DESC LIMIT {$page_start}, {$page_size}";
        $data = $this->db->getAll($sql);

        $mode = 2;
        foreach ($data as $each_key => $each_result) {
            $result_obj = getShengNiuNiu($each_result['numbers'], $mode);

            $lottery_numbers_arr = explode(',', $each_result['numbers']);
            $blue = array_slice($lottery_numbers_arr, 0, 5);
            $red = array_slice($lottery_numbers_arr, 5, 5);

            if ($result_obj['sheng'] == '红方胜') {
                $data[$each_key]['which_win'] = '红';
                $data[$each_key]['numbers_arr'] = $red;
            } else {
                $data[$each_key]['which_win'] = '蓝';
                $data[$each_key]['numbers_arr'] = $blue;
            }
            $data[$each_key]['niu'] = $result_obj['data']['lottery_niu'];
            $data[$each_key]['gong_pai'] = $result_obj['data']['lottery_gp'];
            $data[$each_key]['long_hu'] = $result_obj['data']['lottery_lh'];
            $data[$each_key]['sum_dx'] = $result_obj['data']['lottery_dx'];
            $data[$each_key]['sum_ds'] = $result_obj['data']['lottery_ds'];

            unset($data[$each_key]['numbers']);
        }

        //统计条数
        $count_sql = "SELECT COUNT(*) FROM un_nn WHERE {$where_str}";
        $totalPage = $this->db->result($count_sql) / $page_size;

        $rt_data = [
            'data' => $data,
            'totalPage' => ceil($totalPage),
        ];
        return $rt_data;
    }
}
