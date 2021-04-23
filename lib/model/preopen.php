<?php
/**
 * token表model
 */
!defined('IN_SNYNI') && die('Access Denied!');

class preopenModel extends Model {

    /**
     * 修改预开奖表标志使用状态
     * @param string $where update语句的where条件
     * @param integer $use_flag_val 是否用作开奖的状态值
     * @return string
     */
    public function updateUseFlag($where, $use_flag_val)
    {
        //判断where不能为空
        if (! $where) {
            return false;
        }
        $sql = "SELECT `issue` FROM un_pre_open WHERE $where";
        $issue = $this->db->getone($sql);
        $issue = $issue["issue"];
        $sql = "SELECT COUNT(id) FROM un_pre_open WHERE `use_flag` = '1' AND `issue` = '$issue'";
        $result_data = $this->db->getOne($sql);
        if($result_data["COUNT(id)"]>0){
            return true;
        }

        $sql = "UPDATE un_pre_open SET use_flag = '{$use_flag_val}' WHERE {$where} ";
        $this->db->query($sql);
        return true;
    }

    /**
     * 自开型彩种下，添加指定彩种当期停用预开奖记录
     * 2018-03-31
     * 参数 $insert_data 为关联数组，包含以下键
     *      user_id : 操作人
     *      lottery_type : 彩种
     *      issue : 期号
     */
    public function addIssueStopLog($insert_data)
    {
        $insert_data['user_id'] = intval($insert_data['user_id']);
        $insert_data['lottery_type'] = intval($insert_data['lottery_type']);
        $insert_data['issue'] = floatval($insert_data['issue']);
        $insert_data['insert_time'] = time();

        $insert_result = $this->db->insert('un_pre_open_issue_stop', $insert_data);
        return $insert_result;
    }

    /**
     * 自开型彩种下，检查指定彩种当期是否为停用预开奖
     * 2018-03-31
     */
    public function checkIssueStop($lottery_type, $issue)
    {
        $lottery_type = intval($lottery_type);
        $issue = floatval($issue);

        $where = "WHERE lottery_type = {$lottery_type} AND issue = {$issue}";
        $sql = "SELECT id FROM un_pre_open_issue_stop {$where} LIMIT 1";
        $result_data = $this->db->getOne($sql);

        //如果有数据，则当期预开奖为停用状态，返回false，反之则为开启状态，返回true
        if ($result_data) {
            return false;
        }
        return true;
    }

    /**
     * 自开型彩种下，检查指定彩种是否设置了关闭预开奖
     * 2018-04-09
     */
    public function checkLotteryStop($lottery_type)
    {
        $lottery_type = intval($lottery_type);

        //从redis里取配置数据
        $redis = initCacheRedis();
        $json_data = $redis->hGet('Config:pre_open_setting', 'value');
        $json_obj = json_decode($json_data, true);

        //关闭redis链接
        deinitCacheRedis($redis);

        //获取彩种拼接json对象的key值
        $sha_lv_key = 'sha_lv_' . $lottery_type;

        //如果redis中配置项 Config:pre_open_setting 存的值为'0'，则该彩种预开奖为停用状态，返回false，反之返回true
        if ($json_obj[$sha_lv_key]['is_preopen_running'] == '0') {
            return false;
        }
        return true;
    }

    /**
     * 记录预开奖历史配置值
     * 2018-04-02
     * 参数 $insert_data 为关联数组，包含以下键
     *      setting_type_then : 当时配置的杀率模式
     *      is_preopen_running_then : 当时配置的开关选项
     *      percent_then : 当时配置的杀率值
     *      lottery_type : 彩种
     *      issue : 期号
     */
    public function
    addHistory($insert_data)
    {
        if(isset($insert_data["cal_range"])&&$insert_data["cal_range"]=='0') $insert_data['setting_type_then'] = 3;
        else $insert_data['setting_type_then'] = intval($insert_data['setting_type_then']);
        $insert_data['is_preopen_running_then'] = intval($insert_data['is_preopen_running_then']);
        $insert_data['percent_then'] = intval($insert_data['percent_then']);
        $insert_data['lottery_type'] = intval($insert_data['lottery_type']);
        $insert_data['issue'] = floatval($insert_data['issue']);
        $insert_data['insert_time'] = time();
        unset($insert_data["cal_range"]);
        lg('po_addHistory_pre', var_export(['入库数据insert_data'=>$insert_data, '结果insert_result'=>$insert_data,], true));
        $insert_result = $this->db->insert('un_pre_open_history', $insert_data);

        lg('po_addHistory', var_export(['入库数据insert_data'=>$insert_data, '结果insert_result'=>$insert_result,], true));
        return $insert_result;
    }

    /**
     * 查询预开奖历史配置值
     * 2018-04-02
     */
    public function fetchHistory($lottery_type, $issue)
    {
        $lottery_type = intval($lottery_type);
        $issue = floatval($issue);

        $where = "WHERE lottery_type = {$lottery_type} AND issue = {$issue}";
        $field = 'setting_type_then, percent_then, is_preopen_running_then';
        $sql = "SELECT {$field} FROM un_pre_open_history {$where} LIMIT 1";
        $result_data = $this->db->getOne($sql);

        //返回数据
        if ($result_data) {
            return $result_data;
        }
        return false;
    }

}
