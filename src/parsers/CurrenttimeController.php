<?php

namespace NewstubeParser\parsers;

use NewstubeParser\ParserController;
use PHPHtmlParser\Dom;

class CurrenttimeController extends ParserController
{
    public function getItems()
    {
        $result = [];

        $dom = new Dom;
        $html = $dom->loadFromUrl($this->getParam('site') . $this->getParam('itemsPage'));

        //$html = str_get_html($this->getContent($this->site . $this->itemsPage));
        if (!$html) {
            return false;
        }

        foreach ($html->find($this->getParam('itemsElementSelector')) as $element) {
            $link = $element->find($this->getParam('itemsElementLinkSelector'), 0)->href;
            $date = $element->find($this->getParam('itemsElementDateSelector'), 0)->text();
            $title = $element->find($this->getParam('itemsElementTitleSelector'), 0)->text();
            $externalId = preg_replace('/.*\/a\/(.*)\.html$/i', '$1', $link);
            $result[] = [
                'date' => date_create_from_format('H:i d.m.Y', trim($date)),
                'title' => html_entity_decode(trim($title)),
                'url' => $link,
                'externalId' => $externalId
            ];
        }
        if (empty($result)) {
            return false;
        }

        return $result;
    }

    
    public function getItem($url)
    {
        //$url = $this->getParam('site') . $itemUrl;
        $externalId = preg_replace('/.*\/a\/(.*)\.html$/i', '$1', $url);

        $dom = new Dom;
        $html = $dom->loadFromUrl($url);

        if (!$html) {
            return false;
        }

        $content = $html->find($this->getParam('itemContentSelector'), 0);
        $title = html_entity_decode(trim($content->find($this->getParam('itemContentTitleSelector'), 0)->text()));
        $datetime = $content->find($this->getParam('itemContentDateSelector'), 0)->datetime;
        
        if ($desc_bock = $content->find($this->getParam('itemContentDescriptionSelector'), 0)) {
            $description = html_entity_decode(trim($desc_bock->text()));
        } else {
            $description = '';
        }
            
        $element = $content->find($this->getParam('itemContentVideoSelector'), 0);

        $property = 'data-sources'; //fix для имени аттрибута
        $text = $element->$property;
        $text = html_entity_decode($text);
        $videos = json_decode($text);

        $videoUrls = [];
        //сортировка видео от меньшего качества к большему
        $quality = ['270p', '360p', '720p', '1080p'];
        foreach ($quality as $q) {
            foreach ($videos as $video) {
                if ($q == $video->DataInfo) {
                    $videoUrls[] = $video->Src;
                }
            }
        }

        return [
            'date' => date_create_from_format(\DateTime::ISO8601, $datetime),
            'title' => $title,
            'description' => $description,
            'referenceUrl' => $url,
            'videos' => $videoUrls,
            'externalId' => $externalId
        ];
    }
}
