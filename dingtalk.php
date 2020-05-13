<?php
define('HUB', '');
$config = include '../conf.php';
$config = refreshToken($config);

$opt = isset($_GET['opt']) ? addslashes(trim($_GET['opt'])) : '';       //操作
$code = isset($_GET['code']) ? addslashes(trim($_GET['code'])) : '';    //小程序免登code码

if(empty($opt) || empty($code)){
    ejson(199,[],'鉴权失败');
}

//用户鉴权
$authUrl = 'https://oapi.dingtalk.com/user/getuserinfo?access_token='.$config['token'].'&code='.$code;
$authRs = json_decode(requestGet($authUrl),true);

if($authRs['errcode'] == 0){
    //请求成功
    //测试无需继续往下执行 证明域名正常部署即可
    if($opt == 'test'){
        ejson(200);
    }
    //不是管理员
    if($authRs['is_sys'] != 1){
        ejson(199);
    }
} else {
    ejson(198,[],$authRs['errmsg']);
}


///////////////////////////业务逻辑
//返回所有服务器资源
if($opt == 'listServer'){
    $servers = json_decode(file_get_contents('../servers.json'),true);
    ejson(200,$servers,'ok');
}

//添加服务器资源
if($opt == 'addServer'){
    $server_ip = isset($_POST['server_ip']) ? addslashes(trim($_POST['server_ip'])) : '';
    $server_desc = isset($_POST['server_desc']) ? addslashes(trim($_POST['server_desc'])) : '';
    $server_port = isset($_POST['server_port']) ? addslashes(trim($_POST['server_port'])) : '';
    $server_pwd = isset($_POST['server_pwd']) ? addslashes(trim($_POST['server_pwd'])) : '';
    $server_type = isset($_POST['server_type']) ? addslashes(trim($_POST['server_type'])) : '';

    //TODO 更详细的过滤规则
    if(empty($server_ip) || empty($server_port) || empty($server_pwd) || empty($server_type)){
        ejson(198,[],'参数不能为空');
    }
    $servers = json_decode(file_get_contents('../servers.json'),true);
    foreach($servers as $server){
        if($server['ip'] == $server_ip){
            ejson(198,[],'不能重复添加服务器');
        }
    }
    $add = array(
            array(
                'ip'=>$server_ip,
                'desc'=>$server_desc,
                'opt'=>'查看员工',
                'pwd'=>$server_pwd,
                'type'=>$server_type,
                'port'=>$server_port
            )
        );
    $servers = array_merge($servers,$add);
    $res = file_put_contents('../servers.json',json_encode($servers,JSON_UNESCAPED_UNICODE));
    if($res){
        ejson(200,[],'添加成功');
    } else {
        ejson(197,[],'添加失败');
    }
} else if($opt == 'getAuthorizationUsers'){
    //获取授权员工列表
    $index = intval($_POST['index']);
    $servers = json_decode(file_get_contents('../servers.json'),true);
    if(!isset($servers[$index])){
        ejson(196,[],'服务器信息丢失');
    }
    $oldAuthUsers = $servers[$index]['authUsers'] ? $servers[$index]['authUsers'] : array();
    ejson(200,$oldAuthUsers,'ok');
    
} else if($opt == 'delAuthorizationUser'){
    //删除授权员工
    

} else if($opt == 'addAuthorizationUser'){
    //添加授权员工
    $index = intval($_POST['index']);   //服务器索引
    $authTrueName = isset($_POST['authTrueName']) ? addslashes(trim($_POST['authTrueName'])) : '';
    $authUserName = isset($_POST['authUserName']) ? addslashes(trim($_POST['authUserName'])) : '';
    $authPwd = isset($_POST['authPwd']) ? addslashes(trim($_POST['authPwd'])) : '';
    $authRepwd = isset($_POST['authRepwd']) ? addslashes(trim($_POST['authRepwd'])) : '';
    if(empty($authTrueName) || empty($authUserName) || empty($authPwd) || empty($authRepwd)){
        ejson(195,[],'添加授权员工参数缺失');
    }
    if($authPwd !== $authRepwd){
        ejson(194,[],'添加授权员工密码不一致');
    }
    $servers = json_decode(file_get_contents('../servers.json'),true);
    if(!isset($servers[$index])){
        ejson(196,[],'服务器信息缺失');
    }
    $ssh = _sshConnectByPwd($servers[$index]);
    if($ssh){
        $addAuthUserResult = $ssh->exec("useradd ".$authUserName.";echo ".$authPwd."|passwd --stdin ".$authUserName);
        if($addAuthUserResult){
            $oldAuthUsers = $servers[$index]['authUsers'] ? $servers[$index]['authUsers'] : array();
            $newAuthUser = array(
                array('truename'=>$authTrueName,'username'=>$authUserName)
            );

            $authUsers = array_merge($oldAuthUsers,$newAuthUser);
            $servers[$index]['authUsers'] = $authUsers;
            $res = file_put_contents('../servers.json',json_encode($servers,JSON_UNESCAPED_UNICODE));
            if($res){
                //添加授权员工成功
                ejson(200,$authUsers,'ok');
            } else {
                ejson(192,[],'添加授权员工失败');
            }
        } else {
            ejson(193,[],'添加授权员工失败');
        }
    } else {
        ejson(196,[],'添加授权员工连接失败');
    }
} else {
    ejson(190,[],'非法操作');
}

