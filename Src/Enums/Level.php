<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Enums;

/**
 * Enum representing different log levels.
 */
enum Level: string
{
    /**
     * Indicates a successful operation.
     */
    case SUCCESS = "success";

    /**
     * Indicates an informational message.
     */
    case INFO = "info";

    /**
     * Indicates a warning message.
     */
    case WARNING = "warning";

    /**
     * Indicates an error message.
     */
    case ERROR = "error";

    /**
     * Indicates a critical error message.
     */
    case CRITICAL = "critical";
}