<?php

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Model {
    class TaskOutputData
    {
        private string $status;
        private ?array $data;
        private ?int $currentPage;
        private ?int $totalPages;

        public function __construct(
            string $status,
            ?array $data = null,
            ?int $currentPage = null,
            ?int $totalPages = null
        ){
            $this->status = $status;
            $this->data = $data;
            $this->currentPage = $currentPage;
            $this->totalPages = $totalPages;
        }

        public function getStatus(): string
        {
            return $this->status;
        }

        public function getData(): ?array
        {
            return $this->data;
        }

        public function getCurrentPage(): ?int
        {
            return $this->currentPage;
        }

        public function getTotalPages(): ?int
        {
            return $this->totalPages;
        }
    }
}
