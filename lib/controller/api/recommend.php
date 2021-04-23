<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

/**
 * 首页推荐彩种接口
 */
class RecommendAction extends Action
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 首页热门推荐彩种
     * 2018-05-16
     */
    public function fetchLotteryList()
    {
        //验证token
        // $this->checkAuth();

        $lottery_list = D('Recommend')->fetchLotteryList();

        $rt_data = [
            'data' => $lottery_list,
        ];

        ErrorCode::successResponse($rt_data);

    }

}
