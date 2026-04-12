<?php

namespace App\Command;

use App\Service\Ai\AiTextService;
use App\Service\ConfigurationService;
use App\Exception\Ai\AiResponseTruncatedException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:translate:saints-ai',
    description: 'Translate saint data using AI',
)]
class TranslateSaintsAiCommand extends Command
{
    public function __construct(
        private readonly AiTextService        $aiTextService,
        private readonly ConfigurationService $configurationService,
        private readonly string               $projectDir
    )
    {
        parent::__construct();
    }

    public function getYamlFiles(mixed $path, int $start, ?int $limit): array
    {
        if (!is_dir($path)) {
            return [realpath($path) ?: $path];
        }

        $files = glob(rtrim($path, '/') . '/*.yaml');
        $fileMap = [];

        foreach ($files as $file) {
            if (preg_match('/(\d+)\.yaml$/', $file, $matches)) {
                $fileMap[(int)$matches[1]] = $file;
            } else {
                // For files without numbers, use a high index or something to keep them separate
                // But typically these files are numbered.
                $fileMap[] = $file;
            }
        }

        ksort($fileMap);

        $filteredFiles = [];
        foreach ($fileMap as $num => $file) {
            if (is_int($num) && $num < $start) {
                continue;
            }
            $filteredFiles[] = $file;
        }

        if ($limit !== null) {
            $filteredFiles = array_slice($filteredFiles, 0, $limit);
        }

        return $filteredFiles;
    }

    /**
     * @param SymfonyStyle $io
     * @param array $files
     * @param int $start
     * @return void
     */
    public function translateSaintData(SymfonyStyle $io, array $files, int $start): void
    {
        $io->info(sprintf('Processing %d files starting from number %d', count($files), $start));

        foreach ($files as $file) {
            $io->section(sprintf('Processing file: %s', basename($file)));
            $this->processFile($file, $io);
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::REQUIRED, 'Path to a YAML file or directory containing YAML files')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force translation even if content already exists')
            ->addOption('start', null, InputOption::VALUE_REQUIRED, 'Starting file number to process', '1')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of files to process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('path');
        $start = (int)$input->getOption('start');
        $limit = $input->getOption('limit') !== null ? (int)$input->getOption('limit') : null;

        if (!str_starts_with($path, '/')) {
            $path = $this->projectDir . '/' . $path;
        }

        if (!file_exists($path)) {
            $io->error(sprintf('Path not found: %s', $path));
            return Command::FAILURE;
        }

        $files = $this->getYamlFiles($path, $start, $limit);

        if (empty($files)) {
            $io->warning('No YAML files found matching the criteria.');
            return Command::SUCCESS;
        }

        $this->translateSaintData($io, $files, $start);

        $io->success('Translation process completed.');

        return Command::SUCCESS;
    }

    private function processFile(string $file, SymfonyStyle $io): void
    {
        try {
            $yamlContent = file_get_contents($file);
            $data = Yaml::parse($yamlContent);
        } catch (\Exception $e) {
            $io->error(sprintf('Error reading or parsing YAML in %s: %s', $file, $e->getMessage()));
            return;
        }

        if (empty($data) || !isset($data['saints'])) {
            $io->info('No saints found in file. Skipping.');
            return;
        }

        try {
            $results = $this->callAiForBatch($yamlContent);
            
            // Validate that results is valid YAML and has saints
            $parsedResults = Yaml::parse($results);
            if (!isset($parsedResults['saints'])) {
                throw new \RuntimeException('AI response missing "saints" key');
            }

            file_put_contents($file, $results);
            $io->note(sprintf('Updated %d saints in %s', count($parsedResults['saints']), basename($file)));
        } catch (AiResponseTruncatedException $e) {
            $io->error(sprintf('AI response was truncated for %s: %s', basename($file), $e->getMessage()));
            $io->note('The file was not updated. Try reducing the batch size or increasing max_tokens.');
        } catch (\Exception $e) {
            $io->error(sprintf('Error in AI call for batch in %s: %s', basename($file), $e->getMessage()));
        }
    }

    private function callAiForBatch(string $yamlContent): string
    {
        $prompt = "Translate the following YAML into pt-BR. 
        Yaml structure must be kept, do not translate keys. 
        Respond with the YAML only, no markdown blocks.
        The name should also be translated if there is enough context.
" . $yamlContent;

        $response = $this->aiTextService->chat([
            ['role' => 'system', 'content' => 'You are a helpful assistant that returns ONLY raw YAML. Do not use markdown code blocks.'],
            ['role' => 'user', 'content' => $prompt]
        ], [
            'temperature' => 0.3
        ]);

        $response = preg_replace('/^```(?:yaml)?\n/i', '', $response);
        $response = preg_replace('/\n```$/', '', $response);
        $response = trim($response);

        try {
            $result = Yaml::parse($response);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to parse AI response as YAML: ' . $e->getMessage() . "\nResponse: " . $response);
        }

        if (!isset($result['saints']) || !is_array($result['saints'])) {
            throw new \RuntimeException('AI response is missing the "saints" key or it is not an array.' . "\nResponse: " . $response);
        }

        $inputData = Yaml::parse($yamlContent);
        $inputSaints = $inputData['saints'] ?? [];
        $outputSaints = $result['saints'];

        if (count($inputSaints) !== count($outputSaints)) {
            throw new \RuntimeException(sprintf(
                'AI response has different number of entries. Expected %d, got %d.' . "\nResponse: %s",
                count($inputSaints),
                count($outputSaints),
                $response
            ));
        }

        foreach ($inputSaints as $index => $inputSaint) {
            $outputSaint = $outputSaints[$index];
            $inputKeys = array_keys($inputSaint);
            $outputKeys = array_keys($outputSaint);

            sort($inputKeys);
            sort($outputKeys);

            if ($inputKeys !== $outputKeys) {
                throw new \RuntimeException(sprintf(
                    'AI response entry at index %d has different structure. Expected keys: [%s], got: [%s].' . "\nResponse: %s",
                    $index,
                    implode(', ', $inputKeys),
                    implode(', ', $outputKeys),
                    $response
                ));
            }
        }

        return $response;
    }
}
