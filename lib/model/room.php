<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/11
 * Time: 16:17
 * desc: 游戏房间
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class RoomModel extends CommonModel {
    protected $table='#@_room';

    /**
     * 根据彩种，显示开奖周期数据字串
     * @param number $lottery_type 彩种类别
     * 2018-03-09
     */
    public function getLotteryPeriodInfo($lottery_type)
    {
        $period_info = '';
        switch ($lottery_type) {
            //幸运28
            case '1':
                $period_info = '5分钟一期';
                break;
            //北京pk10
            case '2':
                $period_info = '20分钟一期';
                break;
            //加拿大28
            case '3':
                $period_info = '3分半一期';
                break;
            //幸运飞艇
            case '4':
                $period_info = '5分钟一期';
                break;
            //重庆时时彩
            case '5':
                $hour = date('H');
                $period_info = '20分钟一期';
                break;
            //三分彩
            case '6':
                $period_info = '3分钟一期';
                break;
            //六合彩
            case '7':
                $period_info = '每周开奖3期';
                break;
            //急速六合彩
            case '8':
                $period_info = '5分钟一期';
                break;
            //急速赛车
            case '9':
                $period_info = '3分钟一期';
                break;
            //百人牛牛
            case '10':
                $period_info = '5分钟一期';
                break;
            //分分彩
            case '11':
                $period_info = '1分钟一期';
                break;
            //世界杯
            case '12':
                $period_info = '最快最准最全';
                break;
            //骰宝
            case '13':
                $period_info = '5分钟一期';
                break;
            //分分PK10
            case '14':
                $period_info = '1分钟一期';
                break;

            default:
                break;
        }
        return $period_info;
    }

}