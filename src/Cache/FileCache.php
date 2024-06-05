<?php

namespace Cdek\Cache;

use Cdek\Loader;

class FileCache
{
    const CACHE_FILE_NAME = '.cache.php';
    private static array $store;
    private string $file;

    public function __construct($fileName)
    {
        $this->file = $fileName;
    }

    public function getVars()
    {
        if(!file_exists(Loader::getPluginPath() . DIRECTORY_SEPARATOR . $this->file)){
            return null;
        }

        return self::$store[$this->file] ?? self::$store[$this->file] = require_once(Loader::getPluginPath() . DIRECTORY_SEPARATOR . $this->file);
    }

    public function putVars($vars)
    {
        if(empty($vars)){
            return;
        }

        $logFile = fopen( Loader::getPluginPath() . DIRECTORY_SEPARATOR . $this->file, 'w+');
        $content = '<?php return ' . var_export($vars, true) . ';' . PHP_EOL;

        fwrite($logFile, $content);
        fclose($logFile);
    }
}
