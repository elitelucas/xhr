var store = {	
	processMsg: function(msg) {
		console.log("store.js的processMsg, msg:" + JSON.stringify(msg));
		var commandId = msg.commandid;
                if(commandId == "4103"){                      
                    var contentHtml = '<li><div class="left"><h3><label>用户名：'+ msg.username +'</label><span></span></h3><p>'+ msg.content +'</p></div></li>';
                    $(".serContent").find('ul').append(contentHtml);
                    
                    near($(".serContent"));
		} else if(commandId == "4105") { //接收历史数据
                    
                    //判断历史数据为空时则直接返回
                    if (msg.data.length == 0) {
                       return; 
                    }
                    
                    var contentHtml = '';
                    var username = msg.data[0].username; 
                    for (x in msg.data) {
                        if (msg.data[x].type == 0) {
                            contentHtml = '<li><div class="left"><h3><label>用户名：'+ msg.data[x].username +'</label><span></span></h3><p>'+ msg.data[x].content +'</p></div></li>' + contentHtml; 
                        } else {
                             contentHtml = '<li><div class="right"><h3><span></span><label>客服</label></h3><p>'+ msg.data[x].content +'</p></div></li>' + contentHtml;
                        }
                    }
                    
                    $(".serContent").find('ul').html(contentHtml);
                    
                    near($(".serContent"));
                }
	}
};

/**
 * 聊天室窗口滚动条定位到最下端
 */
function near(obj) {
    setTimeout(function() {
        $('.serContent').css("padding-bottom",$(".serFs").outerHeight()+98);
        var pageHeight = obj.find('ul').outerHeight();
        obj.scrollTop(pageHeight);
    }, 100);
}