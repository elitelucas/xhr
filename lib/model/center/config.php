<?php
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class ConfigModel extends CommonModel
{
    protected $configTable = '#@_config';
    
    /**
     * 系统维护状态设置
     */
    public function updateMaintain($postData)
    {
        $valueData = [];
        
        if (empty($postData['start_time']) || !strtotime($postData['start_time'])) {
            return ['code' => 0, 'msg' => '维护开始时间不能为空！', 'data' => []];
        }
        
        if (empty($postData['end_time'])  || !strtotime($postData['start_time'])) {
            return ['code' => 0, 'msg' => '维护结束时间不能为空！', 'data' => []];
        }
        
        if (empty($postData['message'])) {
            return ['code' => 0, 'msg' => '维护提示语不能为空！', 'data' => []];
        }
        
        $configData = $this->db->getone("SELECT * FROM `un_config` WHERE `nid` = 'maintain_info'");
        if (empty($configData)) {
            $insertData = [
                'nid' => 'maintain_info',
                'value' => json_encode([
                    'status' => 0,
                    'message' => '系统即将升级，维护时间：00:00-00:00',
                    'start_time' => '2018-01-01 00:00',
                    'end_time' => '2018-01-01 00:00',
                    'type' => 0
                ],JSON_UNESCAPED_UNICODE),
                'name' => '平台维护设置',
                'desc' => 'status：1，维护中，0：未维护，start_time：维护开始时间，end_time：维护结束时间，type：0：正常维护，1：加急维护'
            ];
            
            $this->db->insert('un_config', $insertData);
            $configData = $insertData;
        }
        $valueData = json_decode($configData['value'], true);

        if (empty($postData['type'])) {
            $valueData['type'] = 0;
        }else {
            $valueData['type'] = 1;
        }
        
        if (empty($postData['status'])) {
            $valueData['status'] = 0;
        }else {
            $valueData['status'] = 1;
        }
        
        $valueData['start_time'] = $postData['start_time'];
        $valueData['end_time']   = $postData['end_time'];
        $valueData['message']    = $postData['message'];
        
        $value = json_encode($valueData, JSON_UNESCAPED_UNICODE);
        
        $ret = $this->db->update($this->configTable, ['value' => $value], ['nid' => 'maintain_info']);
        
        if ($ret) {
            return ['code' => 1, 'msg' => '修改成功！', 'data' => []];
        }else {
            return ['code' => 1, 'msg' => '修改失败！', 'data' => []];
        }
    }
    


}