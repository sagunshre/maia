<?php

namespace Biigle\Modules\Maia;

use Biigle\Image;
use Biigle\Shape;
use Biigle\Traits\HasPointsAttribute;
use Illuminate\Database\Eloquent\Model;
use Biigle\Contracts\Annotation as AnnotationContract;

abstract class MaiaAnnotation extends Model implements AnnotationContract
{
    use HasPointsAttribute;

    /**
     * Don't maintain timestamps for this model.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'points' => 'array',
        'score' => 'float',
    ];

    /**
     * The image, this MAIA annotation belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function image()
    {
        return $this->belongsTo(Image::class);
    }

    /**
     * The shape of this MAIA annotation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shape()
    {
        return $this->belongsTo(Shape::class);
    }

    /**
     * The MAIA job, this MAIA anotation belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function job()
    {
        return $this->belongsTo(MaiaJob::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * {@inheritdoc}
     */
    public function getShape(): Shape
    {
        return $this->shape;
    }

    /**
     * {@inheritdoc}
     */
    public function getImage(): Image
    {
        return $this->image;
    }

    /**
     * Get the path to the annotation patch file.
     *
     * @return string
     */
    public function getPatchPath(): string
    {
        $prefix = config('maia.patch_storage');
        $format = config('largo.patch_format');
        $name = $this->getPatchFilename();

        return "{$prefix}/{$this->job_id}/{$name}.{$format}";
    }

    /**
     * Get the file name (without extension) of the annotation patch file.
     *
     * @return string
     */
    abstract protected function getPatchFilename(): string;
}
