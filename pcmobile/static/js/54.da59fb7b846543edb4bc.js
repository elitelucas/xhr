webpackJsonp([54],{KkWR:function(t,a){},rdUu:function(t,a,i){"use strict";Object.defineProperty(a,"__esModule",{value:!0});var s=i("4YfN"),n=i.n(s),e=i("9rMa"),o=(n()({},Object(e.c)(["accountData"])),n()({},Object(e.b)(["SET_ACCOUNT_DATA"]),{handleInput:function(t){this.money=t.target.value.replace(/[^\d]/g,"").replace(/^0{1,}/g,"")},nextStep:function(){var t=this;""==this.money?this.$vux.toast.show({text:"Withdrawal amount cannot be blank"}):Number(this.money)<Number(this.bankCardMsg.lower_limit)?this.$vux.toast.show({text:"The withdrawal amount cannot be less than the minimum withdrawal amount"}):Number(this.bankCardMsg.upper_limit)<Number(this.money)?this.$vux.toast.show({text:"The withdrawal amount cannot be greater than the maximum withdrawal amount"}):(this.psdShow=!1,this.psd="",this.$nextTick(function(){t.$refs.psd.focus()}))},_getNeedBet:function(){var t=this,a={};a.id=this.accountData.user_id,a.token=localStorage.getItem("token"),t.$http.post(t.urlRequest+"?m=api&c=app&a=getNeedBet",a).then(function(a){0==a.status&&(t.limit=a.limit,t.limit>0?t.limitFlag=!0:t.limitFlag=!1)}).catch(function(t){console.log(t)})},_getBankCard:function(){var t=this,a={};a.token=localStorage.getItem("token"),t.$vux.loading.show(),t.$http.post(t.urlRequest+"?m=api&c=cash&a=getBankCard",a).then(function(a){0==a.status&&(t.bankCardMsg=a),t.$vux.loading.hide()}).catch(function(a){t.$vux.loading.hide(),console.log(a)})},_submidtCash:function(){var t=this;0!=this.bankCardMsg.withdraw_limit.freeCont&&t.bankCardMsg.withdraw_limit.withdrwlFee>0&&t.bankCardMsg.withdraw_limit.freeCont<=t.bankCardMsg.withdraw_limit.withdrwlCont?t.$vux.confirm.show({content:"The number of withdrawals you made today has exceeded the limit set by the system for free withdrawal per day. You need additional service charge to continue to withdraw:"+t.bankCardMsg.withdraw_limit.withdrwlFee+"Hk / time",onCancel:function(){t.$router.push("/wallet")},onConfirm:function(){t._goWithdraw()}}):t._goWithdraw()},_goWithdraw:function(){var t=this,a={};a.token=localStorage.getItem("token"),a.bank_id=t.bankCardMsg.bank_id,a.money=t.money,a.psd=t.psd,t.$vux.loading.show(),t.$http.post(t.urlRequest+"?m=api&c=cash&a=cash",a).then(function(a){if(0==a.status){var i=t.accountData;i.cash_id=a.cash_id,t.SET_ACCOUNT_DATA({Obj:i}),t.$router.push("/whdProgress"),t.$router.push({path:"/whdProgress",query:{cash_id:a.cash_id}})}else t.psdShow=!0,a.ret_msg&&""!=a.ret_msg&&t.$vux.toast.show({text:a.ret_msg});t.$vux.loading.hide()}).catch(function(a){t.$vux.loading.hide(),console.log(a)})}}),{data:function(){return{bankCardMsg:{},money:"",psd:"",psdShow:!0,isBindBank:!1,isSafe:!1,limit:"",limitFlag:!0}},computed:n()({},Object(e.c)(["accountData"])),created:function(){0==this.accountData.is_banded_bank?this.isBindBank=!0:0==this.accountData.is_set_paypassword?this.isSafe=!0:this._getBankCard(),this._getNeedBet()},methods:n()({},Object(e.b)(["SET_ACCOUNT_DATA"]),{handleInput:function(t){this.money=t.target.value.replace(/[^\d]/g,"").replace(/^0{1,}/g,"")},nextStep:function(){var t=this;""==this.money?this.$vux.toast.show({text:"Withdrawal amount cannot be blank"}):Number(this.money)<Number(this.bankCardMsg.lower_limit)?this.$vux.toast.show({text:"The withdrawal amount cannot be less than the minimum withdrawal amount"}):Number(this.bankCardMsg.upper_limit)<Number(this.money)?this.$vux.toast.show({text:"The withdrawal amount cannot be greater than the maximum withdrawal amount"}):(this.psdShow=!1,this.psd="",this.$nextTick(function(){t.$refs.psd.focus()}))},_getNeedBet:function(){var t=this,a={};a.id=this.accountData.user_id,a.token=localStorage.getItem("token"),t.$http.post(t.urlRequest+"?m=api&c=app&a=getNeedBet",a).then(function(a){0==a.status&&(t.limit=a.limit,t.limit>0?t.limitFlag=!0:t.limitFlag=!1)}).catch(function(t){console.log(t)})},_getBankCard:function(){var t=this,a={};a.token=localStorage.getItem("token"),t.$vux.loading.show(),t.$http.post(t.urlRequest+"?m=api&c=cash&a=getBankCard",a).then(function(a){0==a.status&&(t.bankCardMsg=a),t.$vux.loading.hide()}).catch(function(a){t.$vux.loading.hide(),console.log(a)})},_submidtCash:function(){var t=this;0!=this.bankCardMsg.withdraw_limit.freeCont&&t.bankCardMsg.withdraw_limit.withdrwlFee>0&&t.bankCardMsg.withdraw_limit.freeCont<=t.bankCardMsg.withdraw_limit.withdrwlCont?t.$vux.confirm.show({content:"The number of withdrawals you made today has exceeded the limit set by the system for free withdrawal per day. You need additional service charge to continue to withdraw:"+t.bankCardMsg.withdraw_limit.withdrwlFee+" USD/times",onCancel:function(){t.$router.push("/wallet")},onConfirm:function(){t._goWithdraw()}}):t._goWithdraw()},_goWithdraw:function(){var t=this,a={};a.token=localStorage.getItem("token"),a.bank_id=t.bankCardMsg.bank_id,a.money=t.money,a.psd=t.psd,t.$vux.loading.show(),t.$http.post(t.urlRequest+"?m=api&c=cash&a=cash",a).then(function(a){if(0==a.status){var i=t.accountData;i.cash_id=a.cash_id,t.SET_ACCOUNT_DATA({Obj:i}),t.$router.push("/whdProgress"),t.$router.push({path:"/whdProgress",query:{cash_id:a.cash_id}})}else t.psdShow=!0,a.ret_msg&&""!=a.ret_msg&&t.$vux.toast.show({text:a.ret_msg});t.$vux.loading.hide()}).catch(function(a){t.$vux.loading.hide(),console.log(a)})}}),watch:{accountData:function(t){t&&(0==t.is_banded_bank?this.isBindBank=!0:0==t.is_set_paypassword?this.isSafe=!0:this._getBankCard(),this._getNeedBet())},psd:function(){for(var t=document.querySelectorAll(".inp span"),a=0;a<t.length;a++)t[a].style.backgroundSize="0";for(var i=0;i<this.psd.length;i++)t[i].style.backgroundSize="auto";6==this.psd.length&&this._submidtCash()}}}),r={render:function(){var t=this,a=t.$createElement,i=t._self._c||a;return i("div",[i("div",{staticClass:"headerWrap"},[i("x-header",{staticClass:"header",attrs:{title:"Withdrawal"}})],1),t._v(" "),t.psdShow?[i("div",{staticClass:"has-bank"},[i("div",{staticClass:"bank-wrap"},[i("div",{class:["bank-info","微信"==t.bankCardMsg.bank?"card-WeChat":"支付宝"==t.bankCardMsg.bank?"card-alipay":"card-bank"]},[i("span",{staticClass:"bank-name"},[t._v(t._s(t.bankCardMsg.bank))]),t._v(" "),t.bankCardMsg.account?i("h5",[t._v("\n                        "+t._s((t.bankCardMsg.account.substring(0,t.bankCardMsg.account.length-4).replace(/[0-9]/g,"*")+t.bankCardMsg.account.substring(t.bankCardMsg.account.length-4,t.bankCardMsg.account.length)).replace(/\s/g,"").replace(/(.{4})/g,"$1 "))+"\n                    ")]):t._e(),t._v(" "),i("p",[t._v("Limit: the lowest in a single transaction"+t._s(t.bankCardMsg.lower_limit)+"USD highest"+t._s(t.bankCardMsg.upper_limit)+" USD")])])]),t._v(" "),i("group",{staticClass:"group-no-top"},[i("cell",{staticClass:"prePaid-currency",attrs:{title:"Withdrawal amount","value-align":"left"}},[i("i",{staticClass:"cell-icon cell-icon-currency",attrs:{slot:"icon"},slot:"icon"}),t._v(" "),i("input",{directives:[{name:"model",rawName:"v-model",value:t.money,expression:"money"}],attrs:{placeholder:"Please input the withdrawal amount(USD)"},domProps:{value:t.money},on:{input:[function(a){a.target.composing||(t.money=a.target.value)},t.handleInput]}})])],1),t._v(" "),i("p",{directives:[{name:"show",rawName:"v-show",value:t.limit>0,expression:"limit > 0"}],staticClass:"limit-tips"},[t._v("You also need"),i("span",[t._v(t._s(t.limit))]),t._v("The cash can only be withdrawn after the code is printed")]),t._v(" "),i("div",{staticClass:"submit-btn"},[i("x-button",{staticClass:"weui-btn_radius weui-btn_minRadius",attrs:{disabled:t.limitFlag,type:"warn","action-type":"button"},nativeOn:{click:function(a){return t.nextStep(a)}}},[t._v("Submit now")])],1)],1)]:[i("div",{staticClass:"inp"},[i("p",[t._v("Please enter your fund password")]),t._v(" "),i("span"),i("span"),i("span"),i("span"),i("span"),i("span"),t._v(" "),i("input",{directives:[{name:"model",rawName:"v-model",value:t.psd,expression:"psd"}],ref:"psd",attrs:{type:"number",maxlength:"6"},domProps:{value:t.psd},on:{input:function(a){a.target.composing||(t.psd=a.target.value)}}})])],t._v(" "),i("x-dialog",{staticClass:"public-dialog isBindBank-dialog",model:{value:t.isBindBank,callback:function(a){t.isBindBank=a},expression:"isBindBank"}},[i("div",{staticClass:"dialog-content"},[i("h1",{staticClass:"dialog-tit"},[i("i",{staticClass:"dialog-icon dialog-icon-bankSet"}),i("br"),t._v("\n                Binding bank card\n            ")]),t._v(" "),i("div",{staticClass:"dialog-con"},[t._v("\n                In order to ensure the security of your account fund, you need to bind the bank card successfully before you can withdraw cash safely。\n            ")]),t._v(" "),i("x-button",{staticClass:"weui-btn_radius",attrs:{type:"warn"},nativeOn:{click:function(a){t.$router.push({path:"/bank/bankList"})}}},[t._v("Bind now")])],1),t._v(" "),i("span",{staticClass:"close-icon",on:{click:function(a){t.$router.push({path:"/wallet"})}}})]),t._v(" "),i("x-dialog",{staticClass:"public-dialog isBindBank-dialog",model:{value:t.isSafe,callback:function(a){t.isSafe=a},expression:"isSafe"}},[i("div",{staticClass:"dialog-content"},[i("h1",{staticClass:"dialog-tit"},[i("i",{staticClass:"dialog-icon dialog-icon-safe"}),i("br"),t._v("\n                Fund password not set\n            ")]),t._v(" "),i("div",{staticClass:"dialog-con"},[t._v("\n                To ensure safe and convenient capital transaction, please set your fund password first。\n            ")]),t._v(" "),i("x-button",{staticClass:"weui-btn_radius",attrs:{type:"warn"},nativeOn:{click:function(a){t.$router.push({path:"/paySet"})}}},[t._v("Set now")])],1),t._v(" "),i("span",{staticClass:"close-icon",on:{click:function(a){t.$router.push({path:"/wallet"})}}})])],2)},staticRenderFns:[]};var c=i("vSla")(o,r,!1,function(t){i("KkWR")},"data-v-385c3284",null);a.default=c.exports}});
//# sourceMappingURL=54.da59fb7b846543edb4bc.js.map