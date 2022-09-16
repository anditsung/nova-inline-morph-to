<template>
    <div>
        <default-field :field="field"
                       :errors="errors"
                       :show-help-text="field.helpText != null"
        >
            <select slot="field"
                    v-if="hasMorphToTypes"
                    :id="field.attribute"
                    :dusk="field.attribute"
                    :value="value"
                    @change="handleChange"
                    :class="errorClasses"
                    :disabled="editingExistingResource"
                    class="w-full form-control form-select"
            >
                <option value="" selected disabled>
                    {{ __('Choose Resource') }}
                </option>

                <option v-for="(option, index) in morphToTypes"
                        :key="index"
                        :value="option.value"
                        :selected="value === option.value"
                >
                    {{ option.singularLabel }}
                </option>

            </select>
            <label slot="field" v-else class="flex items-center select-none mt-3">
                {{ __('There are no available options for this resource.') }}
            </label>
        </default-field>

        <template v-if="value">
            <component v-for="(field, index) in selectedResource.fields"
                       :class="{
                            'remove-bottom-border': index === selectedResource.fields.length - 1,
                       }"
                       :id="field.attribute"
                       :is="`form-${field.component}`"
                       :key="index"
                       :errors="errors"
                       :resource-name="selectedResource.value"
                       :field="field"
                       :show-help-text="field.helpText != null"
            />
        </template>

    </div>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova'

export default {

    mixins: [
        HandlesValidationErrors,
        FormField,
    ],

    props: [ 'resourceName', 'resourceId', 'field' ],

    data: () => ({
        value: null,
        selectedResource: null,
    }),

    mounted() {
        if (! this.editingExistingResource && this.field.defaultResource) {
            this.selectedResource = this.morphToTypes.find(resource => resource.value === this.field.defaultResource)
            this.value = this.field.defaultResource
        }
    },

    methods: {
        /*
         * Set the initial, internal value for the field.
         */
        setInitialValue() {
            this.selectedResource = this.morphToTypes.find(resource => resource.value === this.field.morphToType)
            this.value = this.field.morphToType || null
        },

        /**
         * Provide a function that fills a passed FormData object with the
         * field's internal value attribute. Here we are forcing there to be a
         * value sent to the server instead of the default behavior of
         * `this.value || ''` to avoid loose-comparison issues if the keys
         * are truthy or falsey
         */
        async fill(formData) {
            formData.append(this.field.attribute, this.selectedResource.value)

            this.$children.forEach(component => {
                if (component.field.attribute !== this.field.attribute) {
                    component.field.fill(formData)
                }
            })
        },

        /**
         * Handle the selection change event.
         */
        handleChange(e) {
            this.value = e.target.value
            this.selectedResource = this.morphToTypes.find(type => type.value === this.value)

            Nova.$emit(this.field.attribute + '-change', this.value)
        },
    },

    computed: {
        /**
         * Determine if an existing resource is being updated.
         */
        editingExistingResource() {
            return Boolean(this.field.morphToId && this.field.morphToType)
        },

        /**
         * Determine whether there are any morph to types.
         */
        hasMorphToTypes() {
            return this.morphToTypes.length > 0
        },

        morphToTypes() {
            return this.field.morphToTypes
        },
    },
}
</script>
