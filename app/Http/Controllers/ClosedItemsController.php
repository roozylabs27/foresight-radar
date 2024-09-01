<?php

namespace App\Http\Controllers;

use App\Models\Dimension;
use App\Models\DrivingForce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ClosedItemsController extends Controller
{
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
            $result = DrivingForce::closed_items();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
