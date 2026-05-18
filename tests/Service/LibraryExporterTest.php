<?php

namespace App\Tests\Service;

use App\Entity\Book;
use App\Entity\Quote;
use App\Entity\Shelf;
use App\Entity\User;
use App\Enum\ReadingStatus;
use App\Service\LibraryExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LibraryExporterTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LibraryExporter $exporter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->exporter = self::getContainer()->get(LibraryExporter::class);
    }

    public function testRootStructure(): void
    {
        $user = $this->createUser('exporter@example.test');

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'exporter@example.test']);

        $result = $this->exporter->exportForUser($user);

        self::assertArrayHasKey('exportedAt', $result);
        self::assertArrayHasKey('user', $result);
        self::assertArrayHasKey('books', $result);
        self::assertArrayHasKey('shelves', $result);
        self::assertSame('exporter@example.test', $result['user']['email']);
        self::assertNotEmpty($result['exportedAt']);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $result['exportedAt']));
    }

    public function testExportsBooksWithQuotesAndShelf(): void
    {
        $user = $this->createUser('rich@example.test');

        $shelf = (new Shelf())->setOwner($user)->setName('Favoris');
        $this->em->persist($shelf);

        $book = (new Book())
            ->setOwner($user)
            ->setGoogleVolumeId('vol-1')
            ->setTitle('L\'Étranger')
            ->setAuthors(['Albert Camus'])
            ->setCategories(['Roman'])
            ->setIsbn13('9782070360024')
            ->setPageCount(150)
            ->setReadingStatus(ReadingStatus::Finished)
            ->setRating(5)
            ->setPersonalNotes('À relire')
            ->setShelf($shelf);
        $this->em->persist($book);

        $quote = (new Quote())->setBook($book)->setText('Aujourd\'hui, maman est morte.')->setPage(1);
        $this->em->persist($quote);

        $this->em->flush();

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'rich@example.test']);

        $result = $this->exporter->exportForUser($user);

        self::assertCount(1, $result['books']);
        $exportedBook = $result['books'][0];
        self::assertSame('L\'Étranger', $exportedBook['title']);
        self::assertSame(['Albert Camus'], $exportedBook['authors']);
        self::assertSame('finished', $exportedBook['readingStatus']);
        self::assertSame(5, $exportedBook['rating']);
        self::assertSame('À relire', $exportedBook['personalNotes']);
        self::assertSame('Favoris', $exportedBook['shelf']);
        self::assertCount(1, $exportedBook['quotes']);
        self::assertSame('Aujourd\'hui, maman est morte.', $exportedBook['quotes'][0]['text']);
        self::assertSame(1, $exportedBook['quotes'][0]['page']);

        self::assertCount(1, $result['shelves']);
        self::assertSame('Favoris', $result['shelves'][0]['name']);
    }

    public function testIsolationBetweenUsers(): void
    {
        $alice = $this->createUser('alice@example.test');
        $bob = $this->createUser('bob@example.test');

        $aliceBook = (new Book())->setOwner($alice)->setGoogleVolumeId('a1')->setTitle('Alice Book');
        $bobBook = (new Book())->setOwner($bob)->setGoogleVolumeId('b1')->setTitle('Bob Book');
        $this->em->persist($aliceBook);
        $this->em->persist($bobBook);
        $this->em->flush();

        $this->em->clear();
        $alice = $this->em->getRepository(User::class)->findOneBy(['email' => 'alice@example.test']);

        $aliceExport = $this->exporter->exportForUser($alice);
        self::assertCount(1, $aliceExport['books']);
        self::assertSame('Alice Book', $aliceExport['books'][0]['title']);
    }

    private function createUser(string $email): User
    {
        $u = (new User())->setEmail($email)->setDisplayName($email)->setPassword('Secret123');
        $this->em->persist($u);
        $this->em->flush();
        return $u;
    }
}
