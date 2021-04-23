<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

/**
 * 活动中心模型类
 * 2017-12-13 update
 */
class ActcenterModel extends CommonModel
{

    /**
     * 获取一条活动记录
     * 2018-02-02 update
     */
    public function fetchActOne($where, $field = null)
    {
        if (! $field) {
            $field = '*';
        }

        $sql = "SELECT {$field} FROM un_act_center {$where} LIMIT 1";
        $data_one = $this->db->getOne($sql);
        return $data_one;
    }


    /**
     * 查询活动类型数据
     * 2017-12-13 update
     */
    public function fetchActTypeList()
    {
        $sql = "SELECT type_name,type_value FROM un_act_type WHERE is_show = 1 ORDER BY view_sort ASC";
        $data_list = $this->db->getAll($sql);
        return $data_list;
    }

    /**
     * 查询活动相关信息
     * 2017-12-13 update
     */
    public function fetchActInfo($act_type)
    {
        $now_time = time();
        switch ($act_type) {
            case '1':
                //天天返利活动
                $fetch_sql = 'SELECT id AS aid, start_time AS s_time, end_time AS e_time
                    FROM un_ttfl_cfg WHERE main = 1 LIMIT 1';
                $data_info = $this->db->getOne($fetch_sql);
                if ($now_time > $data_info['s_time'] && $now_time < $data_info['e_time']) {
                    $data_info['act_status'] = '1';
                } else {
                    $data_info['act_status'] = '2';
                }
                $data_info['s_date'] = date('Y-m-d H:i:s', $data_info['s_time']);
                $data_info['e_date'] = date('Y-m-d H:i:s', $data_info['e_time']);
                return $data_info;
                // break;
            case '2':
                //大转盘活动
                $fetch_sql = 'SELECT id AS aid, start_time AS s_time, end_time AS e_time
                    FROM un_turntable WHERE is_underway = 1 LIMIT 1';
                $data_info = $this->db->getOne($fetch_sql);
                if (! $data_info) {
                    $fetch_sql2 = 'SELECT start_time AS s_time, end_time AS e_time
                        FROM un_turntable WHERE is_underway = 2 ORDER BY last_updatetime DESC LIMIT 1';

                    //一个活动都没有，则返回false 
                    $data_info2 = $this->db->getOne($fetch_sql2);
                    if (! $data_info2) {
                        return false;
                    }

                    $data_info2['act_status'] = '2';
                    $data_info2['s_date'] = date('Y-m-d H:i:s', $data_info2['s_time']);
                    $data_info2['e_date'] = date('Y-m-d H:i:s', $data_info2['e_time']);
                    return $data_info2;
                }

                if ($now_time > $data_info['s_time'] && $now_time < $data_info['e_time']) {
                    $data_info['act_status'] = '1';
                } else {
                    $data_info['act_status'] = '2';
                }
                $data_info['s_date'] = date('Y-m-d H:i:s', $data_info['s_time']);
                $data_info['e_date'] = date('Y-m-d H:i:s', $data_info['e_time']);
                return $data_info;
                // break;
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
                //博饼活动(activity_type字段为1)，双旦活动(activity_type字段为2)，九宫格活动(activity_type字段为3)，福袋活动(activity_type字段为4)，刮刮乐活动(activity_type字段为5)
                if ($act_type == '3') {
                    $activity_type = 1;
                } elseif ($act_type == '4') {
                    $activity_type = 2;
                } elseif ($act_type == '5') {
                    $activity_type = 3;
                } elseif ($act_type == '6') {
                    $activity_type = 4;
                } elseif ($act_type == '7') {
                    $activity_type = 5;
                }
                $fetch_sql = "SELECT id AS aid, start_time AS s_time, end_time AS e_time
                    FROM un_activity WHERE state = 1 AND activity_type = {$activity_type} LIMIT 1";
                $data_info = $this->db->getOne($fetch_sql);
                if (! $data_info) {
                    $fetch_sql2 = "SELECT id AS aid, start_time AS s_time, end_time AS e_time
                        FROM un_activity WHERE state = 2 AND activity_type = {$activity_type} ORDER BY add_time DESC LIMIT 1";

                    //一个活动都没有，则返回false 
                    $data_info2 = $this->db->getOne($fetch_sql2);
                    if (! $data_info2) {
                        return false;
                    }

                    $data_info2['act_status'] = '2';
                    $data_info2['s_date'] = date('Y-m-d H:i:s', $data_info2['s_time']);
                    $data_info2['e_date'] = date('Y-m-d H:i:s', $data_info2['e_time']);
                    return $data_info2;
                }

                if ($now_time > $data_info['s_time'] && $now_time < $data_info['e_time']) {
                    $data_info['act_status'] = '1';
                } else {
                    $data_info['act_status'] = '2';
                }
                $data_info['s_date'] = date('Y-m-d H:i:s', $data_info['s_time']);
                $data_info['e_date'] = date('Y-m-d H:i:s', $data_info['e_time']);
                return $data_info;
            
            default:
                break;
        }
    }

    /*
     *  活动总数
     * */
    public function actCnt($where = []) {
        $fetch_sql = "SELECT count(*) as cnt FROM un_act_center WHERE is_putaway = 1";
        return $this->db->result($fetch_sql);
    }

    /**
     * H5/APP 查询活动接口
     * 2017-12-14 update
     */
    public function fetchActList($user_info, $page = 1, $page_size = 10)
    {

        //查询字段
        $field = 'act_title, act_type, act_end_time, act_url, act_banner_pic, act_banner_pic_pc, is_underway';

        //调整页码
        $page_code = ($page - 1) * $page_size;

        //查询活动中心记录列表（只取上架的活动数据）
        $fetch_sql = "SELECT {$field} FROM un_act_center WHERE is_putaway = 1
            ORDER BY act_sort ASC LIMIT {$page_code}, {$page_size} ";
        $act_list = $this->db->getAll($fetch_sql);

        //获取活动类型对应的名称
        $act_type_list = $this->fetchActTypeList();
        $act_map_arr = array_column($act_type_list, 'type_name', 'type_value');

        foreach ($act_list as &$each_act) {
            $each_act['act_end_date'] = date('Y.m.d', $each_act['act_end_time']);
            unset($each_act['act_end_time']);

            //天天返利和大转盘
            if ($each_act['act_type'] == '1' || $each_act['act_type'] == '2') {
                $each_act['act_url'] .= "&token={$user_info['token']}";
            }
            //博饼,双旦,九宫格,福袋,刮刮乐
            elseif ($each_act['act_type'] == '3' || $each_act['act_type'] == '4' || $each_act['act_type'] == '5' || $each_act['act_type'] == '6' || $each_act['act_type'] == '7') {
                $each_act['act_url'] .= "&id={$user_info['user_id']}";
            }

            //增加活动名称字段
            $each_act['act_type_name'] = $act_map_arr[$each_act['act_type']];
        }

        return $act_list;
    }

    /**
     * 关闭活动
     * 2018-03-03
     */
    public function updateActIsUnderway($act_type)
    {
        lg('act_center_update', var_export(['act_type' => $act_type], true));
        $act_type = intval($act_type);
        try {
            //1.天天返利 2.大转盘 3.博饼 4.双旦 
            $update_sql = "UPDATE un_act_center SET is_underway = 2 WHERE act_type = {$act_type} ORDER BY id DESC LIMIT 1";
            $this->db->query($update_sql);
            return [
                'status' => 0,
                'msg' => 'success',
            ];
        } catch (Exception $e) {
            return [
                'status' => 500080,
                'msg' => 'The database is busy, please wait',
            ];
        }
    }

}
