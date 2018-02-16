<?php

namespace NewstubeParser;

abstract class ParserController
{
    private $params;
    public $api;

    public function __construct($config)
    {
        $this->params = $config;
    }

    public function getParam($key)
    {
        if (array_key_exists($key, $this->params)) {
            return $this->params[$key];
        }
        throw new Exception(
            sprintf('Param "%s" does not exist.', $key)
        );
    }

    public function getItems()
    {
    }

    public function getItem($url)
    {
    }


    public function setApi($api)
    {
        $this->api = $api;
    }

    public function getMediaByExternalId($externalId)
    {
        return $this->api->GetMedia(0, $externalId);
    }

    public function getMediaByMediaId($mediaId)
    {
        return $this->api->GetMedia($mediaId, 0);
    }

    public function addMedia($item)
    {
        return $this->api->MediaAdd(
            $item['externalId'],
            $item['title'],
            $item['description'],
            date_format($item['date'], \DateTime::ISO8601),
            isset($item['referenceUrl']) ? $item['referenceUrl'] : false
        );
    }

    public function isNeedAddVideo($media)
    {
        return !isset($media->Video) && !isset($media->NextVideo) && $media->State != 20;
    }

    public function addMediaVideoByExternalId($extermalId, $videos)
    {
        if (!isset($videos) || empty($videos)) {
            return false;
        }

        return $this->api->MediaContentAdd(0, $extermalId, $videos);
    }

    public function getStartParsingDate()
    {
        $startParsingDate = $this->getParam('startParsingDate');
        return date_time_set(date_create_from_format('d.m.Y', $startParsingDate), 0, 0);
    }

    public function parse()
    {
        if (!isset($this->api)) {
            throw new Exception("Set api first");
        }

        $start = microtime(true); //Начало выполнения скрипта
        $startDate = $this->getStartParsingDate();

        $items = $this->getItems();
        foreach ($items as $item) {

            echo 'Parsing "' . $item['externalId'] . '"... ';

            //если дата меньше заявленой - пропускаем
            if (!$item['date'] || $item['date'] < $startDate) {
                echo 'Date for parsing too small' . PHP_EOL;
                continue;
            }

            //Проверяем есть ли ролик в базе. Если нет - добавляем
            if (!$media = $this->getMediaByExternalId($item['externalId'])) {
                $simpleMedia = $this->getItem($this->getParam('site') . $item['url']);
                if ($this->addMedia($simpleMedia)) {
                    //если добавляем видео
                    echo 'Adding video... ';

                    if ($simpleMedia && $this->addMediaVideoByExternalId($item['externalId'], $simpleMedia['videos'])) {
                        echo 'OK ';
                    } else {
                        echo 'ERROR ';
                    }
                } else {
                    echo 'ERROR ';
                }
            } else {
                //если ролик есть - проверяем есть ли видео
                if ($this->isNeedAddVideo($media)) {
                    $simpleMedia = $this->getItem($this->getParam('site') . $item['url']);
                    //если нет видео - добавляем
                    echo 'Adding video... ';

                    if ($simpleMedia && $this->addMediaVideoByExternalId($item['externalId'], $simpleMedia['videos'])) {
                        echo 'OK ';
                    } else {
                        echo 'ERROR ';
                    }
                } else {
                    echo 'All isset';
                }
            }

            echo PHP_EOL;
            //break;
        }

        $time = microtime(true) - $start;
        echo 'Script execution time = ' . number_format($time, 2) . ' seconds' . PHP_EOL;
    }

    public function test($args) 
    {
        preg_match('/^-(\w*)=?(.*)?$/', $args, $matches, PREG_OFFSET_CAPTURE);
        $method = $matches[1][0];
        $param = $matches[2][0];

        switch ($method) {
            case 'items':
                $items = $this->getItems();
                if (empty($items))
                    break;
                foreach ($items as $item) {
                    echo 'title = ' . $item['title'] . PHP_EOL;
                    echo 'url = ' . $item['url'] . PHP_EOL;
                    echo 'externalId = ' . $item['externalId'] . PHP_EOL;
                    echo 'date = ' . $item['date']->format(\DateTime::ISO8601) . PHP_EOL;
                    echo '----------' . PHP_EOL;
                }
                echo 'count items = ' . count($items) . PHP_EOL;
                break;
            case 'item':

                $item = $this->getItem($param);
                echo PHP_EOL;
                echo 'title = ' . $item['title'] . PHP_EOL;
                echo 'description = ' . $item['description'] . PHP_EOL;
                echo 'referenceUrl = ' . $item['referenceUrl'] . PHP_EOL;
                echo 'externalId = ' . $item['externalId'] . PHP_EOL;
                echo 'date = ' . $item['date']->format(\DateTime::ISO8601) . PHP_EOL;
                echo 'videos:' . PHP_EOL;
                foreach ($item['videos'] as $k=>$video) {
                    echo '    video-' . $k . ' = ' . $video . PHP_EOL;
                }
                echo PHP_EOL;

                break;
        }
    }
}
