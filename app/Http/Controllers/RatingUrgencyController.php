<?php

namespace App\Http\Controllers;

use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(Request $request, DrivingForceRating $driving_force_rating)
    {
        try {
            DB::beginTransaction();

            if ($request['type'] == 'uncertainty') {
                $driving_force_rating->uncertainty_analysis = $request['value'];
            } else {
                $driving_force_rating->impact_analysis = $request['value'];
            }

            $driving_force_rating->save();

            DB::commit();

            DB::beginTransaction();


            if ($driving_force_rating->impact_analysis != null && $driving_force_rating->uncertainty_analysis != null) {
                if ($driving_force_rating->impact_analysis >= 6 && $driving_force_rating->uncertainty_analysis >= 6) {
                    $driving_force_rating->priority_id = 1;
                } else if ($driving_force_rating->impact_analysis >= 6 && $driving_force_rating->uncertainty_analysis <= 5) {
                    $driving_force_rating->priority_id = 2;
                } else if ($driving_force_rating->impact_analysis <= 5 && $driving_force_rating->uncertainty_analysis >= 6) {
                    $driving_force_rating->priority_id = 2;
                } else {
                    $driving_force_rating->priority_id = 3;
                }
            }

            $driving_force_rating->save();

            DB::commit();


            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully set rating for ' . $driving_force_rating->driving_force->keyword . ' ' . ($request['type'] == 'uncertainty' ? 'uncertainty !' : 'impact !')
            ];
        } catch (\Throwable $th) {

            DB::rollBack();

            $response = [
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $th->getMessage(),
            ];
        }

        return response()->json($response, $response['statusCode']);
    }
}
