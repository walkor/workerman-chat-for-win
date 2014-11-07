<?php
namespace Man\Core\Lib;
/**
 * 环境检查相关
 * 
* @author walkor <walkor@workerman.net>
 */
class Checker
{
    
    /**
     * 最长的workerName
     * @var integer
     */
    protected static $maxWorkerNameLength = 10;
    
    /**
     * 最长的user name
     * @var integer
     */
    protected static $maxUserNameLength = 10;
    
    /**
     * 最长的listen address
     * @var integer
     */
    protected static $maxListenLength = 10;
    
    /**
     * 最长的process count
     * @var integer
     */
    protected static $maxProcessCountLength = 9;
    
    /**
     * 检查扩展支持情况
     * @return void
     */
    public static function checkExtension()
    {
        // 扩展名=>是否是必须
        $need_map = array(
            'pthreads'   => true,
        );
    
        // 检查每个扩展支持情况
        $echo_ext_string = false;
        $pad_length = 26;
        foreach($need_map as $ext_name=>$must_required)
        {
            $suport = extension_loaded($ext_name);
            if($must_required && !$suport)
            {
                if(!$echo_ext_string)
                {
                    echo "----------------------- EXTENSION ------------------------------\n";
                    $echo_ext_string = true;
                }
                \Man\Core\Master::notice($ext_name. " [NOT SUPORT BUT REQUIRED] You have to install  $ext_name extension \tWorkerman start fail");
                exit('* ' . $ext_name. "  [NOT SUPORT BUT REQUIRED] \r\nYou have to install  $ext_name extension \r\n\r\nWorkerman start fail\r\n\r\n");
            }
    
            // 不支持
            if(!$suport)
            {
                if(!$echo_ext_string)
                {
                    echo "----------------------- EXTENSION ------------------------------\n";
                    $echo_ext_string = true;
                }
                echo '* ' , str_pad($ext_name, $pad_length), " [NOT SUPORT] \n";
            }
        }
    }
    
    /**
     * 检查禁用的函数
     * @return void
     */
    public static function checkDisableFunction()
    {
        // 可能禁用的函数
        $check_func_map = array(
            'stream_socket_server',
            'stream_socket_client',
        );
        if($disable_func_string = ini_get("disable_functions"))
        {
            $disable_func_map = array_flip(explode(',', $disable_func_string));
        }
        // 遍历查看是否有禁用的函数
        foreach($check_func_map as $func)
        {
            if(isset($disable_func_map[$func]))
            {
                \Man\Core\Master::notice("Function $func may be disabled\tPlease check disable_functions in php.ini \t Workerman start fail");
                exit("\nFunction $func may be disabled\nPlease check disable_functions in php.ini\n\nWorkerman start fail\n\n");
            }
        }
    }
    
    /**
     * 检查worker配置、worker语法错误等
     * @return void
     */
    public static function checkWorkersConfig()
    {
        foreach(Config::getAllWorkers() as $worker_name=>$config)
        {
            if(strlen($worker_name)>self::$maxWorkerNameLength)
            {
                self::$maxWorkerNameLength = strlen($worker_name);
            }
            if(isset($config['user']) && strlen($config['user']) > self::$maxUserNameLength)
            {
                self::$maxUserNameLength = strlen($config['user']);
            }
            if(isset($config['listen']) && strlen($config['listen']) > self::$maxListenLength)
            {
               self::$maxListenLength = strlen($config['listen']);
            }
        }
        $total_worker_count = 0;
        // 检查worker 
        echo "------------------------ WORKERS -------------------------------\n";
        echo "worker",str_pad('', self::$maxWorkerNameLength+2-strlen('worker')), "listen",str_pad('', self::$maxListenLength+2-strlen('listen')), "processes",str_pad('', self::$maxProcessCountLength+2-strlen('processes')),"","status\n";
        foreach (Config::getAllWorkers() as $worker_name=>$config)
        {
            echo str_pad($worker_name, self::$maxWorkerNameLength+2);
            
            if(isset($config['listen']))
            {
                echo str_pad($config['listen'], self::$maxListenLength+2);
            }
            else 
            {
                echo str_pad('none', self::$maxListenLength+2);
            }
            
            if(empty($config['start_workers']))
            {
                \Man\Core\Master::notice(str_pad($worker_name, 40)." [start_workers not set]\tWorkerman start fail");
                exit(str_pad('', self::$maxProcessCountLength+2)." [start_workers not set]\n\nWorkerman start fail\n");
            }
            
            echo str_pad(' '.$config['start_workers'], self::$maxProcessCountLength+2);
    
            $total_worker_count += $config['start_workers'];
    
            // 语法检查
            if(!$worker_file = \Man\Core\Lib\Config::get($worker_name.'.worker_file'))
            {
                \Man\Core\Master::notice("$worker_name not set worker_file in conf/conf.d/$worker_name.conf");
                echo" [not set worker_file] \n";
                continue;
            }
           
            echo " [OK] \n";
        }
    
        if($total_worker_count > \Man\Core\Master::SERVER_MAX_WORKER_COUNT)
        {
            \Man\Core\Master::notice("Number of worker processes can not be greater than " . \Man\Core\Master::SERVER_MAX_WORKER_COUNT . ".\tPlease check start_workers in " . WORKERMAN_ROOT_DIR . "config/main.php\tWorkerman start fail");
            exit("\nNumber of worker processes can not be greater than " . \Man\Core\Master::SERVER_MAX_WORKER_COUNT . ".\nPlease check start_workers in " . WORKERMAN_ROOT_DIR . "config/main.php\n\nWorkerman start fail\n");
        }
    
        echo "----------------------------------------------------------------\n";
    }
}
