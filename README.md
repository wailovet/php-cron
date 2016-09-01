# php-cron
实现PHP Cron，也就是PHP定时任务，通过本地文件记录schedules，然后通过fsockopen实现非阻塞式的后台访问对应的url来实现定时任务，通过sleep实现定时，~~如果错过任务，则通过用户访问来执行该任务~~ 

## 文件目录
* cron Cron类，命名空间PHPCron，里面实现了任务操作，如读取、添加、更新、删除~~、激活、运行~~等
	- Cron.Class.php 类
	- CronConfig.php 类 对主线程的配置操作
	- ~~cron.php 用来激活和调用的入口文件~~  ThinkCron.php 对主线程的各种操作
	- cron.log 用来记录动作的log文件
* schedules 任务列表，添加的任务会以~~"任务名.php"~~ "任务名.json"的格式保存在这个文件夹内，任务信息将会序列化保存，反序列化后就是一个数组
* ~~demo 用来演示使用Cron的效果文件~~


## 使用

> 初始化

```php
use ThinkCron;
$thinkCron = new ThinkCron();
```

## 主线程

> 启动

```php
$thinkCron->start();
```
> 停止

```php
$thinkCron->stop();
```
> 运行状态

```php
$thinkCron->isRun();
```
> 重置配置

```php
$thinkCron->clean();
```

## 任务操作

> 任务列表

```php
ThinkCron::task();
```
> 添加

```php
ThinkCron::taskAdd($name, $interval, $path); //$interval 单位ms
```
> 修改

```php
ThinkCron::taskUpdate($name, $interval, $path); 
```
> 删除

```php
ThinkCron::taskDelete($name);
```


 

