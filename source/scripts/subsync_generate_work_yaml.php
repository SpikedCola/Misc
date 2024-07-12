<?php

$videos = glob('*.mkv');

$data = [];

foreach ($videos as $video) {
	echo $video."\n";
	$subname = str_replace('.mkv', '.srt', $video);
		$data[] = [
		'sub' => [
		'path' => __DIR__.'/bad subs/'.$subname,
		'lang' => 'eng'
		],
		'ref' => [
			'path' => __DIR__.'/'.$video,
			'lang' => 'spa'
			
		],
		'out' => [
			'path' => __DIR__.'/'.$subname
		]
	];
}

echo "write yaml file\n";

yaml_emit_file('work.yaml', $data);

echo "done\n";