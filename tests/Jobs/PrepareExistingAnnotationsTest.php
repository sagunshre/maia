<?php

namespace Biigle\Tests\Modules\Maia\Jobs;

use Biigle\Modules\Largo\Jobs\GenerateImageAnnotationPatch;
use Biigle\Modules\Maia\Events\MaiaJobContinued;
use Biigle\Modules\Maia\Jobs\NoveltyDetectionResponse;
use Biigle\Modules\Maia\Jobs\PrepareExistingAnnotations;
use Biigle\Modules\Maia\MaiaJobState as State;
use Biigle\Modules\Maia\Notifications\InstanceSegmentationFailed;
use Biigle\Modules\Maia\Notifications\NoveltyDetectionComplete;
use Biigle\Shape;
use Biigle\Tests\ImageAnnotationLabelTest;
use Biigle\Tests\ImageAnnotationTest;
use Biigle\Tests\Modules\Maia\MaiaJobTest;
use Event;
use Illuminate\Support\Facades\Notification;
use Queue;
use TestCase;

class PrepareExistingAnnotationsTest extends TestCase
{
    public function testHandle()
    {
        $a = ImageAnnotationTest::create(['shape_id' => Shape::circleId()]);
        $al1 = ImageAnnotationLabelTest::create(['annotation_id' => $a->id]);
        $al2 = ImageAnnotationLabelTest::create();
        $job = MaiaJobTest::create([
            'volume_id' => $a->image->volume_id,
            'params' => [
                'training_data_method' => 'own_annotations',
            ],
        ]);

        Event::fake();
        Queue::fake();
        (new PrepareExistingAnnotations($job))->handle();
        Event::assertDispatched(MaiaJobContinued::class);
        Queue::assertNotPushed(GenerateImageAnnotationPatch::class);
        $this->assertEquals(1, $job->trainingProposals()->selected()->count());
        $proposal = $job->trainingProposals()->first();
        $this->assertEquals($a->points, $proposal->points);
        $this->assertNotNull($proposal->label_id);
    }

    public function testHandleRestrictLabels()
    {
        $a1 = ImageAnnotationTest::create(['shape_id' => Shape::circleId()]);
        $al1 = ImageAnnotationLabelTest::create(['annotation_id' => $a1->id]);
        // Create only one proposal even though the annotation has two matching labels.
        $al2 = ImageAnnotationLabelTest::create([
            'annotation_id' => $a1->id,
            'label_id' => $al1->label_id,
        ]);
        $a2 = ImageAnnotationTest::create([
            'shape_id' => Shape::circleId(),
            'image_id' => $a1->image_id,
        ]);
        $al3 = ImageAnnotationLabelTest::create(['annotation_id' => $a2->id]);

        $job = MaiaJobTest::create([
            'volume_id' => $a1->image->volume_id,
            'params' => [
                'training_data_method' => 'own_annotations',
                'oa_restrict_labels' => [$al1->label_id],
            ],
        ]);

        (new PrepareExistingAnnotations($job))->handle();
        $this->assertEquals(1, $job->trainingProposals()->selected()->count());
        $proposal = $job->trainingProposals()->first();
        $this->assertEquals($a1->points, $proposal->points);
        $this->assertNull($proposal->score);
        $this->assertNotNull($proposal->label_id);
        $this->assertEquals($al1->label_id, $proposal->label_id);
    }

