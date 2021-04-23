<?php

/**
 * 后台授权验证码模块相关
 * 2017-12-05
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class LogincodeModel extends CommonModel
{

    //检查设备是否存在
    public function isDeviceCodeExists($device_code)
    {
        $fetch_sql = "SELECT userid FROM un_admin WHERE device_code = '{$device_code}' LIMIT 1";
        $device_info = $this->db->result($fetch_sql);
        if ($device_info) {
            return 1;
        } else {
            return 0;
        }
    }

    //绑定设备码和随机码
    public function bindDevice($device_code, $random_code)
    {
        $update_sql = "UPDATE un_admin SET device_code = '{$device_code}' WHERE random_code = '{$random_code}' ";
        $flag = $this->db->query($update_sql);
        return $flag;
    }

    //根据设备码获取授权的随机码
    public function fetchRandomCode($device_code)
    {
        $fetch_sql = "SELECT userid FROM un_admin WHERE device_code = '{$device_code}' LIMIT 1";
        $userid = $this->db->result($fetch_sql);
        if (! $userid) {
            return '';
        }

        //生成6位数字随机码
        $random_code = $this->getRandomCode();
        $now_time = time();

        $update_sql = "UPDATE un_admin SET random_code = '{$random_code}', random_code_createtime = '{$now_time}'
            WHERE device_code = '{$device_code}'";
        $this->db->query($update_sql);

        return $random_code;
    }

    //解绑设备码和管理员
    public function unbindDevice($admin_user_id)
    {
        //生成6位数字随机码
        $random_code = $this->getRandomCode();
        $now_time = time();
        $update_sql = "UPDATE un_admin SET device_code = '', random_code_createtime = '{$now_time}', random_code = '{$random_code}'
            WHERE userid = {$admin_user_id}";

        return $this->db->query($update_sql);
    }

    //生成随机码
    public function getRandomCode()
    {
        return mt_rand(100001,999999);
    }

    //检查验证码是否存在
    public function isRandomCodeExists($random_code)
    {
        $fetch_sql = "SELECT userid FROM un_admin WHERE random_code = '{$random_code}' LIMIT 1";
        $random_code_info = $this->db->result($fetch_sql);
        if ($random_code_info) {
            return 1;
        } else {
            return 0;
        }
    }

    //保存随机授权码开关设置
    public function saveRandomCodeSetting($random_code_is_open)
    {
        //原有配置数据
        $fetch_sql = 'SELECT `value` FROM un_config WHERE nid = "admin_random_code_setting" LIMIT 1';
        $base_json_str = $this->db->result($fetch_sql);

        //创建修改数据json字串
        $base_json_arr = json_decode($base_json_str, true);
        $base_json_arr['is_open'] = $random_code_is_open;
        $update_json_str = json_encode($base_json_arr, JSON_UNESCAPED_UNICODE);

        $update_sql = "UPDATE un_config SET `value` = '{$update_json_str}'
            WHERE nid = 'admin_random_code_setting' LIMIT 1";

        return $this->db->query($update_sql);
    }
}
