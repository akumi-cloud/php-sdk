# Akumi PHP SDK

The official PHP client for [Akumi](https://akumi.cloud), the EU-sovereign,
OpenAI-compatible inference API. One `base_url` for every model, governed and
metered, with your regulated data kept in the EU.

- **Drop-in OpenAI-compatible.** Chat completions, embeddings, and models under one key.
- **EU-sovereign by default.** Requests run on EU-resident models unless you explicitly allow otherwise; the egress guard fails closed.
- **Governed, not just hosted.** PII firewall, per-request residency, and a metadata-only audit trail come with every call.
- **Dependable.** Streaming responses and automatic retries on transient errors.

## Requirements

PHP 8.2 or newer.

## Install

```bash
composer require akumi-cloud/sdk
```

## Quickstart

Create an API key under **app.akumi.cloud -> Platform -> API keys**, then:

```php
use Akumi\Sdk\Akumi;

$akumi = Akumi::fromApiKey('mk_...');

$response = $akumi->chat->create([
    'model' => 'mistral/mistral-large-latest',
    'messages' => [
        ['role' => 'user', 'content' => 'Explain EU data residency in one sentence.'],
    ],
]);

echo $response['choices'][0]['message']['content'];
```

## Streaming

`createStreamed()` returns a generator of OpenAI-compatible chunks:

```php
foreach ($akumi->chat->createStreamed([
    'model' => 'mistral/mistral-large-latest',
    'messages' => [['role' => 'user', 'content' => 'Write a haiku about Frankfurt.']],
]) as $chunk) {
    echo $chunk['choices'][0]['delta']['content'] ?? '';
}
```

## Embeddings

```php
$embeddings = $akumi->embeddings->create([
    'model' => 'mistral/mistral-embed',
    'input' => 'The quarterly report is ready for review.',
]);

$vector = $embeddings['data'][0]['embedding'];
```

## More resources

| Resource | Call |
|---|---|
| List available models | `$akumi->models->list()` |
| Long-term memory | `$akumi->memory->forget([...])` |
| Conversation threads | `$akumi->memoryThreads->list()` |
| Audit logs | `$akumi->auditLogs->list()` |

## Configuration

`fromApiKey()` connects to `https://api.akumi.cloud/v1` and retries transient
failures (429, 502, 503, 504) automatically. Pass a base URL to target another
host.

## Documentation

- Guides: https://akumi.cloud/docs
- API reference: https://akumi.cloud/docs/api-reference

## About

This SDK is generated from the Akumi OpenAPI specification, so it tracks the API
automatically. Found a bug? Open an issue at
https://github.com/akumi-cloud/php-sdk.

MIT licensed.
