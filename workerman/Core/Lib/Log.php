<?php
namespace Man\Core\Lib;
/**
 * 
 * 日志类
 * 
* @author walkor <walkor@workerman.net>
 */
class Log 
{
    /**
     * 能捕获的错误抛出的异常的错误码
     * @var int
     */
    const CATCHABLE_ERROR = 505;
    
    /**
     * 初始化
     * @return bool
     */
    public static function init()
    {
        return self::checkWriteable();
    }
    
    /**
     * 检查log目录是否可写
     * @return bool
     */
    public static function checkWriteable()
    {
        $ok = true;
        if(!is_dir(WORKERMAN_LOG_DIR))
        {
            // 检查log目录是否可读
            umask(0);
            if(@mkdir(WORKERMAN_LOG_DIR, 0777) === false)
            {
                $ok = false;
            }
            @chmod(WORKERMAN_LOG_DIR, 0777);
        }
        
        if(!is_readable(WORKERMAN_LOG_DIR) || !is_writeable(WORKERMAN_LOG_DIR))
        {
            $ok = false;
        }
        
        if(!$ok)
        {
            $pad_length = 26;
            Master::notice(WORKERMAN_LOG_DIR." Need to have read and write permissions\tWorkerman start fail");
            exit("------------------------LOG------------------------\n".str_pad(WORKERMAN_LOG_DIR, $pad_length) . " [NOT READABLE/WRITEABLE] \n\nDirectory ".WORKERMAN_LOG_DIR." Need to have read and write permissions\n\nWorkerman start fail\n\n");
        }
    }
    
    /**
     * 添加日志
     * @param string $msg
     * @return void
     */
    public static function add($msg)
    {
        $log_dir = WORKERMAN_LOG_DIR. '/'.date('Y-m-d');
        umask(0);
        // 没有log目录创建log目录
        if(!is_dir($log_dir))
        {
            mkdir($log_dir,  0777, true);
        }
        if(!is_readable($log_dir))
        {
            return false;
        }
        
        $log_file = $log_dir . "/server.log";
        file_put_contents($log_file, date('Y-m-d H:i:s') . " " . $msg . "\n", FILE_APPEND);
    }
}
