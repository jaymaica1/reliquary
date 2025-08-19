<?php

namespace App\Controller;

use App\Command\MigrateImagesToS3Command;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use SensioLabs\AnsiConverter\Theme\Theme;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for running S3 migration command from the admin interface
 */
#[Route('/admin/s3-migration')]
#[IsGranted('ROLE_ADMIN')]
class AdminS3MigrationController extends AbstractController
{
    private MigrateImagesToS3Command $migrateCommand;

    public function __construct(MigrateImagesToS3Command $migrateCommand)
    {
        $this->migrateCommand = $migrateCommand;
    }

    /**
     * Shows the S3 migration form and handles the migration process
     */
    #[Route('', name: 'app_admin_s3_migration')]
    public function migrate(Request $request): Response
    {
        $output = null;
        $success = null;
        $options = [
            'dry-run' => false,
            'force' => false,
            'delete-local' => false,
        ];

        if ($request->isMethod('POST')) {
            // Get options from the form
            $options['dry-run'] = $request->request->getBoolean('dry_run', false);
            $options['force'] = $request->request->getBoolean('force', false);
            $options['delete-local'] = $request->request->getBoolean('delete_local', false);

            // Set up the command input
            $input = new ArrayInput([
                '--dry-run' => $options['dry-run'],
                '--force' => $options['force'],
                '--delete-local' => $options['delete-local'],
            ]);

            // Capture the command output with maximum verbosity
            $outputBuffer = new BufferedOutput(OutputInterface::VERBOSITY_DEBUG, true);

            // Run the command
            $returnCode = $this->migrateCommand->run($input, $outputBuffer);
            $success = ($returnCode === 0);

            // Get the output content
            $output = $outputBuffer->fetch();
            $converter = new AnsiToHtmlConverter(new class extends Theme {
                public function asArray(): array { return [...parent::asArray(), 'black' => '#2b2b2b']; }
            });
            $output = $converter->convert($output);

            // Add a flash message
            if ($success) {
                if ($options['dry-run']) {
                    $this->addFlash('info', 'S3 migration dry run completed successfully!');
                } else {
                    $this->addFlash('success', 'Images migrated to S3 successfully!');
                }
            } else {
                $this->addFlash('danger', 'Error during S3 migration. Check the output for details.');
            }
        }

        return $this->render('admin/s3_migration/index.html.twig', [
            'output' => $output,
            'success' => $success,
            'options' => $options,
        ]);
    }
}