/**
 * ssh通过密码连接
 * @return number|boolean
 */
function _sshConnectByPwd($serverInfo = array()){
    include '../vendor/autoload.php';
    $ssh = new \phpseclib\Net\SSH2($serverInfo['ip'],$serverInfo['port']);
    if ($ssh->login('root', $serverInfo['pwd'])) { 
        return $ssh;
    }else{ 
        return false;
    }
}

/**
 * token失效验证及更新函数
 */
function refreshToken($config){
    if($config['timeout'] <= time() + 120){
        $getTokenUrl = 'https://oapi.dingtalk.com/gettoken?appkey='.$config['appkey'].'&appsecret='.$config['appsecret'];
        $rs = json_decode(requestGet($getTokenUrl),true);
        $config = array(
            'token'=>$rs['access_token'],
            'timeout'=>time() + 7200,
            'appkey'=>$config['appkey'],
            'appsecret'=>$config['appsecret']
        );
        $CONF = "<?php
                if(!defined('HUB')){
                	exit();
                }
                return array(
                    'token'=>'".$config['token']."',
                    'timeout'=>".$config['timeout'].",
                    'appkey'=>'".$config['appkey']."',
                    'appsecret'=>'".$config['appsecret']."'
                );";
        file_put_contents('../conf.php',$CONF);
    }
    return $config;
}


/**
 * json输出函数
 */
function ejson($code,$data = [], $msg = '',$exit = 1){
    if($exit){
        exit(json_encode(['code'=>$code,'data'=>$data,'msg'=>$msg],JSON_UNESCAPED_UNICODE));
    } else {
        echo json_encode(['code'=>$code,'data'=>$data,'msg'=>$msg],JSON_UNESCAPED_UNICODE);
    }
}

/**
 * GET请求数据 如果有curl扩展 就使用curl进行请求 如果没有相应模块 就使用file_get_contents函数
 * url 		要请求的url地址
 * data		发送数据 json字符串 '{"abc":"123","def":"123"}'
 * cookie 	请求附带的cookie 例子 abc=123;def=456
 * timeout 	超时时间
 * count	请求总数(超时重发)
 */
