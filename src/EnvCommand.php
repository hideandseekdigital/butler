<?php
namespace Console;

use Console\Util\Env as Env;
use Console\Util\Validator as Validator;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class EnvCommand extends SymfonyCommand
{
    public function __construct()
    {
        parent::__construct();
    }

    public function configure()
    {
        $this->setName('env')
            ->setDescription('Generate .butler.env file.')
            ->addOption('configFile', null, InputArgument::OPTIONAL, '.butler.env file path (default to current folder)', './.butler.env');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        // check if there's .env file
        $configFile = $input->getOption('configFile');
        if (!file_exists($configFile)) {
            $this->create_env_file($input, $output, $configFile);
        }
    }

    /**
     * create a new butler config file
     * @param [type] $input
     * @param [type] $output
     * @return void
     */
    public function create_env_file(InputInterface $input, OutputInterface $output, $configFile)
    {
        $helper = $this->getHelper('question');
        $question_start = new ConfirmationQuestion('<question>.butler.env doesn\'t exist, create a new .butler.env file ' . $configFile . '? (Y/n): </question>', true);

        if (!$helper->ask($input, $output, $question_start)) {
            $output->writeln('K, bye!');
            return;
        }

        // create new .env file
        $conf = [
            'domain' => '',
            'db_user' => '',
            'db_pass' => '',
            'db_host' => '',
        ];

        $output->writeln('<comment>You can change these settings in .butler.env file after generation.</comment>');

        // ask for domain
        $q_domain = new Question('Please enter the domain of this site. (e.g. example.cordelta.digital):', 'example.cordelta.digital');

        $q_domain->setValidator(function ($answer) {
            if (!Validator::isDomainValid($answer)) {
                throw new \RuntimeException(
                    'Invalid domain, please try again.'
                );
            }

            return $answer;
        });
        $a_domain = $helper->ask($input, $output, $q_domain);

        $conf['domain'] = $a_domain;

        // ask for db credentials
        // db host
        $q = new Question('Please enter the database host (default=localhost): ', 'localhost');
        $conf['db_host'] = $this->setupEnvVar('db_host', $input, $output, $q);

        // db name
        $q = new Question('Please enter the database name: ', 'butler');
        $conf['db_name'] = $this->setupEnvVar('db_name', $input, $output, $q);

        // db user
        $q = new Question('Please enter the database username: ', 'butler');
        $conf['db_user'] = $this->setupEnvVar('db_user', $input, $output, $q);

        // db pass
        $q = new Question('Please enter the password for this database user: ', '');
        $q->setHidden(true);
        $conf['db_pass'] = $this->setupEnvVar('db_pass', $input, $output, $q);

        $output->writeln('<info>Generating new .butler.env file ' . $configFile . '...</info>');
        Env::generateEnvFile($conf, $configFile);
        $output->writeln('<info>Done.</info>');
    }

    public function setupEnvVar($key, $input, $output, $question)
    {
        $helper = $this->getHelper('question');
        $result = Env::getGlobalEnv($key);
        $create_new = empty($result);
        if ($result) {
            $q = new ConfirmationQuestion('There is a global ' . $key . ' config (' . $result . '), use it here? (Y/n): ', true);
            if (!$helper->ask($input, $output, $q)) {
                $create_new = true;
            }
        }
        if ($create_new) {
            $answer = $helper->ask($input, $output, $question);
            $result = $answer;

            $save_var = new ConfirmationQuestion('Save this config for other wp sites (Y/n): ', true);
            if ($helper->ask($input, $output, $save_var)) {
                Env::saveGlobalEnv($key, $result);
                $output->writeln('Global butler setting saved. [' . $key . ']');
            }
        }

        return $result;
    }

}