    public function testHandleIgnoreExistingLabel()
    {
        $a1 = ImageAnnotationTest::create(['shape_id' => Shape::circleId()]);
        $al1 = ImageAnnotationLabelTest::create(['annotation_id' => $a1->id]);

        // Create only one proposal even though the annotation has two matching labels.
        $al2 = ImageAnnotationLabelTest::create([
            'annotation_id' => $a1->id,
            'label_id' => $al1->label_id,
        ]);

        $a2 = ImageAnnotationTest::create([
            'shape_id' => Shape::circleId(),
            'image_id' => $a1->image_id,
        ]);

        $al3 = ImageAnnotationLabelTest::create(['annotation_id' => $a2->id]);

        $job = MaiaJobTest::create([
            'volume_id' => $a1->image->volume_id,
            'params' => [
                'training_data_method' => 'own_annotations',
                'oa_ignore_existing_label' => true,
            ],
        ]);

        (new PrepareExistingAnnotations($job))->handle();
        $this->assertEquals(2, $job->trainingProposals()->selected()->count());
        $proposal = $job->trainingProposals()->first();
        $this->assertNull($proposal->label_id);
        $this->assertEquals($a1->points, $proposal->points);
        $this->assertNull($proposal->score);
    }

    public function testHandleBothIgnoreExistingLabelAndRestrictLabels()
    {
        $a1 = ImageAnnotationTest::create(['shape_id' => Shape::circleId()]);
        $al1 = ImageAnnotationLabelTest::create(['annotation_id' => $a1->id]);
        // Create only one proposal even though the annotation has two matching labels.
        $al2 = ImageAnnotationLabelTest::create([
            'annotation_id' => $a1->id,
            'label_id' => $al1->label_id,
        ]);
        $a2 = ImageAnnotationTest::create([
            'shape_id' => Shape::circleId(),
            'image_id' => $a1->image_id,
        ]);
        $al3 = ImageAnnotationLabelTest::create(['annotation_id' => $a2->id]);

        $job1 = MaiaJobTest::create([
            'volume_id' => $a1->image->volume_id,
            'params' => [
                'training_data_method' => 'own_annotations',
                'oa_ignore_existing_label' => true,
            ],
        ]);

        $job2 = MaiaJobTest::create([
            'volume_id' => $a1->image->volume_id,
            'params' => [
                'training_data_method' => 'own_annotations',
                'oa_restrict_labels' => [$al1->label_id],
                'oa_ignore_existing_label' => true,
            ],
        ]);

        (new PrepareExistingAnnotations($job1))->handle();
        $this->assertEquals(2, $job1->trainingProposals()->selected()->count());
        $proposal1 = $job1->trainingProposals()->first();
        $this->assertNull($proposal1->label_id);
        $this->assertEquals($a1->points, $proposal1->points);
        $this->assertNull($proposal1->score);

        (new PrepareExistingAnnotations($job2))->handle();
        $this->assertEquals(1, $job2->trainingProposals()->selected()->count());
        $proposal2 = $job2->trainingProposals()->first();
        $this->assertNull($proposal2->label_id);
        $this->assertEquals($a1->points, $proposal2->points);
        $this->assertNull($proposal2->score);
    }


    public function testMultipleAnnotationLables()
    {
        $a1 = ImageAnnotationTest::create([
            'shape_id' => Shape::pointId(),
            'points' => [10, 20],
        ]);

        $l1 = ImageAnnotationLabelTest::create([
            'annotation_id' => $a1->id
        ]);

        $l2 = ImageAnnotationLabelTest::create([
            'annotation_id' => $a1->id
        ]);

        $job = MaiaJobTest::create([
            'volume_id' => $a1->image->volume_id,
            'params' => [
                'training_data_method' => 'own_annotations'
            ],
        ]);

        (new PrepareExistingAnnotations($job))->handle();
        $this->assertEquals(1, $job->trainingProposals()->selected()->count());

        $proposal = $job->trainingProposals()->first();

        $this->assertNotNull($proposal->label_id);
        $this->assertEquals($l1->label_id, $proposal->label_id);
    }

