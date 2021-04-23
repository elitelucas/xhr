<?php

/**
 * 用户关注接口
 * 2018-05-29
 */
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

class UserfollowAction extends Action
{

    public function __construct()
    {
        parent::__construct();
    }

    public function getFollowStatus() {
        $this->checkAuth();

        $res = D('user')->getUserInfo('follow_status', ['id' => $this->userId], 1);
        if($res) {
            ErrorCode::successResponse($res);
        }
        ErrorCode::errorResponse(400, 'fail');
    }

    /*
     * 设置关注状态
     * */
    public function setFollowStatus() {
        $this->checkAuth();

        $follow_status = getParame('follow_status', 1, '', 'int', ['缺少参数follow_status']);
        if(!in_array($follow_status, [1, 2])) {
            ErrorCode::errorResponse(400, 'Parameter error');
        }

        $res = D('user')->save(['follow_status' => $follow_status], ['id' => $this->userId]);
        if(false === $res) {
            ErrorCode::errorResponse(400, 'Operation failed');
        }
        ErrorCode::successResponse();
    }


    /*
     * 关注用户的ID列表
     * */
    public function followUserIdList() {
        getParame('token',1);
        $this->checkAuth();
        $resData = D('user')->getUserFollowUserList($this->userId);
        if($resData['code']) ErrorCode::errorResponse(400, 'Operation failed');
        ErrorCode::successResponse(['data' => $resData['data']]);
    }



    //我的关注
    public function followUserList() {
        getParame('token',1);
        $this->checkAuth();
        $page = getParame('page',0,1,'int');
        $resData = D('user')->followUserList($this->userId, $page);
        if($resData['code']) {
            ErrorCode::errorResponse(400, 'Operation failed');
        }
        ErrorCode::successResponse(['data' => $resData['data']]);
    }

    //关注用户的投注信息
    public function followUserBetInfo() {
        getParame('token',1);
        $this->checkAuth();
        $user_id = getParame('user_id',1);
        $room_id = getParame('room_id',0,'','int');

//        if($room_id) {
//            $room = ' and  o.room_no = '.$room_id;
//        }else {
//            $room = '';
//        }
//
//        $sql = "SELECT o.id, o.user_id, o.room_no, o.issue, o.way, o.money, o.single_money, r.title as room_name, lt.name as lottery_name FROM un_orders o
//                LEFT JOIN un_room r ON o.room_no = r.id
//                LEFT JOIN un_lottery_type lt ON o.lottery_type = lt.id
//                WHERE o.user_id = {$user_id} {$room} ORDER BY o.addtime DESC LIMIT 5";
//        $orderList = $this->db->getall($sql);

        $where = ['user_id' => $user_id, 'room_id' => $room_id];
        $orderList = D('user')->userBetInfo($where,1,5);

        $userInfo = D('user')->getUserInfo('nickname, avatar, honor_upgrade', 'id = '.$user_id, 1);

        ErrorCode::successResponse(['data' => $orderList, 'userData' => $userInfo]);
    }


    /**
     * 获取用户关注的人的投注信息
     * 2018-05-29
     */
    public function fetchFollowData()
    {
        // //验证参数
        // $this->checkInput($_REQUEST, array('token'));

        // //验证token
        // $this->checkAuth();

        // //用户消息
        // $user_id = $this->userId;

        $data = [];

        //初始化redis
        $redis = initCacheRedis();

        deinitCacheRedis($redis);

        $data['data'] = $data;
        ErrorCode::successResponse($data);

    }

}