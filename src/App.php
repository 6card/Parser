<?php

namespace NewstubeParser;

use NewstubeParser\Config;
use NewstubeParser\newstube\NewstubeApi;
use Exception;

class App
{
    private $config;
    private $parser;
    private $params;

    public function __construct(string $config_dir, string $default = 'default.php')
    {
        if (isset($_SERVER['argv'])) {
            $this->params = $_SERVER['argv'];
            array_shift($this->params);
        } else {
            $this->params = [];
        }

        if (empty($this->params) || (isset($this->params[1]) && !isset($this->params[2]))) {
            exit('Use "parser <config> {[command] -param=argument}"' . PHP_EOL);
        }

        $this->config = Config::createFromFiles($config_dir . $default, $config_dir . $this->params[0] . '.php');
        
        $parserConfig = $this->config->get('parser');

        if (isset($parserConfig['class'])) {
            $class = $parserConfig['class'];
            unset($parserConfig['class']);
        } else {
            $class = $this->config->get('controllerNamespace') . ucfirst($this->params[0]) . 'Controller';
        }

        $this->parser = new $class($parserConfig);
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getParser()
    {
        return $this->parser;
    }


    public function run()
    {
        $username = $this->getConfig()->get('username');
        $password = $this->getConfig()->get('password');
        $channelId = $this->getConfig()->get('channelId');

        $newstube = new NewstubeApi($username, $password, $channelId);
        $newstube->Auth()
            or exit("Login failed\n");

        $this->getParser()->setApi($newstube);

        if (isset($this->params[1])) {
            $method = $this->params[1];
            $argument = $this->params[2];
            $this->getParser()->$method($argument);
        }
        else {
            $this->getParser()->parse();
        }
        //
    }
}
