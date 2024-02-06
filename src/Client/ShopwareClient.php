<?php

/**
 * Project: shopware-oauth2-client
 */

namespace AxroShopware\Client;

use Exception;
use GuzzleHttp\Exception\RequestException;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use JsonException as JsonErrorException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use AxroShopware\Exception\AccessTokenException;

class ShopwareClient
{
    private int $expiresAt;
    private string $accessToken;
    private array $promises;
    private string $indexingBehavior;
    public const INDEXING_SYNC = null;
    public const INDEXING_QUEUE = 'use-queue-indexing';
    public const INDEXING_DISABLE = 'disable-indexing';
    public ?LoggerInterface $logger = null;

    public function __construct(
        private string $baseUrl,
        private string $clientId,
        private string $clientSecret,
    ) {
        $this->promises = [];
        $this->indexingBehavior = self::INDEXING_QUEUE;
    }

    public function indexing(string $indexingBehavior): ShopwareClient
    {
        $this->indexingBehavior = $indexingBehavior;
        return $this;
    }

    /**
     * @throws GuzzleException
     * @throws AccessTokenException
     * @throws Exception
     */
    public function request(string $method, string $uri, array $body = [], $returnObject = false): mixed
    {
        try {
            $this->getAccessToken();
            $response = match ($method) {
                'GET' => $this->get($uri),
                'POST' => $this->post($uri, $body),
                'PATCH' => $this->patch($uri, $body),
                'PUT' => $this->put($uri, $body),
                'DELETE' => $this->delete($uri),
                default => throw new Exception('Unsupported')
            };
        } catch (RequestException | AccessTokenException $e) {
            $this->logger?->error(
                $e->getMessage(),
                [
                    'uri' => $e->getMessage(),
                    'body' => $body,
                    'method' => $method
                ]
            );

            throw new $e(
                $e->getMessage(),
                $e->getRequest(),
                $e->getResponse()
            );
        }

        $responseBody = $response->getBody()->getContents();
        if (empty($responseBody)) {
            return true;
        }

        return $this->handleResponse($responseBody, $returnObject);
    }

    /**
     * @throws AccessTokenException
     * @throws Exception
     */
    public function requestAsync(string $method, string $uri, array $body = []): ShopwareClient
    {
        $this->getAccessToken();
        $response = match ($method) {
            'GET' => $this->getAsync($uri),
            'POST' => $this->postAsync($uri, $body),
            'PATCH' => $this->patchAsync($uri, $body),
            'PUT' => $this->putAsync($uri, $body),
            'DELETE' => $this->deleteAsync($uri),
            default => throw new Exception('Unsupported')
        };

        $this->promises[] = $response;
        return $this;
    }

    /**
     * @throws JsonErrorException
     */
    public function promise($returnObject = false): mixed
    {
        $results = Utils::all($this->promises)->wait();
        unset($responses); // @phpstan-ignore-line
        $responses = [];

        foreach ($results as $result) {
            if ($returnObject) {
                $responses = json_decode($result->getBody(), false, 512, JSON_THROW_ON_ERROR);
            } else {
                $responses[] = json_decode($result->getBody(), true, 512, JSON_THROW_ON_ERROR);
            }
        }
        return $responses;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function setClient(bool $token = true): Client
    {
        if ($token) {
            $header = [
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
                'indexing-behavior' => $this->indexingBehavior,
            ];
        } else {
            $header = [
                'Content-Type' => 'application/json',
            ];
        }

        return new Client([
            'base_uri' => $this->baseUrl,
            'headers' => $header,
        ]);
    }

    /**
     * @throws AccessTokenException|GuzzleException
     */
    private function getToken(): void
    {
        $payload = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
        ];

        $this->postToken($payload);
    }

    /**
     */
    private function isTokenExpired(): ?ShopwareClient
    {
        if ($this->expiresAt < (time() + 10)) {
            return $this;
        }
        return null;
    }

    private function setTokenData(array $response): void
    {
        $this->accessToken = $response['access_token'];
        $this->expiresAt = (new DateTime())->modify("+" . $response['expires_in'] . "seconds")->getTimestamp();
    }

    /**
     * @throws GuzzleException
     * @throws AccessTokenException
     */
    private function postToken(array $body): void
    {
        $response = $this->setClient(false)->post('/api/oauth/token', [
            'json' => $body
        ]);

        $data = $this->handleResponse($response->getBody()->getContents(), false);
        if (!($data['access_token'] ?? false)) {
            throw new AccessTokenException('Access token is missing', $data);
        }
        $this->setTokenData($data);
    }

    /**
     * @throws GuzzleException
     */
    private function post(string $uri, array $body): ResponseInterface
    {
        return $this->setClient()->post($uri, [
            'json' => $body
        ]);
    }

    /**
     * @throws GuzzleException
     */
    private function get(string $uri): ResponseInterface
    {
        return $this->setClient()->get($uri);
    }

    /**
     * @throws GuzzleException
     */
    private function patch(string $uri, array $body): ResponseInterface
    {
        return $this->setClient()->patch($uri, [
            'json' => $body
        ]);
    }

    /**
     * @throws GuzzleException
     */
    private function put(string $uri, array $body): ResponseInterface
    {
        return $this->setClient()->put($uri, [
            'json' => $body
        ]);
    }

    /**
     * @throws GuzzleException
     */
    private function delete(string $uri): ResponseInterface
    {
        return $this->setClient()->delete($uri);
    }

    private function postAsync(string $uri, array $body): PromiseInterface
    {
        return $this->setClient()->postAsync($uri, [
            'json' => $body
        ]);
    }

    private function getAsync(string $uri): PromiseInterface
    {
        return $this->setClient()->getAsync($uri);
    }

    private function patchAsync(string $uri, array $body): PromiseInterface
    {
        return $this->setClient()->patchAsync($uri, [
            'json' => $body
        ]);
    }

    private function putAsync(string $uri, array $body): PromiseInterface
    {
        return $this->setClient()->putAsync($uri, [
            'json' => $body
        ]);
    }

    private function deleteAsync(string $uri): PromiseInterface
    {
        return $this->setClient()->deleteAsync($uri);
    }

    private function handleResponse(string $responseBody, $returnObject): mixed
    {
        try {
            if ($returnObject) {
                return json_decode($responseBody, false, 512, JSON_THROW_ON_ERROR);
            }
            return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonErrorException $e) {
            $this->logger?->error(
                'Invalid json in ShopwareClient: ' . $e->getMessage(),
                [
                    'body' => $responseBody,
                ]
            );
        }
        return [];
    }

    /**
     * @throws AccessTokenException
     */
    private function getAccessToken($retry = 0): void
    {
        try {
            if (empty($this->accessToken)) {
                $this->getToken();
            } else {
                $this->isTokenExpired()?->getToken();
            }
        } catch (AccessTokenException | GuzzleException) {
            if ($retry > 3) {
                $this->logger?->error('Missing access token');
                throw new AccessTokenException('Access token is missing');
            }
            sleep(1);
            $retry++;
            $this->getAccessToken($retry);
            $this->logger?->info('Reload access token');
        }
    }
}