    public function testHandleShapeConversion()
    {
        $a1 = ImageAnnotationTest::create([
            'shape_id' => Shape::pointId(),
            'points' => [10, 20],
        ]);

        ImageAnnotationLabelTest::create([
          'annotation_id' => $a1->id,
        ]);

        $a2 = ImageAnnotationTest::create([
            'shape_id' => Shape::rectangleId(),
            'points' => [10, 10, 100, 10, 100, 100, 10, 100],
            'image_id' => $a1->image_id,
        ]);

        ImageAnnotationLabelTest::create([
          'annotation_id' => $a2->id,
        ]);

        $a3 = ImageAnnotationTest::create([
            'shape_id' => Shape::circleId(),
            'points' => [10, 20, 30],
            'image_id' => $a1->image_id,
        ]);

        ImageAnnotationLabelTest::create([
          'annotation_id' => $a3->id,
        ]);

        $a4 = ImageAnnotationTest::create([
            'shape_id' => Shape::lineId(),
            'points' => [10, 10, 20, 20],
            'image_id' => $a1->image_id,
        ]);

        ImageAnnotationLabelTest::create([
          'annotation_id' => $a4->id,
        ]);

        $a5 = ImageAnnotationTest::create([
            'shape_id' => Shape::polygonId(),
            'points' => [10, 10, 20, 20, 0, 20],
            'image_id' => $a1->image_id,
        ]);

        ImageAnnotationLabelTest::create([
          'annotation_id' => $a5->id,
        ]);

        $job = MaiaJobTest::create([
            'volume_id' => $a1->image->volume_id,
            'params' => [
                'training_data_method' => 'own_annotations',
            ],
        ]);

        (new PrepareExistingAnnotations($job))->handle();
        $this->assertEquals(5, $job->trainingProposals()->selected()->count());
        $proposals = $job->trainingProposals;

        $this->assertEquals(Shape::circleId(), $proposals[0]->shape_id);
        // Points get a default radius of 50 px.
        $this->assertEquals([10, 20, 50], $proposals[0]->points);

        $this->assertEquals(Shape::circleId(), $proposals[1]->shape_id);
        $this->assertEquals([55, 55, 63.64], $proposals[1]->points);

        $this->assertEquals(Shape::circleId(), $proposals[2]->shape_id);
        $this->assertEquals([10, 20, 30], $proposals[2]->points);

        $this->assertEquals(Shape::circleId(), $proposals[3]->shape_id);
        $this->assertEquals([15, 15, 7.07], $proposals[3]->points);

        $this->assertEquals(Shape::circleId(), $proposals[4]->shape_id);
        $this->assertEquals([10, 15, 11.18], $proposals[4]->points);

        $this->assertNotNull($proposals->first()->label_id);
    }

    public function testHandleSkipNdNoAnnotations()
    {
        $job = MaiaJobTest::create();

        Notification::fake();
        (new PrepareExistingAnnotations($job))->handle();
        Notification::assertSentTo($job->user, InstanceSegmentationFailed::class);
        $this->assertEquals(0, $job->trainingProposals()->count());
        $this->assertEquals(State::failedInstanceSegmentationId(), $job->fresh()->state_id);
        $this->assertNotEmpty($job->error['message']);
    }

    public function testHandleShowTrainingProposals()
    {
        $a = ImageAnnotationTest::create(['shape_id' => Shape::circleId()]);
        $al1 = ImageAnnotationLabelTest::create(['annotation_id' => $a->id]);
        $al2 = ImageAnnotationLabelTest::create();
        $job = MaiaJobTest::create([
            'volume_id' => $a->image->volume_id,
            'params' => [
                'training_data_method' => 'own_annotations',
                'oa_show_training_proposals' => true,
            ],
        ]);

        Event::fake();
        Queue::fake();
        (new PrepareExistingAnnotations($job))->handle();
        Event::assertNotDispatched(MaiaJobContinued::class);
        Queue::assertPushed(NoveltyDetectionResponse::class);
        $this->assertEquals(0, $job->trainingProposals()->selected()->count());
        $this->assertEquals(1, $job->trainingProposals()->count());
        $proposal = $job->trainingProposals()->first();
        $this->assertEquals($a->points, $proposal->points);

        $this->assertNotNull($proposal->label_id);
    }
}
