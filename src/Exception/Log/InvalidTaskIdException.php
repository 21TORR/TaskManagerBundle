<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Log;

use Torr\TaskManager\Exception\TaskManagerException;

/**
 * The task id of a task log is no valid UUIDv7.
 */
final class InvalidTaskIdException extends \InvalidArgumentException implements TaskManagerException
{
}
