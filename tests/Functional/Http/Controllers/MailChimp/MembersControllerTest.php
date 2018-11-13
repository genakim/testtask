<?php
declare(strict_types=1);

namespace Tests\App\Functional\Http\Controllers\MailChimp;

use Tests\App\TestCases\MailChimp\MemberTestCase;
use Tests\App\TestCases\WithDatabaseTestCase;
use App\Database\Entities\MailChimp\MailChimpList;
use Mailchimp\Mailchimp;

class MembersControllerTest extends WithDatabaseTestCase
{
    /**
     * @var array
     */
    protected static $listData = [
        'name' => 'Test list',
        'permission_reminder' => 'You signed up for updates on Greeks economy.',
        'email_type_option' => false,
        'contact' => [
            'company' => 'Doe Ltd.',
            'address1' => 'DoeStreet 1',
            'address2' => '',
            'city' => 'Doesy',
            'state' => 'Doedoe',
            'zip' => '1672-12',
            'country' => 'US',
            'phone' => '55533344412'
        ],
        'campaign_defaults' => [
            'from_name' => 'John Doe',
            'from_email' => 'john@doe.com',
            'subject' => 'My new campaign!',
            'language' => 'US'
        ],
        'visibility' => 'prv',
        'use_archive_bar' => false,
        'notify_on_subscribe' => 'notify@loyaltycorp.com.au',
        'notify_on_unsubscribe' => 'notify@loyaltycorp.com.au'
    ];

    /**
     * @var array
     */
    protected static $memberData = [
        'email_address' => 'urist.mcvankab@test.com',
        'email_type' => 'text',
        'status' => 'pending',
        'merge_fields' => [
            'name' => 'field_name_1',
            'type' => 'text'
        ],
        'language' => 'english',
        'vip' => false,
        'location' => [
            'latitude' => 41.304566,
            'longitude' => 69.244854
        ],
        'marketing_permissions' => [
            [
                'marketing_permission_id' => 'id',
                'text' => 'permission_text',
                'enabled' => false
            ]
        ],
        'ip_signup' => '192.168.0.1',
        'timestamp_signup' => '2018-11-01 00:00:00',
        'ip_opt' => '192.168.0.1',
        'timestamp_opt' => '2018-11-02 00:00:00',
        'tags' => [
            'a tag',
            'another tag'
        ]
    ];

    /**
     * @var array
     */
    private $createdListIds = [];

    /**
     * @var array
     */
    private $createdMemberHashes = [];

    /**
     * @var array
     */
    protected static $notRequired = [
        'email_type',
        'merge_fields',
        'interests',
        'language',
        'vip',
        'location',
        'marketing_permissions',
        'ip_signup',
        'timestamp_signup',
        'ip_opt',
        'timestamp_opt',
        'tags'
    ];

    /**
     * Call MailChimp to delete members and lists created during test.
     *
     * @return void
     */
    public function tearDown(): void
    {
        /** @var Mailchimp $mailChimp */
        $mailChimp = $this->app->make(Mailchimp::class);

        foreach ($this->createdListIds as $listId) {

            // Delete member on MailChimp after test first
            if(array_key_exists($listId, $this->createdMemberHashes)){
                
                foreach ($this->createdMemberHashes[$listId] as $hash){

                    $mailChimp->delete(\sprintf('lists/%s/members/%s', $listId, $hash));
                }
            }

            // Delete list on MailChimp after test
            $mailChimp->delete(\sprintf('lists/%s', $listId));
        }

        parent::tearDown();
    }

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
        $list = $this->createTestList();

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createTestMember($list['list_id']);

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
        $list = $this->createTestList();

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
        $list = $this->createTestList();

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
        $list = $this->createTestList();

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createTestMember($list['list_id']);

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
        $list = $this->createTestList();

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createTestMember($list['list_id']);

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
        $list = $this->createTestList();

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
        $list = $this->createTestList();

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createTestMember($list['list_id']);

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
        $list = $this->createTestList();

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
        $list = $this->createTestList();

        /**
         * Create test member
         *
         * @var array $member
         */
        $member = $this->createTestMember($list['list_id']);

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
     * Create test MailChimp list
     *
     * @return array
     */
    protected function createTestList(): array
    {
        $this->post('/mailchimp/lists', static::$listData);

        $list = \json_decode($this->response->content(), true);

        if (isset($list['mail_chimp_id'])) {
            $this->createdListIds[] = $list['mail_chimp_id']; // Store MailChimp list id for cleaning purposes
        }

        return $list;
    }

    /**
     * Create test MailChimp member
     *
     * @param string $listId
     *
     * @return array
     */
    protected function createTestMember(string $listId): array {

        $this->post(\sprintf('/mailchimp/lists/%s/members', $listId), static::$memberData);

        $member = \json_decode($this->response->getContent(), true);

        if (isset($member['email_address'])) {
            // Store MailChimp member' email address hash for cleaning purposes
            $this->createdMemberHashes[$listId] = md5(strtolower($member['email_address']));
        }

        return $member;
    }

    /**
     * Asserts error response when list not found.
     *
     * @param string $listId
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
