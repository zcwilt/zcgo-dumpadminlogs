<?php
/**
 *
 * @copyright Copyright 2003-2020 Zen Cart Development Team
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id:  $
 */

namespace Zcgo\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Zcgo\Lib\FileSystem;

class DumpAdminLogsCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'adminlogs:clear';

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setDescription('Dump(empty) the Admin Logs table')
            ->setHelp(
                'This will empty the contents of the admin logs table.' . "\n" .
                '')
            ->setDefinition(
                new InputDefinition(
                    [
                        new InputOption('backup', 'b', InputOption::VALUE_OPTIONAL, 'Output contents of log table to a file'),
                    ])
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->fs = FileSystem::getInstance();
        try {
            $dbh = new \PDO(DB_TYPE . ':host=' . DB_SERVER . ';dbname=' . DB_DATABASE, DB_SERVER_USERNAME, DB_SERVER_PASSWORD);
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        try {
            $sth = $dbh->prepare('SELECT count(*) as count FROM ' . DB_PREFIX . 'admin_activity_log');
            $sth->execute();
            $result = $sth->fetch();
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        if ((int)$result['count'] === 0) {
            $output->writeln('<comment>There are no entries in the Activity Log</comment>');
            return Command::SUCCESS;
        }
        $output->writeln('There are currently ' . $result['count'] . ' entries in Admin Activity Log');
        $helper = $this->getHelper('question');
        $file = $input->getOption('backup');
        if (!$file) {
            $output->writeln('<comment>Warning:No Backup file set</comment>');

        }
        $question = new ConfirmationQuestion('Are you sure you want to delete all admin log data(y/n)?', false);
        if (!$helper->ask($input, $output, $question)) {
            return Command::SUCCESS;
        }

        $file = $input->getOption('backup');


        if ($file && $this->fs->fileExists($file)) {
            $question = new ConfirmationQuestion($file . ' already exists, do you want to overwrite(y/n)?', false);
            if (!$helper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }
        }

        try {
            $this->saveCSVFile($file, $dbh);
        } catch (\Exception $e ) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        try {
            $sth = $dbh->prepare('DELETE FROM ' . DB_PREFIX . 'admin_activity_log');
            $sth->execute();
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>All Entries removed</info>');
        return Command::SUCCESS;
    }

    protected function saveCSVFile(string $file, $dbh): void
    {
        $sth = $dbh->prepare('SELECT * FROM ' . DB_PREFIX . 'admin_activity_log');
        $sth->execute();
        $fp = fopen($file, 'w');
        $this->saveCSVHeader($fp);
        while ($row = $sth->fetch()) {
            $fields = [$row['access_date'], $row['admin_id'], $row['page_accessed'], $row['page_parameters'], $row['ip_address'], $row['flagged'], $row['attention'], $row['gzpost'], $row['logmessage'], $row['severity']];
            fputcsv($fp, $fields);
        }
        fclose($fp);
    }

    protected function saveCSVHeader($fp): void
    {
        $headers = ['access date', 'admin id', 'page_accessed', 'page parameters', 'ip address', 'flagged', 'attention', 'gzpost', 'logmessage', 'severity'];
        fputcsv($fp, $headers);
    }
}
