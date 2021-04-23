var flagFst = true;
var store = {
	userInfo: {},
	msgList: [],
	listener: null,
	keybordDatas: {},
	mainPage: {
		lotteryNo: "",
		lotteryResult: "",
		countDown: 0
	},
	cashDetail: {},// 提现记录详情
	bettingDetail: {},// 投注记录详情
	transactionDetail: {},// 交易记录详情
	rechargeDetail: {},// 充值记录详情
	memberDetail: {},// 会员报表详情
	teamDetail: {},// 团队报表详情
    issue:0,

	processMsg: function(msg) {
		//console.log("store.js的processMsg, msg:" + JSON.stringify(msg));
		var commandId = msg.commandid;
		if(commandId == "3001"){
            if(userinfo.lottery_type == 4){
                //幸运飞艇去掉前面4位期号
                $("#issue").text(msg.issue.substr(4));
            }else{
                $("#issue").text(msg.issue);
            }
            $("#issue").attr("data-issue",msg.issue);

            //清除上一期的投注记录
            if (store.issue != msg.issue) {
                //$(".betTixing").hide();
                $(".room-con-fr-mid-con ul").html('');
                $(".room-con-fr-top em").html(0);
            }
            store.issue = msg.issue; //赋值期号

            if (flagFst) {
                //获取投注记录
                $.ajax({
                    url: "?m=web&c=order&a=nowBet",
                    type: "post",
                    data: {issue: msg.issue, room_no: userinfo.room_id},
                    dataType: "json",
                    success: function(data) {
                        var tzRecord = '';
                        //console.log(data);
                        if (data['list'].length > 0) {
                            $(".room-con-fr-top em").html(data['list'].length);
                            //$(".betTixing").show();
                            for(var i=0; i<data['list'].length; i++){
                                tzRecord +=
                                    '<li><span class="wanf2">'+ data['list'][i].way +
                                    '</span><span class="money2">'+ data['list'][i].money +
                                    '</span><span class="delete2" order-no="'+
                                    data['list'][i].order_no +'"><i class="ico ico-delete2"></i></span></li>'; //添加投注记录
                            }
                            $(".room-con-fr-mid-con ul").html(tzRecord);
                        }
                    }
                });

                flagFst = false;
            }
                        
            fnSet.countDown(msg);
		}else if(commandId == "3004"){
            //机器人或者用户发言
            fnSet.update34(msg);
		}else if(commandId == "3005"){
            //弹窗
            fnSet.update35(msg);
		}else if(commandId == "3007"){
            //用户投注信息,并更新投注记录
            fnSet.update37(msg);
		}else if(commandId == "3014"){
            //踢人
            //debugger;
            fnSet.alert(msg.content,function(){
                window.location.href ="/?m=web&c=lobby&a=index";
                // window.location.href ="/template/win/index.html";
            });
		}else if(commandId == "3008") {
			//3008服务端向客户端推送赔率配置修改
			//debugger;
			//客户端收到这个推送的消息后，再次向服务端请求赔率配置信息
            fnSet.oddsUpdate(msg);
            if(msg.odds_explain){
                newsExplain = msg.odds_explain.replace(/\n/g,"<br/>");
                odds_explain = newsExplain;
			}
		}else if(commandId == "3010"){
            //
		 	$(".room-con-fl-top em.icoAcer").text(msg.money);
		}else if(commandId == "3011"){
            //公布开奖结果
            var roomGroup;
             msg.statistics =msg.statistics.replace(/\\n|\\r/g, "\n");
             roomGroup ='<div class="userBetting"><ul><li class="fl"><div class="head-image"><img src="/statics/web/images/avatar.png"></div>'+
                 '<div class="news-fl"><h3>机器人</h3><div class="news-con" style="font-size: 10px;">'
                 //'<p><span>'+msg.open_time+'</span><span>第<em>'+msg.issue+'</em>期</span></p>'+
             roomGroup +='<p>期号：'+msg.issue+'</p><p>开奖时间：'+msg.open_time+'</p><p>开奖结果：'+msg.result+'</p>';

            if (msg.statistics != '') {
                roomGroup += '<p>统计：<pre style="font-size: 10px;">'+msg.statistics+'</pre></p>';
            }
            roomGroup += '</div></div></li></ul></div>';
			$(".room-chat-con").append(roomGroup);

			if(userinfo.lottery_type ==2 || userinfo.lottery_type ==4){
			    var result =msg.result.split(",");
			    var html='';
			    if(userinfo.lottery_type ==4){
			        html+='第<label id="issue2">'+msg.issue.substr(4)+'</label>期&nbsp;&nbsp;'
                }else{
                    html+='第<label id="issue2">'+msg.issue+'</label>期&nbsp;&nbsp;'
                }
			    var html2 ='<ul><li><span class="issueWarp"><em>'+msg.issue+'</em></span></li><li>'+msg.open_time+'</li><li class="colorjieguo_down">';
			    for(var i=0; i<result.length; i++ ){
                    html+='<i class="colorjieguo color'+result[i]+'">'+result[i]+'</i>';
                    html2 +='<i class="colorjieguo color'+result[i]+'">'+result[i]+'</i>'
                }
                html2+='</li></ul>';
                $(".roomHead2 span").html(html);
                $(".lottery dd").prepend(html2);
            }else{
                $(".roomHead2 span").html('第<label id="issue2">'+msg.issue+'</label>期&nbsp;&nbsp;'+msg.result);
                $(".lottery dd").prepend('<ul><li>'+msg.issue+'</li><li>'+msg.open_time+'</li><li>'+msg.result+'</li></ul>');
            }

			$(".lottery dd ul:last").remove();
            var param = {
                "commandid": "3012",
            };
            wsSendMsg(param);
			fnSet.scrollTop();
		}else if(commandId == "3015"){
            //成功取消投注
            var userBetting =$('.userBetting li.fr');
            var issue =$("#issue").attr("data-issue");
//            fnSet.alert(msg.content);
            //减少对应的下注数
            if (typeof(msg.order_no) != "undefined") {
                var num = $(".betTixing span em").text();
                var tempNum = num.replace(/[^0-9]/ig,'') - 1;
                //console.log(tempNum);
                $(".room-con-fr-top em").html(tempNum);
            }
            
            //去掉对应的下注记录,标黑
            if (typeof(msg.order_no) == "undefined") {//去掉全部
                for(var i=0; i<userBetting.length; i++){
                    if(userBetting.eq(i).attr("data-issue") ==issue){
                        userBetting.eq(i).find("i").show();
                    }
                }
                //$(".betTixing").hide();
                $(".room-con-fr-mid-con ul").html("");
            }else{ //去掉对应的下注记录,划线
                $("li[order-no="+ msg.order_no +"]").css("text-decoration","line-through");
                $("li[order-no="+ msg.order_no +"]").children("label").css("text-decoration","line-through");
                $("li[order-no="+ msg.order_no +"]").attr("bj","true");
                var tzts = $("li[order-no="+ msg.order_no +"]").siblings("li").length;
                var bjts = $("li[order-no="+ msg.order_no +"]").siblings("li[bj=true]").length;
                if (bjts == tzts) {
                    $("li[order-no="+ msg.order_no +"]").parents(".userBetting").find("i").show();
                }
                
                $("i[order-no="+ msg.order_no +"]").parent().parent().remove();  //下注记录中对应的记录删除
                //console.log($(".quxiao ul li").length);
                //if ($(".quxiao ul li").length == 0) {
                //    $(".betTixing").hide();
                //}
            }
            
            //系统消息反馈
            roomGroup ='<div class="userBetting"><ul><li class="fl"><div class="head-image"><img src="/statics/web/images/avatar.png"></div><div class="news-fl"><h3>机器人</h3><div class="news-con" style="font-size: 14px;"><pre>'+msg.content+'</pre></div></div></li></ul></div>'
            $(".room-chat-con").append(roomGroup);
            fnSet.scrollTop();
            
		}else if(commandId == "3018"){
            //用户投注信息,并更新投注记录
            fnSet.update318(msg);
        }else if(commandId == "3020"){
            //
            if(userinfo != null) {
                var param = {
                    "commandid": "3002",
                    "uid": userinfo.userid,
                    "roomid":userinfo.room_id
                };
                wsSendMsg(param);
            }
        }else if(commandId == "3022"){
            //
            $(".zhui_details").hide();//追号详情弹窗隐藏
            fnSet.alert(msg.content);
        }
        else if(commandId == "4003"){
            //
		 	var float ="left";
			var head_url1 ="/statics/web/images/avatar.png";
			if(msg.username ==""){
				msg.username ="机器人";
			}else if(msg.username == userinfo.nickname){
				float ="right";
                head_url1 =userinfo.head_url;
            }

			var contentHtml ='<li><h3 class="'+float+'">'+ msg.username +'</h3><div class="girl '+float+'"><img src="'+head_url1+'"></div><div class="customContent '+float+'"><pre>'+msg.content+'</pre></div></li>';
			$(".customService ul").append(contentHtml);
            setTimeout(function() {
                $('.customService').css("padding-bottom",$(".customNews").outerHeight()+98);
                var pageHeight =$(".customService ul").outerHeight();
                $('.customService').scrollTop(pageHeight);
            }, 100);
		}else if(commandId == "3019") {
            //
            $("#dZh").show();//追号详情图标显示
            $("#zhuiHList").empty();//追号工具列表清空
            fnSet.scrollTop();
        }
	}
};