<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\UserStatus\Tests\Db;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Db\UserStatusMapper;
use Test\TestCase;

class UserStatusMapperTest extends TestCase {

	/** @var UserStatusMapper */
	private $mapper;

	protected function setUp(): void {
		parent::setUp();

		// make sure that DB is empty
		$qb = self::$realDatabase->getQueryBuilder();
		$qb->delete('user_status')->execute();

		$this->mapper = new UserStatusMapper(self::$realDatabase);
	}

	public function testGetTableName(): void {
		$this->assertEquals('user_status', $this->mapper->getTableName());
	}

	public function testGetFindAll(): void {
		$this->insertSampleStatuses();

		$allResults = $this->mapper->findAll();
		$this->assertCount(3, $allResults);

		$limitedResults = $this->mapper->findAll(2);
		$this->assertCount(2, $limitedResults);
		$this->assertEquals('admin', $limitedResults[0]->getUserId());
		$this->assertEquals('user1', $limitedResults[1]->getUserId());

		$offsetResults = $this->mapper->findAll(null, 2);
		$this->assertCount(1, $offsetResults);
		$this->assertEquals('user2', $offsetResults[0]->getUserId());
	}

	public function testGetFind(): void {
		$this->insertSampleStatuses();

		$adminStatus = $this->mapper->findByUserId('admin');
		$this->assertEquals('admin', $adminStatus->getUserId());
		$this->assertEquals('busy', $adminStatus->getStatusType());
		$this->assertEquals(null, $adminStatus->getStatusIcon());
		$this->assertEquals(null, $adminStatus->getMessage());
		$this->assertEquals(42, $adminStatus->getCreatedAt());
		$this->assertEquals(null, $adminStatus->getClearAt());

		$user1Status = $this->mapper->findByUserId('user1');
		$this->assertEquals('user1', $user1Status->getUserId());
		$this->assertEquals('busy', $user1Status->getStatusType());
		$this->assertEquals('💩', $user1Status->getStatusIcon());
		$this->assertEquals('Do not disturb', $user1Status->getMessage());
		$this->assertEquals(1337, $user1Status->getCreatedAt());
		$this->assertEquals(50000, $user1Status->getClearAt());

		$user2Status = $this->mapper->findByUserId('user2');
		$this->assertEquals('user2', $user2Status->getUserId());
		$this->assertEquals('unavailable', $user2Status->getStatusType());
		$this->assertEquals('🏝', $user2Status->getStatusIcon());
		$this->assertEquals('On vacation', $user2Status->getMessage());
		$this->assertEquals(10000, $user2Status->getCreatedAt());
		$this->assertEquals(60000, $user2Status->getClearAt());
	}

	public function testUserIdUnique(): void {
		// Test that inserting a second status for a user is throwing an exception

		$userStatus1 = new UserStatus();
		$userStatus1->setUserId('admin');
		$userStatus1->setStatusType('busy');
		$userStatus1->setCreatedAt(42);

		$this->mapper->insert($userStatus1);

		$userStatus2 = new UserStatus();
		$userStatus2->setUserId('admin');
		$userStatus2->setStatusType('available');
		$userStatus2->setCreatedAt(1337);

		$this->expectException(UniqueConstraintViolationException::class);

		$this->mapper->insert($userStatus2);
	}

	private function insertSampleStatuses(): void {
		$userStatus1 = new UserStatus();
		$userStatus1->setUserId('admin');
		$userStatus1->setStatusType('busy');
		$userStatus1->setCreatedAt(42);

		$userStatus2 = new UserStatus();
		$userStatus2->setUserId('user1');
		$userStatus2->setStatusType('busy');
		$userStatus2->setStatusIcon('💩');
		$userStatus2->setMessage('Do not disturb');
		$userStatus2->setCreatedAt(1337);
		$userStatus2->setClearAt(50000);

		$userStatus3 = new UserStatus();
		$userStatus3->setUserId('user2');
		$userStatus3->setStatusType('unavailable');
		$userStatus3->setStatusIcon('🏝');
		$userStatus3->setMessage('On vacation');
		$userStatus3->setCreatedAt(10000);
		$userStatus3->setClearAt(60000);

		$this->mapper->insert($userStatus1);
		$this->mapper->insert($userStatus2);
		$this->mapper->insert($userStatus3);
	}
}
