<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CriticalUncertainty;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Models\StrategicResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Comment threads on exercise artefacts (roadmap R2.2). Anyone who can open the exercise reads;
 * whoever may give input (see ScenarioPlanningProject::acceptsInputFrom) comments; the author or
 * the coordinator resolves or deletes.
 */
class CommentController extends Controller
{
    public function index(Request $request, ScenarioPlanningProject $project): JsonResponse
    {
        $target = $this->target($project, $request->validate($this->targetRules()));
        $user = $request->user();

        return response()->json([
            'comments' => $target->comments()->with(['author:id,name', 'resolver:id,name'])->get()->map(fn (Comment $comment) => [
                'id' => $comment->uuid,
                'body' => $comment->body,
                'author' => $comment->author?->name,
                'at' => $comment->created_at->toISOString(),
                'resolved_at' => $comment->resolved_at?->toISOString(),
                'resolved_by' => $comment->resolver?->name,
                'can_resolve' => $this->moderates($user, $project, $comment),
                'can_delete' => $this->moderates($user, $project, $comment),
            ])->values(),
            'can_comment' => $project->acceptsInputFrom($user),
        ]);
    }

    public function store(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $validated = $request->validate($this->targetRules() + ['body' => ['required', 'string', 'max:2000']], [], ['body' => 'komentar']);
        abort_unless($project->acceptsInputFrom($request->user()), 403);

        $this->target($project, $validated)->comments()->create([
            'project_id' => $project->id,
            'body' => $validated['body'],
            'author_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Komentar ditambahkan.');
    }

    /** Resolve an open comment, or reopen a resolved one. */
    public function resolve(Request $request, ScenarioPlanningProject $project, Comment $comment): RedirectResponse
    {
        abort_unless($this->moderates($request->user(), $project, $comment), 403);

        $comment->update($comment->resolved_at
            ? ['resolved_at' => null, 'resolved_by' => null]
            : ['resolved_at' => now(), 'resolved_by' => $request->user()->id]);

        return back()->with('success', $comment->resolved_at ? 'Komentar ditandai selesai.' : 'Komentar dibuka kembali.');
    }

    public function destroy(Request $request, ScenarioPlanningProject $project, Comment $comment): RedirectResponse
    {
        abort_unless($this->moderates($request->user(), $project, $comment), 403);

        $comment->delete();

        return back()->with('success', 'Komentar dihapus.');
    }

    private function targetRules(): array
    {
        return ['type' => ['required', Rule::in(array_keys(Comment::TYPES))], 'id' => ['required', 'uuid']];
    }

    /** The commented artefact, which must belong to this exercise. */
    private function target(ScenarioPlanningProject $project, array $validated): ProjectDrivingForce|CriticalUncertainty|ScenarioQuadrant|StrategicResponse
    {
        $target = match ($validated['type']) {
            'force' => ProjectDrivingForce::where('project_id', $project->id)->where('uuid', $validated['id'])->first(),
            'uncertainty' => CriticalUncertainty::where('project_id', $project->id)->where('uuid', $validated['id'])->first(),
            'quadrant' => ScenarioQuadrant::whereHas('scenario', fn ($q) => $q->where('project_id', $project->id))->where('uuid', $validated['id'])->first(),
            'response' => StrategicResponse::where('project_id', $project->id)->where('uuid', $validated['id'])->first(),
            default => null,
        };
        if (! $target) {
            throw ValidationException::withMessages(['id' => 'Item yang dikomentari tidak ada di latihan ini.']);
        }

        return $target;
    }

    private function moderates(User $user, ScenarioPlanningProject $project, Comment $comment): bool
    {
        return $comment->author_id === $user->id || $user->can('update', $project);
    }
}
