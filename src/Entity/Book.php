<?php

namespace App\Entity;

use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $googleVolumeId = null;

    #[ORM\Column(length: 500)]
    private string $title = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $subtitle = null;

    /** @var string[] */
    #[ORM\Column]
    private array $authors = [];

    /** Lowercased, space-joined authors — kept in sync for searching/sorting. */
    #[ORM\Column(length: 600)]
    private string $authorsText = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $publishedDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $pageCount = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** @var string[] */
    #[ORM\Column]
    private array $categories = [];

    /** Lowercased, space-joined categories — kept in sync for filtering. */
    #[ORM\Column(length: 600)]
    private string $categoriesText = '';

    #[ORM\Column(length: 13, nullable: true)]
    private ?string $isbn10 = null;

    #[ORM\Column(length: 17, nullable: true)]
    private ?string $isbn13 = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $thumbnailUrl = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $coverTheme = 0;

    #[ORM\Column(enumType: ReadingStatus::class)]
    private ReadingStatus $readingStatus = ReadingStatus::ToRead;

    #[ORM\Column(nullable: true)]
    private ?int $currentPage = null;

    #[ORM\Column(enumType: PurchaseStatus::class)]
    private PurchaseStatus $purchaseStatus = PurchaseStatus::ToBuy;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $purchasedAt = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $purchaseFormat = null;

    #[ORM\Column(nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $personalNotes = null;

    #[ORM\ManyToOne(inversedBy: 'books')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shelf $shelf = null;

    #[ORM\Column]
    private \DateTimeImmutable $addedAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Quote> */
    #[ORM\OneToMany(targetEntity: Quote::class, mappedBy: 'book', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $quotes;

    public function __construct()
    {
        $this->addedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->quotes = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getGoogleVolumeId(): ?string
    {
        return $this->googleVolumeId;
    }

    public function setGoogleVolumeId(?string $googleVolumeId): static
    {
        $this->googleVolumeId = $googleVolumeId;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /** @return string[] */
    public function getAuthors(): array
    {
        return $this->authors;
    }

    /** @param string[] $authors */
    public function setAuthors(array $authors): static
    {
        $this->authors = array_values($authors);
        $this->authorsText = mb_strtolower(implode(' ', $this->authors));

        return $this;
    }

    public function getAuthorsText(): string
    {
        return $this->authorsText;
    }

    public function getAuthorsLine(): string
    {
        return implode(', ', $this->authors) ?: 'Auteur inconnu';
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): static
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getPublishedDate(): ?string
    {
        return $this->publishedDate;
    }

    public function setPublishedDate(?string $publishedDate): static
    {
        $this->publishedDate = $publishedDate;

        return $this;
    }

    public function getPublishedYear(): ?string
    {
        return $this->publishedDate ? substr($this->publishedDate, 0, 4) : null;
    }

    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    public function setPageCount(?int $pageCount): static
    {
        $this->pageCount = $pageCount;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return string[] */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /** @param string[] $categories */
    public function setCategories(array $categories): static
    {
        $this->categories = array_values($categories);
        $this->categoriesText = mb_strtolower(implode(' ', $this->categories));

        return $this;
    }

    public function getCategoriesText(): string
    {
        return $this->categoriesText;
    }

    public function getPrimaryCategory(): ?string
    {
        return $this->categories[0] ?? null;
    }

    public function getIsbn10(): ?string
    {
        return $this->isbn10;
    }

    public function setIsbn10(?string $isbn10): static
    {
        $this->isbn10 = $isbn10;

        return $this;
    }

    public function getIsbn13(): ?string
    {
        return $this->isbn13;
    }

    public function setIsbn13(?string $isbn13): static
    {
        $this->isbn13 = $isbn13;

        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn13 ?? $this->isbn10;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(?string $thumbnailUrl): static
    {
        $this->thumbnailUrl = $thumbnailUrl;

        return $this;
    }

    public function hasThumbnail(): bool
    {
        return $this->thumbnailUrl !== null && $this->thumbnailUrl !== '';
    }

    public function getCoverTheme(): int
    {
        return $this->coverTheme;
    }

    public function setCoverTheme(int $coverTheme): static
    {
        $this->coverTheme = $coverTheme;

        return $this;
    }

    public function getReadingStatus(): ReadingStatus
    {
        return $this->readingStatus;
    }

    public function setReadingStatus(ReadingStatus $readingStatus): static
    {
        $this->readingStatus = $readingStatus;

        return $this;
    }

    public function getCurrentPage(): ?int
    {
        return $this->currentPage;
    }

    public function setCurrentPage(?int $currentPage): static
    {
        $this->currentPage = $currentPage;

        return $this;
    }

    public function getProgressPercent(): ?int
    {
        if (!$this->pageCount || !$this->currentPage) {
            return null;
        }

        return (int) min(100, round($this->currentPage / $this->pageCount * 100));
    }

    public function getPurchaseStatus(): PurchaseStatus
    {
        return $this->purchaseStatus;
    }

    public function setPurchaseStatus(PurchaseStatus $purchaseStatus): static
    {
        $this->purchaseStatus = $purchaseStatus;

        return $this;
    }

    public function getPurchasedAt(): ?\DateTimeImmutable
    {
        return $this->purchasedAt;
    }

    public function setPurchasedAt(?\DateTimeImmutable $purchasedAt): static
    {
        $this->purchasedAt = $purchasedAt;

        return $this;
    }

    public function getPurchaseFormat(): ?string
    {
        return $this->purchaseFormat;
    }

    public function setPurchaseFormat(?string $purchaseFormat): static
    {
        $this->purchaseFormat = $purchaseFormat;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating === null ? null : max(0, min(5, $rating));

        return $this;
    }

    public function getPersonalNotes(): ?string
    {
        return $this->personalNotes;
    }

    public function setPersonalNotes(?string $personalNotes): static
    {
        $this->personalNotes = $personalNotes;

        return $this;
    }

    public function getShelf(): ?Shelf
    {
        return $this->shelf;
    }

    public function setShelf(?Shelf $shelf): static
    {
        $this->shelf = $shelf;

        return $this;
    }

    public function getAddedAt(): \DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Quote> */
    public function getQuotes(): Collection
    {
        return $this->quotes;
    }

    public function addQuote(Quote $quote): static
    {
        if (!$this->quotes->contains($quote)) {
            $this->quotes->add($quote);
            $quote->setBook($this);
        }

        return $this;
    }

    public function removeQuote(Quote $quote): static
    {
        $this->quotes->removeElement($quote);

        return $this;
    }
}
