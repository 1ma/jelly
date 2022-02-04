<?php

declare(strict_types=1);

namespace ABC\Internal;

use ABC\Constants;
use FastRoute;
use Psr\Http\Message;

/**
 * @internal
 */
final class RequestHandlerResolver
{
    private readonly FastRoute\Dispatcher $router;

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
            FastRoute\Dispatcher::NOT_FOUND => Constants::NOT_FOUND_HANDLER->value,
            FastRoute\Dispatcher::METHOD_NOT_ALLOWED => Constants::BAD_METHOD_HANDLER->value,
            FastRoute\Dispatcher::FOUND => $routeInfo[1]
        };

        $request = match ($service) {
            Constants::NOT_FOUND_HANDLER->value => $request,

            Constants::BAD_METHOD_HANDLER->value =>
                $request
                    ->withAttribute(Constants::ALLOWED_METHODS->value, $routeInfo[1]),

            default =>
                $request
                    ->withAttribute(Constants::HANDLER->value, $routeInfo[1])
                    ->withAttribute(Constants::ARGS->value, $routeInfo[2])
        };

        return $service;
    }
}
