<template>
    <div>
        <panel :panel="{ name: morph.singularLabel, fields: standardFields}" />
        <template v-for="({type, fields}) in relationFields">
            <resource-index v-for="(relationField, index) in fields"
                            :key="index"
                            :field="relationField"
                            :resource-name="relationField.resourceName"
                            :via-resource="relationField.inlineMorphTo.viaResource"
                            :via-resource-id="relationField.inlineMorphTo.viaResourceId"
                            :via-relationship="relationField[`${type}Relationship`]"
                            :relationship-type="type"
                            :load-cards="false"
            />
        </template>
    </div>
</template>

<script>

export default {
    props: [
        'resource',
        'resourceName',
        'resourceId',
        'field'
    ],

    computed: {
        relationFields() {
            return [
                {
                    type: 'hasOne',
                    fields: this.morph.fields.filter(field => field.component === 'has-one-field')
                },
                {
                    type: 'hasMany',
                    fields: this.morph.fields.filter(field => field.component === 'has-many-field')
                },
                {
                    type: 'belongsToMany',
                    fields: this.morph.fields.filter(field => field.component === 'belongs-to-many-field')
                }
            ]
        },

        standardFields() {
            return this.morph.fields.filter(field => ![
                'has-one-field',
                'has-many-field',
                'belongs-to-many-field'
            ].includes(field.component))
        },

        morph() {
            return this.field.morphToTypes.find(resource => resource.value === this.field.morphToType)
        }
    },
}
</script>
