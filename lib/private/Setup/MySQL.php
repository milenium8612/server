<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Michael Göhler <somebody.here@gmx.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OC\Setup;

use OCP\IDBConnection;

class MySQL extends AbstractDatabase {
	public $dbprettyname = 'MySQL/MariaDB';

	public function setupDatabase($username) {
		//check if the database user has admin right
		$connection = $this->connect(['dbname' => null]);

		$this->createSpecificUser($username, $connection);

		//create the database
		$this->createDatabase($connection);

		//fill the database if needed
		$query='select count(*) from information_schema.tables where table_schema=? AND table_name = ?';
		$result = $connection->executeQuery($query, [$this->dbName, $this->tablePrefix.'users']);
		$row = $result->fetch();
		if (!$row or $row['count(*)'] === '0') {
			\OC_DB::createDbFromStructure($this->dbDefinitionFile);
		}
	}

	/**
	 * @param \OC\DB\Connection $connection
	 */
	private function createDatabase($connection) {
		try{
			$name = $this->dbName;
			$user = $this->dbUser;
			//we can't use OC_DB functions here because we need to connect as the administrative user.
			$characterSet = $this->config->getValue('mysql.utf8mb4', false) ? 'utf8mb4' : 'utf8';
			$query = "CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET $characterSet COLLATE ${characterSet}_bin;";
			$connection->executeUpdate($query);
		} catch (\Exception $ex) {
			$this->logger->error('Database creation failed: {error}', [
				'app' => 'mysql.setup',
				'error' => $ex->getMessage()
			]);
			return;
		}

		try {
			//this query will fail if there aren't the right permissions, ignore the error
			$query="GRANT ALL PRIVILEGES ON `$name` . * TO '$user'";
			$connection->executeUpdate($query);
		} catch (\Exception $ex) {
			$this->logger->debug('Could not automatically grant privileges, this can be ignored if database user already had privileges: {error}', [
				'app' => 'mysql.setup',
				'error' => $ex->getMessage()
			]);
		}
	}

	/**
	 * @param IDBConnection $connection
	 * @throws \OC\DatabaseSetupException
	 */
	private function createDBUser($connection) {
		try{
			$name = $this->dbUser;
			$password = $this->dbPassword;
			// we need to create 2 accounts, one for global use and one for local user. if we don't specify the local one,
			// the anonymous user would take precedence when there is one.
			$query = "CREATE USER '$name'@'localhost' IDENTIFIED BY '$password'";
			$connection->executeUpdate($query);
			$query = "CREATE USER '$name'@'%' IDENTIFIED BY '$password'";
			$connection->executeUpdate($query);
		}
		catch (\Exception $ex){
			$this->logger->error('Database User creation failed: {error}', [
                                'app' => 'mysql.setup',
                                'error' => $ex->getMessage()
                        ]);
		}
	}

	/**
	 * @param $username
	 * @param IDBConnection $connection
	 * @return array
	 */
	private function createSpecificUser($username, $connection) {
		try {
			//user already specified in config
			$oldUser = $this->config->getValue('dbuser', false);

			//we don't have a dbuser specified in config
			if ($this->dbUser !== $oldUser) {
				//add prefix to the admin username to prevent collisions
				$adminUser = substr('oc_' . $username, 0, 16);

				$i = 1;
				while (true) {
					//this should be enough to check for admin rights in mysql
					$query = 'SELECT user FROM mysql.user WHERE user=?';
					$result = $connection->executeQuery($query, [$adminUser]);

					//current dbuser has admin rights
					if ($result) {
						$data = $result->fetchAll();
						//new dbuser does not exist
						if (count($data) === 0) {
							//use the admin login data for the new database user
							$this->dbUser = $adminUser;

							//create a random password so we don't need to store the admin password in the config file
							$this->dbPassword =  $this->random->generate(30);

							$this->createDBUser($connection);

							break;
						} else {
							//repeat with different username
							$length = strlen((string)$i);
							$adminUser = substr('oc_' . $username, 0, 16 - $length) . $i;
							$i++;
						}
					} else {
						break;
					}
				};
			}
		} catch (\Exception $ex) {
			$this->logger->info('Can not create a new MySQL user, will continue with the provided user: {error}', [
				'app' => 'mysql.setup',
				'error' => $ex->getMessage()
			]);
		}

		$this->config->setValues([
			'dbuser' => $this->dbUser,
			'dbpassword' => $this->dbPassword,
		]);
	}
}