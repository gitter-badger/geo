<?php

require __DIR__ . "/init.php";

use Vladimino\Geo\Client\GeoClient;

/**
 * @var \Vladimino\Geo\Client\GeoClient
 */
$oClient = new GeoClient('google');
$oClient->getResultsByLocation('Oranienstraße 164, 10969, Berlin Germany');
$oClient->printResults();


