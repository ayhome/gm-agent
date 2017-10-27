<?php
namespace ayhome\agent;

class Base{


  public $reConnectTimerId;
  protected $cli;

//shell脚本管理标示
    const PROCESS_NAME_LOG = ':ayhome-agent-process';
    //pid保存文件
    const PID_FILE = './runtime/agent.pid';

    private $workers;
    private $workNum  = 5;
    private $config   = [];
    private $status   ='running';
    private $ppid     =0;

  public function register()
  {
    $r = $this->encode('register','');
    $this->cli->send($r);
  }

  protected function reConnect()
  {
    $this->clearTimer();
    $this->reConnectTimerId = swoole_timer_tick(5000,function (){
      $this->cli->close(true);
      $this->connect();
    });
  }

  protected function clearTimer()
  {
    if ($this->reConnectTimerId){
      swoole_timer_clear($this->reConnectTimerId);
    }
    $this->reConnectTimerId = 0;
  }

  // 启动子进程，跑业务代码
  public function reserveExec($num, $task)
  {
    $reserveProcess = new \Swoole\Process(function ($worker) use ($num, $task) {
      try {
        $exec = $task["execute"];
        @$worker->name($exec . "#" . $task["id"]);
        $exec = explode(" ", trim($exec));
        $execfile = array_shift($exec);
        $r = $worker->exec($execfile,$exec);
      } catch (Exception $e) {
        $this->show('error: ' . $task['binArgs'][0] . $e->getMessage());
      }
    });
    $pid                 = $reserveProcess->start();
    $this->workers[$pid] = $reserveProcess;
    $this->show('reserve start...' . $pid);
    $this->registSignal();
  }

    //监控子进程
  public function registSignal()
  {
    //主进程收到退出信号，先把子进程结束，再结束自身
    \Swoole\Process::signal(SIGTERM, function ($signo) {
        $this->exit(true);
    });
    \Swoole\Process::signal(SIGUSR1, function ($signo) {
        $this->exit();
    });
    \Swoole\Process::signal(SIGCHLD, function ($signo) {
      while (true) {
        $ret = \Swoole\Process::wait(false);
        if ($ret) {
          $pid           = $ret['pid'];
          $childProcess = $this->workers[$pid];
          $info = "Worker Exit, kill_signal={$ret['signal']} PID={$pid} Worker count:". count($this->workers);
          $this->show($info);
          if ($this->status == 'running') {
            $this->show('Worker status: ' . $this->status);
            $new_pid           = $childProcess->start();
            $this->workers[$new_pid] = $childProcess;
            
            unset($this->workers[$pid]);
          }
          $this->show('Worker count: ' . count($this->workers));
        } else {
          break;
        }
      }
    });
  }
    /**
     * 设置进程名.
     *
     * @param mixed $name
     */
    protected function setProcessName($name)
    {
        //mac os不支持进程重命名
        if (function_exists('swoole_set_process_name') && PHP_OS != 'Darwin') {
            swoole_set_process_name($name);
        }
    }

    protected function checkMasterProcess($command)
    {
        // Get master process PID.
        $pidFile         = self::PID_FILE;
        $masterPid       = @file_get_contents($pidFile);
        //服务没有启动
        if (!$masterPid ) {
            //变成daemon pid会变
            \Swoole\Process::daemon(true, true);
            $this->ppid = getmypid();
            file_put_contents(self::PID_FILE, $this->ppid);
            return;
        }
        $this->ppid = getmypid();
        $masterIsAlive = $masterPid && @posix_kill($masterPid, 0);
        if ($this->ppid != $masterPid) {
          $this->show("MultiProcess[$masterPid] already running");
        }else{
          $this->show("MultiProcess[$masterPid] not run");
        }
    }

  /**
     * 主进程退出后，执行流程.
     *
     * @param bool $killChild 是否强杀子进程
     */
    private function exit($killChild=false)
    {
        @unlink($this->config['logPath'] . '/' . self::PID_FILE);
        $this->logger->log('收到退出信号,[' . $this->ppid . ']主进程退出');
        $this->status = 'stop';
        $this->logger->log('Worker status: ' . $this->status);
        //杀掉子进程
        $this->logger->log('Kill Worker count: ' . count($this->workers));
        //是否强制杀子进程
        if (true === $killChild) {
            foreach ($this->workers as $pid => $worker) {
                //平滑退出，用exit；强制退出用kill
                \Swoole\Process::kill($pid);
                //$worker->exit(0);
                unset($this->workers[$pid]);
                $this->logger->log('主进程收到退出信号,[' . $pid . ']子进程跟着退出');
                $this->logger->log('Worker count: ' . count($this->workers));
            }
        }
        exit();
    }

  public function show($info='',$data = '')
  {
    $time = date('Y-m-d H:i:s');
    if ($data) {
      $d = $this->decode($data);
      $info .="\tcmd:{$d['cmd']}\tparams:{$d['params']}";
    }
    echo "{$time}\t{$info}\n";
  }

  public function decode($r='')
  {
    return json_decode($r,true);
  }

  public function encode($cmd ='',$params = '')
  {
    $r['cmd'] = $cmd;
    $r['params'] = $params;
    return  json_encode($r);
  }
}