<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Batch;

use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Interfaces\Batch\FormatterInterface;
use Symplicity\Outlook\Interfaces\Batch\OStreamInterface;
use Symplicity\Outlook\Interfaces\Entity\BatchWriterEntityInterface;

class InputFormatter implements FormatterInterface
{
    protected const CONTENT_TRANSFER_ENCODING = 'binary';
    protected const CONTENT_TYPE = 'application/http';

    private $logger;
    private $stream;
    private $args;

    public function __construct(LoggerInterface $logger, ?OStreamInterface $stream = null, array $args = [])
    {
        $this->logger = $logger;
        $this->stream = $stream;
        $this->args = $args;
    }

    public function format(BatchWriterEntityInterface $writer): array
    {
        try {
            $streamData = $this->getContents($writer);
            $id = $writer->getId();

            return [
                'name' => $id,
                'contents' => $streamData,
                'headers' => [
                    'Content-Type' => static::CONTENT_TYPE,
                    'Content-Transfer-Encoding' => static::CONTENT_TRANSFER_ENCODING,
                    'Content-ID' => $id
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('unable to generate stream from data provided', [
                'data' => (string) $writer
            ]);
        }

        return [];
    }

    //Mark: Protected
    protected function getContents(BatchWriterEntityInterface $writer): string
    {
        $streamHandler = $this->stream ?? new Stream($writer, $this->args);
        return $streamHandler->create()->getContents();
    }
}
