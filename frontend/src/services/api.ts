/**
 * Barrel re-export for @/services/api alias.
 * All modules using `import api from '@/services/api'` resolve here.
 * The underlying client is the shared Axios instance at @/api/client.
 */
export { default } from '@/api/client'
