<?php
/**
 * Dependency Injection Container
 *
 * @package ImmoBridge
 * @subpackage Core\Container
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Core\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Closure;
use ReflectionClass;
use ReflectionParameter;
use ReflectionException;

/**
 * Dependency Injection Container
 *
 * A lightweight PSR-11 compliant dependency injection container with auto-wiring capabilities.
 *
 * @since 1.0.0
 */
final class Container implements ContainerInterface
{
    /**
     * Container bindings
     *
     * @var array<string, array{concrete: mixed, shared: bool}>
     */
    private array $bindings = [];

    /**
     * Shared instances (singletons)
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Aliases for service identifiers
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Bind a service to the container
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation (class name, closure, or instance)
     * @param bool $shared Whether the service should be a singleton
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $concrete = $concrete ?? $abstract;
        
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];
    }

    /**
     * Bind a singleton service to the container
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation (class name, closure, or instance)
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as a singleton
     *
     * @param string $abstract Service identifier
     * @param mixed $instance The instance to register
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Create an alias for a service
     *
     * @param string $alias The alias name
     * @param string $abstract The original service identifier
     */
    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Get a service from the container
     *
     * @param string $id Service identifier
     * @return mixed The resolved service
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function get(string $id): mixed
    {
        try {
            return $this->resolve($id);
        } catch (ReflectionException $e) {
            throw new ContainerException("Error resolving service '{$id}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a service exists in the container
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || 
               isset($this->instances[$id]) || 
               isset($this->aliases[$id]) ||
               class_exists($id);
    }

    /**
     * Resolve a service from the container
     *
     * @param string $abstract Service identifier
     * @return mixed The resolved service
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
    private function resolve(string $abstract): mixed
    {
        // Check for alias
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        // Return existing instance if it's a singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Get binding or use the abstract as concrete
        $binding = $this->bindings[$abstract] ?? ['concrete' => $abstract, 'shared' => false];
        $concrete = $binding['concrete'];

        // Resolve the concrete implementation
        $instance = match (true) {
            $concrete instanceof Closure => $concrete($this),
            is_string($concrete) && class_exists($concrete) => $this->build($concrete),
            is_string($concrete) => throw new NotFoundException("Service '{$abstract}' not found"),
            default => $concrete
        };

        // Store as singleton if needed
        if ($binding['shared']) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build a class instance with dependency injection
     *
     * @param string $className The class name to build
     * @return object The built instance
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
    private function build(string $className): object
    {
        $reflection = new ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class '{$className}' is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $className();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     *
     * @param array<ReflectionParameter> $parameters Constructor parameters
     * @return array<mixed> Resolved dependencies
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException(
                        "Cannot resolve parameter '{$parameter->getName()}' without type hint"
                    );
                }
                continue;
            }

            $typeName = $type->getName();

            try {
                $dependencies[] = $this->resolve($typeName);
            } catch (NotFoundException $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException(
                        "Cannot resolve dependency '{$typeName}' for parameter '{$parameter->getName()}'"
                    );
                }
            }
        }

        return $dependencies;
    }
}

/**
 * Container Exception
 */
class ContainerException extends \Exception implements ContainerExceptionInterface
{
}

/**
 * Not Found Exception
 */
class NotFoundException extends \Exception implements NotFoundExceptionInterface
{
}
