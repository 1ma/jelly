<?php

declare(strict_types=1);

namespace ABC;

use ABC\Handler;
use ABC\Middleware;
use ABC\Util;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use Webmozart\Assert\Assert;

class Kernel implements Server\RequestHandlerInterface
{
    /**
     * @var Server\MiddlewareInterface[]
     */
    private array $decorators;
    private readonly Util\RouteCollection $routes;
    protected readonly Container\ContainerInterface $container;

    public function __construct(Container\ContainerInterface $container)
    {
        Assert::true($container->has(Constants::NOT_FOUND_HANDLER), '"Not Found" handler service missing');
        Assert::true($container->has(Constants::BAD_METHOD_HANDLER), '"Bad Method" handler service missing');
        Assert::true($container->has(Constants::EXCEPTION_HANDLER), 'Exception handler service missing');

        $this->container = $container;
        $this->decorators = [];
        $this->routes = new Util\RouteCollection;
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function GET(string $pattern, string $service): void
    {
        $this->map('GET', $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function POST(string $pattern, string $service): void
    {
        $this->map('POST', $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function PUT(string $pattern, string $service): void
    {
        $this->map('PUT', $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function UPDATE(string $pattern, string $service): void
    {
        $this->map('UPDATE', $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function DELETE(string $pattern, string $service): void
    {
        $this->map('DELETE', $pattern, $service);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function map(string $method, string $pattern, string $service): void
    {
        Assert::true($this->container->has($service), '%s is not registered as a service');

        $this->routes->addRoute($method, $pattern, $service);
    }

    /**
     * Wrap the whole Kernel with $middleware.
     */
    public function decorate(Server\MiddlewareInterface $middleware): void
    {
        $this->decorators[] = $middleware;
    }

    /**
     * Handle the request and return a response.
     */
    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        return Handler\MiddlewareStack::compose(
            new Handler\RequestRouter(
                $this->container,
                $this->routes
            ),
            new Middleware\ExceptionTrapper(
                $this->container
            ),
            ...$this->decorators
        )->handle($request);
    }
}
