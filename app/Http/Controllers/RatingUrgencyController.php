<?php

namespace App\Http\Controllers;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class RatingUrgencyController extends Controller
{
    //

    public function index()
    {
        $title = 'Rating Urgency';
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });

        return Inertia::render("RatingUrgency/Table", compact('dimensions', 'title'));
    }

    public function fetch_data()
    {
        try {
            $result = DrivingForce::rating_urgency();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(Request $request, DrivingForceRating $driving_force_rating)
    {
        try {
            DB::transaction(function () use ($request, $driving_force_rating) {
                if ($request['type'] == 'uncertainty') {
                    $driving_force_rating->uncertainty_analysis = $request['value'];
                } else {
                    $driving_force_rating->impact_analysis = $request['value'];
                }

                // Calculate priority if both analyses are present
                if ($driving_force_rating->impact_analysis !== null && $driving_force_rating->uncertainty_analysis !== null) {
                    if ($driving_force_rating->impact_analysis >= 6 && $driving_force_rating->uncertainty_analysis >= 6) {
                        $driving_force_rating->priority_id = 1;
                    } elseif ($driving_force_rating->impact_analysis >= 6 || $driving_force_rating->uncertainty_analysis >= 6) {
                        $driving_force_rating->priority_id = 2;
                    } else {
                        $driving_force_rating->priority_id = 3;
                    }
                }

                $driving_force_rating->save();
            });

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully set rating for ' . $driving_force_rating->driving_force->keyword . ' ' . ($request['type'] == 'uncertainty' ? 'uncertainty !' : 'impact !')
            ];
        } catch (\Throwable $th) {
            Log::error('Rating urgency update failed', [
                'rating_id' => $driving_force_rating->id,
                'error' => $th->getMessage(),
            ]);

            $response = [
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'An error occurred while updating the rating. Please try again.',
            ];
        }

        return response()->json($response, $response['statusCode']);
    }
}
