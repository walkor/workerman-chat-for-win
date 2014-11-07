<?php 
namespace Man\Core;

/**
 * 线程Worker类封装
 * @author <walkor@workerman.net>
 */
class Worker extends \Worker
{
    /**
     * 监听socket
     * @var resource
     */
    public $mainSocket = null;
    
    /**
     * worker入口文件
     * @var string
     */
    protected $workerFile = '';
    
    /**
     * 服务名
     * @var string
     */
    protected $serviceName = '';
    
    /**
     * 构造函数
     * @param resource $main_socket
     * @param string $worker_file
     * @param string $service_name
     */
    public function __construct($main_socket, $worker_file, $service_name)
    {
        $this->mainSocket = $main_socket;
        $this->workerFile = $worker_file;
        $this->serviceName = $service_name;
    }
}
/**
 * 抽象ThreadWorker类
 * 必须实现run方法
* @author walkor <walkor@workerman.net>
*/
class ThreadWorker extends \Thread
{
     /**
     * 监听socket，实际上是从Worker类传递来的
     * @var resource
     */
    public $mainSocket = null;
    
    /**
     * 运行线程
     */
    public function run()
    {
        date_default_timezone_set('Asia/Shanghai');
        
        // 保存一个副本
        $this->mainSocket = $this->worker->mainSocket;
        
        // 改变当前工作目录
        chdir(WORKERMAN_ROOT_DIR);
        
        // 重新载入一次配置
        \Man\Core\Lib\Config::reload();
        
        // 载入入口文件
        require_once $this->worker->workerFile;
        
        // 入口文件类名
        $class_name = basename($this->worker->workerFile, '.php');
        
        // 实例化入口类
        $worker = new $class_name($this->worker->serviceName);
        
        // 如果该worker有配置监听端口，则将监听端口的socket传递给子进程
        if($this->mainSocket)
        {
            $worker->setListendSocket($this->mainSocket);
        }
        
        // 使worker开始服务
        $worker->start();
    }
}



