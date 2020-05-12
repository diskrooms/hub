<?php
/**
 * 该模块仅限于私有部署
 * 专业版模块
 */
namespace app\hrm\controller;

use symfony\process\Process;
use symfony\process\Exception\ProcessFailedException;

use app\hrm\model\ResourceModel;
use app\hrm\model\ServerModel;
use app\hrm\model\StaffModel;
use app\hrm\model\DepartModel;
 
class ResourceController extends LoginBaseController
{
    private $central_host = '138.128.214.158';        //服务器IP地址
    private $central_user = 'root';             //服务器root用户
    private $central_pwd = 'u8hjpbpsYyTY';      //服务器root用户密码
    private $central_port = 29671;              //服务器ssh端口
    
    private $connection = null;                 //ssh连接对象
    
    /**
     * 服务器资源列表
     * @return 
     */
    public function list_server()
    {
        $params = $this->request->param();
        //部门结构
        $departModel         = new DepartModel();
        $categoriesTree      = $departModel->departTree(0);
        $this->assign('categories_tree', $categoriesTree);
        //服务器数据
        $servers = ServerModel::getServers('1=1');
        $this->assign('servers',$servers);
        return $this->fetch();
    }
    
    
    /**
     * 返送服务器授权访问用户列表数据节点
     */
    public function getAuthorizationUsers(){
        $params = $this->request->param();
        $server_id = isset($params['server_id']) ? addslashes(trim($params['server_id'])) : null;
        if(!isset($server_id) || empty($server_id)){
            ejson(-1,'参数错误',1);
        }
        //获取ssh连接状态
        /* if(!$this->_sshConnectByCert($server_id)){
            ejson(-2,'请先将公钥追加到/root/.ssh/authorized_keys中',1);
        } */
        $authorizationUsers = ResourceModel::getAuthorizationUsers('server_id='.$server_id);
        if(count($authorizationUsers) > 0){
            $staffIDs = implode(',', array_column($authorizationUsers, 'staff_id'));
            $staffInfos = StaffModel::getStaffs('id IN('.$staffIDs.')','id DESC','','id,truename');
            $staffInfos_ = [];
            foreach ($staffInfos as $v){
                $staffInfos_[$v['id']] = $v['truename'];
            }
            foreach ($authorizationUsers as &$v){
                $v['staff_name'] = $staffInfos_[$v['staff_id']];
            }
        }
        //dump($authorizationUsers);
        ejson(1,$authorizationUsers,1);
    }
    
    /**
     * 删除一位用户授权
     */
    public function delAuthorizationUser(){
        $params = $this->request->param();
        $id = isset($params['id']) ? addslashes(trim($params['id'])) : null;
        if(!isset($id) || empty($id)){
            ejson(-1,'参数错误',1);
        }
        $info = ResourceModel::getAuthorizationUser('id='.$id);
        if(!empty($info)){
            if($ssh_res = $this->_sshConnectByCert($info['server_id'])){
                //新建用户并设置密码
                $stream = ssh2_exec($this->connection, "userdel ".$info['server_user']);
                // 执行php
                //stream_set_blocking($stream, true);
                // 获取执行pwd后的内容
                if ($stream === FALSE){
                    ejson(-2,'删除失败-2',1);
                } else {
                    ResourceModel::delAuthorizationUser('id='.$id);
                    ejson(1,'删除成功',1);
                }
            } else {
                ejson(-1,$ssh_res,1);
            }
            
        } else {
            ejson(0,'未查找到该用户',1);
        }
    }
    
