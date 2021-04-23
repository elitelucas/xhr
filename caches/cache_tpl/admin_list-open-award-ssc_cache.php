<?php  !defined('IN_SNYNI') && die('Access Denied!');?>
<!DOCTYPE html>
<html lang="zh-cn">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
        <link rel="stylesheet" href="statics/admin/css/pintuer.css">
        <link rel="stylesheet" href="statics/admin/css/admin.css">
        <link rel="stylesheet" href="statics/admin/css/datePicker.css">
        <script src="statics/admin/js/jquery.js"></script>
        <!-- <script src="statics/admin/js/jquery.min.js" type="text/javascript"></script> -->
        <script src="statics/admin/js/pintuer.js"></script>  
        <script src="statics/admin/js/layer/2.1/layer.js"></script>  
        <script src="statics/admin/js/jquery.date_input.pack.js"></script> 
        <?php  include template('public-new-ui-header'); ?>
        <style type="text/css">
            /*#page{height: 60px;margin-top: 20px;text-align: center;}
            #page ul li{float: left;margin-right:10px;}
            #page ul .current{ background-color:#0099ff;text-align:center;}*/
        </style>
    </head>
    <body class="new_ui_body">
        <div class="row">
            <div class="col-sm-12">
                <div class="ibox float-e-margins">
                    <div class="ibox-title iboxWTitle">
                        <h5>开奖列表</h5>
                        <!-- <div class="ibox-tools">
                            <a href="#" class="btn btn-white btn-bitbucket">
                                <i class="fa fa-plus-square-o"> </i>
                            </a>
                        </div> -->
                    </div>
                    <div class="ibox-content" style="width: 100%;">
                        <div class="row">
                            <form method="post" id="form" action="">
                                <!--  -->                                
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">期号</span>
                                        <input type="text" value="<?php echo $_REQUEST['issue']?>" class="form-control ip-tips-box pull-left" name="issue" placeholder="请输入期号">
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">处理状态</span>
                                        <select class="form-control" id='status' name="state">
                                            <option value="">选择</option>
                                            <option <?php  if($_REQUEST['state'] == 2) { ?>selected<?php  } ?> value="2">未开奖</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-sm-5">
                                    <div class="input-group m-b">
                                        <input type="button" class="btn btn-primary" value="搜索" onclick="index()" />
                                        &nbsp;
                                        <input type="button" class="btn btn-primary" value="手动补单" onclick="location.href='<?php echo url('','openAward','openward')?>&lottery_type=<?php echo $lottery_type?>'" />
                                        &nbsp;

                                        <?php  if($cancal_order_supper==1) { ?>
                                        <input type="button" class="btn btn-primary" value="一键撤单" onclick="location.href='<?php echo url('','openAward','cancalOrdersByIssue')?>&lottery_type=<?php echo $lottery_type?>'" />
                                        <?php  } ?>
                                        <?php  if($lottery_type == 6 || $lottery_type == 8 || $lottery_type == 11) { ?>
                                        &nbsp;
                                        <input type="button" class="btn btn-primary" value="预开奖" onclick="location.href='<?php echo url('','openAward','preOpenAward')?>&lottery_type=<?php echo $lottery_type?>&flag=2'" />
                                        &nbsp;
                                        <input type="button" class="btn btn-primary" value="多种预开奖" onclick="location.href='<?php echo url('','openAward','preOpenAward')?>&lottery_type=<?php echo $lottery_type?>&flag=3'" />
                                       <?php  } ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <table class="table table-striped table-bordered table-hover" id="editable" aria-describedby="editable_info">
                            <thead>
                                <tr>
                                    <th>期号</th>
                                    <th>开奖号码</th>
                                    <th>开奖结果</th>
                                    <th>标准开奖时间</th>
                                    <th>实际开奖时间</th>
                                    <th>开奖状态</th>
                                    <th>操作</th>
                                </tr>   
                            </thead>
                            <tbody>
                                <?php  if(is_array($list)) { foreach($list as $v) { ?>                                <tr>
                                    <td><?php echo $v['issue']?></td>
                                    <td><?php echo $v['open_result']?></td>
                                    <td><?php 
                                        echo implode($v['open_result1'],',');
                                ?></td>
                                    <td><?php echo $v['open_time']?></td>
                                    <td><?php echo $v['insert_time']?></td>
                                    <td><?php  if($v['state'] === '0') { ?>自动<?php  } else { ?>手动<?php  } ?></td>
                                    <td>
                                        <?php  if($v['state'] !== '0' && $v['state'] !== '1' && (empty($v['user_id']) || $v['user_id']== $adminUid) ) { ?>
                                            <a href="?m=admin&c=openAward&a=openward&issue=<?php echo $v['issue']?>&lottery_type=<?php echo $lottery_type?>&id=<?php echo $v['id']?>" style="color: #0099ff;" >手动开奖</a>
                                        <?php  } elseif($v['state'] !== '0' && $v['state'] !== '1') { ?>
                                            <?php echo $v['admin']?>-处理中
                                        <?php  } ?>
                                        <?php  if($v['state'] == 1) { ?>
                                            <?php echo $v['admin']?>-手动开奖
                                        <?php  } ?>
                                        <?php  if($v['is_call_back']==1) { ?>
                                            <a href="javascript:;" style="color: #0099ff;" onclick="order_back(<?php echo $lottery_type?>,<?php echo $v['issue']?>)">回滚</a>
                                        <?php  } elseif($callback_supper==1 && $v['cbadmin']=='') { ?>
                                            <a href="javascript:;" style="color: #0099ff;" onclick="order_back(<?php echo $lottery_type?>,<?php echo $v['issue']?>)">回滚</a>
                                        <?php  } ?>
                                        <?php  if($v['cbadmin']!='') { ?>
                                            <?php echo $v['cbadmin']?>--回滚
                                            <?php  if($callback_supper==1) { ?>
                                                | <a href="javascript:;" style="color: #0099ff;" onclick="order_back(<?php echo $lottery_type?>,<?php echo $v['issue']?>)">回滚</a>
                                            <?php  } ?>
                                        <?php  } ?>
                                    </td>
                                </tr>
                                <?php  } } ?>                            </tbody>
                        </table>

                        <div class="row foot_page">
                            <div class="col-sm-12" style="<?php  if($show == '') { ?>display:none;<?php  } ?>">
                                <?php echo  $show;?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            var loading = "";//加载中......

            function order_back(lt,lid) {
                $.ajax({
                    type:"post",
                    url:"?m=admin&c=orders&a=order_call_back",
                    data:{"lottery_type":lt,"issue":lid},
                    dataType:"json",
                    beforeSend:function(){
                        loading = layer.load(1);
                    },
                    success:function(data){
                        layer.close(loading);
                        alert(data.msg);
                        if(data.err==0){
                            window.location.reload();
                        }
                    },
                    complete:function(XMLHttpRequest,textStatus){
                        layer.close(loading);
                    },
                    error:function(data){
                        alert('失败');
                    }
                })
            }
            //列表搜索
            function index() {
                var url = "<?php echo url('','','')?>" + '&' + $("#form").serialize();
                location.href = url;
            }
        </script>
    </body>
</html>