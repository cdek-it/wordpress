<?php

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Cache
{

    use Cdek\Exceptions\CdekApiException;
    use Cdek\Loader;

    class FileCache
    {
        private const CACHE_FILE_NAME = '.cache.php';
        private static array $store;

        public static function getVars(): ?array
        {
            if(!file_exists(Loader::getPluginPath() . DIRECTORY_SEPARATOR . self::CACHE_FILE_NAME)){
                return null;
            }

            return self::$store[self::CACHE_FILE_NAME] ?? self::$store[self::CACHE_FILE_NAME] = require_once(Loader::getPluginPath() . DIRECTORY_SEPARATOR . self::CACHE_FILE_NAME);
        }

        /**
         * @param array|null $vars
         *
         * @return void
         * @throws CdekApiException
         */
        public static function putVars(?array $vars): void
        {
            if($vars === null){
                return;
            }

            if(file_exists(Loader::getPluginPath() . DIRECTORY_SEPARATOR . self::CACHE_FILE_NAME)){
                if(!is_writable(Loader::getPluginPath() . DIRECTORY_SEPARATOR . self::CACHE_FILE_NAME)){
                    throw new CdekApiException('[CDEKDelivery] Failed check file rights',
                                               'cdek_error.cache.rights',
                                               ['path' => Loader::getPluginPath() . DIRECTORY_SEPARATOR . self::CACHE_FILE_NAME],
                                               true);
                }
            }else{
                if(!is_writable(Loader::getPluginPath())){
                    throw new CdekApiException('[CDEKDelivery] Failed check directory rights',
                                               'cdek_error.cache.rights',
                                               ['path' => Loader::getPluginPath()],
                                               true);
                }
            }


            $logFile = fopen( Loader::getPluginPath() . DIRECTORY_SEPARATOR . self::CACHE_FILE_NAME, 'w+');

            fwrite($logFile, '<?php return ' . var_export($vars, true) . ';' . PHP_EOL);
            fclose($logFile);
        }

        public static function clear(): void
        {
            unlink(Loader::getPluginPath() . DIRECTORY_SEPARATOR . self::CACHE_FILE_NAME);
        }
    }
}
