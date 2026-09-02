<?php

namespace App\SharedKernel\Domain\Trait;

use Doctrine\ORM\Mapping as ORM;

trait SoftDeletableTrait
{
    #[ORM\Column(options: ['default' => true])]
    private bool $isEnabled = true;

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function disable(): void
    {
        $this->isEnabled = false;
    }

    public function enable(): void
    {
        $this->isEnabled = true;
    }
}
