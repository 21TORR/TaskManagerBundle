<?php declare(strict_types=1);

namespace Torr\TaskManager\Exception\Queue;

use Torr\TaskManager\Exception\TaskManagerException;

/**
 * The queued task wasn't found (e.g. because it was already handled or removed).
 */
final class QueuedTaskNotFoundException extends \RuntimeException implements TaskManagerException
{
}
