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


//业务逻辑
if($opt == 'listServer'){
    //返回所有服务器资源
    $servers = json_decode(file_get_contents('../servers.json'),true);
    ejson(200,$servers,'ok');
}

if($opt == 'getAuthorizationUsers'){
    
}

if($opt == 'delAuthorizationUser'){
    
}

if($opt == 'addAuthorizationUser'){
    
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