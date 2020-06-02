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

namespace OCA\UserStatus\Tests\Service;

use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Db\UserStatusMapper;
use OCA\UserStatus\Exception\InvalidClearAtException;
use OCA\UserStatus\Exception\InvalidStatusIconException;
use OCA\UserStatus\Exception\InvalidStatusTypeException;
use OCA\UserStatus\Exception\StatusMessageTooLongException;
use OCA\UserStatus\Service\StatusService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use Test\TestCase;

class StatusServiceTest extends TestCase {

	/** @var UserStatusMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $mapper;

	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $timeFactory;

	/** @var StatusService */
	private $service;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = $this->createMock(UserStatusMapper::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->service = new StatusService($this->mapper, $this->timeFactory);
	}

	public function testFindAll(): void {
		$status1 = $this->createMock(UserStatus::class);
		$status2 = $this->createMock(UserStatus::class);

		$this->mapper->expects($this->once())
			->method('findAll')
			->with(20, 50)
			->willReturn([$status1, $status2]);

		$this->assertEquals([
			$status1,
			$status2,
		], $this->service->findAll(20, 50));
	}

	public function testFindByUserId(): void {
		$status = $this->createMock(UserStatus::class);
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($status);

		$this->assertEquals($status, $this->service->findByUserId('john.doe'));
	}

	public function testFindByUserIdDoesNotExist(): void {
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(DoesNotExistException::class);
		$this->service->findByUserId('john.doe');
	}

	/**
	 * @param string $userId
	 * @param string $statusType
	 * @param string|null $statusIcon
	 * @param string|null $message
	 * @param int|null $clearAt
	 * @param bool $expectExisting
	 * @param bool $expectSuccess
	 * @param bool $expectException
	 * @param string|null $expectedExceptionClass
	 * @param string|null $expectedExceptionMessage
	 *
	 * @dataProvider setStatusDataProvider
	 */
	public function testSetStatus(string $userId,
								  string $statusType,
								  ?string $statusIcon,
								  ?string $message,
								  ?int $clearAt,
								  bool $expectExisting,
								  bool $expectSuccess,
								  bool $expectException,
								  ?string $expectedExceptionClass,
								  ?string $expectedExceptionMessage): void {
		$userStatus = new UserStatus();

		if ($expectExisting) {
			$userStatus->setId(42);
			$userStatus->setUserId($userId);

			$this->mapper->expects($this->once())
				->method('findByUserId')
				->with($userId)
				->willReturn($userStatus);
		} else {
			$this->mapper->expects($this->once())
				->method('findByUserId')
				->with($userId)
				->willThrowException(new DoesNotExistException(''));
		}

		if ($expectSuccess) {
			if ($expectExisting) {
				$this->mapper->expects($this->once())
					->method('update')
					->willreturnCallback(static function ($userStatusParameter) {
						return $userStatusParameter;
					});
			} else {
				$this->mapper->expects($this->once())
					->method('insert')
					->willreturnCallback(static function ($userStatusParameter) {
						return $userStatusParameter;
					});
			}
		}

		$this->timeFactory
			->method('getTime')
			->willReturn(40);

		if ($expectException) {
			$this->expectException($expectedExceptionClass);
			$this->expectExceptionMessage($expectedExceptionMessage);

			$this->service->setStatus($userId, $statusType, $statusIcon, $message, $clearAt);
		}

		if ($expectSuccess) {
			$actual = $this->service->setStatus($userId, $statusType, $statusIcon, $message, $clearAt);
			$this->assertEquals('john.doe', $actual->getUserId());
			$this->assertEquals($statusType, $actual->getStatusType());
			$this->assertEquals($statusIcon, $actual->getStatusIcon());
			$this->assertEquals($message, $actual->getMessage());
			$this->assertEquals($clearAt, $actual->getClearAt());
			$this->assertEquals(40, $actual->getCreatedAt());
		}
	}

	public function setStatusDataProvider(): array {
		return [
			// Valid requests
			['john.doe', 'unavailable', '🏝', 'On vacation', 50, true, true, false, null, null],
			['john.doe', 'unavailable', '🏝', 'On vacation', 50, false, true, false, null, null],
			['john.doe', 'busy', '📱', 'In a phone call', 42, true, true, false, null, null],
			['john.doe', 'busy', '📱', 'In a phone call', 42, false, true, false, null, null],
			['john.doe', 'available', '🏢', 'At work', null, true, true, false, null, null],
			['john.doe', 'available', '🏢', 'At work', null, false, true, false, null, null],
			// Unknown status type
			['john.doe', 'out-of-office', '📱', 'In a phone call', 42, true, false, true, InvalidStatusTypeException::class,
				'Status-type "out-of-office" is not supported'],
			['john.doe', 'out-of-office', '📱', 'In a phone call', 42, false, false, true, InvalidStatusTypeException::class,
				'Status-type "out-of-office" is not supported'],
			// More than one character for the emoji
			['john.doe', 'busy', '📱📠', 'In a phone call', 42, true, false, true, InvalidStatusIconException::class,
				'Status-Icon is longer than one character'],
			['john.doe', 'busy', '📱📠', 'In a phone call', 42, false, false, true, InvalidStatusIconException::class,
				'Status-Icon is longer than one character'],
			// Message too long
			['john.doe', 'busy', '📱', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', 42, true, false, true, StatusMessageTooLongException::class,
				'Message is longer than supported length of 80 characters'],
			['john.doe', 'busy', '📱', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.', 42, false, false, true, StatusMessageTooLongException::class,
				'Message is longer than supported length of 80 characters'],
			// Message too long - testing it counts characters, not bytes
			['john.doe', 'busy', '📱', '🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃', 42, true,  true, false, null, null],
			['john.doe', 'busy', '📱', '🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃🙃', 42, false,  true, false, null, null],
			// Clear at is in the past
			['john.doe', 'busy', '📱', 'In a phone call', 10, true, false, true, InvalidClearAtException::class, 'ClearAt is in the past'],
			['john.doe', 'busy', '📱', 'In a phone call', 10, false, false, true, InvalidClearAtException::class, 'ClearAt is in the past'],
		];
	}

	public function testRemoveUserStatus(): void {
		$status = $this->createMock(UserStatus::class);
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($status);

		$this->mapper->expects($this->once())
			->method('delete')
			->with($status);

		$actual = $this->service->removeUserStatus('john.doe');
		$this->assertTrue($actual);
	}

	public function testRemoveUserStatusDoesNotExist(): void {
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willThrowException(new DoesNotExistException(''));

		$this->mapper->expects($this->never())
			->method('delete');

		$actual = $this->service->removeUserStatus('john.doe');
		$this->assertFalse($actual);
	}
}
