<?php

declare(strict_types=1);

namespace ABC;

use ABC\Handler;
use ABC\Middleware;
use ABC\Util;
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
     * @var Util\RouteCollection
     */
    private $routes;

    /**
     * @var string[]
     */
    private $decorators;

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function __construct(Container\ContainerInterface $container)
    {
        Util\Assert::hasService($container, Constants::NOT_FOUND_HANDLER);
        Util\Assert::hasService($container, Constants::BAD_METHOD_HANDLER);
        Util\Assert::hasService($container, Constants::EXCEPTION_HANDLER);

        $this->container = $container;
        $this->decorators = [];
        $this->routes = new Util\RouteCollection;
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
        Util\Assert::hasService($this->container, $service);

        $this->routes->addRoute($method, $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function decorate(string $service): void
    {
        Util\Assert::hasService($this->container, $service);

        $this->decorators[] = $service;
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
        return Handler\MiddlewareStack::compose(
            new Handler\RequestRouter(
                $this->container,
                $this->routes,
                ...$this->decorators
            ),
            new Middleware\ExceptionTrapper(
                $this->container
            )
        )->handle($request);
    }
}
