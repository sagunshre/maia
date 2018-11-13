<?php

namespace Biigle\Modules\Maia\Jobs;

use Biigle\Modules\Maia\MaiaJob;
use Biigle\Modules\Maia\MaiaJobState as State;
use Biigle\Modules\Maia\MaiaAnnotationType as Type;

class NoveltyDetectionResponse extends JobResponse
{
    /**
     * {@inheritdoc}
     */
    protected function getNewAnnotationTypeId()
    {
        return Type::trainingProposalId();
    }

    /**
     * {@inheritdoc}
     */
    protected function getCreatedAnnotations(MaiaJob $job)
    {
        return $job->trainingProposals();
    }

    /**
     * {@inheritdoc}
     */
    protected function updateJobState(MaiaJob $job)
    {
        $job->state_id = State::trainingProposalsId();
        $job->save();
    }
}
