<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional;

use Doctrine\ODM\MongoDB\DocumentNotFoundException;
use Doctrine\ODM\MongoDB\Query\FilterCollection;
use Doctrine\ODM\MongoDB\Tests\BaseTestCase;
use Documents\Group;
use Documents\Profile;
use Documents\User;
use MongoDB\BSON\ObjectId;

use function sort;

class FilterTest extends BaseTestCase
{
    /** @var array<string, ObjectId|string|null> */
    private array $ids;

    private FilterCollection $fc;

    public function setUp(): void
    {
        parent::setUp();

        $this->ids = [];

        $groupA = new Group('groupA');
        $groupB = new Group('groupB');

        $profile = new Profile();
        $profile->setFirstName('Timothy');

        $tim = new User();
        $tim->setUsername('Tim');
        $tim->setHits(10);
        $tim->addGroup($groupA);
        $tim->addGroup($groupB);
        $tim->setProfile($profile);
        $this->dm->persist($tim);

        $john = new User();
        $john->setUsername('John');
        $john->setHits(10);
        $this->dm->persist($john);

        $this->dm->flush();
        $this->dm->clear();

        $this->ids['tim']  = $tim->getId();
        $this->ids['john'] = $john->getId();

        $this->fc = $this->dm->getFilterCollection();
    }

    protected function enableUserFilter(): void
    {
        $this->fc->enable('testFilter');
        $testFilter = $this->fc->getFilter('testFilter');
        $testFilter->setParameter('class', User::class);
        $testFilter->setParameter('field', 'username');
        $testFilter->setParameter('value', 'Tim');
    }

    protected function enableGroupFilter(): void
    {
        $this->fc->enable('testFilter');
        $testFilter = $this->fc->getFilter('testFilter');
        $testFilter->setParameter('class', Group::class);
        $testFilter->setParameter('field', 'name');
        $testFilter->setParameter('value', 'groupA');
    }

    protected function enableProfileFilter(): void
    {
        $this->fc->enable('testFilter');
        $testFilter = $this->fc->getFilter('testFilter');
        $testFilter->setParameter('class', Profile::class);
        $testFilter->setParameter('field', 'firstname');
        $testFilter->setParameter('value', 'Something Else');
    }

    public function testRepositoryFind(): void
    {
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithFind());

        $this->enableUserFilter();
        $this->dm->clear();
        self::assertEquals(['Tim'], $this->getUsernamesWithFind());

