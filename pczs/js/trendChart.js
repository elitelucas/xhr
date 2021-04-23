/**
 * 
 */

var time;
var time2;
var pages;
var dataNav = {};
dataNav.timestamp = timestamp().timestamp;
var idArr = {};
// $.myAjax("https://www.sglotto.com/api/Help/getLotteryList", dataNav, function(newData) {
//     idArr = newData.data;
// })
//开奖结果
function trendFun(dataNews) {
 	//2017-12-18添加
	if(dataNews.lottery_type>24&&dataNews.lottery_type<201){
		for(var i=0;i<idArr.length;i++){
			if(dataNews.lottery_type==idArr[i].id){
				dataNews.lottery_type=idArr[i].id2;
			}else if(dataNews.lottery_type==idArr[i].id2){
				dataNews.lottery_type=idArr[i].id;
			}
		}
	}
	// if(dataNews.lottery_type>24&&dataNews.lottery_type<201){
	// 	for(var i=0;i<thisId.length;i++){
	// 		if(dataNews.lottery_type==thisId[i].lottery_type2){
	// 			dataNews.lottery_type=thisId[i].lottery_type;
	// 		}
	// 	}
	// }
	$.ajax({
		// url: "https://www.sglotto.com/Api/Web/trendChart",
		url: "/?m=api&c=trend&a=trendChart",
        type: 'POST',
        data: dataNews,
        async: false,
        dataType: "json",
        success: function (msg) {
        	if(!msg.totalPage){
        		pages=0;
        		return false;
        	}
        	pages = msg.totalPage;
        }
    })
	
	    laypage({
	        cont: 'page'
	        ,pages: pages //总页数
	        ,groups: 5 //连续显示分页数
	        // ,skip: true
	        ,jump: function(obj, first){
	        	if(!first){
			        $.ask('Page '+ obj.curr +'，total '+pages+' pages');
			    }
	            dataNews.page = obj.curr;
	            $.ajax({
					// url: "https://www.sglotto.com/Api/Web/trendChart",
					url: "/?m=api&c=trend&a=trendChart",
	                type: 'POST',
	                data: dataNews,
	                // async: false,
	                dataType: "json",
	                success: function (msg) {
 						
					    $(".total_times td:gt(0)").remove();
	        			$(".average td:gt(0)").remove();
	        			$(".max td:gt(0)").remove();
	        			$(".max_continue td:gt(0)").remove();
	        			if(dataNews.lottery_type==7 || dataNews.lottery_type==8){
			        		//六合彩
							lhcTrend(msg);
						}else if(dataNews.lottery_type==1 || dataNews.lottery_type==3){
							xy28Trend(msg);
						}else if(dataNews.lottery_type==5||dataNews.lottery_type==6||dataNews.lottery_type==11){
							//三分彩
							sscTrend(msg);
						}else if(dataNews.lottery_type==2||dataNews.lottery_type==4||dataNews.lottery_type==9||dataNews.lottery_type==14){
							//pk10
							pk10Trend(msg);
						}
						else if(dataNews.lottery_type==10){
							//牛牛
							nnTrend(msg);							
						}else if(dataNews.lottery_type==13){
							//欢乐骰宝
							sbTrend(msg);							
						}
	                }
	            })
	        }
	    });
}
var bit_0 =new Array();
var bit_1 =new Array();
var bit_2 =new Array();
var bit_3 =new Array();
var bit_4 =new Array();
var stage ="";
var ylShow="";
function pk10Trend(msg){
	msg = msg.data;
	var html = '';
	for(var i=0;i<msg.list.length;i++){
		var lotteryNum = msg.list[i].lottery_numbers.split(",");
		var qiu = '';
		msg.list[i].content[1] = msg.list[i].content[1]?english[msg.list[i].content[1]]:msg.list[i].content[1];
		msg.list[i].content[2] = msg.list[i].content[2]?english[msg.list[i].content[2]]:msg.list[i].content[2];
		msg.list[i].content[3] = msg.list[i].content[3]?english[msg.list[i].content[3]]:msg.list[i].content[3];
		msg.list[i].content[4] = msg.list[i].content[4]?english[msg.list[i].content[4]]:msg.list[i].content[4]
		var longhu = msg.list[i].content[5]?"<span class='redText'>Dragon</span>":"<span class='blueText'>Tiger</span>";
		for(var z=0;z<lotteryNum.length;z++){
			qiu+="<td class='pk10Td'><i class='cheBall cheBall"+parseInt(lotteryNum[z])+"' data-num='"+parseInt(lotteryNum[z])+"'>"+lotteryNum[z]+"</i></td>";
		}
		html+="<tr>"+
			"<td>"+(i+1)+"</td>"+
			"<td>"+msg.list[i].lottery_id+"</td>"+
			qiu+
			"<td>"+msg.list[i].content[0]+"</td>"+
			"<td>"+msg.list[i].content[1]+"</td>"+
			"<td>"+msg.list[i].content[2]+"</td>"+
			"<td>"+msg.list[i].content[3]+"</td>"+
			"<td>"+msg.list[i].content[4]+"</td>"+
			"<td>"+longhu+"</td>"+
		"</tr>";
	}
	$(".sub_table").find("tbody").html(html);
}
function nnTrend(msg){
	$("#reslist").html("");
	var tdLen = $(".trend_numstat_tab table thead tr td").length;
	if(msg.data==""||msg.data==null){
		$("#reslist").prepend("<tr>"+
			"<td colspan='"+tdLen+"'>"+"No history"+"</td>"+
		"</tr>");  
        return false;
	}
	var str = '';
	for(var i=0;i<msg.data.length;i++){
		var qiuhao="";
		for(var j=0;j<msg.data[i].numbers_arr.length;j++){
			qiuhao +="<td class='niuniuPai'><i class='pokeImg poke"+msg.data[i].numbers_arr[j]+"'></i></td>"; 
		}
		var which_win = msg.data[i].which_win=='红'?"<span class='redText'>Red</span>":"<span class='blueText'>Blue</span>";
		var gong_pai = msg.data[i].gong_pai==1?"<span class='redText'>Has</span>":"<span class='blueText'>No</span>";
		var long_hu = msg.data[i].long_hu=='龙'?"<span class='redText'>Dragon</span>":"<span class='blueText'>Tiger</span>";
		var sum_dx = msg.data[i].sum_dx=='小'?'<td></td><td>Small</td>':'<td>Big</td><td></td>';
		var sum_ds = msg.data[i].sum_ds=='单'?'<td>Single</td><td></td>':'<td></td><td>Double</td>';
		str += ("<tr>"+
			"<td>"+(i+1)+"</td>"+
			"<td>"+msg.data[i].lottery_id+"</td>"+
			qiuhao+
			"<td>"+which_win+"</td>"+
			"<td>"+english[msg.data[i].niu]+"</td>"+
			"<td>"+gong_pai+"</td>"+
			"<td>"+long_hu+"</td>"+
			sum_dx+
			sum_ds+
		"</tr>");
	};
	$('#reslist').html(str);
}

