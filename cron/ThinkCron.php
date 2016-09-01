<?php
require(dirname(__FILE__) . '/Cron.FILE.Class.php');
require(dirname(__FILE__) . '/CronConfig.php');
use PHPCron\Config\CronConfig;
use PHPCron\FILE\Cron as CronFILE;

class ThinkCron
{
    private $stop_file;

    public function __construct()
    {
        ignore_user_abort(true);
        set_time_limit(0);
        session_write_close();

        $this->stop_file = dirname(__FILE__) . '/../stop';
        if (!is_dir(dirname(__FILE__) . '/../schedules')) {
            mkdir(dirname(__FILE__) . '/../schedules');
        }
    }

    public static function getConfig()
    {
        $cronConfig = new CronConfig();
        $config = $cronConfig->data();
        return $config;
    }

    public function clean()
    {
        $this->stop();
        $cronConfig = new CronConfig();
        $cronConfig->clean();
        $cronConfig->init();
    }

    public function isRun()
    {
        $cronConfig = new CronConfig();
        $config = $cronConfig->data();
        return !empty($config['run_ing']);
    }

    public function stop()
    {
        file_put_contents($this->stop_file, "1");
        for ($i = 0; $i < 12; $i++) {
            if (!$this->isRun()) {
                return true;
            }
            sleep(1);
        }
        unlink($this->stop_file);
        return false;
    }

    public function restart()
    {
        if ($this->stop()) {
            $this->start();
        }
        return false;
    }

    public function mainInterval($i)
    {
        if (intval($i) > 10000 || intval($i) < 100) {
            throw new Exception("主线程运行间隔在100-10000！");
        }
        $cronConfig = new CronConfig();
        $cronConfig->set("interval", $i);
        return $this;
    }

    public function mainCount($i)
    {
        $cronConfig = new CronConfig();
        $cronConfig->set("count", $i);
        return $this;
    }

    public static function taskAdd($name, $interval, $url)
    {
        $Cron = new CronFILE();
        return $Cron->add($name, $interval, $url);
    }

    public static function taskUpdate($name, $interval, $url)
    {
        $Cron = new CronFILE();
        return $Cron->update($name, $interval, $url);
    }

    public static function taskDelete($name)
    {
        $Cron = new CronFILE();
        $Cron->delete($name);
    }

    public static function task()
    {
        $Cron = new CronFILE();
        return $Cron->info();
    }

    public function start()
    {

        $Cron = new CronFILE();
        $cronConfig = new CronConfig();

        $config = $cronConfig->data();
        $loop = $config['interval'];
        if (intval($loop) > 10000) {
            throw new Exception("主线程运行间隔不能大于10秒！");
        }

        $count = intval($config['count']);


        if ($this->isRun()) {
            throw new Exception("运行中...");
        }

        $k = 0;
        do {
            $config = $cronConfig->data();
            $schedule = $Cron->get();
            $cronConfig->set("run_ing", 1);

            for ($i = 0; $i < count($schedule); $i++) {
                $item = $schedule[$i];
                !empty($item) && $Cron->run($item);
            }

            $loop = $config['interval'];
            if (intval($loop) < 100) break; // 如果循环的间隔为零，则停止
            $cronConfig->set("last_time", time());
            sleep($loop / 1000);

            if (file_exists($this->stop_file)) {
                unlink($this->stop_file);
                $cronConfig->set("run_ing", 0);
                break;
            }

        } while ($count == 0 || $k++ < $count);


        $cronConfig->set("run_ing", 0);
    }
}


