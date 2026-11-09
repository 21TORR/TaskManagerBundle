<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Queue;

use Torr\TaskManager\Exception\TaskManagerException;

/**
 * The given string is no valid queued task id.
 */
final class InvalidQueuedTaskIdException extends \InvalidArgumentException implements TaskManagerException
{
}
