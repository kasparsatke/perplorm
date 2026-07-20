<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Tests\Bookstore\AuthorQuery;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\BookstoreEmployeeQuery;
use Propel\Tests\Bookstore\Map\AuthorTableMap;
use Propel\Tests\Bookstore\Map\BookTableMap;
use Propel\Tests\Bookstore\Map\ReviewTableMap;
use Propel\Tests\Bookstore\RecordLabelQuery;
use Propel\Tests\Helpers\Bookstore\BookstoreDataPopulator;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;

/**
 * @group database
 */
class ModelCriteriaUseGroupByQueryTest extends BookstoreTestBase
{
    /**
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        BookstoreDataPopulator::depopulate();
        BookstoreDataPopulator::populate();
    }

    public function setUp(): void
    {
        parent::setUp();
        AuthorTableMap::clearInstancePool();
        BookTableMap::clearInstancePool();
        ReviewTableMap::clearInstancePool();
    }

    protected string $bookColumns = 'book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id';

    /**
     * @return void
     */
    public function testBasicSql()
    {
        $query = BookQuery::create()
        ->useGroupByReviewQuery()
            ->addAsColumn('totalReviews', 'COUNT(*)')
        ->endUse();

        $expectedSql = <<<SQL
        SELECT $this->bookColumns, subquery_1.totalReviews AS totalReviews 
        FROM book
        LEFT JOIN (
            SELECT review.book_id, COUNT(*) AS totalReviews 
            FROM review
            GROUP BY review.book_id
        ) AS subquery_1 ON (book.id=subquery_1.book_id)
SQL;
        $this->assertQueryAndCount($expectedSql, [2, null, null, null], $query, 'totalReviews');
    }

    /**
     * @return void
     */
    public function testSqlWithAlias()
    {
        $query = BookQuery::create()
        ->useGroupByReviewQuery('r')
            ->addAsColumn('totalReviews', 'COUNT(*)')
        ->endUse();

        $expectedSql = <<<SQL
        SELECT $this->bookColumns, r.totalReviews AS totalReviews 
        FROM book
        LEFT JOIN (
            SELECT r.book_id, COUNT(*) AS totalReviews 
            FROM review r 
            GROUP BY r.book_id
        ) AS r ON (book.id=r.book_id)
SQL;

        $this->assertQueryAndCount($expectedSql, [2, null, null, null], $query, 'totalReviews');
    }

    /**
     * @return void
     */
    public function testNestedGroupBy()
    {
        $query = AuthorQuery::create()
        ->useGroupByBookQuery('b')
            ->useGroupByReviewQuery('r')
                ->addAsColumn('reviewsCount', 'COUNT(*)')
            ->endUse()
            ->addAsColumn('totalReviews', 'SUM(r.reviewsCount)')
        ->endUse()
        ->orderBy('author.first_name');

        $expectedSql = <<<SQL
        SELECT author.id, author.first_name, author.last_name, author.email, author.age, b.totalReviews AS totalReviews 
        FROM author 
        LEFT JOIN (
            SELECT b.author_id, SUM(r.reviewsCount) AS totalReviews 
            FROM book b 
            LEFT JOIN (
                SELECT r.book_id, COUNT(*) AS reviewsCount 
                FROM review r 
                GROUP BY r.book_id
            ) AS r ON (b.id=r.book_id) 
            GROUP BY b.author_id
        ) AS b ON (author.id=b.author_id) 
        ORDER BY author.first_name ASC
SQL;
        $this->assertQueryAndCount($expectedSql, [null, null, 2, null], $query, 'totalReviews');

    }

