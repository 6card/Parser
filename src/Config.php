<?php

namespace NewstubeParser;

use Exception;

class Config
{
    private $data = [];

    private function __construct(array $config)
    {
        $this->data = $config;
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        throw new Exception(
            sprintf('config param "%s" does not exist.', $key)
        );
    }

    public static function createFromFiles($default, $custom)
    {
        $default_config = require($default);
        $custom_config = [];
        if (file_exists($custom)) {
            $custom_config = require($custom);
        } elseif ($custom !== null) {
            exit("The configuration file does not exist: $custom\n");
        }

        $config = array_replace_recursive($default_config, $custom_config);
        return new self($config);
    }
}
