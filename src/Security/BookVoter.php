<?php

namespace App\Security;

use App\Entity\Book;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Book>
 */
final class BookVoter extends Voter
{
    public const OWN = 'BOOK_OWN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::OWN && $subject instanceof Book;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject instanceof Book && $subject->getOwner()?->getId() === $user->getId();
    }
}
