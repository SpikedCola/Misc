<?php declare(strict_types=1);

$seasons = json_decode('{"items": [
	{
    "label": "Temporada 26",
    "url": "/seasons/south-park/tu06id/temporada-26",
    "seasonNumber": 26
}, {
    "label": "Temporada 25",
    "url": "/seasons/south-park/lrnlos/temporada-25",
    "seasonNumber": 25
}, {
    "label": "Temporada 24",
    "url": "/seasons/south-park/wjz4g1/temporada-24",
    "seasonNumber": 24
}, {
    "label": "Temporada 23",
    "url": "/seasons/south-park/yd9e68/temporada-23",
    "seasonNumber": 23
}, {
    "label": "Temporada 22",
    "url": "/seasons/south-park/wcj3pl/temporada-22",
    "seasonNumber": 22
}, {
    "label": "Temporada 21",
    "url": "/seasons/south-park/oid99e/temporada-21",
    "seasonNumber": 21
}, {
    "label": "Temporada 20",
    "url": "/seasons/south-park/cysw6o/temporada-20",
    "seasonNumber": 20
}, {
    "label": "Temporada 19",
    "url": "/seasons/south-park/bl4xrv/temporada-19",
    "seasonNumber": 19
}, {
    "label": "Temporada 18",
    "url": "/seasons/south-park/krtuuh/temporada-18",
    "seasonNumber": 18
}, {
    "label": "Temporada 17",
    "url": "/seasons/south-park/xndhzz/temporada-17",
    "seasonNumber": 17
}, {
    "label": "Temporada 16",
    "url": "/seasons/south-park/jray0n/temporada-16",
    "seasonNumber": 16
}, {
    "label": "Temporada 15",
    "url": "/seasons/south-park/lpr4hd/temporada-15",
    "seasonNumber": 15
}, {
    "label": "Temporada 14",
    "url": "/seasons/south-park/328guw/temporada-14",
    "seasonNumber": 14
}, {
    "label": "Temporada 13",
    "url": "/seasons/south-park/t4vdby/temporada-13",
    "seasonNumber": 13
}, {
    "label": "Temporada 12",
    "url": "/seasons/south-park/gfstdd/temporada-12",
    "seasonNumber": 12
}, {
    "label": "Temporada 11",
    "url": "/seasons/south-park/w0sorw/temporada-11",
    "seasonNumber": 11
}, {
    "label": "Temporada 10",
    "url": "/seasons/south-park/s6x4l8/temporada-10",
    "seasonNumber": 10
}, {
    "label": "Temporada 9",
    "url": "/seasons/south-park/z7qxgq/temporada-9",
    "seasonNumber": 9
}, {
    "label": "Temporada 8",
    "url": "/seasons/south-park/ehqdq0/temporada-8",
    "seasonNumber": 8
}, {
    "label": "Temporada 7",
    "url": "/seasons/south-park/9ooeyv/temporada-7",
    "seasonNumber": 7
}, {
    "label": "Temporada 6",
    "url": "/seasons/south-park/4kea2v/temporada-6",
    "seasonNumber": 6
}, {
    "label": "Temporada 5",
    "url": "/seasons/south-park/2emiow/temporada-5",
    "seasonNumber": 5
}, {
    "label": "Temporada 4",
    "url": "/seasons/south-park/ebik79/temporada-4",
    "seasonNumber": 4
}, {
    "label": "Temporada 3",
    "url": "/seasons/south-park/194tqq/temporada-3",
    "seasonNumber": 3
}, {
    "label": "Temporada 2",
    "url": "/seasons/south-park/cfnwds/temporada-2",
    "seasonNumber": 2
}, {
    "label": "Temporada 1",
    "url": "/seasons/south-park/yjy8n9/temporada-1",
    "seasonNumber": 1
}]}');

foreach ($seasons->items as $season) {
	echo $season->label."\n";
	
	// load page to get first 10 episodes - hit api to get more
	$url = 'https://www.southpark.lat'.$season->url;
	$contents = file_get_contents($url);
	$matches = [];
	if (!preg_match('/window\.__DATA__ = (\{.+?\});/i', $contents, $matches)) {
		var_dump($contents);
		echo "didnt find window.data";
		die;
	}
	$data = json_decode($matches[1]);
	if (!$data) {
		var_dump($contents, $matches[1]);
		echo "json didnt decode";
		die;
	}
	foreach ($data->children as $child) {
		if ('MainContainer' !== $child->type) {
			continue;
		}
		foreach ($child->children as $c2) {
			if (!($c2->props->isEpisodes ?? false)) {
				continue;
			}
			$episodes = $c2->props->items;
			foreach ($episodes as $episode) {
				$nameParts = [];
				if (!preg_match('/T(\d+).+E(\d+)/i', $episode->meta->header->title->text, $nameParts)) {
					echo 'a'; die;
				}
				$name = sprintf(
					'S%02dE%02d',
					$nameParts[1],
					$nameParts[2]
				);
				$results[$name] = 'https://www.southpark.lat'.$episode->url;
			}
			if ($c2->props->loadMore ?? false) {
				echo "loadmore\n";

				// fetch another api call to get the rest of the episodes.
				$moreContents = file_get_contents('https://www.southpark.lat'.$c2->props->loadMore->url);
				if (!$moreContents) {
					echo 'more fialed'; die;
				}
				$moreJson = json_decode($moreContents);
				if (!$moreJson) {
					echo 'more json failed'; die;
				}
				foreach ($moreJson->items as $item) {
					$nameParts = [];
					if (!preg_match('/T(\d+).+E(\d+)/i', $item->meta->header->title->text, $nameParts)) {
						echo 'a'; die;
					}
					$name = sprintf(
						'S%02dE%02d',
						$nameParts[1],
						$nameParts[2]
					);
					$results[$name] = 'https://www.southpark.lat'.$item->url;
				}
			}
		}
	}
	var_dump($results);
	echo "\n\n";
}

$ytdlp = 'yt-dlp -N 5 -S vcodec:h264,res:1080,ext:mp4:m4a --merge-output-format mp4 --embed-chapters --embed-subs --no-mtime --restrict-filenames';

$outfile = __DIR__.'/outfile-'.microtime(true).'.cmd';
$fp = fopen($outfile, 'w');
fwrite($fp, "@echo on\n");
foreach ($results as $seasonep => $url) {
	// %% is escaping % for windows batch
	fwrite($fp, "{$ytdlp} -o \"D:\\yt-dl\\sp\\{$seasonep} - %%(title)s.%%(ext)s\" {$url}\n");
}
fclose($fp);
