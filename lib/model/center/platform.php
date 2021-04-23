<?php
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'model' . DS . 'common.php');

class PlatformModel extends CommonModel
{

    //获取消息信息
    public function getMessageDetail($message_id)
    {
        $messageData = $this->db->getone("SELECT * FROM un_message WHERE id = " . $message_id);
        
        return $messageData;
    }
    
    //修改消息状态
    public function updateMessageDetail($postData, $message_id)
    {
        if (empty($postData) || empty ($message_id)) {
            return false;
        }
        
        $ret = $this->db->update('un_message', $postData, ['id' => $message_id]);
        
        if ($ret) {
            return true;
        }else {
            return false;
        }
    }
    
    //删除公告消息
    public function deleteNotice($message_id)
    {
        $ret = $this->db->delete(['un_message'], ['id' => $message_id]);
        
        if ($ret) {
            return true;
        }else {
            return false;
        }
    }
    
    //添加公告信息
    public function addNotice($insertData)
    {
        if (empty($insertData)) {
            return false;
        }
        
        $ret = $this->db->insert("un_message", $insertData);
         
        return $ret;
    }
    
    //检查是否有已设置了弹窗的公告信息
    public function hasPopupAnnouncement()
    {
        $now_time = time();
        $sql = "SELECT id FROM un_message WHERE type = 1 AND is_popup = 1 AND expired_time > {$now_time}";

        $rt = $this->db->getOne($sql);

        if ($rt && $rt['id']) {
            return $rt['id'];
        }
        
        return false;
    }
}
