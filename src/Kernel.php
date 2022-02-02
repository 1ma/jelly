<?php

declare(strict_types=1);

namespace ABC;

use ABC\Handler;
use ABC\Middleware;
use ABC\Util;
use InvalidArgumentException;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message;
use Psr\Http\Server;

final class Kernel implements Server\RequestHandlerInterface
{
    /**
     * @var Server\MiddlewareInterface[]
     */
    private array $middlewares;
    private readonly Util\RouteCollection $routes;
    private readonly ContainerInterface $container;
    private readonly ServerRequestCreatorInterface $creator;
    private readonly Output $output;

    public function __construct(
        ContainerInterface $container,
        ServerRequestCreatorInterface $creator,
        Output $output = new Output\FastCGI()
    )
    {
        Util\Assert::true($container->has(Constants::NOT_FOUND_HANDLER), '"Not Found" handler service missing');
        Util\Assert::true($container->has(Constants::BAD_METHOD_HANDLER), '"Bad Method" handler service missing');
        Util\Assert::true($container->has(Constants::EXCEPTION_HANDLER), 'Exception handler service missing');

        $this->middlewares = [];
        $this->routes = new Util\RouteCollection;
        $this->container = $container;
        $this->creator = $creator;
        $this->output = $output;
    }

    /**
     * @throws InvalidArgumentException If the container does not have $service
     */
    public function GET(string $pattern, string $service): void
    {
        $this->map('GET', $pattern, $service);
    }

    /**
     * @throws InvalidArgumentException If the container does not have $service
     */
    public function POST(string $pattern, string $service): void
    {
        $this->map('POST', $pattern, $service);
    }

    /**
     * @throws InvalidArgumentException If the container does not have $service
     */
    public function PUT(string $pattern, string $service): void
    {
        $this->map('PUT', $pattern, $service);
    }

    /**
     * @throws InvalidArgumentException If the container does not have $service
     */
    public function UPDATE(string $pattern, string $service): void
    {
        $this->map('UPDATE', $pattern, $service);
    }

    /**
     * @throws InvalidArgumentException If the container does not have $service
     */
    public function DELETE(string $pattern, string $service): void
    {
        $this->map('DELETE', $pattern, $service);
    }

    /**
     * @throws InvalidArgumentException If the container does not have $service
     */
    public function map(string $method, string $pattern, string $service): void
    {
        Util\Assert::true($this->container->has($service), '%s is not registered as a service');

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
