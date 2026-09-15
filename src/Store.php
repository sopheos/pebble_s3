<?php

namespace Pebble\S3;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class Store
{
    const DEFAULT_TYPE = 'application/octet-stream';

    private ?S3Client $client = null;
    private ?string $bucket = null;

    public function setClient(S3Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function client(): S3Client
    {
        if ($this->client === null) {
            throw new RuntimeException('client_undefined');
        }

        return $this->client;
    }

    public function setBucket(string $bucket): static
    {
        $this->bucket = $bucket;
        return $this;
    }

    public function getBucket(): string
    {
        if ($this->bucket === null) {
            throw new RuntimeException('bucket_undefined');
        }

        return $this->bucket;
    }

    public function createBucket(): bool
    {
        $bucket = $this->getBucket();

        try {
            // bucket already exists
            $this->client->headBucket(['Bucket' => $bucket]);
            return false;
        } catch (AwsException $ex) {
            // create
            if ($ex->getStatusCode() === 404) {
                $this->client->createBucket(['Bucket' => $bucket]);
                return true;
            } else {
                throw $ex;
            }
        }
    }

    public function has(string $key): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->getBucket(),
                'Key'    => $key,
            ]);
            return true;
        } catch (AwsException $ex) {
            if ($ex->getStatusCode() === 404) {
                return false;
            }
            throw $ex;
        }
    }

    public function get(string $key): ?Item
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->getBucket(),
                'Key'    => $key,
            ]);

            return Item::fromResult($result);
        } catch (AwsException $ex) {
            if ($ex->getStatusCode() === 404) {
                return null;
            }
            throw $ex;
        }
    }

    public function setFile(string $key, string $path, string $contentType = self::DEFAULT_TYPE): ?string
    {
        if (str_contains($path, "\0") || !is_file($path)) {
            throw new InvalidArgumentException('invalid_file_path');
        }

        $this->client->putObject([
            'Bucket'      => $this->getBucket(),
            'Key'         => $key,
            'SourceFile'  => $path,
            'ContentType' => mime_content_type($path) ?: $contentType,
        ]);

        return $key;
    }

    public function set(string $key, mixed $data, string $contentType = self::DEFAULT_TYPE): ?string
    {
        if (! (is_resource($data) || is_string($data) || $data instanceof StreamInterface)) {
            throw new InvalidArgumentException('unsupported_file');
        }

        $this->client->putObject([
            'Bucket'      => $this->getBucket(),
            'Key'         => $key,
            'Body'        => $data,
            'ContentType' => $contentType,
        ]);

        return $key;
    }

    public function delete(string $key): bool
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->getBucket(),
                'Key'    => $key,
            ]);

            return true;
        } catch (AwsException $ex) {
            if ($ex->getStatusCode() === 404) {
                return true;
            }
            throw $ex;
        }
    }

    public function deleteMany(array $keys): array
    {
        if (! $keys) {
            return [];
        }

        $bucket = $this->getBucket();
        $errors = [];

        // S3 limite à 1000 clés par requête DeleteObjects
        foreach (array_chunk($keys, 1000) as $chunk) {
            $result = $this->client->deleteObjects([
                'Bucket' => $bucket,
                'Delete' => [
                    'Objects' => array_map(fn(string $key) => ['Key' => $key], $chunk),
                    'Quiet'   => true, // ne renvoie que les erreurs, pas les succès
                ],
            ]);

            foreach ($result['Errors'] ?? [] as $error) {
                $errors[] = $error['Key'];
            }
        }

        return $errors; // clés qui n'ont PAS pu être supprimées
    }

    public function list(string $prefix = ''): iterable
    {
        $paginator = $this->client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->getBucket(),
            'Prefix' => $prefix,
        ]);
        foreach ($paginator as $page) {
            foreach ($page['Contents'] ?? [] as $object) {
                yield $object['Key'];
            }
        }
    }
}