    /**
     * @return void
     */
    public function testFilterThroughInnerJoin()
    {
        $query = BookQuery::create()
        ->useGroupByReviewQuery('r', Criteria::INNER_JOIN)
            ->addAsColumn('totalReviews', 'COUNT(*)')
        ->endUse();

        $expectedSql = <<<SQL
        SELECT $this->bookColumns, r.totalReviews AS totalReviews 
        FROM book 
        INNER JOIN (
            SELECT r.book_id, COUNT(*) AS totalReviews
            FROM review r 
            GROUP BY r.book_id
        ) AS r ON (book.id=r.book_id)
SQL;
        $this->assertQueryAndCount($expectedSql, [2], $query, 'totalReviews');
    }

    /**
     * @return void
     */
    public function testMultiKeyRelation()
    {
        $query = RecordLabelQuery::create()
        ->useGroupByReleasePoolQuery('p')
            ->addAsColumn('totalReleases', 'COUNT(*)')
        ->endUse()
        ->orderBy('totalReleases');
        
        $expectedSql = <<<SQL
        SELECT record_label.id, record_label.abbr, record_label.name, p.totalReleases AS totalReleases 
        FROM record_label 
        LEFT JOIN (
            SELECT p.record_label_id, p.record_label_abbr, COUNT(*) AS totalReleases
            FROM release_pool p
            GROUP BY p.record_label_id,p.record_label_abbr
        ) AS p ON (record_label.id=p.record_label_id AND record_label.abbr=p.record_label_abbr) 
        ORDER BY totalReleases ASC
SQL;
        $this->assertQueryAndCount($expectedSql, [1,3], $query, 'totalReleases');
    }

    /**
     * @return void
     */
    public function testGroupByWithManyToMany()
    {
        $query = BookQuery::create()
        ->useGroupByBookListRelQuery('list', Criteria::INNER_JOIN)
            ->useBookClubListQuery('club')
                ->filterByTheme('Happiness')
            ->endUse()
            ->addAsColumn('listAppearances', 'COUNT(*)')
        ->endUse()
        ->orderBy('title');
        
        $expectedSql = <<<SQL
        SELECT $this->bookColumns, list.listAppearances AS listAppearances
        FROM book
        INNER JOIN (
            SELECT list.book_id, COUNT(*) AS listAppearances
            FROM book_x_list list 
            INNER JOIN book_club_list club ON (list.book_club_list_id=club.id) 
            WHERE club.theme=:p1 
            GROUP BY list.book_id
        ) AS list ON (book.id=list.book_id)
        ORDER BY book.title ASC
SQL;

        $this->assertQueryAndCount($expectedSql, [1,1], $query, 'listAppearances');
    }

    /**
     * @return void
     */
    public function testGroupBySelfJoin()
    {
        $query = BookstoreEmployeeQuery::create()
        ->useGroupBySubordinateQuery('s', Criteria::INNER_JOIN)
            ->addAsColumn('teamSize', 'COUNT(*)')
        ->endUse()
        ->orderBy('name');

        $expectedSql = <<<SQL
        SELECT bookstore_employee.id, bookstore_employee.class_key, 
            bookstore_employee.name, bookstore_employee.job_title,
            bookstore_employee.supervisor_id, bookstore_employee.salary,
            s.teamSize AS teamSize 
        FROM bookstore_employee
        INNER JOIN (
            SELECT s.supervisor_id, COUNT(*) AS teamSize 
            FROM bookstore_employee s 
            GROUP BY s.supervisor_id
        ) AS s ON (bookstore_employee.id=s.supervisor_id)
        ORDER BY bookstore_employee.name ASC
SQL;

        $this->assertQueryAndCount($expectedSql, [1], $query, 'teamSize');
    }

    /**
     * @param string $expectedSql
     * @param array $expectedCount
     * @param Criteria $query
     * @param string $countName
     * @return void
     */
    public function assertQueryAndCount(string $expectedSql, array $expectedCount, Criteria $query, string $countName)
    {
        $this->assertVendorSql($expectedSql, $query);

        $objects = $query->findObjects();
        $counts = $objects->getColumnValues($countName);
        $this->assertEquals($expectedCount, $counts);
    }
}
