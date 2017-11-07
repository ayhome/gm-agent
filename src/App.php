<?php
namespace ayhome\agent;

use Hprose\Swoole\Client;
class App 
{

  public $host = '127.0.0.1';
  public $port = 9911;

  public function __construct()
  {
    $cmd = new \ayhome\cli\Command();
  }

  public function start($value='')
  {
    $cli = new \Hprose\Swoole\Client("tcp://{$this->host}:{$this->port}");

    
    \swoole_timer_tick(1000, function() use($cli){

      $cli->tickTask()->then(function($r) use ($cli) {
        foreach ($r as $k) {
          $k['title'] = '接收成功';
          $cli->taskLog($k);
        }
        return $r;
      })->then(function($r) use ($cli) {
        foreach ($r as $k) {
          $k['title'] = '执行任务';
          $cli->taskLog($k);
        }
        return $r;
      })->then(function($r) use ($cli) {
        foreach ($r as $k) {
          // $this->execTask($k,$cli);
        }
      });

    });

    
  }

  function execTask($task = '',$cli = '')
  {
    $process = new \Swoole\Process(function ($worker) use ($task,$cli) {
      try {
        $exec = $task["execute"];
        @$worker->name($exec . "#" . $task["id"]);
        $exec = explode(" ", trim($exec));
        $execfile = array_shift($exec);
        $retmsg = $worker->exec($execfile,$exec);

        // $process->write('Hello');
      } catch (Exception $e) {
        $task['title'] = '任务执行';
        $task['status'] = -1;
        $task['msg'] = $e->getMessage();
        $cli->taskLog($task);
      }
    },true);
    $pid  = $process->start();

    $task['title'] = '任务执行';
    $task['status'] = 1;
    $r = $cli->taskLog($task)->then(function($r){
      // print_r($r);
    });
  }


  

  
}