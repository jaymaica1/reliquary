<?php

namespace App\Controller;

use App\Repository\RelicRepository;
use App\Repository\SaintRepository;
use App\Service\LocationResolverService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Knp\Component\Pager\PaginatorInterface;

/**
 * Controller for the home page that displays relics
 * 
 * When a user has geolocation defined (either authenticated user or guest with session data),
 * the home page will filter relics to show only those within a 45km radius of the user's location.
 * If no geolocation is available, all relics will be displayed.
 */
final class HomeController extends AbstractController
{
    private const DEFAULT_RADIUS_KM = 45;
    #[Route('/', name: 'app_landing')]
    public function landing(SaintRepository $saintRepository): Response
    {
        $featuredSaints = $saintRepository->findFeatured();

        return $this->render('home/landing.html.twig', [
            'featuredSaints' => $featuredSaints,
        ]);
    }

    #[Route('/', name: 'app_home')]
    public function index(RelicRepository $relicRepository, SaintRepository $saintRepository, Request $request, Security $security, LocationResolverService $locationResolver): Response
    {
        // Check if there's a search query
        $searchQuery = $request->query->get('q');
        
        // Define radius here instead of in getFilteredRelics
        $radius = self::DEFAULT_RADIUS_KM;
        
        // Resolve location here
        $locationData = $locationResolver->resolveLocation($request, $security, $searchQuery);
        
        $result = $this->getFilteredRelics($relicRepository, $security->getUser(), $locationData, $radius);
        
        // Get saint of the day based on current date
        $today = new \DateTime();
        $saintsOfDay = $saintRepository->findByFeastDate($today);
        $saintOfDay = !empty($saintsOfDay) ? $saintsOfDay[0] : null;
        $otherSaints = !empty($saintsOfDay) && count($saintsOfDay) > 1 ? array_slice($saintsOfDay, 1) : [];

        return $this->render('home/index.html.twig', [
            'relics' => $result['relics'],
            'radius' => $radius,
            'locationAvailable' => $locationData['available'],
            'searchQuery' => $searchQuery,
            'userLocation' => $locationData['location'],
            'saintOfDay' => $saintOfDay,
            'otherSaints' => $otherSaints,
        ]);
    }

    #[Route('/home/desktop', name: 'app_home_relics_desktop', methods: ['GET'])]
    public function homeRelicsDesktop(
        RelicRepository $relicRepository, 
        Request $request, 
        Security $security, 
        PaginatorInterface $paginator,
        LocationResolverService $locationResolver
    ): Response
    {
        // Define radius here
        $radius = self::DEFAULT_RADIUS_KM;
        
        // Get search query if present
        $searchQuery = $request->query->get('q');
        
        // Resolve location here with search query
        $locationData = $locationResolver->resolveLocation($request, $security, $searchQuery);
        
        $result = $this->getFilteredRelics($relicRepository, $security->getUser(), $locationData, $radius);

        $pagination = $paginator->paginate(
            $result['relics'],
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('relic/_relic_list_desktop.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/home/mobile', name: 'app_home_relics_mobile', methods: ['GET'])]
    public function homeRelicsMobile(
        RelicRepository $relicRepository, 
        Request $request, 
        Security $security, 
        PaginatorInterface $paginator,
        LocationResolverService $locationResolver
    ): Response
    {
        // Define radius here
        $radius = self::DEFAULT_RADIUS_KM;
        
        // Get search query if present
        $searchQuery = $request->query->get('q');
        
        // Resolve location here with search query
        $locationData = $locationResolver->resolveLocation($request, $security, $searchQuery);
        
        $result = $this->getFilteredRelics($relicRepository, $security->getUser(), $locationData, $radius);

        $pagination = $paginator->paginate(
            $result['relics'],
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('relic/_relic_list_mobile.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    private function getFilteredRelics(
        RelicRepository $relicRepository, 
        ?object $user,
        array $locationData,
        float $radius
    ): array
    {
        $userLocation = $locationData['location'];

        // Filter relics by geolocation if available
        if ($userLocation) {
            $relics = $relicRepository->findWithinRadius(
                $userLocation['latitude'],
                $userLocation['longitude'],
                $radius,
                $user // Pass the current user for visibility restrictions
            );
        } else {
            // Fall back to all relics if no geolocation is available
            $relics = $relicRepository->findAllWithVisibility($user);
        }

        return [
            'relics' => $relics
        ];
    }
}
