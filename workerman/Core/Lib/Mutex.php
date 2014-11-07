<?php
namespace Man\Core\Lib;
/**
 * 信号量
 */
class Mutex
{
    
    /**
     * 信号量
     * @var resource
     */
    private static $semFd = null;
    
    /**
     * 获取写锁
     * @return true
     */
    public static function get()
    {
        \Mutex::lock(self::getSemFd());
    }
    
    /**
     * 释放写锁
     * @return true
     */
    public static function release()
    {
        \Mutex::unlock(self::getSemFd());
    }
    
    /**
     * 获得SemFd
     */
    protected static function getSemFd()
    {
        if(!self::$semFd )
        {
            self::$semFd = \Mutex::create();
        }
        return self::$semFd;
    }
}
