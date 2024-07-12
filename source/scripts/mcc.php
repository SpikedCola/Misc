<?php declare(strict_types=1);

// this feed is specifically season 6 only.

$feedUrl = 'https://sd.tusnovelashd.co/category/masterchef-celebrity-colombia-2024-temporada-6/feed/';
$outDir = '/storage/TV Shows/MasterChef Celebrity Colombia/';
$seenGuidsFile = __DIR__.'/mcc_seen_guids.json';

echo gmdate('c')." start\n\n";

$seenGuids = [];
if (file_exists($seenGuidsFile)) {
        $seenGuids = json_decode(file_get_contents($seenGuidsFile), true);
}

echo "fetch feed: {$feedUrl}\n";
$feed = simplexml_load_file($feedUrl);

// only process 1st page, stop when we see a guid we've seen before.
foreach ($feed->channel->item as $item) {
        $title = (string)$item->title;
        echo "processing \"{$title}\"\n";

        $guid = (string)$item->guid;
        if (isset($seenGuids[$guid])) {
                echo "hit an episode weve seen, stopping\n";
                die;
        }

        $matches = [];
        if (!preg_match('/Cap[ií]tulo (\d+)/i', $title, $matches)) {
                echo "failed to parse episode number from title\ntitle: {$title}\n";
                die;
        }
        $epNum = (int)$matches[1];

        $content = (string)$item->children('content', true);

        // parse url from content
        $matches = [];
        if (!preg_match('/src="(.+?)"/i', $content, $matches)) {
                echo "failed to parse url from content\ncontent: {$content}\n";
                die;
        }
        $url = $matches[1];
        // fixup url if necessary
        if (0 === strpos($url, '//')) {
                $url = 'https:'.$url;
        }
        echo "parsed url: {$url}\n";

        // download video
        $outTitle = 'MasterChef Celebrity Colombia 2024 - Capítulo '.$epNum;
        echo "download video, save as: {$outTitle}\n";
        $cmd = "yt-dlp -N 100 -o \"{$outDir}{$outTitle}.%(ext)s\" {$url}";
        $resultCode = null;
        passthru($cmd, $resultCode);
        if ($resultCode !== 0) {
                echo "yt-dlp failed with code {$resultCode}\ncmd: {$cmd}\n";
                die;
        }

        // store seen guid
        $seenGuids[$guid] = true;
        file_put_contents($seenGuidsFile, json_encode($seenGuids));

        echo "done episode\n\n";
}

echo gmdate('c')." done\n";