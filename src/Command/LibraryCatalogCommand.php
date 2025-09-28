<?php

namespace App\Command;

use App\Service\WifParser;
use App\Entity\Pattern;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'library:catalog',
    description: 'Scans all WIF files in the data directory and creates a catalog.json or populates the database',
    aliases: ['wif:catalog']
)]
class LibraryCatalogCommand extends Command
{
    public function __construct(
        private WifParser $wifParser,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'database',
            'd',
            InputOption::VALUE_NONE,
            'Create Pattern entities in the database instead of generating a JSON file'
        );
        
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_REQUIRED,
            'Limit the number of files to process (useful for testing)',
            null
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $useDatabase = $input->getOption('database');
        $limit = $input->getOption('limit');
        
        // Increase memory limit for large datasets
        ini_set('memory_limit', '512M');
        
        if ($useDatabase) {
            $io->title('Library Database Populator');
        } else {
            $io->title('Library Catalog Generator');
        }

        $dataDir = getcwd() . '/data';
        $wifDir = $dataDir . '/wif';
        $catalogFile = $dataDir . '/catalog.json';

        // Check if data directory exists
        if (!is_dir($dataDir)) {
            $io->error("Data directory not found: {$dataDir}");
            return Command::FAILURE;
        }

        // Check if wif directory exists
        if (!is_dir($wifDir)) {
            $io->error("WIF directory not found: {$wifDir}");
            return Command::FAILURE;
        }

        $io->section('Scanning WIF files...');

        // Find all .wif files recursively
        $finder = new Finder();
        $finder->files()
            ->in($wifDir)
            ->name('*.wif')
            ->sortByName();

        $totalFiles = count($finder);
        
        // Apply limit if specified
        if ($limit !== null) {
            $limit = (int) $limit;
            $totalFiles = min($totalFiles, $limit);
            $io->note("Found {$totalFiles} WIF files to process (limited to {$limit})");
        } else {
            $io->note("Found {$totalFiles} WIF files to process");
        }

        if ($totalFiles === 0) {
            $io->warning('No WIF files found in the data directory.');
            return Command::SUCCESS;
        }

        $catalog = [];
        $processed = 0;
        $errors = 0;
        $created = 0;
        $updated = 0;

        $progressBar = $io->createProgressBar($totalFiles);
        $progressBar->start();

        foreach ($finder as $file) {
            // Check limit
            if ($limit !== null && $processed >= $limit) {
                break;
            }
            
            try {
                // Extract only the metadata we need without full parsing
                $catalogEntry = $this->extractWifMetadata($file->getRealPath());
                
                if ($useDatabase) {
                    // Create or update Pattern entity
                    $result = $this->createOrUpdatePattern($catalogEntry);
                    if ($result === 'created') {
                        $created++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    }
                } else {
                    // Add to catalog array for JSON file
                    $catalog[] = $catalogEntry;
                }
                
                $processed++;

            } catch (\Exception $e) {
                $errors++;
                // Log error but continue processing other files
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $io->error("Error processing {$file->getFilename()}: " . $e->getMessage());
                }
            }

            $progressBar->advance();

            // Give a brief status update every 1000 files
            if ($processed % 1000 === 0) {
                $progressBar->clear();
                if ($useDatabase) {
                    $io->comment("Processed: {$processed} files, Created: {$created}, Updated: {$updated}, Errors: {$errors}");
                } else {
                    $io->comment("Processed: {$processed} files, Errors: {$errors}");
                }
                $progressBar->display();
            }

            // Free memory periodically and flush database
            if ($processed % 1000 === 0) {
                if ($useDatabase) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
                gc_collect_cycles();
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        if ($useDatabase) {
            // Flush any remaining entities to database
            $io->section('Saving to database...');
            $this->entityManager->flush();
            
            $io->success("Database populated successfully!");
            $io->definitionList(
                ['Total files processed' => $processed],
                ['Patterns created' => $created],
                ['Patterns updated' => $updated],
                ['Errors encountered' => $errors]
            );
        } else {
            // Write catalog to JSON file
            $io->section('Writing catalog file...');
            
            try {
                $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
                $jsonContent = json_encode($catalog, $jsonOptions);
                
                if (file_put_contents($catalogFile, $jsonContent) === false) {
                    $io->error("Failed to write catalog file: {$catalogFile}");
                    return Command::FAILURE;
                }

                $io->success("Catalog created successfully!");
                $io->definitionList(
                    ['Catalog file' => $catalogFile],
                    ['Total files processed' => $processed],
                    ['Errors encountered' => $errors],
                    ['Catalog entries' => count($catalog)]
                );
            } catch (\Exception $e) {
                $io->error("Failed to create catalog: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        if ($errors > 0) {
            $io->warning("Some files could not be processed. Use -v flag for detailed error messages.");
        }

        return Command::SUCCESS;
    }

    private function extractWifMetadata(string $filePath): array
    {
        $title = 'Untitled';
        $source = '';
        $shafts = 0;
        $treadles = 0;

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Cannot open file: {$filePath}");
        }

        $currentSection = null;
        $foundData = ['title' => false, 'source' => false, 'shafts' => false, 'treadles' => false];
        
        try {
            while (($line = fgets($handle)) !== false && !array_reduce($foundData, fn($carry, $item) => $carry && $item, true)) {
                $line = trim($line);
                
                // Skip empty lines and comments
                if (empty($line) || str_starts_with($line, ';')) {
                    continue;
                }

                // Check for section header
                if (preg_match('/^\[([^\]]+)\]$/', $line, $matches)) {
                    $currentSection = strtoupper($matches[1]);
                    continue;
                }

                // Parse key-value pairs based on current section
                if ($currentSection && str_contains($line, '=')) {
                    $parts = explode('=', $line, 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);

                    // Remove comments from value
                    $commentIndex = strpos($value, ';');
                    if ($commentIndex !== false) {
                        $value = trim(substr($value, 0, $commentIndex));
                    }

                    if ($currentSection === 'TEXT' && $key === 'Title') {
                        $title = $value;
                        $foundData['title'] = true;
                    } elseif ($currentSection === 'WIF' && $key === 'Source Program') {
                        $source = $value;
                        $foundData['source'] = true;
                    } elseif ($currentSection === 'WEAVING' && $key === 'Shafts') {
                        $shafts = (int)$value;
                        $foundData['shafts'] = true;
                    } elseif ($currentSection === 'WEAVING' && $key === 'Treadles') {
                        $treadles = (int)$value;
                        $foundData['treadles'] = true;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'title' => $title,
            'source' => $source,
            'shafts' => $shafts,
            'treadles' => $treadles,
            'filepath' => $filePath
        ];
    }

    private function createOrUpdatePattern(array $catalogEntry): string
    {
        // Extract filename from filepath for unique identification
        $filename = basename($catalogEntry['filepath'], '.wif');
            // Create new pattern
            $pattern = new Pattern();
            $pattern->setTitle($catalogEntry['title']);
            $pattern->setShafts($catalogEntry['shafts']);
            $pattern->setTreadles($catalogEntry['treadles']);
            $pattern->setWif(file_get_contents($catalogEntry['filepath']));
            $pattern->setCreatedAt(new \DateTimeImmutable());
            $pattern->setUpdateAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($pattern);
            return 'created';
    }
}
