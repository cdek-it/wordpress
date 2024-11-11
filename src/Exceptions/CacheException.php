<?php

declare(strict_types=1);

namespace Cdek\Exceptions;

use Cdek\Contracts\ExceptionContract;

class CacheException extends ExceptionContract
{
    protected string $key = 'cache.fs.rights';
    public function __construct(string $path)
    {
        parent::__construct('Failed to check fs rights', [
            'path' => $path,
        ]);
    }
}
