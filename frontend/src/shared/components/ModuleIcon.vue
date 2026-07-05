<template>
  <!-- Safe, componentized module icon: static frontend-owned SVG paths keyed by
       module code. Replaces v-html rendering of DB-supplied `icon_svg` (XSS-safe). -->
  <svg
    viewBox="0 0 24 24"
    width="100%"
    height="100%"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <path v-for="(d, i) in paths" :key="i" :d="d" />
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ code?: string | null }>()

const ICONS: Record<string, string[]> = {
  dashboard:     ['M3 3h7v7H3z', 'M14 3h7v7h-7z', 'M14 14h7v7h-7z', 'M3 14h7v7H3z'],
  catalog:       ['M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z', 'M7.5 7.5h.01'],
  inventory:     ['M3 7l9-4 9 4-9 4z', 'M3 7v10l9 4 9-4V7', 'M12 11v10'],
  orders:        ['M3 4h2l2 12h12', 'M7 7h14l-2 6H8', 'M8 19a1.5 1.5 0 1 0 0 .01', 'M17 19a1.5 1.5 0 1 0 0 .01'],
  customers:     ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M4 20a8 8 0 0 1 16 0'],
  payments:      ['M2 6h20v12H2z', 'M2 10h20', 'M6 15h4'],
  delivery:      ['M3 6h11v9H3z', 'M14 9h4l3 3v3h-7', 'M7 18a1.6 1.6 0 1 0 0 .01', 'M18 18a1.6 1.6 0 1 0 0 .01'],
  suppliers:     ['M3 21V9l6 4V9l6 4V9l6 4v8z', 'M3 21h18'],
  import_export: ['M4 7h11', 'M11 3l4 4-4 4', 'M20 17H9', 'M13 13l-4 4 4 4'],
  reports:       ['M4 20V10', 'M10 20V4', 'M16 20v-7', 'M2 20h20'],
  marketplace:   ['M3 9l1-5h16l1 5', 'M4 9v11h16V9', 'M3 9h18', 'M9 20v-6h6v6'],
  pos:           ['M5 3h14v18l-3-2-2 2-2-2-2 2-3-2z', 'M8 7h8', 'M8 11h8'],
  _default:      ['M4 4h16v16H4z'],
}

const paths = computed(() => ICONS[props.code ?? ''] ?? ICONS._default)
</script>
