<?php

declare(strict_types=1);

namespace ABC\Middleware;

use ABC\Constants;
use ABC\Util\Assert;
use FastRoute;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use TypeError;

final class RequestRouter implements Server\MiddlewareInterface
{
    /**
     * @var Container\ContainerInterface
     */
    private $container;

    /**
     * @var FastRoute\Dispatcher
     */
    private $dispatcher;

    public function __construct(
        Container\ContainerInterface $container,
        FastRoute\DataGenerator $generator
    )
    {
        $this->container = $container;
        $this->dispatcher = new FastRoute\Dispatcher\GroupCountBased($generator->getData());
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     * @throws TypeError
     */
    public function process(
        Message\ServerRequestInterface $request,
        Server\RequestHandlerInterface $handler
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

        return $handler->handle(
            $request
                ->withAttribute(Constants::HANDLER, $routeInfo[1])
                ->withAttribute(Constants::ARGS, $routeInfo[2])
        );
    }
}
