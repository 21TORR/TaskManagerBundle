<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Queue;

use Torr\TaskManager\Exception\TaskManagerException;

/**
 * The queued task was already started by a worker, so it can't be removed anymore.
 */
final class QueuedTaskAlreadyStartedException extends \RuntimeException implements TaskManagerException
{
}
