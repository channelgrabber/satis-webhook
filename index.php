<?php
fastcgi_finish_request();

const LOCK_FILE = "/var/run/satis.lock";
const TIME_LIMIT = 60;

$lockHandle = fopen(LOCK_FILE, "w+");
set_time_limit(TIME_LIMIT);
while(!flock($lockHandle, LOCK_EX)) {
    // busy waiting
}


require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

if (!file_exists(__DIR__.'/config.yml')) {
    echo "Please, define your satis configuration in a config.yml file.\nYou can use the config.yml.dist as a template.";
    exit(-1);
}

$defaults = array(
    'bin' => 'bin/satis',
    'json' => 'satis.json',
    'webroot' => 'web/',
    'user' => null,
);
$config = Yaml::parse(__DIR__.'/config.yml');
$config = array_merge($defaults, $config);

$errors = array();
if (!file_exists($config['bin'])) {
    $errors[] = 'The Satis bin could not be found.';
}

if (!file_exists($config['json'])) {
    $errors[] = 'The satis.json file could not be found.';
}

if (!file_exists($config['webroot'])) {
    $errors[] = 'The webroot directory could not be found.';
}

if (!empty($errors)) {
    echo 'The build cannot be run due to some errors. Please, review them and check your config.yml:'."\n";
    foreach ($errors as $error) {
        echo '- '.$error."\n";
    }
    exit(-1);
}

$command = sprintf('%s build %s %s', $config['bin'], $config['json'], $config['webroot']);
if (isset($_GET['package'])) {
    $command .= ' ' . $_GET['package'];
    chdir($config['repositories'] .  '/' . $_GET['package']);
    exec('git fetch origin && git remote update --prune origin && git branch -D `git branch -l | grep -v \* | xargs` ; for remote in `git branch -r | grep -v HEAD `; do git checkout --track $remote ; done');
    chdir(__DIR__);
}
if (null !== $config['user']) {
    $command = sprintf('sudo -u %s -i %s', $config['user'], $command);
}

$process = new Process($command);
$exitCode = $process->run(function ($type, $buffer) {
    if ('err' === $type) {
        echo 'E';
        error_log($buffer);
    } else {
        echo '.';
    }
});

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

echo "\n\n" . ($exitCode === 0 ? 'Successful rebuild!' : 'Oops! An error occured!') . "\n";
