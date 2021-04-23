<?php
/**
 * 2017-09-20
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class TurntableAction extends Action
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 查询活动相关配置信息（包括大转盘和博饼）
     * 2017-09-20
     * @method GET  /index.php?m = api&c = turntable&a = fetch_activity_setting
     * @return json
     */
    public function fetch_activity_setting () {
        //接收参数
        // $this->checkInput($_REQUEST, array('code',), 'all');
        
        //查询大转盘和博饼活动是否开启
        $sql = "SELECT nid,value FROM un_config WHERE nid IN ('bo_bin', 'turntable_setting', 'christmas', 'nine_gong')";
        $data = $this->db->getAll($sql);
        $setting_arr = array_column($data, 'value', 'nid');
        
        $bo_bing_info = json_decode($setting_arr['bo_bin'], true);
        $turntable_info = json_decode($setting_arr['turntable_setting'], true);
        $christmas_info = json_decode($setting_arr['christmas'], true);
        $nine_gong_info = json_decode($setting_arr['nine_gong'], true);


        //查询大转盘是否有活动正在进行
        $now_time = time();
        $where = "start_time < {$now_time} AND end_time > {$now_time} AND is_underway = 1";
        $running_info = $this->db->getOne("SELECT id FROM un_turntable WHERE {$where} LIMIT 1");

        $christmas_info['state'] = 0;
        $bo_bing_info['state'] = 0;
        $nine_gong_info['state'] = 0;
        $activity_config = $this->db->getall("select id,activity_type from #@_activity where state = 1");
        if(!empty($activity_config)){
            foreach ($activity_config as $val) {
                if($val['activity_type'] == 1){
                    $bo_bing_info['state'] = 1;
                }
                if ($val['activity_type'] == 2) {
                    $christmas_info['state'] = 1;
                }
                if ($val['activity_type'] == 3) {
                    $nine_gong_info['state'] = 1;
                }
            }
        }
        $json_arr = [
            'bo_bing_info' => $bo_bing_info,
            'turntable_is_show' => $turntable_info['is_show_in_profile'],
            'christmas_info' => $christmas_info,
            'nine_gong_info' => $nine_gong_info,
            'turntable_running' => $running_info ? '1' : '0',
        ];
        ErrorCode::successResponse($json_arr);
    }

}