function sbTrend(msg){
	//骰宝
	msg=msg.data
	$("#reslist").html("");
	var tdLen = $(".trend_numstat_tab table thead tr td").length;
	if(msg.list==""||msg.list==null){
		$("#reslist").prepend("<tr>"+
			"<td colspan='"+tdLen+"'>"+"No history"+"</td>"+
		"</tr>");  
        return false;
	}
	var str = '';
	
	for(var i=0;i<msg.list.length;i++){
		msg.list[i].spare_2[1] = msg.list[i].spare_2[1]?english[msg.list[i].spare_2[1]]:msg.list[i].spare_2[1];
		msg.list[i].spare_2[2] = msg.list[i].spare_2[2]?english[msg.list[i].spare_2[2]]:msg.list[i].spare_2[2];
		msg.list[i].spare_2[3] = msg.list[i].spare_2[3]?english[msg.list[i].spare_2[3]]:msg.list[i].spare_2[3];
		msg.list[i].spare_2[4] = msg.list[i].spare_2[4]?english[msg.list[i].spare_2[4]]:msg.list[i].spare_2[4];
		msg.list[i].spare_2[5] = msg.list[i].spare_2[5]?english[msg.list[i].spare_2[5]]:msg.list[i].spare_2[5];
		str += ("<tr>"+
			"<td>"+msg.list[i].issue+"</td>"+
			"<td style='color:#E5001C'>"+msg.list[i].spare_2[0]+"</td>"+
			"<td style='color:#F02284'>"+msg.list[i].spare_2[1]+"</td>"+
			"<td style='color:#348FED'>"+msg.list[i].spare_2[2]+"</td>"+
			"<td style='color:#F02284'>"+msg.list[i].spare_2[3]+"</td>"+
			"<td style='color:#348FED'>"+msg.list[i].spare_2[4]+"</td>"+
			"<td style='color:#E57942'>"+msg.list[i].spare_2[5]+"</td>"+
		"</tr>");
	};
	$('#reslist').html(str);
}
function lhcTrend(msg){
	$("#reslist").html("");
	var tdLen = $(".trend_numstat_tab table thead tr td").length;
	if(msg.data==""||msg.data==null){
		$("#reslist").prepend("<tr>"+
                                "<td colspan='"+tdLen+"'>"+"No history"+"</td>"+
                            "</tr>");  
		// layer.closeAll();
        return false;
    }
	for(var i=0;i<msg.data.length;i++){
		var qiuhao="";
		var info = msg.data[i].info;
		var str='';
		for(var j=0;j<msg.data[i].numbers.length;j++){
			qiuhao +="<td><i class='icon'>"+ (msg.data[i].numbers[j]<10?"0"+msg.data[i].numbers[j]:msg.data[i].numbers[j]) +"</i></td>"; 
		}
		var str='',str2=''
		if(info.tmHe==''){
			str = "<span>"+ (info.tm_sumBig=="" ? info.tm_sumSmall : info.tm_sumBig) +"</span>"+
			"<span>"+ (info.tm_sumDouble=="" ? info.tm_sumSingle : info.tm_sumDouble) +"</span>";
		}else{
			str2 = "<span>"+ info.tmHe +"</span>"
		}
		$("#reslist").append("<tr>"+
								"<td>"+(i+1)+"</td>"+
								"<td>"+msg.data[i].lottery_id+"</td>"+
								qiuhao+
								"<td>"+
									"<span class='col_count'>"+info.sum+"</span>"+
									"<span>"+ (info.sumBig=="" ? info.sumSmall : info.sumBig) +"</span>"+
									"<span>"+ (info.sumDouble=="" ? info.sumSingle : info.sumDouble) +"</span>"+
								"</td>"+
								"<td>"+
									"<span>"+ (info.tmSmall=="" ? info.tmBig : info.tmSmall) +"</span>"+
									"<span>"+ (info.tmDouble=="" ? info.tmSingle : info.tmDouble) +"</span>"+
									str2+
								"</td>"+
								"<td>"+
									str+
								"</td>"+
								"<td class='col_long'>"+info.tmAnimal+"</td>"+
								"<td class='col_red'>"+info.sebo+"</td>"+
							"</tr>");
	}
	$("#reslist").find("span").each(function(){
		if($(this).html()=="大"||$(this).html()=="合大"){
			$(this).addClass("col_big");
		}else if($(this).html()=="小"||$(this).html()=="合小"){
			$(this).addClass("col_small");
		}else if($(this).html()=="单"||$(this).html()=="合单"){
			$(this).addClass("col_single");
		}else if($(this).html()=="双"||$(this).html()=="合双"){
			$(this).addClass("col_double");
		}
	})
	$("#reslist").find(".icon").each(function(){
		var colVal = $(this).html();
		var arrRed = ['01','02','07','08','12','13','18','19','23','24','29','30','34','35','40','45','46'];
        var arrBlue = ['03','04','09','10','14','15','20','25','26','31','36','37','41','42','47','48'];
        var arrGreen = ['05','06','11','16','17','21','22','27','28','32','33','38','39','43','44','49'];
        var inxRed = $.inArray(colVal , arrRed);
        var inxBlue = $.inArray(colVal , arrBlue);
        var inxGreen = $.inArray(colVal , arrGreen);

        if(inxRed > '-1'){
            $(this).addClass("i_red");
        }
        if(inxBlue > '-1'){
            $(this).addClass("i_blue");
        }
        if(inxGreen > '-1'){
            $(this).addClass("i_green");
        }
	})
	// layer.closeAll();
}

