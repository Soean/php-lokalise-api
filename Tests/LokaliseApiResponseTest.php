<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Lokalise\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JsonException;
use Lokalise\LokaliseApiResponse as ApiResponse;
use PHPUnit\Framework\TestCase;

final class LokaliseApiResponseTest extends TestCase
{

    private ?array $headers = null;

    protected function setUp(): void
    {
        $this->headers = [
            'X-Pagination-Total-Count' => 14,
            'X-Pagination-Page-Count' => 2,
            'X-Pagination-Limit' => 10,
            'X-Pagination-Page' => 1,
            'X-Pagination-Next-Cursor' => ''
        ];
    }

    protected function tearDown(): void
    {
        $this->headers = null;
    }

    public function testHeaders(): void
    {
        $apiResponse = $this->getApiResponse(200, $this->headers);

        self::assertEquals($this->headers, $apiResponse->headers);
    }

    public function testGetTotalCount(): void
    {
        $apiResponse = $this->getApiResponse(200, $this->headers);

        self::assertEquals($this->headers['X-Pagination-Total-Count'], $apiResponse->getTotalCount());
    }

    public function testGetPageCount(): void
    {
        $apiResponse = $this->getApiResponse(200, $this->headers);

        self::assertEquals($this->headers['X-Pagination-Page-Count'], $apiResponse->getPageCount());
    }

    public function testGetPaginationLimit(): void
    {
        $apiResponse = $this->getApiResponse(200, $this->headers);

        self::assertEquals($this->headers['X-Pagination-Limit'], $apiResponse->getPaginationLimit());
    }

    public function testGetPaginationPage(): void
    {
        $apiResponse = $this->getApiResponse(200, $this->headers);

        self::assertEquals($this->headers['X-Pagination-Page'], $apiResponse->getPaginationPage());
    }

    public function testBody(): void
    {
        $body = ['keys' => ['one' => 'key']];
        $apiResponse = $this->getApiResponse(200, [], $body);

        self::assertEquals($body, $apiResponse->body);
    }

    public function testGetContent(): void
    {
        $body = ['keys' => ['one' => 'key']];
        $apiResponse = $this->getApiResponse(200, [], $body);

        self::assertEquals($body, $apiResponse->getContent());
    }

    public function testToArray(): void
    {
        $body = ['keys' => ['one' => 'key']];
        $apiResponse = $this->getApiResponse(200, [], $body);

        self::assertEquals($body, $apiResponse->getContent());
        self::assertEquals($body, $apiResponse->__toArray());
    }

    public function testToString(): void
    {
        $body = ['keys' => ['one' => 'key']];
        $bodyString = '{"keys":{"one":"key"}}';
        $apiResponse = $this->getApiResponse(200, [], $body);

        self::assertEquals($body, $apiResponse->getContent());
        self::assertEquals($bodyString, (string)$apiResponse);
    }

    /**
     * @param int $status
     * @param array $headers
     * @param null|array $body
     *
     * @return ApiResponse
     * @throws JsonException
     */
    private function getApiResponse(int $status = 200, array $headers = [], array $body = null): ApiResponse
    {
        $client = new Client([
            'handler' => HandlerStack::create(
                new MockHandler([
                    new Response(
                        $status,
                        $headers,
                        json_encode($body, JSON_THROW_ON_ERROR)
                    )
                ])
            )
        ]);

        try {
            $guzzleResponse = $client->request('GET', 'https://api.lokalise.com/api2');
        } catch (RequestException $e) {
            $guzzleResponse = new Response($status, $headers, json_encode($body, JSON_THROW_ON_ERROR));
        } catch (GuzzleException $e) {
            $guzzleResponse = new Response($status, $headers, json_encode($body, JSON_THROW_ON_ERROR));
        }

        return new ApiResponse($guzzleResponse);
    }
}
