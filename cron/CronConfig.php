<?php

/**
 * Created by PhpStorm.
 * User: Wailovet
 * Date: 2016/8/31
 * Time: 14:21
 */

namespace PHPCron\Config;
class CronConfig
{

    private $path = null;

    public function __construct()
    {
        $this->path = dirname(__FILE__) . '/../config.json';
        $this->init();

    }

    public function init()
    {
        if (!file_exists($this->path)) {
            file_put_contents($this->path, json_encode(array(
                "interval" => 1000
            )));
        }
    }

    public function clean()
    {
        unlink($this->path);
    }

    public function set($key, $val)
    {
        $data = $this->data();
        $data[$key] = $val;
        file_put_contents($this->path, json_encode($data));
    }

    public function get($key)
    {
        $data = $this->data();
        return $data[$key];
    }

    public function data()
    {
        $json = file_get_contents($this->path);
        $data = json_decode($json, true);
        return $data;
    }
}