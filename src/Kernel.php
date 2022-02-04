<?php

declare(strict_types=1);

namespace ABC;

use ABC\Handlers;
use ABC\Internal;
use ABC\Middlewares;
use LogicException;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message;
use Psr\Http\Server;
use function fastcgi_finish_request;
use function function_exists;
use function header;
use function implode;
use function sprintf;

final class Kernel implements Server\RequestHandlerInterface
{
    /**
     * The default behaviour is emitting the response in chunks of at most 8 MiB at a time.
     *
     * This is to avoid exhausting the memory available to the PHP process.
     * By default, the memory_limit INI directive is 128MB so 8 MiB should be
     * low enough in virtually all cases.
     */
    private const DEFAULT_CHUNK_SIZE = 8 * (1024 ** 2);

    /**
     * @var Server\MiddlewareInterface[]
     */
    private array $middlewares;
    private readonly Internal\RouteCollection $routes;
    private readonly ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        Internal\Assert::hasService($container, Constants::NOT_FOUND_HANDLER->value, '"Not Found" handler service missing');
        Internal\Assert::hasService($container, Constants::BAD_METHOD_HANDLER->value, '"Bad Method" handler service missing');
        Internal\Assert::hasService($container, Constants::EXCEPTION_HANDLER->value, 'Exception handler service missing');

        $this->middlewares = [];
        $this->routes = new Internal\RouteCollection;
        $this->container = $container;
    }

    /**
     * @throws LogicException If the container does not have $service
     */
    public function GET(string $pattern, string $service): void
    {
        $this->map('GET', $pattern, $service);
    }

    /**
     * @throws LogicException If the container does not have $service
     */
    public function POST(string $pattern, string $service): void
    {
        $this->map('POST', $pattern, $service);
    }

    /**
     * @throws LogicException If the container does not have $service
     */
    public function PUT(string $pattern, string $service): void
    {
        $this->map('PUT', $pattern, $service);
    }

    /**
     * @throws LogicException If the container does not have $service
     */
    public function UPDATE(string $pattern, string $service): void
    {
        $this->map('UPDATE', $pattern, $service);
    }

    /**
     * @throws LogicException If the container does not have $service
     */
    public function DELETE(string $pattern, string $service): void
    {
        $this->map('DELETE', $pattern, $service);
    }

    /**
     * @throws LogicException If the container does not have $service
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
    public function run(ServerRequestCreatorInterface $factory): void
    {
        $response = $this->handle($factory->fromGlobals());

        header(sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        ));

        foreach ($response->getHeaders() as $name => $values) {
            header(sprintf(
                '%s: %s',
                $name,
                implode(', ', $values)
            ));
        }

        // Only attempt to echo the response when neither the
        // X-SendFile nor X-Accel-Redirect headers are present in the response
        if (!$response->hasHeader('X-Sendfile') && !$response->hasHeader('X-Accel-Redirect')) {
            $chunkSize = $this->container->has(Constants::ECHO_CHUNK_SIZE->value) ?
                $this->container->get(Constants::ECHO_CHUNK_SIZE->value) : self::DEFAULT_CHUNK_SIZE;

            $stream = $response->getBody();
            $stream->rewind();

            while ('' !== $chunk = $stream->read($chunkSize)) {
                echo $chunk;
            }

            $stream->close();
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
}
