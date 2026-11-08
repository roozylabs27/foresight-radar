<?php

namespace App\Http\Controllers;

use App\Models\ProjectParticipant;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Participants of an exercise (roadmap R2.2). Managed by the coordinator: `manage-scenario` and the
 * project policy (creator, owner, admin).
 */
class ProjectParticipantController extends Controller
{
    public function store(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'user' => ['required', 'uuid', TenantRule::exists('users', 'uuid')],
            'role' => ['required', Rule::in(ProjectParticipant::ROLES)],
            'division' => ['nullable', 'string', 'max:100'],
        ], [], ['user' => 'peserta', 'role' => 'peran', 'division' => 'divisi']);

        $userId = User::where('uuid', $validated['user'])->value('id');
        if ($project->participants()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages(['user' => 'Orang ini sudah menjadi peserta latihan.']);
        }

        $project->participants()->create([
            'user_id' => $userId,
            'role' => $validated['role'],
            'division' => $validated['division'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Peserta ditambahkan.');
    }

    public function update(Request $request, ScenarioPlanningProject $project, ProjectParticipant $participant): RedirectResponse
    {
        $this->authorize('update', $project);

        $participant->update($request->validate([
            'role' => ['required', Rule::in(ProjectParticipant::ROLES)],
            'division' => ['nullable', 'string', 'max:100'],
        ], [], ['role' => 'peran', 'division' => 'divisi']));

        return back()->with('success', 'Peserta diperbarui.');
    }

    public function destroy(ScenarioPlanningProject $project, ProjectParticipant $participant): RedirectResponse
    {
        $this->authorize('update', $project);

        $participant->delete();

        return back()->with('success', 'Peserta dihapus dari latihan.');
    }
}
