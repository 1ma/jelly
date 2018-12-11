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
    protected $container;

    /**
     * @var Server\MiddlewareInterface[]
     */
    private $decorators;

    /**
     * @var Util\Dictionary
     */
    private $dictionary;

    /**
     * @var Util\RouteCollection
     */
    private $routes;

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
        $this->dictionary = new Util\Dictionary;
        $this->routes = new Util\RouteCollection;
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function get(string $pattern, string $service, array $extraTags = []): void
    {
        $this->map('GET', $pattern, $service, $extraTags);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function post(string $pattern, string $service, array $extraTags = []): void
    {
        $this->map('POST', $pattern, $service, $extraTags);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function put(string $pattern, string $service, array $extraTags = []): void
    {
        $this->map('PUT', $pattern, $service, $extraTags);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function update(string $pattern, string $service, array $extraTags = []): void
    {
        $this->map('UPDATE', $pattern, $service, $extraTags);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function delete(string $pattern, string $service, array $extraTags = []): void
    {
        $this->map('DELETE', $pattern, $service, $extraTags);
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     */
    public function map(string $method, string $pattern, string $service, array $extraTags = []): void
    {
        Util\Assert::hasService($this->container, $service);

        $this->routes->addRoute($method, $pattern, $service);

        $this->dictionary->tag($service, $service);
        foreach ($extraTags as $extraTag) {
            $this->dictionary->tag($service, $extraTag);
        }
    }

    /**
     * Bind a $middleware (a service name) to the given $tag.
     */
    public function add(string $tag, string $middleware)
    {
        Util\Assert::hasService($this->container, $middleware);

        $this->dictionary->push($tag, $middleware);
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
                $this->routes,
                $this->dictionary
            ),
            new Middleware\ExceptionTrapper(
                $this->container
            ),
            ...$this->decorators
        )->handle($request);
    }
}
