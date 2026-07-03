<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Support\PaginatesWithCache;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    use PaginatesWithCache;
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Customer
    {
        return Customer::create($attributes);
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        $query = Customer::query();

        return $this->cachedPaginate(Customer::class, $query, $perPage, orderBy: ['name' => 'asc']);
    }

    public function restore(Customer $customer): Customer
    {
        $customer->restore();

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes, Customer $customer): Customer
    {
        $customer->update($attributes);

        return $customer;
    }
}
