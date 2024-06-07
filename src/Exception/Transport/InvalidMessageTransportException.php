<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Transport;

use Torr\TaskManager\Exception\TaskManagerException;

final class InvalidMessageTransportException extends \RuntimeException implements TaskManagerException
{
}
