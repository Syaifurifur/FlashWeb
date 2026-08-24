<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompetitionNotification;
use App\Models\CompetitionSession;
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
        $editionId = EventEdition::resolveCurrent()->id;
        $query = CompetitionNotification::where('event_edition_id', $editionId)->with(['competition:id,title', 'author:id,name'])->latest('published_at');
        if (! $this->canManageAll($request)) {
            $query->whereIn('competition_id', $request->user()->manageableCompetitionsQuery($editionId)->select('id'));
        }

        return $query->limit(100)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'competition_id'=>'nullable|exists:competitions,id',
            'competition_session_id'=>'nullable|exists:competition_sessions,id',
            'title'=>'required|string|max:160',
            'message'=>'required|string|max:5000',
        ]);

        if (! empty($data['competition_session_id'])) {
            $session=CompetitionSession::findOrFail($data['competition_session_id']);
            abort_unless(!empty($data['competition_id']) && (int)$data['competition_id']===$session->competition_id,422,'Kota pelaksanaan tidak sesuai dengan lomba yang dipilih.');
        }

        if (! $this->canManageAll($request)) {
            abort_unless(
                ! empty($data['competition_id'])
                && $request->user()->manageableCompetitionsQuery(EventEdition::resolveCurrent()->id)->whereKey($data['competition_id'])->exists(),
                403
            );
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
            abort_unless(
                $notification->competition_id
                && $request->user()->manageableCompetitionsQuery(EventEdition::resolveCurrent()->id)->whereKey($notification->competition_id)->exists(),
                403
            );
        }
        $notification->delete();

        return response()->noContent();
    }

    public function participantIndex(Request $request)
    {
        $competitionIds = $request->user()->registrations()->pluck('competition_id');
        $sessionIds = $request->user()->registrations()->whereNotNull('competition_session_id')->pluck('competition_session_id');

        return CompetitionNotification::with(['competition:id,title', 'author:id,name'])
            ->whereIn('event_edition_id', $request->user()->registrations()->pluck('event_edition_id'))
            ->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('competition_id')->orWhere(fn($scoped)=>$scoped
                ->whereIn('competition_id',$competitionIds)
                ->where(fn($session)=>$session->whereNull('competition_session_id')->orWhereIn('competition_session_id',$sessionIds))))
            ->latest('published_at')
            ->limit(50)
            ->get();
    }
}
