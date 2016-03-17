<?php

if (isset($_ENV['BOOTSTRAP_CLEAR_CACHE_ENV'])) {
passthru(sprintf(
'php "%s/console" cache:clear --env=%s --no-warmup',
__DIR__,
$_ENV['BOOTSTRAP_CLEAR_CACHE_ENV']
));
}

require __DIR__.'/autoload.php';