<?php  !defined('IN_SNYNI') && die('Access Denied!');?>
<!DOCTYPE html>
<html lang="zh-cn">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
        <meta name="renderer" content="webkit">
        <title></title>
        <link rel="stylesheet" href="statics/admin/css/pintuer.css">
        <link rel="stylesheet" href="statics/admin/css/admin.css">
        <link rel="stylesheet" href="statics/admin/js/layer/2.1/skin/layer.css">
        <link rel="stylesheet" href="statics/admin/js/layer/2.1/skin/layer.ext.css">
        <script src="statics/admin/js/jquery.js"></script>
        <script src="statics/admin/js/layer/2.1/layer.js"></script>
        <script src="statics/admin/js/layer/2.1/extend/layer.ext.js"></script>
        <script src="statics/admin/js/pintuer.js"></script>
        <?php  include template('public-new-ui-header'); ?>
        <style type='text/css'>
            #page{height: 60px;margin-top: 20px;text-align: center;}
            #page ul li{float: left;margin-right:10px;}
            #page ul .current{ background-color:#0099ff;text-align:center;}
            /*#table tr td{
                text-align:left;
                border:1px solid #ccc;
            }
            #table tr td a{
                color:#2fa4e7;
            }
            #table tr td{
                background-color:#f5f5f5;
            }*/
        </style>
    </head>
    <body class="new_ui_body">

        <div class="row">
            <div class="col-sm-12">                
                <div class="ibox float-e-margins">

                    <div class="ibox-title iboxWTitle">
                        <h5>用户详情信息</h5>
                        <div class="ibox-tools">
                            <a href="javascript:;" class="btn btn-white btn-bitbucket" onclick="javascript:history.go(-1);">
                                <i class="fa fa-reply"></i>返回
                            </a>
                        </div>
                    </div>
                    <div class="ibox-content bagCol" style="width: 100%;">
                        <form action='?m=admin&c=user&a=search_black' method='post'>
                            <!--  -->

                            <div class="ibox-title">
                                <h5>基本信息</h5>
                            </div>
                            <div class="ibox-content m-b" style="width: 100%;">
                                <div class="row">
                                    <div class="col-sm-4 m-b">
                                        <span class="col-sm-6 back-page text-right">账号 </span>
                                        <span class="col-sm-6"> <?php echo $data['username']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">真实姓名 </span>
                                        <span class="col-sm-6"> <?php  if(in_array(1,$show_user_info)) { ?><?php echo $data['realname']?><?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">昵称 </span>
                                        <span class="col-sm-6"> <?php echo $data['nickname']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">现金账户余额 </span>
                                        <span class="col-sm-6"> <?php  if($data['ac_money']=='') { ?> 0.00 <?php  } else { ?> <?php echo $data['ac_money']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">元宝余额 </span>
                                        <span class="col-sm-6"> <?php  if($data['yuanbao']=='') { ?> 0.00 <?php  } else { ?> <?php echo $data['yuanbao']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">历史充值总额 </span>
                                        <span class="col-sm-6"> <?php  if($data['cntRecharge']=='') { ?>0.00 <?php  } else { ?> <?php echo $data['cntRecharge']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">历史总盈亏 </span>
                                        <span class="col-sm-6"> <?php  if($data['cntBetProfit']=='') { ?> 0.00 <?php  } else { ?> <?php echo $data['cntBetProfit']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">历史提现总额 </span>
                                        <span class="col-sm-6"> <?php  if($data['ztx']=='') { ?>0.00 <?php  } else { ?> <?php echo $data['ztx']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">今日投注 </span>
                                        <span class="col-sm-6"> <?php  if($data['tz']=='') { ?>0.00 <?php  } else { ?> <?php echo $data['tz']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">今日中奖 </span>
                                        <span class="col-sm-6"> <?php  if($data['zj']=='') { ?> 0.00 <?php  } else { ?> <?php echo $data['zj']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">今日充值 </span>
                                        <span class="col-sm-6"> <?php  if($data['cz']=='') { ?>0.00 <?php  } else { ?> <?php echo $data['cz']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">今日提现 </span>
                                        <span class="col-sm-6"> <?php  if($data['tx']=='') { ?>0.00 <?php  } else { ?> <?php echo $data['tx']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">本次提款所需打码量 </span>
                                        <span class="col-sm-6"> <?php  if($data['dml'] == '') { ?>0.00 <?php  } else { ?> <?php echo $data['dml']?> <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right">是否真人 </span>
                                        <span class="col-sm-6"> <?php  if($real_man==1) { ?>是<?php  } else { ?>否<?php  } ?> </span>
                                    </div>
                                </div>
                            </div>

                            <div class="ibox-title">
                                <h5>详细信息</h5>
                            </div>
                            <div class="ibox-content m-b" style="width: 100%;">
                                <div class="row">
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 微信号 </span>
                                        <span class="col-sm-6"> <?php  if(in_array(2,$show_user_info)) { ?><?php echo $data['weixin']?><?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 手机号 </span>
                                        <span class="col-sm-6"> <?php  if(in_array(3,$show_user_info)) { ?><?php echo $data['mobile']?><?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> QQ </span>
                                        <span class="col-sm-6"> <?php  if(in_array(5,$show_user_info)) { ?><?php echo $data['qq']?><?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 邮箱 </span>
                                        <span class="col-sm-6"> <?php  if(in_array(4,$show_user_info)) { ?><?php echo $data['email']?><?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 发卡银行 </span>
                                        <span class="col-sm-6"> <?php echo $data['account_name']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 银行卡号 </span>
                                        <span class="col-sm-6"> <?php echo $data['account']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 当前状态 </span>
                                        <span class="col-sm-6"> <?php  if($data['is_online']==0 ) { ?> 离线 <?php  } else { ?> 在线 <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 最后登录时间 </span>
                                        <span class="col-sm-6"> <?php echo  date('Y-m-d H:i:s',$data['logintime']);?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 最后登录设备 </span>
                                        <span class="col-sm-6"> <?php echo $data['device_name']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 注册时间 </span>
                                        <span class="col-sm-6"> <?php echo  date('Y-m-d H:i:s',$data['regtime']);?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 注册IP </span>
                                        <span class="col-sm-6"> <?php echo $data['regip']?><?php  if($data['reg_ip_attribution'] != '') { ?>(<?php echo $data['reg_ip_attribution']?>) <?php  } ?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 注册设备 </span>
                                        <span class="col-sm-6"> <?php echo $data['regtype']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 常用IP </span>
                                        <span class="col-sm-6" id="ip" style="color: #0099ff;cursor: pointer;">查看</span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 会员层级 </span>
                                        <span class="col-sm-6"> <?php echo $data['layer_id']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 最后登录域名 </span>
                                        <span class="col-sm-6"> <?php echo $data['last_login_source']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 用户来源 </span>
                                        <span class="col-sm-6"> <?php echo $data['source']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 备注 </span>
                                        <span class="col-sm-6"> <input id="remark" type="text" name="remark" class="input form-control" value="<?php echo $data['remark']?>" />
                                        <button class="btn btn-primary" type="button" id="remark_btn">提交</button>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="ibox-title">
                                <h5>团队信息</h5>
                            </div>
                            <div class="ibox-content m-b" style="width: 100%;">
                                <div class="row">
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 直属上级 </span>
                                        <span class="col-sm-6"><?php echo $data['parent']?></span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 团队人数 </span>
                                        <span class="col-sm-6"><a  href="?m=admin&c=user&a=lst&team=<?php echo $data['id']?>" style="color: #0099ff;"><?php echo $data['team_sum']?></a></span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 返水 </span>
                                        <span class="col-sm-6"><?php echo $data['cntBack']?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="ibox-title">
                                <h5>积分信息</h5>
                            </div>
                            <div class="ibox-content m-b" style="width: 100%;">
                                <div class="row">
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 累计积分 </span>
                                        <span class="col-sm-6"> <?php echo $data['honor_score']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 累计扣分 </span>
                                        <span class="col-sm-6"> <?php echo $data['lose_score']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 当前荣誉 </span>
                                        <span class="col-sm-6"> <?php echo $honor['name']?> </span>
                                    </div>
                                    <div class="col-sm-4 m-b ">
                                        <span class="col-sm-6 back-page text-right"> 当前积分 </span>
                                        <span class="col-sm-6"> <?php echo $honor['user_score']?> </span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <script type="text/javascript">
            //时间插件
            $(function () {
                $('#remark_btn').click(function () {
                    var remark = $('#remark').val();
                    $.ajax({
                        url: '?m=admin&c=user&a=update_remark',
                        data: {remark:remark,id:'<?php echo $id?>'},
                        dataType: 'json',
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
                            if (result.status>0) {
                                layer.msg(result.msg, {icon: 6, shade: [0.5, '#393D49']}, function () {
//                                    location.reload();
                                });
                            } else {
                                if (result.msg) {
                                    layer.msg(result.msg, {icon: 5, shade: [0.5, '#393D49']});
                                }
                            }
                        }
                    });
                });

                $("#ip").click(function () {
                    layer.open({
                        type: 2,
                        title: '用户常用IP(近20次)',
                        shadeClose: true,
                        shade: 0.8,
                        area: ['820px','70%'],
                        content: '?m=admin&c=user&a=getUserIp&id=<?php echo $id?>' //iframe的url
                    });
                })
            });
            /*
            //冻结账号
            function jd(state) {
                var msg = (state==0)?"确定解封账号吗?":"确定冻结账号吗?";
                layer.confirm(msg, function(index){
                    //do something
                    $.ajax({
                        type: 'GET',
                        url: '?m=admin&c=user&a=biaoji&id=<?php echo $data["id"]?>&state=' + state,
                        success: function () {
                            layer.msg('设置成功！！！', {icon: 6, shade: [0.5, '#393D49']},function(){
                                location.reload();
                            });
                        }
                    });
                    layer.close(index);
                });
            }

            function fx(state) {
                var msg = (state==0)?"确定解除风险会员吗?":"确定标记风险会员吗?";
                layer.confirm(msg, function(index){
                    $.ajax({
                        type: 'GET',
                        url: "?m=admin&c=user&a=biaoji&id=<?php echo $data['id']?>&state=" + state,
                        success: function () {
                            layer.msg('设置成功！！！', {icon: 6, shade: [0.5, '#393D49']},function(){
                                location.reload();
                            });
                        }
                    });
                    layer.close(index);
                });
            }
            */
        </script>
    </body>
</html>
