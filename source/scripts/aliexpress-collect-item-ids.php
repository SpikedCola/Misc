<?php declare(strict_types=1);

// required so we get english/CAD results
$cookie = 'aep_usuc_f=site=glo&c_tp=CAD&region=CA&b_locale=en_US;';

for ($page = 1; $page <= 5; $page++) {
	echo "page {$page}\n";
	
	$url = "https://www.aliexpress.com/w/wholesale-ws2815.html?page={$page}&g=y&SearchText=ws2815";
	
	$ch = curl_init($url);
	curl_setopt_array($ch, [
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_COOKIE => $cookie
	]);
	$response = curl_exec($ch);
	curl_close($ch);
	
	$matches = [];
	if (!preg_match_all('|"productId":"(\d+)"|i', $response, $matches)) {
		echo $response;
		echo "\n\nno matches\n";
		die;
	}
	foreach ($matches[1] as $match) {
		$itemIds[$match] = $match;
	}
}

echo count($itemIds)." results:\n\n";

echo implode(",\n", array_values($itemIds));