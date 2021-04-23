<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

/**
 * desc: 走势图，暂供pc端接口使用
 */
class TrendAction extends Action
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 彩种走势图
     * 2018-04-12
     */
    public function trendChart()
    {

        // //验证token
        // $this->checkAuth();

        // $user_info = [
        //     'user_id' => $this->userId,
        //     'token' => $_REQUEST['token'],
        // ];

        $lottery_type = intval($_REQUEST['lottery_type']) ? : 1;

        //日期，不传则默认当天
        $day = trim($_REQUEST['day']) ? : date('Y-m-d');

    

        //分页信息
        $page = intval($_REQUEST['page']) ? : 1;

        $trend_model = D('Trend');

        switch ($lottery_type) {
            //28类 【幸运28、加拿大28】
            case '1':
            case '3':
                $rt_data = $trend_model->trendChart28($lottery_type, $page, $day);
                break;
            //PK10赛车类 【北京PK10、幸运飞艇、急速赛车】
            case '2':
            case '4':
            case '9':
            case '14':
                $rt_data = $trend_model->trendChartPK10($lottery_type, $page, $day);
                break;
            //时时彩类 【重庆时时彩、三分彩、分分彩】
            case '5':
            case '6':
            case '11':
                $rt_data = $trend_model->trendChartSSC($lottery_type, $page, $day);
                break;
            //六合彩类 【香港六合彩】
            case '7':
                //六合彩如果不传日期，则会默认取一整年数据
                $rt_data = $trend_model->trendChartLHC($lottery_type, $page, trim($_REQUEST['day']));
                break;
            //六合彩类 【急速六合彩】
            case '8':
                $rt_data = $trend_model->trendChartLHC($lottery_type, $page, $day);
                break;
            //牛牛类【百人牛牛】
            case '10':
                $rt_data = $trend_model->trendChartNN($lottery_type, $page, $day);
                break;
            //欢乐骰宝
            case '13':
                $rt_data = $trend_model->trendChartSB($lottery_type, $page, $day);
                break;
        }

        ErrorCode::successResponse($rt_data);

    }


    
    /**
     * 彩种历史数据
     * 2018-04-12
     */
    public function getMoreLottery()
    {
        $lottery_type = intval($_REQUEST['lottery_type']) ? : 1;

        //日期，不传则默认当天
        $day = trim($_REQUEST['day']) ? : date('Y-m-d');



        //分页信息
        $page = intval($_REQUEST['page']) ? : 1;

        $trend_model = D('Trend');
        switch ($lottery_type) {
            //28类 【幸运28、加拿大28】
            case '1':
            case '3':
                $rt_data = $trend_model->getMoreLottery28($lottery_type, $page, $day);
                break;
            //PK10赛车类 【北京PK10、幸运飞艇、急速赛车】
            case '2':
            case '4':
            case '9':
            case '14':
                $rt_data = $trend_model->getMoreLotteryPK10($lottery_type, $page, $day);
                break;
            //时时彩类 【重庆时时彩、三分彩、分分彩】
            case '5':
            case '6':
            case '11':
                $rt_data = $trend_model->getMoreLotterySSC($lottery_type, $page, $day);
                break;
            //六合彩类 【香港六合彩】
            case '7':
                //六合彩如果不传日期，则会默认取一整年数据
                $rt_data = $trend_model->getMoreLotteryLHC($lottery_type, $page, trim($_REQUEST['day']));
                break;
            //六合彩类 【急速六合彩】
            case '8':
                $rt_data = $trend_model->getMoreLotteryLHC($lottery_type, $page, $day);
                break;
            //牛牛类 【百人牛牛】
            case '10':
                $rt_data = $trend_model->getMoreLotteryNN($lottery_type, $page, $day);
                break;
            //欢乐骰宝
            case '13':
                $rt_data = $trend_model->getMoreLotterySB($lottery_type, $page, $day);
                break;
        }
        ErrorCode::successResponse($rt_data);

    }

    /**
     * 或取注册信息接口
     * @author bell <bell.gao@wiselinkcn.com>
     * @copyright 2018-05-04 14:13:12
     */
    public function getRegInfo(){
        $uid = $_REQUEST['id'];
        if (empty($uid)) {
            $uid = 0;
        }
        $redis = initCacheRedis();
        $config = json_decode($redis->hGet("Config:set_register_info","value"),true);
        deinitCacheRedis($redis);
        $username = $this->db->getone("select username from #@_user where id = {$uid}")['username'];
        $config['username'] = empty($username) ? '' : $username;
        $config['state'] = $config['status'];
        unset($config['status']);
        ErrorCode::successResponse($config);
    }



}
