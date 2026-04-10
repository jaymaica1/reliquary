<?php

namespace App\Controller;

use App\Entity\Relic;
use App\Entity\Saint;
use App\Enum\RelicDegree;
use App\Enum\RelicStatus;
use App\Form\RelicType;
use App\Repository\RelicRepository;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/relic')]
final class RelicController extends AbstractController
{
    #[Route(name: 'app_relic_index', methods: ['GET'])]
    public function index(Request $request, RelicRepository $relicRepository, PaginatorInterface $paginator): Response
    {
        $filter = $request->query->get('filter');
        $query = $request->query->get('q');
        $location = $request->query->get('location');
        $distance = $request->query->get('distance', 50); // Default 50km
        $user = $this->getUser();

        $locationData = null;
        if ($location) {
            // Here we would ideally geocode the location string to lat/lng
            // For now, let's check if the user has a stored geolocation or if it's in the session
            // BUT the requirement says "make location a separated field", and "include a slider for distance".
            // It also says "There is code available for finding geolocation in the system already".
            
            // If the user entered a location, we should probably try to geocode it.
            // Let's see if we can use OpenStreetMapService.
        }

        // Check for coordinates in the request (might be sent by JS)
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        if ($lat && $lng) {
            $locationData = [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'radius' => (float) $distance
            ];
        }

        $queryBuilder = $relicRepository->findAllQuery($filter, $user, $query, $locationData);
        $relicsForMap = [];
        if ($locationData) {
            $allRelics = $queryBuilder->getResult();
            foreach ($allRelics as $relic) {
                if ($relic->getLatitude() && $relic->getLongitude()) {
                    $relicsForMap[] = [
                        'id' => $relic->getId(),
                        'latitude' => $relic->getLatitude(),
                        'longitude' => $relic->getLongitude(),
                        'saint' => $relic->getSaint() ? (string) $relic->getSaint() : 'Unknown Saint',
                        'address' => $relic->getAddress(),
                        'location' => $relic->getLocation(),
                    ];
                }
            }
        }

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
        );

        return $this->render('relic/index.html.twig', [
            'pagination' => $pagination,
            'filter' => $filter,
            'query' => $query,
            'location' => $location,
            'distance' => $distance,
            'relic_degrees' => RelicDegree::cases(),
            'relics_for_map' => $relicsForMap,
            'location_data' => $locationData,
        ]);
    }

    #[Route('/my-relics', name: 'app_my_relics', methods: ['GET'])]
    public function myRelics(Request $request, RelicRepository $relicRepository, PaginatorInterface $paginator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $filter = $request->query->get('filter');
        $query = $request->query->get('q');
        $location = $request->query->get('location');
        $distance = $request->query->get('distance', 50);
        
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        $locationData = null;
        if ($lat && $lng) {
            $locationData = [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'radius' => (float) $distance
            ];
        }

        $queryBuilder = $relicRepository->findByCreatorQuery($user, $filter, $query, $locationData);
        $relicsForMap = [];
        if ($locationData) {
            $allRelics = $queryBuilder->getResult();
            foreach ($allRelics as $relic) {
                if ($relic->getLatitude() && $relic->getLongitude()) {
                    $relicsForMap[] = [
                        'id' => $relic->getId(),
                        'latitude' => $relic->getLatitude(),
                        'longitude' => $relic->getLongitude(),
                        'saint' => $relic->getSaint() ? (string) $relic->getSaint() : 'Unknown Saint',
                        'address' => $relic->getAddress(),
                        'location' => $relic->getLocation(),
                    ];
                }
            }
        }

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
        );

        return $this->render('relic/index.html.twig', [
            'pagination' => $pagination,
            'filter' => $filter,
            'query' => $query,
            'location' => $location,
            'distance' => $distance,
            'relic_degrees' => RelicDegree::cases(),
            'title' => 'My Relics',
            'relics_for_map' => $relicsForMap,
            'location_data' => $locationData,
        ]);
    }

    #[Route('/new', name: 'app_relic_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ImageService $imageService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $relic = new Relic();
        $form = $this->createForm(RelicType::class, $relic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $relic->setCreator($this->getUser());

            // All relics start with PENDING status regardless of user role
            $relic->setStatus(RelicStatus::PENDING);

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $image = $imageService->createRelicImage($imageFile, $relic, $this->getUser());
                $relic->addImage($image);
            }

            $entityManager->persist($relic);
            $entityManager->flush();

            $this->addFlash('success', 'common.relic.submit.success');

            return $this->redirectToRoute('app_relic_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('relic/new.html.twig', [
            'relic' => $relic,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_relic_show', methods: ['GET'])]
    public function show(Relic $relic): Response
    {
        // Check if the user has permission to view this relic
        $this->denyAccessUnlessGranted('view', $relic);
        
        return $this->render('relic/show.html.twig', [
            'relic' => $relic,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_relic_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Relic $relic, EntityManagerInterface $entityManager, ImageService $imageService): Response
    {
        $this->denyAccessUnlessGranted('edit', $relic);

        $form = $this->createForm(RelicType::class, $relic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image removal
            $imagesToRemove = $request->request->all('remove_images');
            if (!empty($imagesToRemove)) {
                foreach ($imagesToRemove as $imageId) {
                    $image = $entityManager->getRepository(\App\Entity\RelicImage::class)->find($imageId);
                    if ($image && $image->getRelic() === $relic) {
                        $imageService->deleteImage($image);
                        $relic->removeImage($image);
                        $entityManager->remove($image);
                    }
                }
            }

            // Handle new image upload
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $image = $imageService->createRelicImage($imageFile, $relic, $this->getUser());
                $relic->addImage($image);
            }

            $entityManager->flush();

            $this->addFlash('success', 'common.relic.update.success');
            return $this->redirectToRoute('app_relic_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('relic/edit.html.twig', [
            'relic' => $relic,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_relic_delete', methods: ['POST'])]
    public function delete(Request $request, Relic $relic, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('delete', $relic);

        if ($this->isCsrfTokenValid('delete'.$relic->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($relic);
            $entityManager->flush();
            $this->addFlash('success', 'common.relic.delete.success');
        }

        return $this->redirectToRoute('app_relic_index', [], Response::HTTP_SEE_OTHER);
    }

}
