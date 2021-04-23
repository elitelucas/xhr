<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/15
 * Time: 18:32
 * desc: 站内信息
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'web' . DS . 'action.php');

class MessageAction extends Action{
    /**
     * 数据表
     */
    private $model;

    public function __construct(){
        parent::__construct();
        $this->model = D('message');
    }

    /**
     * 站内信息页面
     * @return web
     */
    public function index() {
        //验证token
        $this->checkAuth();
        if(isset($_GET['flag_m'])){
            session::set('flag_m',$_GET['flag_m']);
        }
        $JumpUrl = $this->getUrl();
        switch (session::get('flag_m')){
            case 1:
                $history_go = $JumpUrl['8'];
                break;
            case 3:
                $history_go = $JumpUrl['14'];
                break;
            case 4:
                $history_go = $JumpUrl['23'];
                break;
            case 5:
                $history_go = $JumpUrl['30']."&room_id=".session::get('room_id');
                break;
            default:
                $history_go = "";
        }
        include template('news');
    }

    /**
     * 站内消息列表
     * @method GET  /index.php?m=api&c=message&a=dataList&token=36b64f2e4d4237ba3e2743c5d8aa0b02&type=1[2]&page=0
     * @param token string 用户token
     * @param type string 信息类型1 公告; 2 信息
     * @param page int 分页
     * @return web
     */
    public function dataList(){
        $type = trim($_REQUEST['type']);
        $page = trim($_REQUEST['page']);
        $page = empty($page) ? 1 : intval($page);

        //验证token
        $this->checkAuth();
        //初始化redis
        $redis = initCacheRedis();
        $GameConfig= $redis -> hGetAll("Config:100006");
        $num = $GameConfig['value'] ? $GameConfig['value'] : 30;
        $start = ($page - 1) * $num;
        if($type == 1 || $type == 2){
            $res = $this->getMessage($this->userId,$start,$num,$type);
        }else{
            ErrorCode::errorResponse(100008,'无此类型的消息');
        }
        //关闭redis链接
        deinitCacheRedis($redis);

        //将时间转化为需要的格式
        if (!empty($res)) {
            foreach ($res as $k=>$v) {
                $res[$k]['time'] = date('H:i', $v['addtime']); //具体时间
                $res[$k]['month'] = date('m', $v['addtime']); //月
                $res[$k]['day'] = date('d', $v['addtime']); //日
            }
        }
//        print_r($res);
        ErrorCode::successResponse(array('list' => $res));
    }

    /**
     * 站内消息详情
     * @method GET  /index.php?m=api&c=message&a=detail&token=b5062b58d2433d1983a5cea888597eb6&type=1&id=2
     * @param token string 用户token
     * @param type string 信息类型1 公告; 2 信息
     * @param id string 信息id
     * @return web
     */
    public function detail(){
        $type = trim($_REQUEST['type']);
        $mid = trim($_REQUEST['id']);

        //验证token
        $this->checkAuth();
        if($type == 1 || $type == 2){
            $res = $this->getOneMessage($this->userId,$mid,$type);
        }else{
            ErrorCode::errorResponse(100008,'无此类型的消息');
        }

        include template('news-detail');
    }

    /**
     * 信息系统公告
     * @param $uid 用户id
     * @return $SMessage array
     */
    protected function getMessage($uid,$start,$num,$type){
        if($type == 1)
        {
            $sql = "select * from un_message where touser_id = '0' and type = 1 order by addtime desc";
        }
        elseif($type == 2)
        {
            $sql = "select * from un_message where touser_id like '%|".$uid."|%' and type = 2 order by addtime desc";
        }
        $LSMIds = $this->db->getall($sql);
        $SMessage  = array();
        $LSM = array_slice($LSMIds,$start,$num);
        if(empty($LSM)){
            return $SMessage;
        }
        foreach($LSM as $key=>$val)
        {

            if(strpos($val['has_read'],"|".$uid."|") === false)
            {
                $LSM[$key]['has_read'] = 0;
            }
            else
            {
                $LSM[$key]['has_read'] = 1;
            }
            $SMessage = $LSM;

        }
        return $SMessage;
    }

    /**
     * 信息系统公告
     * @param $uid int 用户id
     * @param $mid int 信息id
     * @return $list array
     */
    protected function getOneSysMessage($uid,$mid){
        $reids = initCacheRedis();
        $list = $reids -> hGetAll("SysMessage:".$mid);
        if(empty($list)){
            ErrorCode::errorResponse(100009,'此消息不存在,请稍后再试');
        }

        return $list;
    }


