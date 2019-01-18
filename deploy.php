<?php
namespace Deployer;

require 'recipe/common.php';

// Project name
set('application', 'ordure');

// Project repository
set('repository', 'https://github.com/jimwins/ordure.git');

// Host(s)
inventory('hosts.yml');

// Tasks
desc('Deploy your project');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
