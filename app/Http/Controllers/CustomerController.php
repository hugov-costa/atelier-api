<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Services\CustomerService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Customers', weight: 21)]
class CustomerController extends Controller
{
    public function __construct(private CustomerService $customers) {}

    /**
     * Delete a customer
     *
     * Removes the customer and cascades to their commission orders.
     */
    public function destroy(Customer $customer): Response
    {
        $this->authorize('delete', $customer);

        $this->customers->delete($customer);

        return response()->noContent();
    }

    /**
     * List customers
     *
     * Returns a paginated collection of customers. Restricted to staff.
     */
    #[QueryParameter('per_page', 'Number of items per page (1-100).', required: false, type: 'integer', example: 15)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        return CustomerResource::collection($this->customers->paginate($request->integer('per_page', 15)))
            ->additional(['message' => null]);
    }

    /**
     * Restore a customer
     *
     * Restores a soft-deleted customer and cascades to their commission orders. Restricted to staff.
     */
    public function restore(Customer $customer): JsonResponse
    {
        $this->authorize('restore', $customer);

        return ApiResponse::item(new CustomerResource($this->customers->restore($customer)));
    }

    /**
     * Show a customer
     */
    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return ApiResponse::item(new CustomerResource($customer));
    }

    /**
     * Create a customer
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customers->create($request->validated());

        return ApiResponse::item(new CustomerResource($customer), status: Response::HTTP_CREATED);
    }

    /**
     * Update a customer
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer = $this->customers->update($request->validated(), $customer);

        return ApiResponse::item(new CustomerResource($customer));
    }
}
