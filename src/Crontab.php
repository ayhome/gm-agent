<?php
namespace ayhome\agent;

use Group\Rpc\RpcService;
class Crontab extends Base
{

  public function __construct($setting = array())
  {
    $this->checkMasterProcess($command);
    $this->setProcessName('gm-server: ' . $this->ppid . self::PROCESS_NAME_LOG);
    // $this->registSignal();

    $setting = array(
      'open_length_check' => 0,
      // 'package_max_length' => 2465792,
      'package_length_type' => 'N',
      'heartbeat_idle_time' => 30,
      'heartbeat_check_interval' => 10,
      'max_request' => 2000,
      // 'package_length_offset' => 4
    );

    if (!empty($setting['daemon'])) {
        \swoole_process::daemon(true,false);
    }
    if (!empty($setting['title'])){
        $this->setProcessTitle($setting['title']);
    }
    $this->cli = new \swoole_client(SWOOLE_SOCK_TCP,SWOOLE_SOCK_ASYNC);
    $this->cli->on("Connect",[$this,"onConnect"]);
    $this->cli->on("Error",[$this,"onError"]);
    $this->cli->on("Receive",[$this,"onReceive"]);
    $this->cli->on("Close",[$this,"onClose"]);
    $this->cli->set($setting);
    
    $this->connect();
  }

  public function connect()
  {
    $config = array(
      "host" =>'127.0.0.1',
      "port" =>9911,
    );
    $info = "connect=>host:".$config["host"]." port:".$config["port"];
    $this->show($info);
    $this->cli->connect($config["host"],$config["port"],30);
  }



  public function onConnect($cli='')
  {
    $this->show('连接成功');
    //清除重连定时器
    $this->clearTimer();
    //连接上了注册服务
    $info = "正在发起注册";
    $this->show($info);

    
    $this->register();

  }



  public function onReceive($cli,$data)
  {

    $data = $this->decode($data);
    if ($data['task']['execute']) {
      $task = $data['task'];
      $execute = $task['execute'];
      // for ($i = 0; $i < $task['run_number']+1; $i++) {
        // print_r($task);
        $this->reserveExec(1, $task);
      // }
      // $this->registSignal();
    }
  }

  public function onClose($cli)
  {
    // $info = "连接关闭,[5]秒之后重新连接";
    // $this->show($info);    
    $this->reConnect();
  }

  public function onError($cli)
  {
    if ($cli->errCode == 61 || $cli->errCode == 111){
      $info = "连接中心服失败,[5]秒之后重新连接";
      $this->show($info);

      $this->reConnect();
    }else{
      $info = "Error=>code:".$cli->errCode."msg:".socket_strerror($cli->errCode);
      $this->show($info);
    }
  }



}