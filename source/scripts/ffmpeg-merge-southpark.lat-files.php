<?php declare(strict_types=1);

$files = glob('*.mp4');

// group files by season/episode
$grouped = [];
foreach ($files as $file) {
	$parts = explode(' - ', $file, 2);
	$grouped[$parts[0]][] = $file;
}

// for each file, create a final filename 
$final = [];
foreach ($grouped as $ep => $files) {
	// sort files, should already be sorted but just in case.
	sort($files);
	// use first file to generate name
	$file = $files[0];
	$nameParts = explode('_E_', $file, 2);
	$name = 'South Park - '.ucwords(str_replace('_', ' ', str_replace('South_Park_', '', $nameParts[0]))).'.mp4';
	$final[$ep] = [
	    'name' => $name,
	    'files' => $files
	];
}

// should already be asc but sort just in case
ksort($final);

foreach ($final as $ep => $data) {
	$durations = [];
	$files = $data['files'];
	$name = $data['name'];
	echo $name."\n";
	echo "probe files\n";
	foreach ($files as $file) {
		echo " {$file}\n";
		$probeCmd = "ffprobe -v quiet -of csv=p=0 -show_entries format=duration \"{$file}\"";
		$probeOutput = [];
		$probeCode = null;
		exec($probeCmd, $probeOutput, $probeCode);
		if (0 !== $probeCode) {
			var_dump($file);
			echo 'probe failed';
			die;
		}
		$durations[$file] = (float)$probeOutput[0];
	}
	
	// build chapters
	echo "build chapters\n";
	$chapters = [];
	foreach ($files as $idx => $file) {
		$start = 0;
		if ($idx) {
			// start comes from end of previous chapter
			$start = $chapters[$idx-1]['end'];
		}
		$duration = $durations[$file];
		$chapters[] = [
		    'title' => 'Chapter '.($idx+1),
		    'start' => $start,
		    'end' => $start+$duration
		];
	}
	
	// write input file
	echo "write input file\n";
	$inputFile = __DIR__.'/input.txt';
	$fp = fopen($inputFile, 'w');
	foreach ($files as $file) {
		fwrite($fp, "file '{$file}'\n");
	}
	fclose($fp);
	
	// write metadata file
	echo "write metadata file\n";
	$metadataFile = __DIR__.'/combined.metadata.txt';
	$fp2 = fopen($metadataFile, 'w');
	fwrite($fp2, ";FFMETADATA1\n");
	foreach ($chapters as $chapter) {
		fwrite($fp2, "[CHAPTER]
TIMEBASE=1/1
START={$chapter['start']}
END={$chapter['end']}
title={$chapter['title']}
");
	}
	fclose($fp2);
	
	if (!is_dir(__DIR__.'/merged/')) {
		mkdir(__DIR__.'/merged/');
	}
	
	echo "merge\n";
	// use metadata when merging. hide_banner and loglevel silence output. -safe required for our format of outfile name.
	$mergeCmd = "ffmpeg -hide_banner -loglevel error -f concat -safe 0 -y -i \"{$inputFile}\" -i \"{$metadataFile}\" -c copy -scodec copy -map_metadata 1 \"merged/{$name}\"";
	$mergeCode = null;
	passthru($mergeCmd, $mergeCode);
	if (0 !== $mergeCode) {
		var_dump($file, $mergeCmd);
		echo 'merge failed';
		die;
	}
	
	// cleanup on success
	echo "cleanup\n";
	unlink($inputFile);
	unlink($metadataFile);
	
	echo "ok\n\n";

}