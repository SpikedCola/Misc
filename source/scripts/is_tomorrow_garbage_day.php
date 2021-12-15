<?php declare(strict_types=1);

var_dump(is_tomorrow_garbage_day());

/**
 * Check if tomorrow is garbage day in my particular service area.
 * 
 * Quick and dirty, @todo error checking.
 * 
 * @return bool
 */
function is_tomorrow_garbage_day(int $serviceId = 1210) : bool {
	$dt = new \DateTime('tomorrow midnight', new \DateTimeZone('America/Toronto'));
	$tomorrow = $dt->format('Y-m-d');
	$url = "https://api.recollect.net/api/places/6EADDF9E-C14E-11EA-B52F-850DAA7C7910/services/{$serviceId}/events";
	$json = json_decode(file_get_contents($url));
	foreach ($json->events as $event) {
		if ($tomorrow === $event->day) {
			foreach ($event->flags as $flag) {
				if ('Garbage' === $flag->name) {
					return true;
				}
			}
		}
	}
	return false;
}