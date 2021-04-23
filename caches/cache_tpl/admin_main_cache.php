<?php  !defined('IN_SNYNI') && die('Access Denied!');?>
<!DOCTYPE html>
<html lang="zh-cn">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
        <link rel="stylesheet" href="statics/admin/css/pintuer.css">
        <link rel="stylesheet" href="statics/admin/css/admin.css">
        <script src="statics/admin/js/jquery.js"></script>
        <!-- <script src="statics/admin/js/jquery.min.js" type="text/javascript"></script> -->
        <script src="statics/admin/js/pintuer.js"></script>  
        <script src="statics/admin/js/layer/2.1/layer.js"></script>
        <link rel="stylesheet" href="statics/admin/jedate/skin/jedate.css">
        <script type="text/javascript" src="statics/admin/jedate/jquery.jedate.min.js"></script>
        <?php  include template('public-new-ui-header'); ?>
        <style type="text/css">
            #page{height: 60px;margin-top: 20px;text-align: center;}
            #page ul li{float: left;margin-right:10px;}
            #page ul .current{ background-color:#0099ff;text-align:center;}
        </style>
    </head>
    <body class="new_ui_body">
	    <div class="ibox float-e-margins">
		    <div class="ibox-title iboxWTitle">
		        <h5>房间内在线人数统计</h5>
	        </div>
	   		<div class="ibox float-e-margins">
		       	<div class="ibox-content" style="width: 100%;">
		           <div class="row">
					   <form method="post" id="form" action="">
		               <div class="col-sm-2">
						   <div class="input-group m-b"><span class="input-group-addon">彩种类型</span>
							   <select name="lottery_type" id="lottery_type" class="input form-control">
								   <option <?php  if($lottery_type==0 ) { ?> selected <?php  } ?> value=0>所有彩种</option>
								   <?php  if(is_array($lottery_map)) { foreach($lottery_map as $lottery_map_k => $lottery_map_v) { ?>   <option value="<?php echo $lottery_map_k?>" <?php  if($lottery_type==$lottery_map_k) { ?> selected <?php  } ?>><?php echo $lottery_map_v?></option><?php echo $lottery_map_k?>
								   <?php  } } ?>   </select>
						   </div>
		               </div>
		               <div class="col-sm-1">
		               		<div class="input-group m-b">
	               				<button id="s_btn" class="btn btn-primary" type="button">搜索</button>
                            </div>
		               </div>	
	               </form>
				   </div>
				   <div id="editable_wrapper" class="dataTables_wrapper form-inline" role="grid" style="width: 100%">
   	                <table class="table table-striped table-bordered table-hover  dataTable" id="editable" aria-describedby="editable_info">
	  	                <thead>
						<tr role="row">
							<th>序号</th>
							<th>彩种房间</th>
							<th>房间在线人数</th>
							<th>在线房间人数占比</th>
							<th>当日房间总投注</th>
							<th>操作</th>
						</tr>
						</thead>
                    	<tbody>
                    	<tr class="odd" style="display: none"></tr>
                    <?php  if(is_array($totals)) { foreach($totals as $k => $v) { ?>                        <tr>
                            <td><?php echo $k?></td>
							<td><?php echo $v['title']?></td>
							<td><?php echo $v['person']?></td>
							<td><?php echo $v['ray']?></td>
							<td><?php echo $v['bet']?></td>
							<td onclick="getInfo('<?php echo $k?>')">查看房间详情</td>
                        </tr>
                        <?php  } } ?></tbody>
					</table>
				   </div>
				   <div class="row">
					   <div class="col-sm-8">
						   <div class="dataTables_info" id="editable_info" role="alert" aria-live="polite" aria-relevant="all">
							   <span class="back-page" style="margin-right: 20px;">总计</span>
							   <span class="back-page">房间会员人数</span>
							   <span style="padding-right: 20px;"><?php echo $i?></span>
							   <span class="back-page">投注总额</span>
							   <span style="padding-right: 20px;"><?php echo $totalMoney?></span>
							   <!--<span class="back-page total_info">-->
								<!--<a href="javascript:void(0)" class="total">点击查看统计数据</a>-->
							   <!--</span>-->
						   </div>
					   </div>
					   <div class="col-sm-4">
						   <?php echo  $show;?>
					   </div>
				   </div>
      			</div>
   			</div><script type="text/javascript">
