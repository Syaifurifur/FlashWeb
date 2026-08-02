<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompetitionNotification;
use App\Models\EventEdition;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function canManageAll(Request $request): bool
    {
        return $request->user()->role === 'super_admin' || $request->user()->hasPermission('competitions.manage');
    }

    public function index(Request $request)
    {
        $query = CompetitionNotification::where('event_edition_id', EventEdition::resolveCurrent()->id)->with(['competition:id,title', 'author:id,name'])->latest('published_at');
        if (! $this->canManageAll($request)) {
            $query->where('competition_id', $request->user()->competition_id);
        }

        return $query->limit(100)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'competition_id'=>'nullable|exists:competitions,id',
            'title'=>'required|string|max:160',
            'message'=>'required|string|max:5000',
        ]);

        if (! $this->canManageAll($request)) {
            abort_unless($request->user()->competition_id && (int) $data['competition_id'] === (int) $request->user()->competition_id, 403);
        }

        $notification = CompetitionNotification::create([
            ...$data,
            'event_edition_id'=>EventEdition::resolveCurrent()->id,
            'author_id'=>$request->user()->id,
            'published_at'=>now(),
        ]);

        return response()->json($notification->load(['competition:id,title', 'author:id,name']), 201);
    }

    public function destroy(Request $request, CompetitionNotification $notification)
    {
        if (! $this->canManageAll($request)) {
            abort_unless($notification->competition_id === $request->user()->competition_id, 403);
        }
        $notification->delete();

        return response()->noContent();
    }

    public function participantIndex(Request $request)
    {
        $competitionIds = $request->user()->registrations()->pluck('competition_id');

        return CompetitionNotification::with(['competition:id,title', 'author:id,name'])
            ->whereIn('event_edition_id', $request->user()->registrations()->pluck('event_edition_id'))
            ->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('competition_id')->orWhereIn('competition_id', $competitionIds))
            ->latest('published_at')
            ->limit(50)
            ->get();
    }
}
