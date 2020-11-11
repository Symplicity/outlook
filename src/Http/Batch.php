<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Response;
use Symplicity\Outlook\Batch\InputFormatter;
use Symplicity\Outlook\Batch\Response as BatchResponseHandler;
use Symplicity\Outlook\Calendar;
use Symplicity\Outlook\Exception\BatchBoundaryMissingException;
use Symplicity\Outlook\Exception\BatchLimitExceededException;
use Symplicity\Outlook\Exception\BatchRequestEmptyException;
use Symplicity\Outlook\Interfaces\Batch\FormatterInterface;
use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;
use Symplicity\Outlook\Interfaces\Entity\WriterInterface;
use Symplicity\Outlook\Interfaces\Http\BatchConnectionInterface;
use Symplicity\Outlook\Interfaces\Http\RequestOptionsInterface;
use Symplicity\Outlook\Utilities\RequestType;

class Batch extends Connection implements BatchConnectionInterface
{
    public function batch(RequestOptionsInterface $requestOptions, array $args = []): ?BatchResponseHandler
    {
        $boundary = $this->getBatchBoundary($requestOptions);
        $body = $this->getBatchBody($requestOptions);
        $responses = null;

        $batchContent = $this->getParsedBody($body, $args);
        if (count($batchContent) == 0) {
            throw new BatchRequestEmptyException('Batch request is empty');
        }

        $outlookResponse = $this->execBatch($requestOptions, $batchContent, $boundary);
        if ($outlookResponse !== null) {
            $responses = new BatchResponseHandler($outlookResponse, ['eventInfo' => static::$eventInfo]);
        }

        return $responses;
    }

    protected function getParsedBody(array $body, array $args = []): array
    {
        $batchContent = [];
        $upsertInputFormatter = $this->getFormatter($args);
        foreach ($body as $writer) {
            switch (true) {
                case $writer instanceof DeleteInterface:
                    $batchContent[] = $this->prepareBatchDelete($writer, $upsertInputFormatter);
                    break;
                default:
                    $batchContent[] = $this->prepareBatchWrite($writer, $upsertInputFormatter);
            }

            unset($writer);
        }

        return $batchContent;
    }

    private function prepareBatchWrite(WriterInterface $writer, FormatterInterface $upsertInputFormatter): array
    {
        $contentToWrite = [];
        $formattedContent = $upsertInputFormatter->format($writer);
        if (count($formattedContent)) {
            $contentToWrite = $formattedContent;
            static::$eventInfo[$writer->getId()] = [
                'guid' => $writer->getGuid() ?? null,
                'method' => $writer->getMethod(),
                'eventType' => $writer->getInternalEventType(),
                'Sensitivity' => $writer->getSensitivity()
            ];
        }

        return $contentToWrite;
    }

    private function prepareBatchDelete(DeleteInterface $delete, FormatterInterface $upsertInputFormatter): array
    {
        $contentToWrite = [];
        $formattedContent = $upsertInputFormatter->format($delete);
        $internalId = $delete->getId();
        $guid = $delete->getGuid();
        if (count($formattedContent) && $internalId && $guid) {
            $contentToWrite = $formattedContent;
            static::$eventInfo[$internalId] = [
                'guid' => $guid,
                'method' => RequestType::Delete,
                'eventType' => $delete->getInternalEventType(),
                'delete' => true
            ];
        }

        return $contentToWrite;
    }

    protected function execBatch(RequestOptionsInterface $requestOptions, array $batchContent, string $boundary): ?Response
    {
        $responses = null;

        try {
            /** @var Client $client */
            $client = $this->createClientWithRetryHandler($this->upsertRetryDelay());
            $responses = $client->request(RequestType::Post, \Symplicity\Outlook\Http\Request::getBatchApi(), [
                'headers' => $requestOptions->getHeaders(),
                'body' => new MultipartStream($batchContent, $boundary)
            ]);
        } catch (\Exception $e) {
            // If we get a client error, skip the response
        }

        return $responses;
    }

    // Mark: Protected
    protected function getFormatter(array $args = []): FormatterInterface
    {
        if (isset($args['batchInputFormatter']) && $args['batchInputFormatter'] instanceof FormatterInterface) {
            $upsertInputFormatter = $args['batchInputFormatter'];
        } else {
            $upsertInputFormatter = new InputFormatter($this->logger);
        }

        return $upsertInputFormatter;
    }

    // Mark: Private
    private function getBatchBoundary(RequestOptionsInterface $requestOptions): string
    {
        if (($boundary = $requestOptions->getBatchBoundary()) === null) {
            throw new BatchBoundaryMissingException('batch boundary id is missing');
        }

        return $boundary;
    }

    private function getBatchBody(RequestOptionsInterface $requestOptions): array
    {
        $body = $requestOptions->getBody();
        if (count($body) > Calendar::BATCH_BY) {
            throw new BatchLimitExceededException('batch maximum limit of 20 items was exceeded');
        }

        return $body;
    }
}
