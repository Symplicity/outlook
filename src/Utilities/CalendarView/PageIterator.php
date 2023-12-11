<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Utilities\CalendarView;

use Http\Promise\Promise;
use Http\Promise\RejectedPromise;
use InvalidArgumentException;
use Microsoft\Kiota\Abstractions\HttpMethod;
use Microsoft\Kiota\Abstractions\RequestAdapter;
use Microsoft\Kiota\Abstractions\RequestHeaders;
use Microsoft\Kiota\Abstractions\RequestInformation;
use Microsoft\Kiota\Abstractions\Serialization\Parsable;

class PageIterator
{
    private PageResult $currentPage;
    private bool $hasNext = false;
    private int $pauseIndex;
    private RequestHeaders $headers;
    private ?array $requestOptions = [];

    public function __construct(private readonly mixed $response, private readonly RequestAdapter $requestAdapter, private ?array $constructorCallable = null)
    {
        if ($response instanceof Parsable && !$constructorCallable) {
            $constructorCallable = [get_class($response), 'createFromDiscriminatorValue'];
        } elseif ($constructorCallable === null) {
            $constructorCallable = [PageResult::class, 'createFromDiscriminatorValue'];
        }

        $this->constructorCallable = $constructorCallable;
        $this->pauseIndex = 0;
        $this->headers = new RequestHeaders();
    }

    public function setHeaders(array $headers): void
    {
        $this->headers->putAll($headers);
    }

    public function setRequestOptions(array $requestOptions): void
    {
        $this->requestOptions = $requestOptions;
    }

    public function setPauseIndex(int $pauseIndex): void
    {
        $this->pauseIndex = $pauseIndex;
    }

    /**
     * @throws \JsonException
     * @throws \Exception
     */
    public function iterate(callable $callback): void
    {
        $page = $this->convertToPage($this->response);
        if ($page !== null) {
            $this->currentPage = $page;
            $this->hasNext = true;
        }

        while(true) {
            $keepIterating = $this->enumerate($callback);

            if (!$keepIterating) {
                return;
            }

            $nextPage = $this->next();

            if (empty($nextPage)) {
                $this->hasNext = false;
                return;
            }

            $this->currentPage = $nextPage;
            $this->pauseIndex = 0;
        }
    }

    /**
     * @throws \Exception
     */
    public function next(): ?PageResult
    {
        if (empty($this->currentPage->getOdataNextLink())) {
            return null;
        }

        $response = $this->fetchNextPage();
        $result = $response->wait();
        return $this->convertToPage($result);
    }

    public function enumerate(?callable $callback): ?bool
    {
        $keepIterating = true;

        $pageItems = $this->currentPage->getValue();

        if (empty($pageItems)) {
            return false;
        }

        for ($i = $this->pauseIndex; $i < count($pageItems); $i++) {
            $keepIterating = $callback !== null ? $callback($pageItems[$i]) : true;

            if (!$keepIterating) {
                $this->pauseIndex = $i + 1;
                break;
            }
        }
        return $keepIterating;
    }

    public function hasNext(): bool
    {
        return $this->hasNext;
    }

    public function getDeltaLink(): ?string
    {
        return $this->currentPage->getOdataDeltaLink();
    }

    /**
     * @throws \JsonException
     */
    private function convertToPage($response): ?PageResult
    {
        $page = new PageResult();
        if ($response === null) {
            throw new InvalidArgumentException('$response cannot be null');
        }

        $value = null;
        if (is_array($response)) {
            $value = $response['value'] ?? ['value' => []];
        } elseif ($response instanceof Parsable &&
            method_exists($response, 'getValue')) {
            $value = $response->getValue();
        } elseif (is_object($response)) {
            $value = property_exists($response, 'value') ? $response->value : [];
        }

        if ($value === null) {
            throw new InvalidArgumentException('The response does not contain a value.');
        }

        $this->setLinks($page, $response);
        $page->setValue($value);

        return $page;
    }

    /**
     * @throws \JsonException
     */
    private function setLinks(PageResult $page, mixed $response): void
    {
        $parsablePage = ($response instanceof Parsable) ? $response : json_decode(json_encode($response, JSON_THROW_ON_ERROR), true);
        if (is_array($parsablePage)) {
            $page->setOdataNextLink($parsablePage['@odata.nextLink'] ?? '');
            $page->setOdataDeltaLink($parsablePage['@odata.deltaLink'] ?? '');
        } elseif ($parsablePage instanceof Parsable) {
            if (\method_exists($parsablePage, 'getOdataNextLink')) {
                $page->setOdataNextLink($parsablePage->getOdataNextLink());
            }

            if (\method_exists($parsablePage, 'getOdataDeltaLink')) {
                $page->setOdataDeltaLink($parsablePage->getOdataDeltaLink());
            }
        }
    }

    private function fetchNextPage(): Promise
    {
        $nextLink = $this->currentPage->getOdataNextLink();

        if ($nextLink === null) {
            return new RejectedPromise(new InvalidArgumentException('The response does not have a nextLink'));
        }

        if (!filter_var($nextLink, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Could not parse the nextLink url.');
        }

        $requestInfo = new RequestInformation();
        $requestInfo->httpMethod = HttpMethod::GET;
        $requestInfo->setUri($nextLink);
        $requestInfo->setHeaders($this->headers->getAll());
        if ($this->requestOptions !== null) {
            $requestInfo->addRequestOptions(...$this->requestOptions);
        }

        return $this->requestAdapter->sendAsync($requestInfo, $this->constructorCallable);
    }
}
