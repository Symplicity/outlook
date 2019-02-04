<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Http;

use Generator;
use Symplicity\Outlook\Exception\ResponseIteratorException;
use Symplicity\Outlook\Interfaces\Http\ConnectionInterface;
use Symplicity\Outlook\Interfaces\Http\RequestOptionsInterface;
use Symplicity\Outlook\Interfaces\Http\ResponseIteratorInterface;
use Symplicity\Outlook\Utilities\ResponseHandler;

class ResponseIterator implements ResponseIteratorInterface
{
    public const NextPageLink = '@odata.nextLink';
    public const DeltaLink = '@odata.deltaLink';
    public const SkipTokenLink = '@odata.skipToken';
    public const ItemsKey = 'value';

    protected $connection;
    protected $urlParams;
    protected $firstPage;
    /** @var RequestOptionsInterface $requestOptions */
    protected $requestOptions;
    protected $deltaLink;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function setItems(string $url, RequestOptionsInterface $requestOptions) : ResponseIteratorInterface
    {
        $this->requestOptions = $requestOptions;
        $this->requestOptions->addPreferenceHeaders([
            'odata.maxpagesize=1',
            'odata.track-changes',
            'outlook.timezone="' . $this->requestOptions->getPreferredTimezone() . '"'
        ]);

        $this->firstPage = $this->getPage($url);
        return $this;
    }

    public function each() : ?Generator
    {
        $page = $this->firstPage;

        if (isset($page[static::DeltaLink])) {
            $page[static::NextPageLink] = $page[static::DeltaLink];
            unset($page[static::DeltaLink]);
        }

        while (isset($page[static::NextPageLink])) {
            $this->requestOptions->resetUUID();
            $this->requestOptions->addPreferenceHeaders([
                'odata.maxpagesize=50',
                'odata.track-changes',
                'outlook.timezone="' . $this->requestOptions->getPreferredTimezone() . '"'
            ]);

            $page = $this->getPage($page[static::NextPageLink]);

            // Loop complete if we get a deltaLink
            if (isset($page[static::DeltaLink])) {
                $this->saveDeltaLink($page[static::DeltaLink]);
            }

            $counter = \count($page[static::ItemsKey]) ?? 0;
            for ($i = 0; $i < $counter; $i++) {
                yield $page[static::ItemsKey][$i];
            }
        }
    }

    private function getPage(string $url) : array
    {
        try {
            $response = $this->connection->get($url, $this->requestOptions);
            return ResponseHandler::toArray($response);
        } catch (\Exception $e) {
            throw (new ResponseIteratorException(
                $e->getMessage(),
                $e->getCode()));
        }
    }

    private function saveDeltaLink(string $url) : void
    {
        $this->deltaLink = $url;
    }

    public function getDeltaLink() : string
    {
        return $this->deltaLink;
    }
}
