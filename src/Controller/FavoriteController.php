<?php

namespace App\Controller;

use App\Entity\Pattern;
use App\Entity\PatternFavorite;
use App\Repository\PatternFavoriteRepository;
use App\Repository\PatternRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class FavoriteController extends AbstractController
{
    #[Route('/api/favorite/pattern/{patternId}', name: 'app_favorite_toggle', methods: ['POST'], requirements: ['patternId' => '\d+'])]
    public function toggleFavorite(int $patternId, Request $request, EntityManagerInterface $entityManager, PatternRepository $patternRepository, PatternFavoriteRepository $favoriteRepository): JsonResponse
    {
        // Check if user is authenticated
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        // Find pattern by ID
        $pattern = $patternRepository->find($patternId);
        if (!$pattern) {
            return $this->json(['error' => 'Pattern not found'], 404);
        }

        // Check if favorite already exists
        $existingFavorite = $favoriteRepository->findOneBy([
            'pattern' => $pattern,
            'account' => $user
        ]);

        if ($existingFavorite) {
            // Remove favorite
            $entityManager->remove($existingFavorite);
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'favorited' => false,
                'message' => 'Pattern removed from favorites'
            ]);
        } else {
            // Add favorite
            $favorite = new PatternFavorite();
            $favorite->setPattern($pattern);
            $favorite->setAccount($user);
            
            $entityManager->persist($favorite);
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'favorited' => true,
                'message' => 'Pattern added to favorites'
            ]);
        }
    }

    #[Route('/api/favorite/pattern/{patternId}/status', name: 'app_favorite_status', methods: ['GET'], requirements: ['patternId' => '\d+'])]
    public function getFavoriteStatus(int $patternId, PatternRepository $patternRepository, PatternFavoriteRepository $favoriteRepository): JsonResponse
    {
        // Check if user is authenticated
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['favorited' => false]);
        }

        // Find pattern by ID
        $pattern = $patternRepository->find($patternId);
        
        if (!$pattern) {
            return $this->json(['favorited' => false]);
        }

        // Check if favorite exists
        $favorite = $favoriteRepository->findOneBy([
            'pattern' => $pattern,
            'account' => $user
        ]);

        return $this->json([
            'favorited' => $favorite !== null
        ]);
    }


    #[Route('/api/favorites', name: 'app_favorites_list', methods: ['GET'])]
    public function getFavorites(PatternFavoriteRepository $favoriteRepository): JsonResponse
    {
        // Check if user is authenticated
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        // Get user's favorites
        $favorites = $favoriteRepository->findBy(['account' => $user], ['id' => 'DESC']);
        
        $favoriteData = [];
        foreach ($favorites as $favorite) {
            $pattern = $favorite->getPattern();
            $favoriteData[] = [
                'id' => $favorite->getId(),
                'pattern' => [
                    'id' => $pattern->getId(),
                    'title' => $pattern->getTitle(),
                    'shafts' => $pattern->getShafts(),
                    'treadles' => $pattern->getTreadles(),
                ]
            ];
        }

        return $this->json([
            'favorites' => $favoriteData
        ]);
    }
}
