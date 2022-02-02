<?php

declare(strict_types=1);

namespace ABC\Handlers;

use ABC\Internal;
use ABC\Kernel;
use FastRoute;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use TypeError;

final class RequestRouter implements Server\RequestHandlerInterface
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
     * @throws Container\ContainerExceptionInterface
     * @throws Container\NotFoundExceptionInterface
     * @throws TypeError
     */
    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        $routeInfo = $this->router->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        $handler = match ($routeInfo[0]) {
            FastRoute\Dispatcher::FOUND => Internal\Assert::isRequestHandler($this->container->get($routeInfo[1])),
            FastRoute\Dispatcher::NOT_FOUND => Internal\Assert::isRequestHandler($this->container->get(Kernel::NOT_FOUND_HANDLER_SERVICE)),
            FastRoute\Dispatcher::METHOD_NOT_ALLOWED => Internal\Assert::isRequestHandler($this->container->get(Kernel::BAD_METHOD_HANDLER_SERVICE))
        };

        $request = match ($routeInfo[0]) {
            FastRoute\Dispatcher::FOUND =>
                $request
                    ->withAttribute(Kernel::HANDLER, $routeInfo[1])
                    ->withAttribute(Kernel::ARGS, $routeInfo[2]),
            FastRoute\Dispatcher::NOT_FOUND =>
                $request
                    ->withAttribute(Kernel::ERROR_TYPE, 404),
            FastRoute\Dispatcher::METHOD_NOT_ALLOWED =>
                $request
                    ->withAttribute(Kernel::ERROR_TYPE, 405)
                    ->withAttribute(Kernel::ALLOWED_METHODS, $routeInfo[1])
        };

        return $handler->handle($request);
    }
}
