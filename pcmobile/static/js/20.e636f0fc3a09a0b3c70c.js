webpackJsonp([20],{"6W3u":function(t,e,i){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var s=i("4YfN"),a=i.n(s),n=i("9rMa"),l=i("j108"),r=i("inDh"),o=i("cy8Z"),c=i("zv2G"),u=i("9r/T"),_=(l.a,r.a,o.a,c.a,a()({},Object(n.c)(["platformConfig"])),{components:{Tab:l.a,TabItem:r.a,Marquee:o.a,MarqueeItem:c.a},data:function(){return{introShow:!0,ruleShow:!1,nowLi:null,congratulation:!1,gameTime:0,noTimes:!1,flag:!1,indexMsg:null,rules:null,myAward:null,page:1,count:1,idIndex:0,ruleIndex:[0,1,2,4,7,6,5,3],result:null,id:0,pullUpLoad:!1,browserkernel:"",templateNum:"",styleTemplate:null}},computed:a()({},Object(n.c)(["platformConfig"])),created:function(){document.title="幸运九宫格";var t=this;t._getActivityBack(),t.browserRedirect(),t.id=t.$route.query.id,t.id&&(t._getIndex(),t._getRules())},mounted:function(){var t=this;window.onresize=function(){Object(u.clearTimeout)(t.browserTime),t.browserTime=Object(u.setTimeout)(function(){t.browserRedirect()},400)}},methods:{browserRedirect:function(){var t=navigator.userAgent.toLowerCase(),e="ipad"==t.match(/ipad/i),i="iphone os"==t.match(/iphone os/i),s="midp"==t.match(/midp/i),a="rv:1.2.3.4"==t.match(/rv:1.2.3.4/i),n="ucweb"==t.match(/ucweb/i),l="android"==t.match(/android/i),r="windows ce"==t.match(/windows ce/i),o="windows mobile"==t.match(/windows mobile/i);this.browserkernel=e||i||s||a||n||l||r||o?"h5":"pc"},_getActivityBack:function(){var t=this,e={type:2};t.$http.post(t.urlRequest+"?m=api&c=app&a=getActivityBack",e).then(function(e){t.templateNum=e.data,console.log("风格模板：",t.templateNum)})},playAgain:function(){this.page=1,this._getRules(),this._getIndex(),this.congratulation=!0},ruleHide:function(){this.introShow=!0,this.ruleShow=!1},tabChange:function(t){var e=this;0==t?(this.introShow=!0,this.pullUpLoad=!1,Object(u.setTimeout)(function(){e.$refs.scroll.initScroll()},20)):(this.introShow=!1,this.pullUpLoad=!0,Object(u.setTimeout)(function(){e.$refs.scroll.initScroll()},20))},pullingDown:function(){this.page=1,this._getRules()},pullingUp:function(){this.pullUpLoadFlag?this.$refs.scroll.forceUpdate(!1):(this.page+=1,this._getRules(1))},turning:function(){var t=this;if(t.gameTime<=0)t.noTimes=!0;else if(!t.flag){t.flag=!0,t.result=null,t.count=1,t._getLucDraw(),t.nowLi=1,t.timer=setInterval(function(){t.count++,7==t.idIndex?t.idIndex=0:t.idIndex++,t.nowLi=t.indexMsg.config[t.ruleIndex[t.idIndex]].prize_id},80)}},_getRules:function(t){var e=this,i={};i.id=e.id,i.type=3,i.page=e.page,e.pullUpLoadFlag=!1,e.$http.post(e.urlRequest+"?m=web&c=activity&a=nineRole",i).then(function(t){0==t.status&&(e.rules=t.result,1==e.page?e.myAward=t.result.list:e.myAward=e.myAward.concat(t.result.list),t.result.list.length<20&&(e.pullUpLoadFlag=!0))}).catch(function(t){console.log(t)})},_getIndex:function(){var t=this,e={};e.id=t.id,e.type=3,t.$vux.loading.show(),t.$http.post(t.urlRequest+"?m=web&c=activity&a=nineIndex",e).then(function(e){0==e.status&&(t.indexMsg=e.result,t.gameTime=t.indexMsg.num),t.$vux.loading.hide()}).catch(function(e){t.$vux.loading.hide(),console.log(e)})},_getLucDraw:function(){var t=this,e={};e.id=t.id,e.type=3,t.$http.post(t.urlRequest+"?m=web&c=activity&a=nineLucDraw",e).then(function(e){if(0!=e.status)return clearInterval(t.timer1),clearInterval(t.timer),t.nowLi=null,t.flag=!1,void t.$vux.toast.text(e.ret_msg,"bottom");t.result=e.result,t.gameTime=t.result.num}).catch(function(e){t.$vux.toast.text("Lottery failed, please try again","bottom"),console.log(e)})}},watch:{browserkernel:function(t){2==this.templateNum?this.styleTemplate="h5"==t?"mobileStyle1":"pcStyle1":4==this.templateNum&&(this.styleTemplate="h5"==t?"mobileStyle2":"pcStyle2")},templateNum:function(t){2==t&&(this.styleTemplate="h5"==this.browserkernel?"mobileStyle1":"pcStyle1"),4==t&&(this.styleTemplate="h5"==this.browserkernel?"mobileStyle2":"pcStyle2")},platformConfig:function(t){t.platform_name&&(document.title="幸运九宫格")},count:function(){var t=this;if(t.result&&(t.count==16+Math.ceil(8*Math.random())||t.count>24)){clearInterval(t.timer);var e=0;t.timer1=setInterval(function(){e++,7==t.idIndex?t.idIndex=0:t.idIndex++,t.nowLi=t.indexMsg.config[t.ruleIndex[t.idIndex]].prize_id,e>3&&t.nowLi==t.result.prize_id&&(clearInterval(t.timer1),t.congratulation=!0,t.flag=!1)},300)}}},beforeDestroy:function(){clearInterval(this.timer1),clearInterval(this.timer)}}),m={render:function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{class:t.styleTemplate},["h5"==t.browserkernel?[s("div",{staticClass:"mainBoxH5"},[s("div",{staticClass:"top",on:{click:function(e){t.ruleShow=!0}}}),t._v(" "),s("div",{staticClass:"slogan"}),t._v(" "),t.rules?s("p",{staticClass:"actDate"},[t._v("活动日期："+t._s(t.rules.time.start_time)+" 至 "+t._s(t.rules.time.end_time))]):t._e(),t._v(" "),t.indexMsg?s("div",{staticClass:"content"},[s("p",{staticClass:"content_p"},[t._v("你的抽奖次数: "),s("span",[t._v(t._s(t.gameTime))])]),t._v(" "),s("ul",{staticClass:"content_ul"},[t._l(t.indexMsg.config,function(e,a){return a<4?s("li",{key:a,class:[t.nowLi==e.prize_id?"active":""]},[1!=e.prize_id&&e.prize_img?[s("img",{attrs:{src:t.imgRequest+"/"+e.prize_img}})]:[s("img",{attrs:{src:i("EsAx"),alt:""}})],t._v(" "),s("span",[t._v(t._s(e.prize_name))])],2):t._e()}),t._v(" "),s("li",{on:{click:t.turning}}),t._v(" "),t._l(t.indexMsg.config,function(e,a){return a>=4&&a<8?s("li",{key:a,class:[t.nowLi==e.prize_id?"active":""]},[1!=e.prize_id&&e.prize_img?[s("img",{attrs:{src:t.imgRequest+"/"+e.prize_img}})]:[s("img",{attrs:{src:i("EsAx"),alt:""}})],t._v(" "),s("span",[t._v(t._s(e.prize_name))])],2):t._e()})],2),t._v(" "),s("div",{staticClass:"marqueeBox"},[t.indexMsg.list?s("Marquee",t._l(t.indexMsg.list,function(e,i){return s("MarqueeItem",{key:i},[t._v("恭喜"),s("span",{staticClass:"num"},[t._v(t._s(e.username.slice(0,3)+"***"))]),t._v("，获得 "),s("i",{staticClass:"award"},[t._v(t._s(e.prize_name))])])})):t._e()],1)]):t._e(),t._v(" "),t.rules?s("p",{staticClass:"copy",domProps:{innerHTML:t._s(t.rules.statement)}}):t._e()])]:t._e(),t._v(" "),"pc"==t.browserkernel?[s("div",{staticClass:"mainBoxPC"},[s("div",{staticClass:"topLeft"}),t._v(" "),s("div",{staticClass:"topRight"}),t._v(" "),s("span",{staticClass:"pcRules",on:{click:function(e){t.ruleShow=!0}}}),t._v(" "),s("div",{staticClass:"slogan"}),t._v(" "),t.rules?s("p",{staticClass:"actDate"},[t._v("活动日期："+t._s(t.rules.time.start_time)+" 至 "+t._s(t.rules.time.end_time))]):t._e(),t._v(" "),s("div",{staticClass:"content"},[s("h4",[t._v("你的抽奖次数: "),s("span",[t._v(t._s(t.gameTime))])]),t._v(" "),t.indexMsg?s("ul",{staticClass:"content_ul clearfix"},[t._l(t.indexMsg.config,function(e,a){return a<4?s("li",{key:a,class:[t.nowLi==e.prize_id?"active":""]},[1!=e.prize_id&&e.prize_img?[s("img",{attrs:{src:t.imgRequest+"/"+e.prize_img}})]:[s("img",{attrs:{src:i("faxJ"),alt:""}})],t._v(" "),s("span",[t._v(t._s(e.prize_name))])],2):t._e()}),t._v(" "),s("li",{on:{click:t.turning}}),t._v(" "),t._l(t.indexMsg.config,function(e,a){return a>=4&&a<8?s("li",{key:a,class:[t.nowLi==e.prize_id?"active":""]},[1!=e.prize_id&&e.prize_img?[s("img",{attrs:{src:t.imgRequest+"/"+e.prize_img}})]:[s("img",{attrs:{src:i("faxJ"),alt:""}})],t._v(" "),s("span",[t._v(t._s(e.prize_name))])],2):t._e()})],2):t._e(),t._v(" "),t.indexMsg?s("div",{staticClass:"marquee"},[t.indexMsg&&t.indexMsg.list.length>3?[s("div",{class:[t.indexMsg.list.length<=14?"marqueeBox14":"marqueeBox20"]},[t._l(t.indexMsg.list,function(e){return s("p",[t._v("恭喜 "),s("span",{staticClass:"num"},[t._v(t._s(e.username.slice(0,3)+"***"))]),t._v("，获得 "),s("i",{staticClass:"award"},[t._v(t._s(e.prize_name))])])}),t._v(" "),t._l(t.indexMsg.list,function(e){return s("p",[t._v("恭喜 "),s("span",{staticClass:"num"},[t._v(t._s(e.username.slice(0,3)+"***"))]),t._v("，获得 "),s("i",{staticClass:"award"},[t._v(t._s(e.prize_name))])])})],2)]:[s("div",{staticClass:"ss"},t._l(t.indexMsg.list,function(e){return s("p",[t._v("恭喜 "),s("span",{staticClass:"num"},[t._v(t._s(e.username.slice(0,3)+"***"))]),t._v("，获得 "),s("i",{staticClass:"award"},[t._v(t._s(e.prize_name))])])}))]],2):t._e()]),t._v(" "),t.rules?s("p",{staticClass:"copy",domProps:{innerHTML:t._s(t.rules.statement)}}):t._e()])]:t._e(),t._v(" "),s("x-dialog",{staticClass:"public-dialog",nativeOn:{click:function(e){t.congratulation=!1}},model:{value:t.congratulation,callback:function(e){t.congratulation=e},expression:"congratulation"}},[t.result?s("div",{staticClass:"dialog-content"},[1!=t.result.prize_id?s("img",{staticClass:"title",attrs:{src:i("nsxJ"),alt:""}}):t._e(),t._v(" "),s("h1",{staticClass:"dialog-tit"},[t._v("\n                "+t._s(t.result.prize_name)+"\n            ")]),t._v(" "),s("div",{staticClass:"dialog-con"},[1!=t.result.prize_id?[t.result.prize_img?s("img",{attrs:{src:t.imgRequest+"/"+t.result.prize_img,alt:""}}):s("img",{attrs:{src:i("EsAx"),alt:""}})]:[s("img",{attrs:{src:i("CTaQ"),alt:""}})]],2),t._v(" "),s("button",{staticClass:"btn",on:{click:t.playAgain}},[t._v("再玩一次")])]):t._e()]),t._v(" "),s("x-dialog",{staticClass:"public-dialog noTimes",nativeOn:{click:function(e){t.noTimes=!1}},model:{value:t.noTimes,callback:function(e){t.noTimes=e},expression:"noTimes"}},[s("div",{staticClass:"dialog-content"},[s("h1",{staticClass:"dialog-tit times"},[t._v("\n                抱歉，您没有抽奖次数！\n            ")]),t._v(" "),s("div",{staticClass:"dialog-con"},[s("img",{attrs:{src:i("CTaQ"),alt:""}})]),t._v(" "),s("p",[t._v("充值和投注可以增加抽奖次数")])]),t._v(" "),s("span",{staticClass:"close-icon",on:{click:function(e){t.noTimes=!1}}})]),t._v(" "),t.ruleShow?s("div",{staticClass:"introduce"},[s("p",[s("img",{attrs:{src:i("KD/M"),alt:""},on:{click:t.ruleHide}})]),t._v(" "),s("tab",{attrs:{"line-width":3,"custom-bar-width":"100px","bar-active-color":"#FEC806","active-color":"#FEC806","default-color":"#fff"}},[s("tab-item",{attrs:{selected:""},on:{"on-item-click":t.tabChange}},[t._v("活动说明")]),t._v(" "),s("tab-item",{on:{"on-item-click":t.tabChange}},[t._v("我的奖品")])],1),t._v(" "),s("div",{staticClass:"temp"},[s("scroll",{ref:"scroll",attrs:{pullDownRefresh:!0,pullUpLoad:t.pullUpLoad,data:t.myAward},on:{pullingDown:t.pullingDown,pullingUp:t.pullingUp}},[t.introShow&&t.rules?s("flexbox",{staticClass:"item-container",attrs:{orient:"vertical"}},[s("flexbox-item",{attrs:{span:5}},[s("a",[t._v("活动时间")]),t._v(" "),s("p",[t._v(t._s(t.rules.time.start_time+" — "+t.rules.time.end_time))])]),t._v(" "),s("flexbox-item",{attrs:{span:5}},[s("a",[t._v("活动介绍")]),t._v(" "),s("p",{domProps:{innerHTML:t._s(t.rules.details)}})]),t._v(" "),s("flexbox-item",{staticClass:"rulesBox",attrs:{span:5}},[s("a",[t._v("活动规则")]),t._v(" "),s("p",[t._v("1.充值达"+t._s(t.rules.role.topup_money)+"元宝，获得"+t._s(t.rules.role.topup_num)+"次抽奖机会；")]),t._v(" "),s("p",[t._v("2.投注达"+t._s(t.rules.role.bet_money)+"元宝，获得"+t._s(t.rules.role.bet_num)+"次抽奖机会；")]),t._v(" "),s("p",[t._v("3.赢分达"+t._s(t.rules.role.win_money)+"元宝，获得"+t._s(t.rules.role.win_num)+"次抽奖机会；")]),t._v(" "),s("p",[t._v("4.输分达"+t._s(t.rules.role.lose_money)+"元宝，获得"+t._s(t.rules.role.lose_num)+"次抽奖机会；")]),t._v(" "),s("p",[t._v("5.充值投注赠送次数即时生效，输赢赠送次数每日12点结算，以上规则均在此次活动时间内生效。")])])],1):s("table",{staticClass:"item-container",attrs:{cellspacing:"0",cellspadding:"0","border-collapse":"collapse"}},[s("thead",[s("tr",[s("th",[t._v("时间")]),t._v(" "),s("th",[t._v("奖项")]),t._v(" "),s("th",[t._v("奖品名称")])])]),t._v(" "),s("tbody",[t.myAward&&t.myAward.length>0?t._l(t.myAward,function(e,i){return s("tr",{key:i},[s("td",[t._v(t._s(e.add_time))]),t._v(" "),s("td",[t._v(t._s(e.prize_project))]),t._v(" "),s("td",[t._v(t._s(e.prize_name))])])}):[s("tr",[s("td",{attrs:{colspan:"3"}},[t._v("没有奖品记录")])])]],2)])],1)],1)],1):t._e()],2)},staticRenderFns:[]};var d=i("vSla")(_,m,!1,function(t){i("jKSI")},"data-v-3ae9c480",null);e.default=d.exports},faxJ:function(t,e,i){t.exports=i.p+"static/img/icon2.f410ef4.png"},jKSI:function(t,e){}});
//# sourceMappingURL=20.e636f0fc3a09a0b3c70c.js.map