function xyncTrend(msg){
	var tdLen = $(".trend_numstat_tab table thead tr td").length+$(".trend_numstat_tab table thead tr th").length;
	$(".trend_numstat_tab table").find("tbody").find(".qiu").remove();
	if(msg.data==""||msg.data==null){
		$(".trend_numstat_tab table").find("tbody").prepend("<tr>"+
				                                                "<td colspan='"+tdLen+"'>"+"No history"+"</td>"+
				                                            "</tr>");  
		// layer.closeAll();
        return false;
    }
    var result ="";
    var qiu = [];
    for(var i=0;i<msg.data.length;i++){
    	var yl="";
        qiu = msg.data[i].numbers.split(",");
    	for(var j=0;j<msg.yl[i].length;j++){
	    	yl +="<td><i class='hideTd'>"+msg.yl[i][j]+"</i></td>";
	    }
	    result+="<tr class='qiu'>"+
                    "<td class='qihao'>"+msg.data[i].lottery_id+"</td>"+
                    yl+
                "</tr>";
    }

    $(".trend_numstat_tab table").find("tbody").prepend(result);
  	$(".qiu").each(function(){
  		var tdQiu = $(this).find("td").not(".qihao");
  		for(var i=0;i<tdQiu.length;i++){
  			if(tdQiu.eq(i).find("i").html()=="0"){
  				tdQiu.eq(i).find("i").removeClass("hideTd");
  				tdQiu.eq(i).html("<i class='icon i"+(((i+1) <10)? '0'+(i+1) :(i+1))+"'>"+(i+1)+"</i>");
  				
  			}
  		}
  	})
	// layer.closeAll();
}
function klsfTrend(msg){
	var tdLen = $(".trend_numstat_tab table thead tr td").length+$(".trend_numstat_tab table thead tr th").length;
	$(".trend_numstat_tab table").find("tbody").find(".qiu").remove();
	if(msg.data==""||msg.data==null){
		$(".trend_numstat_tab table").find("tbody").prepend("<tr>"+
				                                                "<td colspan='"+tdLen+"'>"+"No history"+"</td>"+
				                                            "</tr>");  
		// layer.closeAll();
        return false;
    }
    var result ="";
    var qiu = [];
    for(var i=0;i<msg.data.length;i++){
    	var yl="";
        qiu = msg.data[i].numbers.split(",");
    	for(var j=0;j<msg.yl[i].length;j++){
	    	yl +="<td><i class='hideTd'>"+msg.yl[i][j]+"</i></td>";
	    }
	    result+="<tr class='qiu'>"+
                    "<td class='qihao'>"+msg.data[i].lottery_id+"</td>"+
                    yl+
                "</tr>";
    }

    $(".trend_numstat_tab table").find("tbody").prepend(result);
  	$(".qiu").each(function(){
  		var tdQiu = $(this).find("td").not(".qihao");
  		for(var i=0;i<tdQiu.length;i++){
  			if(tdQiu.eq(i).find("i").html()=="0"){
  				tdQiu.eq(i).find("i").removeClass("hideTd");
  				if(i>17){
  					tdQiu.eq(i).html("<i class='icon i2'>"+(i+1)+"</i>");
  				}else{
  					tdQiu.eq(i).html("<i class='icon i5'>"+(i+1)+"</i>");
  				}
  				
  			}
  		}
  	})
	// layer.closeAll();
}
function qxcTrend(msg){
	bit_0 =new Array();
	bit_1 =new Array();
	bit_2 =new Array();
	bit_3 =new Array();
	stage ="";

	if(msg.data==""||msg.data==null){
		issue ="<tr><td>"+"No Records"+"</td></tr>";
		noresult ="<div class='noresult'>No Records</div>";
        $("#issue tbody").html(issue);
		$(".trend_numstat").append(noresult); 		$('#getResult').hide()
		
    	stage=1;
    	ylShow = "";
    	CreateFun();
		// layer.closeAll();
        return false;
    }

    $('#getResult').show(); 	$('.noresult').remove();     stage =msg.data.length;
    ylShow = msg;
	var issue ="";
	var result ="";
	var xuhao ="";
	var num='';
    for(var i=0; i<stage; i++){
    	num=msg.data[i].numbers.split(",");
        bit_3.push('T3'+i+"_"+num[0]);
        bit_2.push('T2'+i+"_"+num[1]);
        bit_1.push('T1'+i+"_"+num[2]);
        bit_0.push('T0'+i+"_"+num[3]);
        xuhao +="<tr><td>"+(i+1)+"</td></tr>";
        issue +="<tr><td>"+msg.data[i].lottery_id+"</td></tr>";
        result +="<tr><td>"+msg.data[i].numbers+"</td></tr>";
    }
    $("#xuhao tbody").html(xuhao);
    $("#issue tbody").html(issue);
    $("#result tbody").html(result);
    CreateFun();
	// layer.closeAll();
}
function dpcTrend(msg){
	bit_0 =new Array();
	bit_1 =new Array();
	bit_2 =new Array();
	stage ="";

	if(msg.data==""||msg.data==null){
		issue ="<tr><td>"+"No Records"+"</td></tr>";
        noresult ="<div class='noresult'>No Records</div>";
        $("#xuhao tbody").html('<tr><td>1</td></tr>');
        $("#issue tbody").html(issue);
		$(".trend_numstat").append(noresult); 		$('#getResult').hide()
		
    	stage=1;
    	ylShow = "";
    	CreateFun();
		// layer.closeAll();
        return false;
    }

    $('#getResult').show(); 	$('.noresult').remove();     stage =msg.data.length;
    ylShow = msg;
	var issue ="";
	var result ="";
	var xuhao ="";
	var num='';
    for(var i=0; i<stage; i++){
    	num=msg.data[i].numbers.split(",");
        bit_2.push('T2'+i+"_"+num[0]);
        bit_1.push('T1'+i+"_"+num[1]);
        bit_0.push('T0'+i+"_"+num[2]);
        xuhao +="<tr><td>"+(i+1)+"</td></tr>";
        issue +="<tr><td>"+msg.data[i].lottery_id+"</td></tr>";
        result +="<tr><td>"+msg.data[i].numbers+"</td></tr>";
    }
    $("#xuhao tbody").html(xuhao);
    $("#issue tbody").html(issue);
    $("#result tbody").html(result);
    CreateFun();
	// layer.closeAll();
}
function bjkl8Trend(msg){
	var zongkai=[];//总开
	var zdlk=[];//最大连开
	var zdyl=[];//最大遗漏
	var pjyl=[];//平均遗漏
	var dqyl=[];//当前遗漏
	var inx=0;//初始值
	$(".trend_numstat_tab").each(function(){
		var dataNum=$(this).find("table").attr("data-num");
		if(dataNum=="01"){
			zongkai = msg.cxzc.slice(0,20);
			zdlk = msg.zdlk.slice(0,20);
			zdyl = msg.zdyl.slice(0,20);
			pjyl = msg.avgyl.slice(0,20);
			dqyl = msg.dqyl.slice(0,20);
			inx=1;
		}else if(dataNum=="21"){
			zongkai = msg.cxzc.slice(20,40);
			zdlk = msg.zdlk.slice(20,40);
			zdyl = msg.zdyl.slice(20,40);
			pjyl = msg.avgyl.slice(20,40);
			dqyl = msg.dqyl.slice(20,40);
			inx=21;
		}else if(dataNum=="41"){
			zongkai = msg.cxzc.slice(40,60);
			zdlk = msg.zdlk.slice(40,60);
			zdyl = msg.zdyl.slice(40,60);
			pjyl = msg.avgyl.slice(40,60);
			dqyl = msg.dqyl.slice(40,60);
			inx=41;
		}else if(dataNum=="61"){
			zongkai = msg.cxzc.slice(60,80);
			zdlk = msg.zdlk.slice(60,80);
			zdyl = msg.zdyl.slice(60,80);
			pjyl = msg.avgyl.slice(60,80);
			dqyl = msg.dqyl.slice(60,80);
			inx=61;
		}	
		var classN = "i5";
		var html = '';
		for(var j=0;j<20;j++){
			if(inx>39){
				classN="i4";
			}
			html+="<tr>"+
					"<td><i class='icon "+classN+"'>"+inx+"</i></td>"+
					"<td>"+zongkai[j]+"</td>"+
					"<td>"+zdlk[j]+"</td>"+
					"<td>"+zdyl[j]+"</td>"+
					"<td>"+pjyl[j]+"</td>"+
					"<td>"+dqyl[j]+"</td>"+
				  "</tr>";
			inx++;
		}	
		$(this).find("table").find("tbody").html(html);
	})
	// layer.closeAll();
}
function sscTrend(msg){
	bit_0 =new Array();
	bit_1 =new Array();
	bit_2 =new Array();
	bit_3 =new Array();
	bit_4 =new Array();
	stage ="";

	if(msg.data==""||msg.data==null){
		issue ="<tr><td>"+"No Records"+"</td></tr>";
        noresult ="<div class='noresult'>No Records</div>";
        $("#issue tbody").html(issue);
		$(".trend_numstat").append(noresult); 		$('#getResult').hide()
		
    	stage=1;
    	ylShow = "";
    	CreateFun();
		// layer.closeAll();
        return false;
    }

    $('#getResult').show(); 	$('.noresult').remove();     stage =msg.data.length;
    ylShow = msg;
	var issue ="";
	var result ="";
	var num='';
    for(var i=0; i<stage; i++){
    	num=msg.data[i].numbers.split(",");
        bit_4.push('T4'+i+"_"+num[0]);
        bit_3.push('T3'+i+"_"+num[1]);
        bit_2.push('T2'+i+"_"+num[2]);
        bit_1.push('T1'+i+"_"+num[3]);
        bit_0.push('T0'+i+"_"+num[4]);
        issue +="<tr><td>"+msg.data[i].lottery_id+"</td></tr>";
        result +="<tr><td>"+msg.data[i].numbers+"</td></tr>";
    }
    $("#issue tbody").html(issue);
    $("#result tbody").html(result);
    CreateFun();
	// layer.closeAll();
}
function ksTrend(msg){
	bit_0 =new Array();
	bit_1 =new Array();
	bit_2 =new Array();
	stage ="";
	if(msg.data==""||msg.data==null){
		issue ="<tr><td>"+"No Records"+"</td></tr>";
        noresult ="<div class='noresult'>No Records</div>";
        issueD ="<tr><td>"+"No Records"+"</td></tr>";
        $("#issue tbody").html(issue);
    	$(".trend_numstat").append(noresult); 		$('#getResult').hide()
		$("#issueD tbody").html(issueD);
		
    	stage=1;
    	ylShow = "";
    	CreateFun2();
		// layer.closeAll();
        return false;
    }
    $('#getResult').show(); 	$('.noresult').remove();     stage =msg.data.length;
    ylShow = msg;
	var issue ="";
	var result ="";
    for(var i=0; i<stage; i++){
    	num=msg.data[i].numbers.split(",");
        bit_2.push('T2'+i+"_"+num[0]);
        bit_1.push('T1'+i+"_"+num[1]);
        bit_0.push('T0'+i+"_"+num[2]);
        issue +="<tr><td>"+msg.data[i].lottery_id+"</td></tr>";
        result +="<tr><td>"+msg.data[i].numbers+"</td></tr>";
    }
    var  issueD="";
    for(var k=0; k<msg.lottery.length; k++){
    	var text = new Array();
    	issueD+='<tr>';
    	for(var j=0; j<msg.lottery[k].content.length; j++){
    		var t =msg.lottery[k];
    		text.push(t.content[j]);
    		issueD+='<td>'+t.content[j]+'</td>';
    		
    	}
    	issueD+='</tr>';
    }
    $("#issueD tbody").html(issueD);
    $("#issue tbody").html(issue);
    $("#result tbody").html(result);
    $("#issueD tbody tr td").eq(0).css("width","60px");
    $("#issueD tbody tr td").eq(3).css("width","60px");
    CreateFun2();
	// layer.closeAll();
}
function xy28Trend(msg){
	bit_0 =new Array();
	stage ="";
	noresult ="<div class='noresult'>No Records</div>";
	if(msg.data==""||msg.data==null){
		issue ="<tr><td>"+"No Records"+"</td></tr>";
        issueD ="<tr><td>"+"No Records"+"</td></tr>";
        $("#issue tbody").html(issue);
		$(".trend_numstat").append(noresult);
		$('#getResult').hide()
    	$("#issueD tbody").html(issueD);
    	stage=1;
    	ylShow = "";
    	// CreateFun3();
		// layer.closeAll();aaa
        return false;
	}
    $('#getResult').show(); 	$('.noresult').remove();     stage =msg.data.length;
    ylShow = msg;
	var issue ="";
	var result ="";
	var result2 ="";
    for(var i=0; i<stage; i++){
    	num=msg.data[i].numbers.split(",");
        bit_0.push('T0'+i+"_"+msg.data[i].numbers);
        issue +="<tr><td>"+msg.data[i].lottery_id+"</td></tr>";
         var result1 ="";
        
        result +="<tr><td>"+result1+"</td></tr>";
    }
    var issueD="";
    for(var k=0; k<msg.lottery.length; k++){
    	var text = new Array();
    	issueD+='<tr>';
    	for(var j=0; j<msg.lottery[k].content.length; j++){
    		var t =msg.lottery[k];
   			
    		text.push(t.content[j]);
    
    		if(j>0&&j<5){
    			issueD+='<td>'+t.content[j]+'</td>';
    		}
    	}
    	if(text[9]||text[10]){
    		var ta =text[9]+text[10];
    		issueD+='<td>'+ta+'</td>';
    	}else if(text[9]==""&&text[10]==""){
			issueD+='<td>&nbsp;</td>';
    	}
    	issueD+='</tr>';
    }
    $("#issueD tbody").html(issueD);
    $("#issue tbody").html(issue);
    CreateFun3();
	// layer.closeAll();
}
function CreateFun3(flag){
	if(flag){
		return
	}
    CreateTable3();
    function CreateTable3() {
    	var tId = $(".trend_numstat_tab .len").length;
        var inx=28;
		for(var i =0; i<tId; i++){
			inx--;
			if(i==0){
            	var arr = bit_0;
            }else if(i==1){
            	var arr = bit_1;
            }else if(i==2){
            	var arr = bit_2;
            }
            var tbody = "";
            for (var g = 0; g <stage; g++) {
                tbody += "<tr class='qiu'>";
                // if(ylShow==""){
	                for (var j = 0; j < 28; j++) {
						// tbody += "<td id='T"+ i + g + "_" + ((j <10)? '0'+j :j) + "'><i class='omit'>" + ((j <10)? '0'+j :j) + "</i></td>";
						if(j==arr[g].split("_")[1]){				
							tbody += "<td id='T"+ i + g + "_" + ((j <10)? '0'+j :j) + "'><i class='omit'>" + ((j <10)? '0'+j :j) + "</i></td>";
						}else{
							tbody += "<td id='T"+ i + g + "_" + ((j <10)? '0'+j :j) + "'></td>";
						}
					}	
                // }else{
	            //     for (var j = 0; j < ylShow.yl[g].length; j++) {
	            //         tbody += "<td id='T"+ i + g + "_" + ((j <10)? '0'+j :j) + "'><i class='omit hideTd'>" + ylShow.yl[g][j] + "</i></td>";
	            //     }	
                // }
            }
            tbody += "</tr>";
            $("#zstable"+i+" tbody").html(tbody);
            // $("#zstable"+i+" tbody").find("tr").each(function(){
            // 	var showTd = $(this).find("td");
            // 	for(var k=0;k<showTd.length;k++){
            // 		if(showTd.eq(k).find("i").html()=="0"){
            // 			showTd.eq(k).find("i").removeClass("hideTd");
            // 			showTd.eq(k).find("i").html(k);
            // 		}
            // 	}
            // })
		}
    }
    CreateLine(bit_0, 20, "#ff6600", "canvasdiv0", "#2a9342");
}
function CreateFun2(){
    CreateTable2();
    function CreateTable2() {
        var tId = $(".trend_numstat_tab .len").length;
        var inx=tId;
		for(var i =0; i<tId; i++){
			inx--;
			var tbody = "";
			if(i==0){
            	var arr = bit_0;
            }else if(i==1){
            	var arr = bit_1;
            }else if(i==2){
            	var arr = bit_2;
            }
            for (var g = 0; g <stage; g++) {
                tbody += "<tr class='qiu'>";
                // if(ylShow==""){
	                for (var j = 0; j < 10; j++) {
	                	if(j==arr[g].split("_")[1]){
	                		tbody += "<td id='T"+ i + g + "_" + j + "'><i class='omit'>"+j+"</i></td>";
	                	}else{
	                		tbody += "<td id='T"+ i + g + "_" + j + "'><i class='omit hideTd'></i></td>";
	                	}
	                    
	                }	
                // }else{
	               //  for (var j = 0; j < ylShow.yl[g][inx].length; j++) {
	               //      tbody += "<td id='T"+ i + g + "_" + j + "'><i class='omit hideTd'>" + ylShow.yl[g][inx][j] + "</i></td>";
	               //  }	
                // }
            }
            tbody += "</tr>";
			$("#zstable"+i+" tbody").html(tbody);
            // $("#zstable"+i+" tbody").find("tr").each(function(){
            // 	var showTd = $(this).find("td");
            // 	for(var k=0;k<showTd.length;k++){
            // 		if(showTd.eq(k).find("i").html()=="0"){
            // 			showTd.eq(k).find("i").removeClass("hideTd");
            // 			showTd.eq(k).find("i").html(k+1);
            // 		}
            // 	}
            // })
		}
    }
    CreateLine(bit_2, 20, "#ff6600", "canvasdiv2", "#0170b6");
    CreateLine(bit_1, 20, "#ff6600", "canvasdiv1", "#a65d00");
    CreateLine(bit_0, 20, "#ff6600", "canvasdiv0", "#2a9342");
}
function CreateFun(){
    CreateTable();
    function CreateTable() {
        var tId = $(".trend_numstat_tab .len").length;
        var inx=tId;
		for(var i =0; i<tId; i++){
			inx--;
            var tbody = "";
            if(i==0){
            	var arr = bit_0;
            }else if(i==1){
            	var arr = bit_1;
            }else if(i==2){
            	var arr = bit_2;
            }else if(i==3){
            	var arr = bit_3;
            }else if(i==4){
            	var arr = bit_4;
            }
            for (var g = 0; g <stage; g++) {
                tbody += "<tr class='qiu'>";
                // if(ylShow==""){
	                for (var j = 0; j < 10; j++) {
	                	if(j==arr[g].split("_")[1]){
	                		tbody += "<td id='T"+ i + g + "_" + j + "'><i class='omit'>"+j+"</i></td>";
	                	}else{
	                		tbody += "<td id='T"+ i + g + "_" + j + "'><i class='omit hideTd'></i></td>";
	                	}
	                    
	                }	
                // }else{
	               //  for (var j = 0; j < ylShow.yl[g][inx].length; j++) {
	               //      tbody += "<td id='T"+ i + g + "_" + j + "'><i class='omit hideTd'>" + ylShow.yl[g][inx][j] + "</i></td>";
	               //  }	
                // }
            }
            tbody += "</tr>";
            $("#zstable"+i+" tbody").html(tbody);

            // $("#zstable"+i+" tbody").find("tr").each(function(){
            // 	var showTd = $(this).find("td");
            // 	for(var k=0;k<showTd.length;k++){
            // 		if(showTd.eq(k).find("i").html()=="0"){
            // 			showTd.eq(k).find("i").removeClass("hideTd");
            // 			showTd.eq(k).find("i").html(k);
            // 		}
            // 	}
            // })
		}
    }
    CreateLine(bit_4, 20, "#ff6600", "canvasdiv4", "#ea740a");
    CreateLine(bit_3, 20, "#ff6600", "canvasdiv3", "#413106");
    CreateLine(bit_2, 20, "#ff6600", "canvasdiv2", "#0170b6");
    CreateLine(bit_1, 20, "#ff6600", "canvasdiv1", "#a65d00");
    CreateLine(bit_0, 20, "#ff6600", "canvasdiv0", "#2a9342");
}
//画线
function CreateLine(ids, w, c, div, bg) {
	if($("#" + div).length==0){
		return false;
	}
    $("#" + div).html("");
    var list = ids;
    for (var j = list.length - 1; j > 0; j--) {
        var tid = $("#" + list[j]);
        var fid = $("#" + list[j - 1]);
        var f_width = fid.outerWidth();
        var f_height = fid.outerHeight();
        var f_offset = fid.offset();
        var f_top = f_offset.top-$(".trend_numstat_tab").offset().top;
        var f_left = f_offset.left-$(".trend_numstat_tab").offset().left;
        var t_offset = tid.offset();
        var t_top = t_offset.top-$(".trend_numstat_tab").offset().top;
        var t_left = t_offset.left-$(".trend_numstat_tab").offset().left;
        var cvs_left = Math.min(f_left, t_left);
        var cvs_top = Math.min(f_top, t_top);
        tid.children("i").css("background", bg).css("color", "#fff");
        fid.children("i").css("background", bg).css("color", "#fff");
        var cvs = document.createElement("canvas");
        cvs.width = Math.abs(f_left - t_left) < w ? w : Math.abs(f_left - t_left);
        cvs.height = Math.abs(f_top - t_top);
        cvs.style.top = cvs_top + parseInt(f_height / 2) + "px";
        cvs.style.left = cvs_left + parseInt(f_width / 2) + "px";
        cvs.style.position = "absolute";
        var cxt = cvs.getContext("2d");
        cxt.save();
        cxt.strokeStyle = c;
        cxt.lineWidth = 1;
        cxt.lineJoin = "round";
        cxt.beginPath();
        cxt.moveTo(f_left - cvs_left, f_top - cvs_top);
        cxt.lineTo(t_left - cvs_left, t_top - cvs_top);
        cxt.closePath();
        cxt.stroke();
        cxt.restore();
        $("#" + div).append(cvs);
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

$(function(){
	//北京PK10号码统计遗漏范围
	$(document).on("click",".explain_list button",function(){
		var inx = parseFloat($(this).prev("input").val());
		$(".tongji").find("td").each(function(){
			if($(this).html()>inx){
				$(this).css("color","red")
			}
		})
	})
    
})