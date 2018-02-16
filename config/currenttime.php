<?php

return [
    'parser' => [        
        'site' => 'https://www.currenttime.tv',
        'itemsPage' => '/p/6411.html',
        'startParsingDate' => '10.02.2018',

        'itemsElementSelector' => 'div#content li .media-block div.content',
        'itemsElementLinkSelector' => 'a',
        'itemsElementDateSelector' => 'span.date',
        'itemsElementTitleSelector' => 'span.title',

        'itemContentSelector' => 'div.#content',
        'itemContentTitleSelector' => 'div.media-container h1',
        'itemContentDateSelector' => 'div.media-container span.date time',
        'itemContentDescriptionSelector' => 'div.intro p',
        'itemContentVideoSelector' => 'div.media-pholder video',
    ],
    'username' => '',
    'password' => '',
    'channelId' => 32703,
];