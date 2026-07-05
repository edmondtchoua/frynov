<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 16 16"
    fill="none"
    class="icon"
    :role="title ? 'img' : undefined"
    :aria-label="title || undefined"
    :aria-hidden="title ? undefined : 'true'"
  >
    <title v-if="title">{{ title }}</title>
    <component
      :is="shape.is"
      v-for="(shape, i) in shapes"
      :key="i"
      v-bind="shape.attrs"
      stroke="currentColor"
      :stroke-width="strokeWidth"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
</template>

<script setup lang="ts">
/**
 * Icon — single source of truth for the small line icons reused across the app (audit UX-11).
 * Replaces hand-drawn inline <svg> blocks with `<Icon name="plus" />`. Icons are defined as a
 * static whitelist of SVG primitives (no v-html), all on a 16×16 grid with currentColor stroke.
 * Decorative by default (aria-hidden); pass `title` to expose it as an accessible image.
 */
import { computed } from 'vue'

type Primitive = { tag: 'path' | 'circle' | 'line' | 'rect'; [attr: string]: string | number }

const props = withDefaults(
  defineProps<{ name: string; size?: number | string; strokeWidth?: number; title?: string }>(),
  { size: 16, strokeWidth: 1.5 },
)

// 16×16 viewBox. Keep names semantic (action, not appearance).
const REGISTRY: Record<string, Primitive[]> = {
  plus:    [{ tag: 'path', d: 'M8 3v10' }, { tag: 'path', d: 'M3 8h10' }],
  search:  [{ tag: 'circle', cx: 7, cy: 7, r: 4.5 }, { tag: 'path', d: 'M14 14l-3.4-3.4' }],
  view:    [{ tag: 'circle', cx: 8, cy: 8, r: 2.5 }, { tag: 'path', d: 'M1 8c1.6-3.6 4-5.5 7-5.5S13.4 4.4 15 8c-1.6 3.6-4 5.5-7 5.5S2.6 11.6 1 8z' }],
  edit:    [{ tag: 'path', d: 'M11.3 2.6l2.1 2.1L6 12.1l-2.8.7.7-2.8z' }],
  close:   [{ tag: 'path', d: 'M4 4l8 8' }, { tag: 'path', d: 'M12 4l-8 8' }],
  trash:   [{ tag: 'path', d: 'M3 4.5h10' }, { tag: 'path', d: 'M6 4.5V3h4v1.5' }, { tag: 'path', d: 'M4.6 4.5l.5 8a1 1 0 001 .95h3.8a1 1 0 001-.95l.5-8' }],
  check:   [{ tag: 'path', d: 'M3.5 8.5l3 3 6-7' }],
  download:[{ tag: 'path', d: 'M8 2v8' }, { tag: 'path', d: 'M4.5 7L8 10.5 11.5 7' }, { tag: 'path', d: 'M3 13.5h10' }],
  filter:  [{ tag: 'path', d: 'M2.5 3.5h11L9.3 8.6v3.6l-2.6 1.3V8.6z' }],
  'chevron-left':  [{ tag: 'path', d: 'M10 3.5L5.5 8l4.5 4.5' }],
  'chevron-right': [{ tag: 'path', d: 'M6 3.5L10.5 8 6 12.5' }],
}

const shapes = computed(() =>
  (REGISTRY[props.name] ?? []).map(({ tag, ...attrs }) => ({ is: tag, attrs })),
)
</script>

<style scoped>
.icon { display: inline-block; vertical-align: middle; flex-shrink: 0; }
</style>
