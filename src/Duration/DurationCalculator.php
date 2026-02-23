<?php declare(strict_types=1);

namespace Torr\TaskManager\Duration;

/**
 * @final
 */
readonly class DurationCalculator
{
	/**
	 * Returns the duration in nanoseconds
	 */
	public function calculateDuration (\DateTimeImmutable $start, \DateTimeImmutable $end) : float
	{
		$diff = $start->diff($end, true);

		$seconds = $diff->days * 24 * 60 * 60
			+ $diff->h * 60 * 60
			+ $diff->i * 60
			+ $diff->s
			+ $diff->f;

		return $seconds * 1e9;
	}
}
