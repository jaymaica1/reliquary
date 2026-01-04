<?php

namespace App\Controller;

use App\Entity\Saint;
use App\Repository\SaintRepository;
use App\Service\AiImageService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for managing saints tools in the admin area (featured status, image generation, etc.)
 */
#[Route('/admin/saints')]
#[IsGranted('ROLE_ADMIN')]
class AdminSaintsToolsController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Lists all saints with tools (featured status and image generation)
     */
    #[Route('/tools', name: 'app_admin_saints_tools')]
    public function saintsTools(
        Request $request,
        SaintRepository $saintRepository,
        PaginatorInterface $paginator
    ): Response {
        $searchTerm = $request->query->get('q');
        
        $queryBuilder = $saintRepository->createQueryBuilder('s');
        
        if ($searchTerm) {
            $queryBuilder
                ->leftJoin('s.translations', 't')
                ->andWhere('LOWER(s.name) LIKE LOWER(:searchTerm) OR LOWER(t.name) LIKE LOWER(:searchTerm)')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }
            
        $query = $queryBuilder
            ->orderBy('s.featured', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery();
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            200
        );
        
        return $this->render('admin/saints/tools.html.twig', [
            'pagination' => $pagination,
            'searchTerm' => $searchTerm,
        ]);
    }
    
    /**
     * Generate image for a saint
     */
    #[Route('/tools/{id}/generate-image', name: 'app_admin_saints_generate_image', methods: ['POST'])]
    public function generateImage(
        Saint $saint,
        AiImageService $aiImageService,
        ImageService $imageService,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        $prompt = $request->request->get('prompt');
        if (!$prompt) {
            $prompt = sprintf(
                "A realistic and artistic oil painting portrait of Saint %s, %s. %s",
                $saint->getName(),
                $saint->getAbstract() ?? '',
                $saint->getBiography() ? 'Inspiration: ' . substr(strip_tags($saint->getBiography()), 0, 500) : ''
            );
        }

        $imageUrl = $aiImageService->generatePortrait($prompt);

        if (!$imageUrl) {
            $this->addFlash('error', $this->translator->trans('admin.saints.tools.flash.image_generation_failed', ['%name%' => $saint->getName()], 'admin'));
            return $this->redirectToRoute('app_admin_saints_tools');
        }

        try {
            $saintImage = $imageService->createSaintImageFromUrl($imageUrl, $saint, $this->getUser());
            $entityManager->persist($saintImage);
            $entityManager->flush();
            $this->addFlash('success', $this->translator->trans('admin.saints.tools.flash.image_generated', ['%name%' => $saint->getName()], 'admin'));
        } catch (\Exception $e) {
            $this->addFlash('error', $this->translator->trans('admin.saints.tools.flash.image_save_failed', ['%error%' => $e->getMessage()], 'admin'));
        }

        return $this->redirectToRoute('app_admin_saints_tools');
    }

    /**
     * Bulk generate images for saints
     */
    #[Route('/tools/bulk-generate-images', name: 'app_admin_saints_bulk_generate_images', methods: ['POST'])]
    public function bulkGenerateImages(
        Request $request,
        SaintRepository $saintRepository,
        AiImageService $aiImageService,
        ImageService $imageService,
        EntityManagerInterface $entityManager
    ): Response {
        $saintIds = $request->request->all('saint_ids');

        if (empty($saintIds)) {
            $this->addFlash('error', $this->translator->trans('admin.saints.tools.flash.bulk_error', [], 'admin'));
            return $this->redirectToRoute('app_admin_saints_tools');
        }

        $saints = $saintRepository->findBy(['id' => $saintIds]);
        $successCount = 0;
        $failCount = 0;

        foreach ($saints as $saint) {
            $prompt = sprintf(
                "A realistic and artistic oil painting portrait of Saint %s, %s. %s",
                $saint->getName(),
                $saint->getAbstract() ?? '',
                $saint->getBiography() ? 'Inspiration: ' . substr(strip_tags($saint->getBiography()), 0, 500) : ''
            );

            $imageUrl = $aiImageService->generatePortrait($prompt);

            if ($imageUrl) {
                try {
                    $saintImage = $imageService->createSaintImageFromUrl($imageUrl, $saint, $this->getUser());
                    $entityManager->persist($saintImage);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                }
            } else {
                $failCount++;
            }
        }

        if ($successCount > 0) {
            $entityManager->flush();
            $this->addFlash('success', $this->translator->trans('admin.saints.tools.flash.bulk_image_generated', ['%count%' => $successCount], 'admin'));
        }

        if ($failCount > 0) {
            $this->addFlash('error', $this->translator->trans('admin.saints.tools.flash.bulk_image_failed', ['%count%' => $failCount], 'admin'));
        }

        return $this->redirectToRoute('app_admin_saints_tools');
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
        
        return $this->redirectToRoute('app_admin_saints_tools');
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
            $this->addFlash('error', $this->translator->trans('admin.saints.tools.flash.bulk_error', [], 'admin'));
            return $this->redirectToRoute('app_admin_saints_tools');
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
            $this->addFlash('info', $this->translator->trans('admin.saints.tools.flash.no_changes', [], 'admin'));
        }
        
        return $this->redirectToRoute('app_admin_saints_tools');
    }
}