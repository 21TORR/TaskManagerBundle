<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Registry;

use Torr\TaskManager\Exception\TaskManagerException;

final class UnknownTaskKeyException extends \InvalidArgumentException implements TaskManagerException
{
}
