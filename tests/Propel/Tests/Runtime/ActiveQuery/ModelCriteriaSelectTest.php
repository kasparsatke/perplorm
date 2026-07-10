<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime\ActiveQuery;

use PHPUnit\Framework\Attributes\DataProvider;
use Propel\Runtime\ActiveQuery\Exception\UnknownColumnException;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\QueryExecutor\QueryExecutionException;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Formatter\OnDemandFormatter;
use Propel\Tests\Bookstore\AuthorQuery;
use Propel\Tests\Bookstore\BookstoreEmployeeAccountQuery;
use Propel\Tests\Bookstore\BookQuery;
use Propel\Tests\Bookstore\Map\AuthorTableMap;
use Propel\Tests\Bookstore\Map\BookTableMap;
use Propel\Tests\Helpers\Bookstore\BookstoreDataPopulator;
use Propel\Tests\Helpers\Bookstore\BookstoreTestBase;

/**
 * Test class for ModelCriteria select() method.
 *
 * @author Francois Zaninotto
 *
 * @group database
 */
class ModelCriteriaSelectTest extends BookstoreTestBase
{
    /**
     * @return void
     */
    public function testSelectThrowsExceptionWhenCalledWithAnEmptyString()
    {
        $this->expectException(PropelException::class);

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select('');
    }

    /**
     * @return void
     */
    public function testSelectThrowsExceptionWhenCalledWithAnEmptyArray()
    {
        $this->expectException(PropelException::class);

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select([]);
    }

    /**
     * @return void
     */
    public function testSelectStringNoResult()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->where('Propel\Tests\Bookstore\Book.title = ?', 'kdjfhlkdsh');
        $c->select('Title');
        $titles = $c->find($this->con);

