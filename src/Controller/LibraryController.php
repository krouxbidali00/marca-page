<?php

namespace App\Controller;

use App\Dto\LibraryFilter;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use App\Enum\ReadingStatus;
use App\Repository\BookRepository;
use App\Repository\ShelfRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class LibraryController extends AbstractController
{
    /** Common categories offered in the filter sidebar (in addition to any present in the active filter). */
    private const SUGGESTED_CATEGORIES = ['Roman', 'Essai', 'Poésie', 'Bande dessinée', 'Théâtre', 'Sciences humaines', 'Histoire', 'Jeunesse'];

    #[Route('/library', name: 'app_library')]
    public function index(Request $request, BookRepository $books, ShelfRepository $shelves): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $filter = LibraryFilter::fromRequest($request);
        $page = $books->paginateForLibrary($user, $filter);

        $categoryOptions = self::SUGGESTED_CATEGORIES;
        foreach ($filter->categories as $cat) {
            if (!\in_array($cat, $categoryOptions, true)) {
                $categoryOptions[] = $cat;
            }
        }

        return $this->render('library/index.html.twig', [
            'page' => $page,
            'filter' => $filter,
            'shelves' => $shelves->findBy(['owner' => $user], ['name' => 'ASC']),
            'totalInLibrary' => $books->count(['owner' => $user]),
            'categoryOptions' => $categoryOptions,
            'readingStatuses' => ReadingStatus::cases(),
            'purchaseStatuses' => PurchaseStatus::cases(),
        ]);
    }
}
