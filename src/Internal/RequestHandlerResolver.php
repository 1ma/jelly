<?php

declare(strict_types=1);

namespace Jelly\Internal;

use FastRoute;
use Jelly\Constants;
use Psr\Http\Message;

/**
 * @internal
 */
final readonly class RequestHandlerResolver
{
    private FastRoute\Dispatcher $router;

    public function __construct(FastRoute\DataGenerator $routeCollection)
    {
        $this->router = new FastRoute\Dispatcher\GroupCountBased($routeCollection->getData());
    }

    public function resolve(Message\ServerRequestInterface &$request): string
    {
        $routeInfo = $this->router->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        $service = match ($routeInfo[0]) {
            FastRoute\Dispatcher::NOT_FOUND => Constants\Services::NOT_FOUND_HANDLER->value,
            FastRoute\Dispatcher::METHOD_NOT_ALLOWED => Constants\Services::BAD_METHOD_HANDLER->value,
            FastRoute\Dispatcher::FOUND => $routeInfo[1]
        };

        $request = match ($service) {
            Constants\Services::NOT_FOUND_HANDLER->value => $request,

            Constants\Services::BAD_METHOD_HANDLER->value => $request
                ->withAttribute(Constants\Attributes::ALLOWED_METHODS->value, $routeInfo[1]),

            default => $request
                ->withAttribute(Constants\Attributes::HANDLER->value, $routeInfo[1])
                ->withAttribute(Constants\Attributes::ARGS->value, $routeInfo[2])
        };

        return $service;
    }
}
