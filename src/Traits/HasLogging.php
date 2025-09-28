<?php

declare(strict_types=1);

namespace GrimReapper\MsGraph\Traits;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Trait providing logging functionality.
 */
trait HasLogging
{
    protected LoggerInterface $logger;

    /**
     * Set the logger instance.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get the logger instance.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger ?? new NullLogger();
    }

    /**
     * Log an emergency message.
     */
    protected function logEmergency(string $message, array $context = []): void
    {
        $this->getLogger()->emergency($message, $context);
    }

    /**
     * Log an alert message.
     */
    protected function logAlert(string $message, array $context = []): void
    {
        $this->getLogger()->alert($message, $context);
    }

    /**
     * Log a critical message.
     */
    protected function logCritical(string $message, array $context = []): void
    {
        $this->getLogger()->critical($message, $context);
    }

    /**
     * Log an error message.
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->getLogger()->error($message, $context);
    }

    /**
     * Log a warning message.
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->getLogger()->warning($message, $context);
    }

    /**
     * Log a notice message.
     */
    protected function logNotice(string $message, array $context = []): void
    {
        $this->getLogger()->notice($message, $context);
    }

    /**
     * Log an info message.
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->getLogger()->info($message, $context);
    }

    /**
     * Log a debug message.
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->getLogger()->debug($message, $context);
    }

    /**
     * Log a message with an arbitrary level.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $this->getLogger()->log($level, $message, $context);
    }

    /**
     * Check if the logger is enabled for the given level.
     */
    protected function isLogging(string $level): bool
    {
        return $this->getLogger()->isHandling($level);
    }

    /**
     * Create a contextual logger with additional data.
     */
    protected function withContext(array $context): ContextualLogger
    {
        return new ContextualLogger($this->getLogger(), $context);
    }
}
