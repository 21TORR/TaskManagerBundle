<?php declare(strict_types=1);

namespace Torr\TaskManager\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Torr\TaskManager\Config\BundleConfig;

/**
 * Integrates into symfony/messenger, by automatically detecting the failure transports
 */
final class AutoDetectFailureTransportsCompilerInterface implements CompilerPassInterface
{
	/**
	 * @inheritDoc
	 */
	public function process (ContainerBuilder $container) : void
	{
		$failureTransports = [];

		foreach ($container->findTaggedServiceIds("messenger.receiver") as $key => $tagConfigs)
		{
			foreach ($tagConfigs as $tagConfig)
			{
				if (!\is_array($tagConfig))
				{
					continue;
				}

				$isFailure = $tagConfig["is_failure_transport"] ?? null;
				$alias = $tagConfig["alias"] ?? null;

				if (\is_string($alias) && true === $isFailure)
				{
					$failureTransports[] = $alias;
				}
			}
		}

		if (!empty($failureTransports))
		{
			$container->getDefinition(BundleConfig::class)
				->setArgument('$failureTransports', $failureTransports);
		}
	}
}
