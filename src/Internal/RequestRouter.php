<?php

declare(strict_types=1);

namespace ABC\Internal;

use ABC\Internal;
use ABC\Kernel;
use FastRoute;
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
     * @throws RuntimeException If the resolved service is not an implementation of the RequestHandlerInterface
     */
    public function resolve(Message\ServerRequestInterface &$request): Server\RequestHandlerInterface
    {
        $routeInfo = $this->router->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        $service = match ($routeInfo[0]) {
            FastRoute\Dispatcher::NOT_FOUND => Kernel::NOT_FOUND_HANDLER_SERVICE,
            FastRoute\Dispatcher::METHOD_NOT_ALLOWED => Kernel::BAD_METHOD_HANDLER_SERVICE,
            FastRoute\Dispatcher::FOUND => $routeInfo[1]
        };

        $request = match ($service) {
            Kernel::NOT_FOUND_HANDLER_SERVICE =>
                $request
                    ->withAttribute(Kernel::ERROR_TYPE, 404),

            Kernel::BAD_METHOD_HANDLER_SERVICE =>
                $request
                    ->withAttribute(Kernel::ERROR_TYPE, 405)
                    ->withAttribute(Kernel::ALLOWED_METHODS, $routeInfo[1]),

            default =>
                $request
                    ->withAttribute(Kernel::HANDLER, $routeInfo[1])
                    ->withAttribute(Kernel::ARGS, $routeInfo[2])
        };

        try {
            return Internal\Assert::isRequestHandler($this->container->get($service));
        } catch (Container\ContainerExceptionInterface) {
            throw new RuntimeException("Error retrieving '$service' from container");
        } catch (TypeError) {
            throw new RuntimeException("'$service' service does not implement RequestHandlerInterface");
        }
    }
}
