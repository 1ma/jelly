<?php

declare(strict_types=1);

namespace ABC;

use ABC\Handler\Pipeliner;
use ABC\Middleware\ExceptionTrapper;
use ABC\Middleware\RequestRouter;
use ABC\Util\Assert;
use ABC\Util\RouteCollection;
use ABC\Util\Seam;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;

class Kernel implements Server\RequestHandlerInterface
{
    /**
     * @var Container\ContainerInterface
     */
    private $container;

    /**
     * @var RouteCollection
     */
    private $routes;

    /**
     * @var string[]
     */
    private $middlewares;

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function __construct(Container\ContainerInterface $container)
    {
        Assert::hasService($container, Constants::NOT_FOUND_HANDLER);
        Assert::hasService($container, Constants::BAD_METHOD_HANDLER);
        Assert::hasService($container, Constants::EXCEPTION_HANDLER);

        $this->container = $container;
        $this->routes = new RouteCollection;
    }

    public function getContainer(): Container\ContainerInterface
    {
        return $this->container;
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function get(string $pattern, string $service): void
    {
        $this->map('GET', $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function post(string $pattern, string $service): void
    {
        $this->map('POST', $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function map(string $method, string $pattern, string $service): void
    {
        Assert::hasService($this->container, $service);

        $this->routes->addRoute($method, $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function decorate(string $service): void
    {
        Assert::hasService($this->container, $service);

        $this->middlewares[] = $service;
    }

    /**
     * Handle the request and return a response.
     */
    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        $exceptionHandling = new ExceptionTrapper(
            $this->container
        );

        $routing = new RequestRouter(
            $this->container,
            $this->routes
        );

        $middlewaring = new Pipeliner(
            $this->container,
            $this->middlewares
        );

        return $exceptionHandling->process($request, new Seam($routing, $middlewaring));
    }
}
