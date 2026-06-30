<?php

declare(strict_types=1);

namespace Akumi\Sdk\Client;

use Akumi\Sdk\Exceptions\ApiException;
use Akumi\Sdk\Exceptions\AuthenticationException;
use Akumi\Sdk\Exceptions\InvalidRequestException;
use Akumi\Sdk\Exceptions\RateLimitException;
use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds and sends HTTP requests for the SDK: bearer auth, JSON encoding,
 * status-to-exception mapping, retries, and incremental SSE reads.
 */
final class Transport
{
    private ClientInterface $client;

    private RequestFactoryInterface $requests;

    private StreamFactoryInterface $streams;

    public function __construct(private readonly Config $config)
    {
        $this->client = $config->httpClient ?? Psr18ClientDiscovery::find();
        $factory = new Psr17Factory;
        $this->requests = $config->requestFactory ?? $factory;
        $this->streams = $config->streamFactory ?? $factory;
    }

    /**
     * @param  array<string, scalar|null>|null  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws \Akumi\Sdk\Exceptions\ApiException
     */
    public function send(string $method, string $path, ?array $query = null, ?array $body = null): array
    {
        $response = $this->dispatch($method, $path, $query, $body);
        $raw = (string) $response->getBody();

        if ($raw === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return \Generator<int, array<string, mixed>>
     *
     * @throws \Akumi\Sdk\Exceptions\ApiException
     */
    public function stream(string $method, string $path, ?array $body = null): \Generator
    {
        $response = $this->dispatch($method, $path, null, $body, stream: true);
        $streamBody = $response->getBody();
        $buffer = '';

        while (! $streamBody->eof()) {
            $buffer .= $streamBody->read(8192);

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $newlinePos), "\r");
                $buffer = substr($buffer, $newlinePos + 1);

                if (! str_starts_with($line, 'data:')) {
                    continue;
                }
                $data = trim(substr($line, 5));

                if ($data === '' || $data === '[DONE]') {
                    continue;
                }

                /** @var array<string, mixed> $event */
                $event = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                yield $event;
            }
        }

        $tail = rtrim($buffer, "\r");

        if (str_starts_with($tail, 'data:')) {
            $data = trim(substr($tail, 5));

            if ($data !== '' && $data !== '[DONE]') {
                /** @var array<string, mixed> $event */
                $event = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                yield $event;
            }
        }
    }

    /**
     * @param  array<string, scalar|null>|null  $query
     * @param  array<string, mixed>|null  $body
     *
     * @throws \Akumi\Sdk\Exceptions\ApiException
     */
    private function dispatch(string $method, string $path, ?array $query, ?array $body, bool $stream = false): \Psr\Http\Message\ResponseInterface
    {
        $url = rtrim($this->config->baseUrl, '/') . $path;

        if ($query !== null && $query !== []) {
            $url .= '?' . http_build_query(array_filter($query, static fn ($v): bool => $v !== null));
        }

        $attempt = 0;
        do {
            $request = $this->requests->createRequest($method, $url)
                ->withHeader('Authorization', 'Bearer ' . $this->config->apiKey)
                ->withHeader('Accept', $stream ? 'text/event-stream' : 'application/json');

            if ($body !== null) {
                $request = $request
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($this->streams->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
            }

            $response = $this->client->sendRequest($request);
            $status = $response->getStatusCode();

            if ($status < 400) {
                return $response;
            }

            $shouldRetry = $attempt < $this->config->maxRetries && \in_array($status, $this->config->retryOn, true);

            if ($shouldRetry) {
                $attempt++;
                usleep((int) (250_000 * (2 ** ($attempt - 1))));

                continue;
            }

            throw $this->mapError($status, (string) $response->getBody());
        } while (true);
    }

    private function mapError(int $status, string $raw): ApiException
    {
        $body = [];

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }
        $message = \is_string($body['error']['message'] ?? null) ? $body['error']['message'] : "HTTP {$status}";

        return match (true) {
            $status === 401, $status === 403 => new AuthenticationException($message, $status, $body),
            $status === 429 => new RateLimitException($message, $status, $body),
            $status >= 400 && $status < 500 => new InvalidRequestException($message, $status, $body),
            default => new ApiException($message, $status, $body),
        };
    }
}
