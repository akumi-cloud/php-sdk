<?php

declare(strict_types=1);

namespace Akumi\Sdk\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Config
{
    /**
     * @param  string  $apiKey  Bearer token sent on every request.
     * @param  string  $baseUrl  API base URL (no trailing slash needed).
     * @param  int  $maxRetries  Maximum number of retry attempts on transient errors.
     * @param  list<int>  $retryOn  HTTP status codes that trigger a retry.
     * @param  \Psr\Http\Client\ClientInterface|null  $httpClient  Optional PSR-18 client override.
     * @param  \Psr\Http\Message\RequestFactoryInterface|null  $requestFactory  Optional PSR-17 request factory override.
     * @param  \Psr\Http\Message\StreamFactoryInterface|null  $streamFactory  Optional PSR-17 stream factory override.
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl = 'https://api.akumi.cloud',
        public readonly int $maxRetries = 2,
        public readonly array $retryOn = [429, 500, 502, 503, 504],
        public readonly ?ClientInterface $httpClient = null,
        public readonly ?RequestFactoryInterface $requestFactory = null,
        public readonly ?StreamFactoryInterface $streamFactory = null,
    ) {}
}
