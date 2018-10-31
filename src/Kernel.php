<?php

declare(strict_types=1);

namespace ABC;

use ABC\Middleware\ExceptionTrapper;
use ABC\Handler\RequestRouter;
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
        $this->middlewares = [];
        $this->routes = new RouteCollection;
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
    public function put(string $pattern, string $service): void
    {
        $this->map('PUT', $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function update(string $pattern, string $service): void
    {
        $this->map('UPDATE', $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function delete(string $pattern, string $service): void
    {
        $this->map('DELETE', $pattern, $service);
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

    public function getContainer(): Container\ContainerInterface
    {
        return $this->container;
    }

    /**
     * Handle the request and return a response.
     */
    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        return Seam::compose(
            new RequestRouter(
                $this->container,
                $this->routes,
                ...$this->middlewares
            ),
            new ExceptionTrapper(
                $this->container
            )
        )->handle($request);
    }
}