//                function s_index() {
////                    var lottery_type = $(this).val();
////                    location.href = "?m=admin&c=default&a=main&lottery_type="+lottery_type;
//                }
                $('#s_btn').click(function () {
                    var lottery_type = $('#lottery_type').val();
                    location.href = "?m=admin&c=default&a=main&lottery_type="+lottery_type;
				});

                var loading = "";//加载中......
                function getInfo(id) {
                    console.log(id);

                    $.ajax({
                        url: '?m=admin&c=default&a=getInfo',
                        data: {"roomid":id},
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
                            if (result.code==0) {
                                var html = '<div class="ibox-content" style="width: 100%;">' +
                                    '<div class="row">' +
                                    '<div class="col-sm-5">\n' +
                                    '<div class="input-group m-b"><span class="input-group-addon">会员账号</span>\n' +
                                    '<input type="text" name="username" placeholder="" class="form-control" id="username_sub" value="">' +
                                    '</div>' +
                                    '</div>' +
                                    '<div class="col-sm-1">\n' +
                                    '<div class="input-group m-b">\n' +
                                    '<button onclick="s_sub()" class="btn btn-primary" type="button">搜索</button>' +
                                    '</div>' +
                                    '</div>' +
                                    '</form>' +
                                    '</div>' +
                                    '<table class="table table-striped table-bordered table-hover  dataTable" id="editable_sub" aria-describedby="editable_info">' +
                                    '<thead>' +
                                    '<tr role="row">\n' +
                                    '<th>序号</th>' +
                                    '<th>会员帐号</th>' +
                                    '<th>当日房间总投注</th>' +
                                    '</tr>' +
                                    '</thead>' +
                                    '<tbody>';
                                for(var key in result.list){
                                    html+='<tr><td>'+key+'</td><td class="name">'+result.list[key].username+'</td><td>'+result.list[key].bet+'</td></tr>';
                                }
                                html += '</tbody> </table>';
                                console.log(result);
                                layer.open({
                                    area: ['850px', '500px'],
                                    closeBtn:2,
                                    title:false,
                                    type: 1,
                                    content:html  //这里content是一个普通的String
                                });
                            } else {
                                if (result.msg) {
                                    layer.msg(result.msg, {icon: 5, shade: [0.5, '#393D49']}, function () {
                                        location.reload();
                                    });

                                }
                            }
                        }
                    });
                }

                function s_sub() {
                    var name = $('#username_sub').val();
                    $("#editable_sub").find(".name").each(function (i) {
                        if($(this).text().indexOf(name) == -1){
                            $(this).parent().hide();
                        }else{
                            $(this).parent().show();
                        }
                    })
                }

                function lookUp(uid,obj) {
                    $.ajax({
                        url: '?m=admin&c=finance&a=lookUp',
                        data: {"uid":uid,"type":2},
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
                            if (result.code==0) {
                                console.log(result);
                                $(obj).html(result.res);
                            } else {
                                if (result.msg) {
                                    layer.msg(result.msg, {icon: 5, shade: [0.5, '#393D49']}, function () {
//                                location.reload();
                                    });

                                }
                            }
                        }
                    });
                }


                $(".total").click(function(){
                    var data = {
                        'data' : $("#form").serialize(),
                        'type' : 2
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
                                var hmtl = "<span class='back-page' style='margin-right: 20px;'>合计</span>" +
                                    "<span class='back-page'>充值成功</span> " +
                                    "<span style='padding-right: 20px;'>"+result.data.succMoney+"</span>" +
                                    "<span class='back-page'>待处理</span> " +
                                    "<span style='padding-right: 20px;'>"+result.data.dealMoney+"</span>" +
                                    "<span class='back-page'>驳回</span> " +
                                    "<span style='padding-right: 20px;'>"+result.data.cancMoney+"</span>"
                                $(".total_info").html(hmtl)
                            } else {
                                layer.msg(result.msg, {icon: 5,shade: [0.5, '#393D49']});
                            }
                        }
                    });
                })

                //列表搜索
                function index() {
                    var time_reg = /^(\d{4})-(0\d{1}|1[0-2])-(0\d{1}|[12]\d{1}|3[01])$/;
                    var s_time = $("#s_time").val();
                    var e_time = $("#e_time").val();
                    if((s_time != '' && !time_reg.test(s_time)) || (e_time != '' && !time_reg.test(e_time))){
                        layer.msg('请输入正确的时间格式！！！', {icon: 5, shade: [0.5, '#393D49']});
                        return false;
                    }
                    if(s_time > e_time){
                        layer.msg('开始日期不能大于结束日期！！！', {icon: 5, shade: [0.5, '#393D49']});
                        return false;
                    }

                    var url = '?m=admin&c=topup&a=topup' + '&' + $("#form").serialize();
                    location.href = url;
                }

                $(function () {
                    $(".date_picker").jeDate({
                        isinitVal:true,
                        festival:true,
                        ishmsVal:true,
                        minDate: '2016-01-01',
                        maxDate: $.nowDate(0),
                        format:"YYYY-MM-DD",
                        zIndex:3000,
                    })

                    var payment_id = "<?php echo $where['payment_id']?>";
                    $("#payment_id option[value='"+payment_id+"']").attr("selected",true);

                    var status = "<?php echo $where['status']?>";
                    $("#status option[value='"+status+"']").attr("selected",true);

                    var is_realname = "<?php echo $where['is_realname']?>";
                    $("#is_realname option[value='"+is_realname+"']").attr("selected",true);
                });

                //自动充值设置
                function setAutoCharge(){
                    location.href = "?m=admin&c=topup&a=setAutoLineRecharge";
                }

			</script>
		</div>
    </body>
</html>
<style type="text/css">
	.layui-layer-page .layui-layer-content{ overflow: inherit;}
	.ibox-content{ height: 100%; overflow: auto;}
</style>