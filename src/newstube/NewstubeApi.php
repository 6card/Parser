<?php

namespace NewstubeParser\newstube;

use Exception;

class NewstubeApi
{
    public $apiUrl = 'https://api.newstube.ru/v2/';

    private $_login;
    private $_password;
    private $_sessionId;
    private $_channelId;

    public function __construct($login, $password, $channelId)
    {
        $this->_login = $login;
        $this->_password = $password;
        $this->_channelId = $channelId;
    }

    private function respondHandler($response)
    {
        if (!$response) {
            return false;
        }
        $data = json_decode($response);

        try {
            if ($data->Success === false) {
                if (isset($data->Message)) {
                    throw new Exception($data->Message->Text, $data->Message->Id);
                }
            }
        } catch (Exception $e) {
            echo 'Error message: "' . $e->getMessage() . '" with code: #' . $e->getCode() . PHP_EOL;
        }

        if (!isset($data) || !isset($data->Data) || empty((array)$data->Data)) {
            return false;
        }
        
        return $data->Data;
    }

    public function Auth()
    {
        if (!$this->_login || !$this->_password) {
            return false;
        }

        if (($data = $this->respondHandler($this->curlPost('Auth/Login', ["UserName" => $this->_login, "Password" => $this->_password]))) != false) {
            $this->_sessionId = $data->SessionId;
            return true;
        }
        
        return false;
    }

    public function PageCount($stateId, $title)
    {
        $params = [
            'SessionId' => $this->_sessionId,
            'channelId' => $this->_channelId,
            'stateId' => $stateId,
            'title' => $title
        ];
        return $this->respondHandler($this->curlGet('Media/MediasPageCount', $params));
    }

    public function ExternalIdToMediaId($externalId)
    {
        $params = [
            'ExternalId' => $externalId,
            'SessionId' => $this->_sessionId
        ];
        return $this->respondHandler($this->curlGet('Media/ExternalIdToMediaId', $params));
    }

    public function MediaAdd($externalId, $title = '', $description = '', $shootDate, $referenceUrl = false)
    {
        $params = [
            'Data' => [
                'ChannelId' => $this->_channelId,
                'ExternalId' => $externalId,
                'Title' => $title,
                'Description' => $description,
                'ShootDate' => $shootDate
            ],
            'SessionId' => $this->_sessionId
        ];

        /*
        if (!empty($videoUrls)) {
            $params['Data']['VideoUrls'] = $videoUrls;
        }
        */

        if ($referenceUrl) {
            $params['Data']['ReferenceUrl'] = $referenceUrl;
        }

        //fix for isset media
        $content = $this->curlPost('Media/MediaAdd', $params);
        $data = json_decode($content);
        if (isset($data->Message->Id) && $data->Message->Id == 5) {
            return $data->Data;
        }
  
        return  $this->respondHandler($content);
    }

    public function GetMedia($mediaId, $externalId)
    {
        $params = [
            'mediaId' => $mediaId,
            'ExternalId' => $externalId,
            'SessionId' => $this->_sessionId
        ];

        //fix for isset media
        $content = $this->curlGet('Media/GetMedia', $params);
        $data = json_decode($content);
        if (isset($data->Message->Id) && $data->Message->Id == 2) {
            return false;
        }

        return $this->respondHandler($content);
    }

    public function MediaContentAdd($mediaId, $externalId, $videoUrls)
    {
        $params = [
            'MediaId' => $mediaId,
            'ExternalId' => $externalId,
            'VideoUrls' => $videoUrls,
            'SessionId' => $this->_sessionId
        ];
        return $this->respondHandler($this->curlPost('Media/MediaContentAdd', $params));
    }

    public function UploadStart($mediaId, $externalId)
    {
        $params = [
            'MediaId' => $mediaId,
            'ExternalId' => $externalId,
            'SessionId' => $this->_sessionId
        ];

        $content = $this->curlPost('VideoUpload/Start', $params);
        return $this->respondHandler($content);
    }

    public function UploadDataPortion($uploadSessionId, $position, $data)
    {
        $url = 'VideoUpload/UploadData?uploadSessionId='.$uploadSessionId.'&position='.$position;
        
        $content = $this->curlPost($url, false, $data, false);
        return $this->respondHandler($content);
    }

    public function UploadComplete($uploadSessionId)
    {
        $params = [
            'UploadSessionId' => $uploadSessionId
        ];
        $content = $this->curlPost('VideoUpload/Complete', $params);
        return $content;
    }