    /**
     * 增加一位用户授权
     * TODO 使用验证器进行验证
     */
    public function addAuthorizationUser(){
        $params = $this->request->param();
        $server_id = isset($params['server-id']) ? addslashes(trim($params['server-id'])) : null;
        $authorization_staff = isset($params['authorization-staff']) ? addslashes(trim($params['authorization-staff'])) : null;
        $authorization_username = isset($params['authorization-username']) ? addslashes(trim($params['authorization-username'])) : null;
        $authorization_pwd = isset($params['authorization-pwd']) ? addslashes(trim($params['authorization-pwd'])) : null;
        $authorization_repwd = isset($params['authorization-repwd']) ? addslashes(trim($params['authorization-repwd'])) : null;
        if(empty($server_id) || empty($authorization_staff) || empty($authorization_username) || empty($authorization_pwd) || empty($authorization_repwd)){
            ejson(-1,'参数错误',1);
        }
        if($authorization_pwd != $authorization_repwd){
            ejson(-1,'密码不一致',1);
        }
        $data = array(
            'server_id'=>$server_id,
            'staff_id'=>$authorization_staff,
            'server_user'=>$authorization_username,
            'server_pwd'=>$authorization_pwd
        );
        //原则上一位员工在一台服务器上只允许添加一个用户
        $authorization_count = ResourceModel::getCount('server_id='.$server_id.' AND staff_id='.$authorization_staff);
        if($authorization_count > 0){
            ejson(-1,'已存在用户,请不要重复添加',1);
        }

        //执行cmd命令在物理机上添加用户
        /*$process = new Process(array('sudo', '-'));
        $process->run();
        //executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        dump($process->getOutput());*/
        
        if($this->_sshConnectByCert($server_id)){
            //新建用户并设置密码
            $stream = ssh2_exec($this->connection, "useradd ".$authorization_username.";echo ".$authorization_pwd."|passwd --stdin ".$authorization_username);
            // 执行php
            stream_set_blocking($stream, true);
            // 获取执行pwd后的内容
            if ($stream === FALSE){
                //die("exec failed");
                ejson(-1,'添加用户失败',1);
            } else {
                //echo 'result: ' . stream_get_contents($stream) . '<br/>';
                //添加linux用户成功 如果是应用服务器 修改 /etc/ssh/sshd_config配置 在后面追加 限定访问主机
                $server_info = ServerModel::getServer('id='.$server_id);
                if($server_info['type'] == 2){
                    //应用服务器
                    $central_server_info = ServerModel::getServer('type=1');
                    if(empty($central_server_info)){
                        ejson(-2,'请先添加中控服务器',1);
                    } else {
                        $central_server_ip = $central_server_info['ip'];
                        $stream = ssh2_exec($this->connection, "echo 'AllowUsers ".$authorization_username."@".$central_server_ip."'>>/etc/ssh/sshd_config");
                        if($stream === FALSE){
                            ejson(-1,'添加中控权限失败',1);
                        } else {
                            ResourceModel::addAuthorizationUser($data);
                            ejson(1,'添加用户成功',1);
                        }
                    }
                } else {
                    //中控服务器
                    ResourceModel::addAuthorizationUser($data);
                    ejson(1,'添加用户成功',1);
                }
            }
        }
        
    }
    
    /**
     * 私有方法
     * ssh通过密码链接
     * @return number|boolean
     */
    private function _sshConnectByPwd($server_id = 1){
        $server_info = ServerModel::getServer('id='.$server_id);
        $connection = ssh2_connect($server_info['ip'], $server_info['port']);// 链接远程服务器
        if (!$connection){
            //ejson(-1,'connection to ' . $host . ':22 failed',1);
            return -1;
        }
        //获取验证方式
        $auth_methods = ssh2_auth_none($connection, 'root');
        if (in_array('password', $auth_methods)) {
            // 通过password方式登录远程服务器
            if (ssh2_auth_password($connection, 'root', $server_info['root_pwd'])) {
                $this->connection = $connection;
                return true;
            }
        }
        return false;
    }
    
    /**
     * 私有方法
     * ssh通过证书链接
     * @return number|boolean
     */
    private function _sshConnectByCert($server_id = 1){
        $server_info = ServerModel::getServer('id='.$server_id);
        $connection = ssh2_connect($server_info['ip'], $server_info['port']);// 链接远程服务器
        if (!$connection){
            return -1;
        }
        $auth_methods = ssh2_auth_none($connection, 'root');
        if (in_array('publickey', $auth_methods)) {
            // 通过公钥方式登录远程服务器 - 用SecureCRT生成openSSH密钥新格式去测试 无法通过 用旧方式生成就通过了
            $pubfile = DATA_PATH.'server_pri_key/'.$server_info['ip'].'-pub.key';
            $prifile = DATA_PATH.'server_pri_key/'.$server_info['ip'].'-pri.key';
            $res = @ssh2_auth_pubkey_file($connection, 'root', $pubfile, $prifile);
            if ($res) {
                $this->connection = $connection;
                return true;
            }
        }
        return false;
    }
    