        $expectedSQL = $this->getSql('SELECT book.title AS "Title" FROM book WHERE book.title = \'kdjfhlkdsh\'');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'find() called after select(string) selects a single column');
        $this->assertInstanceOf('Propel\Runtime\Collection\ArrayCollection', $titles, 'find() called after select(string) returns a PropelArrayCollection object');
        $this->assertTrue(is_array($titles->getData()), 'find() called after select(string) returns an empty ArrayCollection object');
        $this->assertEquals(0, count($titles), 'find() called after select(string) returns an empty array if no record is found');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->where('Propel\Tests\Bookstore\Book.title = ?', 'kdjfhlkdsh');
        $c->select('Title');
        $title = $c->findOne();
        $this->assertTrue($title === null, 'findOne() called after select(string) returns null when no record is found');
    }

    /**
     * @return void
     */
    public function testSelectStringAcceptsColumnNames()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select('Title');
        $titles = $c->find();
        $expectedSQL = $this->getSql('SELECT book.title AS "Title" FROM book');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'select() accepts short column names');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select('Propel\Tests\Bookstore\Book.title');
        $titles = $c->find();
        $expectedSQL = $this->getSql('SELECT book.title AS "Propel\Tests\Bookstore\Book.title" FROM book');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'select() accepts complete column names');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book', 'b');
        $c->select('b.title');
        $titles = $c->find();
        $expectedSQL = $this->getSql('SELECT book.title AS "b.title" FROM book');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'select() accepts complete column names with table aliases');
    }

    /**
     * @return void
     */
    public function testSelectStringFind()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select('Title');
        $titles = $c->find($this->con);
        $this->assertEquals($titles->count(), 4, 'find() called after select(string) returns an array with one row for each record');
        $this->assertEquals($titles->shift(), 'Harry Potter and the Order of the Phoenix', 'find() called after select(string) returns an array of column values');
        $this->assertEquals($titles->shift(), 'Quicksilver', 'find() called after select(string) returns an array of column values');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Author');
        $c->where('Propel\Tests\Bookstore\Author.FirstName = ?', 'Neal');
        $c->select('FirstName');
        $authors = $c->find($this->con);
        $this->assertEquals($authors->count(), 1, 'find() called after select(string) allows for where() statements');
        $expectedSQL = $this->getSql("SELECT author.first_name AS \"FirstName\" FROM author WHERE author.first_name = 'Neal'");
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'find() called after select(string) allows for where() statements');
    }

    /**
     * @return void
     */
    public function testSelectStringFindOne()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select('Title');
        $title = $c->findOne($this->con);
        $expectedSQL = $this->getSql('SELECT book.title AS "Title" FROM book LIMIT 1');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'findOne() called after select(string) selects a single column and requests a single row');
        $this->assertTrue(is_string($title), 'findOne() called after select(string) returns a string');
        $this->assertEquals($title, 'Harry Potter and the Order of the Phoenix', 'findOne() called after select(string) returns the column value of the first row matching the query');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Author');
        $c->where('Propel\Tests\Bookstore\Author.FirstName = ?', 'Neal');
        $c->select('FirstName');
        $author = $c->findOne($this->con);
        $this->assertNotInstanceOf(Collection::class, $author, 'findOne() called after select(string) allows for where() statements');
        $expectedSQL = $this->getSql("SELECT author.first_name AS \"FirstName\" FROM author WHERE author.first_name = 'Neal' LIMIT 1");
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'findOne() called after select(string) allows for where() statements');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Author');
        $c->select(AuthorTableMap::COL_FIRST_NAME);
        $author = $c->find($this->con);
        $expectedSQL = $this->getSql('SELECT author.first_name FROM author');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'select(string) accepts model TableMap Constants');
    }

    public static function UnknownColumnDataProvider(): array
    {
        return [
            ['author.NOT_EXISTING_COLUMN', UnknownColumnException::class, 'prefixed column should throw error if it cannot be resolved'],
            ['NOT_EXISTING_COLUMN', QueryExecutionException::class, 'unknown column without prefix fails on DB side'],
        ];
    }

    /**
     * @return void
     */
    #[DataProvider('UnknownColumnDataProvider')]
    public function testUnknownColumnException(string $column, string $exceptionClass, string $message)
    {
        $c = AuthorQuery::create()->select($column);

        $this->expectException($exceptionClass);
        $c->find($this->con);
    }


    /**
     * @return void
     */
    public function testSelectStringJoin()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->where('Author.FirstName = ?', 'Neal');
        $c->select('Title');
        $titles = $c->find($this->con);
        $this->assertEquals($titles->count(), 1, 'find() called after select(string) allows for join() statements');
        $expectedSQL = $this->getSql("SELECT book.title AS \"Title\" FROM book INNER JOIN author ON (book.author_id=author.id) WHERE author.first_name = 'Neal'");
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'find() called after select(string) allows for join() statements');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->where('Author.FirstName = ?', 'Neal');
        $c->select('Author.FirstName');
        $titles = $c->find($this->con);
        $this->assertEquals($titles->shift(), 'Neal', 'find() called after select(string) will return values from the joined table using complete column names');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->where('Author.FirstName = ?', 'Neal');
        $c->select('Title');
        $title = $c->findOne($this->con);
        $this->assertNotInstanceOf(Collection::class, $title, 'findOne() called after select(string) allows for join() statements');
        $expectedSQL = $this->getSql("SELECT book.title AS \"Title\" FROM book INNER JOIN author ON (book.author_id=author.id) WHERE author.first_name = 'Neal' LIMIT 1");
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'findOne() called after select(string) allows for where() statements');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->where('Author.FirstName = ?', 'Neal');
        $c->select('Author.FirstName');
        $title = $c->findOne($this->con);
        $this->assertEquals($title, 'Neal', 'findOne() called after select(string) will return values from the joined table using complete column names');
    }

    /**
     * @return void
     */
    public function testSelectStringWildcard()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select('*');
        $book = $c->findOne($this->con);
        $expectedSQL = $this->getSql('SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id FROM book LIMIT 1');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'select(\'*\') selects all the columns from the main object');
        $this->assertIsArray($book, 'findOne() called after select(\'*\') returns an array');
        $expectedColumns = ['book.id', 'book.title', 'book.isbn', 'book.price', 'book.publisher_id', 'book.author_id'];
        $this->assertEquals($expectedColumns, array_keys($book), 'select(\'*\') returns all the columns from the main object, in complete form');
    }

    /**
     * @return void
     */
    public function testLongNameInAs()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        // fix for a bug/limitation in pdo_dblib where it truncates columnnames to a maximum of 31 characters when doing PDO::FETCH_ASSOC
        $authenticatorLongName = 'Propel\Tests\Bookstore\BookstoreEmployeeAccount.Authenticator';
        $passwordLongName = 'Propel\Tests\Bookstore\BookstoreEmployeeAccount.Password';

        $c = BookstoreEmployeeAccountQuery::create()
            ->select([$authenticatorLongName, $passwordLongName]);
        $account = $c->findOne($this->con);
        
        $expected = [$authenticatorLongName => 'Password', $passwordLongName => 'johnp4ss'];
        $this->assertEquals($expected, $account, 'select() does not mind long column names');
    }

    /**
     * @return void
     */
    public function testSelectArray()
    {
        $authors = AuthorQuery::create()
            ->where('Propel\Tests\Bookstore\Author.FirstName = ?', 'Neal')
            ->select(['FirstName', 'LastName'])
            ->find($this->con);

        $this->assertEquals($authors->count(), 1, 'find() called after select(array) allows for where() statements');

        $expectedSQL = $this->getSql("SELECT author.first_name AS \"FirstName\", author.last_name AS \"LastName\" FROM author WHERE author.first_name = 'Neal'");
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'find() called after select(array) allows for where() statements');
    }

    /**
     * @return void
     */
    public function testSelectArrayFindOne()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $c = AuthorQuery::create()
            ->where('Propel\Tests\Bookstore\Author.FirstName = ?', 'Neal')
            ->select(['FirstName', 'LastName']);
        $author = $c->findOne($this->con);

        $this->assertEquals(count($author), 2, 'findOne() called after select(array) allows for where() statements');
        $expectedSQL = $this->getSql("SELECT author.first_name AS \"FirstName\", author.last_name AS \"LastName\" FROM author WHERE author.first_name = 'Neal' LIMIT 1");
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'findOne() called after select(array) allows for where() statements');
    }

    /**
     * @return void
     */
    public function testSelectArrayJoin()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->where('Author.FirstName = ?', 'Neal');
        $c->select(['Title', 'ISBN']);
        $titles = $c->find($this->con);
        $this->assertEquals($titles->count(), 1, 'find() called after select(array) allows for join() statements');
        $expectedSQL = $this->getSql("SELECT book.title AS \"Title\", book.isbn AS \"ISBN\" FROM book INNER JOIN author ON (book.author_id=author.id) WHERE author.first_name = 'Neal'");
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'find() called after select(array) allows for join() statements');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->where('Author.FirstName = ?', 'Neal');
        $c->select(['Author.FirstName', 'Author.LastName']);
        $titles = $c->find($this->con);
        $this->assertEquals(array_values($titles->shift()), ['Neal', 'Stephenson'], 'find() called after select(array) will return values from the joined table using complete column names');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->where('Author.FirstName = ?', 'Neal');
        $c->select(['Title', 'ISBN']);
        $title = $c->findOne($this->con);
        $this->assertEquals(count($title), 2, 'findOne() called after select(array) allows for join() statements');
        $expectedSQL = $this->getSql("SELECT book.title AS \"Title\", book.isbn AS \"ISBN\" FROM book INNER JOIN author ON (book.author_id=author.id) WHERE author.first_name = 'Neal' LIMIT 1");
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'findOne() called after select(array) allows for join() statements');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->where('Author.FirstName = ?', 'Neal');
        $c->select(['Author.FirstName', 'Author.LastName']);
        $title = $c->findOne($this->con);
        $this->assertEquals(array_values($title), ['Neal', 'Stephenson'], 'findOne() called after select(array) will return values from the joined table using complete column names');
    }

    /**
     * @return void
     */
    public function testSelectArrayRelation()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->orderBy('Propel\Tests\Bookstore\Book.title');
        $c->select(['Propel\Tests\Bookstore\Author.LastName', 'Propel\Tests\Bookstore\Book.title']);
        $rows = $c->find($this->con);
        $expectedSQL = $this->getSql('SELECT author.last_name AS "Propel\Tests\Bookstore\Author.LastName", book.title AS "Propel\Tests\Bookstore\Book.title" FROM book INNER JOIN author ON (book.author_id=author.id) ORDER BY book.title ASC');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'select(array) can select columns from several tables (many-to-one)');

        $expectedRows = [
            [
                'Propel\Tests\Bookstore\Author.LastName' => 'Byron',
                'Propel\Tests\Bookstore\Book.title' => 'Don Juan',
            ],
            [
                'Propel\Tests\Bookstore\Author.LastName' => 'Rowling',
                'Propel\Tests\Bookstore\Book.title' => 'Harry Potter and the Order of the Phoenix',
            ],
            [
                'Propel\Tests\Bookstore\Author.LastName' => 'Stephenson',
                'Propel\Tests\Bookstore\Book.title' => 'Quicksilver',
            ],
            [
                'Propel\Tests\Bookstore\Author.LastName' => 'Grass',
                'Propel\Tests\Bookstore\Book.title' => 'The Tin Drum',
            ],
        ];
        $this->assertEquals(serialize($rows->getData()), serialize($expectedRows), 'find() called after select(array) returns columns from several tables (many-to-one');

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->select(['Author.LastName', 'Book.title']);
        $c->orderBy('Book.id');
        $c->orderBy('Author.id');
        $rows = $c->find($this->con);
        $expectedSQL = $this->getSql('SELECT author.last_name AS "Author.LastName", book.title AS "Book.title" FROM book INNER JOIN author ON (book.author_id=author.id) ORDER BY book.id ASC,author.id ASC');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'select(array) can select columns from several tables (many-to-one)');

        $expectedRows = [
            [
                'Author.LastName' => 'Rowling',
                'Book.title' => 'Harry Potter and the Order of the Phoenix',
            ],
            [
                'Author.LastName' => 'Stephenson',
                'Book.title' => 'Quicksilver',
            ],
            [
                'Author.LastName' => 'Byron',
                'Book.title' => 'Don Juan',
            ],
            [
                'Author.LastName' => 'Grass',
                'Book.title' => 'The Tin Drum',
            ],
        ];
        $this->assertEquals(serialize($rows->getData()), serialize($expectedRows), 'find() called after select(array) returns columns from several tables (many-to-one');
    }

    /**
     * @return void
     */
    public function testSelectArrayWithColumn()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $c = BookQuery::create()
            ->join('Propel\Tests\Bookstore\Book.Author')
            ->withColumn('LOWER(Propel\Tests\Bookstore\Book.title)', 'LowercaseTitle')
            ->select(['LowercaseTitle', 'Propel\Tests\Bookstore\Book.title'])
            ->orderBy('Propel\Tests\Bookstore\Book.title');
        $rows = $c->find($this->con);

        $expectedSQL = $this->getSql('SELECT book.title AS "Propel\Tests\Bookstore\Book.title", LOWER(book.title) AS LowercaseTitle FROM book INNER JOIN author ON (book.author_id=author.id) ORDER BY book.title ASC');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'find() called after select(array) can cope with a column added with withColumn()');

        $expectedRows = [
            [
                'LowercaseTitle' => 'don juan',
                'Propel\Tests\Bookstore\Book.title' => 'Don Juan',
            ],
            [
                'LowercaseTitle' => 'harry potter and the order of the phoenix',
                'Propel\Tests\Bookstore\Book.title' => 'Harry Potter and the Order of the Phoenix',
            ],
            [
                'LowercaseTitle' => 'quicksilver',
                'Propel\Tests\Bookstore\Book.title' => 'Quicksilver',
            ],
            [
                'LowercaseTitle' => 'the tin drum',
                'Propel\Tests\Bookstore\Book.title' => 'The Tin Drum',
            ],
        ];
        $this->assertEqualsCanonicalizing($expectedRows, $rows->getData(), 'find() called after select(array) can cope with a column added with withColumn()');
    }

    /**
     * @return void
     */
    public function testSelectAllWithColumn()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->join('Propel\Tests\Bookstore\Book.Author');
        $c->useQuery('Author')
            ->withColumn('Propel\Tests\Bookstore\Author.LastName', 'authorLastName')
            ->endUse();
        $rows = $c->find($this->con);

        $expectedSQL = $this->getSql('SELECT book.id, book.title, book.isbn, book.price, book.publisher_id, book.author_id, author.last_name AS authorLastName FROM book INNER JOIN author ON (book.author_id=author.id)');
        $this->assertEquals($expectedSQL, $this->con->getLastExecutedQuery(), 'Rest of table after column added with withColumn() is not properly loaded');
    }

    /**
     * @return void
     */
    public function testSelectArrayPaginate()
    {
        BookstoreDataPopulator::depopulate($this->con);
        BookstoreDataPopulator::populate($this->con);

        $pager = BookQuery::create()
            ->select(['Id', 'Title', 'ISBN', 'Price'])
            ->paginate(1, 10, $this->con);

        $this->assertInstanceOf('Propel\Runtime\Util\PropelModelPager', $pager);
        foreach ($pager as $result) {
            $this->assertEquals(['Id', 'Title', 'ISBN', 'Price'], array_keys($result));
        }
    }

    /**
     * @return void
     */
    public function testGetSelectReturnsNullByDefault()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $this->assertNull($c->getSelect());
    }

    /**
     * @return void
     */
    public function testGetSelectReturnsArrayWhenSelectingASingleColumn()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select('Title');
        $this->assertEquals(['Title'], $c->getSelect());
    }

    /**
     * @return void
     */
    public function testGetSelectReturnsArrayWhenSelectingSeveralColumns()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select(['Id', 'Title']);
        $this->assertEquals(['Id', 'Title'], $c->getSelect());
    }

    /**
     * @return void
     */
    public function testGetSelectReturnsArrayWhenSelectingASingleColumnAsArray()
    {
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->select(['Title']);
        $this->assertEquals(['Title'], $c->getSelect());
    }

    /**
     * @return void
     */
    public function testGetSelectReturnsArrayWhenSelectingAllColumns()
    {
        $c = BookQuery::create()->select('*');

        $expectedColumns = BookTableMap::buildLocalTableColumnExpressions($c);
        $this->assertEquals($expectedColumns, $c->getSelect());
    }

    /**
     * @return void
     */
    public function testFormatterWithSelect()
    {
        if ($this->runningOnSQLite()) {
            $this->markTestIncomplete('Can cause `General error: 5 database is locked` when DataFetcherInterface in formatter is not properly closed');
        }
        $c = new ModelCriteria('bookstore', 'Propel\Tests\Bookstore\Book');
        $c->keepQuery(false); // just for this test's purpose
        $c->setFormatter(ModelCriteria::FORMAT_ON_DEMAND);
        $c->select(['Id', 'Title']);
        $rows = $c->find($this->con);

        $this->assertInstanceOf(OnDemandFormatter::class, $c->getFormatter(), 'The formatter is preserved');
    }
}
