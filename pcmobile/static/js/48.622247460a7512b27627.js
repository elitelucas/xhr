webpackJsonp([48],{hFL7:function(t,e){},hYmc:function(t,e,i){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var s=i("4YfN"),a=i.n(s),n=i("Hpey"),r=i("9rMa"),l=i("xQdF"),c=i("J8qA"),o=(c.a,a()({},Object(r.c)(["userInfo"])),{components:{dataTimeFilter:c.a},data:function(){return{datePicker:!1,pikerShow:!1,beginTime:Object(n.a)(new Date,"YYYY-MM-DD"),endTime:Object(n.a)(new Date,"YYYY-MM-DD"),pullUpLoad:!0,memberList:[],sortTypeList:[{id:1,name:"Last offline"},{id:2,name:"Latest registration"},{id:3,name:"Most profit and loss"}],searchValue:"",nowId:1,sortType:1,sortValue:"Last offline",page:1}},computed:a()({},Object(r.c)(["userInfo"])),created:function(){this.resetAll()},methods:{activeCopy:function(t){Object(l.a)(t.target,"activeScroll"),document.addEventListener("touchmove",function(){Object(l.c)(t.target,"activeScroll")}),document.addEventListener("touchend",function(){Object(l.c)(t.target,"activeScroll")})},goToDetail:function(t){this.$router.push({path:"/memberDetail",query:{id:t,start_time:this.beginTime,end_time:this.endTime}})},pullingDown:function(){this.page=1,this._getMyMember(2)},pullingUp:function(){this.pullUpLoadFlag?this.$refs.scroll.forceUpdate(!1):(this.page+=1,this._getMyMember())},resetAll:function(){var t=this;t.sortType=1,t.sortValue="Last offline",t.beginTime="",t.endTime="",t.nowId=1,t._getMyMember(1)},pickHide:function(t){this.datePicker=t},getTime:function(t){this.beginTime=t[0],this.endTime=t[1],this.datePicker=!1,this._getMyMember(1)},picking:function(t){var e=this;this.nowId=t,this.sortType=t,this.sortValue=this.sortTypeList.find(function(e){return e.id==t}).name,setTimeout(function(){e.pikerShow=!1},200),this._getMyMember(1)},_getMyMember:function(t){var e=this;if(e.pullUpLoadFlag=!1,""!=e.searchValue||t){2!=t&&e.$vux.loading.show();var i={};i.token=localStorage.getItem("token"),i.start_time=e.beginTime,i.end_time=e.endTime,i.sort_type=e.sortType,i.user_value=e.searchValue,i.page=e.page,e.$http.post(e.urlRequest+"?m=api&c=user&a=myMember",i).then(function(t){0==t.status?(e.searchValue="",1==e.page?e.memberList=t.list:e.memberList=e.memberList.concat(t.list),console.log("data",e.memberList),t.list.length<20&&(e.pullUpLoadFlag=!0)):t.ret_msg&&""!=t.ret_msg&&e.$vux.toast.show({text:t.ret_msg}),e.$vux.loading.hide()}).catch(function(t){e.$vux.loading.hide(),e.$refs.scroll.forceUpdate(!1),console.log(t)})}else e.$vux.toast.text("Please enter the account number","bottom")}},filters:{timeFilter:function(t){var e=new Date(1e3*t);return e.getFullYear()+"-"+(e.getMonth()+1)+"-"+e.getDate()+" "+e.getHours()+":"+e.getMinutes()}},watch:{$route:function(t,e){"MemberDetail"!=e.name&&"Member"==t.name&&(this.memberList=[],this.resetAll())}}}),m={render:function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",[s("div",{staticClass:"headerWrap"},[s("x-header",{staticClass:"header"},[t._v("Member report\n            "),s("a",{attrs:{slot:"right"},on:{click:t.resetAll},slot:"right"},[t._v("Reset")])])],1),t._v(" "),s("flexbox",{staticClass:"headFilter",attrs:{gutter:0}},[s("flexbox-item",{staticClass:"item-type",attrs:{span:3.2},nativeOn:{click:function(e){t.datePicker=!0}}},[s("span",{staticClass:"item-title"},[t._v("Betting time")]),t._v(" "),s("i",{staticClass:"item-icon"},[s("img",{attrs:{src:i("4/iK"),alt:""}})])]),t._v(" "),s("flexbox-item",{staticClass:"item-type",class:[t.pikerShow?"active":""],attrs:{span:3.2},nativeOn:{click:function(e){t.pikerShow=!0}}},[s("span",{staticClass:"item-title"},[t._v(t._s(t.sortValue))]),t._v(" "),s("i",{staticClass:"item-icon triangle",staticStyle:{"pointer-events":"none"}},[s("img",{staticClass:"down",attrs:{src:i("sUte"),alt:""}}),t._v(" "),s("img",{staticClass:"up",attrs:{src:i("mrzh"),alt:""}})])]),t._v(" "),s("flexbox-item",{staticClass:"item-search",attrs:{span:5.6}},[s("x-input",{attrs:{"show-clear":!1,placeholder:"Search Account"},model:{value:t.searchValue,callback:function(e){t.searchValue=e},expression:"searchValue"}},[s("icon",{attrs:{slot:"right",type:"search"},nativeOn:{click:function(e){t._getMyMember(0)}},slot:"right"})],1)],1)],1),t._v(" "),s("div",{staticClass:"trading-hour"},[s("span",[t._v("Betting time：")]),t._v(" "),""==t.beginTime&&""==t.endTime?[s("span",[t._v("all")])]:[s("span",[t._v(t._s(t.beginTime))]),t._v(" "),s("span",[t._v("to")]),t._v(" "),s("span",[t._v(t._s(t.endTime))])]],2),t._v(" "),s("div",{staticClass:"item-list-wrapper"},[t.memberList&&t.memberList.length?[s("scroll",{ref:"scroll",attrs:{pullDownRefresh:!0,pullUpLoad:t.pullUpLoad,data:t.memberList},on:{pullingDown:t.pullingDown,pullingUp:t.pullingUp}},[s("div",t._l(t.memberList,function(e,i){return s("flexbox",{key:i,staticClass:"item-list activeBox",nativeOn:{click:function(i){t.$router.push("/memberDetail?id="+e.id+"&start_time="+t.beginTime+"&end_time="+t.endTime)},touchstart:function(e){t.activeCopy(e)}}},[s("flexbox-item",{staticClass:"pointEvents",attrs:{span:6.5}},[s("p",[s("span",[t._v("account number：")]),t._v(" "),s("span",{staticClass:"text-black"},[t._v(t._s(e.username))])]),t._v(" "),s("p",[s("span",[t._v(t._s(t._f("timeFilter")(e.regtime)))])])]),t._v(" "),s("flexbox-item",{staticClass:"pointEvents",attrs:{span:5.5}},[s("p",[s("span",[t._v("Profit and loss of members：")]),t._v(" "),s("span",{staticClass:"text-red"},[t._v(t._s(e.total_profit_amt))])]),t._v(" "),s("p",[s("span",[t._v("Account balance：")]),t._v(" "),s("span",{staticClass:"text-black"},[t._v(t._s(e.money))])])])],1)}))])]:[s("img",{staticClass:"noDataImg",attrs:{src:i("w+73"),alt:""}})]],2),t._v(" "),s("transition",{attrs:{name:"picker"}},[t.pikerShow?s("div",{staticClass:"picker",on:{click:function(e){if(e.target!==e.currentTarget)return null;t.pikerShow=!1}}},[t.pikerShow?s("div",{staticClass:"innerBox"},[s("h4",{staticClass:"vux-1px-b"},[t._v("Select search type")]),t._v(" "),s("div",{staticClass:"picker-list clearfix"},t._l(t.sortTypeList,function(e,i){return s("div",{key:i},[s("button",{class:{cur:e.id==t.nowId},on:{click:function(i){t.picking(e.id)}}},[t._v(t._s(e.name))])])})),t._v(" "),s("a",{attrs:{href:"javascript:void(0)"},on:{click:function(e){t.pikerShow=!1}}},[t._v("cancel")])]):t._e()]):t._e()]),t._v(" "),s("dataTimeFilter",{attrs:{endTime:t.endTime,beginTime:t.beginTime,datePicker:t.datePicker},on:{getTime:t.getTime,pickHide:t.pickHide}})],1)},staticRenderFns:[]};var u=i("vSla")(o,m,!1,function(t){i("hFL7")},"data-v-5d1a4360",null);e.default=u.exports}});
//# sourceMappingURL=48.622247460a7512b27627.js.map