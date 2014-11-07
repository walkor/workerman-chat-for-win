<?php 
namespace Man\Core;

if(!defined('WORKERMAN_ROOT_DIR'))
{
    define('WORKERMAN_ROOT_DIR', realpath(__DIR__."/../../")."/");
}

require_once WORKERMAN_ROOT_DIR . 'Core/Lib/Checker.php';
require_once WORKERMAN_ROOT_DIR . 'Core/Lib/Config.php';
require_once WORKERMAN_ROOT_DIR . 'Core/Lib/Task.php';
require_once WORKERMAN_ROOT_DIR . 'Core/Lib/Mutex.php';
require_once WORKERMAN_ROOT_DIR . 'Core/Lib/Log.php';
require_once WORKERMAN_ROOT_DIR . 'Core/Events/Select.php';
require_once WORKERMAN_ROOT_DIR . 'Core/SocketWorker.php';

/**
 * 
 * 主进程
 * 
 * @package Core
 * 
* @author walkor <walkor@workerman.net>
 * <b>使用示例:</b>
 * <pre>
 * <code>
 * Man\Core\Master::run();
 * <code>
 * </pre>
 * 
 */
class Master
{
    /**
     * 版本
     * @var string
     */
    const VERSION = '2.1.4-mt';
    
    /**
     * 服务名
     * @var string
     */
    const NAME = 'WorkerMan';
    
    /**
     * 服务状态 启动中
     * @var integer
     */ 
    const STATUS_STARTING = 1;
    
    /**
     * 服务状态 运行中
     * @var integer
     */
    const STATUS_RUNNING = 2;
    
    /**
     * 服务状态 关闭中
     * @var integer
     */
    const STATUS_SHUTDOWN = 4;
    
    /**
     * 服务状态 平滑重启中
     * @var integer
     */
    const STATUS_RESTARTING_WORKERS = 8;
    
    /**
     * 整个服务能够启动的最大进程数
     * @var integer
     */
    const SERVER_MAX_WORKER_COUNT = 5000;
    
    /**
     * 服务的状态，默认是启动中
     * @var integer
     */
    protected static $serverStatus = self::STATUS_STARTING;
    
    /**
     * 用来监听端口的Socket数组，用来fork worker使用
     * @var array
     */
    protected static $listenedSockets = array();
    
    /**
     * master进程pid
     * @var integer
     */
    protected static $masterPid = 0;
    
    /**
     * server统计信息 ['start_time'=>time_stamp, 'worker_exit_code'=>['worker_name1'=>[code1=>count1, code2=>count2,..], 'worker_name2'=>[code3=>count3,...], ..] ]
     * @var array
     */
    protected static $serverStatusInfo = array(
        'start_time' => 0,
        'worker_exit_code' => array(),
    );
    
    /**
     * 线程池
     * @var array
     */
    protected static $threads = array();
    
    /**
     * 是否有线程终止了
     * @var bool
     */
    protected static $threadIsTerminated = false;
    
    /**
     * 服务运行
     * @return void
     */
    public static function run()
    {
        // 输出信息
        self::notice("Workerman is starting ...", true);
        // 初始化
        self::init();
        // 检查环境
        self::checkEnv();
        // 执行各个项目启动前脚本
        self::beforeStart();
        // 创建监听套接字
        self::createSocketsAndListen();
        // 创建worker进程
        self::createWorkers();
        // 输出信息
        self::notice("Workerman start success ...", true);
        // 标记sever状态为运行中...
        self::$serverStatus = self::STATUS_RUNNING;
        // 主循环
        self::loop();
    }
    
    /**
     * 初始化 配置、进程名、共享内存、消息队列等
     * @return void
     */
    public static function init()
    {
        // 获取配置文件
        $config_path = Lib\Config::$filename;
    }
    
