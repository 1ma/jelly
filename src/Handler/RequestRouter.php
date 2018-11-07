<?php

declare(strict_types=1);

namespace ABC\Handler;

use ABC\Constants;
use ABC\Util\Assert;
use ABC\Util\Dictionary;
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
    private $router;

    /**
     * @var Dictionary
     */
    private $dictionary;

    public function __construct(
        Container\ContainerInterface $container,
        FastRoute\DataGenerator $routeCollection,
        Dictionary $dictionary
    )
    {
        $this->container = $container;
        $this->router = new FastRoute\Dispatcher\GroupCountBased($routeCollection->getData());
        $this->dictionary = $dictionary;
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

        if (FastRoute\Dispatcher::NOT_FOUND === $routeInfo[0]) {
            return Assert::isARequestHandler($this->container->get(Constants::NOT_FOUND_HANDLER))
                ->handle($request->withAttribute(Constants::ERROR_TYPE, 404));
        }

        if (FastRoute\Dispatcher::METHOD_NOT_ALLOWED === $routeInfo[0]) {
            return Assert::isARequestHandler($this->container->get(Constants::BAD_METHOD_HANDLER))
                ->handle(
                    $request
                        ->withAttribute(Constants::ERROR_TYPE, 405)
                        ->withAttribute(Constants::ALLOWED_METHODS, $routeInfo[1])
                );
        }

        return MiddlewareStack::compose(
            $this->container->get($routeInfo[1]),
            ...\array_map(function(string $service) {
                return $this->container->get($service);
            }, $this->dictionary->lookup($routeInfo[1]))
        )->handle(
            $request
                ->withAttribute(Constants::HANDLER, $routeInfo[1])
                ->withAttribute(Constants::ARGS, $routeInfo[2])
        );
    }
}
