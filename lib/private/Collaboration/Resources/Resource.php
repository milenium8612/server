<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Collaboration\Resources;


use OCP\Collaboration\Resources\ICollection;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\IResource;
use OCP\IDBConnection;

class Resource implements IResource {

	/** @var IManager */
	protected $manager;

	/** @var IDBConnection */
	protected $connection;

	/** @var string */
	protected $type;

	/** @var string */
	protected $id;

	public function __construct(IManager $manager, IDBConnection $connection, string $type, string $id) {
		$this->manager = $manager;
		$this->connection = $connection;
		$this->type = $type;
		$this->id = $id;
	}

	/**
	 * @return string
	 * @since 15.0.0
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * @return string
	 * @since 15.0.0
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @param IResource $resource
	 * @return ICollection[]
	 * @since 15.0.0
	 */
	public function getCollections(IResource $resource): array {
		$collections = [];

		$query = $this->connection->getQueryBuilder();

		$query->select('collection_id')
			->from('collres_resources')
			->where($query->expr()->eq('resource_type', $query->createNamedParameter($resource->getType())))
			->andWhere($query->expr()->eq('resource_id', $query->createNamedParameter($resource->getId())));

		$result = $query->execute();
		while ($row = $result->fetch()) {
			$collections[] = $this->manager->getCollection((int) $row['collection_id']);
		}
		$result->closeCursor();

		return $collections;
	}
}