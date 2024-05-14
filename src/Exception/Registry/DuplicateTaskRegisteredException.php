<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Registry;

use Torr\TaskManager\Exception\TaskManagerException;

final class DuplicateTaskRegisteredException extends \InvalidArgumentException implements TaskManagerException
{
}
