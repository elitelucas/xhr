<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/18
 * Time: 18:09
 * desc; 充值记录表
 */
!defined('IN_SNYNI') && die('Access Denied!');

include_cache(S_PAGE . 'model' . DS . 'common.php');

class AdminModel extends CommonModel {
    
    public function check($username,$password){
		

        $sql ="select * from un_admin where username='$username' and password='$password'";
  
        $data = $this->db->getone($sql);
 

            if(!empty($data)){
        	
              $_SESSION['admin'] = $data;
 
        	  return true;
            }
		
    }

}