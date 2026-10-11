<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(private Product $model) {}

    public function findById(int $id): ?Product
    {
        return $this->model->find($id);
    }

    public function findForUpdate(int $id): ?Product
    {
        return $this->model->lockForUpdate()->find($id);
    }

    /** @return Collection<int, Product> */
    public function getAll(): Collection
    {
        return $this->model->with(['category', 'services'])->orderBy('name')->get();
    }

    /** @return Collection<int, Product> */
    public function getActive(): Collection
    {
        return $this->model->active()->with(['category', 'services'])->orderBy('name')->get();
    }

    /** @return Collection<int, Product> */
    public function getLowStock(): Collection
    {
        return $this->model->active()->lowStock()->with('category')->orderBy('name')->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }

    public function delete(Product $product): bool
    {
        return (bool) $product->delete();
    }
}
