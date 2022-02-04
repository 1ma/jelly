<?php

declare(strict_types=1);

namespace ABC;

use ABC\Handlers;
use ABC\Internal;
use ABC\Middlewares\UncaughtExceptionStopper;
use LogicException;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message;
use Psr\Http\Server;
use TypeError;
use function array_map;
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

    private readonly Internal\MiddlewareChainResolver $chainResolver;
    private readonly Internal\RouteCollection $routes;
    private readonly ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        Internal\Assert::hasService($container, Constants::NOT_FOUND_HANDLER->value, 'Mandatory NOT_FOUND_HANDLER service missing');
        Internal\Assert::hasService($container, Constants::BAD_METHOD_HANDLER->value, 'Mandatory BAD_METHOD_HANDLER service missing');
        Internal\Assert::hasService($container, Constants::EXCEPTION_HANDLER->value, 'Mandatory EXCEPTION_HANDLER service missing');

        $this->chainResolver = new Internal\MiddlewareChainResolver();
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
        Internal\Assert::hasService($this->container, $service);

        $this->routes->addRoute($method, $pattern, $service);
    }

    public function wrap(string $service): void
    {
        Internal\Assert::hasService($this->container, $service);

        $this->chainResolver->pushGlobalMiddleware($service);
    }

    /**
     * Handles the request and returns a response.
     */
    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        $handlerName = (new Internal\RequestHandlerResolver($this->routes))->resolve($request);
        $middlewareChainNames = $this->chainResolver->resolve($handlerName);

        try {
            $handler = Internal\Assert::isRequestHandler($this->container->get($handlerName));
            $middlewareChain = array_map(function (string $name): Server\MiddlewareInterface {
                return $this->container->get($name);
            }, $middlewareChainNames);
        } catch (ContainerExceptionInterface|TypeError $e) {
            throw new LogicException(message: $e->getMessage(), previous: $e);
        }

        $middlewareChain[] = new UncaughtExceptionStopper($this->container);

        return Handlers\ExecutionStack::compose($handler, ...$middlewareChain)->handle($request);
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
