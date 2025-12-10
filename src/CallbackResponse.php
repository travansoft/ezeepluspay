<?php

namespace Travansoft\EzeePlusPay;

class CallbackResponse
{
    private $valid;
    private $data;
    private $error;

    public function __construct(bool $valid, $data = null, string $error = null)
    {
        $this->valid = $valid;
        $this->data  = $data;
        $this->error = $error;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
