(window["webpackJsonp"]=window["webpackJsonp"]||[]).push([["chunk-348d5522"],{"154a":function(t,e,a){},"28a5":function(t,e,a){"use strict";var i=a("aae3"),s=a("cb7c"),n=a("ebd6"),r=a("0390"),l=a("9def"),c=a("5f1b"),o=a("520a"),u=a("79e5"),g=Math.min,d=[].push,p="split",m="length",h="lastIndex",v=4294967295,f=!u((function(){RegExp(v,"y")}));a("214f")("split",2,(function(t,e,a,u){var _;return _="c"=="abbc"[p](/(b)*/)[1]||4!="test"[p](/(?:)/,-1)[m]||2!="ab"[p](/(?:ab)*/)[m]||4!="."[p](/(.?)(.?)/)[m]||"."[p](/()()/)[m]>1||""[p](/.?/)[m]?function(t,e){var s=String(this);if(void 0===t&&0===e)return[];if(!i(t))return a.call(s,t,e);var n,r,l,c=[],u=(t.ignoreCase?"i":"")+(t.multiline?"m":"")+(t.unicode?"u":"")+(t.sticky?"y":""),g=0,p=void 0===e?v:e>>>0,f=new RegExp(t.source,u+"g");while(n=o.call(f,s)){if(r=f[h],r>g&&(c.push(s.slice(g,n.index)),n[m]>1&&n.index<s[m]&&d.apply(c,n.slice(1)),l=n[0][m],g=r,c[m]>=p))break;f[h]===n.index&&f[h]++}return g===s[m]?!l&&f.test("")||c.push(""):c.push(s.slice(g)),c[m]>p?c.slice(0,p):c}:"0"[p](void 0,0)[m]?function(t,e){return void 0===t&&0===e?[]:a.call(this,t,e)}:a,[function(a,i){var s=t(this),n=void 0==a?void 0:a[e];return void 0!==n?n.call(a,s,i):_.call(String(s),a,i)},function(t,e){var i=u(_,t,this,e,_!==a);if(i.done)return i.value;var o=s(t),d=String(this),p=n(o,RegExp),m=o.unicode,h=(o.ignoreCase?"i":"")+(o.multiline?"m":"")+(o.unicode?"u":"")+(f?"y":"g"),y=new p(f?o:"^(?:"+o.source+")",h),T=void 0===e?v:e>>>0;if(0===T)return[];if(0===d.length)return null===c(y,d)?[d]:[];var D=0,w=0,b=[];while(w<d.length){y.lastIndex=f?w:0;var x,L=c(y,f?d:d.slice(w));if(null===L||(x=g(l(y.lastIndex+(f?0:w)),d.length))===D)w=r(d,w,m);else{if(b.push(d.slice(D,w)),b.length===T)return b;for(var k=1;k<=L.length-1;k++)if(b.push(L[k]),b.length===T)return b;w=D=x}}return b.push(d.slice(D)),b}]}))},"5fe9":function(t,e,a){"use strict";a.d(e,"a",(function(){return i}));a("28a5");function i(t,e){var a=new Date,i=a.getTime(),s=a.getDate(),n=a.toLocaleDateString(),r={beginTime:"",endTime:""};if(r.endTime=n,!e||"DD"==e){var l=i-3600*Math.abs(t)*1e3*24;t!=Math.abs(t)&&(r.endTime=new Date(l).toLocaleDateString()),r.beginTime=new Date(l).toLocaleDateString()}if("MM"==e){if(Math.abs(t)>12)return!1;if(0==t){var c=n.split("/");c.splice(2,1,"1"),r.beginTime=c.join("/")}else{for(var o=i-3600*s*1e3*24,u=new Date(o).toLocaleDateString(),g=0;g<Math.abs(t)-1;g++){var d=u.split("/"),p=d[d.length-1];o-=3600*p*1e3*24,u=new Date(o).toLocaleDateString()}if(t==Math.abs(t)){var m=n.split("/");u.split("/")[2]<m[2]&&(m[2]=u.split("/")[2]),m[1]-t<=0?(m[0]=m[0]-1,m[1]=12-Math.abs(m[1]-t)):m.splice(1,1,m[1]-t),r.beginTime=m.join("/")}else{var h=u.split("/");h.splice(2,1,"1"),r.beginTime=h.join("/"),r.endTime=new Date(o).toLocaleDateString()}}}return r}},"761f":function(t,e,a){"use strict";a.r(e);var i=function(){var t=this,e=t.$createElement,a=t._self._c||e;return a("div",[a("h2",{staticClass:"public-title"},[t._v(t._s(t.english.投注记录))]),a("div",{staticClass:"search-wrapper clearfix"},[a("div",{staticClass:"search-item item-auto"},[a("span",[t._v(t._s(t.english.时间)+"： ")])]),a("div",{staticClass:"search-item item-DatePicker"},[a("DatePicker",{ref:"beginTime",attrs:{options:t.startOption,placeholder:t.english.开始时间},model:{value:t.beginTime,callback:function(e){t.beginTime=e},expression:"beginTime"}}),a("DatePicker",{ref:"endTime",attrs:{options:t.startOption,placeholder:t.english.结束时间},model:{value:t.endTime,callback:function(e){t.endTime=e},expression:"endTime"}})],1),a("div",{staticClass:"search-item"},[a("Button",{attrs:{type:"error"},on:{click:t.search}},[t._v(t._s(t.english.搜索))])],1),a("div",{staticClass:"search-item trading"},[a("span",{staticClass:"text"},[t._v("|")]),a("span",{class:1==t.msg?"cur":"",on:{click:function(e){return t._getDate(0,"DD",1)}}},[t._v(t._s(t.english.今天))]),a("span",{class:2==t.msg?"cur":"",on:{click:function(e){return t._getDate(-1,"DD",2)}}},[t._v(t._s(t.english.昨天))]),a("span",{class:3==t.msg?"cur":"",on:{click:function(e){return t._getDate(7,"DD",3)}}},[t._v(t._s(t.english.本周))]),a("span",{class:4==t.msg?"cur":"",on:{click:function(e){return t._getDate(0,"MM",4)}}},[t._v(t._s(t.english.本月))]),a("span",{class:5==t.msg?"cur":"",on:{click:function(e){return t._getDate(-1,"MM",5)}}},[t._v(t._s(t.english.上月))])])]),a("div",{directives:[{name:"show",rawName:"v-show",value:t.typeList.length||t.statusList.length,expression:"typeList.length || statusList.length"}],staticClass:"screen-wrapper clearfix"},[a("ul",[a("li",{staticClass:"screen-list"},[a("div",{staticClass:"name"},[t._v(t._s(t.english.分类)+"：")]),a("div",{staticClass:"all"},[a("span",{class:{active:0==t.lotteryType},on:{click:function(e){return t._lotteryType(0)}}},[t._v(t._s(t.english.全部))])]),a("div",{staticClass:"list"},t._l(t.typeList,(function(e,i){return a("span",{directives:[{name:"show",rawName:"v-show",value:6==e.id||e.id>=9,expression:"item.id==6||item.id>=9"}],key:i,class:{active:t.lotteryType==e.id},on:{click:function(a){return t._lotteryType(e.id)}}},[t._v(t._s(t.english[e.name]))])})),0)]),a("li",{staticClass:"screen-list"},[a("div",{staticClass:"name"},[t._v(t._s(t.english.状态)+"：")]),a("div",{staticClass:"all"},[a("span",{class:{active:0==t.tradingStatus},on:{click:function(e){return t._tradingStatus(0)}}},[t._v(t._s(t.english.全部))])]),a("div",{staticClass:"list"},t._l(t.statusList,(function(e,i){return a("span",{key:i,class:{active:t.tradingStatus==e.id},on:{click:function(a){return t._tradingStatus(e.id)}}},[t._v(t._s(t.english[e.name]))])})),0)])])]),a("Table",{staticClass:"table-wrapper",attrs:{stripe:"",columns:t.tableData,data:t.listData,loading:t.listLoading}}),a("div",{directives:[{name:"show",rawName:"v-show",value:t.listData.length,expression:"listData.length"}],staticClass:"page-wrapper clearfix"},[a("div",{staticClass:"total-wrapper"},[a("span",[t._v(t._s(t.english.投注)+": ")]),a("span",{staticClass:"text-green"},[t._v("-"+t._s(t.amountTotal.money))]),a("span",{staticStyle:{padding:"0 5px"}},[t._v("|")]),a("span",[t._v(t._s(t.english.中奖)+": ")]),a("span",{staticClass:"text-red"},[t._v(t._s(t.amountTotal.award>=0?"+":"-")+t._s(t.amountTotal.award))]),a("span",{staticStyle:{padding:"0 5px"}},[t._v("|")]),a("span",[t._v(t._s(t.english.盈利)+": ")]),a("span",{class:t.profit>0?"text-red":"text-green"},[t._v(t._s(t.profit>0?"+":"")+t._s(t.profit))])]),a("Page",{attrs:{total:t.pageCount,current:t.page},on:{"on-change":function(e){return t._getPage(t.page)},"update:current":function(e){t.page=e}}})],1)],1)},s=[],n=(a("28a5"),a("7f7f"),a("8c91")),r=a("5fe9"),l=a("0207"),c={data:function(){return{startOption:{disabledDate:function(t){return t&&t.valueOf()>Date.now()}},english:l,msg:-1,beginTime:"",endTime:"",lotteryType:0,tradingStatus:0,pageCount:10,page:1,profit:0,listLoading:!1,typeList:[],statusList:[],amountTotal:[],listData:[],tableData:[{title:l.时间,key:"addtime"},{title:l.期号,key:"issue"},{title:l.类型,key:"name"},{title:l.投注内容,key:"way"},{title:l.投注金额,render:function(t,e){return t("div",[t("span",{class:"text-green"},"-"+e.row.money)])}},{title:l.中奖金额,render:function(t,e){return t("div",[t("span",{class:"text-red"},"+"+e.row.award)])}},{title:l.状态,render:function(t,e){return t("div",[t("span",{class:"已中奖"==e.row.status?"text-red":""},l[e.row.status])])}}]}},created:function(){this.$route.query.lottery_type&&(this.lotteryType=this.$route.query.lottery_type),this._getDate(0,"DD",-1)},methods:{_getTimes:function(){this.beginTime=this.$refs.beginTime.visualValue,this.endTime=this.$refs.endTime.visualValue},search:function(){var t=this;if(t.msg=-1,t.page=1,t._getTimes(),t.beginTime>t.endTime)return t.$Message.warning("结束时间不能小于开始时间"),!1;t._getListData()},_getDate:function(t,e,a){var i=this;if(i.msg=a,i.page=1,7==t){var s=new Date,l=s.getTime(),c=s.getDay();i.beginTime=Object(n["a"])(new Date(l-864e5*c),"yyyy-MM-dd"),i.endTime=Object(n["a"])(new Date(l),"yyyy-MM-dd")}else{var o=Object(r["a"])(t,e);i.beginTime=Object(n["a"])(new Date(o.beginTime),"yyyy-MM-dd"),i.endTime=Object(n["a"])(new Date(o.endTime),"yyyy-MM-dd")}i._getListData()},_getPage:function(t){var e=this;e.page=t,e._getTimes(),e._getListData()},resetAll:function(){var t=this;t.msg=-1,t.beginTime="",t.endTime="",t.lotteryType=0,t.tradingStatus=0,t.page=1,t._getListData()},_lotteryType:function(t){var e=this;e.lotteryType=t,e.page=1,e._getTimes(),e._getListData()},_tradingStatus:function(t){var e=this;e.tradingStatus=t,e.page=1,e._getTimes(),e._getListData()},_getListData:function(){var t=this,e=this;e.listLoading=!0;var a={};a.token=localStorage.getItem("token"),a.start_time=e.beginTime,a.end_time=e.endTime,a.type=e.lotteryType,a.status=e.tradingStatus,a.page=e.page,e.$http.post(e.urlRequest+"?m=api&c=order&a=betList",a).then((function(a){0==a.data.status?(e.typeList=a.data.gameInfo,e.statusList=a.data.trantype,e.amountTotal=a.data.total,e.profit=(e.amountTotal.award-e.amountTotal.money).toFixed(2),e.listData=a.data.list,e.listData=e.listData.map((function(t){return t.name=l[t.name],t.way.split("_").length>1?t.way=l[t.way.split("_")[0]]+"_"+l[t.way.split("_")[1]]:t.way=l[t.way],t})),e.pageCount=10*a.data.pageNum):""!=a.data.ret_msg&&t.$Message.warning(a.data.ret_msg),e.listLoading=!1})).catch((function(t){e.listLoading=!1}))}},watch:{}},o=c,u=(a("a993"),a("2877")),g=Object(u["a"])(o,i,s,!1,null,"69b87a93",null);e["default"]=g.exports},a993:function(t,e,a){"use strict";a("154a")}}]);
//# sourceMappingURL=chunk-348d5522.08f8089b.js.map