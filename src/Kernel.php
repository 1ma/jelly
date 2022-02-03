<?php

declare(strict_types=1);

namespace ABC;

use ABC\Handlers;
use ABC\Internal;
use ABC\Middlewares;
use InvalidArgumentException;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message;
use Psr\Http\Server;

final class Kernel implements Server\RequestHandlerInterface
{
    /**
     * This is a service name that the framework expects to map to a RequestHandlerInterface
     * that will handle Not Found (HTTP 404) errors.
     */
    public const NOT_FOUND_HANDLER_SERVICE = 'abc.404';

    /**
     * This is a service name that the framework expects to map to a RequestHandlerInterface
     * that will handle Method Not Allowed (HTTP 405) errors.
     */
    public const BAD_METHOD_HANDLER_SERVICE = 'abc.405';

    /**
     * This is a service name that the framework expects to map to a RequestHandlerInterface
     * that will handle any uncaught exceptions thrown by the application.
     */
    public const EXCEPTION_HANDLER_SERVICE = 'abc.500';

    /**
     * If an uncaught exception is captured, a Method Not Found error occurs, or a
     * Not Found error occurs, the framework will attach the appropriate HTTP error
     * code to the request under this attribute key.
     *
     * It will either be 500, 405 or 404 respectively.
     */
    public const ERROR_TYPE = 'abc.error';

    /**
     * If an HTTP 405 Method Not Allowed error occurs, the framework will attach the list
     * of valid HTTP verbs to the request under this attribute key.
     *
     * A RequestHandlerInterface stored in the container as Kernel::BAD_METHOD_HANDLER_SERVICE
     * can rely on the fact that the request it receives will have this attribute, and its
     * value will always be a non-empty array of strings, such as ['GET', 'PUT', 'DELETE'].
     *
     * @see Handlers\MethodNotAllowed for an example
     */
    public const ALLOWED_METHODS = 'abc.allowed_methods';

    /**
     * If the framework traps an uncaught exception it will be attached to the request
     * under this attribute key.
     *
     * A RequestHandlerInterface stored in the container as Kernel::EXCEPTION_HANDLER_SERVICE
     * can rely on the fact that the request it receives will have this attribute, and its
     * value will always be an exception.
     *
     * @see Middlewares\ExceptionTrapper for an example
     */
    public const EXCEPTION = 'abc.exception';

    /**
     * On every successfully routed request, the framework will attach its arguments
     * under this attribute key.
     *
     * Arguments are the named placeholders that can be defined in FastRoute's
     * path declarations, such as '/hello/{name}'.
     */
    public const ARGS = 'abc.args';

    /**
     * On every successfully routed request, the framework will attach the service
     * name of the designed request handler under this attribute key.
     */
    public const HANDLER = 'abc.handler';

    /**
     * @var Server\MiddlewareInterface[]
     */
    private array $middlewares;
    private readonly Internal\RouteCollection $routes;
    private readonly ContainerInterface $container;
    private readonly ServerRequestCreatorInterface $creator;
    private readonly Output $output;

    public function __construct(
        ContainerInterface $container,
        ServerRequestCreatorInterface $creator,
        Output $output = new Output\FastCGI()
    )
    {
        Internal\Assert::hasService($container, self::NOT_FOUND_HANDLER_SERVICE, '"Not Found" handler service missing');
        Internal\Assert::hasService($container, self::BAD_METHOD_HANDLER_SERVICE, '"Bad Method" handler service missing');
        Internal\Assert::hasService($container, self::EXCEPTION_HANDLER_SERVICE, 'Exception handler service missing');

        $this->middlewares = [];
        $this->routes = new Internal\RouteCollection;
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
        Internal\Assert::hasService($this->container, $service, "$service is not registered as a service");

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
        return Handlers\MiddlewareStack::compose(
            (new Internal\RequestRouter($this->container, $this->routes))->resolve($request),
            new Middlewares\ExceptionTrapper(
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
