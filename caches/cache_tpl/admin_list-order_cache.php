<?php  !defined('IN_SNYNI') && die('Access Denied!');?>
<!DOCTYPE html>

<html lang="zh-cn">

    <head>

        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

        <meta http-equiv="X-UA-Compatible" content="IE=edge">

        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

        <script type="text/javascript">var qingzzStartTime1 = new Date().getTime();</script>

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

            div.m-r-some{

                margin-right: -8.5%;

            }

            .back-page>b{

                font-weight: normal;

                color:#656565;

            }

            #roomSelect>option.hide_lottery{

                display: none;

            }

            #roomSelect>option.show_lottery{

                display: inline-block;

            }

            .date_picker.form-control.two_span_inline{

                width: 46%;

                max-width: 220px;

                min-width: 200px;

            }

        </style>

    </head>

    <body class="new_ui_body">

        <div class="row">

            <div class="col-sm-12">

                <div class="ibox float-e-margins">

                    <div class="ibox-title iboxWTitle">

                        <h5>订单列表</h5>

                        <div class="ibox-tools">

                            <a href="?m=admin&c=role&a=fbOrderDelay">修改订单生效时间</a>

                        </div>

                    </div>

                    <div class="ibox-content" style="width: 100%;">

                        <div class="row">

                            <form method="post" id="form" action="">

                                <input type="hidden" id="current_lottery_type" value="<?php echo $where['lottery_type']?>" />

                                <div class="col-sm-2">

                                    <div class="input-group m-b"><span class="input-group-addon">彩种类型</span>

                                        <select name="lottery_type" id="lottery_type" class="input form-control">

                                            <option value="">所有彩种</option>

                                            <?php  if(is_array($lottery_map)) { foreach($lottery_map as $lottery_map_k => $lottery_map_v) { ?>                                                <option value="<?php echo $lottery_map_k?>" <?php  if($where['lottery_type']=='$lottery_map_k') { ?> selected <?php  } ?>><?php echo $lottery_map_v?></option>

                                            <?php  } } ?>                                        </select>

                                    </div>

                                </div>



                                <div class="col-sm-2">

                                    <div class="input-group m-b"><span class="input-group-addon">房间</span>

                                        <select name="room" class="input form-control" id="roomSelect">

                                            <option value="">所有房间</option>

                                            <?php 

                                                foreach ($roomInfo as $val) {

                                                    $selected_str = ($where['room'] == $val['id']) ? 'selected="selected"' : '';

                                                    echo "<option class='hide_lottery' value='{$val['id']}' data-type='{$val['lottery_type']}' {$selected_str}><!-- {$val['lottery_title']}-- -->{$val['title']}</option>";

                                                }

                                            ?>

                                        </select>

                                    </div>

                                </div>



                                <div class="col-sm-2">

                                    <div class="input-group m-b"><span class="input-group-addon">账号</span>

                                        <input type="text" value="<?php echo $where['username']?>" class="input form-control" name="username" placeholder="请输入账号">

                                    </div>

                                </div>



                                <div class="col-sm-2">

                                    <div class="input-group m-b"><span class="input-group-addon">流水号</span>

                                        <input type="text" value="<?php echo $where['order_no']?>" class="input form-control" name="order_no" placeholder="请输入流水号">

                                    </div>

                                </div>



                                <div class="col-sm-2">

                                    <div class="input-group m-b"><span class="input-group-addon">期号</span>

                                        <input type="text" value="<?php echo $where['Issue']?>" class="input form-control" name="Issue" placeholder="请输入期号">

                                    </div>

                                </div>



                                <div class="col-sm-2">

                                    <div class="input-group m-b"><span class="input-group-addon">玩法</span>

                                        <select name="way" class="input form-control" id="way">

                                            <option value="">所有玩法</option>

                                        </select>

                                    </div>

                                </div>



                                <div class="col-sm-2">

                                    <div class="input-group m-b"><span class="input-group-addon">订单状态</span>

                                        <select class="input form-control" id="award_state" name="award_state">

                                            <option value="">所有状态</option>

                                            <option value="0">待开奖</option>

                                            <option value="1">未中奖</option>

                                            <option value="2">已中奖</option>

                                            <option value="3">撤单</option>

                                            <option value="4">和局</option>

                                        </select>

                                    </div>

                                </div>



                                <div class="col-sm-2">

                                    <div class="input-group m-b"><span class="input-group-addon">用户类型</span>

                                        <select name="rg_type" class="input form-control">

                                            <option value="0" <?php  if($where['rg_type']=='0') { ?> selected <?php  } ?>>正常用户</option>

                                            <option value="8" <?php  if($where['rg_type']==8) { ?> selected <?php  } ?>>游客</option>

                                            <option value="11" <?php  if($where['rg_type']==11) { ?> selected <?php  } ?>>假人</option>

                                        </select>

                                    </div>

                                </div>



                                <div class="col-sm-5">

                                    <div class="input-group m-b"><span class="input-group-addon">购彩时间</span>

                                        <input class="date_picker form-control two_span_inline" value="<?php echo $where['s_time']?>" id="s_time" name="s_time"/>

                                        <span class="to-inline">-</span>

                                        <input class="date_picker form-control two_span_inline" value="<?php echo $where['e_time']?>" id="e_time" name="e_time"/>

                                    </div>

                                </div>



                                <div class="col-sm-2">

                                    <div class="input-group m-b"><span class="input-group-addon">快捷查询</span>

                                        <select class="form-control" id='quick' name="quick">

                                            <option value="0" <?php  if($quick == 0) { ?>selected<?php  } ?>>全部</option>

                                            <option value="1" <?php  if($quick == 1) { ?>selected<?php  } ?>>今日</option>

                                            <option value="2" <?php  if($quick == 2) { ?>selected<?php  } ?>>昨日</option>

                                            <option value="3" <?php  if($quick == 3) { ?>selected<?php  } ?>>本周</option>

                                            <option value="4" <?php  if($quick == 4) { ?>selected<?php  } ?>>本月</option>

                                            <option value="5" <?php  if($quick == 5) { ?>selected<?php  } ?>>上月</option>

                                        </select>

                                    </div>

                                </div>



                                <div class="col-sm-8">

                                    <div class="input-group m-b">

                                        <button type="button" onclick="index(1)" class="btn btn-primary">搜索</button>

                                        &nbsp;

                                        <button type="button" class="btn btn-primary" id="reset_btn">重置</button>

										&nbsp;

                                        <button type="button" onclick="index(2)" class="btn btn-primary">直属搜索</button>

                                        &nbsp;

                                        <button type="button" onclick="index(3)" class="btn btn-primary">团队搜索</button>

                                    </div>

                                </div>



                                <!--  -->



                                <input type="hidden" name="type" value="2" id="son" disabled="disabled" />

                                <input type="hidden" name="type" value="3" id="team" disabled="disabled"/>

                            </form>

                        </div>



                        <div id="editable_wrapper" class="dataTables_wrapper form-inline" role="grid" style="width: 100%">

                            <table class="table table-striped table-bordered table-hover" id="editable" aria-describedby="editable_info">

                                <thead>

                                    <tr>

                                        <th>ID</th>

                                        <th>流水号</th>

                                        <th>账号</th>

                                        <th>房间</th>

                                        <th>期号</th>

                                        <th>玩法</th>

                                        <th>开奖结果</th>

                                        <th>投注金额</th>

                                        <th>单注金额</th>

                                        <th>会员盈利</th>

                                        <th>状态</th>

                                        <th>奖金</th>

                                        <!--<th>下注平台</th>-->

                                        <th>投注时间</th>

                                        <th>开奖时间</th>

                                        <th>订单类型</th>

                                        <th>订单合法</th>

                                        <th>投注赔率</th>

                                        <th>投注盘口</th>

                                        <th>投注比分</th>

                                        <th>类型</th>

                                        <th>开奖比分</th>

                                        <th>操作</th>

                                    </tr>

                                </thead>

                                <tbody id="list_tb">

                                    <?php  if(is_array($list)) { foreach($list as $v) { ?>                                    <tr>

                                        <td><?php echo $v['id']?></td>

                                        <td><?php echo $v['order_no']?></td>

                                        <td><?php echo $v['username']?></td>

                                        <td><?php echo $v['lottery_title']?>--<?php echo $Room[$v['room_no']]?></td>

                                        <td><?php echo $v['issue']?></td>

                                        <td><?php echo $v['way']?></td>

                                        <td><?php echo $issue[$v['lottery_type']][$v['issue']]['open_result']?></td>

                                        <td><?php echo $v['money']?></td>

                                        <td><?php echo $v['single_money']?></td>

                                        <td>

                                            <?php  if($v['gain'] > 0) { ?><font style="color: #FF3300;"><?php echo $v['gain']?></font><?php  } ?>

                                            <?php  if($v['gain'] < 0) { ?><font style="color: #0099ff;"><?php echo $v['gain']?></font><?php  } ?>

                                            <?php  if($v['gain'] == 0) { ?><?php echo $v['gain']?><?php  } ?>

                                        </td>

                                        <td><?php echo $v['award_state_cn']?></td>

                                        <td><?php echo $v['award']?></td>

                                        <!--<td>-->

                                            <?php  if($v['flag'] == 1) { ?>iOS<?php  } ?>

                                            <?php  if($v['flag'] == 2) { ?>Android<?php  } ?>

                                            <?php  if($v['flag'] == 3) { ?>H5<?php  } ?>

                                            <?php  if($v['flag'] == 4) { ?>PC<?php  } ?>

                                        <!--</td>-->

                                        <td><?php echo $v['addtime']?></td>

                                        <td><?php echo $issue[$v['lottery_type']][$v['issue']]['open_time']?></td>

                                        <td><?php echo $v['bet_type']?></td>

                                        <td><?php  if($v['ext_a'] != '') { ?><span style="color:red;font-weight:bold;"><?php echo $v['ext_a']?></span><?php  } else { if($v['is_legal'] == 0) { ?>非法<?php  } if($v['is_legal'] == 1) { ?>合法<?php  } } ?></td>

                                        <td><?php echo $v['odds']?></td>

                                        <td><?php echo $v['pan_kou']?></td>

                                        <td><?php echo $v['bi_feng']?></td>

                                        <td><?php echo $v['type']?></td>

                                        <td><?php echo $v['result_bi_feng']?></td>

                                        <td class="font-icon">

                                            <?php  if($v['award_state_cn'] == '待开奖') { ?>

                                                <?php  if($cancal_order_supper == 1) { ?>      

                                                    <a href="javascript:;" style="color: #0099ff;" onclick="cancal_order(<?php echo $v['id']?>,this)" data-id="<?php echo $v['id']?>">撤单</a>

                                                <?php  } ?>

                                            <?php  } ?>

                                        </td>

                                    </tr>

                                    <?php  } } ?>                                </tbody>

                            </table>

                        </div>



                        <div class="row foot_page">

                            <div class="col-sm-8">

                                <div class="dataTables_info editable_info" id="listPage" role="alert" aria-live="polite" aria-relevant="all">

                                    <span class="back-page">

                                    本页小计：

                                    投注金额 <b><?php echo $pageSum?></b>&nbsp;&nbsp;

                                    会员盈亏 

                                    <?php  if($gainSum > 0) { ?><font style="color: #FF3300;"><b><?php echo $gainSum?></b></font><?php  } ?>

                                    <?php  if($gainSum < 0) { ?><font style="color: #0099ff;"><b><?php echo $gainSum?></b></font><?php  } ?>  

                                    <?php  if($gainSum == 0) { ?><b>0</b><?php  } ?>

                                    </span>

                                    <br />

                                </div>

                            </div>

                            <div class="col-sm-4" id="page">

                                <?php echo $show?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <input type="hidden" class="run_time_value" data-info='<?php echo  json_encode($post_run_data);  ?>'/>

        



        <script type="text/javascript">

            var loading = "";//加载中......

            var totalNum = 0;



            function cancal_order(id,e){

                console.log(id);

                $.ajax({

                    type:"post",

                    url:"?m=admin&c=orders&a=cancal_order",

                    data:{"id":id},

                    dataType:"json",

                    beforeSend:function(){

                        loading = layer.load(1);

                    },

                    success:function(data){

                        layer.close(loading);

                        if(data.err==0){

                            layer.msg('撤单成功', {icon: 6, shade: [0.5, '#393D49']});

                            $(e).parents('tr').find('td:contains("待开奖")').html('撤单');

                            $(e).remove();

                        }

                        if(data.err==1){

                            layer.msg(data.msg, {icon: 5, shade: [0.5, '#393D49']});

//                            $(e).parents('tr').find('td:contains("待开奖")').html('撤单');

//                            $(e).remove();

                        }

//                        console.log(data);

                    },

                    complete:function(XMLHttpRequest,textStatus){

//                        $(".loading").empty();

//                        $('.loading').css('z-index','-1');

                    },

                    error:function(data){

                        alert('网络异常，可能已经操作成功，请手工刷新!');

                    }

                })

                //order_no

            }

            //列表搜索

            function index(type) {

                if(type == 2)

                {

                    $("#son").attr("disabled",false);

                    $("#team").attr("disabled",true);

                }

                else if(type == 3)

                {

                    $("#son").attr("disabled",true);

                    $("#team").attr("disabled",false);

                }

                var url = '?m=admin&c=orders&a=order&search=1&' + $("#form").serialize();

                location.href = url;

            }



            function getTotal(obj){

                // var obj = $(obj).parent();

                var data = {

                    'data' : $("#form").serialize(),

                    'type' : 5

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

                            $(obj).parent().html(result.data);

                        } else {

                            layer.msg(result.msg, {icon: 5,shade: [0.5, '#393D49']});

                        }

                    }

                });

            }

            

            function listPage() {

            	var listPage = '';

            	var data = JSON.parse('<?php echo  json_encode($where); ?>');

            	var url = '?m=admin&c=orders&a=listOrderPage';

            	var style = "<?php echo  Session::get('style'); ?>";

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

                        $('#listPage').append('<span style="color: red;font-style: italic;margin-left: 20px;">汇总数据加载失败，请重新刷新当前页面.</span>')

                        $('#page').append('<span style="color: red;font-style: italic;margin-left: 20px;">汇总数据加载失败，请重新刷新当前页面.</span>')

                    },

                    success: function (data) {

                    	console.log(data);

                        if(data.code != 0)

                        {

                        	$('#loading').remove();

                        	$('#totaloading').remove()

                        	$('#listPage').append('<span style="color: red;font-style: italic;margin-left: 20px;">汇总数据加载失败，请重新刷新当前页面.</span>')

                            $('#page').append('<span style="color: red;font-style: italic;margin-left: 20px;">汇总数据加载失败，请重新刷新当前页面.</span>')

                        }

                        else

                        {

                        	$('#loading').remove();

                        	$('#totaloading').remove()

                            var listPage = '<span style="margin-left: 20px;'+style+'"><a href="javascript:void(0)" onclick="getTotal(this)">点击查看总计现金余额</a></span>';

                        	$('#listPage').append(listPage);

                        	if (data.data.show != '') {

                        		$('#page').append(data.data.show);

                        	}



                            totalNum = data.data.pagecount;

                        }

                    }

                });

            }



            $(function () {

            	listPage();

                $("#way").click();



                //时间插件

                $(".date_picker").jeDate({

                    isinitVal:true,

                    festival:true,

                    ishmsVal:true,

                    minDate: '2016-01-01',

                    maxDate: $.nowDate(0),

                    format:"YYYY-MM-DD hh:mm:ss",

                    zIndex:3000,

                })



                $("#award_state option[value='"+"<?php echo $where['award_state']?>"+"']").attr("selected",true);

                $("#way option[value='"+"<?php echo $where['way']?>"+"']").attr("selected",true);

                var play = $.parseJSON('<?php echo $play?>');

                var _html = '<option value="">所有玩法</option>';

                $.each(play,function(i,v){

                    var checked = "";

                    if(i == "<?php echo $where['way']?>"){

                        checked = "selected";

                    }

                    _html += "<option " + checked + ">" + i + "</option>";

                });

                $("#way").html(_html);

                

                $("#reset_btn").click(function(){

                    location.href = '?m=admin&c=orders&a=order&reset=1';

                });



                //缓存房间下拉节点

                var $roomSelect = $('#roomSelect');



                //切换彩种时，显示相应的房间

                $('#lottery_type').change(function () {

                    var current_lottery_id = $(this).find('option:selected').val();



                    //当选中全选时

                    if (current_lottery_id == '') {

                        //隐藏掉除了“所有房间”之外的其他 option 选项，并自动选中“所有房间”

                        $roomSelect

                            .find('[data-type]').addClass('hide_lottery').removeClass('show_lottery').end()

                            .find(':first-child').prop('selected', true);

                        return;

                    }



                    //关闭不是当前彩种的房间选项

                    $roomSelect.find('[data-type!="' + current_lottery_id + '"]').addClass('hide_lottery').removeClass('show_lottery');



                    //开启当前彩种的房间选项

                    $roomSelect.find('[data-type="' + current_lottery_id + '"],:first-child').addClass('show_lottery').removeClass('hide_lottery');



                    $roomSelect.find(':first-child').prop('selected', true);



                    //筛选玩法

                    $("#way").click();



                });



                //初始化选中

                var current_lottery_type = $('#current_lottery_type').val();

                if (current_lottery_type != '') {

                    var $tmp_lottery_type = $('#lottery_type');

                    $tmp_lottery_type.find('option[value="' + current_lottery_type + '"]').prop('selected', true);

                    $roomSelect.find('[data-type="' + current_lottery_type + '"]').addClass('show_lottery').removeClass('hide_lottery');

                }



            });



            $("#roomSelect").click(function(){

                $("#way").click();

            })

            

            

            var temp_room='';

            var temp_lottery='';

            //选择不同的房间，加载不同房间的玩法

            $("#way").click(function(){

                // var lottery_type = $('[name="lottery_type"] option:selected').val();

                // var lottery_type = $("#roomSelect option:selected").data("type");

                var lottery_type = $("#lottery_type").val();

                if (lottery_type == "") {

                    return;

                }



                var room = $("#roomSelect").val();

                if (room == '') {

                    room = $("#roomSelect option.show_lottery[data-type]:eq(0)").val();

                }

                

                if((temp_room!='' && temp_lottery!='') && (temp_room==room && temp_lottery==lottery_type)){

                    return;

                }

                

                temp_room=room;

                temp_lottery=lottery_type;

                if(lottery_type==''||room==''){

                    alert('参数错误，无法加载该房间的玩法');

                    return;

                }

                $.ajax({

                    type:"post",

                    url:"?m=admin&c=orders&a=wanfa",

                    data:{"lottery_type":lottery_type,"room":room},

                    dataType:"json",

                    success:function(data){

                        console.log(data);

                        if(data==-1){

                            alert('参数传递错误，无法加载该房间的玩法');

                            return;

                        }else if(data==-2){

                            alert('未查询到该房间的玩法');

                        }else{

                            var play = data;

                            var _html = '<option value="">所有玩法</option>';

                            $.each(play,function(i,v){

                                var checked = "";

                                if(v['way'] == "<?php echo $where['way']?>"){

                                    checked = "selected";

                                }

                                _html += "<option " + checked + ">" + v['way'] + "</option>";

                            });

                            $("#way").html(_html);

                        }

                    },

                    error:function(data){

                        alert('无法获取该房间的玩法');

                    }

                })

            });



        </script>

    </body>

</html>