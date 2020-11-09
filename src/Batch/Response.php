<?php
declare(strict_types=1);

namespace Symplicity\Outlook\Batch;

use Closure;
use Psr\Http\Message\ResponseInterface as OutlookResponseInterface;
use Symplicity\Outlook\Entities\BatchErrorEntity;
use Symplicity\Outlook\Entities\BatchResponseDeleteEntity;
use Symplicity\Outlook\Entities\BatchResponseReader;
use Symplicity\Outlook\Interfaces\Batch\ResponseInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Utilities\RequestType;
use Symplicity\Outlook\Utilities\ResponseHandler;
use IteratorAggregate;

class Response implements ResponseInterface, IteratorAggregate
{
    private $response = [];
    private $outlookResponse;
    protected $args = [];

    public function __construct(OutlookResponseInterface $response, array $args = [])
    {
        $this->outlookResponse = $response;
        $this->args = $args;
    }

    public function getIterator()
    {
        $this->setResponse();
        return new \ArrayIterator($this->response);
    }

    //Mark: Protected
    protected function setResponse()
    {
        $responses = ResponseHandler::toArray($this->outlookResponse);
        if (isset($responses['responses']) && is_array($responses['responses'])) {
            foreach ($responses['responses'] as $resp) {
                $this->generateResponseItems($resp);
            }
        }
    }

    protected function generateResponseItems(array $response): void
    {
        $statusCode = $response['status'] ?? 0;
        $body = $response['body'] ?? [];
        $id = $response['id'] ?? null;
        if (empty($body['error'])) {
            $items = $this->args['eventInfo'][$id] ?? [];
            $items = array_merge($items, ['statusCode' => $statusCode]);
            if ($statusCode === 204 && $items['method'] === RequestType::Delete) {
                $this->response[$id] = [
                    'response' => new BatchResponseDeleteEntity($items['guid'], $response['id']),
                    'item' => $items
                ];

                return;
            }

            $this->response[$id] = [
                'response' => $this->getResponseReader($body),
                'item' => $items
            ];

            return;
        }

        $this->response[$id] = [
            'response' => new BatchErrorEntity($response),
            'item' => $this->args['eventInfo'][$id] ?? []
        ];
    }

    protected function getResponseReader(array $body): ReaderEntityInterface
    {
        if (isset($this->args['batchResponseReader']) && $this->args['batchResponseReader'] instanceof Closure) {
            $batchReader = $this->args['batchResponseReader']->call($this, $body);
            if ($batchReader instanceof ReaderEntityInterface) {
                return $batchReader;
            }
        }

        return new BatchResponseReader($body);
    }
}
