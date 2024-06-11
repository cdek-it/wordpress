<?php

namespace Cdek\Model;

class CoreApiHeadersData
{
    private array $headers = [];

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setCurrentPage(int $currentPage): self
    {
        $this->headers['X-Current-Page'] = $currentPage;
        return $this;
    }

    public function setTotalPages(int $totalPages): self
    {
        $this->headers['X-Total-Pages'] = $totalPages;
        return $this;
    }


}
