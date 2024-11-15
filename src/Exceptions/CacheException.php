<?php

declare(strict_types=1);

namespace Cdek\Exceptions;

use Cdek\Contracts\ExceptionContract;

class CacheException extends ExceptionContract
{
    protected string $key = 'cache.fs.rights';

    public function __construct(string $path)
    {
        $this->message = $this->message ?: sprintf(
            /* translators: %s: Cache directory path */
            esc_html__('Cache directory is not writable: %s', 'cdekdelivery'),
            $path,
        );

        parent::__construct([
            'path' => $path,
        ]);
    }
}
