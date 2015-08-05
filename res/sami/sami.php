<?php

use Sami\Sami;

if (!$json = file_get_contents('./composer.json')) {
    throw new RuntimeException('No Composer configuration found.');
}

$configuration = json_decode($json);

if (JSON_ERROR_NONE !== json_last_error()) {
    throw new RuntimeException('Invalid Composer configuration.');
}

$namespace = null;

if (property_exists($configuration, 'autoload')) {
    if (property_exists($configuration->autoload, 'psr-4')) {
        $namespaces = get_object_vars($configuration->autoload->{'psr-4'});

        foreach ($namespaces as $ns => $path) {
            if ('_empty_' !== $ns) {
                $namespace = trim($ns, '\\');
            }

            break;
        }
    } elseif (property_exists($configuration->autoload, 'psr-0')) {
        $namespaces = get_object_vars($configuration->autoload->{'psr-0'});

        foreach ($namespaces as $ns => $path) {
            if ('_empty_' !== $ns) {
                $namespace = trim($ns, '\\');
            }

            break;
        }
    }
}

if (null === $namespace) {
    if (!property_exists($configuration, 'name')) {
        throw new RuntimeException(
            'No project name set in Composer configuration.'
        );
    }

    $name = $configuration->name;
    $openedLevel = 0;
} else {
    $namespaceAtoms = explode('\\', $namespace);
    $namespaceAtomCount = count($namespaceAtoms);

    if ($namespaceAtomCount > 1) {
        array_shift($namespaceAtoms);
    }

    $name = implode(' - ', $namespaceAtoms);
    $openedLevel = $namespaceAtomCount;
}

return new Sami(
    './src',
    array(
        'title' => sprintf('%s API', $name),
        'default_opened_level' => $openedLevel,
        'build_dir' => './artifacts/documentation/api',
        'cache_dir' => './artifacts/documentation/api-cache',
    )
);
