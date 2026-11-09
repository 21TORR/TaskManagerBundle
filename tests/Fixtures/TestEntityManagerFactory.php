<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Fixtures;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Creates an entity manager with an in-memory SQLite database, containing the task manager entities.
 */
final class TestEntityManagerFactory
{
	/**
	 *
	 */
	public static function create () : EntityManager
	{
		$config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2) . "/src/Entity"], true);
		$config->enableNativeLazyObjects(true);

		$connection = DriverManager::getConnection(["driver" => "pdo_sqlite", "memory" => true], $config);
		$entityManager = new EntityManager($connection, $config);

		new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

		return $entityManager;
	}
}
