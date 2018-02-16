<?php


namespace NewstubeParser\parsers;

use NewstubeParser\ParserController;

use PHPHtmlParser\Dom;
use YoutubeDownloader\Provider\Youtube\Provider;
use Exception;

class YoutubeController extends ParserController
{
    public function getItems()
    {
        $result = [];

        $youtube = $this->curlGet('https://www.youtube.com/feeds/videos.xml?channel_id='.$this->getParam('youtubeChannelId'));
        $xml = simplexml_load_string($youtube, "SimpleXMLElement", LIBXML_NOCDATA);
        foreach ($xml->entry as $k => $v) {
            //print_r($v->children('yt',TRUE)->videoId);
            $result[] = [
                'url' => trim($v->link->attributes()->href),
                'title' => trim($v->children('media', true)->group->children('media', true)->title),
                'description' => trim($v->children('media', true)->group->children('media', true)->description),
                'date' => date_timezone_set(date_create_from_format('Y-m-d\TH:i:sO', trim($v->published)), timezone_open('Europe/Moscow')),
                'externalId' => trim($v->children('yt', true)->videoId),
            ];
        }
        
        if (empty($result)) {
            return false;
        }

        return $result;
    }


    public function getItem($video_id)
    {
        $youtube_provider = Provider::createFromOptions([]);
        $video_info = $youtube_provider->provide($video_id);
        return $video_info;
    }

