<?php
!defined('IN_SNYNI') && die('Access Denied!');
include_cache(S_PAGE . 'controller' . DS . 'center' . DS . 'action.php');

class InterfaceAction extends Action
{

    public function __construct()
    {
        parent::__construct();
    }
    
    public function getDataInfo(){
        $data = [];
         if (!$this->checkAuth()) {
             $this->retArr['data'][] = $_REQUEST;
             $this->returnCurl();
             return;
         }
        $type = $_REQUEST['type'];
        switch ($type){
            case 1:
                $model = D("center/interface");
                $data['data'] = $model->getReportInfo($_REQUEST);
                break;
            case 2:
                $model = D("center/interface");
                $data['data'] = $model->getLotteryStopOrSell($_REQUEST);
                break;
            case 3:
                $model = D("center/interface");
                $res = $model->setLotteryStopOrSell($_REQUEST);
                if (!$res) {
                    $this->retArr['code'] = 1;
                    $this->retArr['msg'] = "Failed to change";
                } else {
                    $this->retArr['msg'] = "Change successfully";
                }
                break;
            case 4:
                $model = D("center/interface");
                $data['data'] = $model->getLotteryList($_REQUEST);
                break;
        }
        $this->returnCurl($data);
    }
    
    
    
    
    
}
