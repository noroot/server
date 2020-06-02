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

namespace OCA\UserStatus\Tests\Controller;

use OCA\UserStatus\Controller\UserStatusController;
use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Exception\InvalidClearAtException;
use OCA\UserStatus\Exception\InvalidStatusIconException;
use OCA\UserStatus\Exception\InvalidStatusTypeException;
use OCA\UserStatus\Exception\StatusMessageTooLongException;
use OCA\UserStatus\Service\StatusService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\ILogger;
use OCP\IRequest;
use Test\TestCase;
use Throwable;

class UserStatusControllerTest extends TestCase {

	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;

	/** @var StatusService|\PHPUnit\Framework\MockObject\MockObject */
	private $service;

	/** @var UserStatusController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$request = $this->createMock(IRequest::class);
		$userId = 'john.doe';
		$this->logger = $this->createMock(ILogger::class);
		$this->service = $this->createMock(StatusService::class);

		$this->controller = new UserStatusController('user_status', $request,
			$userId, $this->logger, $this->service);
	}

	public function testGetStatus(): void {
		$userStatus = $this->getUserStatus();

		$this->service->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($userStatus);

		$response = $this->controller->getStatus();
		$this->assertEquals([
			'id' => 1337,
			'userId' => 'john.doe',
			'statusType' => 'unavailable',
			'statusIcon' => '🏝',
			'message' => 'On vacation',
			'createdAt' => 10000,
			'clearAt' => 60000,
		], $response->getData());
	}

	public function testGetStatusDoesNotExist(): void {
		$this->service->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(OCSNotFoundException::class);
		$this->expectExceptionMessage('No status for the current user');

		$this->controller->getStatus();
	}

	/**
	 * @param string $statusType
	 * @param string|null $statusIcon
	 * @param string|null $message
	 * @param int|null $clearAt
	 * @param bool $expectSuccess
	 * @param bool $expectException
	 * @param Throwable|null $exception
	 * @param string|null $exceptionMessage
	 * @param bool $expectLogger
	 * @param string|null $expectedLogMessage
	 *
	 * @dataProvider setStatusDataProvider
	 */
	public function testSetStatus(string $statusType,
								  ?string $statusIcon,
								  ?string $message,
								  ?int $clearAt,
								  bool $expectSuccess,
								  bool $expectException,
								  ?Throwable $exception,
								  bool $expectLogger,
								  ?string $expectedLogMessage): void {
		$userStatus = $this->getUserStatus();

		if ($expectException) {
			$this->service->expects($this->once())
				->method('setStatus')
				->with('john.doe', $statusType, $statusIcon, $message, $clearAt)
				->willThrowException($exception);
		} else {
			$this->service->expects($this->once())
				->method('setStatus')
				->with('john.doe', $statusType, $statusIcon, $message, $clearAt)
				->willReturn($userStatus);
		}

		if ($expectLogger) {
			$this->logger->expects($this->once())
				->method('debug')
				->with($expectedLogMessage);
		}
		if ($expectException) {
			$this->expectException(OCSBadRequestException::class);
			$this->expectExceptionMessage('Original exception message');
		}

		$response = $this->controller->setStatus($statusType, $statusIcon, $message, $clearAt);

		if ($expectSuccess) {
			$this->assertEquals([
				'id' => 1337,
				'userId' => 'john.doe',
				'statusType' => 'unavailable',
				'statusIcon' => '🏝',
				'message' => 'On vacation',
				'createdAt' => 10000,
				'clearAt' => 60000,
			], $response->getData());
		}
	}

	public function setStatusDataProvider(): array {
		return [
			['busy', '👨🏽‍💻', 'Busy developing the status feature', 500, true, false, null, false, null],
			['busy', '👨🏽‍💻', 'Busy developing the status feature', 500, false, true, new InvalidClearAtException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to an invalid clearAt value "500"'],
			['busy', '👨🏽‍💻', 'Busy developing the status feature', 500, false, true, new InvalidStatusIconException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to an invalid icon value "👨🏽‍💻"'],
			['busy', '👨🏽‍💻', 'Busy developing the status feature', 500, false, true, new InvalidStatusTypeException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to an invalid status type "busy"'],
			['busy', '👨🏽‍💻', 'Busy developing the status feature', 500, false, true, new StatusMessageTooLongException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to a too long status message.'],
		];
	}

	public function testClearStatus(): void {
		$this->service->expects($this->once())
			->method('removeUserStatus')
			->with('john.doe');

		$response = $this->controller->clearStatus();
		$this->assertEquals([], $response->getData());
	}

	private function getUserStatus(): UserStatus {
		$userStatus = new UserStatus();
		$userStatus->setId(1337);
		$userStatus->setUserId('john.doe');
		$userStatus->setStatusType('unavailable');
		$userStatus->setStatusIcon('🏝');
		$userStatus->setMessage('On vacation');
		$userStatus->setCreatedAt(10000);
		$userStatus->setClearAt(60000);

		return $userStatus;
	}
}
