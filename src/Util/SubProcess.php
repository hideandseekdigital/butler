<?php

namespace Console\Util;

// use Console\Util\Git;
use Symfony\Component\Process\Process;

class SubProcess
{
    public static function sync($input, $output, $rootDir = './')
    {
        try {
            \chdir($rootDir);
            self::exportDB($input, $output);
            Git::addAll($input, $output);
            Git::commit($input, $output, '[Butler] Server sync.');
            Git::push($input, $output);
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return;
        }
    }

    public static function exportDB($input, $output)
    {
        $output->writeln('<info>Exporting database...</info>');
        if (!is_dir('sql')) {
            mkdir('sql');
        }

        $filename = 'sql/export.sql';
        $process = new Process(['wp', 'db', 'export', '--add-drop-table', $filename]);
        $process->run();
        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        echo $process->getOutput();
    }

    public static function importDB($input, $output, $envFile)
    {
        $output->writeln('<info>[db:import] Creating database.</info>');
        $cmd = "wp db create";
        $process = Process::fromShellCommandline($cmd);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        $config = Env::loadConfig($envFile);
        $newSiteUrl = 'http://' . $config['domain'];

        if (!file_exists('./sql/export.sql')) {
            $output->writeln('[db:import] No sql export found, skipping import.');
            return;
        }

        // import db from sql
        $output->writeln('[db:import] wp db import sql/export.sql');
        $cmd = 'wp db import sql/export.sql';
        $process = Process::fromShellCommandline($cmd);
        $process->setWorkingDirectory('./');
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        // get current url stored in db
        $output->writeln('[db:import] Getting the site url in database.');
        $cmd = 'wp option get siteurl';
        $process = Process::fromShellCommandline($cmd);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
        $currentSiteUrl = $process->getOutput();

        // replace current url to url in wp-config
        $output->writeln('<info>[db:import] Replacing site urls in the database...</info>');
        $cmd = "wp search-replace $currentSiteUrl $newSiteUrl";
        $process = Process::fromShellCommandline($cmd);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        $output->writeln('<info>If you have virtual hosts set up already, you should have a local site running at ' . $newSiteUrl . '</info>');
        $output->writeln('Run <info>virtualhost create ' . $config['domain'] . ' ' . getcwd() . '</info>');
    }
}