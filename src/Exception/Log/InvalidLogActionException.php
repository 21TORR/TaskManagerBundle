<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Log;

use Torr\TaskManager\Exception\TaskManagerException;

final class InvalidLogActionException extends \RuntimeException implements TaskManagerException
{
}
