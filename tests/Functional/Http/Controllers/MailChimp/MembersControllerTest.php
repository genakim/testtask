<?php
declare(strict_types=1);

namespace Tests\App\Functional\Http\Controllers\MailChimp;

use Tests\App\TestCases\MailChimp\MemberTestCase;
use Tests\App\TestCases\WithDatabaseTestCase;
use App\Database\Entities\MailChimp\MailChimpList;
use Mailchimp\Mailchimp;

class MembersControllerTest extends MemberTestCase
{
    /**
     * Test application creates successfully member and returns it back with id from MailChimp.
     *
     * @return array
     */
    public function testCreateMemberSuccessfully(): array
    {
        /**
         * Create test list
         *
         * @var array $list
         */
        $list = $this->createList(self::$listData);

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createMember(self::$memberData, $list['list_id']);

        $this->assertResponseOk();
        $this->seeJson(static::$memberData);
        //$this->seeJsonStructure(static::$memberData);
        self::assertArrayHasKey('mail_chimp_id', $member);
        self::assertNotNull($member['mail_chimp_id']);

        return $member;
    }

    /**
     * Test application returns error response with errors when member validation fails.
     *
     * @return void
     */
    public function testCreateMemberValidationFailed(): void
    {
        /**
         * Create test list
         *
         * @var array $list
         */
        $list = $this->createList(self::$listData);

        $this->post(\sprintf('/mailchimp/lists/%s/members', $list['list_id']));

        $content = \json_decode($this->response->getContent(), true);

        $this->assertResponseStatus(400);
        self::assertArrayHasKey('message', $content);
        self::assertArrayHasKey('errors', $content);
        self::assertEquals('Invalid data given', $content['message']);

        foreach (\array_keys(static::$memberData) as $key) {
            if (\in_array($key, static::$notRequired, true)) {
                continue;
            }

            self::assertArrayHasKey($key, $content['errors']);
        }
    }

    /**
     * Test application returns error response when member not found.
     *
     * @return void
     */
    public function testRemoveMemberNotFoundException(): void
    {
        /**
        * Create test list
         *
         * @var array $list
        */
        $list = $this->createList(self::$listData);

        $this->delete(\sprintf('/mailchimp/lists/%s/members/%s', $list['list_id'], 'invalid-member-id'));

        $this->assertMemberNotFoundResponse('invalid-member-id');
    }

    /**
     * Test application returns empty successful response when removing existing member.
     *
     * @return void
     */
    public function testRemoveMemberSuccessfully(): void
    {
        /**
         * Create test list
         *
         * @var array $list
         */
        $list = $this->createList(self::$listData);

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createMember(self::$memberData, $list['list_id']);

        // try delete test member
        $this->delete(\sprintf('/mailchimp/lists/%s/members/%s', $list['list_id'], $member['member_id']));

        $this->assertResponseOk();
        self::assertEmpty(\json_decode($this->response->content(), true));
    }


    /**
     * Test application returns successful response with member data when requesting existing member.
     *
     * @return void
     */
    public function testShowMemberSuccessfully(): void
    {
        /**
         * Create test list
         *
         * @var array $list
         */
        $list = $this->createList(self::$listData);

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createMember(self::$memberData, $list['list_id']);

        // try show member
        $this->get(\sprintf('/mailchimp/lists/%s/members/%s', $list['list_id'], $member['member_id']));
        $content = \json_decode($this->response->getContent(), true);

        $this->assertResponseOk();

        foreach (static::$memberData as $key => $value) {
            self::assertArrayHasKey($key, $content);
            self::assertEquals($value, $content[$key]);
        }
    }

    /**
     * Test application returns error response when member not found.
     *
     * @return void
     */
    public function testShowMemberNotFoundException(): void
    {
        /**
         * Create test list
         *
         * @var array $list
         */
        $list = $this->createList(self::$listData);

        $this->get(\sprintf('/mailchimp/lists/%s/members/%s', $list['list_id'], 'invalid-member-id'));

        $this->assertMemberNotFoundResponse('invalid-member-id');
    }

    public function testUpdateMemberSuccessfully()
    {
        /**
         * Create test list
         *
         * @var array $list
         */
        $list = $this->createList(self::$listData);

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createMember(self::$memberData, $list['list_id']);

        // try update member
        $this->put(\sprintf('/mailchimp/lists/%s/members/%s', $list['list_id'], $member['member_id']),
            ['status' => 'subscribed']);
        $content = \json_decode($this->response->content(), true);

        $this->assertResponseOk();

        foreach (\array_keys(static::$memberData) as $key) {
            self::assertArrayHasKey($key, $content);
            self::assertEquals('subscribed', $content['status']);
        }
    }

    public function testUpdateMemberNotFoundException(): void
    {
        /**
         * Create test list
         *
         * @var array $list
         */
        $list = $this->createList(self::$listData);

        $this->put(\sprintf('/mailchimp/lists/%s/members/%s', $list['list_id'], 'invalid-member-id'));

        $this->assertMemberNotFoundResponse('invalid-member-id');
    }

    public function testUpdateMemberValidationFailed(): void
    {
        /**
         * Create test list
         *
         * @var array $list
         */
        $list = $this->createList(self::$listData);

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createMember(self::$memberData, $list['list_id']);

        $this->put(\sprintf('/mailchimp/lists/%s/members/%s', $list['list_id'], $member['member_id']),
            ['status' => 'invalid']);

        $content = \json_decode($this->response->content(), true);

        $this->assertResponseStatus(400);
        self::assertArrayHasKey('message', $content);
        self::assertArrayHasKey('errors', $content);
        self::assertArrayHasKey('status', $content['errors']);
        self::assertEquals('Invalid data given', $content['message']);
    }

    /**
     * Asserts error response when list not found.
     *
     * @param string $memberId
     *
     * @return void
     */
    protected function assertMemberNotFoundResponse(string $memberId): void
    {
        $content = \json_decode($this->response->content(), true);

        $this->assertResponseStatus(404);
        self::assertArrayHasKey('message', $content);
        self::assertEquals(\sprintf('MailChimpMember[%s] not found', $memberId), $content['message']);
    }

}
