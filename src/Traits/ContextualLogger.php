<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Traits;

use Psr\Log\LoggerInterface;

/**
 * Logger wrapper that adds context to all log messages.
 */
final class ContextualLogger implements LoggerInterface
{
    private LoggerInterface $logger;
    private array $context;

    public function __construct(LoggerInterface $logger, array $context = [])
    {
        $this->logger = $logger;
        $this->context = $context;
    }

    /**
     * Add context to the logger.
     */
    public function withContext(array $context): self
    {
        $newContext = array_merge($this->context, $context);
        return new self($this->logger, $newContext);
    }

    /**
     * Get the current context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * System is unusable.
     */
    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->logger->emergency($message, $this->mergeContext($context));
    }

    /**
     * Action must be taken immediately.
     */
    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->logger->alert($message, $this->mergeContext($context));
    }

    /**
     * Critical conditions.
     */
    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->logger->critical($message, $this->mergeContext($context));
    }

    /**
     * Runtime errors that do not require immediate action.
     */
    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->logger->error($message, $this->mergeContext($context));
    }

    /**
     * Exceptional occurrences that are not errors.
     */
    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->logger->warning($message, $this->mergeContext($context));
    }

    /**
     * Normal but significant events.
     */
    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->logger->notice($message, $this->mergeContext($context));
    }

    /**
     * Interesting events.
     */
    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->logger->info($message, $this->mergeContext($context));
    }

    /**
     * Detailed debug information.
     */
    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->logger->debug($message, $this->mergeContext($context));
    }

    /**
     * Logs with an arbitrary level.
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $this->mergeContext($context));
    }

    /**
     * Check if the logger is handling the given level.
     */
    public function isHandling(int|string $level): bool
    {
        return $this->logger->isHandling($level);
    }

    /**
     * Merge the contextual data with the provided context.
     */
    private function mergeContext(array $context): array
    {
        return array_merge($this->context, $context);
    }
}
