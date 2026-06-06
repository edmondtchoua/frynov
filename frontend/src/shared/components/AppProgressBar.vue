<template>
  <Transition name="nprogress">
    <div v-if="active" class="nprogress" :class="{ 'nprogress--error': error }" aria-hidden="true">
      <div
        class="nprogress__bar"
        :style="{ transform: `scaleX(${progress / 100})` }"
      />
      <!-- Glare / peg highlight at the right edge of the bar -->
      <div
        class="nprogress__peg"
        :style="{ left: `${progress}%` }"
      />
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { useProgress } from '@/composables/useProgress'

const { active, progress, error } = useProgress()
</script>

<style scoped>
.nprogress {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  z-index: 9999;
  pointer-events: none;
  overflow: visible;
}

/* The filled portion — uses scaleX transform for GPU-accelerated animation */
.nprogress__bar {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  transform-origin: left center;
  background: linear-gradient(90deg, #10b981, #3b82f6);
  transition: transform 0.12s ease;
  border-radius: 0 3px 3px 0;
}

/* Error state */
.nprogress--error .nprogress__bar {
  background: linear-gradient(90deg, #ef4444, #f97316);
}

/* Glare dot at the leading edge */
.nprogress__peg {
  position: absolute;
  top: -3px;
  width: 80px;
  height: 9px;
  margin-left: -80px;
  box-shadow: 0 0 10px #10b981, 0 0 5px #10b981;
  border-radius: 50%;
  transform: rotate(3deg);
  transition: left 0.12s ease;
  pointer-events: none;
}

.nprogress--error .nprogress__peg {
  box-shadow: 0 0 10px #ef4444, 0 0 5px #ef4444;
}

/* Mount / unmount fade */
.nprogress-enter-active { transition: opacity 0.1s ease; }
.nprogress-leave-active { transition: opacity 0.25s ease; }
.nprogress-enter-from,
.nprogress-leave-to     { opacity: 0; }
</style>
