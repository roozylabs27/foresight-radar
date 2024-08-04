<?php

namespace App\Http\Controllers;

use App\Models\Dimension;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimeHorizonController extends Controller
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

        return Inertia::render("TimeHorizon/Table", compact('dimensions'));
    }
}
