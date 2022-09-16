import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'

import NewFormField from "./components/New/FormField"
import NewIndexField from "./components/New/IndexField"
import NewDetailField from "./components/New/DetailField"

Nova.booting((Vue, router, store) => {
    Vue.component('index-inline-morph-to', IndexField)
    Vue.component('detail-inline-morph-to', DetailField)
    Vue.component('form-inline-morph-to', FormField)

    Vue.component('index-new-morph-to', NewIndexField)
    Vue.component('form-new-morph-to', NewFormField)
    Vue.component('detail-new-morph-to', NewDetailField)
})
