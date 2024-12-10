<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Contracts {

    use Cdek\CoreApi;
    use Iterator;

    abstract class TaskContract
    {
        protected CoreApi $api;
        protected ?array $taskMeta;
        protected string $id;

        /**
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\CacheException
         * @throws \Cdek\Exceptions\External\CoreAuthException
         */
        public function __invoke(string $id): void
        {
            $this->api = new CoreApi;
            $this->id  = $id;

            $this->taskMeta = $this->api->taskGet($this->id)['meta'];

            foreach ($this->process() as $result){
                $this->api->taskResultCreate($this->id, $result);
            }
        }
        abstract protected function process(): Iterator;

        abstract public static function getName(): string;
    }
}
