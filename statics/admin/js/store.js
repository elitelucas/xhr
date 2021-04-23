var store = {
	processMsg: function(msg) {
		console.log("store.js的processMsg, msg:" + JSON.stringify(msg));
		var commandId = msg.commandid;
                if(commandId == "4103"){
                    //新消息提示
                    var flag = flag2 = true;
                    console.log("打开的窗口：" + kqWindow);
                    $("#userList li").each(function() {//判断该用户是否已存在，存在则停止向下执行
                        console.log($(this).text());
                        if ($(this).text() == msg.username){ //存在    
                     
                            //判断子页面是否打开
                            for (x in kqWindow){
                                if (kqWindow[x] == msg.username) { //已打开
                                   frameName = "layui-layer-iframe" + x;
                                   flag2 = false;
                                   break;
                                }
                            }
                            
                            if (flag2) { //未打开窗口则标红
                                $(this).attr("client_id", msg.client_id);
                                $(this).addClass("bg-red");
                            }
                            
                           flag = false;
                        }
                    });
                    
                    if (flag) {
                        var usernameLi = '<li client_id="'+ msg.client_id + '" class="bg-red">' + msg.username + '</li>';
                        $("#userList").append(usernameLi);
                        
                        //更新在线人数
                        var num = $("#zMsg em").html();
                        num++;
                        $("#zMsg em").html(num)
                    }  
		}
	}
};