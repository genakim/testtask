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
        'name' => 'New list',
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
        'email_address' => 'urist.mcvankab+3@freddie.com',
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
    private $createdMembers = [];

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

            if(array_key_exists($listId, $this->createdMembers)){

                $createdMembers = $this->createdMembers[$listId];

                foreach ($createdMembers as $subscriberHash){

                    $mailChimp->delete(\sprintf('lists/%s/members/%s', $listId, $subscriberHash));
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
        //$this->seeJsonStructure(static::$memberData);
        self::assertArrayHasKey('mail_chimp_id', $member);
        self::assertNotNull($member['mail_chimp_id']);

        return $member;
    }

    /**
     * Test application returns empty successful response when removing existing member.
     *
     * @param array $member
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

    public function testShowMemberSuccessfully()
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
        $member = \json_decode($this->response->getContent(), true);

        $this->assertResponseOk();
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
            $this->createdMembers[$listId] = md5(strtolower($member['email_address']));
        }

        return $member;
    }

}
