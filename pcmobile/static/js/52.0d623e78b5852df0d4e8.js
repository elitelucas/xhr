webpackJsonp([52],{"+JtA":function(t,e,a){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var i=a("4YfN"),o=a.n(i),n=(a("//TE"),a("9rMa")),s=(a("Y0Uy"),o()({},Object(n.c)(["accountData"])),{data:function(){return{gameList:[],showPwdDlg:!1,lottery_type:0,tip:"",showTip:!1,roomTitle:this.$route.query.type_name,notLoginFlag:!1}},mounted:function(){var t=this;localStorage.getItem("token"),this.$route.query.id;this.$http.post(this.urlRequest+"?m=api&c=game&a=getGameListForType",{id:this.$route.query.id}).then(function(e){0==e.status&&(t.gameList=e.list,console.log(t.gameList)),t.$vux.loading.hide()})},created:function(){this.$vux.loading.show()},computed:o()({},Object(n.c)(["accountData"])),methods:{headBack:function(){this.$router.push({path:"/home"})},goLogin:function(){this.$router.push({path:"/login"})},goGame:function(t){var e=this;localStorage.getItem("token")?this.$http.post(this.urlRequest+"?m=api&c=game&a=getGameUrl",{id:t}).then(function(t){e.$vux.loading.hide(),0==t.status?window.location.href=t.data.gameUrl:e.$vux.toast.show({text:t.ret_msg})}):this.notLoginFlag=!0}}}),r={render:function(){var t=this,e=t.$createElement,i=t._self._c||e;return i("div",{staticClass:"isHeader"},[i("div",{staticClass:"headerWrap"},[i("x-header",{staticClass:"header",attrs:{title:t.roomTitle,"left-options":{preventGoBack:!0}},on:{"on-click-back":t.headBack}})],1),t._v(" "),t._l(t.gameList,function(e,o){return i("div",{key:o,staticClass:"roomPanel",class:{"no-img":!e.game_img},on:{click:function(a){t.goGame(e.id)}}},[e.game_img?i("img",{staticClass:"data-img",attrs:{src:t.imgRequest+e.game_img}}):i("img",{staticClass:"default-img",attrs:{src:a("cCrS")}})])}),t._v(" "),i("br"),t._v(" "),i("div",{directives:[{name:"transfer-dom",rawName:"v-transfer-dom"}]},[i("confirm",{attrs:{title:"温馨提示","confirm-text":"去登录"},on:{"on-confirm":t.goLogin},model:{value:t.notLoginFlag,callback:function(e){t.notLoginFlag=e},expression:"notLoginFlag"}},[t._v("\n\t\t\t\t您还没有登录\n\t\t\t")])],1)],2)},staticRenderFns:[]};var c=a("vSla")(s,r,!1,function(t){a("5T//")},"data-v-3fae9457",null);e.default=c.exports},"5T//":function(t,e){}});
//# sourceMappingURL=52.0d623e78b5852df0d4e8.js.map