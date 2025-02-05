<?php

namespace Biigle\Modules\Maia\Traits;

use Biigle\ImageAnnotation;

trait QueriesExistingAnnotations
{
    /**
     * Get the query for the annotations to convert.
     *
     * @param int $volumeId
     * @param array $restrictLabels
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getExistingAnnotationsQuery($volumeId, $restrictLabels = [], $ignoreLabels = false)
    {
        $includeImageAnnotationLabel = !$ignoreLabels || !empty($restrictLabels);

        return ImageAnnotation::join('images', 'image_annotations.image_id', '=', 'images.id')
            ->where('images.volume_id', $volumeId)
            ->when($includeImageAnnotationLabel, function ($query) {
              return $query->join('image_annotation_labels', 'image_annotation_labels.annotation_id', '=', 'image_annotations.id');
            })
            ->when(!empty($restrictLabels), function ($query) use ($restrictLabels) {
                return $query->whereIn('image_annotation_labels.label_id', $restrictLabels);
            });
    }
}