        $this->fc->disable('testFilter');
        $this->dm->clear();
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithFind());
    }

    protected function getUsernamesWithFind(): array
    {
        $repository = $this->dm->getRepository(User::class);

        $tim  = $repository->find($this->ids['tim']);
        $john = $repository->find($this->ids['john']);

        $usernames = [];

        if (isset($tim)) {
            $usernames[] = $tim->getUsername();
        }

        if (isset($john)) {
            $usernames[] = $john->getUsername();
        }

        sort($usernames);

        return $usernames;
    }

    public function testRepositoryFindBy(): void
    {
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithFindBy());

        $this->enableUserFilter();
        $this->dm->clear();
        self::assertEquals(['Tim'], $this->getUsernamesWithFindBy());

        $this->fc->disable('testFilter');
        $this->dm->clear();
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithFindBy());
    }

    protected function getUsernamesWithFindBy(): array
    {
        $all = $this->dm->getRepository(User::class)->findBy(['hits' => 10]);

        $usernames = [];
        foreach ($all as $user) {
            $usernames[] = $user->getUsername();
        }

        sort($usernames);

        return $usernames;
    }

    public function testRepositoryFindOneBy(): void
    {
        self::assertEquals('John', $this->getJohnsUsernameWithFindOneBy());

        $this->enableUserFilter();
        $this->dm->clear();
        self::assertEquals(null, $this->getJohnsUsernameWithFindOneBy());

        $this->fc->disable('testFilter');
        $this->dm->clear();
        self::assertEquals('John', $this->getJohnsUsernameWithFindOneBy());
    }

    protected function getJohnsUsernameWithFindOneBy(): ?string
    {
        $john = $this->dm->getRepository(User::class)->findOneBy(['id' => $this->ids['john']]);

        return isset($john) ? $john->getUsername() : null;
    }

    public function testRepositoryFindAll(): void
    {
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithFindAll());

        $this->enableUserFilter();
        $this->dm->clear();
        self::assertEquals(['Tim'], $this->getUsernamesWithFindAll());

        $this->fc->disable('testFilter');
        $this->dm->clear();
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithFindAll());
    }

    protected function getUsernamesWithFindAll(): array
    {
        $all = $this->dm->getRepository(User::class)->findAll();

        $usernames = [];
        foreach ($all as $user) {
            $usernames[] = $user->getUsername();
        }

        sort($usernames);

        return $usernames;
    }

    public function testReferenceMany(): void
    {
        self::assertEquals(['groupA', 'groupB'], $this->getGroupsByReference());

        $this->enableGroupFilter();
        $this->dm->clear();
        self::assertEquals(['groupA'], $this->getGroupsByReference());

        $this->fc->disable('testFilter');
        $this->dm->clear();
        self::assertEquals(['groupA', 'groupB'], $this->getGroupsByReference());
    }

    protected function getGroupsByReference(): array
    {
        $tim = $this->dm->getRepository(User::class)->find($this->ids['tim']);

        $groupnames = [];
        foreach ($tim->getGroups() as $group) {
            try {
                $groupnames[] = $group->getName();
            } catch (DocumentNotFoundException) {
               //Proxy object filtered
            }
        }

        sort($groupnames);

        return $groupnames;
    }

    public function testReferenceOne(): void
    {
        self::assertEquals('Timothy', $this->getProfileByReference());

        $this->enableProfileFilter();
        $this->dm->clear();
        self::assertEquals(null, $this->getProfileByReference());

        $this->fc->disable('testFilter');
        $this->dm->clear();
        self::assertEquals('Timothy', $this->getProfileByReference());
    }

    protected function getProfileByReference(): ?string
    {
        $tim = $this->dm->getRepository(User::class)->find($this->ids['tim']);

        $profile = $tim->getProfile();
        try {
            return $profile->getFirstname();
        } catch (DocumentNotFoundException) {
            //Proxy object filtered
            return null;
        }
    }

    public function testDocumentManagerRef(): void
    {
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithDocumentManager());

        $this->enableUserFilter();
        $this->dm->clear();
        self::assertEquals(['Tim'], $this->getUsernamesWithDocumentManager());

        $this->fc->disable('testFilter');
        $this->dm->clear();
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithDocumentManager());
    }

    protected function getUsernamesWithDocumentManager(): array
    {
        $tim  = $this->dm->getReference(User::class, $this->ids['tim']);
        $john = $this->dm->getReference(User::class, $this->ids['john']);

        $usernames = [];

        try {
            $usernames[] = $tim->getUsername();
        } catch (DocumentNotFoundException) {
            //Proxy object filtered
        }

        try {
            $usernames[] = $john->getUsername();
        } catch (DocumentNotFoundException) {
            //Proxy object filtered
        }

        sort($usernames);

        return $usernames;
    }

    public function testQuery(): void
    {
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithQuery());

        $this->enableUserFilter();
        $this->dm->clear();
        self::assertEquals(['Tim'], $this->getUsernamesWithQuery());

        $this->fc->disable('testFilter');
        $this->dm->clear();
        self::assertEquals(['John', 'Tim'], $this->getUsernamesWithQuery());
    }

    protected function getUsernamesWithQuery(): array
    {
        $qb    = $this->dm->createQueryBuilder(User::class);
        $query = $qb->getQuery();
        $all   = $query->execute();

        $usernames = [];
        foreach ($all as $user) {
            $usernames[] = $user->getUsername();
        }

        sort($usernames);

        return $usernames;
    }

    public function testMultipleFiltersOnSameField(): void
    {
        $this->fc->enable('testFilter');
        $testFilter = $this->fc->getFilter('testFilter');
        $testFilter->setParameter('class', User::class);
        $testFilter->setParameter('field', 'username');
        $testFilter->setParameter('value', 'Tim');

        $this->fc->enable('testFilter2');
        $testFilter2 = $this->fc->getFilter('testFilter2');
        $testFilter2->setParameter('class', User::class);
        $testFilter2->setParameter('field', 'username');
        $testFilter2->setParameter('value', 'John');

        /* These two filters will merge and create a query that requires the
         * username to equal both "Tim" and "John", which is impossible for a
         * non-array, string field. No results should be returned.
         */
        self::assertEmpty($this->getUsernamesWithFindAll());
    }

    public function testNullFilterOnStringField(): void
    {
        $this->fc->enable('testFilter');
        $testFilter = $this->fc->getFilter('testFilter');
        $testFilter->setParameter('class', User::class);
        $testFilter->setParameter('field', 'password');
        $testFilter->setParameter('value', null);

        $builder = $this->dm->createAggregationBuilder(User::class)
        ->match()
        ->field('username')
        ->text('John');

        self::assertCount(1, $builder->getAggregation()->execute());
    }
}
