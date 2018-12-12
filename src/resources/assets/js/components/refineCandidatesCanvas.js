/**
 * A variant of the annotation canvas used for the refinement of annotation candidates.
 *
 * @type {Object}
 */
biigle.$component('maia.components.refineCandidatesCanvas', {
    mixins: [biigle.$require('maia.components.refineCanvas')],
    props: {
        convertedAnnotations: {
            type: Array,
            default: function () {
                return [];
            },
        },
    },
    methods: {
        createConvertedAnnotationsLayer: function () {
            this.convertedAnnotationFeatures = new ol.Collection();
            this.convertedAnnotationSource = new ol.source.Vector({
                features: this.convertedAnnotationFeatures
            });

            var fakeFeature = new ol.Object();
            fakeFeature.set('color', '999999');

            this.convertedAnnotationLayer = new ol.layer.Vector({
                source: this.convertedAnnotationSource,
                // Should be below unselected candidates which are at index 99. Else
                // the attach label interaction doesn't work.
                zIndex: 98,
                updateWhileAnimating: true,
                updateWhileInteracting: true,
                style: biigle.$require('annotations.stores.styles').features(fakeFeature),
            });
        },
    },
    watch: {
        convertedAnnotations: function (annotations) {
            this.refreshAnnotationSource(annotations, this.convertedAnnotationSource);
        },
    },
    created: function () {
        this.createConvertedAnnotationsLayer();
        this.map.addLayer(this.convertedAnnotationLayer);
    },
});
