<?php  !defined('IN_SNYNI') && die('Access Denied!');?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title></title>
    <link rel="stylesheet" href="statics/admin/css/datePicker.css">
    <link rel="stylesheet" href="statics/admin/css/admin.css">
    <link rel="stylesheet" href="statics/admin/js/layer/2.1/skin/layer.css">
    <link rel="stylesheet" href="statics/admin/js/layer/2.1/skin/layer.ext.css">
    <script src="statics/admin/js/jquery.js"></script>
    <script src="statics/admin/js/layer/2.1/layer.js"></script>
    <script src="statics/admin/js/layer/2.1/extend/layer.ext.js"></script>
    <script src="statics/admin/js/pintuer.js"></script>

    <!-- template-begin+++ -->
    <?php  include template('public-new-ui-header'); ?>
    <!-- template-end+++ -->

</head>
<body style="margin: 15px;background-color: #f3f3f4;">
<div class="row">
    <div class="col-sm-12">
        <div class="ibox float-e-margins">
            <div class="ibox-title iboxWTitle">
                <h5>预开奖列表</h5>
                <div class="ibox-tools">
                    <a href="?m=admin&c=preopen&a=editConf&lottery_type=<?php echo $current_lottery_type?>" class="btn btn-white btn-bitbucket">
                        <i class="fa fa-plus-square-o"> </i> 修改配置
                    </a>
                </div>
            </div>
            <div class="ibox-content" style="width: 100%;">
                <div class="row">
                    <form method="post" id="form" class="form-inline">

                        <div class="col-sm-2">
                            <div class="input-group m-b"><span class="input-group-addon">选择彩种</span>
                                <select name="lottery_type" class="form-control" id="lottery_type" >
                                    <?php  if(is_array($lottery_type_map)) { foreach($lottery_type_map as $each_key => $each_lottery) { ?>                                    <option value="<?php echo $each_key?>" <?php  if($current_lottery_type == $each_key) { ?>selected="selected"<?php  } ?>>
                                    <?php echo $each_lottery?>
                                    </option>
                                    <?php  } } ?>                                </select>
                            </div>
                        </div>

                    </form>
                </div>
                <div id="editable_wrapper" class="dataTables_wrapper form-inline" role="grid" style="width: 100%">
                    <table class="table table-striped table-bordered table-hover  dataTable" id="editable" aria-describedby="editable_info">
                        <thead>
                        <tr>
                            <th>序号</th>
                            <th>期号</th>
                            <th>彩种</th>
                            <th>开奖号码</th>
                            <th>开奖类型</th>
                            <th>配置杀率</th>
                            <th>模式</th>
                            <th>结果插入时间</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody id="list_tb">
                        <?php  if(empty($list)) { ?>
                        <tr>
                            <td colspan="9" align="center"> 暂无信息 </td>
                        </tr>
                        <?php  } else { ?>
                        <?php  if(is_array($list)) { foreach($list as $index_key => $v) { ?>                        <tr>
                            <td class="content_center"><?php echo  $page_start + $index_key + 1; ?></td>
                            <td class="content_center"><?php echo $v['issue']?></td>
                            <td class="content_center"><?php echo $lottery_type_map[$current_lottery_type]?></td>
                            <td class="content_center">
                               
                                <?php  if(is_array($v['lottery_result_list'])) { foreach($v['lottery_result_list'] as $each_lottery_key => $each_lottery_result) { ?>                                <span <?php  if($v['use_flag_list'][$each_lottery_key] == '1') { ?>style="color:red"<?php  } ?>>
                                            <?php echo $each_lottery_result?>(<?php echo  substr($v['sha_lv_list'][$each_lottery_key], 0, -4), '%'?>)
                                        </span>
                                <br>
                                <?php  } } ?>                            </td>
                            <td class="content_center">
                                <?php  if($v['has_by_hand'] == '1') { ?>
                                <span class="red_font">手动预开奖</span>
                                <?php  } else { ?>
                                <?php  if($v['bu_dan_flag'] != '1' && $running_issue > $v['issue']) { ?>
                                <span class="red_font">手动补单</span>
                                <?php  } else { ?>
                                <?php  if($v['is_preopen_running_then'] == '1') { ?>
                                自动预开奖
                                <?php  } else { ?>
                                --
                                <?php  } ?>
                                <?php  } ?>
                                <?php  } ?>
                            </td>
                            <td class="content_center">
                                <?php  if($v['setting_type_then'] == '2') { ?>
                                <?php echo $v['percent_then']?>
                                <?php  } else { ?>
                                --
                                <?php  } ?>
                            </td>
                            <td class="content_center">
                                <?php  if($v['setting_type_then'] == '1') { ?>
                                最大模式
                                <?php  } elseif($v['setting_type_then'] == '2') { ?>
                                接近模式
                                <?php  } elseif($v['setting_type_then'] == '3') { ?>
                                月结模式
                                <?php  } else { ?>
                                --
                                <?php  } ?>
                            </td>
                            <td class="content_center"><?php echo  date('Y-m-d H:i:s', $v['insert_time_final']);?></td>
                            <td class="content_center">
                                <?php  if($v['has_by_hand'] == '1') { ?>
                                <a class="add_issue_edit" href="<?php echo url('','preopen','preOpenAward')?>&issue=<?php echo $v['issue']?>&lottery_type=<?php echo $current_lottery_type?>" style="color: #0099ff;" data-title="編輯当期预开奖">
                                    <i class="fa fa fa-edit"></i>
                                </a>
                                <?php  } ?>
                                <?php  if(($page_start + $index_key) == 0) { ?>                                
                                <?php  if($v['is_issue_stop'] == '1') { ?>
                                <a class="add_issue_stop" href="javascript:;" style="color: #0099ff;" data-issue="<?php echo $v['issue']?>" data-lottery-type="<?php echo $current_lottery_type?>" data-title="关闭当期预开奖">
                                    <i class="fa fa fa-stop"></i>
                                </a>                                
                                <?php  } else { ?>
                                已关闭当期预开奖
                                <?php  } ?>
                                <?php  } else { ?>
                                --
                                <?php  } ?>
                            </td>
                            <!--
                            <td class="content_center">
                                --
                            </td>
                            -->
                        </tr>
                        <?php  } } ?>                        <?php  } ?>

                        </tbody>
                    </table>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <?php echo  $show;?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        <script type="text/javascript">
            var loading = "";//加载中......
            // ?m=admin&c=preopen&a=list
            $('#lottery_type').change(function () {
                location.href = '?m=admin&c=preopen&a=list&lottery_type=' + $(this).val();
            });

            //关闭当期预开奖
            $('.add_issue_stop').click(function () {

                var $this = $(this);
                var url = '?m=admin&c=preopen&a=addIssueStopLog';

                layer.confirm('确认关闭当期预开奖？', {
                    btn: ['确认', '取消']
                }, function () {
                    $.ajax({
                        url: url,
                        data: {
                            issue : $this.data('issue'),
                            lottery_type : $this.data('lottery-type'),
                        },
                        dataType: 'json',
                        type: 'post',
                        beforeSend: function () {
                            loading = layer.load(1);
                        },
                        error: function (e) {
                            layer.close(loading);
                            layer.alert('操作失败，请检查网络', {icon: 5, shade: [0.5, '#393D49']});
                        },
                        success: function (result) {
                            layer.close(loading);
                            if (result.status == '0') {
                                layer.msg('操作成功', {icon: 6, shade: [0.5, '#393D49']}, function () {
                                    location.reload();
                                });
                            } else {
                                layer.alert(result.msg, {icon: 5, shade: [0.5, '#393D49']}, function () {
                                    location.reload();
                                });
                            }
                        }
                    });
                }, function () {
                });
            });

        </script>
    </body>
</html>