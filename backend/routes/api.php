<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ManagementController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\JudgingController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\VenueController;
use App\Http\Controllers\Api\CompetitionTypeController;
use App\Http\Controllers\Api\EventEditionController;
use Illuminate\Support\Facades\Route;

Route::get('/competitions', [PublicController::class, 'competitions']);
Route::get('/editions', [EventEditionController::class, 'publicIndex']);
Route::get('/competition-types', [PublicController::class, 'competitionTypes']);
Route::get('/competitions/{slug}', [PublicController::class, 'competition']);
Route::get('/venues', [PublicController::class, 'venues']);
Route::get('/venues/{slug}', [PublicController::class, 'venue']);
Route::get('/competitions/{slug}/tournament', [TournamentController::class, 'publicView']);
Route::get('/competitions/{slug}/schedule', [ScheduleController::class, 'publicView']);
Route::get('/content/home-hero', [ContentController::class, 'hero']);
Route::get('/content/landing-extras', [ContentController::class, 'landingExtras']);
Route::get('/content/data-consent', [ContentController::class, 'dataConsent']);
Route::get('/content/general-documents', [ContentController::class, 'generalDocuments']);
Route::post('/registrations', [PublicController::class, 'registerParticipant']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);

Route::middleware('api.auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::middleware('role:participant')->group(function () {
        Route::get('/participant/registrations', [ParticipantController::class, 'index']);
        Route::get('/participant/registrations/{registration}', [ParticipantController::class, 'show']);
        Route::post('/participant/registrations/{registration}', [ParticipantController::class, 'update']);
        Route::post('/participant/registrations/{registration}/team', [ParticipantController::class, 'updateTeam']);
        Route::put('/participant/registrations/{registration}/team-profile', [ParticipantController::class, 'updateTeamProfile']);
        Route::put('/participant/registrations/{registration}/teacher', [ParticipantController::class, 'updateTeacher']);
        Route::put('/participant/registrations/{registration}/member-slots/{memberOrder}', [ParticipantController::class, 'upsertMember']);
        Route::put('/participant/registrations/{registration}/official-slots/{officialOrder}', [ParticipantController::class, 'upsertOfficial']);
        Route::post('/participant/registrations/{registration}/member-slots/{memberOrder}/documents', [ParticipantController::class, 'uploadMemberSlotDocuments']);
        Route::post('/participant/registrations/{registration}/members/{registrationMember}/documents', [ParticipantController::class, 'uploadMemberDocuments']);
        Route::post('/participant/registrations/{registration}/documents', [ParticipantController::class, 'uploadDocuments']);
        Route::post('/participant/registrations/{registration}/work-submission', [ParticipantController::class, 'submitWork']);
        Route::get('/participant/notifications', [NotificationController::class, 'participantIndex']);
        Route::get('/participant/judging-results', [JudgingController::class, 'participantResults']);
    });

    Route::get('/manage/dashboard', [ManagementController::class, 'dashboard'])->middleware('permission:dashboard.view');
    Route::get('/manage/editions', [EventEditionController::class, 'index'])->middleware('role:super_admin');
    Route::post('/manage/editions', [EventEditionController::class, 'store'])->middleware('role:super_admin');
    Route::put('/manage/editions/{edition}', [EventEditionController::class, 'update'])->middleware('role:super_admin');
    Route::post('/manage/editions/{edition}/activate', [EventEditionController::class, 'activate'])->middleware('role:super_admin');
    Route::get('/manage/competitions', [ManagementController::class, 'competitions'])->middleware('permission:competitions.view');
    Route::get('/manage/venues', [VenueController::class, 'index'])->middleware('permission:competitions.view');
    Route::get('/manage/competition-types', [CompetitionTypeController::class, 'index'])->middleware('permission:competitions.view');
    Route::get('/manage/city-staff', [ManagementController::class, 'cityStaff'])->middleware('permission:competitions.view');
    Route::put('/manage/competitions/{competition}', [ManagementController::class, 'updateCompetition'])->middleware('permission:competitions.edit,competitions.manage');
    Route::patch('/manage/competitions/{competition}/guides', [ManagementController::class, 'updateGuides'])->middleware('permission:competitions.view');
    Route::post('/manage/competitions/{competition}/downloadable-documents', [ManagementController::class, 'updateDownloadableDocuments'])->middleware('permission:competitions.edit,competitions.manage');
    Route::patch('/manage/competitions/{competition}/format', [ManagementController::class, 'updateFormat'])->middleware('permission:competitions.format');
    Route::get('/manage/registrations', [ManagementController::class, 'registrations'])->middleware('permission:registrations.view');
    Route::get('/manage/registration-competitions', [ManagementController::class, 'registrationCompetitions'])->middleware('permission:registrations.view');
    Route::get('/manage/registrations/export', [ManagementController::class, 'export'])->middleware('permission:registrations.export');
    Route::get('/manage/registrations/{registration}', [ManagementController::class, 'registration'])->middleware('permission:registrations.view');
    Route::patch('/manage/registrations/{registration}/review', [ManagementController::class, 'review'])->middleware('permission:registrations.review');
    Route::patch('/manage/registrations/{registration}/payment-verification', [ManagementController::class, 'verifyPayment'])->middleware('permission:registrations.review');
    Route::patch('/manage/registration-members/{registrationMember}/nisn-verification', [ManagementController::class, 'verifyMemberNisn'])->middleware('permission:registrations.review');
    Route::delete('/manage/registrations/{registration}', [ManagementController::class, 'destroyRegistration'])->middleware('role:super_admin');
    Route::get('/manage/content/home-hero', [ContentController::class, 'manageHero'])->middleware('permission:content.manage');
    Route::post('/manage/content/home-hero', [ContentController::class, 'updateHero'])->middleware('permission:content.manage');
    Route::get('/manage/content/landing-extras', [ContentController::class, 'manageLandingExtras'])->middleware('permission:content.manage');
    Route::post('/manage/content/landing-extras', [ContentController::class, 'updateLandingExtras'])->middleware('permission:content.manage');
    Route::get('/manage/content/data-consent', [ContentController::class, 'manageDataConsent'])->middleware('permission:content.manage');
    Route::post('/manage/content/data-consent', [ContentController::class, 'updateDataConsent'])->middleware('permission:content.manage');
    Route::get('/manage/general-documents', [ContentController::class, 'manageGeneralDocuments'])->middleware('role:super_admin,pic');
    Route::post('/manage/general-documents', [ContentController::class, 'updateGeneralDocuments'])->middleware('role:super_admin,pic');
    Route::middleware('permission:notifications.manage')->group(function () {
        Route::get('/manage/notifications', [NotificationController::class, 'index']);
        Route::post('/manage/notifications', [NotificationController::class, 'store']);
        Route::delete('/manage/notifications/{notification}', [NotificationController::class, 'destroy']);
    });
    Route::middleware('permission:judging.manage')->group(function () {
        Route::get('/manage/judging', [JudgingController::class, 'manage']);
        Route::put('/manage/judging/competitions/{competition}/criteria', [JudgingController::class, 'configure']);
        Route::patch('/manage/judging/registrations/{registration}/verify', [JudgingController::class, 'verifyWork']);
        Route::post('/manage/judging/registrations/{registration}/assign', [JudgingController::class, 'assign']);
        Route::delete('/manage/judging/assignments/{assignment}', [JudgingController::class, 'unassign']);
        Route::post('/manage/judging/competitions/{competition}/lock', [JudgingController::class, 'lock']);
        Route::post('/manage/judging/competitions/{competition}/announce', [JudgingController::class, 'announce']);
    });
    Route::middleware('permission:judging.score')->group(function () {
        Route::get('/judge/assignments', [JudgingController::class, 'judgeAssignments']);
        Route::put('/judge/assignments/{assignment}/score', [JudgingController::class, 'score']);
    });
    Route::middleware('permission:tournaments.manage')->group(function () {
        Route::get('/manage/tournaments', [TournamentController::class, 'manage']);
        Route::post('/manage/tournaments/competitions/{competition}/draw', [TournamentController::class, 'start']);
        Route::put('/manage/tournaments/matches/{match}', [TournamentController::class, 'updateMatch']);
        Route::post('/manage/tournaments/draws/{draw}/knockout', [TournamentController::class, 'generateKnockout']);
        Route::post('/manage/tournaments/draws/{draw}/lock', [TournamentController::class, 'lock']);
        Route::get('/manage/schedules', [ScheduleController::class, 'manage']);
        Route::put('/manage/schedules/competitions/{competition}/venues', [ScheduleController::class, 'configureVenues']);
        Route::put('/manage/schedules/matches/{match}', [ScheduleController::class, 'updateMatch']);
        Route::post('/manage/schedules/competitions/{competition}/blocks', [ScheduleController::class, 'storeBlock']);
        Route::put('/manage/schedules/blocks/{block}', [ScheduleController::class, 'updateBlock']);
        Route::delete('/manage/schedules/blocks/{block}', [ScheduleController::class, 'destroyBlock']);
    });

    Route::middleware('permission:competitions.manage')->group(function () {
         Route::post('/manage/venues', [VenueController::class, 'store']);
         Route::put('/manage/venues/{venue}', [VenueController::class, 'update']);
         Route::put('/manage/venues/{venue}/staff-assignments', [VenueController::class, 'updateStaffAssignments']);
         Route::delete('/manage/venues/{venue}', [VenueController::class, 'destroy']);
        Route::post('/manage/competition-types', [CompetitionTypeController::class, 'store']);
        Route::put('/manage/competition-types/{competitionType}', [CompetitionTypeController::class, 'update']);
        Route::delete('/manage/competition-types/{competitionType}', [CompetitionTypeController::class, 'destroy']);
        Route::post('/manage/competitions', [ManagementController::class, 'storeCompetition']);
        Route::delete('/manage/competitions/{competition}', [ManagementController::class, 'destroyCompetition']);
        Route::get('/manage/pics', [ManagementController::class, 'pics']);
        Route::post('/manage/pics', [ManagementController::class, 'storePic']);
        Route::put('/manage/pics/{user}', [ManagementController::class, 'updatePic']);
        Route::delete('/manage/pics/{user}', [ManagementController::class, 'destroyPic']);
        Route::get('/manage/competitions/{competition}/pics', [ManagementController::class, 'competitionPics']);
        Route::put('/manage/competitions/{competition}/pics', [ManagementController::class, 'assignCompetitionPics']);
    });
    Route::middleware('permission:accounts.manage')->group(function () {
        Route::get('/manage/accounts', [ManagementController::class, 'accounts']);
        Route::post('/manage/accounts', [ManagementController::class, 'storeAccount']);
        Route::put('/manage/accounts/{user}', [ManagementController::class, 'updateAccount']);
        Route::delete('/manage/accounts/{user}', [ManagementController::class, 'destroyAccount']);
    });
    Route::get('/manage/roles', [RoleController::class, 'index'])->middleware('permission:roles.manage,accounts.manage');
    Route::middleware('permission:roles.manage')->group(function () {
        Route::post('/manage/roles', [RoleController::class, 'store']);
        Route::put('/manage/roles/{accessRole}', [RoleController::class, 'update']);
        Route::delete('/manage/roles/{accessRole}', [RoleController::class, 'destroy']);
    });
});
