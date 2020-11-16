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
use Symplicity\Outlook\Http\Request as OutlookHttpRequest;

class Batch extends Connection implements BatchConnectionInterface
{
    public function post(RequestOptionsInterface $requestOptions, array $args = []): ?BatchResponseHandler
    {
        $boundary = $this->getBoundary($requestOptions);
        $body = $this->getBody($requestOptions);
        $responses = null;

        $batchContent = $this->getParsedBody($body, $args);
        if (count($batchContent) == 0) {
            throw new BatchRequestEmptyException('Batch request is empty');
        }

        $outlookResponse = $this->exec($requestOptions, $batchContent, $boundary);
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
                    $batchContent[] = $this->prepareDelete($writer, $upsertInputFormatter);
                    break;
                default:
                    $batchContent[] = $this->prepareWrite($writer, $upsertInputFormatter);
            }

            unset($writer);
        }

        return $batchContent;
    }

    private function prepareWrite(WriterInterface $writer, FormatterInterface $upsertInputFormatter): array
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

    private function prepareDelete(DeleteInterface $delete, FormatterInterface $upsertInputFormatter): array
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

    protected function exec(RequestOptionsInterface $requestOptions, array $batchContent, string $boundary): ?Response
    {
        $responses = null;

        try {
            /** @var Client $client */
            $client = $this->createClientWithRetryHandler($this->upsertRetryDelay());
            $responses = $client->request(RequestType::Post, OutlookHttpRequest::getBatchApi(), [
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
    private function getBoundary(RequestOptionsInterface $requestOptions): string
    {
        if (($boundary = $requestOptions->getBatchBoundary()) === null) {
            throw new BatchBoundaryMissingException('batch boundary id is missing');
        }

        return $boundary;
    }

    private function getBody(RequestOptionsInterface $requestOptions): array
    {
        $body = $requestOptions->getBody();
        if (count($body) > Calendar::BATCH_BY) {
            throw new BatchLimitExceededException('batch maximum limit of 20 items was exceeded');
        }

        return $body;
    }
}
