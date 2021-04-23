<?php

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

/**
 * desc: APP 红包活动接口类
 */
class RedpacketAction extends Action
{
    /**
     * 数据表
     */
    // private $model;
    // private $model1;
    // private $model2;
    // private $model3;
    // private $model4;

    public function __construct(){
        parent::__construct();
        // $this->model = D('banner');
        // $this->model1 = D('user');
        // $this->model2 = D('room');
        // $this->model3 = D('message');
        // $this->model4 = D('account');
    }

    /**
     * 红包领取列表接口
     * @method GET/POST  /index.php?m=api&c=redpacket&a=gainRedpacket&redpacket_id=100&token=usertoken
     * @param string token 用户token
     * @param int redpacket_id 红包活动id
     * @return json
     * 备注：如果 self_gain_money 为0，则表示没有抢到红包
     */
    public function gainRedpacket()
    {
        // //验证参数
        // $this->checkInput($_REQUEST, array('token'));

        //验证token
        $this->checkAuth();

        $user_id = $this->userId;

        $redpacket_id = intval($_REQUEST['redpacket_id']);
        
        //防止用户短时间多次点击提现接口，后台同一个用户出现多个未处理的提现订单（默认下同时间内只有一个提现订单）
        $preventFlag = 'gainRedpacket' . $this->userId;
        if (preventSupervene($preventFlag, 2)) {
            ErrorCode::errorResponse(400403,'You click too fast, please come back later!');
        }
      
        //后台再次验证用户是否有抽取红包资格
        $check_user_rt_arr = D('Redpacket')->fetchRedpacketCondition($user_id, $redpacket_id);

        //查询用户是否参与过活动
        $has_join_info = D('RedpacketGainLog')->checkUserHasJoinRedpacket($user_id, $redpacket_id);

        //判断是否有资格参加红包活动。此处，当 $has_join_info['flag'] 为true时，表示该用户参加过该活动
        $user_condition_bool = ($check_user_rt_arr['betting_val_bool'] || $check_user_rt_arr['recharge_money_val_bool'] || $check_user_rt_arr['recharge_times_val_bool']);
        if (($check_user_rt_arr['user_group_limit_bool'] && $user_condition_bool) || $check_user_rt_arr['is_reserved_user']) {

            //抢红包/查看红包逻辑
            $rt_data = D('Redpacket')->gainRedpacket($user_id, $redpacket_id, $has_join_info);
            ErrorCode::successResponse($rt_data);

        } else {
            //记录当前这个风险用户
            $lg_data = [
                'user_id' => $user_id,
                'redpacket_id' => $redpacket_id,
                'user_condition_bool' => $user_condition_bool,
                'check_user_rt_arr' => $check_user_rt_arr,
            ];
            lg('hb_bad_user', var_export($lg_data, true));
            ErrorCode::errorResponse(400403, 'The network is busy, please wait');
        }


    }

    /**
     * 当前红包活动记录分页数据
     * 2017-11-06
     * @method GET/POST  /index.php?m=api&c=redpacket&a=currentRedpacketList&redpacket_id=1&page=2&token=usertoken
     * @param string token 用户token
     * @param int redpacket_id 红包活动id
     * @param int page 页码
     * @return json
     */
    public function currentRedpacketList()
    {
        //验证token
        $this->checkAuth();

        $redpacket_id = intval($_REQUEST['redpacket_id']);
        $page = intval($_REQUEST['page']);

        $rt_data = D('RedpacketGainLog')->currentRedpacketList($this->userId, $redpacket_id, $page);
        ErrorCode::successResponse($rt_data);
    }

    /**
     * 红包活动规则接口
     * 2017-11-04
     * @method GET/POST  /index.php?m=api&c=redpacket&a=redpacketRules&redpacket_id=1&token=usertoken
     * @param string token 用户token
     * @param int redpacket_id 红包活动id
     * @return json
     */
    public function redpacketRules()
    {
        //验证token
        $this->checkAuth();
        $redpacket_id = intval($_REQUEST['redpacket_id']);

        $rt_data = D('Redpacket')->redpacketRules($this->userId, $redpacket_id);
        ErrorCode::successResponse($rt_data);
    }

    /**
     * 红包历史统计记录
     * 2017-11-04
     * @method GET/POST  /index.php?m=api&c=redpacket&a=redpacketCount&year=2017&token=usertoken
     * @param string token 用户token
     * @param int year 查询年份
     * @return json
     */
    public function redpacketCount()
    {
        //验证token
        $this->checkAuth();
        $year = intval($_REQUEST['year']) ? : date('Y');

        $rt_data = D('RedpacketGainLog')->redpacketCount($this->userId, $year);
        ErrorCode::successResponse($rt_data);
    }


    /**
     * 个人红包历史记录分页数据
     * 2017-11-06
     * @method GET/POST  /index.php?m=api&c=redpacket&a=selfRedpacketHistory&year=2017&page=2&token=usertoken
     * @param string token 用户token
     * @param int year 年份
     * @param int page 页码
     * @return json
     */
    public function selfRedpacketHistory()
    {
        //验证token
        $this->checkAuth();
        $year = intval($_REQUEST['year']) ? : date('Y');
        $page = intval($_REQUEST['page']) ? : 1;

        $history_list_info = D('RedpacketGainLog')->selfRedpacketHistory($this->userId, $year, $page);
        $rt_data = [
            'history_list' => $history_list_info,
        ];

        ErrorCode::successResponse($rt_data);
    }

    /**
     * 用户达到领取红包条件，但红包已领完，则跳红包列表
     * @method POST  /index.php?m=api&c=redpacket&a=redPacketGainList
     * @param string token 用户token  redpacket_id  红包ID
     * @return json
     ***/

    public function redPacketGainList()
    {
        $this->checkAuth();
        $packetId = $_REQUEST['redpacket_id'];
//        $packetId = 5;
        $list = D('Redpacket')->redPacketGainList($packetId);

        ErrorCode::successResponse($list);
    }

    /**
     * 检查用户是否具有红包资格
     * 2018-04-03
     * @method GET/POST  /index.php?m=api&c=redpacket&a=checkRedpacketByUser&token=usertoken
     * @param string token 用户token
     * @return json
     */
    public function checkRedpacketByUser()
    {
        //验证token
        $this->checkAuth();

        $redpacket_info = D('Redpacket')->checkRedpacketByUser($this->userId);
        $rt_data = [
            'redpacket_info' => $redpacket_info,
        ];

        ErrorCode::successResponse($rt_data);
    }
}
