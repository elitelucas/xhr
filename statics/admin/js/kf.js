var kqWindow = new Array();

$(function() {
    $("#userList li").live("click", function() {     
        var username = $(this).html();
        var client_id = $(this).attr("client_id");

        //判断窗口是否已打开
        for(x in kqWindow) {
           if (kqWindow[x] == username) {
               layer.open({
                   content: "<p class='alert_msg'>聊天窗口已打开</p>",
                   btn: '我知道了'
               });
               return;
           }
        }
        
        //将用户标为灰色
        $(this).removeClass("bg-red");
        
        //新开窗口
        window.open("?m=admin&c=customService&a=kfWeb&client_id=" + client_id + "&username=" + username);
     
//         layer.open({
//           type: 2,
//           title: '客服在线',
//           shadeClose: true,
//           shade: false,
//           maxmin: true, //开启最大化最小化按钮
//           area: ['1000px', '852px'],
//           content: "?m=admin&c=customService&a=kfWeb&client_id=" + client_id + "&username=" + username,
//           success: function(layero, index) {
//                kqWindow[index] = username;
//                console.log(kqWindow);
//           },
//           cancel: function(index) {
//               kqWindow.splice(index,1);
//               console.log(kqWindow);
//           }
//         });   
    });

    $("#zMsg").click(function() {
      $("#userList").toggleClass("show-user");
    }); 
});



