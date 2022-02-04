<?php

declare(strict_types=1);

namespace ABC\Internal;

use ABC\Constants;
use ABC\Internal;
use FastRoute;
use LogicException;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use RuntimeException;
use TypeError;

/**
 * @internal
 */
final class RequestRouter
{
    private readonly Container\ContainerInterface $container;
    private readonly FastRoute\Dispatcher $router;

    public function __construct(
        Container\ContainerInterface $container,
        FastRoute\DataGenerator $routeCollection
    )
    {
        $this->container = $container;
        $this->router = new FastRoute\Dispatcher\GroupCountBased($routeCollection->getData());
    }

    /**
     * @throws LogicException If the resolved service is not an implementation of the RequestHandlerInterface
     */
    public function resolve(Message\ServerRequestInterface &$request): Server\RequestHandlerInterface
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

        try {
            return Internal\Assert::isRequestHandler($this->container->get($service));
        } catch (Container\ContainerExceptionInterface) {
            throw new LogicException("Error retrieving '$service' from container");
        } catch (TypeError) {
            throw new LogicException("'$service' service does not implement RequestHandlerInterface");
        }
    }
}