function requestGet($url, $data = '',$header = array(), $cookie = '',$timeout = 6, $count = 3){
    static $index = 0 ;
    $index++;
    if(empty($url) || (strpos($url, 'http') === false)){
        throw new exception('缺少url参数或者url格式不合法,url应包含http或者https协议');
        exit();
    } else {
        //如果是https协议的url,检查openssl组件
        //用检测函数存在的方式进行检查 检测组件是否加载的方式不一定准确
        //有些组件被直接编译进了php,并不一定是通过加载组件的方式进行加载的，比如说 openssl
        if(strpos($url, 'https') !== false){
            if(!function_exists('openssl_open')){
                throw new exception('缺少openssl组件支持,请确保openssl扩展已经正确加载或已经编译进php');
                exit();
            }
        }
    }
    $ch = null;
    if(function_exists('curl_init')){
        $ch = curl_init();
    } else {
        //throw new Exception('没有curl_init函数,请检查curl组件是否已经正常加载');
        //exit();
    }
    if($ch){
        //初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE); //不认证https证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        //curl_setopt($ch, CURLOPT_PROXY, '192.168.1.100'); //代理服务器地址
        //curl_setopt($ch, CURLOPT_PROXYPORT,'8888'); 		//代理服务器端口
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);		//设置获取的信息以文件流的形式返回，而不是直接输出
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        if(!empty($cookie)){
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if(!empty($header)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        $content = curl_exec($ch);
        if($content === false){
            if(curl_errno($ch) == CURLE_OPERATION_TIMEDOUT){
                if($index < $count){
                    //重发请求
                    requestGet($url,$header,$cookie,$timeout,$count);
                }
            } else {
                exit(curl_error($ch));
            }
        }
        curl_close($ch);
    } else {
        //检查 allow_url_fopen 配置
        //开启返回 "1" 关闭返回 ""
        //allow_url_fopen的修改范围是PHP_INI_SYSTEM，这个选项只能在php.ini或httpd.conf中修改，不能在脚本中修改
        if(ini_get('allow_url_fopen') == ''){
            //ini_set('allow_url_fopen', '1');
            throw new Exception('请检查allow_url_fopen配置项是否在php.ini中开启');
            exit();
        }
        $content = file_get_contents($url);
    }
    return $content;
}


/**
 * POST请求数据 如果有curl扩展 就使用curl进行请求 如果没有相应模块 就是用file_get_contents函数
 * url 		要请求的url地址
 * data		发送数据 数组格式或者json字符串 '{"abc":"123","def":"123"}'
 * header	自定义请求头	array('accept:application/json','content-type:application/json')
 * timeout 	超时时间
 * count	请求总数(超时重发)
 */
function requestPost($url = '', $data = array(), $header = array(), $timeout = 6, $count = 3){
    static $index = 0 ;
    $index++;
    if(empty($url) || (strpos($url, 'http') === false)){
        throw new exception('缺少url参数或者url格式不合法,url应包含http或者https协议');
        exit();
    } else {
        //如果是https协议的url,检查openssl组件
        //用检测函数存在的方式进行检查 检测组件是否加载的方式不一定准确
        //有些组件被直接编译进了php,并不一定是通过加载组件的方式进行加载的，比如说 openssl
        if(strpos($url, 'https') !== false){
            if(!function_exists('openssl_open')){
                throw new exception('缺少openssl组件支持,请确保openssl扩展已经正确加载或已经编译进php');
                exit();
            }
        }
    }
    $ch = null;
    if(function_exists('curl_init')){
        $ch = curl_init();
    }
    if($ch){
        //初始化curl
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE); //不验证 https 证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        if(empty($header)){
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        //curl_setopt($ch, CURLOPT_PROXY, '192.168.1.100'); //代理服务器地址
        //curl_setopt($ch, CURLOPT_PROXYPORT,'8888'); 		//代理服务器端口
        //curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, 1);				// post方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);	// post数据 php数组格式或字符串
        $content = curl_exec($ch);
        if($content === false){
            if(curl_errno($ch) == CURLE_OPERATION_TIMEDOUT){
                if($index < $count){
                    //请求重发
                    requestPost($url,$data);
                }
            }
            
        }
        curl_close($ch);
    } else {
        //检查 allow_url_fopen 配置
        //开启返回 "1" 关闭返回 ""
        //allow_url_fopen的修改范围是PHP_INI_SYSTEM，这个选项只能在php.ini或httpd.conf中修改，不能在脚本中修改
        if(ini_get('allow_url_fopen') == ''){
            //ini_set('allow_url_fopen', '1');
            throw new Exception('请检查allow_url_fopen配置项是否在php.ini中开启');
            exit();
        }
        $data = http_build_query($data);
        $context = array(
            'http'=>array(
                'method'=>'POST',
                'content'=>$data
            )
        );
        $context  = stream_context_create($context);
        $content = file_get_contents($url,false,$context);
    }
    return $content;
}