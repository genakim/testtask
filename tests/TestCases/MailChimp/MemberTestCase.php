<?php
declare(strict_types=1);

namespace Tests\App\TestCases\MailChimp;

use App\Database\Entities\MailChimp\MailChimpList;
use App\Database\Entities\MailChimp\MailChimpMember;
use Illuminate\Http\JsonResponse;
use Mailchimp\Mailchimp;
use Mockery;
use Mockery\MockInterface;
use Tests\App\TestCases\WithDatabaseTestCase;
use Faker;

abstract class MemberTestCase extends WithDatabaseTestCase
{
    protected const MAILCHIMP_EXCEPTION_MESSAGE = 'MailChimp exception';


    /**
     * @var array
     */
    protected $createdMemberHashes = [];

    /**
     * @var array
     */
    protected $createdListIds = [];

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

    public function setUp(): void
    {
        parent::setUp();

        $faker = Faker\Factory::create();

        /**
         * generate email for test member
         * to avoid error MailChimp server
         * related to multiple action with same member
         */
        self::$memberData['email_address'] = $faker->email;
    }

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

                $createdMembers = $this->createdMemberHashes[$listId];

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
     * Asserts error response when list not found.
     *
     * @param string $listId
     *
     * @return void
     */
    protected function assertListNotFoundResponse(string $listId): void
    {
        $content = \json_decode($this->response->content(), true);

        $this->assertResponseStatus(404);
        self::assertArrayHasKey('message', $content);
        self::assertEquals(\sprintf('MailChimpList[%s] not found', $listId), $content['message']);
    }

    /**
     * Asserts error response when MailChimp exception is thrown.
     *
     * @param \Illuminate\Http\JsonResponse $response
     *
     * @return void
     */
    protected function assertMailChimpExceptionResponse(JsonResponse $response): void
    {
        $content = \json_decode($response->content(), true);

        self::assertEquals(400, $response->getStatusCode());
        self::assertArrayHasKey('message', $content);
        self::assertEquals(self::MAILCHIMP_EXCEPTION_MESSAGE, $content['message']);
    }

    /**
     * Create MailChimp list into database.
     *
     * @param array $data
     *
     * @return \App\Database\Entities\MailChimp\MailChimpList
     */
    protected function createDbList(array $data): MailChimpList
    {
        $list = new MailChimpList($data);

        $this->entityManager->persist($list);
        $this->entityManager->flush();

        return $list;
    }

    /**
     * Create MailChimp list into database and MailChimp web service
     *
     * @param array $data
     *
     * @return array
     */
    protected function createList(array $data): array
    {
        $this->post('/mailchimp/lists', $data);

        $list = \json_decode($this->response->content(), true);

        if (isset($list['mail_chimp_id'])) {
            $this->createdListIds[] = $list['mail_chimp_id']; // Store MailChimp list id for cleaning purposes
        }

        return $list;
    }

    /**
     * Create MailChimp member into database.
     *
     * @param array $data
     *
     * @param string $listId
     *
     * @return \App\Database\Entities\MailChimp\MailChimpMember
     */
    protected function createDbMember(array $data, string $listId): MailChimpMember
    {
        $member = new MailChimpMember($data);

        $member->setList($listId);

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return $member;
    }


    /**
     * Create MailChimp member into database and MailChimp web service
     *
     * @param array $data
     *
     * @param string $listId
     *
     * @return array
     */
    protected function createMember(array $data, string $listId): array {

        $this->post(\sprintf('/mailchimp/lists/%s/members', $listId), $data);

        $member = \json_decode($this->response->getContent(), true);

        if (isset($member['email_address'])) {
            // Store MailChimp member' email address hash for cleaning purposes
            $this->createdMemberHashes[$listId] = md5(strtolower($member['email_address']));
        }

        return $member;
    }


    /**
     * Returns mock of MailChimp to trow exception when requesting their API.
     *
     * @param string $method
     *
     * @return \Mockery\MockInterface
     *
     * @SuppressWarnings(PHPMD.StaticAccess) Mockery requires static access to mock()
     */
    protected function mockMailChimpForException(string $method): MockInterface
    {
        $mailChimp = Mockery::mock(Mailchimp::class);

        $mailChimp
            ->shouldReceive($method)
            ->once()
            ->withArgs(function (string $method, ?array $options = null) {
                return !empty($method) && (null === $options || \is_array($options));
            })
            ->andThrow(new \Exception(self::MAILCHIMP_EXCEPTION_MESSAGE));

        return $mailChimp;
    }
}
