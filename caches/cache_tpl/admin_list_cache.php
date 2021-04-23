<?php  !defined('IN_SNYNI') && die('Access Denied!');?>
<!DOCTYPE html>
<html lang="zh-cn">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
        <title></title>
        <script type="text/javascript">var qingzzStartTime1 = new Date().getTime();</script>
        <link rel="stylesheet" href="statics/admin/css/pintuer.css">
        <link rel="stylesheet" href="statics/admin/css/admin.css">
        <link rel="stylesheet" href="statics/admin/js/layer/2.1/skin/layer.css">
        <link rel="stylesheet" href="statics/admin/js/layer/2.1/skin/layer.ext.css">
        <script src="statics/admin/js/jquery.js"></script>
        <script src="statics/admin/js/layer/2.1/layer.js"></script>
        <script src="statics/admin/js/layer/2.1/extend/layer.ext.js"></script>
        <script src="statics/admin/js/pintuer.js"></script>
        <link rel="stylesheet" href="statics/admin/css/datePicker.css">
        <?php  include template('public-new-ui-header'); ?>
        <script src="statics/admin/js/jquery.min.js" type="text/javascript"></script>
        <script src="statics/admin/js/jquery.date_input.pack.js"></script>   	
        <style type='text/css'>
           /* #page{height: 60px;margin-top: 20px;text-align: center;}
            #page ul li{float: left;margin-right:10px;}
            #page ul .current{ background-color:#0099ff;text-align:center;}
            .table td div.username{
                height: 23px;
                overflow: hidden;
                white-space:nowrap;
                text-overflow: ellipsis;
            }*/
            .col-sm-2.pos-un,
            .input-group.pos-un {position: unset;}
            .dchyDlg{width: 60%;margin: 0 auto;}
           .dchyDlg input{margin: 10px 0px;width: 100%;}

        </style>
    </head>

    <?php 
    $view_title = '';
    if(isset($_GET['flag']) && $_GET['flag']==1){
        if ($data['team'] != '') {
            $view_title = '团队在线会员列表';
        } else {
            $view_title = '在线会员列表';
        }
    }else{
        if ($data['team'] != '') {
            $view_title = '团队会员列表';
        } else {
            $view_title = '会员列表';
        }
    }?>
    <body class="new_ui_body">
        <div class="row">
            <div class="col-sm-12">
                <div class="ibox float-e-margins">
                    <div class="ibox-title iboxWTitle">
                        <h5><?php echo $view_title?></h5>
                        <div class="ibox-tools">
                            <button onclick="window.open(location.href)" class="btn btn-white btn-bitbucket">
                                <i class="fa fa-plus-square-o"> </i> 新页面打开
                            </button>
                            <button onclick="location.reload();" class="btn btn-white btn-bitbucket">
                                <i class="fa fa-repeat"> </i> 刷新页面
                            </button>
                            <a href="?m=admin&c=user&a=dummy" onclick="prohibitClick()" class="btn btn-white btn-bitbucket">
                                <i class="fa fa-plus-square-o"> </i> 手动新增假人
                            </a>
                            <a href="?m=admin&c=user&a=adddummy" class="btn btn-white btn-bitbucket">
                                <i class="fa fa-plus-square-o"> </i> 自动新增假人
                            </a>
                            <?php  if($data['team']>0) { ?>
                            <a onclick="history.back()"  class="btn btn-white btn-bitbucket">
                                <i class="fa fa-reply"> </i> 返回
                            </a>
                            <?php  } ?>
                        </div>
                    </div>
                    <div class="ibox-content" style="width: 100%;">
                        <div class="row">
                            <form action="" id="form" class="form-inline">

                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">会员账号</span>
                                        <input type="text" name="username" placeholder="会员账号" class="form-control" id="username" value="<?php echo $data['username']?>">
                                    </div>
                                </div>
                                <!-- <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">会员姓名</span>
                                        <input type="text" name="realname" placeholder="会员姓名" class="form-control" id="realname">
                                    </div>
                                </div> -->
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">会员昵称</span>
                                        <input type="text" name="nickname" placeholder="会员昵称" class="form-control" id="nickname" value="<?php echo $data['nickname']?>">
                                    </div>
                                </div>

                                <?php  if(in_array(2,$show_user_info)) { ?>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">会员微信</span>
                                        <input type="text" name="weixin" placeholder="会员微信" class="form-control" id="weixin" value="<?php echo $data['weixin']?>">
                                    </div>
                                </div>
                                <?php  } ?>

                                <?php  if(in_array(3,$show_user_info)) { ?>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">会员手机</span>
                                        <input type="text" name="mobile" placeholder="会员手机" class="form-control" id="mobile" value="<?php echo $data['mobile']?>">
                                    </div>
                                </div>
                                <?php  } ?>
                                <?php  if(in_array(1,$show_user_info)) { ?>
                                <!-- <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">银行名</span>
                                        <input type="text" name="bankname" placeholder="银行名" class="form-control" id="bankname" value="<?php echo $data['bankname']?>">
                                    </div>
                                </div> -->
                                <?php  } ?>

                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">银行账号</span>
                                        <input type="text" name="bankaccount" placeholder="银行账号" class="form-control" id="bankaccount" value="<?php echo $data['bankaccount']?>">
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">常用IP</span>
                                        <input type="text" name="loginip" placeholder="常用IP" class="form-control" id="loginip" value="<?php echo $data['loginip']?>">
                                    </div>
                                </div>
                                <div class="col-sm-2 pos-un">
                                    <div class="input-group m-b pos-un"><span class="input-group-addon">注册时间</span>
                                        <input type="text" name="regtime" placeholder="注册时间" class="form-control" id="datePicker" value="<?php echo $data['regtime']?>">
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">最后登录域名</span>
                                        <input type="text" name="last_login_source" placeholder="最后登录域名" class="form-control" id="last_login_source" value="<?php echo $data['last_login_source']?>">
                                    </div>
                                </div>
                                <?php  if(in_array(1,$show_user_info)) { ?>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">会员姓名</span>
                                        <input type="text" name="realname" placeholder="会员姓名" class="form-control" id="realname" value="<?php echo $data['realname']?>">
                                    </div>
                                </div>
                                <?php  } ?>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">状态</span>
                                    <select name="state" class="form-control" id="state">
                                        <option value="">全部</option>
                                        <option value="0" <?php  if($data['state']==='0') { ?> selected <?php  } ?>>正常</option>
                                        <option value="1" <?php  if($data['state']==1) { ?> selected <?php  } ?>>监控</option>
                                        <option value="2" <?php  if($data['state']==2) { ?> selected <?php  } ?>>禁用</option>
                                    </select>
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">会员组</span>
                                    <select name="group_id" class="form-control" id="group_id">
                                        <option value="">全部</option>
                                        <?php  if(is_array($group)) { foreach($group as $v) { ?>                                        <option value="<?php echo $v['id']?>" <?php  if($data['group_id']==$v['id']) { ?> selected<?php  } ?>><?php echo $v['name']?></option>
                                        <?php  } } ?>                                    </select>
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">会员层级</span>
                                    <select name="layer_id" class="form-control" id="layer_id">
                                        <option value="">全部</option>
                                        <?php  if(is_array($layer)) { foreach($layer as $l) { ?>                                        <option value="<?php echo $l['layer']?>" <?php  if($data['layer_id']==$l['layer']) { ?> selected<?php  } ?>><?php echo $l['layer']?></option>
                                        <?php  } } ?>                                    </select>
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">最后登录时间</span>
                                    <select name="lastlogintime" class="form-control" id="lastlogintime">
                                        <option value="">选择</option>
                                        <option <?php  if($data['lastlogintime']==='86400') { ?> selected <?php  } ?> value="86400">一天</option>
                                        <option <?php  if($data['lastlogintime']==='604800') { ?> selected <?php  } ?> value="604800">一周</option>
                                        <option <?php  if($data['lastlogintime']==='2592000') { ?> selected <?php  } ?> value="2592000">一月</option>
                                        <option <?php  if($data['lastlogintime']==='7776000') { ?> selected <?php  } ?> value="7776000">一季</option>
                                    </select>
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">用户类型</span>
                                    <select name="rg_type" class="form-control" id="rg_type">
                                        <option value="0" <?php  if($data['rg_type']=='') { ?> selected <?php  } ?>>正常用户</option>
                                        <option value="8" <?php  if($data['rg_type']==8) { ?> selected <?php  } ?>>游客</option>
                                        <option value="11" <?php  if($data['rg_type']==11) { ?> selected <?php  } ?>>假人</option>
                                    </select>
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">在线状态</span>
                                    <select name="online" class="form-control" id="online">
                                        <option value="">全部</option>
                                        <option value="1" <?php  if($data['online']==1) { ?> selected <?php  } ?>>在线</option>
                                        <option value="2" <?php  if($data['online']==2) { ?> selected <?php  } ?>>离线</option>
                                    </select>
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">排序</span>
                                    <select name="sort" class="form-control" id="sort">
                                        <option value="0">选择</option>
                                        <option value="1" <?php  if($data['sort']==1) { ?> selected <?php  } ?>>注册时间</option>
                                        <option value="2" <?php  if($data['sort']==2) { ?> selected <?php  } ?>>最后登录时间</option>
                                        <option value="3" <?php  if($data['sort']==3) { ?> selected <?php  } ?>>现金余额</option>
                                        <option value="4" <?php  if($data['sort']==4) { ?> selected <?php  } ?>>历史充值次数</option>
                                    </select>
                                    </div>
                                </div>
								
								<div class="col-sm-2">
                                    <div class="input-group m-b"><span class="input-group-addon">统计</span>
                                    <select name="filter" class="form-control" id="filter">
                                        <option value="0">选择</option>
                                        <option value="1" <?php  if($data['filter']==1) { ?> selected <?php  } ?>>今日历史首存人数</option>
                                        <option value="2" <?php  if($data['filter']==2) { ?> selected <?php  } ?>>今日首存用户</option>
                                    </select>
                                    </div>
                                </div>

                                <div class="col-sm-2">
                                    <div class="input-group m-b">
                                        <!-- <span class="input-group-addon">按钮</span> -->
                                        <button type="button" class="btn btn-primary" id="submit_btn">搜索</button>
                                        &nbsp;
                                        <button type="button" class="btn btn-primary" id="cancel_btn">重置</button>
                                        
                                    </div>
                                </div>
								 <div class="col-sm-8">
                                    <div class="input-group m-b">
                                        
                                        <button type="button" class="btn btn-primary" id="getValue">设置会员层级</button>
                                        &nbsp;
                                        <button type="button" class="btn btn-primary" id="getGroupValue">设置会员组</button>
                                         &nbsp;
                                        <button type="button" class="btn btn-primary" id="class_btn">修改会员上级</button>
                                        <?php  if($ex_role==1 && $exportDisplay==1) { ?>
										&nbsp;
                                        <button type="button" class="btn btn-primary" id="export_btn">导出会员信息</button>
                                        <?php  } ?>
                                    </div>
                                </div>
                                <div class="form-group hidden">
                                    <label for="sort">排序：</label>
                                    <input type="text"  name="team" value="<?php echo $data['team']?>"/>
                                </div>
                            </form>
                        </div>

                        <div id="editable_wrapper" class="dataTables_wrapper form-inline" role="grid" style="width: 100%">
                            <table class="table table-striped table-bordered table-hover" id="editable" aria-describedby="editable_info">
                                <thead>
                                <tr>
                                    <th>ID&nbsp;<input type="checkbox" id="all">全选</th>
                                    <th>账号</th>
                                    <th>姓名</th>
                                    <th>直属上级</th>
                                    <!-- <th>手机号</th> -->
                                    <!-- <th>直属上级</th> -->
                                    <!-- <th>卡号</th> -->
                                    <th>可用余额</th>
                                    <th>冻结金额</th>
                                    <?php  if($data['sort'] == 4) { ?>
                                    <th>充值次数</th>
                                    <?php  } ?>
                                    <!-- <th>常用IP</th> -->
                                    <th>会员组</th>
                                    <!-- <th>会员层级</th> -->
                                    <th>状态</th>
                                    <th>注册时间</th>
                                    <th>最后登录时间</th>
                                    <th>最后登录IP</th>
                                    <th>登录IP归属地</th>
                                    <!-- <th>最后登录设备</th> -->
                                    <!-- <th>最后登录域名</th> -->
                                    <!-- <th>用户来源</th> -->
                                    <th>团队</th>
                                    <th>直属会员</th>
                                    <th>操作</th>
                                </tr>
                                </thead>
                                <tbody id="list">

                                <?php  foreach($userinfo as $k=>$v){?>
                                <tr>
                                    <td><input type="checkbox" value="<?php echo $v['id']?>"><?php echo $v['id']?></td>
                                    <td><a onclick="detail('<?php echo $v['id']?>')" href="javascript:;" style="color: #0099ff;"><div class="username"><?php echo $v['username']?></div></a></td>
                                    <?php  if(in_array(1,$show_user_info)) { ?>
                                    <td><?php echo $v['realname']?></td>
                                    <td><?php echo $v['parent_name']?></td>
                                    <?php  } else { ?>
                                    <td></td>
                                    <td></td>
                                    <?php  } ?>
                                    <!-- <?php  if(in_array(3,$show_user_info)) { ?>
                                    <td><?php echo  decrypt($v['mobile']);?></td>
                                    <?php  } else { ?>
                                    <td></td>
                                    <?php  } ?>
                                    <td title="<?php echo $v['parent']?>"><div class="username"><?php echo $v['parent']?></div></td>
                                    <td><?php echo  decrypt($v['account']);?></td> -->
                                    <td><?php echo $v['money']?></td>
                                    <td><?php echo $v['money_freeze']?></td>
                                    <?php  if($data['sort'] == 4) { ?>
                                    <td><?php echo $v['count']?></td>
                                    <?php  } ?>
                                    <!-- <td><?php  if($v['login_ip_attribution'] == '') { ?> <?php echo $v['loginip']?> <?php  } else { ?> <?php echo $v['loginip']?>(<?php echo $v['login_ip_attribution']?>)<?php  } ?></td> -->
                                    <td><?php echo $v['name']?></td>
                                    <!-- <td><?php echo $v['layer_id']?></td> -->
                                    <td><?php echo $v['state_str']?></td>
                                    <td><?php echo $v['regtime']?></td>
                                    <td><?php echo $v['logintime']?></td>
                                    <td><?php echo $v['loginip']?></td>
                                    <td><?php echo $v['login_ip_attribution']?></td>
                                    <!-- <td>
                                        <?php  if($v['device_flag'] == '1') { ?>
                                            iOS
                                        <?php  } elseif($v['device_flag'] == '2') { ?>
                                            Android
                                        <?php  } elseif($v['device_flag'] == '3') { ?>
                                            H5
                                        <?php  } else { ?>
                                            --
                                        <?php  } ?>
                                    </td>
                                    <td><?php echo $v['last_login_source']?></td>
                                    <td><?php echo $v['source']?></td> -->
                                    <td><a onclick="team('<?php echo $v['id']?>')" href="javascript:;" style="color: #0099ff;">查看</a></td>
                                    <td><a onclick="leaguer('<?php echo $v['id']?>')" href="javascript:;" style="color: #0099ff;">查看</a></td>
                                    <td class="font-icon">
                                        <a href="?m=admin&c=user&a=update_user&id=<?php echo $v['id']?>" style="color: #0099ff;" data-title="资料修改"><i class="fa fa-reorder"></i></a>
                                        <a href="?m=admin&c=user&a=update_user_pass&id=<?php echo $v['id']?>" style="color: #0099ff;" data-title="重置登录密码"><i class="fa fa-unlock-alt"></i></a>
                                        <a href="?m=admin&c=user&a=update_user_repaypass&id=<?php echo $v['id']?>" style="color: #0099ff;" data-title="重置资金密码"><i class="fa fa-key"></i></a>
                                        <a href="?m=admin&c=user&a=user_bank&id=<?php echo $v['id']?>" style="color: #0099ff;" data-title="银行卡修改"><i class="fa fa-credit-card"></i></a>
                                        <a href="?m=admin&c=user&a=adjust&user_id=<?php echo $v['id']?>&name=<?php echo $v['username']?>" style="color: #0099ff;" data-title="额度调整"><i class="fa fa-sliders"></i></a>
                                        <?php  if($v['state'] == 1) { ?>
                                        <a href="javascript:;" onclick="fx(0, <?php echo $v['id']?>)" style="color: #0099ff;" data-title="解除风险会员"><i class="fa fa-flag"></i></a>
                                        <?php  } else { ?>
                                        <a href="javascript:;" onclick="fx(1, <?php echo $v['id']?>)" style="color: #0099ff;" data-title="标记风险会员"><i class="fa fa-flag-o"></i></a>
                                        <?php  } ?>

                                        <?php  if($v['state'] == 2) { ?>
                                            <a href="javascript:;" onclick="jd(0, <?php echo $v['id']?>);" style="color: #0099ff;" data-title="解封账号"><i class="fa fa-frown-o"></i></a>
                                        <?php  } else { ?>
                                            <a href="javascript:;" onclick="jd(2, <?php echo $v['id']?>);" style="color: #0099ff;" data-title="冻结账号"><i class="fa fa-smile-o"></i></a>
                                        <?php  } ?>

                                        <a href="javascript:;" onclick="offUser(<?php echo $v['id']?>)" style="color: #0099ff;" data-title="强制踢线"><i class="fa fa-chain-broken"></i></a>
                                    </td>
                                </tr>
                                <?php  }?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row foot_page">
                            <div class="col-sm-6">
                                <div class="dataTables_info" id="editable_info" role="alert" aria-live="polite"
                                     aria-relevant="all">
                                     <span class="back-page">当前页现金余额：<?php echo $countNum?></span>
                        			 <span class="back-page" id="totalPage"></span>
                                </div>
                            </div>
                            <div class="col-sm-6" id='page'>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" class="run_time_value" data-info='<?php echo  json_encode($post_run_data);  ?>'/>


        <script type="text/javascript">
            loading = '';
            function offUser(id)
            {
                layer.confirm('是否将该用户强制踢线？', {icon: 3, title:'确认'}, function(){
                    var data = {'uid':id,}
                    $.ajax({
                        url: "?m=admin&c=user&a=forcedKick",
                        data:data,
                        dataType: 'json',
                        type: 'post',
                        beforeSend: function () {
                            loading = layer.load(1);
                        },
                        error: function () {
                            layer.close(loading);
                            layer.msg('服务器错误！！！', {icon: 5, shade: [0.5, '#393D49']});
                        },
                        success: function (data) {
                            if(data['code'] != 0)
                            {
                                layer.msg(data['msg'], {icon: 5, shade: [0.5, '#393D49']}, function () {
                                    return false;
                                });
                            }
                            else
                            {
                                layer.msg(data['msg'], {icon: 6, shade: [0.5, '#393D49']}, function () {
                                    location.href = "?m=admin&c=user&a=lst";
                                });
                            }
                        },
                        complete:function(){
                            layer.close(loading);
                        }
                    });
                });
            }
            // function addroob(){
            //     $.ajax({
            //         url: '?m=admin&c=user&a=addroob',
            //         dataType: 'json',
            //         type: 'post',
            //         beforeSend: function () {
            //             loading = layer.load(1);
            //         },
            //         error: function (e) {
            //             layer.close(loading);
            //             console.log(e);
            //             layer.msg('网络错误！！！', {icon: 5,shade: [0.5, '#393D49']});
            //         },
            //         success: function (result) {
            //             console.log(result);
                        
            //         }
            //     });
            // }
            //会员详情
            function detail(id){
                location.href="?m=admin&c=user&a=detail&id=" + id;
            }
            
            //团队记录
            function team(id){
                location.href = "?m=admin&c=user&a=lst&team=" +　id;
            }
            
          //直属会员记录
            function leaguer(id){
                location.href = "?m=admin&c=user&a=leaguerList&type=1&team=" +　id;
            }
            
            // leaguer
            //检测全选
            function allchk(){
                var chknum = $("#list :checkbox").size();//选项总个数
                var chk = 0;
                $("#list :checkbox").each(function () {
                    if($(this).prop("checked")==true){
                        chk++;
                    }
                });
                if(chknum==chk){//全选
                    $("#all").prop("checked",true);
                }else{//不全选
                    $("#all").prop("checked",false);
                }
            }
            function getTotal(obj){
                // var obj = $(obj).parent();
                var data = {
                    'data' : $("#form").serialize(),
                    'type' : 4
                };
                $.ajax({
                    url: '?m=admin&c=finance&a=statisticsData',
                    data: data,
                    dataType: 'json',
                    type: 'post',
                    beforeSend: function () {
                        loading = layer.load(1);
                    },
                    error: function (e) {
                        layer.close(loading);
                        console.log(e);
                        layer.msg('网络错误！！！', {icon: 5,shade: [0.5, '#393D49']});
                    },
                    success: function (result) {
                        console.log(result);
                        layer.close(loading);
                        if (result.code == '0') {
                            var html = "<span class='back-page' style='margin-left: 20px;"+result.data[0].auth_style+"'>总计现金余额：</span>" +
                                "<span style='padding-right: 20px;"+result.data[0].auth_style+"'>"+result.data[0].countMoney+"</span>" ;
                            $(obj).parent().html(html);
                        } else {
                            layer.msg(result.msg, {icon: 5,shade: [0.5, '#393D49']});
                        }
                    }
                });
            }
            
            function listPage() {
                if(<?php echo $count_data?>==1){
                    return;
                }
            	var listPage = '';
            	var url = "?m=admin&c=user&a=listPage";
            	var data = JSON.parse('<?php echo  json_encode($data) ?>')
            	$.ajax({
                    url: url,
                    data:data,
                    dataType: 'json',
                    type: 'post',
                    beforeSend: function () {
                    	$('#page').append('<span id="loading" style="color: lavender;border:0px;font-style: italic;margin-left: 20px;">玩命加载中...</span>');
                    	$('#listPage').append('<span id="totaloading" style="color: lavender;border:0px;font-style: italic;margin-left: 20px;">玩命加载中...</span>');
                    },
                    error: function () {
                    	$('#totaloading').remove();
                    	$('#loading').remove();
                        $('#listPage').after('<span>汇总数据加载失败，请重新刷新当前页面.</span>')
                        $('#page').append('<span>汇总数据加载失败，请重新刷新当前页面.</span>')

                    },
                    success: function (data) {
                    	console.log(data);
                        if(data['code'] != 0)
                        {
                        	$('#totaloading').remove();
                        	$('#loading').remove();
                        	$('#listPage').after('<span>汇总数据加载失败，请重新刷新当前页面.</span>')
                            $('#page').append('<span>汇总数据加载失败，请重新刷新当前页面.</span>')
                        }
                        else
                        {
                        	$('#totaloading').remove();
                        	$('#loading').remove();
                        	var listPage = '<span style="margin-left: 20px;"><a href="javascript:void(0)" onclick="getTotal(this)">点击查看总计现金余额</a></span>';
                        	$('#totalPage').after(listPage);
                            $('#page').append(data.data.show);
                            pageNum = data.data.pagecount;
                        }
                    }
                });
            	
            }
            
            //时间插件
            $(function () {
            	listPage();
                //全选或全不选
                $("#all").click(function(){
                    if(this.checked){
                        $("#list :checkbox").prop("checked", true);
                    }else{
                        $("#list :checkbox").prop("checked", false);
                    }
                });
                //设置全选复选框
                $("#list :checkbox").click(function(){
                    allchk();
                });

                //获取选中选项的值
                $("#getGroupValue").click(function(){
                    var valArr = new Array;
                    $("#list :checkbox[checked]").each(function(i){
                        valArr[i] = $(this).val();
                    });
                    var vals = valArr.join(',');
                    if(vals == ''){
                        layer.msg("请选择要设置的用户！！！", {icon: 5, shade: [0.5, '#393D49']});
                        return false;
                    }
                    var index = layer.open({
                        type: 2,
                        area: ['800px','500px'],
                        title: "设置会员组",
                        content: "<?php echo url('','',setUserGroup)?>"+"&ids="+vals,
                        end:function () {
                            location.reload(true);
                        }
                    });
//                    layer.full(index);
                });


                //获取选中选项的值
                $("#getValue").click(function(){
                    var valArr = new Array;
                    $("#list :checkbox[checked]").each(function(i){
                        valArr[i] = $(this).val();
                    });
                    var vals = valArr.join(',');
                    if(vals == ''){
                        layer.msg("请选择要设置的用户！！！", {icon: 5, shade: [0.5, '#393D49']});
                        return false;
                    }
                    var index = layer.open({
                        type: 2,
                        title: "设置会员层级",
                        area: ['800px','500px'],
                        content: "<?php echo url('','',setUserLayer)?>"+"&ids="+vals,
                        end:function () {
                            location.reload(true);
                        }
                    });
//                    layer.full(index);
                });

                $('#datePicker').date_input();
                $("#submit_btn").click(function () {
                    var regtime = $("#datePicker").val();
                    var reg = /^(\d{4})-(0\d{1}|1[0-2])-(0\d{1}|[12]\d{1}|3[01])$/;
                    if(regtime != "" && !reg.test(regtime)){
                        layer.msg('注册时间格式不正确！！！', {icon: 5, shade: [0.5, '#393D49']});
                        return false;
                    }
                    location.href = "?m=admin&c=user&a=lst&" + $("#form").serialize();
                });
                $("#cancel_btn").click(function () {
                    location.href = "?m=admin&c=user&a=lst";
                });
                $(".table .username").click(function(){
                    $(this).toggleClass("username");
                });
                
                
                $("#hplus").click(function () {
                    location.href = "?m=admin&c=user&a=hplus";
                });
                $("#bootstrap").click(function () {
                    location.href = "?m=admin&c=user&a=bootstrap";
                });
                
                //绑定上下级关系
                $("#class_btn").click(function () {
                	 var valArr = new Array;
                     $("#list :checkbox[checked]").each(function(i){
                         valArr[i] = $(this).val();
                     });
                     var vals = valArr.join(',');
                     if(vals == ''){
                         layer.msg("请选择要设置的用户！！！", {icon: 5, shade: [0.5, '#393D49']});
                         return false;
                     }
                     var index = layer.open({
                         type: 2,
                         title: "修改会员上级",
                         area: ['800px','500px'],
                         content: "<?php echo url('','',setUserClass)?>"+"&ids="+vals,
                         end:function () {
                             location.reload(true);
                         }
                     });
                });
            });

            $("#export_btn").click(function () {
                layer.open({
                    type: 1,
                    skin: 'layui-layer-rim', //加上边框
                    area: ['420px', '280px'], //宽高
                    content: '<div class="dchyDlg"><div>选择日期：</div><input type="date" id="date"><div>输入密码：<input id="pws" type="text"  class="input form-control" /></div></div>',
                    btn: ['确定', '取消'],
                    yes: function () {
                        $.ajax({
                            url: '?m=admin&c=user&a=export_userinfo',
                            dataType: 'json',
                            data:{pws:$('#pws').val()},
                            type: 'post',
                            beforeSend: function () {
                                loading = layer.load(1);
                            },
                            error: function () {
                                layer.close(loading);
                                layer.msg('网络异常，稍后再试！！！', {icon: 5, shade: [0.5, '#393D49']});
                            },
                            success: function (result) {
                                layer.close(loading);
                                if (result.code==1) {
                                    layer.msg(result.msg, {icon: 5, shade: [0.5, '#393D49']}, function () {
                                    });
                                } else {

                                    var date = $('#date').val();
                                    window.location.href = "?m=admin&c=user&a=do_export_userinfo&date="+date;
//                            if (result.msg) {
//                                layer.msg(result.msg, {icon: 5, shade: [0.5, '#393D49']}, function () {
//                                    location.reload();
//                                });
//                            }
                                }
                            }
                        });
                    }
                });


            });


            //操作风险会员
            function fx(state, user_id) {
                var msg = (state==0)?"确定解除风险会员吗?":"确定标记风险会员吗?";
                layer.confirm(msg, function(index){
                    $.ajax({
                        type: 'GET',
                        url: "?m=admin&c=user&a=biaoji&id=" + user_id + "&state=" + state,
                        success: function () {
                            layer.msg('设置成功！！！', {icon: 6, shade: [0.5, '#393D49']},function(){
                                location.reload();
                            });
                        }
                    });
                    layer.close(index);
                });
            }

            //冻结账号
            function jd(state, user_id) {
                var msg = (state==0)?"确定解封账号吗?":"确定冻结账号吗?";
                layer.confirm(msg, function(index){
                    //do something
                    $.ajax({
                        type: 'GET',
                        url: '?m=admin&c=user&a=biaoji&id=' + user_id + '&state=' + state,
                        success: function () {
                            layer.msg('设置成功！！！', {icon: 6, shade: [0.5, '#393D49']},function(){
                                location.reload();
                            });
                        }
                    });
                    layer.close(index);
                });

            }
        </script>
    </body>
</html>