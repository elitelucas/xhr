<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

/**
 * 首页热门彩种推荐
 * 2018-05-16
 */
class RecommendModel extends CommonModel
{

    /**
     * 获取热门彩种列表
     * 2018-05-16
     */
    public function fetchLotteryList()
    {
        $redis = initCacheRedis();
        $recommend_lottery_list = $redis -> HMGet('Config:recommend_lottery_list',['value']);

        $list = json_decode($recommend_lottery_list['value'], true);

        foreach ($list as $k => $v) {
            if ($v['is_recommend'] == '0') {
                unset($list[$k]);
            }
        }

        $sort_arr = array_column($list, 'sort');

        //按照sort字段排序
        array_multisort($sort_arr, SORT_ASC, $list);

        //关闭redis链接
        deinitCacheRedis($redis);

        return $list;
    }


}
