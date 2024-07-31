<?php

namespace App\Http\Controllers;

use App\Models\DrivingForce;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class DrivingForceController extends Controller
{
    //

    public function index()
    {
        return Inertia::render("DrivingForce");
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
