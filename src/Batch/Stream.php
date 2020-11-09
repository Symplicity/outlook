<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Batch;

use GuzzleHttp\Psr7\AppendStream;
use Symplicity\Outlook\Interfaces\Batch\OStreamInterface;
use function GuzzleHttp\Psr7\stream_for;
use Psr\Http\Message\StreamInterface;
use Symplicity\Outlook\Http\Request;
use Symplicity\Outlook\Interfaces\Entity\BatchWriterEntityInterface;

class Stream implements OStreamInterface
{
    protected const HTTP_VERSION = 'HTTP/1.1';
    protected const DEFAULT_CONTENT_TYPE = 'application/json';

    private $writer;
    private $args;

    public function __construct(BatchWriterEntityInterface $writer, array $args = [])
    {
        $this->writer = $writer;
        $this->args = $args;
    }

    public function create(): StreamInterface
    {
        $stream = new AppendStream();
        $stream->addStream(stream_for($this->getRequestLine()));
        $stream->addStream(stream_for("\r\n"));
        $stream->addStream(stream_for($this->getContentTypeHeader()));
        $stream->addStream(stream_for("\r\n\r\n"));
        $stream->addStream(stream_for(json_encode($this->writer)));
        $stream->addStream(stream_for("\r\n"));
        return $stream;
    }

    // Mark: Protected
    protected function getRequestLine(): string
    {
        return sprintf(
            '%s %s %s',
            $this->writer->getMethod(),
            self::getRequestUrl() . $this->writer->getUrl() . $this->selectByQueryParameter(),
            static::HTTP_VERSION
        );
    }

    protected function getContentTypeHeader(): string
    {
        $contentType = $this->args['content-type'] ?? static::DEFAULT_CONTENT_TYPE;
        return "Content-Type: {$contentType}";
    }

    protected function selectByQueryParameter(): string
    {
        return '?$select=Id,Subject,WebLink,Type,SeriesMasterId,LastModifiedDateTime';
    }

    // Mark: static
    protected static function getRequestUrl(): string
    {
        return DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . Request::OUTLOOK_VERSION;
    }
}
