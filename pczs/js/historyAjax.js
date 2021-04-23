/**
 * 
 */

var time;
var time2;
var pages;
//开奖结果
    var niuniu = 0;
function historyFun(dataNews) {
    // if(!dataNews.user_id==''){
    // layer.msg("拼命加载中...", {time: 0 });
         $.ajax({
            url: "/?m=api&c=trend&a=getMoreLottery",
            type: 'POST',
            data: dataNews,
            // async: false,
            dataType: "json",
            success: function (msg) {
                // console.log(msg)
                callBack(msg,dataNews);
                // layer.closeAll();
                //分页
                // layui.use(['laypage', 'layer'], function(){
                    // var laypage = layui.laypage
                    // ,layer = layui.layer;
                    laypage({
                        cont: 'page'
                        ,pages: pages //总页数
                        ,groups: 5 //连续显示分页数
                        ,jump: function(obj, first){
                            dataNews.page = obj.curr;
                            // dataNews.day = $(".doubledate").val();
                            $.ajax({
                                url: "/?m=api&c=trend&a=getMoreLottery",
                                type: 'POST',
                                data: dataNews,
                                // async: false,
                                dataType: "json",
                                success: function (msg) {
                                    if(!first){
                                        $.ask('Page '+ obj.curr +'，total '+pages+' pages');
                                    }
                                    callBack(msg,dataNews);
                                }
                            })
                        }
                    });
                // })
            }
        })   
    // }
}
function callBack(msg,dataNews){
    var msg = msg.data
    pages = msg.totalPage;
    //彩种时间
    var lotteryDate;
    //彩种期号
    var lotteryQihao;
    //彩种球号
    var lotteryNum;
    //牛牛
    var blueArr,redArr;
    $(".sub_table").find("tbody").html("");

    if(msg.list==""||msg.list==null){
        var len = $(".sub_table thead td").length;
        $(".sub_table").find("tbody").append("<tr>"+
                                                "<td colspan='"+len+"'>"+"No record"+"</td>"+
                                            "</tr>");  
       return false;
    }
    for(var i=0;i<msg.list.length;i++){

        lotteryDate = formatDate(parseInt(msg.list[i].lottery_date)*1000);

        lotteryQihao = msg.list[i].lottery_id;
        if(dataNews.lottery_type=="10"){
            blueArr = msg.list[i].content.blue.numbers_arr
            redArr = msg.list[i].content.red.numbers_arr
        }else{
            lotteryNum = msg.list[i].lottery_numbers.split(",");
        }
        var qiu = '';
        var bluePai='',redPai='';
        if(dataNews.lottery_type=="2"||dataNews.lottery_type=="4"||dataNews.lottery_type=="9"||dataNews.lottery_type=="14"){//北京PK10、幸运飞艇号码样式
            for(var z=0;z<lotteryNum.length;z++){
                qiu+="<i class='cheBall cheBall"+parseInt(lotteryNum[z])+"' data-num='"+parseInt(lotteryNum[z])+"'>"+lotteryNum[z]+"</i>";
            }
        }else if(dataNews.lottery_type=="3" || dataNews.lottery_type=="1"){ //幸运28号码样式
            for(var z=0;z<lotteryNum.length;z++){
                if(z==2){
                    // $(".lottery_results").append("<span class='"+className+"'>"+lottNum[i]+"</span><span>=</span>");
                    qiu+="<i class='sscBall' data-num='"+parseInt(lotteryNum[z])+"'>"+lotteryNum[z]+"</i>=";
                }else if(z==3){
                    // $(".lottery_results").append("<span class='"+className+"'>"+lottNum[i]+"</span>");
                    qiu+="<i class='sscBall' data-num='"+parseInt(lotteryNum[z])+"'>"+lotteryNum[z]+"</i>";
                }else{
                    // $(".lottery_results").append("<span class='"+className+"'>"+lottNum[i]+"</span><span>+</span>");
                    qiu+="<i class='sscBall' data-num='"+parseInt(lotteryNum[z])+"'>"+lotteryNum[z]+"</i>+";
                }
            }
        }else if(dataNews.lottery_type=="10"){
            for(var g=0;g<blueArr.length;g++){
                bluePai+="<i class='pokeImg poke"+blueArr[g]+"'></i>"
                redPai+="<i class='pokeImg poke"+redArr[g]+"'></i>"
            }
            
        }else if(dataNews.lottery_type=="13"){
            for(var z=0;z<lotteryNum.length;z++){ //骰宝
                qiu+="<i class='sbBall sbBall"+parseInt(lotteryNum[z])+"' data-num='"+parseInt(lotteryNum[z])+"'></i>";
            }
        }else{
            for(var z=0;z<lotteryNum.length;z++){ //其他通用号码样式
                qiu+="<i class='sscBall' data-num='"+parseInt(lotteryNum[z])+"'>"+lotteryNum[z]+"</i>";
            }
        }
        if(dataNews.lottery_type=="2"||dataNews.lottery_type=="4"||dataNews.lottery_type=="9"||dataNews.lottery_type=="14"){
            //北京PK10、幸运飞艇
            msg.list[i].content[1] = msg.list[i].content[1]?english[msg.list[i].content[1]]:msg.list[i].content[1];
            msg.list[i].content[2] = msg.list[i].content[2]?english[msg.list[i].content[2]]:msg.list[i].content[2];
            msg.list[i].content[3] = msg.list[i].content[3]?english[msg.list[i].content[3]]:msg.list[i].content[3];
            msg.list[i].content[4] = msg.list[i].content[4]?english[msg.list[i].content[4]]:msg.list[i].content[4];
            $(".sub_table").find("tbody").append("<tr>"+
                "<td >"+lotteryDate+"</td>"+
                "<td>"+msg.list[i].lottery_id+"</td>"+
                "<td>"+qiu+"</td>"+
                "<td class='col_count'>"+msg.list[i].content[0]+"</td>"+
                "<td class='col_big'>"+msg.list[i].content[1]+"</td>"+
                "<td class='col_small'>"+msg.list[i].content[2]+"</td>"+
                "<td class='col_double'>"+msg.list[i].content[3]+"</td>"+
                "<td class='col_single'>"+msg.list[i].content[4]+"</td>"+
            "</tr>")  
        }else if(dataNews.lottery_type=="1" || dataNews.lottery_type=="3"){
            //28彩
            $(".sub_table").find("tbody").append("<tr>"+
                "<td >"+lotteryDate+"</td>"+
                    "<td>"+msg.list[i].lottery_id+"</td>"+
                    "<td>"+qiu+"</td>"+
                    "<td class='col_count'>"+parseInt(msg.list[i].content[0])+"</td>"+
                    "<td class='col_big'>"+msg.list[i].content[1]+"</td>"+
                    "<td class='col_small'>"+msg.list[i].content[2]+"</td>"+
                    "<td class='col_double'>"+msg.list[i].content[3]+"</td>"+
                    "<td class='col_single'>"+msg.list[i].content[4]+"</td>"+
                    "<td class='col_big'>"+msg.list[i].content[5]+"</td>"+
                    "<td class='col_small'>"+msg.list[i].content[6]+"</td>"+
                    "<td class='col_big'>"+msg.list[i].content[7]+"</td>"+
                    "<td class='col_small'>"+msg.list[i].content[8]+"</td>"+
                    "<td class='col_big'>"+msg.list[i].content[9]+"</td>"+
                    "<td class='col_small'>"+msg.list[i].content[10]+"</td>"+
                "</tr>")  
        }else if(dataNews.lottery_type=="7" || dataNews.lottery_type=="8"){
            //六合彩
                if(msg.list[i].content[8]==""){
                    if(msg.list[i].content[9]==""){
                        var con = msg.list[i].content[10];
                    }else{
                        var con = msg.list[i].content[9];
                    }
                }else {
                    var con = msg.list[i].content[8];
                }
                if(msg.list[i].content[13]==""){
                    if(msg.list[i].content[14]==""){
                        var hedx = msg.list[i].content[15];
                    }else{
                        var hedx = msg.list[i].content[14];
                    }
                }else {
                    var hedx = msg.list[i].content[13];
                }
                $(".sub_table").find("tbody").append("<tr>"+
                "<td>"+msg.list[i].lottery_id+"</td>"+  //开奖期号
                "<td>"+qiu+"</td>"+             //开奖球号
                "<td class='col_count'>"+msg.list[i].content[0]+"</td>"+ //总和总数
                "<td class='col_big'>"+(msg.list[i].content[1]=="" ? msg.list[i].content[2] : msg.list[i].content[1])+"</td>"+  //总和单双
                "<td class='col_small'>"+(msg.list[i].content[3]=="" ? msg.list[i].content[4] : msg.list[i].content[3])+"</td>"+    //总和大小
                "<td class='col_double'>"+(msg.list[i].content[6]=="" ? msg.list[i].content[7] : msg.list[i].content[6])+"</td>"+   //特码单双
                "<td class='col_single'>"+con+"</td>"+   //特码大小
                "<td class='col_double'>"+(msg.list[i].content[11]=="" ? msg.list[i].content[12] : msg.list[i].content[11])+"</td>"+ //特码合单双
                "<td class='col_single'>"+hedx+"</td>"+ //特码合大小
                // "<td class='col_count'>"+(msg.list[i].content[16]=="" ? msg.list[i].content[17] : msg.list[i].content[16])+"</td>"+ //特码尾大小
            "</tr>")    
             
            $(".sub_table").find(".sscBall").each(function(){
                var colVal = $(this).html();
                var arrRed = ['01','02','07','08','12','13','18','19','23','24','29','30','34','35','40','45','46'];
                var arrBlue = ['03','04','09','10','14','15','20','25','26','31','36','37','41','42','47','48'];
                var arrGreen = ['05','06','11','16','17','21','22','27','28','32','33','38','39','43','44','49'];
                var inxRed = $.inArray(colVal , arrRed);
                var inxBlue = $.inArray(colVal , arrBlue);
                var inxGreen = $.inArray(colVal , arrGreen);

                if(inxRed > '-1'){
                    $(this).addClass("i_red icon");
                }
                if(inxBlue > '-1'){
                    $(this).addClass("i_blue icon");
                }
                if(inxGreen > '-1'){
                    $(this).addClass("i_green icon");
                }
            })
        }else if(dataNews.lottery_type=="10"){
            //牛牛
            var blueWin = msg.list[i].content.blue.win_str=='胜'?'winColor':'lossColor';
            var redWin = msg.list[i].content.red.win_str=='胜'?'winColor':'lossColor';
            $(".sub_table").find("tbody").append("<tr>"+
                "<td>"+lotteryDate+"</td>"+  //时间
                "<td>"+msg.list[i].lottery_id+"</td>"+  //开奖期号
                "<td class='flexTd'><span><span>"+english[msg.list[i].content.blue.niu]+'</span></span>'+bluePai+"<span>Blue <span class='"+blueWin+"'>"+english[msg.list[i].content.blue.win_str]+"</span></span></td>"+            
                "<td class='flexTd'><span>Red <span class='"+redWin+"'>"+english[msg.list[i].content.red.win_str]+"</span></span>"+redPai+'<span><span>'+english[msg.list[i].content.red.niu]+"</span></span></td>"+            
            "</tr>") 
        }else if(dataNews.lottery_type=="6" || dataNews.lottery_type=="5" || dataNews.lottery_type=="11"){
            //时时彩
            msg.list[i].content[1] = msg.list[i].content[1]?english[msg.list[i].content[1]]:msg.list[i].content[1];
            msg.list[i].content[2] = msg.list[i].content[2]?english[msg.list[i].content[2]]:msg.list[i].content[2];
            msg.list[i].content[3] = msg.list[i].content[3]?english[msg.list[i].content[3]]:msg.list[i].content[3];
            msg.list[i].content[4] = msg.list[i].content[4]?english[msg.list[i].content[4]]:msg.list[i].content[4];
            msg.list[i].content[5] = msg.list[i].content[5]?english[msg.list[i].content[5]]:msg.list[i].content[5];
            msg.list[i].content[6] = msg.list[i].content[6]?english[msg.list[i].content[6]]:msg.list[i].content[6];
            msg.list[i].content[7] = msg.list[i].content[7]?english[msg.list[i].content[7]]:msg.list[i].content[7];
            $(".sub_table").find("tbody").append("<tr>"+
            "<td >"+lotteryDate+"</td>"+
            "<td>"+msg.list[i].lottery_id+"</td>"+
            "<td>"+qiu+"</td>"+
            "<td class='col_big'>"+msg.list[i].content[1]+"</td>"+
            "<td class='col_small'>"+msg.list[i].content[2]+"</td>"+
            "<td class='col_double'>"+msg.list[i].content[3]+"</td>"+
            "<td class='col_single'>"+msg.list[i].content[4]+"</td>"+
            "<td class='col_long'>"+msg.list[i].content[5]+"</td>"+
            "<td class='col_hu'>"+msg.list[i].content[6]+"</td>"+
            "<td class='col_sum'>"+msg.list[i].content[7]+"</td>"+
            "</tr>")  
        }else if(dataNews.lottery_type=="13"){
            //骰宝
            msg.list[i].spare_2[1] = msg.list[i].spare_2[1]?english[msg.list[i].spare_2[1]]:msg.list[i].spare_2[1];
            msg.list[i].spare_2[2] = msg.list[i].spare_2[2]?english[msg.list[i].spare_2[2]]:msg.list[i].spare_2[2];
            msg.list[i].spare_2[3] = msg.list[i].spare_2[3]?english[msg.list[i].spare_2[3]]:msg.list[i].spare_2[3];
            msg.list[i].spare_2[4] = msg.list[i].spare_2[4]?english[msg.list[i].spare_2[4]]:msg.list[i].spare_2[4];
            msg.list[i].spare_2[5] = msg.list[i].spare_2[5]?english[msg.list[i].spare_2[5]]:msg.list[i].spare_2[5];
            $(".sub_table").find("tbody").append("<tr>"+
            "<td >"+lotteryDate+"</td>"+
            "<td>"+msg.list[i].issue+"</td>"+
            "<td><div class='sbImgPanel'>"+qiu+"</div></td>"+
            "<td class='col_big'>"+msg.list[i].spare_2[0]+"</td>"+
            "<td class='col_small'>"+msg.list[i].spare_2[1]+"</td>"+
            "<td class='col_double'>"+msg.list[i].spare_2[2]+"</td>"+
            "<td class='col_single'>"+msg.list[i].spare_2[3]+"</td>"+
            "<td class='col_long'>"+msg.list[i].spare_2[4]+"</td>"+
            "<td class='col_hu'>"+msg.list[i].spare_2[5]+"</td>"+
            "</tr>")  
        }else{
            $(".sub_table").find("tbody").append("<tr>"+
            "<td >"+lotteryDate+"</td>"+
            "<td>"+msg.list[i].lottery_id+"</td>"+
            "<td>"+qiu+"</td>"+
            "<td class='col_count'>"+msg.list[i].content[0]+"</td>"+
            "<td class='col_big'>"+msg.list[i].content[1]+"</td>"+
            "<td class='col_small'>"+msg.list[i].content[2]+"</td>"+
            "<td class='col_double'>"+msg.list[i].content[3]+"</td>"+
            "<td class='col_single'>"+msg.list[i].content[4]+"</td>"+
            "<td class='col_long'>"+msg.list[i].content[5]+"</td>"+
            "<td class='col_hu'>"+msg.list[i].content[6]+"</td>"+
            "<td class='col_sum'>"+msg.list[i].content[7]+"</td>"+
            "<td class=''>"+msg.list[i].content[8]+"</td>"+
            "<td class=''>"+msg.list[i].content[9]+"</td>"+
            "</tr>")    
        }
            
        // }
    }
}
function formatDate(curTime){
    var toDay = new Date(curTime);//创建时间对象
    var Year1= toDay.getFullYear();//年
    var Month1= toDay.getMonth()+1;//月
    var day1=toDay.getDate();//日
    var Hours1=toDay.getHours();//时
    var Mi1=toDay.getMinutes();//分
    var Sec1=toDay.getSeconds();//秒
    if(Hours1<10)Hours1 = '0'+Hours1;
    if(Month1<10)Month1 = '0'+Month1;
    if(day1<10)day1 = '0'+day1;
    if(Mi1<10)Mi1 = '0'+Mi1;
    if(Sec1<10)Sec1 = '0'+Sec1;

    return Year1+"-"+Month1+"-"+day1+" "+Hours1+":"+Mi1+":"+Sec1;
}

