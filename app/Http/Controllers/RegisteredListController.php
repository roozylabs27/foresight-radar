<?php

namespace App\Http\Controllers;

use App\Models\Dimension;
use App\Models\DrivingForceRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RegisteredListController extends Controller
{
    public function index()
    {
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });
        $title = "Registered List";

        return Inertia::render('Report/RegisteredList', compact('dimensions','title'));
    }

    public function registered_list()
    {
        try {
            $result = DrivingForceRating::overall_status();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
