<?php

/*
 * @作者：否子戈
 *
 * 用来实现本地的PHP定时任务，无需借助Liunx CronTab，而是借助生成本地文件，可以实现定时任务
 *
 */

namespace PHPCron\FILE;

use Exception;

date_default_timezone_set('PRC');

class Cron
{
    private $schedules_dir;


    function __construct()
    {
        $this->schedules_dir = (dirname(__FILE__) . '/../schedules');
    }

    // 判断任务是否存在
    function exists($name)
    {
        $file = $this->schedules_dir . '/' . $name . '.php';
        if (!file_exists($file)) return false;
        return true;
    }

    // 获取某个任务的信息 //
    function get($name = null)
    {
        // 为空是，获取任务列表
        if (empty($name)) {
            $schedules_files = $this->_scandir($this->schedules_dir);
            $schedules = array();
            if (!empty($schedules_files)) foreach ($schedules_files as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                $schedules[] = basename($file, '.json');
            }
            return $schedules;
        }
        // 否则获取对应的任务信息
        $file = $this->schedules_dir . '/' . $name . '.json';
        if (!file_exists($file)) return false; // 不存在该任务时，返回错误
        $file_content = file_get_contents($file);
        $schedule = json_decode($file_content, true);

        return $schedule;
    }

    function info()
    {
        $schedules = array();
        $schedules_files = $this->_scandir($this->schedules_dir);
        foreach ($schedules_files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $data = json_decode(file_get_contents($this->schedules_dir . "/{$file}"));

            if ($data) {
                $schedules[] = $data;
            }
        }
        return $schedules;
    }


    /**
     * 添加一个任务（添加到列表中，而不是马上执行）
     * @param $name
     * @param $interval
     * @param $url
     * @return bool|int
     */
    function add($name, $interval, $url)
    {
        if ($this->exists($name)) return false; // 如果已经存在该任务了，就只能使用update，而不能使用add
        return $this->update($name, $interval, $url);
    }

    /**
     * 更新任务，当任务不存在时，添加这个任务
     * @param $name
     * @param $interval
     * @param $url
     * @return bool|int
     */
    function update($name, $interval, $url)
    {
        $file = $this->schedules_dir . '/' . $name . '.json';
        $gmt_time = microtime(true);
        $schedule = array();
        if (file_exists($file) && filemtime($file) > $gmt_time - 1) return 0;


        $schedule['name'] = $name;
        $schedule['last_run_time'] = 0;
        $schedule['last_success_time'] = 0;
        $schedule['interval'] = $interval;
        $schedule['url'] = $url;
        $schedule = json_encode($schedule);

        // 创建任务信息文件
        file_put_contents($file, $schedule);
        return true;
    }

    // 删除一个任务
    function delete($name)
    {
        $file = $this->schedules_dir . '/' . $name . '.json';
        @unlink($file);
    }

    // 立即执行一个任务，执行时，就会更新任务信息
    function run($name)
    {
        $file = $this->schedules_dir . '/' . $name . '.json';

        if (!file_exists($file)) return false; // 不存在该任务时，返回错误
        $gmt_time = microtime(true);
        if (filemtime($file) > $gmt_time - 1) return 0; // 如果文件被极速写入，为了防止文件被同时更改，则返回错误
        $schedule = $this->get($name);
        if (empty($schedule)) {
            return false;
        }

        $time = time();

        if ($time * 1000 - intval($schedule['last_run_time']) * 1000 >= intval($schedule['interval'])) {// 执行url
            $url = $schedule['url'];
            list($error_code, $error_msg) = $this->selfSock($url);

            // 记录这次执行的情况
            $msg = date('Y-m-d H:i:s') . " 执行任务：$name 结果：$error_code $error_msg\n";
            $this->_log($msg);
            $schedule['last_run_time'] = $time;
        }


//
//        // 更新schedule
//        $schedule['last_run_time'] = time();

        $schedule = json_encode($schedule);
        file_put_contents($file, $schedule);
        return true;
    }



    /*
     * 公有函数，并非cron内含动作
     */
    // 远程请求（不获取内容）函数
    function _sock($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $port = $port ? $port : 80;
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) $path .= '?' . $query;
        if ($scheme == 'https') {
            $host = 'ssl://' . $host;
        }
        if ($fp = @fsockopen($host, $port, $error_code, $error_msg, 5)) {
            stream_set_blocking($fp, 0);//开启了手册上说的非阻塞模式
            $header = "GET $path HTTP/1.1\r\n";
            $header .= "Host: $host\r\n";
            $header .= "Connection: Close\r\n\r\n";//长连接关闭
            fwrite($fp, $header);
            fclose($fp);
        }
        return array($error_code, $error_msg);
    }


    function selfSock($path)
    {

        $port = intval($_SERVER["SERVER_PORT"]);;
        $host = $_SERVER['SERVER_NAME'];


        if ($fp = @fsockopen("127.0.0.1", $port, $error_code, $error_msg, 5)) {
            stream_set_blocking($fp, 0);//开启了手册上说的非阻塞模式
            $header = "GET $path HTTP/1.1\r\n";
            $header .= "Host: $host\r\n";
            $header .= "Connection: Close\r\n\r\n";//长连接关闭
            fwrite($fp, $header);
            fclose($fp);
        }
        return array($error_code, $error_msg);
    }

    // 记录log
    function _log($msg)
    {
        $file = dirname(__FILE__) . '/cron.log';
        $fp = fopen($file, 'a');
        fwrite($fp, $msg);
        fclose($fp);
    }

    // 浏览目录
    private function _scandir($dir)
    {
        if (function_exists('scandir')) {
            return scandir($dir);
        } else {
            $handle = @opendir($dir);
            $arr = array();
            while (($arr[] = @readdir($handle)) !== false) {

            }
            @closedir($handle);
            $arr = array_filter($arr);
            return $arr;
        }
    }
}