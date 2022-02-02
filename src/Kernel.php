<?php

declare(strict_types=1);

namespace ABC;

use ABC\Handler;
use ABC\Middleware;
use ABC\Util;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use Webmozart\Assert\Assert;

final class Kernel implements Server\RequestHandlerInterface
{
    /**
     * @var Server\MiddlewareInterface[]
     */
    private array $middlewares;
    private readonly Util\RouteCollection $routes;
    private readonly Container\ContainerInterface $container;
    private readonly ServerRequestCreatorInterface $creator;
    private readonly Output $output;

    public function __construct(
        Container\ContainerInterface $container,
        ServerRequestCreatorInterface $creator,
        Output $output = new Output\FastCGI()
    )
    {
        Assert::true($container->has(Constants::NOT_FOUND_HANDLER), '"Not Found" handler service missing');
        Assert::true($container->has(Constants::BAD_METHOD_HANDLER), '"Bad Method" handler service missing');
        Assert::true($container->has(Constants::EXCEPTION_HANDLER), 'Exception handler service missing');

        $this->middlewares = [];
        $this->routes = new Util\RouteCollection;
        $this->container = $container;
        $this->creator = $creator;
        $this->output = $output;
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
     * Wraps the Kernel with $middleware.
     */
    public function decorate(Server\MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Handles the request and returns a response.
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
            ...$this->middlewares
        )->handle($request);
    }

    /**
     * Creates a request from the global variables,
     * handles it and calls the Output service to send
     * the response back.
     */
    public function run(): void
    {
        $this->output->send(
            $this->handle(
                $this->creator->fromGlobals()
            )
        );
    }
}
