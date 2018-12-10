<?php

namespace Biigle\Modules\Maia\Http\Controllers\Views;

use Biigle\Role;
use Biigle\Volume;
use Biigle\Project;
use Biigle\LabelTree;
use Illuminate\Http\Request;
use Biigle\Modules\Maia\MaiaJob;
use Biigle\Http\Controllers\Views\Controller;
use Biigle\Modules\Maia\MaiaJobState as State;

class MaiaJobController extends Controller
{
    /**
     * Show the overview of MAIA jobs for a volume
     *
     * @param int $id Volume ID
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $volume = Volume::findOrFail($id);
        $this->authorize('edit-in', $volume);

        if ($volume->hasTiledImages()) {
            abort(404);
        }

        $jobs = MaiaJob::where('volume_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        $hasJobsInProgress = $jobs
            ->whereIn('state_id', [
                State::noveltyDetectionId(),
                State::trainingProposalsId(),
                State::instanceSegmentationId(),
            ])
            ->count() > 0;

        $hasJobsRunning = $jobs
            ->whereIn('state_id', [
                State::noveltyDetectionId(),
                State::instanceSegmentationId(),
            ])
            ->count() > 0;

        $newestJobHasFailed = $jobs->isNotEmpty() ? $jobs[0]->hasFailed() : false;

        return view('maia::index', compact(
            'volume',
            'jobs',
            'hasJobsInProgress',
            'hasJobsRunning',
            'newestJobHasFailed'
        ));
    }

    /**
     * Show a MAIA job
     *
     * @param Request $request
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $job = MaiaJob::findOrFail($id);
        $this->authorize('access', $job);
        $volume = $job->volume;
        $states = State::pluck('id', 'name');

        $user = $request->user();

        if ($job->state_id === State::annotationCandidatesId()) {
            if ($user->can('sudo')) {
                // Global admins have no restrictions.
                $projectIds = $volume->projects()->pluck('id');
            } else {
                // Array of all project IDs that the user and the image have in common
                // and where the user is editor, expert or admin.
                $projectIds = Project::inCommon($user, $image->volume_id, [
                    Role::editorId(),
                    Role::expertId(),
                    Role::adminId(),
                ])->pluck('id');
            }

            // All label trees that are used by all projects which are visible to the
            // user.
            $trees = LabelTree::with('labels')
                ->select('id', 'name')
                ->whereIn('id', function ($query) use ($projectIds) {
                    $query->select('label_tree_id')
                        ->from('label_tree_project')
                        ->whereIn('project_id', $projectIds);
                })
                ->get();
        } else {
            $trees = collect([]);
        }


        return view('maia::show', compact('job', 'volume', 'states', 'trees'));
    }
}
