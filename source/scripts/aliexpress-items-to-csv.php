<?php declare(strict_types=1);

$itemIds = [
    1005004289391906,
    1005005577071926,
    1005006047094128,
    1005005880356788,
    1005004444748439,
    4001322411818,
];

// required so we get english/CAD results.
// warning: if we dont pass user cookies, we will see "welcome deals" with max qty 1, not true prices.
//          consider putting cookies from chrome here.
$cookie = 'aep_usuc_f=site=glo&c_tp=CAD&region=CA&b_locale=en_US;';

$outFile = 'aliexpress-results-'.microtime(true).'.csv';

$headers = [
    'title' => 'Title',
    'url' => 'URL',
    'seller_name' => 'Seller Name',
    'seller_store_url' => 'Seller Store URL',
    'seller_since' => 'Seller Since',
    'sku_id' => 'Sku ID',
    'price' => 'Price'
];

$fp = fopen($outFile, 'w');
fputcsv($fp, $headers);
foreach ($itemIds as $itemId) {
	echo "fetch {$itemId} ...";
	$url = "https://www.aliexpress.com/item/{$itemId}.html";

	$ch = curl_init($url);
	curl_setopt_array($ch, [
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_COOKIE => $cookie
	]);
	$response = curl_exec($ch);
	curl_close($ch);

	$matches = [];
	if (!preg_match('/window\.runParams = \{\s+data: (\{.+?\})\s+\};/is', $response, $matches)) {
		echo $response;
		echo "\n\nmatch fail\n";
		die;
	}
	$json = json_decode($matches[1]);

	$fields = [
	    'title' => $json->metaDataComponent->title,
	    'url' => $json->metaDataComponent->ogurl,
	    'seller_name' => $json->sellerComponent->storeName,
	    'seller_store_url' => $json->sellerComponent->storeURL,
	    'seller_since' => $json->sellerComponent->formatOpenTime,
	];

	$skuProperties = $json->skuComponent->productSKUPropertyList;
	$skus = $json->priceComponent->skuPriceList;
	echo " process ".count($skus)." skus...";
	foreach ($skus as $sku) {
		$skuProps = explode(',', $sku->skuPropIds);
		$props = [];
		foreach ($skuProps as $skuProp) {
			// find which list its in
			foreach ($skuProperties as $sp) {
				$propertyGroupName = $sp->skuPropertyName;
				foreach ($sp->skuPropertyValues as $spv) {
					// intentionally loose lookup here
					if ($skuProp == $spv->propertyValueIdLong) {
						$props[$propertyGroupName] = $spv->propertyValueDisplayName;
						break 2;
					}
				}
			}
		}

		// couldnt figure out how to match shipping cost to each sku :(
//		var_dump($sku);
		//echo $sku->freightExt."\n";
		$freight = json_decode($sku->freightExt);

		// skuActivityAmount is included when there is a discount offered.
		$amount = $sku->skuVal->skuActivityAmount ?? $sku->skuVal->skuAmount ?? null;
		if (!$amount) {
			var_dump($sku->skuVal);
			echo "\n\nmissing skuActivityAmount/skuAmount\n";
			die;
		}

		$skuFields = $fields + [
		    'sku_id' => $sku->skuId,
		    'price' => $amount->value
		] + $props;

		// only care about ws2815 + ip30 + 60
		foreach ($props as $prop) {
			if (false !== strpos($prop, '2815')) {
				foreach ($props as $prop2) {
					if (false !== stripos($prop2, 'ip30')) {
						foreach ($props as $prop3) {
							if (false !== strpos($prop3, '60')) {
								fputcsv($fp, $skuFields);
								break 3;
							}
						}
					}
				}
			}
		}
	}

	echo " ok\n";
}
fclose($fp);
echo "done - wrote {$outFile}\n";