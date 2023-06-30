<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Batch;

use GuzzleHttp\Psr7\AppendStream;
use Symplicity\Outlook\Interfaces\Batch\OStreamInterface;
use GuzzleHttp\Psr7\Utils;
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
        $stream->addStream(Utils::streamFor($this->getRequestLine()));
        $stream->addStream(Utils::streamFor("\r\n"));
        $stream->addStream(Utils::streamFor($this->getContentTypeHeader()));
        $stream->addStream(Utils::streamFor("\r\n\r\n"));
        $stream->addStream(Utils::streamFor(json_encode($this->writer)));
        $stream->addStream(Utils::streamFor("\r\n"));
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
