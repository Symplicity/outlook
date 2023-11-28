<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;

class CalendarViewDeltaTokenQueryParamMiddleware
{
    private const SKIP_TOKEN_STRING = '$skiptoken';
    private const DELTA_TOKEN_STRING = '$deltaToken';

    private $nextHandler;

    public function __construct(callable $nextHandler, private readonly array $args = [])
    {
        $this->nextHandler = $nextHandler;
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        $path = $request->getUri()->getQuery();

        if (isset($this->args['deltaToken'])
            && !str_contains($path, self::SKIP_TOKEN_STRING)) {
            $uri = Uri::withQueryValue(
                $request->getUri(),
                self::DELTA_TOKEN_STRING,
                $this->args['deltaToken']
            );

            $request = $request->withUri($uri);
        }

        return \call_user_func($this->nextHandler, $request, $options);
    }

    public static function init(array $options = []): callable
    {
        return static fn (callable $handler): static => new static($handler, $options);
    }
}
