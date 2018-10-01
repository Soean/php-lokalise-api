<?php

namespace Lokalise\Endpoints;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use Lokalise\Exceptions\LokaliseApiException;
use Lokalise\Exceptions\LokaliseResponseException;
use Lokalise\LokaliseApiResponse;

class Endpoint implements EndpointInterface
{

    const FETCH_ALL_LIMIT = 1000;

    /** @var string $baseUrl API base URL */
    protected $baseUrl;

    /** @var null|string `X-Api-Token` authentication header */
    protected $apiToken;

    /**
     * Endpoint constructor.
     *
     * @param string $baseUrl parent::constant
     * @param string $apiToken Client provided authentication token
     */
    public function __construct($baseUrl, $apiToken)
    {
        $this->baseUrl = $baseUrl;
        $this->apiToken = $apiToken;
    }

    /**
     * @param string $requestType GET|POST|PUT|DELETE
     * @param string $uri
     * @param array $queryParams
     * @param array $body
     *
     * @return LokaliseApiResponse
     * @throws LokaliseApiException
     */
    protected function request($requestType, $uri, $queryParams = [], $body = [])
    {

        $client = new Client();

        $options = [
            'headers' => [
                'X-Api-Token' => $this->apiToken,
            ],
        ];
        if (!empty($queryParams)) {
            $options['query'] = $this->fixArraysInQueryParams($queryParams);
        }
        if (!empty($body)) {
            $options['json'] = $body;
        }

        try {
            $guzzleResponse = $client->request($requestType, "{$this->baseUrl}$uri", $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $guzzleResponse = $e->getResponse();
            } else {
                throw new LokaliseApiException($e->getMessage(), $e->getCode());
            }
        } catch (GuzzleException $e) {
            throw new LokaliseApiException($e->getMessage(), $e->getCode());
        }

        $body = $guzzleResponse->getBody();
        if (is_null($body)) {
            throw new LokaliseApiException('Not found', 404);
        }
        $bodyJson = @json_decode($body, true);
        if (!is_array($bodyJson) || json_last_error() !== JSON_ERROR_NONE) {
            throw new LokaliseApiException('Not found', 404);
        }

        return new LokaliseApiResponse($guzzleResponse);
    }

    /**
     * @param string $requestType
     * @param string $uri
     * @param array $queryParams
     * @param array $body
     * @param string $bodyResponseKey
     *
     * @return LokaliseApiResponse
     * @throws LokaliseApiException
     */
    protected function requestAll($requestType, $uri, $queryParams = [], $body = [], $bodyResponseKey = '')
    {
        $page = 1;
        $queryParams = array_merge($queryParams, ['limit' => self::FETCH_ALL_LIMIT, 'page' => $page]);

        $result = $this->request($requestType, $uri, $queryParams, $body);
        while ($result->getPageCount() > $page) {
            $page++;
            $queryParams = array_merge($queryParams, ['limit' => self::FETCH_ALL_LIMIT, 'page' => $page]);
            $previousResult = clone $result;
            $result = $this->request($requestType, $uri, $queryParams, $body);
            if (is_array($result->body[$bodyResponseKey]) && is_array($previousResult->body[$bodyResponseKey])) {
                $result->body[$bodyResponseKey] = array_merge($previousResult->body[$bodyResponseKey], $result->body[$bodyResponseKey]);
            }
        }

        return $result;
    }

    /**
     * @param array $queryParams
     *
     * @return null|string
     */
    protected function queryParamsToQueryString($queryParams)
    {
        return (!empty($queryParams) ? http_build_query($queryParams) : null);
    }

    /**
     * Method is replacing arrays in query parameters with comma separated values
     * @link https://lokalise.co/api2docs/curl/
     *
     * @param array $queryParams
     * @return array
     */
    private function fixArraysInQueryParams($queryParams)
    {
        foreach ($queryParams as $paramName => $paramValue) {
            $queryParams[$paramName] = $this->replaceArrayWithCommaSeparatedString($paramValue);
        }
        return $queryParams;
    }

    /**
     * Recursion method to replace arrays with imploded values
     *
     * @param mixed $array
     * @return array|string
     */
    private function replaceArrayWithCommaSeparatedString($array)
    {
        if (is_array($array)) {
            $foundMultiDimension = false;
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    $foundMultiDimension = true;
                }
            }
            if (!$foundMultiDimension) {
                return implode(',', $array);
            } else {
                foreach ($array as $key => $value) {
                    $array[$key] = $this->replaceArrayWithCommaSeparatedString($value);
                }

                return $this->replaceArrayWithCommaSeparatedString($array);
            }
        }

        return $array;
    }

}