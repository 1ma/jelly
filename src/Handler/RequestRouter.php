<?php

declare(strict_types=1);

namespace ABC\Handler;

use ABC\Constants;
use ABC\Util\Assert;
use ABC\Util\Seam;
use FastRoute;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use TypeError;

final class RequestRouter implements Server\RequestHandlerInterface
{
    /**
     * @var Container\ContainerInterface
     */
    private $container;

    /**
     * @var FastRoute\Dispatcher
     */
    private $dispatcher;

    /**
     * @var string[]
     */
    private $middlewares;

    public function __construct(
        Container\ContainerInterface $container,
        FastRoute\DataGenerator $generator,
        string ...$middlewares
    )
    {
        $this->container = $container;
        $this->dispatcher = new FastRoute\Dispatcher\GroupCountBased($generator->getData());
        $this->middlewares = $middlewares;
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     * @throws TypeError
     */
    public function handle(
        Message\ServerRequestInterface $request
    ): Message\ResponseInterface
    {
        $routeInfo = $this->dispatcher->dispatch(
            $request->getMethod(), $request->getUri()->getPath()
        );

        if (FastRoute\Dispatcher::NOT_FOUND === $routeInfo[0]) {
            $fourOhFour = Assert::isARequestHandler($this->container->get(Constants::NOT_FOUND_HANDLER));

            return $fourOhFour->handle(
                $request->withAttribute(Constants::ERROR_TYPE, 404)
            );
        }

        if (FastRoute\Dispatcher::METHOD_NOT_ALLOWED === $routeInfo[0]) {
            $fourOhFive = Assert::isARequestHandler($this->container->get(Constants::BAD_METHOD_HANDLER));

            return $fourOhFive->handle(
                $request
                    ->withAttribute(Constants::ERROR_TYPE, 405)
                    ->withAttribute(Constants::ALLOWED_METHODS, $routeInfo[1])
            );
        }

        return Seam::compose(
            $this->container->get($routeInfo[1]),
            ...\array_map(function(string $service) {
                return $this->container->get($service);
            }, $this->middlewares)
        )->handle(
            $request
                ->withAttribute(Constants::HANDLER, $routeInfo[1])
                ->withAttribute(Constants::ARGS, $routeInfo[2])
        );
    }
}
