<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourierDispatchRequest;
use App\Http\Requests\UpdateCourierDispatchRequest;
use App\Services\CourierDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourierDispatchRequestController extends Controller
{
    public function __construct(private readonly CourierDispatchService $dispatchService) {}

    public function index(Request $request): Response
    {
        $search = $this->dispatchService->normalizeSearch($request->query('search'));

        return Inertia::render('dispatch-requests/index', [
            'dispatchRequests' => $this->dispatchService->paginate($search),
            'search' => $search ?? '',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('dispatch-requests/create', $this->dispatchService->getFormOptions());
    }

    public function store(StoreCourierDispatchRequest $request): RedirectResponse
    {
        $this->dispatchService->create($request->validated());

        return to_route('dispatch-requests.index')->with('success', 'Dispatch request queued successfully.');
    }

    public function edit(int $dispatchRequest): Response
    {
        return Inertia::render('dispatch-requests/edit', [
            'dispatchRequest' => $this->dispatchService->find($dispatchRequest),
            ...$this->dispatchService->getFormOptions(),
        ]);
    }

    public function update(UpdateCourierDispatchRequest $request, int $dispatchRequest): RedirectResponse
    {
        $this->dispatchService->update($this->dispatchService->find($dispatchRequest), $request->validated());

        return to_route('dispatch-requests.index')->with('success', 'Dispatch request updated and queued again.');
    }

    public function destroy(int $dispatchRequest): RedirectResponse
    {
        $this->dispatchService->delete($this->dispatchService->find($dispatchRequest));

        return to_route('dispatch-requests.index')->with('success', 'Dispatch request deleted successfully.');
    }
}
