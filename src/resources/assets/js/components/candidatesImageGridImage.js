/**
 * A variant of the image grid image used for the selection of MAIA annotation candidates.
 *
 * @type {Object}
 */
biigle.$component('maia.components.candidatesImageGridImage', {
    mixins: [biigle.$require('volumes.components.imageGridImage')],
    template: '<figure class="image-grid__image" :class="classObject" :title="title">' +
        '<div v-if="showIcon" class="image-icon">' +
            '<i class="fas" :class="iconClass"></i>' +
        '</div>' +
        '<img @click="toggleSelect" :src="url || emptyUrl">' +
        '<div v-if="showAnnotationLink" class="image-buttons">' +
            '<a :href="showAnnotationLink" target="_blank" class="image-button" title="Show the annotation in the annotation tool">' +
                '<span class="fa fa-external-link-square-alt" aria-hidden="true"></span>' +
            '</a>' +
        '</div>' +
    '</figure>',
    computed: {
        showAnnotationLink: function () {
            return false;
        },
        selected: function () {
            return this.$parent.selectedCandidateIds.hasOwnProperty(this.image.id);
        },
        title: function () {
            if (this.selectable) {
                return this.selected ? 'Remove selected label' : 'Assign selected label';
            }
        },
    },
    methods: {
        getBlob: function () {
            return biigle.$require('maia.api.annotationCandidate').getFile({id: this.image.id});
        },
    },
});
