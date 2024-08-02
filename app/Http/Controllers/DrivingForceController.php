<?php

namespace App\Http\Controllers;

use App\Models\Dimension;
use App\Models\DrivingForce;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class DrivingForceController extends Controller
{
    //

    public function index()
    {
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });

        return Inertia::render("DrivingForce/Table", compact('dimensions'));
    }

    public function fetch_data()
    {
        try {
            $result = DrivingForce::filter();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