    /**
     * 信息系统公告
     * @param $uid int 用户id
     * @param $mid int 信息id
     * @return $list array
     */
    protected function getOneMessage($uid,$mid,$type){
        $where = array(
            'id' => $mid,
        );
        $res = $this->model->getOneCoupon('*',$where);
        if($type == 1)
        {
            if(empty($res)){
                ErrorCode::errorResponse(100009,'此消息不存在,请稍后再试');
            }
        }
        elseif($type == 2)
        {
            if(empty($res) || strpos($res['touser_id'], $uid) === false){
                ErrorCode::errorResponse(100009,'此消息不存在,请稍后再试');
            }
        }

        //修改读取记录
        if(strpos($res['has_read'],"|".$uid."|") === false)
        {
            $data['has_read'] = $res['has_read']."|".$uid."|";
            $where['id'] = $mid;
            $this->model->save($data,$where);
        }
        return $res;
    }

    /**
     * 多维数组排序
     * @param $array array
     * @param $field string
     * @param $desc bool
     */
    function sortArrByField(&$array, $field, $desc = false){
        $fieldArr = array();
        foreach ($array as $k => $v) {
            $fieldArr[$k] = $v[$field];
        }
        $sort = $desc == false ? SORT_ASC : SORT_DESC;
        array_multisort($fieldArr, $sort, $array);
    }
    
    /**
     * 获取用户反馈信息
     */
    public function getFeedbackInfo()
    {
        $allow_type = array('jpg','jpeg','gif','png');
        $arrUrl = [];
        $postData = $_REQUEST;
    
        //验证参数
        $this->checkInput($_REQUEST, array('token', 'content', 'type'));
        //验证token
        $this->checkAuth();
    
        if ($postData['content'] == '') {
            echo json_encode(['code' => 0, 'ret_msg' => 'The text description cannot be empty', 'data' => []]);
            return;
        }
    
        if (!is_int($postData['type'])) {
            $postData['type'] = 1;
        }
    
        $arrFile = $_FILES['files']['name'];
        if (!empty($arrFile)) {
            $count = count($arrFile);
            if ($count > 3) {
                echo json_encode(['code' => 0, 'ret_msg' => 'You can only upload up to 3 images at a time!', 'data' => []]);
                return;
            }
            for ($i = 0; $i < $count; $i++) {
                $name = $_FILES['files']['name'][$i];
                $arrname = explode('.', $name);
                $type = strtolower(end($arrname));
                if (!in_array($type,$allow_type)){
                    echo json_encode(['code' => 0, 'ret_msg' => 'Upload image format error', 'data' => []]);
                    return;
                }
                if ($_FILES['files']['size'][$i] > C('upload_maxsize') * 1000) {
                    echo json_encode(['code' => 0, 'ret_msg' => 'Upload image larger than 2M', 'data' => []]);
                    return;
                }
            }
    
            $feedbackPath = S_ROOT . C('upfile_path') .'/feedback/';
            if (!file_exists($feedbackPath)) {
                @mkdir($feedbackPath, 0777, true);
            }
    
            for ($i = 0; $i < $count; $i++) {
                $name = $_FILES['files']['name'][$i];
                $arrname = explode('.', $name);
                $type = strtolower(end($arrname));
    
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $feedbackPath . MD5($name) . '.' . $type)){
                    $arrUrl[] = '/' . C('upfile_path') .'/feedback/' . MD5($name) . '.' . $type;
                }else{
                    if (!empty($arrUrl)) {
                        foreach ($arrUrl as $ka => $va) {
                            unlink(S_ROOT . ltrim($va,'/'));
                        }
                    }
                    echo json_encode(['code' => 0, 'ret_msg' => 'Failed to upload image', 'data' => []]);
                    return;
                }
            }
        }
    
        $feedback = [
            'type' => $postData['type'],
            'user_id' => $this->userId,
            'content' => $postData['content'],
            'cellphone' =>  $postData['cellphone'],
            'image_url' => json_encode($arrUrl),
            'status'  => 0,
            'create_time' => time()
        ];
    
        $ret = $this->db->insert('un_feedback', $feedback);
        if ($ret) {
            echo json_encode(['code' => 1, 'ret_msg' => 'Submitted successfully!', 'data' => []]);
        }else {
            if (!empty($arrUrl)) {
                foreach ($arrUrl as $ka => $va) {
                    unlink(S_ROOT . ltrim($va,'/'));
                }
            }
            echo json_encode(['code' => 0, 'ret_msg' => 'Submission Failed', 'data' => []]);
        }
    
        return;
    }
    
}
