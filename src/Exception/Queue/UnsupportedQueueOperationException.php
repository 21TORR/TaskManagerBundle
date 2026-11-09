<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Queue;

use Torr\TaskManager\Exception\TaskManagerException;

/**
 * The transport of the queue doesn't support the operation (e.g. listing or removing single tasks).
 */
final class UnsupportedQueueOperationException extends \RuntimeException implements TaskManagerException
{
}
