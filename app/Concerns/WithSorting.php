<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Consuming components must declare:
 * - public string $sortBy
 * - public string $sortDirection
 * - protected array $sortable
 */
trait WithSorting
{
    public function sort(string $column): void
    {
        if (! in_array($column, $this->sortable, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySorting(Builder $query): Builder
    {
        $sortBy = in_array($this->sortBy, $this->sortable, true)
            ? $this->sortBy
            : ($this->sortable[0] ?? 'id');

        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortDirection);
    }
}
