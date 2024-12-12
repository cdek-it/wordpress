<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Tasks {

    use Cdek\Contracts\TaskContract;
    use Cdek\Loader;
    use Cdek\Model\TaskResult;
    use Iterator;

    class Migrate extends TaskContract {

        protected function process(): Iterator
        {
            foreach (Loader::MIGRATORS as $migrator){
                (new $migrator)();
            }

            yield new TaskResult('success');
        }

        public static function getName(): string
        {
            return 'migrate-connector';
        }
    }
}
