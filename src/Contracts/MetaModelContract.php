<?php

declare(strict_types=1);

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Contracts {

    abstract class MetaModelContract {
        protected const ALIASES = [];

        protected array $meta;
        protected array $dirty = [];

        /** @noinspection MissingReturnTypeInspection */
        public function __get(string $key)
        {
            if (isset($this->meta[$key])) {
                return $this->meta[$key];
            }

            if (!isset(static::ALIASES[$key])) {
                return null;
            }

            foreach (static::ALIASES[$key] as $alias) {
                if (!isset($this->meta[$alias])) {
                    continue;
                }

                $this->meta[$key] = $this->meta[$alias];
                unset($this->meta[$alias]);
                $this->save();

                return $this->meta[$key];
            }

            return null;
        }

        /** @noinspection MissingParameterTypeDeclarationInspection */
        public function __set(string $key, $value): void
        {
            $this->meta[$key] = $value;

            if(!in_array($key, $this->dirty, true)) {
                $this->dirty[] = $key;
            }
        }

        public function __isset(string $key): bool
        {
            return isset($this->meta[$key]);
        }

        public function __unset(string $key): void
        {
            unset($this->meta[$key]);
        }

        abstract public function save(): void;
        abstract public function clean(): void;
    }
}
