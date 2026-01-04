<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class LogController extends AbstractController
{
    #[Route('/admin/logs', name: 'app_admin_logs')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $logDir = $this->getParameter('kernel.logs_dir');
        $logFiles = [];
        $selectedLog = null;
        $logContent = '';

        // Find all log files
        if (file_exists($logDir)) {
            $finder = new Finder();
            $finder->files()->in($logDir)->name('*.log')->sortByModifiedTime();
            
            foreach ($finder as $file) {
                $logFiles[] = [
                    'filename' => $file->getFilename(),
                    'path' => $file->getRealPath(),
                    'modified' => new \DateTime('@' . $file->getMTime()),
                    'size' => $file->getSize(),
                ];
            }
            
            // Get the most recent log file
            if (!empty($logFiles)) {
                $selectedLog = $logFiles[0];
                $logContent = $this->getLogContent($selectedLog['path']);
            }
        }

        return $this->render('log/index.html.twig', [
            'logFiles' => $logFiles,
            'selectedLog' => $selectedLog,
            'logContent' => $logContent,
        ]);
    }

    #[Route('/admin/logs/{filename}', name: 'app_admin_logs_view')]
    #[IsGranted('ROLE_ADMIN')]
    public function view(string $filename): Response
    {
        $logDir = $this->getParameter('kernel.logs_dir');
        $logPath = $logDir . '/' . $filename;
        $logFiles = [];
        $selectedLog = null;
        $logContent = '';

        // Security check - make sure the file is within the logs directory
        if (!file_exists($logPath) || !str_starts_with(realpath($logPath), realpath($logDir))) {
            throw $this->createNotFoundException('Log file not found');
        }

        // Find all log files
        if (file_exists($logDir)) {
            $finder = new Finder();
            $finder->files()->in($logDir)->name('*.log')->sortByModifiedTime();
            
            foreach ($finder as $file) {
                $logFile = [
                    'filename' => $file->getFilename(),
                    'path' => $file->getRealPath(),
                    'modified' => new \DateTime('@' . $file->getMTime()),
                    'size' => $file->getSize(),
                ];
                
                $logFiles[] = $logFile;
                
                if ($file->getFilename() === $filename) {
                    $selectedLog = $logFile;
                }
            }
            
            if ($selectedLog) {
                $logContent = $this->getLogContent($selectedLog['path']);
            }
        }

        return $this->render('log/index.html.twig', [
            'logFiles' => $logFiles,
            'selectedLog' => $selectedLog,
            'logContent' => $logContent,
        ]);
    }

    #[Route('/admin/logs/delete/{filename}', name: 'app_admin_logs_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $filename, CsrfTokenManagerInterface $csrfTokenManager, Request $request): Response
    {
        $token = $request->request->get('_token');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('delete' . $filename, $token))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $logDir = $this->getParameter('kernel.logs_dir');
        $logPath = $logDir . '/' . $filename;

        // Security check - make sure the file is within the logs directory
        if (!file_exists($logPath) || !str_starts_with(realpath($logPath), realpath($logDir))) {
            $this->addFlash('error', 'Log file not found.');
            return $this->redirectToRoute('app_admin_logs');
        }

        try {
            unlink($logPath);
            $this->addFlash('success', sprintf('Log file "%s" has been deleted.', $filename));
        } catch (\Exception $e) {
            $this->addFlash('error', sprintf('Could not delete log file: %s', $e->getMessage()));
        }

        return $this->redirectToRoute('app_admin_logs');
    }

    private function getLogContent(string $path): string
    {
        // For large files, we might want to only read the last N lines
        $maxLines = 1000;
        $content = '';
        
        if (file_exists($path)) {
            // For large files, use a more efficient approach
            if (filesize($path) > 5 * 1024 * 1024) { // 5MB
                $content = $this->getTailOfFile($path, $maxLines);
            } else {
                $content = file_get_contents($path);
            }
        }
        
        return $content;
    }

    private function getTailOfFile(string $path, int $lines): string
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        
        $offset = max(0, $lastLine - $lines);
        $file->seek($offset);
        
        $content = '';
        while (!$file->eof()) {
            $content .= $file->fgets();
        }
        
        return $content;
    }
}