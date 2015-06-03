<?php

namespace Vladimino\Geo\Provider;

use Vladimino\Geo\Entity\Result;
use Vladimino\Geo\Entity\ResultCollection;
use Vladimino\Geo\Service\ResultFactory;

/**
 * GeoProviderInterface implementation for Google Geocoding API provider.
 *
 *
 * @url https://developers.google.com/maps/documentation/geocoding/
 * @package Vladimino\Geo\Provider
 * @author vladimino
 */
class GoogleGeoProvider extends BaseProvider implements GeoProviderInterface
{
    /**
     * Returns the name of the Provider.
     *
     * @return string
     */
    public function getName()
    {
        return 'google';
    }

    /**
     * Returns the name of for the section in configuration file.
     *
     * @return string
     */
    public function getConfigSection()
    {
        return 'geocoding';
    }

    /**
     * @param string $sLocation String to geocode.
     *
     * @return \Vladimino\Geo\Entity\ResultCollection|null
     * @throws \Exception if query was not executed properly
     */
    public function getResultsByLocation($sLocation)
    {
        $this->setLocation($sLocation);

        try {
            $aGoogleResults = $this->executeQuery();
        } catch (\Exception $e) {
            throw new \Exception(sprintf("%s Error. %s", __CLASS__, $e->getMessage()));
        }

        /** @var \Vladimino\Geo\Entity\ResultCollection $oCollection */
        $oCollection = new ResultCollection($this->getName(), $this->getLocation());

        /** @var array $aGoogleResult */
        foreach ($aGoogleResults as $aGoogleResult) {
            /** @var \Vladimino\Geo\Entity\Result $oResult */
            $oResult = $this->convertResult($aGoogleResult);
            $oCollection->addResult($oResult);
        }

        return $oCollection;
    }

    /**
     * @return array|null
     * @throws \RuntimeException if response is empty
     * @throws \InvalidArgumentException if API key is invalid
     * @throws \RuntimeException if daily quota is exceeded
     * @throws \RuntimeException if status is wrong or no results found
     */

    private function executeQuery()
    {
        /** @var string $sUrl */
        $sUrl = $this->buildQueryUrl();

        /** @var string $sResponse */
        $sContent = file_get_contents($sUrl);

        /** @var array $aResult */
        $aResult = json_decode($sContent, true);

        // API error
        if (empty($sContent) || !$aResult) {
            throw new \RuntimeException(sprintf('Could not execute query: "%s".', $sUrl));
        }

        // Invalid API key
        if ('REQUEST_DENIED' === $aResult['status'] && 'The provided API key is invalid.' === $aResult['error_message']) {
            throw new \InvalidArgumentException(sprintf('API key is invalid: %s', $sUrl));
        }
        // You are over your quota
        if ('OVER_QUERY_LIMIT' === $aResult['status']) {
            throw new \RuntimeException(sprintf('Daily quota exceeded: %s', $sUrl));
        }
        // No results
        if (!isset($aResult['status']) || !count($aResult['results']) || 'OK' !== $aResult['status']) {
            throw new \RuntimeException(sprintf('No results or unexpected status returned for query: %s', $sUrl));
        }

        return $aResult['results'];
    }

    /**
     * Build URL from config parameters and user input
     *
     * @return string
     */

    private function buildQueryUrl()
    {
        /** @var string $sUrl */
        $sBaseUrl = $this->getConfig('url') . $this->getConfig('format');

        /** @var array $aParams */
        $aParams = array(
            'address' => $this->getLocation(),
            'key' => $this->getConfig('api_key')
        );

        return $sBaseUrl . '?' . http_build_query($aParams);
    }

    /**
     * Convert Google Maps Result to Result object
     *
     * @param array $aGoogleResult
     * @return \Vladimino\Geo\Entity\Result
     */
    private function convertResult(array $aGoogleResult)
    {
        /** @var array $aAdress */
        $aAddress = $this->extractAddress($aGoogleResult['address_components']);

        /** @var array $aComponents */
        $aComponents = [
            $aAddress['country'],
            $aAddress['state'],
            $aAddress['city'],
            $aGoogleResult['geometry']['location']['lng'],
            $aGoogleResult['geometry']['location']['lat']
        ];

        return ResultFactory::make($aComponents);
    }

    /**
     * Walk through address components to extract an address
     *
     * @param array $aAddressComponents
     * @return array
     */
    private function extractAddress(array $aAddressComponents)
    {
        /** @var array $aAddress */
        $aAddress = [
            'country' => null,
            'state' => null,
            'city' => null
        ];

        /** @var array $aComponent */
        foreach ($aAddressComponents as $aComponent) {
            foreach ($aComponent['types'] as $type) {
                switch ($type) {
                    case 'country':
                        $aAddress['country'] = $aComponent['long_name'];
                        break;
                    case 'administrative_area_level_1':
                        $aAddress['state'] = $aComponent['long_name'];
                        break;
                    case 'locality':
                        $aAddress['city'] = $aComponent['long_name'];
                        break;
                    default:
                }
            }
        }

        return $aAddress;
    }

}