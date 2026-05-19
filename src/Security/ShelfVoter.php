<?php

namespace App\Security;

use App\Entity\Shelf;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Shelf>
 */
final class ShelfVoter extends Voter
{
    public const OWN = 'SHELF_OWN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::OWN && $subject instanceof Shelf;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject instanceof Shelf && $subject->getOwner() === $user;
    }
}
