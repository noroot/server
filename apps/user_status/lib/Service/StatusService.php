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

namespace OCA\UserStatus\Service;

use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Db\UserStatusMapper;
use OCA\UserStatus\Exception\InvalidClearAtException;
use OCA\UserStatus\Exception\InvalidStatusIconException;
use OCA\UserStatus\Exception\InvalidStatusTypeException;
use OCA\UserStatus\Exception\StatusMessageTooLongException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Class StatusService
 *
 * @package OCA\UserStatus\Service
 */
class StatusService {

	/** @var UserStatusMapper */
	private $mapper;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * @var string[]
	 */
	private $allowedStatusTypes = [
		'available',
		'busy',
		'unavailable',
	];

	/** @var int */
	private $maximumMessageLength = 80;

	/**
	 * StatusService constructor.
	 *
	 * @param UserStatusMapper $mapper
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(UserStatusMapper $mapper,
								ITimeFactory $timeFactory) {
		$this->mapper = $mapper;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 */
	public function findAll(?int $limit = null, ?int $offset = null): array {
		return $this->mapper->findAll($limit, $offset);
	}

	/**
	 * @param string $userId
	 * @return UserStatus
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function findByUserId(string $userId): ?UserStatus {
		return $this->mapper->findByUserId($userId);
	}

	/**
	 * @param string $userId
	 * @param string $statusType
	 * @param string|null $statusIcon
	 * @param string|null $message
	 * @param int|null $clearAt
	 * @return UserStatus
	 * @throws InvalidClearAtException
	 * @throws InvalidStatusIconException
	 * @throws InvalidStatusTypeException
	 * @throws StatusMessageTooLongException
	 */
	public function setStatus(string $userId,
							  string $statusType,
							  ?string $statusIcon,
							  ?string $message,
							  ?int $clearAt): UserStatus {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			$userStatus = new UserStatus();
			$userStatus->setUserId($userId);
		}

		// Check if status-type is valid
		if (!\in_array($statusType, $this->allowedStatusTypes, true)) {
			throw new InvalidStatusTypeException('Status-type "' . $statusType . '" is not supported');
		}
		// Check if statusIcon contains only one character
		if (\mb_strlen($statusIcon) > 1) {
			throw new InvalidStatusIconException('Status-Icon is longer than one character');
		}
		// Check for maximum length of custom message
		if ($message !== null && \mb_strlen($message) > $this->maximumMessageLength) {
			throw new StatusMessageTooLongException('Message is longer than supported length of ' . $this->maximumMessageLength . ' characters');
		}
		// Check that clearAt is in the future
		if ($clearAt !== null && $clearAt < $this->timeFactory->getTime()) {
			throw new InvalidClearAtException('ClearAt is in the past');
		}

		$userStatus->setStatusType($statusType);
		$userStatus->setStatusIcon($statusIcon);
		$userStatus->setMessage($message);
		$userStatus->setCreatedAt($this->timeFactory->getTime());
		$userStatus->setClearAt($clearAt);

		if ($userStatus->getId() === null) {
			return $this->mapper->insert($userStatus);
		}

		return $this->mapper->update($userStatus);
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function removeUserStatus(string $userId): bool {
		try {
			$userStatus = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			// if there is no status to remove, just return
			return false;
		}

		$this->mapper->delete($userStatus);
		return true;
	}
}
