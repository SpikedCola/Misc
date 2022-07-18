<?php declare(strict_types=1);

/**
 * Given a youtube channel, find and like all the videos.
 * 
 * Expecting a URL to be passed. Different endpoints for "channel" or "user"
 * thought they both look nearly identical in the YT interface.
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> 2022-07-18
 */

// config, create & download a json file from google.
define('CLIENT_SECRET_FILE', __DIR__.'/client_secret_....json');
// end config

require_once(__DIR__.'/../classes/vendor/autoload.php');

if (!CLIENT_SECRET_FILE || !file_exists(CLIENT_SECRET_FILE)) {
	echo "create & download an oauth json file from google first\n";
	die;
}

function usage() {
	echo "usage: php ".basename(__FILE__)." https://youtube.com/user/abcd\n";
}

$url = $argv[1] ?? null;
if (!$url) {
	usage();
	die;
}

// "liking" requires the use of oauth
$client = new Google_Client();
$client->setScopes([
    'https://www.googleapis.com/auth/youtube.force-ssl',
]);
$client->setAuthConfig(CLIENT_SECRET_FILE);
$client->setAccessType('offline');

// request authorization from the user.
$authUrl = $client->createAuthUrl();
echo "Open this link in your browser:\n{$authUrl}\n";
echo "Enter verification code (copy 'code' param from redirect url): ";
$authCode = trim(fgets(STDIN));
// exchange authorization code for an access token.
$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
$client->setAccessToken($accessToken);

// service that does things
$service = new Google_Service_YouTube($client);

// look up user/channel to get "uploads" playlist
$matches = [];
if (!preg_match('#/(c|channel|user)/([^/]+)#i', $url, $matches)) {
	usage();
	die;
}
switch ($matches[1]) {
	case 'channel':
		$searchType = 'id';
		break;
	case 'c':
	case 'user':
		$searchType = 'forUsername';
		break;
	default:
		echo "unhandled URL type: {$matches[1]}\n";
		die;
}
$channelQuery = $matches[2];
$channelResponse = $service->channels->listChannels('snippet,contentDetails', [ $searchType => $channelQuery ]);
$channel = $channelResponse->items[0] ?? null;
if (!$channel) {
	echo "failed to find channel for url {$url}\n";
	die;
}
$uploadsPlaylist = $channel->contentDetails->relatedPlaylists->uploads ?? null;
if (!$uploadsPlaylist) {
	echo "failed to find channel's 'uploads' playlist\n";
	var_dump($channel);
	die;
}
echo "found channel's 'upload' playlist '{$uploadsPlaylist}', fetching videos...\n";

$videoQuery = [ 
    'playlistId' => $uploadsPlaylist, 
    'maxResults' => 50
];
$nextPageToken = '';
$count = 0;

do {
	// pagination
	if ($nextPageToken) {
		$videoQuery['pageToken'] = $nextPageToken;
	}

	// get all videos from uploads playlist and like each one
	$videosResponse = $service->playlistItems->listPlaylistItems('snippet,contentDetails', $videoQuery);

	$videos = $videosResponse->items ?? [];

	echo "\nprocessing ".count($videos)." video(s)...\n";

	foreach ($videos as $video) {
		$videoId = $video->contentDetails->videoId;
		$title = $video->snippet->title;
		echo "> liking ({$videoId}) {$title}...";
		$likeResponse = $service->videos->rate($videoId, 'like');
		if (204 !== $likeResponse->getStatusCode()) {
			echo "liking failed for some reason:\n";
			var_dump($likeResponse);
			die;
		}
		echo " ok\n";
		$count++;
	}
	
	// pagination
	$nextPageToken = $videosResponse->nextPageToken;
	if ($nextPageToken) {
		echo "fetching next page...\n";
	}
}
while ($nextPageToken);

echo "\ndone! liked {$count} video(s)\n";