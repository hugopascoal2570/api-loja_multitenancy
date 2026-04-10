<?php

namespace App\Repositories;

use App\DTO\Category\CategoryDTO;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CategoryRepository
{
    public function __construct(protected Category $model) {}

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find(string $id): Category
    {
        return $this->model->findOrFail($id);
    }

    public function store(CategoryDTO $data): Category
    {
        $baseSlug = Str::slug($data->name);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->model->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $this->model->create([
            'name' => $data->name,
            'description' => $data->description,
            'slug' => $slug,
        ]);
    }

    public function update(Category $category, CategoryDTO $data): Category
    {
        $baseSlug = Str::slug($data->name);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->model->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $category->update([
            'name' => $data->name,
            'description' => $data->description,
            'slug' => $slug,
        ]);

        return $category;
    }    

    public function destroy(Category $category): void
    {
        $category->delete();
    }
}
