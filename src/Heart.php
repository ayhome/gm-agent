<?php
namespace ayhome\agent;

class Heart extends App
{
  public $process;
  public function __construct($process = '')
  {
    if ($process) $this->process = $process;
    if (!$this->process) return;
  }

  public function getInfo($process='')
  {
    if ($process) $this->process = $process;
    if (!$this->process) return;

    $list = explode(",", $this->process);
    $data = array();
    foreach ($list as $k) {
      $d = $this->processInfo($k);
      if ($d) {
        $data = array_merge($data,$d);
      }
    }
  }

  public function processInfo($name ='')
  {
    if (!$name) return;
    $cmd = "ps aux|grep {$name} | grep -v grep |awk '{print $1, $2, $6, $8, $9, $11}'";
    exec($cmd, $out);
    $info = array();
    foreach ($out as $v) {
      $line = explode(" ", $v);
      $vv =  array();
      $pid = $line[1];
      $outs = '';
      $cmd = "ls -l /proc/{$pid} |awk '{print $11}'";
      exec($cmd, $outs);

      $vv['user'] = $line[0];
      $vv['pid'] = $pid;
      $vv['rss'] = $line[2];
      $vv['stat'] = $line[3];
      $vv['start'] = $line[4];
      $vv['cwd'] = $outs[10];
      $vv['exe'] = $outs[12];
      $vv['command'] = $line[5];
      $info[] = $vv;
    }
    return $info;
  }
}