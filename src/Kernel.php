<?php

declare(strict_types=1);

namespace ABC;

use ABC\Internal;
use LogicException;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message;
use Psr\Http\Server;
use TypeError;
use function array_map;
use function array_reverse;
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

    private readonly ContainerInterface $container;
    private readonly Internal\MiddlewareChainResolver $chainResolver;
    private readonly Internal\RouteCollection $routes;

    /**
     * @throws LogicException If the container is missing any of the mandatory services
     */
    public function __construct(ContainerInterface $container)
    {
        Internal\Assert::hasService($container, Constants\Services::NOT_FOUND_HANDLER->value, 'Mandatory NOT_FOUND_HANDLER service missing');
        Internal\Assert::hasService($container, Constants\Services::BAD_METHOD_HANDLER->value, 'Mandatory BAD_METHOD_HANDLER service missing');

        $this->container = $container;
        $this->chainResolver = new Internal\MiddlewareChainResolver();
        $this->routes = new Internal\RouteCollection;
    }

    /**
     * @throws LogicException If the container does not have a service named $service
     */
    public function GET(string $pattern, string $service, string ...$groups): void
    {
        $this->map('GET', $pattern, $service, ...$groups);
    }

    /**
     * @throws LogicException If the container does not have a service named $service
     */
    public function POST(string $pattern, string $service, string ...$groups): void
    {
        $this->map('POST', $pattern, $service, ...$groups);
    }

    /**
     * @throws LogicException If the container does not have a service named $service
     */
    public function PUT(string $pattern, string $service, string ...$groups): void
    {
        $this->map('PUT', $pattern, $service, ...$groups);
    }

    /**
     * @throws LogicException If the container does not have a service named $service
     */
    public function UPDATE(string $pattern, string $service, string ...$groups): void
    {
        $this->map('UPDATE', $pattern, $service, ...$groups);
    }

    /**
     * @throws LogicException If the container does not have a service named $service
     */
    public function DELETE(string $pattern, string $service, string ...$groups): void
    {
        $this->map('DELETE', $pattern, $service, ...$groups);
    }

    /**
     * @throws LogicException If the container does not have a service named $service
     */
    public function map(string $method, string $pattern, string $service, string ...$groups): void
    {
        Internal\Assert::hasService($this->container, $service);

        $this->routes->addRoute($method, $pattern, $service);
        $this->chainResolver->pushHandler($service, ...$groups);
    }

    /**
     * @throws LogicException If the container does not have a service named $service
     */
    public function wrap(string $service): void
    {
        Internal\Assert::hasService($this->container, $service);

        $this->chainResolver->pushGlobalMiddleware($service);
    }

    /**
     * @throws LogicException If the container does not have a service named $service
     */
    public function tag(string $service, string ...$groups): void
    {
        Internal\Assert::hasService($this->container, $service);

        $this->chainResolver->pushLocalMiddleware($service, ...$groups);
    }

    /**
     * Handles the request and returns a response.
     *
     * @throws LogicException If any of the services you wired up turn out
     *                        not to be of the type they are supposed to be.
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

        return Internal\ExecutionStack::compose($handler, ...$middlewareChain)
            ->handle(
                $request->withAttribute(
                    Constants\Attributes::MIDDLEWARE_CHAIN->value,
                    array_reverse($middlewareChainNames)
                )
            );
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
            $chunkSize = $this->container->has(Constants\Settings::ECHO_CHUNK_SIZE->value) ?
                $this->container->get(Constants\Settings::ECHO_CHUNK_SIZE->value) : self::DEFAULT_CHUNK_SIZE;

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
