<?php

declare(strict_types=1);

namespace ABC\Handler;

use ABC\Constants;
use ABC\Util\Assert;
use FastRoute;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use TypeError;
use function assert;

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
     * @throws Container\NotFoundExceptionInterface
     * @throws TypeError
     */
    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        $routeInfo = $this->router->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        if (FastRoute\Dispatcher::FOUND === $routeInfo[0]) {
            $handler = Assert::isARequestHandler($this->container->get($routeInfo[1]));
            $request = $request
                ->withAttribute(Constants::HANDLER, $routeInfo[1])
                ->withAttribute(Constants::ARGS, $routeInfo[2]);
        }

        if (FastRoute\Dispatcher::NOT_FOUND === $routeInfo[0]) {
            $handler = Assert::isARequestHandler($this->container->get(Constants::NOT_FOUND_HANDLER));
            $request = $request
                ->withAttribute(Constants::ERROR_TYPE, 404);
        }

        if (FastRoute\Dispatcher::METHOD_NOT_ALLOWED === $routeInfo[0]) {
            $handler = Assert::isARequestHandler($this->container->get(Constants::BAD_METHOD_HANDLER));
            $request = $request
                ->withAttribute(Constants::ERROR_TYPE, 405)
                ->withAttribute(Constants::ALLOWED_METHODS, $routeInfo[1]);
        }

        assert(isset($handler));

        return $handler->handle($request);
    }
}
