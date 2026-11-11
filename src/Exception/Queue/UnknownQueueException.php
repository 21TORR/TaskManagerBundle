<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Queue;

use Torr\TaskManager\Exception\TaskManagerException;

/**
 * The queue doesn't exist or is no queue that can hold queued tasks.
 */
final class UnknownQueueException extends \InvalidArgumentException implements TaskManagerException
{
}
