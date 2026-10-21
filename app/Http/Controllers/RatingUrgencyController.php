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
use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Services\DrivingForceService;
use Symfony\Component\HttpFoundation\Response;

class RatingUrgencyController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected DrivingForceService $drivingForceService)
    {
    }

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
            $result = $this->drivingForceService->ratingUrgency(request()->all());

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching rating urgency data');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(Request $request, DrivingForceRating $driving_force_rating)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:impact,uncertainty'],
            'value' => ['required', 'integer', 'between:1,10'],
        ]);

        try {
            DB::transaction(function () use ($validated, $driving_force_rating) {
                if ($validated['type'] == 'uncertainty') {
                    $driving_force_rating->uncertainty_analysis = $validated['value'];
                } else {
                    $driving_force_rating->impact_analysis = $validated['value'];
                }

                $driving_force_rating->priority_id = $driving_force_rating->calculatePriority() ?? $driving_force_rating->priority_id;
                $driving_force_rating->save();
            });

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully set rating for ' . $driving_force_rating->driving_force->keyword . ' ' . ($validated['type'] == 'uncertainty' ? 'uncertainty !' : 'impact !')
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
