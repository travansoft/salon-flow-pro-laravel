<?php

namespace App\Services;

use App\Models\Branch;

class BranchContext
{
    private ?Branch $branch = null;

    private bool $bypassed = false;

    private bool $noneResolved = false;

    public function set(Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function get(): ?Branch
    {
        return $this->branch;
    }

    public function has(): bool
    {
        return $this->branch !== null;
    }

    /**
     * Explicitly bypass branch scoping for a deliberate, cross-branch context
     * such as consolidated reports or super-admin.
     */
    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    public function bypass(): void
    {
        $this->bypassed = true;
    }

    /**
     * Marks that branch resolution genuinely ran and found nothing to select
     * (no assigned branches, or multiple assigned branches with none chosen
     * yet) — distinct from a bug where BranchContext::set() was simply never
     * called. Branch-scoped queries return empty instead of throwing.
     */
    public function markNoneResolved(): void
    {
        $this->noneResolved = true;
    }

    public function isNoneResolved(): bool
    {
        return $this->noneResolved;
    }
}
