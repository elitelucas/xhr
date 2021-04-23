webpackJsonp([71], {
    Wxnb: function(t, e) {},
    aec1: function(t, e, a) {
        "use strict";
        Object.defineProperty(e, "__esModule", {
            value: !0
        });
        var r = a("S9nr")
          , s = a("Hpey")
          , l = a("s4gL")
          , _ = a("uYm6")
          , o = a("cTn1")
          , n = (r.a,
        l.a,
        _.a,
        o.a,
        {
            components: {
                Datetime: r.a,
                Checker: l.a,
                CheckerItem: _.a,
                Popup: o.a
            },
            data: function() {
                return {
                    english: [{
                        "待开奖": "Not yet",
                        "未中奖": "Lose",
                        "已中奖": "Win",
                        "幸运": "Lucky",
                        "和局": "Award",
                        "开奖走势": "Lottery trend",
                        "日期筛选": "Date filter",
                        "加拿大": "Canada",
                        "急速赛车": "Fast 3 cars",
                        "分分PK10": "Fast 1 car",
                        "三分彩": "Sharp 3 colors",
                        "百人牛牛": "Bullfight",
                        "分分彩": "Sharp 1 color",
                        "欢乐骰宝": "Dice",
                        "飞艇": "airship",
                        "期数": "Period",
                        "开奖号码": "Winning number",
                        "总和": "Sum",
                        "和值": "Value",
                        "大": "Big",
                        "小": "Small",
                        "单": "Odd",
                        "双": "Even",
                        "组合": "combination",
                        "极值": "Limit",
                        "极大": "Biggest",
                        "极小": "Smallest",
                        "号码分布": "Distrubution",
                        "大小": "Size",
                        "单双": "Odd/Even",
                        "充提记录": "Transaction record",
                        "额度转换": "Quota conversion",
                        "目前额度": "Current quota",
                        "现金余额": "Cash balance",
                        "转出": "Roll out",
                        "转入": "Roll in",
                        "数额": "Amount",
                        "提交订单": "Submit orders",
                        "一键转入": "One-click transfer",
                        "总余额": "Total balance",
                        "刷新": "Refresh",
                        "现金余额不足": "Cash balance is not enough",
                        "请输入正确的转账金额": "Please enter the correct transfer amount",
                        "线上支付": "Online payment",
                        "线下支付": "Offline payment",
                        "充值方式": "Deposit method",
                        "返现比例": "Cash back ratio",
                        "充值银行": "Deposit bank",
                        "充值金额": "Deposit amount",
                        "充值限额": "Deposit limit",
                        "下限": "Lower limit",
                        "其他": "Other",
                        "下一步": "Next step",
                        "提交中": "Submitting",
                        "该方式额度已达充值限额": "This method has reached the top-up limit",
                        "建议通过银行卡转账入款": "We recommend depositing by bank card transfer",
                        "发卡银行": "Bank",
                        "扫一扫二维码支付": "Scan the QR code to pay",
                        "我已完成支付": "Completed the payment",
                        "收款人": "Receiver",
                        "收款账号": "Receiver username",
                        "附加码": "Additional code",
                        "扫码支付": "scan code to pay",
                        "支付": "pay",
                        "点击确认提交": "Click confirm to submit",
                        "支付信息提交成功": "Payment information submitted successfully",
                        "线上": "Online",
                        "线下": "Offline",
                        "转账": "transfer",
                        "充值异常": "Deposit error",
                        "重新充值": "Deposit again",
                        "支付成功": "Pay successfully",
                        "未支付": "Not pay",
                        "请根据支付情况点击下方按钮请不要重复支付": "Please click the button below according to the payment situation, please do not repeat payment",
                        "获取银行列表失败": "Failed to get bank list",
                        "切换后将取消当前未完成订单": "After switching, the current outstanding order will be cancelled",
                        "是否继续": "would you continue",
                        "请输入充值金额": "Please input deposit amount",
                        "输入的资金格式有误": "The format of the entered funds is incorrect",
                        "请选择银行": "Please select the bank",
                        "充值金额不能小于": "The deposit amount cannot be less than",
                        "正向你跳转第三方支付": "Redirecting third-party payment to you",
                        "去支付": "Pay",
                        "该浏览器不支持自动复制": "This browser does not support automatic copy",
                        "已完成支付": "Payment completed",
                        "确认关闭": "would you close",
                        "填写提现金额": "Input the withdraw amount",
                        "等待审核": "Waiting for review",
                        "交易完成": "Transaction complete",
                        "您还没有设置支付密码": "You have not set a payment password",
                        "立即设置": "Set up now",
                        "银行卡信息": "Bank card information",
                        "限额": "Limit",
                        "提现金额": "Withdrawal amount",
                        "请输入提现金额": "Please input the withdraw amount",
                        "您还需要": "You still need",
                        "打码量才可提现": "code to withdraw",
                        "提现密码": "Withdrawal password",
                        "请联系客服": "please contact service center",
                        "您今天提现次数已经超出系统设置的每天免费提现次数限制": "Your number of withdrawals today has exceeded the daily free withdrawal limit set by the system",
                        "继续提现需要额外手续费": "and additional handling fees will be required for continued withdrawals",
                        "账号充值": "Deposit",
                        "立即提现": "Withdraw",
                        "在线客服": "Online service",
                        "请输入房间密码": "Please input the room password",
                        "该彩种已停售": "This color has been discontinued",
                        "视频直播": "Live video",
                        "彩票介绍": "Lottery introduction",
                        "投注截止": "Bet deadline",
                        "已封盘": "Closed",
                        "开奖中": "Awarding",
                        "已停售": "Closed",
                        "蓝": "Blue",
                        "红": "Red",
                        "对子": "Pair",
                        "豹子": "Triple",
                        "单骰": "Single dice",
                        "双骰": "Double dice",
                        "收起视频": "Collapse video",
                        "关闭": "Close",
                        "已有结果": "There are results",
                        "请刮图层": "please scratch the coating",
                        "此处为最佳刮奖区": "Here is the best scratch area",
                        "选择玩法": "Choose",
                        "追号": "Chase number",
                        "赔率说明": "Odds description",
                        "猜数字": "Guess the number",
                        "已选": "seleted",
                        "最小投注": "Min bet",
                        "请输入下注金额": "Please enter the bet amount",
                        "梭哈": "All in",
                        "确认下注": "Confirm bet",
                        "追": "chasing",
                        "翻": "tip",
                        "倍": "times",
                        "生成追号": "Generate chase number",
                        "确认投注": "Confirm bet",
                        "追号清单": "Chase number list",
                        "中奖即停": "Stop winning",
                        "猜双面": "Guess the size",
                        "猜车号": "Guess the car",
                        "猜龙虎": "Guess the winner",
                        "猜庄闲": "Guess the dealer",
                        "猜冠亚": "Guess first",
                        "冠亚和": "First and second",
                        "翻倍": "Multiple",
                        "冠军": "Champian",
                        "亚军": "Second",
                        "第三名": "Third",
                        "第四名": "Forth",
                        "第五名": "Fifth",
                        "第六名": "Sixth",
                        "第七名": "Seventh",
                        "第八名": "Eighth",
                        "第九名": "Ninth",
                        "第十名": "Tenth",
                        "猜区段": "Guess block",
                        "庄闲": "Dealer",
                        "冠亚": "First",
                        "和": "Both",
                        "区段": "Block",
                        "猜号码": "Guess the number",
                        "请选择玩法": "Please choose how to play",
                        "最多追加100期": "100 additional issues at most",
                        "长龙投注": "Dragon bet",
                        "删除": "Delete",
                        "暂无数据": "No Data",
                        "猜总和": "Guess the sum",
                        "第一球": "First ball",
                        "第二球": "Second ball",
                        "第三球": "Third ball",
                        "第四球": "Forth ball",
                        "第五球": "Fifth ball",
                        "本期追号投注": "This Chase number bet",
                        "第一名": "First",
                        "第二名": "Second",
                        "三军": "Three armies",
                        "双面": "Two sides",
                        "长牌": "Long card",
                        "短牌": "Short card",
                        "点数": "Points",
                        "特码-特A": "Champian",
                        "特码": "Champian",
                        "正码": "Top",
                        "正特": "Special",
                        "连码": "High",
                        "半波": "Semi-high",
                        "尾数": "Regular",
                        "一肖": "Semi-regular",
                        "特肖": "Low",
                        "连肖": "Semi-low",
                        "连尾": "Bottom",
                        "不中": "Not in",
                        "正码6": "Top1-6",
                        "正6龙虎": "Top1-6 winner",
                        "特A": "ChampianA",
                        "特B": "ChampianB",
                        "正A": "TopA",
                        "正B": "TopB",
                        "正1特": "Special1",
                        "正2特": "Special2",
                        "正3特": "Special3",
                        "正4特": "Special4",
                        "正5特": "Special5",
                        "正6特": "Special6",
                        "三中二": "Two in three",
                        "三全中": "In three",
                        "二全中": "In two",
                        "二中特": "Special in two",
                        "特串": "Specialstring",
                        "二肖连中": "In half two",
                        "三肖连中": "In half three",
                        "四肖连中": "In half four",
                        "二肖连不中": "Not in half two",
                        "三肖连不中": "Not in half three",
                        "四肖连不中": "Not in half four",
                        "二尾连中": "In end two",
                        "三尾连中": "In end three",
                        "四尾连中": "In end four",
                        "二尾连不中": "Not in end two",
                        "三尾连不中": "Not in end three",
                        "四尾连不中": "Not in end four",
                        "五不中": "Not in five",
                        "六不中": "Not in six",
                        "七不中": "Not in seven",
                        "八不中": "Not in eight",
                        "九不中": "Not in nine",
                        "十不中": "Not in ten",
                        "追号只能选择一注": "Only one bet can be seleted for chase",
                        "最多选择10个号码": "Choose 10 numbers at most",
                        "最多选择8个号码": "Choose 8 numbers at most",
                        "最多选择9个号码": "Choose 9 numbers at most",
                        "最多选择11个号码": "Choose 11 numbers at most",
                        "最多选择12个号码": "Choose 12 numbers at most",
                        "最多选择13个号码": "Choose 13 numbers at most",
                        "猜胜负": "Guess the winner",
                        "猜牛牛": "Guess the bull",
                        "猜牌面": "Guess the face",
                        "猜花色": "Guess the color",
                        "猜公牌": "Guess the card",
                        "第一张": "First card",
                        "第二张": "Second card",
                        "第三张": "Third card",
                        "第四张": "Forth card",
                        "第五张": "Fifth card",
                        "猜对子": "Guess right",
                        "猜围骰": "Guess triple",
                        "猜单骰": "Guess single dice",
                        "猜双骰": "Guess two dices",
                        "第一骰": "First dice",
                        "第二骰": "Second dice",
                        "第三骰": "Third dice",
                        "大小单双": "Odd and even",
                        "特殊玩法": "Special play",
                        "快速转账": "Quick transfer",
                        "开奖历史": "Award History",
                        "基本走势": "Basic Trend",
                        "号码统计": "Statistics",
                        "日期": "Date",
                        "查询": "Search",
                        "查看单号分布": "View traking number distribution",
                        "还原": "Reduction",
                        "大小单双分布": "Distribution",
                        "序号": "Serial number",
                        "佰": "Hundred",
                        "拾": "Collection",
                        "个": "Piece",
                        "出现总次数": "Total number of occurrences",
                        "平均遗漏值": "Average missing value",
                        "最大遗漏值": "Max missing value",
                        "最大连出值": "Max continuous value",
                        "龙虎": "Red/Blue",
                        "后二": "Last two",
                        "后三": "Last three",
                        "龙": "Blue",
                        "虎": "Red",
                        "有": "Yes",
                        "无": "No",
                        "奇偶和": "Parity sum",
                        "上中下": "Top/Middle/Bottom",
                        "偶": "Even",
                        "奇": "Odd",
                        "上": "Top",
                        "中": "Middle",
                        "下": "Bottom",
                        "彩球": "Colored ball",
                        "总开": "Always open",
                        "最大连开": "Max continuous opening",
                        "最大遗漏": "Biggest omission",
                        "平均遗漏": "Average omission",
                        "当前遗漏": "Currently missing",
                        "总和尾": "Sum of tail",
                        "尾大": "Oda",
                        "尾小": "Oko",
                        "开牌结果": "Open result",
                        "胜负": "Win/Lose",
                        "牛牛": "Bull",
                        "公牌": "Public license",
                        "总和大": "Sum of big",
                        "总和小": "Sum of small",
                        "牌": "Card",
                        "万位": "Ten thousand",
                        "千位": "Thousand",
                        "百位": "Hundred",
                        "十位": "Ten",
                        "个位": "One",
                        "开奖点数": "Points",
                        "彩球号": "Ball number",
                        "总号": "Total number",
                        "特码合数": "Special code number",
                        "特码生肖": "Special code zodiac",
                        "色波": "Color wave",
                        "总数": "Total",
                        "合单双": "Odd and Even",
                        "合大小": "Big and Small",
                        "黄铜": "Brass",
                        "白银": "Silver",
                        "黄金": "Gold",
                        "铂金": "Steel",
                        "钻石": "Diamond",
                        "大师": "VVVIP",
                        "王者": "VVIP",
                        "至尊": "VIP",
                        "初级房": "Junior",
                        "中级房": "Regular",
                        "高级房": "Expert",
                        "Vip房": "VIP",
                        "5分钟一期": "Every 5 mins",
                        "20分钟一期": "Every 20 mins",
                        "3分半一期": "Every 3.5 mins",
                        "3分钟一期": "Every 3 mins",
                        "每周开奖3期": "3 times per week",
                        "1分钟一期": "Every minute",
                        "最快最准最全": "Quick",
                        "质": "Quality",
                        "合": "Sum",
                        "红方胜": "Red Win",
                        "蓝方胜": "Blue Win",
                        "大单": "Big Odd",
                        "大双": "Big Even",
                        "小单": "Small Odd",
                        "小双": "Small Even",
                        "庄": "Banker",
                        "闲": "Dealer",
                        "无牛": "No bull",
                        "牛一": "First",
                        "牛二": "Second",
                        "牛三": "Third",
                        "牛四": "Forth",
                        "牛五": "Fifth",
                        "牛六": "Sixth",
                        "牛七": "Seventh",
                        "牛八": "Eighth",
                        "牛九": "Ninth",
                        "花色牛": "Color bull",
                        "黑桃": "Spade",
                        "梅花": "Club",
                        "红心": "Heart",
                        "方块": "Diamond",
                        "A": "A",
                        "J": "J",
                        "Q": "Q",
                        "K": "K",
                        "0": "0",
                        "1": "1",
                        "2": "2",
                        "3": "3",
                        "4": "4",
                        "5": "5",
                        "6": "6",
                        "7": "7",
                        "8": "8",
                        "9": "9",
                        "10": "10",
                        "11": "11",
                        "12": "12",
                        "13": "13",
                        "14": "14",
                        "15": "15",
                        "16": "16",
                        "17": "17",
                        "18": "18",
                        "19": "19",
                        "有公牌": "Public Cards",
                        "无公牌": "No public Cards",
                        "1-2": "1-2",
                        "1-3": "1-3",
                        "1-4": "1-4",
                        "1-5": "1-5",
                        "1-6": "1-6",
                        "2-3": "2-3",
                        "2-4": "2-4",
                        "2-5": "2-5",
                        "2-6": "2-6",
                        "3-4": "3-4",
                        "3-5": "3-5",
                        "3-6": "3-6",
                        "4-5": "4-5",
                        "4-6": "4-6",
                        "5-6": "5-6"
                    }],
                    nowTime: "",
                    nowTimeYMD: "YYYY-MM-DD",
                    nowYear: Object(s.a)(new Date, "YYYY"),
                    minYear: "2000",
                    maxYear: "2030",
                    trendData: [],
                    pullUpLoad: !0,
                    pullDownRefresh: !0,
                    page: 1,
                    lotteryList: [],
                    lotteryValue: [],
                    lotteryData: [],
                    checkerData: "",
                    lotteryFlag: !1,
                    lottery_type: null,
                    lotteryTitle: ["胜负", "牛牛", "公牌", "龙虎", "总和大", "总和小", "单", "双"],
                    lotteryTitle01: ["值", "大", "小", "单", "双", "大单", "大双", "小单", "小双"],
                    lotteryTitle02: ["冠亚和", "大", "小", "单", "双", "龙虎"],
                    lotteryTitle05: ["总和", "大", "小", "单", "双", "龙虎和"],
                    lotteryTitle07: ["总和", "特码", "色波", "大", "小", "单", "双", "合大", "合小", "合单", "合双", "和"],
                    lotteryTitle10: ["胜负", "牛牛", "公牌", "龙虎", "总和大", "总和小", "单", "双"],
                    lotteryTitle13: ["总和", "大", "小", "单", "双", "豹子"],
                    freeScroll: !0,
                    scrollx: 0,
                    scrolly: 0,
                    pullup: !0,
                    pullTxt: "Release and load more immediately",
                    roomInfo: {}
                }
            },
            created: function() {
                localStorage.getItem("lotteryId") ? this.lottery_type = localStorage.getItem("lotteryId") : this.lottery_type = this.$route.query.lottery_type,
                this.probeType = 3,
                this.listenScroll = !0
            },
            mounted: function() {
                var t = this;
                7 == this.lottery_type && (this.nowTime = "",
                setTimeout(function() {
                    t.nowTime = Object(s.a)(new Date, "YYYY"),
                    t.nowTimeYMD = "YYYY",
                    t.minYear = Number(t.nowYear) - 2,
                    t.maxYear = Number(t.nowYear)
                }, 20))
            },
            computed: {},
            methods: {
                goLotteryRoom: function() {
                    var t = this;
                    if (localStorage.getItem("token")) {
                        var e = {
                            token: localStorage.getItem("token"),
                            lottery_type: this.lottery_type
                        };
                        this.$vux.loading.show(),
                        this.$http.post(this.urlRequest + "?m=api&c=openAward&a=instantlyBetting", e).then(function(e) {
                            t.$vux.loading.hide(),
                            0 == e.status ? (t.roomInfo = e.data,
                            console.log(t.roomInfo),
                            t.$router.push({
                                path: "/room/" + t.roomInfo.room_id,
                                query: {
                                    lottery_type: t.roomInfo.lottery_type,
                                    id: t.roomInfo.room_id
                                }
                            })) : e.ret_msg && "" != e.ret_msg && t.$vux.toast.show({
                                text: e.ret_msg
                            })
                        }).catch(function(e) {
                            t.$vux.loading.hide(),
                            t.$vux.toast.show({
                                text: "Data request timeout"
                            })
                        })
                    } else {
                        var a = this;
                        this.$vux.alert.show({
                            content: "You are not logged in. Please log in first",
                            onHide: function() {
                                a.$router.push({
                                    path: "/login"
                                })
                            }
                        })
                    }
                },
                lotteryTypeToggle: function() {
                    this.lotteryFlag ? this.lotteryFlag = !1 : this.lotteryFlag = !0
                },
                onItemClick: function(t) {
                    this.value || (this.lotteryFlag = !1),
                    this.lottery_type = t.id,
                    localStorage.setItem("lotteryId", t.id)
                },
                headBack: function() {
                    localStorage.removeItem("lotteryId"),
                    this.goLotteryRoom()
                },
                initScroll: function() {
                    this.trendTableWidth = this.$refs.trendTable.offsetWidth + "px",
                    console.log(this.trendTableWidth)
                },
                scroll: function(t) {
                    this.scrollx = t.x,
                    this.scrolly = t.y
                },
                pullingDown: function() {
                    this.page = 1,
                    this._getDataList(0)
                },
                pullingUp: function() {
                    if (this.trendData) {
                        if (this.pullUpLoadFlag)
                            return this.$refs.scroll.forceUpdate(!1),
                            !1;
                        this.page += 1,
                        this._getDataList(0)
                    }
                },
                _getDataList: function(t) {
                    var e = this
                      , a = {
                        token: localStorage.getItem("token"),
                        date: this.nowTime,
                        lottery_type: this.lottery_type,
                        page: this.page
                    };
                    t && this.$vux.loading.show(),
                    this.pullUpLoadFlag = !1,
                    this.$http.post(this.urlRequest + "?m=api&c=openAward&a=trendList", a).then(function(a) {
                        if (t && e.$vux.loading.hide(),
                        0 == a.status) {
                            var lotteries = [];
                            for (var r in 1 == e.page ? e.trendData = a.list : e.trendData = e.trendData.concat(a.list),
                            e.lotteryData = a.gameInfo,
                            e.lotteryData){
                                if(e.lotteryData[r].id==6||e.lotteryData[r].id>=9){
                                    lotteries.push(e.lotteryData[r]);
                                }
                                if (e.lotteryData[r].id == e.lottery_type) {
                                    e.checkerData = e.lotteryData[r].name;
                                }
                            }
                            a.list.length < 50 && (e.pullUpLoadFlag = !0)
                            e.lotteryData = lotteries;
                        } else
                            a.ret_msg && "" != a.ret_msg && e.$vux.toast.show({
                                text: a.ret_msg
                            })
                    }).catch(function(t) {
                        e.$refs.scroll.forceUpdate(!1),
                        e.pullUpLoad = !1,
                        e.$vux.loading.hide(),
                        e.$vux.toast.show({
                            text: "Data request timeout"
                        })
                    })
                }
            },
            watch: {
                lotteryData: {
                    handler: function(t) {
                        var e = this;
                        t[0].length > 0 && this.$nextTick(function() {
                            e.initScroll()
                        })
                    },
                    deep: !0
                },
                scrolly: function(t) {
                    this.$refs.trendRight.style.transform = "translate3d(0," + t + "px,0)"
                },
                lottery_type: function(t) {
                    var e = this;
                    t && (this.trendData = [],
                    this.page = 1,
                    7 == t ? (this.nowTime = "",
                    setTimeout(function() {
                        e.nowTime = Object(s.a)(new Date, "YYYY"),
                        e.nowTimeYMD = "YYYY",
                        e.minYear = Number(e.nowYear) - 2,
                        e.maxYear = Number(e.nowYear),
                        e._getDataList(1),
                        e.$refs.trendRight.style.transform = "translate3d(0, 0, 0)"
                    }, 20)) : (this.nowTime = "",
                    setTimeout(function() {
                        e.nowTime = Object(s.a)(new Date, "YYYY-MM-DD"),
                        e.nowTimeYMD = "YYYY-MM-DD",
                        e.minYear = 2e3,
                        e.maxYear = 2030,
                        e._getDataList(1),
                        e.$refs.trendRight.style.transform = "translate3d(0, 0, 0)"
                    }, 20)))
                },
                nowTime: function(t) {
                    t && (this.trendData = [],
                    this.page = 1,
                    this._getDataList(1),
                    this.trendData.length && (this.$refs.scroll.scrollTo(0, 0),
                    this.$refs.trendRight.style.transform = "translate3d(0, 0, 0)"))
                }
            }
        })
          , i = {
            render: function() {
                var t = this
                  , e = t.$createElement
                  , r = t._self._c || e;
                return r("div", [r("div", {
                    staticClass: "headerWrap"
                }, [r("x-header", {
                    staticClass: "header",
                    attrs: {
                        "left-options": {
                            preventGoBack: !0
                        }
                    },
                    on: {
                        "on-click-back": t.headBack
                    }
                }, [r("span", {
                    staticClass: "head-triangle",
                    on: {
                        click: t.lotteryTypeToggle
                    }
                }, [t._v(t._s(t.checkerData ? t.english[0][t.checkerData] : "Prize opening trend")), r("i", {
                    class: {
                        "triangle-up": t.lotteryFlag
                    }
                })])])], 1), t._v(" "), r("group", {
                    staticClass: "top-group top-date-search"
                }, ["" != t.nowTime ? r("datetime", {
                    attrs: {
                        "min-year": t.minYear,
                        "max-year": t.maxYear,
                        title: "Date Filter",
                        format: t.nowTimeYMD,
                        "year-row": "{value}",
                        "month-row": "{value}",
                        "day-row": "{value}",
                        "confirm-text": "Ok",
                        "cancel-text": "cancel"
                    },
                    model: {
                        value: t.nowTime,
                        callback: function(e) {
                            t.nowTime = e
                        },
                        expression: "nowTime"
                    }
                }) : t._e()], 1), t._v(" "), r("div", {
                    staticClass: "trend"
                }, [r("div", {
                    staticClass: "trendRight"
                }, [r("div", {
                    ref: "trendRight",
                    staticClass: "trendCon"
                }, [t.trendData.length ? r("table", [t._m(0), t._v(" "), r("tbody", [1 == t.lottery_type || 3 == t.lottery_type || 15 == t.lottery_type || 16 == t.lottery_type || 17 == t.lottery_type || 18 == t.lottery_type ? r("tr", [r("td", [t._v("interval")])]) : t._e(), t._v(" "), t._l(t.trendData, function(e, a) {
                    return r("tr", {
                        key: a
                    }, [r("td", [r("span", {
                        staticClass: "text-red"
                    }, [t._v(t._s(e.issue))])])])
                })], 2)]) : t._e()])]), t._v(" "), t.trendData.length > 0 ? r("div", [t.trendData.length ? r("scroll", {
                    ref: "scroll",
                    staticClass: "top-date-scroll",
                    attrs: {
                        data: t.trendData,
                        pullUpLoad: t.pullUpLoad,
                        pullDownRefresh: t.pullDownRefresh,
                        freeScroll: t.freeScroll,
                        "listen-scroll": t.listenScroll,
                        "probe-type": t.probeType
                    },
                    on: {
                        pullingDown: t.pullingDown,
                        pullingUp: t.pullingUp,
                        scroll: t.scroll
                    }
                }, [r("div", {
                    ref: "trendTable",
                    staticClass: "trendCon zouTableTop"
                }, [10 == t.lottery_type ? r("table", [r("thead", [r("tr", [r("th", [t._v(t.english[0]["胜负"])]), t._v(" "), r("th", [t._v(t.english[0]["牛牛"])]), t._v(" "), r("th", [t._v(t.english[0]["公牌"])]), t._v(" "), r("th", [t._v(t.english[0]["龙虎"])]), t._v(" "), r("th", [t._v(t.english[0]["总和大"])]), t._v(" "), r("th", [t._v(t.english[0]["总和小"])]), t._v(" "), r("th", [t._v(t.english[0]["单"])]), t._v(" "), r("th", [t._v(t.english[0]["双"])])])]), t._v(" "), r("tbody", t._l(t.trendData, function(e, a) {
                    return r("tr", {
                        key: a
                    }, [r("td", [r("span", {
                        class: ["红" == e.spare_2[0] ? "txt-red" : "蓝" == e.spare_2[0] ? "txt-blue" : ""]
                    }, [t._v(t._s(t.english[0][e.spare_2[0]]))])]), t._v(" "), r("td", [r("span", [t._v(t._s(t.english[0][e.spare_2[1]]))])]), t._v(" "), r("td", [r("span", {
                        class: ["有" == e.spare_2[2] ? "txt-red" : "无" == e.spare_2[2] ? "txt-blue" : ""]
                    }, [t._v(t._s(t.english[0][e.spare_2[2]]))])]), t._v(" "), r("td", [r("span", {
                        class: ["龙" == e.spare_2[3] ? "txt-red" : "虎" == e.spare_2[3] ? "txt-blue" : ""]
                    }, [t._v(t._s(t.english[0][e.spare_2[3]]))])]), t._v(" "), r("td", [r("span", [t._v(t._s(t.english[0][e.spare_2[4]]))])]), t._v(" "), r("td", [r("span", [t._v(t._s(t.english[0][e.spare_2[5]]))])]), t._v(" "), r("td", [r("span", [t._v(t._s(t.english[0][e.spare_2[6]]))])]), t._v(" "), r("td", [r("span", [t._v(t._s(t.english[0][e.spare_2[7]]))])])])
                }))]) : t._e(), t._v(" "), 2 == t.lottery_type || 4 == t.lottery_type || 9 == t.lottery_type || 14 == t.lottery_type || 21 == t.lottery_type ? r("table", [r("thead", [r("tr", [r("th", [t._v("1st/2nd")]), t._v(" "), r("th", [t._v("Big")]), t._v(" "), r("th", [t._v("Small")]), t._v(" "), r("th", [t._v("Odd")]), t._v(" "), r("th", [t._v("Even")]), t._v(" "), r("th", [t._v("Blue/Red")])])]), t._v(" "), r("tbody", t._l(t.trendData, function(e, a) {
                    return r("tr", {
                        key: a
                    }, [r("td", [r("span", [t._v(t._s(e.open_result))])]), t._v(" "), t._l(e.spare_2, function(e, a) {
                        return r("td", {
                            key: a
                        }, ["" != e.split("-")[0] ? r("span", {
                            style: {
                                color: e.split("-")[1]
                            }
                        }, [t._v(t._s(t.english[0][e.split("-")[0]]))]) : t._e()])
                    })], 2)
                }))]) : t._e(), t._v(" "), 5 == t.lottery_type || 6 == t.lottery_type || 11 == t.lottery_type || 19 == t.lottery_type || 20 == t.lottery_type ? r("table", [r("thead", [r("tr", [r("th", [t._v("Sum")]), t._v(" "), r("th", [t._v("Big")]), t._v(" "), r("th", [t._v("Small")]), t._v(" "), r("th", [t._v("Odd")]), t._v(" "), r("th", [t._v("Even")]), t._v(" "), r("th", [t._v("Blue/Red/Both")])])]), t._v(" "), r("tbody", t._l(t.trendData, function(e, a) {
                    return r("tr", {
                        key: a
                    }, [r("td", [r("span", [t._v(t._s(e.spare_2[0].split("-")[0]))])]), t._v(" "), t._l(e.spare_2, function(e, a) {
                        if(a != 0){
                            return r("td", {
                                key: a
                            }, ["" != e.split("-")[0] ? r("span", {
                                class: ["小" == e.split("-")[0] || "双" == e.split("-")[0] ? "txt-blue" : "大" == e.split("-")[0] || "单" == e.split("-")[0] ? "txt-red" : ""],
                                style: {
                                    color: e.split("-")[1]
                                }
                            }, [t._v(t._s(t.english[0][e.split("-")[0]]))]) : t._e()])
                    }})], 2)
                }))]) : t._e(), t._v(" "), 13 == t.lottery_type ? r("table", [r("thead", [r("tr", [r("th", [t._v("Sum")]), t._v(" "), r("th", [t._v("Big")]), t._v(" "), r("th", [t._v("Small")]), t._v(" "), r("th", [t._v("Odd")]), t._v(" "), r("th", [t._v("Even")]), t._v(" "), r("th", [t._v("Triple")])])]), t._v(" "), r("tbody", t._l(t.trendData, function(e, a) {
                    return r("tr", {
                        key: a
                    }, t._l(e.spare_2, function(e, a) {
                        if(a == 0 || a == 5){
                            return r("td", {
                                key: a
                            }, ["" != e.split("-")[0] ? r("span", {
                                class: ["小" == e.split("-")[0] || "双" == e.split("-")[0] ? "txt-blue" : "大" == e.split("-")[0] || "单" == e.split("-")[0] ? "txt-red" : ""],
                                style: {
                                    color: e.split("-")[1]
                                }
                            }, [t._v(t._s(e.split("-")[0]))]) : t._e()])
                        }else{
                            return r("td", {
                                key: a
                            }, ["" != e.split("-")[0] ? r("span", {
                                class: ["小" == e.split("-")[0] || "双" == e.split("-")[0] ? "txt-blue" : "大" == e.split("-")[0] || "单" == e.split("-")[0] ? "txt-red" : ""],
                                style: {
                                    color: e.split("-")[1]
                                }
                            }, [t._v(t._s(t.english[0][e.split("-")[0]]))]) : t._e()])
                    }}))
                }))]) : t._e(), t._v(" "), 22 == t.lottery_type || 23 == t.lottery_type ? r("table", [r("thead", [r("tr", [r("th", [t._v("总和")]), t._v(" "), r("th", [t._v("大")]), t._v(" "), r("th", [t._v("小")]), t._v(" "), r("th", [t._v("单")]), t._v(" "), r("th", [t._v("双")]), t._v(" "), r("th", [t._v("豹子")])])]), t._v(" "), r("tbody", t._l(t.trendData, function(e, a) {
                    return r("tr", {
                        key: a
                    }, t._l(e.spare_2, function(e, a) {
                        return r("td", {
                            key: a
                        }, [r("span", {
                            class: ["小" == e.split("_")[1] || "双" == e.split("_")[1] ? "txt-red" : "大" == e.split("_")[1] || "单" == e.split("_")[1] ? "txt-blue" : ""],
                            style: {
                                color: e.split("_")[1]
                            }
                        }, [t._v(t._s(e.split("_").length > 1 ? e.split("_")[1] : e))])])
                    }))
                }))]) : t._e(), t._v(" "), 7 == t.lottery_type || 8 == t.lottery_type ? r("table", [r("thead", [r("tr", [r("th", [t._v("总和")]), t._v(" "), r("th", [t._v("特码")]), t._v(" "), r("th", [t._v("色波")]), t._v(" "), r("th", [t._v("大")]), t._v(" "), r("th", [t._v("小")]), t._v(" "), r("th", [t._v("单")]), t._v(" "), r("th", [t._v("双")]), t._v(" "), r("th", [t._v("合大")]), t._v(" "), r("th", [t._v("合小")]), t._v(" "), r("th", [t._v("合单")]), t._v(" "), r("th", [t._v("合双")]), t._v(" "), r("th", [t._v("和")])])]), t._v(" "), r("tbody", t._l(t.trendData, function(e, a) {
                    return r("tr", {
                        key: a
                    }, [r("td", [r("span", [t._v(t._s(e.spare_2[0]))])]), t._v(" "), r("td", [r("span", [t._v(t._s(e.spare_2[1] + "[" + e.spare_2[2] + "][" + e.spare_2[3] + "]"))])]), t._v(" "), r("td", [r("span", {
                        class: ["红波" == e.spare_2[4] ? "txt-red" : "绿波" == e.spare_2[4] ? "txt-green" : "蓝波" == e.spare_2[4] ? "txt-blue" : ""]
                    }, [t._v(t._s(e.spare_2[4]))])]), t._v(" "), r("td", [r("span", {
                        staticClass: "txt-blue"
                    }, [t._v(t._s(e.spare_2[5]))])]), t._v(" "), r("td", [r("span", {
                        staticClass: "txt-red"
                    }, [t._v(t._s(e.spare_2[6]))])]), t._v(" "), r("td", [r("span", {
                        staticClass: "txt-blue"
                    }, [t._v(t._s(e.spare_2[7]))])]), t._v(" "), r("td", [r("span", {
                        staticClass: "txt-red"
                    }, [t._v(t._s(e.spare_2[8]))])]), t._v(" "), r("td", [r("span", {
                        staticClass: "txt-blue"
                    }, [t._v(t._s(e.spare_2[9]))])]), t._v(" "), r("td", [r("span", {
                        staticClass: "txt-red"
                    }, [t._v(t._s(e.spare_2[10]))])]), t._v(" "), r("td", [r("span", {
                        staticClass: "txt-blue"
                    }, [t._v(t._s(e.spare_2[11]))])]), t._v(" "), r("td", [r("span", {
                        staticClass: "txt-red"
                    }, [t._v(t._s(e.spare_2[12]))])]), t._v(" "), r("td", [r("span", [t._v(t._s(e.spare_2[13]))])])])
                }))]) : t._e(), t._v(" "), 1 == t.lottery_type || 3 == t.lottery_type || 15 == t.lottery_type || 16 == t.lottery_type || 17 == t.lottery_type || 18 == t.lottery_type ? r("table", [r("thead", [r("tr", [r("th", [t._v("value")]), t._v(" "), r("th", [t._v("big")]), t._v(" "), r("th", [t._v("small")]), t._v(" "), r("th", [t._v("Odd")]), t._v(" "), r("th", [t._v("Even")]), t._v(" "), r("th", [t._v("big Odd")]), t._v(" "), r("th", [t._v("big Even")]), t._v(" "), r("th", [t._v("small Odd")]), t._v(" "), r("th", [t._v("small Even")])])]), t._v(" "), r("tbody", [r("tr", [r("td", [t._v("-")]), t._v(" "), r("td", [t._v(t._s(t.trendData[0].spare_3[0]))]), t._v(" "), r("td", [t._v(t._s(t.trendData[0].spare_3[1]))]), t._v(" "), r("td", [t._v(t._s(t.trendData[0].spare_3[2]))]), t._v(" "), r("td", [t._v(t._s(t.trendData[0].spare_3[3]))]), t._v(" "), r("td", [t._v(t._s(t.trendData[0].spare_3[4]))]), t._v(" "), r("td", [t._v(t._s(t.trendData[0].spare_3[5]))]), t._v(" "), r("td", [t._v(t._s(t.trendData[0].spare_3[6]))]), t._v(" "), r("td", [t._v(t._s(t.trendData[0].spare_3[7]))])]), t._v(" "), t._l(t.trendData, function(e, a) {
                    return r("tr", {
                        key: a
                    }, [r("td", [r("span", [t._v(t._s(e.open_result))])]), t._v(" "), t._l(e.spare_2, function(e, a) {
                        return r("td", {
                            key: a
                        }, ["" != e.split("-")[0] ? r("span", {
                            style: {
                                color: e.split("-")[1]
                            }
                        }, [t._v(t._s(e.split("-")[0]))]) : t._e()])
                    })], 2)
                })], 2)]) : t._e()])]) : t._e()], 1) : [r("img", {
                    staticClass: "noDataImg",
                    attrs: {
                        src: a("w+73"),
                        alt: ""
                    }
                })]], 2), t._v(" "), r("div", {
                    staticClass: "bottom-skip"
                }, [r("a", {
                    staticClass: "text-red",
                    attrs: {
                        herf: "javascript:void(0);"
                    },
                    on: {
                        click: t.goLotteryRoom
                    }
                }, [t._v("Buy lottery now"), r("i", {
                    staticClass: "icon"
                })])]), t._v(" "), r("div", {
                    directives: [{
                        name: "transfer-dom",
                        rawName: "v-transfer-dom"
                    }]
                }, [r("transition", {
                    attrs: {
                        name: "lotteryType"
                    }
                }, [t.lotteryFlag ? r("div", {
                    staticClass: "lotteryType-inner"
                }, [r("div", {
                    staticClass: "amount-tab-list"
                }, [r("checker", {
                    attrs: {
                        "default-item-class": "",
                        "selected-item-class": "active"
                    },
                    model: {
                        value: t.checkerData,
                        callback: function(e) {
                            t.checkerData = e
                        },
                        expression: "checkerData"
                    }
                }, t._l(t.lotteryData, function(e, a) {
                    return r("checker-item", {
                        key: a,
                        attrs: {
                            value: e.name
                        },
                        on: {
                            "on-item-click": function(a) {
                                t.onItemClick(e)
                            }
                        }
                    }, [r("span", [t._v(t._s(t.english[0][e.name]))])])
                }))], 1)]) : t._e()]), t._v(" "), t.lotteryFlag ? r("div", {
                    staticClass: "lotteryType-cover",
                    class: {
                        "lotteryType-show": t.lotteryFlag
                    },
                    on: {
                        click: function(e) {
                            if (e.target !== e.currentTarget)
                                return null;
                            t.lotteryFlag = !1
                        }
                    }
                }) : t._e()], 1)], 1)
            },
            staticRenderFns: [function() {
                var t = this.$createElement
                  , e = this._self._c || t;
                return e("thead", [e("th", [this._v("issue")])])
            }
            ]
        };
        var v = a("vSla")(n, i, !1, function(t) {
            a("Wxnb")
        }, "data-v-01baa880", null);
        e.default = v.exports
    }
});
//# sourceMappingURL=71.6a2eaf22f537a6926538.js.map
