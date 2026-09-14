<?php

namespace Pebble\S3;

use Aws\Result;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class Item
{
    public const DEFAULT_TYPE = 'application/octet-stream';

    public StreamInterface $body;
    public string $type = self::DEFAULT_TYPE;
    public int $length = 0;
    public int $date = 0;
    public ?string $etag = null;

    public static function fromResult(Result $result): static
    {
        $item = new static();

        $item->body   = $result['Body'];
        $item->type   = $result['ContentType'] ?? self::DEFAULT_TYPE;
        $item->length = (int) ($result['ContentLength'] ?? 0);
        $item->etag   = isset($result['ETag']) ? trim($result['ETag'], '"') : null;

        if (isset($result['LastModified'])) {
            $item->date = $result['LastModified']->getTimestamp();
        }

        return $item;
    }

    public function body(bool $rewind = true): StreamInterface
    {
        if ($rewind && $this->body->isSeekable()) {
            $this->body->rewind();
        }

        return $this->body;
    }

    /**
     * @return resource
     */
    public function createResource()
    {
        $resource = $this->body(true)->detach();

        if (!is_resource($resource)) {
            throw new RuntimeException('not_resource');
        }

        return $resource;
    }
}
