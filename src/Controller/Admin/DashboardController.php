<?php

namespace App\Controller\Admin;

use App\Entity\Saint;
use App\Entity\Relic;
use App\Enum\RelicStatus;
use App\Repository\SaintRepository;
use App\Repository\RelicRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'app_admin_')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(SaintRepository $saintRepository, RelicRepository $relicRepository): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'incompleteSaintsCount' => $saintRepository->countIncomplete(),
            'pendingRelicsCount' => $relicRepository->countByStatus(RelicStatus::PENDING),
        ]);
    }
}