    /**
     * php生成RSA密钥对,私钥存储在服务器上,公钥返送回前端
     */
    public function rsaKey(){
        include_once VENDOR_PATH.'/phpseclib/Crypt/RSA.php';
        
        $params = $this->request->param();
        $server_ip = isset($params['server_ip']) ? addslashes(trim($params['server_ip'])) : null;
        if(empty($server_ip)){
            ejson(-2,'参数错误',1);
        }
        $config = array(
            "digest_alg" => "sha512",
            "private_key_bits" => 2048, //字节数    512 1024  2048   4096 等
            "private_key_type" => OPENSSL_KEYTYPE_RSA, //加密类型
        );
        //创建公钥和私钥   返回资源
        $res = openssl_pkey_new($config);
        //从得到的资源中获取私钥，把私钥赋给$privKey
        openssl_pkey_export($res, $privKey, null, $config);
        //从得到的资源中获取公钥，返回公钥$pubKey
        $pubKey = openssl_pkey_get_details($res);
        $pubKey = $pubKey["key"];
        
        //使用phpseclib库将openssl密钥转换成openssh格式
        $rsa = new \Crypt_RSA();
        $rsa->loadKey($pubKey);
        $rsa->setPublicKey();
        $pubKey = $rsa->getPublicKey(CRYPT_RSA_PUBLIC_FORMAT_OPENSSH);
        
        $server_pri_key_dir = DATA_PATH.'server_pri_key/';
        //创建私钥存放文件夹
        if(!file_exists($server_pri_key_dir)){
            $mk_result = mkdir($server_pri_key_dir);
            if($mk_result === false){
                ejson(-1,'创建文件夹失败,请修改data目录权限',1);
            }
        } else {
            if(!is_dir($server_pri_key_dir)){
                ejson(-1,'存在同名文件',1);
            }
        }
        //私钥写入文件
        $server_pri_key_file = $server_pri_key_dir.$server_ip.'-pri.key';
        $write_pri_result = file_put_contents($server_pri_key_file,$privKey);
        $server_pub_key_file = $server_pri_key_dir.$server_ip.'-pub.key';
        $write_pub_result = file_put_contents($server_pub_key_file,$pubKey);
        
        if($write_pri_result === false || $write_pub_result === false){
            ejson(-1,'写文件失败',1);
        }
        ejson(1,$pubKey,1);
    }
    
    /**
     * 添加服务器
     */
   public function addServer(){
       $params = $this->request->param();
       $server_ip = isset($params['server-ip']) ? addslashes(trim($params['server-ip'])) : null;
       $server_note = isset($params['server-note']) ? addslashes(trim($params['server-note'])) : '';
       $server_port = isset($params['server-port']) ? addslashes(trim($params['server-port'])) : 22;
       $server_type = isset($params['server-type']) ? addslashes(trim($params['server-type'])) : 2;
       $server_public_key = isset($params['server-public-key']) ? addslashes(trim($params['server-public-key'])) : null;
       
       if(empty($server_ip) || empty($server_public_key)){
           ejson(-1,'参数错误',1);
       }
       $server_exist = ServerModel::getCount('ip="'.$server_ip.'"');
       if($server_exist > 0){
           ejson(-2,'服务器已存在,请勿重复添加',1);
       }
       $server_data = array(
           'ip'=>$server_ip,
           'note'=>$server_note,
           'type'=>$server_type,
           'port'=>$server_port,
           'pub_key'=>$server_public_key
       );
       $add_res = ServerModel::addServer($server_data);
       if($add_res > 0){
           //添加成功
           ejson(1,'ok',1);
       } else {
           ejson(-3,'fail',1);
       }
   }
   
    
    /**
     * 测试页
     */
   public function test(){
       return $this->fetch();
   }
   
   public function test2(){
       $process = new Process(array('ls', '-lsa'));
       $process->run();
       
       //executes after the command finishes
       if (!$process->isSuccessful()) {
           throw new ProcessFailedException($process);
       }
       
       dump($process->getOutput());
   }
}
