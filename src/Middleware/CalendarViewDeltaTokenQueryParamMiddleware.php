<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;

final class CalendarViewDeltaTokenQueryParamMiddleware
{
    private const SKIP_TOKEN_STRING = '$skiptoken';
    private const DELTA_TOKEN_STRING = '$deltaToken';

    /** @var callable $nextHandler */
    private $nextHandler;

    /**
     * @param array<string, mixed> $args
     */
    public function __construct(callable $nextHandler, private readonly array $args = [])
    {
        $this->nextHandler = $nextHandler;
    }

    /**
     * @param RequestInterface $request
     * @param array<string, mixed> $options
     * @return PromiseInterface
     */
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

    /**
     * @param array<string, mixed> $options
     * @return callable
     */
    public static function init(array $options = []): callable
    {
        return static fn (callable $handler): self => new self($handler, $options);
    }
}