    public function curlGet($url)
    {
        try {
            $ch = curl_init($url);
            if (! ini_get('open_basedir')) {
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            $curl_response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode != 200) {
                throw new Exception("response from ".$url." with code ".$httpCode);
            }

            if (false === $curl_response) {
                throw new Exception(curl_error($ch), curl_errno($ch));
            }
            return $curl_response;

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function curlDownload($url, $name)
    {
        try {
            $fp = fopen($name, 'w+');
            $ch = curl_init($url);
            //curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $curl_response = curl_exec($ch);            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode != 200) {
                throw new Exception("response from ".$url." with code ".$httpCode);
            }

            if (false === $curl_response) {
                throw new Exception(curl_error($ch), curl_errno($ch));
            }

            curl_close($ch);
            fclose($fp);

            return $curl_response;

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }


    public function uploadVideo($file, $mediaId)
    {
        return $this->api->addVideo($file, $mediaId, 0);
    }

    public function getExtension($type)
    {
        $ext_parst = explode('/', $type);
        return $ext_parst[1];
    }

    public function downloadBestStream($streams, $adaptive_streams, $videoId)
    {
        if (!$best_stream =  $this->getBestStream($streams, $adaptive_streams)) {
            return false;
        }

        if (is_array($best_stream)) {
            //download video
            $videoFileName = $videoId . '_video.'.$this->getExtension($best_stream['video']->getType());
            $videoFile = $this->downloadFile($best_stream['video']->getUrl(), $videoFileName);
            //download audio
            $audioFileName = $videoId . '_audio.'.$this->getExtension($best_stream['audio']->getType());
            $audioFile = $this->downloadFile($best_stream['audio']->getUrl(), $audioFileName);

            if (!$audioFile || !$videoFile) {
                return false;
            }

            return [
                'video' => $videoFile,
                'audio' => $audioFile,
            ];
        } else {
            $fileName = $videoId. '.' . $this->getExtension($best_stream->getType());
            $fullFile = $this->downloadFile($best_stream->getUrl(), $fileName);
            if (!$fullFile) {
                return false;
            }

            return $fullFile;
        }
    }

    public function downloadFile($url, $filename)
    {
        $file = $this->getParam('downloadDir') . $filename;

        //echo 'Downloading ' . $filename.'...';
        if ($this->curlDownload($url, $file)) {
            //echo 'DL_OK ';
            return $file;
        } else {
            //echo 'DL_ERROR ';
            return false;
        }
    }

    public function getBestStream($streams, $adaptive_streams)
    {
        $priorityVideoAudioItags = [
            22, //video+audio MP4 hd720
            18, //video+audio MP4 360p
            36, //video+audio 3GP 240p
        ];
    
        $priorityVideoItags = [
            137, //video MP4 1080p
            136, //video MP4 720p
            135, //video MP4 480p
            134, //video MP4 360p
            133, //video MP4 240p
        ];
    
        $priorityAudioItags = [
            141, //audio m4a 256k
            140, //audio m4a 128k
            139, //audio m4a 48k
        ];

        if (empty($streams)) {
            return false;
        }

        if ($adaptive_streams) {
            foreach ($adaptive_streams as $stream) {
                if (!isset($video)) {
                    foreach ($priorityVideoItags as $videoItag) {
                        if ($videoItag == $stream->getItag()) {
                            $video = $stream;
                            break;
                        }
                    }
                }
                
                if (!isset($audio)) {
                    foreach ($priorityAudioItags as $audioItag) {
                        if ($audioItag == $stream->getItag()) {
                            $audio = $stream;
                            break;
                        }
                    }
                }
    
                if (isset($video) && isset($audio)) {
                    return [
                        'video' => $video,
                        'audio' => $audio
                    ];
                }
            }
        }

        foreach ($streams as $stream) {
            foreach ($priorityVideoAudioItags as $videoAudioItag) {
                if ($videoAudioItag == $stream->getItag()) {
                    $videoAudio = $stream;
                    break;
                }
            }
            if (isset($videoAudio)) {
                return $videoAudio;
            }
        }
        
        return false;
    }

    public function combineVideoAudio($downloadingFile, $videoId)
    {
        $combinedFile = $this->getParam('downloadDir') . $videoId . '.mp4';
        $cmd = 'ffmpeg -i ' . $downloadingFile['video'] . ' -i ' .  $downloadingFile['audio'] . ' -c:v copy -c:a copy -y ' . $combinedFile . ' 2>&1';
        exec($cmd, $output);
        if (strpos(implode(' ', $output), 'Output #0, mp4') !== false || file_exists($combinedFile)) {
            return $combinedFile;
        }
        return false;
    }

    public function cleanDownloadDir(){
        $dir = $this->getParam('downloadDir');
        $files = glob($dir.'*'); // get all file names
        foreach($files as $file){ // iterate files
        if(is_file($file))
            unlink($file); // delete file
        }

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
            //пробуем добавить ролик
            if ($answerMediaId = $this->addMedia($item)) {
                //Если ролик добавился или уже существует - проверяем есть ли видео
                if($this->isNeedAddVideo($this->getMediaByMediaId($answerMediaId))) {
                    $video_info = $this->getItem($item['externalId']);
                    //если нет видео - загружаем
                    echo 'Downloading stream... ';
                    if ($downloadingFile = $this->downloadBestStream($video_info->getFormats(), $video_info->getAdaptiveFormats(), $item['externalId'])) {
                        echo 'OK ';
                        //если это разделенное видео - соединяем
                        if (is_array($downloadingFile)) {
                            echo 'Combining streams... ';
                            if ($downloadingFile = $this->combineVideoAudio($downloadingFile, $item['externalId'])) {
                                echo 'OK ';
                            } else {
                                echo 'ERROR ';
                            }
                        }
                        // отправляем файл
                        if ($downloadingFile) {
                            echo 'Upload video... ';
                            if ($this->uploadVideo($downloadingFile, $answerMediaId)) {
                                echo 'OK ';
                            } else {
                                echo 'ERROR ';
                            }
                        }
                    } else {
                        echo 'ERROR ';
                    }
                }
                else {
                    echo 'All isset';
                }
            }
            else {
                echo 'ERROR';
            }
            echo PHP_EOL;
        }

        $this->cleanDownloadDir();

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
                    echo 'description = ' . $item['description'] . PHP_EOL;
                    echo 'url = ' . $item['url'] . PHP_EOL;
                    echo 'externalId = ' . $item['externalId'] . PHP_EOL;
                    echo 'date = ' . $item['date']->format(\DateTime::ISO8601) . PHP_EOL;
                    echo '----------' . PHP_EOL;
                }
                echo 'count items = ' . count($items) . PHP_EOL;
                break;
            case 'item':

                $video_info = $this->getItem($param);
                $best_stream =  $this->getBestStream($video_info->getFormats(), $video_info->getAdaptiveFormats());

                echo PHP_EOL;
                echo 'title = ' . $video_info->getTitle() . PHP_EOL;
                echo 'best_stream:' . PHP_EOL;
                if (is_array($best_stream)) {                    
                    echo '    ' . $best_stream['video']->getType() . ' ' . $best_stream['video']->getQuality() . PHP_EOL;
                    echo '    videoUrl = ' . $best_stream['video']->getUrl() . PHP_EOL;

                    echo '    ' . $best_stream['audio']->getType() . ' ' . $best_stream['audio']->getQuality() . PHP_EOL;
                    echo '    audioUrl = ' . $best_stream['audio']->getUrl() . PHP_EOL;
                }
                else {
                    echo '    video-audio ' . $best_stream->getType() . ' ' . $best_stream->getQuality() . PHP_EOL;
                    echo '    video-audioUrl = ' . $best_stream->getUrl() . PHP_EOL;
                }
                
                echo PHP_EOL;

                break;
        }
    }
}
