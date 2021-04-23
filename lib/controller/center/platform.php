<?php
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'center' . DS . 'action.php');

class PlatformAction extends Action
{

    public function __construct()
    {
        parent::__construct();
    }
    
    
    //公告推荐
    public function recom()
    {
        $msg = '';
        $recom = (int)trim($_REQUEST['recom']);
        $message_id = (int)trim($_REQUEST['message_id']);
        $platformModel = D('center/platform');
    
        if (!in_array($recom, [1, 2])) {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'Announcement push status error';
            $this->retArr['data']['retData'] = ['message_id' => $message_id];
            $this->returnCurl();
            return;
        }
    
        $noticeDetail = $platformModel->getMessageDetail($message_id);
        if ($noticeDetail['recom'] == $recom) {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'There is no change in the announcement push status';
            $this->retArr['data']['retData'] = ['message_id' => $message_id];
            $this->returnCurl();
            return;
        }
    
        $ret = $platformModel->updateMessageDetail(['recom' => $recom],$message_id);
        if ($ret) {
            $this->retArr['msg']  = 'Announcement to modify the push status successfully';
        }else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'Announcement failed to modify push status';
        }
    
        $this->retArr['data']['retData'] = ['message_id' => $message_id];
        $this->returnCurl();
        return;
    }
    
    public function deleteNotice()
    {
        $postData = $_REQUEST;
        $platformModel = D('center/platform');
        
        if (!$this->checkAuth()) {
            $this->retArr['data'][] = $_REQUEST;
            $this->returnCurl();
            return;
        }
        
        if (empty($postData['message_id'])) {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'Announcement ID cannot be empty';
            $this->retArr['data']['retData'] = ['message_id' => $postData['message_id']];
            $this->returnCurl();
            return;
        }
        
        $ret = $platformModel->deleteNotice($postData['message_id']);
        
        if ($ret) {
            $this->retArr['msg'] = 'Announcement deleted successfully';
            $this->retArr['data']['retData'] = ['message_id' => $postData['message_id']];
        }else {
            $this->retArr['code'] = 1;
            $this->retArr['msg']  = 'Failed to delete announcement';
            $this->retArr['data']['retData'] = ['message_id' => $postData['message_id']];
        }
        
        $this->returnCurl();
        return;
    }
    
    //二次添加公告
    public function setNotice()
    {
        $postData = $_REQUEST;
        $platformModel = D('center/platform');
    
        if (!$this->checkAuth()) {
            $this->retArr['data'][] = $_REQUEST;
            $this->returnCurl();
            return;
        }
    
        if (empty($postData['title']) || empty($postData['content'])) {
            $this->retArr['data']['retData'] = ['code' => 0, 'msg' => 'Announcement subject and content cannot be empty', 'data' => []];
            $this->returnCurl();
            return;
        }
    
        $insertData = [
            'title'         => $postData['title'],
            'content'       => $postData['content'],
            'recom'         => isset($postData['recom']) ? $postData['recom'] : 0,
            'is_popup'      => isset($postData['is_popup']) ? $postData['is_popup'] : 0,
            'expired_time'  => isset($postData['expired_time']) ? strtotime($postData['expired_time']) : strtotime(date('+1day')),
            'type'          => 1,
            'touser_id'     => 0,
            'user_id'       => $this->admin['userid'],
            'addtime'       => time()
        ];
    
        $notice_id = $platformModel->addNotice($insertData);
    
        if ($notice_id) {
            $this->retArr['data']['retData'] = ['code' => 2, 'msg' => 'The announcement was successfully published!', 'data' => ['notice_id' => $notice_id]];
        }else {
            $this->retArr['data']['retData'] = ['code' => 0, 'msg' => 'Announcement failed!', 'data' => []];
        }
    
        $this->returnCurl();
        return;
    }
    
    //添加公告
    public function addNotice()
    {
        $postData = $_REQUEST;
        $platformModel = D('center/platform');
        
        if (!$this->checkAuth()) {
            $this->retArr['data'][] = $_REQUEST;
            $this->returnCurl();
            return;
        }
        
        if (empty($postData['title']) || empty($postData['content'])) {
            $this->retArr['data']['retData'] = ['code' => 0, 'msg' => 'Announcement subject and content cannot be empty', 'data' => []];
            $this->returnCurl();
            return;
        }
        
        if ($postData['is_popup'] == 1) {
            $hasflag = $platformModel->hasPopupAnnouncement();
            if ($hasflag !== false) {
                $ret = [
                    'code' => 1,
                    'msg'  => 'There are currently pop-up announcements. Should they be replaced with the current pop-up announcements?',
                    'data' => []
                ];
                
                $this->retArr['data']['retData'] = $ret;
                $this->returnCurl();
                return;
            }
        }
        
        $insertData = [
            'title'         => $postData['title'],
            'content'       => $postData['content'],
            'recom'         => isset($postData['recom']) ? $postData['recom'] : 0,
            'is_popup'      => isset($postData['is_popup']) ? $postData['is_popup'] : 0,
            'expired_time'  => isset($postData['expired_time']) ? strtotime($postData['expired_time']) : strtotime(date('+1day')),
            'type'          => 1,
            'touser_id'     => 0,
            'user_id'       => $this->admin['userid'],
            'addtime'       => time()
        ];
        
        $notice_id = $platformModel->addNotice($insertData);
        
        if ($notice_id) {
            $this->retArr['data']['retData'] = ['code' => 2, 'msg' => 'The announcement was successfully published!', 'data' => ['notice_id' => $notice_id]];
            
        }else {
            $this->retArr['data']['retData'] = ['code' => 0, 'msg' => 'Announcement failed!', 'data' => []];
        }
        
        $this->returnCurl();
        return;
    }
    
    public function getMaintain()
    {
        $data = [];

        if (!$this->checkAuth()) {
            $this->retArr['data'][] = $_REQUEST;
            $this->returnCurl();
            return;
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
        
        $data = json_decode($configData['value'], true);
        
        $this->returnCurl($data);

        return;
    }
    
    public function updateMaintain()
    {
        $postData = $_REQUEST;
        $configModel = D('center/config');
        
        $ret = $configModel->updateMaintain($postData);
        
        $this->returnCurl($ret);
    }
}
