<?php

namespace Cdek\Cache;

use Cdek\Exceptions\CdekApiException;
use Cdek\Loader;

class FileCache
{
    const CACHE_FILE_NAME = '.cache.php';
    private static array $store;
    private string $file;

    public function __construct($fileName = self::CACHE_FILE_NAME)
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

        if(!is_writable(Loader::getPluginPath())){
            throw new CdekApiException('[CDEKDelivery] Failed check directory rights',
                                       'cdek_error.cache.rights',
                                       ['path' => Loader::getPluginPath()],
                                       true);
        }

        $arPath = explode(DIRECTORY_SEPARATOR, $this->file);
        unset($arPath[count($arPath) - 1]);

        if(!is_writable(Loader::getPluginPath() . implode(DIRECTORY_SEPARATOR, $arPath))){
            throw new CdekApiException('[CDEKDelivery] Failed check directory rights',
                                       'cdek_error.cache.rights',
                                       ['path' => Loader::getPluginPath() . implode(DIRECTORY_SEPARATOR, $arPath)],
                                       true);
        }

        $logFile = fopen( Loader::getPluginPath() . DIRECTORY_SEPARATOR . $this->file, 'w+');

        $content = '<?php return ' . var_export($vars, true) . ';' . PHP_EOL;

        fwrite($logFile, $content);
        fclose($logFile);
    }

    public function clear()
    {
        unlink(Loader::getPluginPath() . DIRECTORY_SEPARATOR . $this->file);
    }
}
