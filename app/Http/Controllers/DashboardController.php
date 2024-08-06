<?php

namespace App\Http\Controllers;

use App\Models\DrivingForceRating;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard');
    }

    public function prioritizing()
    {
        try {
            $result = DrivingForceRating::prioritizing();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
