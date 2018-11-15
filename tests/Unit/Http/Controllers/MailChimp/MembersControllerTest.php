<?php
declare(strict_types=1);

namespace Tests\App\Unit\Http\Controllers\MailChimp;

use App\Http\Controllers\MailChimp\MembersController;
use Tests\App\TestCases\MailChimp\MemberTestCase;

class MembersControllerTest extends MemberTestCase
{
    /**
     * Test controller returns error response when exception is thrown during create MailChimp request.
     *
     * @return void
     */
    public function testCreateMemberMailChimpException(): void
    {
        /** @noinspection PhpParamsInspection Mock given on purpose */
        $controller = new MembersController($this->entityManager, $this->mockMailChimpForException('post'));

        // create test list
        $list = $this->createDbList(self::$listData);

        // If there is no list id, skip
        if (null === $list->getId()) {
            self::markTestSkipped('Unable to create, no id provided for list');

            return;
        }

        $this->assertMailChimpExceptionResponse($controller->create($this->getRequest(static::$memberData), $list->getId()));
    }

    /**
     * Test controller returns error response when exception is thrown during remove MailChimp request.
     *
     * @return void
     */
    public function testRemoveMemberMailChimpException(): void
    {
        /** @noinspection PhpParamsInspection Mock given on purpose */
        $controller = new MembersController($this->entityManager, $this->mockMailChimpForException('delete'));
        $list = $this->createDbList(static::$listData);

        // If there is no list id, skip
        if (null === $list->getId()) {
            self::markTestSkipped('Unable to remove, no id provided for list');

            return;
        }

        $member = $this->createDbMember(self::$memberData, $list->getId());

        // If there is no member id, skip
        if (null === $member->getId()) {
            self::markTestSkipped('Unable to update, no id provided for member');

            return;
        }

        $this->assertMailChimpExceptionResponse($controller->remove($list->getId(), $member->getId()));
    }

    /**
     * Test controller returns error response when exception is thrown during update MailChimp request.
     *
     * @return void
     */
    public function testUpdateMemberMailChimpException(): void
    {
        /** @noinspection PhpParamsInspection Mock given on purpose */
        $controller = new MembersController($this->entityManager, $this->mockMailChimpForException('patch'));
        $list = $this->createDbList(static::$listData);

        // If there is no list id, skip
        if (null === $list->getId()) {
            self::markTestSkipped('Unable to update, no id provided for list');

            return;
        }

        $member = $this->createDbMember(self::$memberData, $list->getId());

        // If there is no member id, skip
        if (null === $member->getId()) {
            self::markTestSkipped('Unable to update, no id provided for member');

            return;
        }

        $this->assertMailChimpExceptionResponse($controller->update($this->getRequest(), $list->getId(), $member->getId()));
    }
}
