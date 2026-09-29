<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Models\Dimension;
use App\Models\DrivingForce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DrivingForceService;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ClosedItemsController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected DrivingForceService $drivingForceService)
    {
    }

    public function index()
    {
        $dimensions = Dimension::select('id', 'name')->get()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });

        $title = "Closed Items";

        return Inertia::render("ClosedItems/Table", compact('dimensions', 'title'));
    }

    public function fetch_data()
    {
        try {
            $result = $this->drivingForceService->closedItems(request()->all());

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching closed items');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
