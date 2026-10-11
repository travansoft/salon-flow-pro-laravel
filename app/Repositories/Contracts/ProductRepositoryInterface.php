<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findForUpdate(int $id): ?Product;

    /** @return Collection<int, Product> */
    public function getAll(): Collection;

    /** @return Collection<int, Product> */
    public function getActive(): Collection;

    /** @return Collection<int, Product> */
    public function getLowStock(): Collection;

    /** @param array<string, mixed> $data */
    public function create(array $data): Product;

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data): Product;

    public function delete(Product $product): bool;
}
