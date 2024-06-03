<?php

namespace Cdek\Cache;

use Cdek\Loader;

class FileCache
{
    private static array $store;
    private string $file;

    public function __construct($fileName)
    {
        $this->file = $fileName;
    }

    public function getVars()
    {
        return self::$store[$this->file] ?? self::$store[$this->file] = require_once(Loader::getPluginPath() . DIRECTORY_SEPARATOR . $this->file);
    }

    public function putVars($vars)
    {
        if(empty($vars)){
            return;
        }

        $logFile = fopen( Loader::getPluginPath() . DIRECTORY_SEPARATOR . $this->file, 'w+');
        $content = '<?php return [';

        $this->recurseContent($content, $vars);
        $content .= '];';

        fwrite($logFile, $content);
        fclose($logFile);
    }

    private function recurseContent(&$content, $vars)
    {
        $countVars = count($vars);
        $i = 0;

        foreach ($vars as $key => $var){
            $i++;
            if(is_array($var)){
                $content .= '"' . $key . '" => [';
                $this->recurseContent($content, $var);
                $content .= ']';
            }else{
                $content .= '"' . $key . '" => "' . $var  . '"';
            }

            if($i < $countVars){
                $content .= ',';
            }

        }
    }
}
