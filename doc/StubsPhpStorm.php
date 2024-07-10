<?php

class Socket
{

}

class CurlHandle
{

}

class CurlMultiHandle
{

}

class UnitEnum
{
    public $name;
    public $value;
}

class BackedEnum
{
    public $name;
    public $value;
}

class WeakReference
{
    /**
     * @return object|null
     */
    public function get() {
        return null;
    }
}

abstract class ReflectionFunctionAbstract
{
    public function getTentativeReturnType(): ?ReflectionType
    {
        return null;
    }

}

class ReflectionAttribute
{

}

class ReflectionEnum
{

}

class ReflectionEnumUnitCase
{

}

class ReflectionEnumBackedCase
{

}

class ReflectionUnionType
{

}

class ReflectionIntersectionType
{

}

class ReflectionReference
{
    public function getId(): string
    {
        return '';
    }
}

class ReflectionFiber
{
    public function __construct(Fiber $fiber)
    {

    }

    public function getFiber(): Fiber
    {
        return new Fiber(static function () {});
    }

    public function getExecutingFile(): string
    {
        return '';
    }

    public function getExecutingLine(): int
    {
        return 0;
    }

    public function getCallable(): callable
    {
        return static function () {};
    }

    public function getTrace(int $options = DEBUG_BACKTRACE_PROVIDE_OBJECT): array
    {
        return [];
    }
}

class Fiber
{
    public function __construct(callable $callback)
    {

    }

    /**
     * Starts execution of the fiber. Returns when the fiber suspends or terminates.
     *
     * @param TStart ...$args Arguments passed to fiber function.
     *
     * @return TSuspend|null Value from the first suspension point or NULL if the fiber returns.
     *
     * @throws FiberError If the fiber has already been started.
     * @throws Throwable If the fiber callable throws an uncaught exception.
     */
    public function start(mixed ...$args): mixed
    {
        return 0;
    }

    /**
     * Resumes the fiber, returning the given value from {@see Fiber::suspend()}.
     * Returns when the fiber suspends or terminates.
     *
     * @param TResume $value
     *
     * @return TSuspend|null Value from the next suspension point or NULL if the fiber returns.
     *
     * @throws FiberError If the fiber has not started, is running, or has terminated.
     * @throws Throwable If the fiber callable throws an uncaught exception.
     */
    public function resume(mixed $value = null): mixed
    {
        return 0;
    }

    /**
     * Throws the given exception into the fiber from {@see Fiber::suspend()}.
     * Returns when the fiber suspends or terminates.
     *
     * @param Throwable $exception
     *
     * @return TSuspend|null Value from the next suspension point or NULL if the fiber returns.
     *
     * @throws FiberError If the fiber has not started, is running, or has terminated.
     * @throws Throwable If the fiber callable throws an uncaught exception.
     */
    public function throw(Throwable $exception): mixed
    {
        return 0;
    }

    /**
     * @return bool True if the fiber has been started.
     */
    public function isStarted(): bool
    {
        return true;
    }

    /**
     * @return bool True if the fiber is suspended.
     */
    public function isSuspended(): bool
    {
        return true;
    }

    /**
     * @return bool True if the fiber is currently running.
     */
    public function isRunning(): bool
    {
        return true;
    }

    /**
     * @return bool True if the fiber has completed execution (returned or threw).
     */
    public function isTerminated(): bool
    {
        return true;
    }

    /**
     * @return TReturn Return value of the fiber callback. NULL is returned if the fiber does not have a return statement.
     *
     * @throws FiberError If the fiber has not terminated or the fiber threw an exception.
     */
    public function getReturn(): mixed
    {
        return true;
    }

    /**
     * @return self|null Returns the currently executing fiber instance or NULL if in {main}.
     */
    public static function getCurrent(): ?Fiber
    {
        return true;
    }

    /**
     * Suspend execution of the fiber. The fiber may be resumed with {@see Fiber::resume()} or {@see Fiber::throw()}.
     *
     * Cannot be called from {main}.
     *
     * @param TSuspend $value Value to return from {@see Fiber::resume()} or {@see Fiber::throw()}.
     *
     * @return TResume Value provided to {@see Fiber::resume()}.
     *
     * @throws FiberError Thrown if not within a fiber (i.e., if called from {main}).
     * @throws Throwable Exception provided to {@see Fiber::throw()}.
     */
    public static function suspend(mixed $value = null): mixed
    {
        return true;
    }
}
