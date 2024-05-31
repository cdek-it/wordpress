<?php

namespace Cdek\Cache;

use Cdek\Loader;

class FileCache
{
    const FILE_EXT = '.php';

    public static  $enable = true;
    public static  $path = '/cache';
    private static $keys = [];
    private string $file;

    public function __construct($fileName)
    {
        $this->file = $fileName;
    }
    public static function get($name)
    {
        if (self::$enable) {
            $file = Loader::getPluginPath() . self::$path . '/' . $name . self::FILE_EXT;
            if (file_exists($file)) {
                return file_get_contents($file);
            } else {
                self::$keys[] = $name;
                return false;
            }
        } else {
            return '';
        }
    }

    public function getVars()
    {
        return require_once(Loader::getPluginPath() . '/' . $this->file . self::FILE_EXT);
    }

    public function putVars($vars)
    {
        if(empty($vars)){
            return;
        }

        $logFile = fopen( Loader::getPluginPath() . '/' . $this->file . self::FILE_EXT, 'w+');
        $content = '<?php return [';

        $this->recurseContent($content, $vars);
        $content .= '];';

        fwrite($logFile, $content);
        fclose($logFile);
    }

    private function recurseContent(&$content, $vars)
    {
        foreach ($vars as $key => $var){
            if(is_array($var)){
                $content .= '"' . $key . '" => [';
                $this->recurseContent($content, $var);
                $content .= '],';
            }else{
                $content .= '"' . $key . '" => "' . $var  . '",';
            }

        }
    }

    public static function set($content)
    {
        if (self::$enable) {
            $name = array_pop(self::$keys);
            $dir  = Loader::getPluginPath() . self::$path . '/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            file_put_contents($dir . '/' . $name . self::FILE_EXT, $content);
        }

        return $content;
    }

    public static function begin($name)
    {
        if ($content = self::get($name)) {
            echo $content;
            return false;
        } else {
            ob_start();
            return true;
        }
    }

    public static function end()
    {
        echo self::set(ob_get_clean());
    }

    public static function clear()
    {
        $dir = Loader::getPluginPath() . self::$path;
        foreach (glob($dir . '/*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
