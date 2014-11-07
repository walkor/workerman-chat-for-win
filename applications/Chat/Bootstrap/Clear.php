<?php
/**
 * workerman启动前的清除脚本
 * @author walkor <walkor@workerman.net>
 */
require_once __DIR__ . '/../Lib/Store.php';
require_once __DIR__ . '/../Config/Store.php';
require_once __DIR__ . '/../Lib/StoreDriver/File.php';

use \Lib\Store;

// 文件存储驱动，删除文件
if(\Config\Store::$driver = \Config\Store::DRIVER_FILE)
{
    Store::instance('gateway')->destroy();
}
// 其它存储驱动，删除老的gateway通讯接口
else
{
    $key = 'GLOBAL_GATEWAY_ADDRESS';
    Store::instance('gateway')->delete($key);
}