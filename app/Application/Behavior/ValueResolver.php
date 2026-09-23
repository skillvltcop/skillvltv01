<?php

declare(strict_types=1);

namespace App\Application\Behavior;

use App\Domain\Behavior\Exceptions\UnresolvablePathException;

final class ValueResolver
{
    public function resolve(
        string $path,
        array $input,
        array $context,
        array $state,
    ): mixed {
        if (! preg_match(
            '/^(input|context|state)\.[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*$/',
            $path
        )) {
            throw new UnresolvablePathException(
                "Invalid path: {$path}"
            );
        }

        [$source, $key] = explode('.', $path, 2);

        $target = match ($source) {
            'input' => $input,
            'context' => $context,
            'state' => $state,
        };

        $missing = new \stdClass();

        $value = data_get($target, $key, $missing);

        if ($value === $missing) {
            throw new UnresolvablePathException(
                "Unable to resolve path: {$path}"
            );
        }

        return $value;
    }
}