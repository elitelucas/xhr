<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/15
 * Time: 18:32
 * desc: 站内信息
 */

!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'api' . DS . 'action.php');

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
     * 站内消息列表
     * @method GET  /index.php?m=api&c=message&a=dataList&token=36b64f2e4d4237ba3e2743c5d8aa0b02&type=1[2]&page=0
     * @param token string 用户token
     * @param type string 信息类型1 公告; 2 信息
     * @param page int 分页
     * @return json
     */
    public function dataList(){
        //验证参数
        $this->checkInput($_REQUEST, array('token','type','page'),all);
        $type = trim($_REQUEST['type']);
        $page = trim($_REQUEST['page']);
        $page = empty($page) ? 1 : intval($page);

        //验证token
        $this->checkAuth();
        /* //初始化redis
        $redis = initCacheRedis();
        $GameConfig= $redis -> hGetAll("Config:100006");
        $num = $GameConfig['value']?$GameConfig['value']:20;
        //关闭redis链接
        deinitCacheRedis($redis); 
        */
        $num = 10;
        $start = ($page - 1) * $num;
        if($type == 1 || $type == 2){
            $res = $this->getMessage($this->userId,$start,$num,$type);
            $pageSum = $this->cntDataList($this->userId, $type, $num);
        }else{
            ErrorCode::errorResponse(100008,'无此类型的消息');
        }
        
        ErrorCode::successResponse(array('list' => $res, 'pageSum' => $pageSum));
    }
    
    /**
     * 用户站内消息总数
     * @method GET  /index.php?m=api&c=message&a=dataList&token=36b64f2e4d4237ba3e2743c5d8aa0b02&type=1[2]&page=0
     * @param token string 用户token
     * @param type string 信息类型1 公告; 2 信息
     * @return json
     */
    public function cntDataList($userId,$type, $pageSize)
    {
        $pageSum = 0;
        
         if($type == 1)
        {
            $sql = 'SELECT count(id) as cnt FROM un_message WHERE touser_id = "0" AND type = 1';
        }
        elseif($type == 2)
        {
            $sql = "SELECT count(id) as cnt FROM un_message WHERE touser_id LIKE '%|{$userId}|%' AND type = 2";
        }
        $arrCnt = $this->db->getone($sql);
        
        if (empty($arrCnt['cnt'])) {
            $arrCnt['cnt'] = 0;
        }
        
        $pageSum = ceil($arrCnt['cnt'] / $pageSize);
        
        return $pageSum;
    }

    /**
     * 站内消息详情
     * @method GET  /index.php?m=api&c=message&a=detail&token=b5062b58d2433d1983a5cea888597eb6&type=1&id=2
     * @param token string 用户token
     * @param type string 信息类型1 公告; 2 信息
     * @param id string 信息id
     * @return json
     */
    public function detail(){
        //验证参数
        $this->checkInput($_REQUEST, array('token','type','id'));
        $type = trim($_REQUEST['type']);
        $mid = trim($_REQUEST['id']);

        //验证token
        $this->checkAuth();
        // $this->checkAuth();
        if($type == 1 || $type == 2){
            $res = $this->getOneMessage($this->userId,$mid,$type);
        }else{
            ErrorCode::errorResponse(100008,'无此类型的消息');
        }
        ErrorCode::successResponse(array('list' => $res));
    }
    
    /**
     * 站内消息批量标记已读
     * @method GET  /index.php?m=api&c=message&a=setMessageSatus&token=b5062b58d2433d1983a5cea888597eb6&type=1&ids=2,3,4
     * @param token string 用户token
     * @param type string 信息类型1 公告; 2 信息
     * @param ids array 信息id数组
     * @return json
     */
    public function setMessageSatus(){
        //验证参数
        $this->checkInput($_REQUEST, array('token','type','ids'));
        $type = trim($_REQUEST['type']);
        $mid = trim($_REQUEST['ids']);

        //验证token
        $this->checkAuth();

        if (empty($mid)) {
            ErrorCode::errorResponse(100009,'消息ID不能为空');
        }

        if($type == 1){
            $sql = 'SELECT `id`, `has_read` FROM `un_message` WHERE `touser_id` = "0" AND `type` = 1 AND `id` IN (' .  $mid . ')';
        }elseif($type == 2){
            $sql = "SELECT * FROM un_message WHERE type = 2 AND state = 0 AND touser_id LIKE '%|{$this->userId}|%' AND id IN (" .  $mid . ")";
        }else {
            ErrorCode::errorResponse(100009,'消息类型错误');
        }
        
        $arrMessage = $this->db->getall($sql);
        foreach ($arrMessage as $ka => $va) {
            if(strpos($va['has_read'],"|" . $this->userId . "|") === false)
            {
                $data['has_read'] = $va['has_read'] . "|" . $this->userId . "|";
                $where['id'] = $va['id'];
                $this->model->save($data,$where);
            }
        }
        
        ErrorCode::errorResponse(100008,'标记已读成功');
    }


    /**
     * 公告消息详情查询
     * @method GET  /index.php?m=api&c=message&a=detailNoneToken&id=2
     * @param id string 信息id
     * @return json
     */
    public function detailNoneToken()
    {
        //验证参数
        $mid = intval($_REQUEST['id']);

        //type为系统公告消息标识，固定值为1
        $type = 1;

        //公告消息默认不需要 $user_id
        $uid = 0;

        $res = $this->getOneMessage($uid, $mid, $type);
        ErrorCode::successResponse(['list' => $res]);
    }
    
    /**
     * 获取帮助文档信息
     */
    public function getHelperInfo()
    {
        //验证参数
        $this->checkInput($_REQUEST, array('token'));
        //验证token
        $this->checkAuth();
        
        $page_size = 10;
        $page = empty(trim($_REQUEST['page'])) ? 1 : trim($_REQUEST['page']);
        $page_start = ($page - 1) * $page_size;
        
        $keyword = isset($_REQUEST['keyword']) ? trim($_REQUEST['keyword']) : '';
        
        if (empty($keyword)) {
            $sql = "SELECT id, title, content, create_time FROM un_article_helper WHERE `status` = 1 ORDER BY id LIMIT {$page_start},{$page_size}";
        }else {
            $sql = "SELECT id, title, content, create_time FROM un_article_helper WHERE `status` = 1 AND title like '%{$keyword}%' ORDER BY id LIMIT {$page_start},{$page_size}";
        }
        
        $helperList = $this->db->getall($sql);
        
        foreach($helperList as  $kh => $vh) {
            $helperList[$kh]['create_time'] = date("Y-m-d H:i:s", $vh['create_time']);
        }
        
        
        ErrorCode::successResponse(['data' => ['list' =>$helperList]]);
    }

    /**
     * 信息message
     * @param $where mixed 条件
     * @param $limit string 条数
     * @return $res array
     */
    protected function getMessage($uid,$start,$num,$type){
        if($type == 1)
        {
            $sql = 'SELECT * FROM un_message WHERE touser_id = "0" AND type = 1 ORDER BY addtime DESC';
        }
        elseif($type == 2)
        {
            $sql = "SELECT * FROM un_message WHERE touser_id LIKE '%|{$uid}|%' AND type = 2 ORDER BY addtime DESC";
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
     * @param $uid 用户id
     * @return $SMessage array
     */
    protected function getSysMessage($uid,$start,$num){
        $reids = initCacheRedis();
        $LSMIds = $reids->lRange('SysMessageIds', 0, -1);
        $SMessage  = array();
        $LSM = array_slice($LSMIds,$start,$num);
        if(empty($LSM)){
            return $SMessage;
        }

        foreach ($LSM as $v){
            $list = $reids -> hGetAll("SysMessage:".$v);
            $SMessage[] = $list;
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
    public function sortArrByField(&$array, $field, $desc = false){
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
            ErrorCode::errorResponse(100012,'文本描述不能为空');
        }

        $postData['type'] = getParame('type', 0, 1, 'int');
//        if (!is_int($postData['type'])) {
//            $postData['type'] = 1;
//        }

        $arrFile = $_FILES['files']['name'];
        if (!empty($arrFile)) {
            $count = count($arrFile);
            if ($count > 4) {
                ErrorCode::errorResponse(100013,'每次最多只能上传3张图片！');
            }
            for ($i = 0; $i < $count; $i++) {
                $name = $_FILES['files']['name'][$i];

                if(empty($name)) continue;          //过滤空图片

                $arrname = explode('.', $name);
                $type = strtolower(end($arrname));
                if (!in_array($type,$allow_type)){
                    ErrorCode::errorResponse(100014,'上传图片格式错误');
                }
                
                if ($_FILES['files']['size'][$i] > C('upload_maxsize') * 1000) {
                    ErrorCode::errorResponse(100015,'上传图片大于2M');
                }
            }

            $feedbackPath = S_ROOT . C('upfile_path') .'/feedback/';
            if (!file_exists($feedbackPath)) {
                @mkdir($feedbackPath, 0777, true);
            }
            
            for ($i = 0; $i < $count; $i++) {
                $name = $_FILES['files']['name'][$i];

                if(empty($name)) continue;          //过滤空图片

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
                    ErrorCode::errorResponse(100016,'上传图片失败！');
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
            ErrorCode::successResponse(['ret_msg' => 'Submitted successfully']);
        }else {
            if (!empty($arrUrl)) {
                foreach ($arrUrl as $ka => $va) {
                    unlink(S_ROOT . ltrim($va,'/'));
                }
            }
            ErrorCode::errorResponse(100019,'Submission Failed');
        }

        return;
    }
    

    
    
    
    
}
