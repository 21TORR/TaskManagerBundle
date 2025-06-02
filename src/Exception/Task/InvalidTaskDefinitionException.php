<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Task;

use Torr\TaskManager\Exception\TaskManagerException;

/**
 * @final
 */
class InvalidTaskDefinitionException extends \InvalidArgumentException implements TaskManagerException
{
}