    /**
     * 检查环境配置
     * @return void
     */
    public static function checkEnv()
    {
        // 检查函数禁用情况
        Lib\Checker::checkDisableFunction();
        
        // 检查log目录是否可读
        Lib\Log::init();
        
        // 检查扩展
        Lib\Checker::checkExtension();
        
        // 检查配置和语法错误等
        Lib\Checker::checkWorkersConfig();
    }
    
    /**
     * 启动前执行各个配置中的前置脚本
     * 一般是用来清理数据
     */
    public static function beforeStart()
    {
        foreach (Lib\Config::getAllWorkers() as $worker_name=>$config)
        {
            $hook_file = isset($config['before_start']) ? $config['before_start'] : '';
            if($hook_file && is_file($hook_file))
            {
                require $hook_file;
            }
        }
    }
    
    
    /**
     * 获取主进程pid
     * @return int
     */
    public static function getMasterPid()
    {
        return self::$masterPid;
    }
    
    /**
     * 根据配置文件，创建监听套接字
     * @return void
     */
    protected static function createSocketsAndListen()
    {
        // 循环读取配置创建socket
        foreach (Lib\Config::getAllWorkers() as $worker_name=>$config)
        {
            if(isset($config['listen']))
            {
                $flags = substr($config['listen'], 0, 3) == 'udp' ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
                $error_no = 0;
                $error_msg = '';
                // 创建监听socket
                self::$listenedSockets[$worker_name] = stream_socket_server($config['listen'], $error_no, $error_msg, $flags);
                if(!self::$listenedSockets[$worker_name])
                {
                    Lib\Log::add("can not create socket {$config['listen']} info:{$error_no} {$error_msg}\tServer start fail");
                    exit("\n\033[31;40mcan not create socket {$config['listen']} info:{$error_no} {$error_msg}\033[0m\n\n\033[31;40mServer start fail\033[0m\n\n");
                }
            }
        }
    }
    
    
    /**
     * 根据配置文件创建Workers
     * @return void
     */
    protected static function createWorkers()
    {
        require_once WORKERMAN_ROOT_DIR . 'Core/ThreadWorker.php';
        $workers = Lib\Config::getAllWorkers();
        foreach($workers as $worker_name=>$config)
        {
            $main_socket = isset(self::$listenedSockets[$worker_name]) ? self::$listenedSockets[$worker_name] : null;
            $worker_file = \Man\Core\Lib\Config::get($worker_name.'.worker_file');
            self::$threads[$worker_name] = new \Pool($config['start_workers'], '\Man\Core\Worker', array($main_socket, $worker_file, $worker_name));
            for($i=0; $i<$config['start_workers'];$i++)
            {
                self::$threads[$worker_name]->submit(new ThreadWorker());
            }
        }
    }
    
    /**
     * 检查是否有线程退出了
     * @param thread $work
     */
    public static function check($work)
    {
        self::$threadIsTerminated = false;
        if($work->isTerminated())
        {
            self::$threadIsTerminated = true;
            return true;
        }
        return false;
    }
    
    /**
     * 主进程主循环 主要是监听子进程退出、服务终止、平滑重启信号
     * @return void
     */
    public static function loop()
    {
         while(1)
        {
            usleep(100000);
            foreach(self::$threads as $worker_name => $pool)
            {
                self::$threads[$worker_name]->collect(array('\Man\Core\Master', 'check'));
                if(self::$threadIsTerminated)
                {
                    self::$threads[$worker_name]->submit(new ThreadWorker());
                } 
            }
        } 
    }
    
    /**
     * 停止服务
     * @return void
     */
    public static function stop()
    {
        exit(0);
    }
    
    /**
     * notice,记录到日志
     * @param string $msg
     * @param bool $display
     * @return void
     */
    public static function notice($msg, $display = false)
    {
        Lib\Log::add("Server:".$msg);
        if($display)
        {
            if(self::$serverStatus == self::STATUS_STARTING)
            {
                echo($msg."\n");
            }
        }
    }
}

