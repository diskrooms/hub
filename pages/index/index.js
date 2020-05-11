import Utils from '../../assets/js/utils.js';

let app = getApp();
Page({
  data: {
    show: false,        //是否显示域名填写框
    showAddServerModal:false,  //是否显示添加服务器对话框
    validDomain:false,  //JS正则匹配所填写域名是否合法
    domain:'',          //存储填写域名值
    listData:[],
    showAuthUser:false, //是否显示授权员工面板
    addAuthUser:false,  //是否显示添加授权员工对话框
    authorizationUserData:{}      //需要添加授权员工表单数据
  },
  
  Utils: new Utils(),

  onLoad() {
      let that = this
      
      //查找缓存中是否已经存放域名 未存放则弹出添加域名对话框提示添加
      dd.getStorage({
        key: 'hubDomain',
        success: function(res) {
          if(!res.data){
            that.setData({'show':true})
          } else {
            that.setData({'domain':res.data})
            //console.log(res)
            //请求接口获取服务资源数据
            dd.getAuthCode({
                success:function(authRes){
                  dd.httpRequest({
                    url: 'http://'+res.data+'/dingtalk.php?opt=listServer&code='+authRes.authCode,
                    method: 'GET',
                    data: {},
                    dataType: 'json',
                    timeout:2000,
                    success: function(serversRes) {
                      that.setData({'listData':serversRes.data.data})
                    },
                    fail: function(res) {
                      console.log(res)
                    }
                  });
                },
                fail:function(err){

                }
            });
          }
        },
        fail: function(res){
          dd.alert({content: res.errorMessage});
        }
      });
  },

  onShow(){
      /*dd.getAuthCode({
          success:function(res){
            //dd.alert({content: res.authCode});
          },
          fail:function(err){

          }
      })*/
  },

  //打开“添加服务器”对话框
  showAddServerModal(){
    this.setData({"showAddServerModal":true})
  },

  //关闭“添加服务器”对话框
  closeAddServerModal(){
      this.setData({"showAddServerModal":false})
  },

  //执行添加服务器操作


  //执行添加域名操作
  addDomain(e){
      let that = this
      let domain = e.detail.value.domain

      //获取小程序免登code
      dd.getAuthCode({
          success:function(res){
            //console.log(res) 662e6b09e81633ca9784795a270a2728
            let code = res.authCode
            //测试域名是否部署了服务
            dd.httpRequest({
              url: 'http://'+domain+'/dingtalk.php?opt=test&code='+code,
              method: 'POST',
              data: {'opt':'test','code':code},
              dataType: 'json',
              timeout:2000,
              success: function(res) {
                dd.setStorage({
                  key: 'hubDomain',
                  data:domain,
                  success: function() {
                    that.setData({'show':false})
                    dd.alert({content: '添加成功'});
                  },
                  fail: function(res){
                    dd.alert({content: res.errorMessage});
                  }
                });
              },
              fail: function(res) {
                console.log(res)
              },
              complete: function(res) {
                console.log(res)
              }
            });
          },
          fail:function(err){

          }
      });

      


      
  },

  //对实时输入的 url 作检测 看是否是合法的域名
  checkDomain(e){
    let domain = e.detail.value
    if(this.Utils.validDomain(domain)){
      this.setData({"validDomain":true})
    } else {
      this.setData({"validDomain":false})
    }
  },

  //显示授权员工面板
  showAuthUsers(e){
      this.setData({"showAuthUser":true})
  },
  
  //关闭授权员工面板
  hiddenAuthUsers(e){
      this.setData({"showAuthUser":false})
  },

  //显示添加授权员工面板
  showAddAuthUser(e){
    this.setData({"addAuthUser":true})
  },

  //隐藏添加授权员工面板
  hideAddAuthUser(e){
    this.setData({"addAuthUser":false})
  },

  //选择授权员工
  chooseEmployee(e){
    let that = this
    dd.complexChoose({
        title:"测试标题",            //标题
        multiple:true,            //是否多选
        limitTips:"超出了",          //超过限定人数返回提示
        maxUsers:1000,            //最大可选人数
        pickedUsers:[],            //已选用户
        pickedDepartments:[],          //已选部门
        disabledUsers:[],            //不可选用户
        disabledDepartments:[],        //不可选部门
        requiredUsers:[],            //必选用户（不可取消选中状态）
        requiredDepartments:[],        //必选部门（不可取消选中状态）
        permissionType:"xxx",          //可添加权限校验，选人权限，目前只有GLOBAL这个参数
        responseUserOnly:false,        //返回人，或者返回人和部门
        success:function(res){
            if(res.users.length == 0){
              dd.showToast({
                type: 'fail',
                content: '暂不支持部门选择',
                duration: 2000
              });
            } else if(res.users.length > 1) {
              dd.showToast({
                type: 'fail',
                content: '暂不支持多员工选择',
                duration: 2000
              });
            } else {
              let authorizationUserData = {}
              authorizationUserData['employee_name'] = res.users[0]['name']
              authorizationUserData['authorization_name'] = 'dingding-'+res.users[0]['userId']
              authorizationUserData['authorization_pwd'] = 'dingding-'+res.users[0]['userId']
              authorizationUserData['authorization_repwd'] = 'dingding-'+res.users[0]['userId']
              that.setData({'authorizationUserData':authorizationUserData})
            }
        },
        fail:function(err){
        }
    })
  }
});