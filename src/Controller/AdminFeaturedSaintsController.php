<?php

namespace App\Controller;

use App\Entity\Saint;
use App\Repository\SaintRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for managing featured saints in the admin area
 */
#[Route('/admin/saints')]
#[IsGranted('ROLE_ADMIN')]
class AdminFeaturedSaintsController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Lists all saints with their featured status
     */
    #[Route('/featured', name: 'app_admin_saints_featured')]
    public function featuredSaints(
        Request $request,
        SaintRepository $saintRepository,
        PaginatorInterface $paginator
    ): Response {
        $query = $saintRepository->createQueryBuilder('s')
            ->orderBy('s.featured', 'DESC')
            ->getQuery();
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            200
        );
        
        return $this->render('admin/saints/featured.html.twig', [
            'pagination' => $pagination,
        ]);
    }
    
    /**
     * Toggle featured status for a saint
     */
    #[Route('/featured/{id}/toggle', name: 'app_admin_saints_toggle_featured', methods: ['POST'])]
    public function toggleFeatured(
        Saint $saint,
        EntityManagerInterface $entityManager
    ): Response {
        $saint->setFeatured(!$saint->isFeatured());
        $entityManager->flush();
        
        if ($saint->isFeatured()) {
            $this->addFlash('success', $this->translator->trans('admin.saints.featured.flash.saint_featured', ['%name%' => $saint->getName()], 'admin'));
        } else {
            $this->addFlash('success', $this->translator->trans('admin.saints.featured.flash.saint_unfeatured', ['%name%' => $saint->getName()], 'admin'));
        }
        
        return $this->redirectToRoute('app_admin_saints_featured');
    }
    
    /**
     * Bulk feature/unfeature saints
     */
    #[Route('/featured/bulk', name: 'app_admin_saints_bulk_featured', methods: ['POST'])]
    public function bulkFeatured(
        Request $request,
        EntityManagerInterface $entityManager,
        SaintRepository $saintRepository
    ): Response {
        $saintIds = $request->request->all('saint_ids');
        $action = $request->request->get('action');
        
        if (empty($saintIds) || !in_array($action, ['feature', 'unfeature'])) {
            $this->addFlash('error', $this->translator->trans('admin.saints.featured.flash.bulk_error', [], 'admin'));
            return $this->redirectToRoute('app_admin_saints_featured');
        }
        
        $saints = $saintRepository->findBy(['id' => $saintIds]);
        $featured = ($action === 'feature');
        $count = 0;
        
        foreach ($saints as $saint) {
            if ($saint->isFeatured() !== $featured) {
                $saint->setFeatured($featured);
                $count++;
            }
        }
        
        if ($count > 0) {
            $entityManager->flush();
            if ($featured) {
                $this->addFlash('success', $this->translator->trans('admin.saints.featured.flash.bulk_featured', ['%count%' => $count], 'admin'));
            } else {
                $this->addFlash('success', $this->translator->trans('admin.saints.featured.flash.bulk_unfeatured', ['%count%' => $count], 'admin'));
            }
        } else {
            $this->addFlash('info', $this->translator->trans('admin.saints.featured.flash.no_changes', [], 'admin'));
        }
        
        return $this->redirectToRoute('app_admin_saints_featured');
    }
}