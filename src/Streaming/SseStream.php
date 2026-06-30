<?php

declare(strict_types=1);

namespace Akumi\Sdk\Streaming;

/**
 * Marker/utility namespace for streaming. The incremental SSE parsing lives in
 * Transport::stream(); this class exposes the [DONE] sentinel used by callers
 * and generated code that needs to reason about stream termination.
 */
final class SseStream
{
    public const string DONE = '[DONE]';
}