    /*
    function AuthCheck() {
        $url = 'Auth/Check';
        $params = [
            'SessionId' => $this->_sessionId
        ];
        return $this->curlGet($url, $params) === 'true';
    }
    */
    public function SetVideo($mediaId, $externalId, $videoId)
    {
        $params = [
            'VideoId' => $videoId,
            'Force' => true,
            'SessionId' => $this->_sessionId,
            'MediaId' => $mediaId,
            'ExternalId' => $externalId
        ];
        $content = $this->curlPost('Media/SetVideo', $params);
        return $this->respondHandler($content);
    }
    
    public function addVideo($file, $mediaId, $externalId)
    {
        $position = 0;
        $uploadSessionId = $this->UploadStart($mediaId, $externalId);

        try {
            if (!file_exists($file)) {
                throw new Exception("Cannot open the file ");
            }

            if (!$file_handle = fopen($file, "rb")) {
                throw new Exception("Cannot read the file ");
            }
   
            while ($data = fread($file_handle, 1000000)) {
                $this->UploadDataPortion($uploadSessionId, $position, $data);
                $position += strlen($data);

                //echo "\r\033[K";
                //echo "uploading video... \033[s";
                //echo round(($position + 1000000) * 100 / filesize($file));
                //echo "%";
            }
            //echo "\033[uDone";
            //echo PHP_EOL;
            $answer = json_decode($this->UploadComplete($uploadSessionId));

            if (!$answer) {
                return false;
            }

            if ($answer->Success == false && $answer->Message->Id == 17) {
                $this->SetVideo($mediaId, $externalId, $answer->Data);
            }

            return $answer->Success;


            fclose($file_handle);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    

    private function curlPost($url, $params, $data = false, $headers = true)
    {
        try {
            $ch = curl_init();
            if (false === $ch) {
                throw new Exception('failed to initialize');
            }

            $fullUrl = $this->apiUrl.$url;
            if ($params !== false) {
                $data = json_encode($params);
            }
            //echo $fullUrl . PHP_EOL;
            //print_r($data);

            curl_setopt($ch, CURLOPT_URL, $fullUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            
            if ($headers) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Accept: application/json'
                ));
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200) {
                throw new Exception("response from ".$fullUrl." with code ".$httpCode);
            }

            if (false === $content) {
                throw new Exception(curl_error($ch), curl_errno($ch));
            }
            
            curl_close($ch);

            //print_r($content);

            return $content;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    private function curlGet($url, $params = false)
    {
        try {
            $ch = curl_init();
            if (false === $ch) {
                throw new Exception('failed to initialize');
            }

            $fullUrl = $this->apiUrl.$url;
            if ($params) {
                $fullUrl .= '?'.http_build_query($params);
            }

            curl_setopt($ch, CURLOPT_URL, $fullUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json'
                ));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode != 200) {
                throw new Exception("response from ".$fullUrl." with code ".$httpCode);
            }

            if (false === $content) {
                throw new Exception(curl_error($ch), curl_errno($ch));
            }
            
            curl_close($ch);

            return $content;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
}

/*
$mediaId = 1124855;
$newstube = new Newstube();
echo $newstube->PageCount(32703, -1, 'тест даты парсера')->Data.PHP_EOL;
$uploadSessionId = $newstube->UploadStart($mediaId)->Data;

$file = "dbeb35b7-f6ec-4b21-98b0-3994e22aaeec_fullhd.mp4";
//$file = "small.mp4";
$line = 1;
$count_line = 5;
$position = 0;

try {
    if(!file_exists($file))
        throw new Exception("Cannot open the file");

    if(!$file_handle = fopen($file, "rb"))
        throw new Exception("Cannot read the file");

    $filesize = filesize($file);
    while ($data = fread($file_handle, 1000000)) // читаем по одному байту до конца файла
    {
        //echo strlen($data)."\r";// выводим значение каждого байта
        $newstube->UploadDataPortion($uploadSessionId, $position, $data);

        $position += strlen($data);

        echo "\r\033[K";
        echo "uploading video... \033[s";
        echo round(($position + 1000000) * 100 / $filesize);
        echo "%";
    }
    echo "\033[uDone";
    echo PHP_EOL;
    $answer = json_decode($newstube->UploadComplete($uploadSessionId));

    if ($answer->Success == false && $answer->Message->Id == 17) {
        $newstube->SetVideo($mediaId, $answer->Data);
    }


    fclose($file_handle);
} catch(Exception $e) {
    echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
}
*/

//$newstube = new Newstube();
//var_dump($newstube->AuthCheck());
//echo $newstube->PageCount(32703, -1, 'тест даты парсера');

//echo $newstube->UploadStart(1124879);

//echo $newstube->MediaAdd(32703, 'тест даты парсера', 'время 2018-01-16 11:07:00', '2018-01-16T11:07:00+03:00');
//echo "2018-01-16T07:46:53.000Z".PHP_EOL;
//$date = date_create('2018-01-09T06:07:00+03:00');
//echo date_format($date, 'Y-m-d\TH:i:s');
