<?php declare(strict_types=1);

/**
 * Collect NATB member info & shove into csv.
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> 2024-01-11
 */

$headers = [
    'uid' => 'Member ID',
    'prf' => 'Member Profile',
    'ctc' => 'Owner',
    'nam' => 'Business Name',
    'ad1' => 'Address',
    'ad2' => 'Address 2',
    'cit' => 'City',
    'sta' => 'State',
    'zip' => 'ZIP',
    'con' => 'Country',
    'loc1' => 'Latitude',
    'loc0' => 'Longitude',
    'phn' => 'Phone',
    'fax' => 'Fax',
    'web' => 'Website',
    'bbb' => 'Better Business Bureau',
    'goo' => 'Google',
    'igm' => 'Instagram',
    'fbk' => 'Facebook',
    'lki' => 'LinkedIn',
    'pin' => 'Pinterest',
    'twt' => 'Twitter',
    'ylp' => 'Yelp',
    'you' => 'YouTube'
];

$outfile = 'natb_members_'.microtime(true).'.csv';
$fp = fopen($outfile, 'w');
echo "get member list\n\n";
$response = natb_get('https://api.membershipworks.com/v2/directory?_st&_rf=Members');
$json = json_decode($response);
$users = $json->usr;
$count = count($users);
$i = 0;
fputcsv($fp, $headers);
foreach ($users as $usr) {
	$i++;
	echo "[{$i}/{$count}] get member {$usr->uid}\n";
	// look up each usr to get full details.
	$memberResponse = natb_get("https://api.membershipworks.com/v2/account/{$usr->uid}/profile");
	$memberJson = json_decode($memberResponse);
	$csv = [];
	foreach ($headers as $key => $value) {
		switch ($key) {
		    case 'uid':
		    case 'ctc':
		    case 'nam':
		    case 'phn':
		    case 'fax':
		    case 'web':
			    $value = $memberJson->$key;
			    break;
		    case 'ad1':
		    case 'ad2':
		    case 'cit':
		    case 'sta':
		    case 'zip':
		    case 'con':
			    // ad2 can be missing
			    $value = $memberJson->adr->$key ?? '';
			    break;
		    case 'loc1':
			    // missing on at least 1 member
			    $value = $memberJson->adr->loc[1] ?? '';
			    break;
		    case 'loc0':
			    // missing on at least 1 member
			    $value = $memberJson->adr->loc[0] ?? '';
			    break;
		    case 'bbb':
		    case 'fbk':
		    case 'goo':
		    case 'igm':
		    case 'lki':
		    case 'pin':
		    case 'twt':
		    case 'ylp':
		    case 'you':
			    // pfu or some entries can be missing
			    $value = $memberJson->pfu->$key ?? '';
			    break;
		    case 'prf':
			    $value = 'https://www.natb.org/find-a-member/#!biz/id/'.$memberJson->uid;
			    break;
		    default: 
			    throw new RuntimeException('unhandled key '.$key);
		}
		$csv[$key] = $value;
	}
	fputcsv($fp, $csv);
}
fclose($fp);
echo "\ndone - wrote to {$outfile}\n";

function natb_get(string $url) : string {
	$ch = curl_init($url);
	curl_setopt_array($ch, [
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_HTTPHEADER => [
		// x-org header needed for authentication.
		'x-org: 19584'
	    ]
	]);
	$response = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);
	if (200 !== $info['http_code']) {
		var_dump($response);
		echo "didnt get 200 for {$url}\n";
		die;
	}
	return $response;
}
