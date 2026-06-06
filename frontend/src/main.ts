import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
// import 'primeicons/primeicons.css'  // disabled: package has broken exports in this env

import App    from './App.vue'
import router from './router'

import './assets/main.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(PrimeVue, {
  theme: {
    preset: Aura,
    options: {
      prefix:     'p',
      darkModeSelector: '.dark',
      cssLayer: false,
    },
  },
})

app.mount('#app')
