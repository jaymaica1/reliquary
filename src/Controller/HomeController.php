<?php

namespace App\Controller;

use App\Repository\RelicRepository;
use App\Repository\SaintRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
    #[Route('/', name: 'app_home')]
    public function landing(SaintRepository $saintRepository, RelicRepository $relicRepository): Response
    {
        $featuredSaints = $saintRepository->findFeatured();

        $today = new \DateTime();
        $saintsOfDay = $saintRepository->findByFeastDate($today);
        $saintOfDay = !empty($saintsOfDay) ? $saintsOfDay[0] : null;
        $otherSaints = !empty($saintsOfDay) && count($saintsOfDay) > 1 ? array_slice($saintsOfDay, 1) : [];

        $landingShowcaseRelics = $relicRepository->findApprovedWithImagesForShowcase(6);

        return $this->render('home/landing.html.twig', [
            'featuredSaints' => $featuredSaints,
            'saintOfDay' => $saintOfDay,
            'otherSaints' => $otherSaints,
            'landingShowcaseRelics' => $landingShowcaseRelics,
        ]);
    }

}
