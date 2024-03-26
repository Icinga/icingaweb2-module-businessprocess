<?php

$includes = [];
if (PHP_VERSION_ID >= 80000) {
    $includes[] = __DIR__ . '/phpstan-baseline-8x.neon';
} else {
    $includes[] = __DIR__ . '/phpstan-baseline-7x.neon';
}

return [
    'includes' => $includes
];
