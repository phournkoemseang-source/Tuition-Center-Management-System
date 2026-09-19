<script setup lang="ts">
import { computed } from 'vue'
import { avatarHue, initials } from '../utils/format'

const props = withDefaults(defineProps<{ name: string; size?: 'sm' | 'md' | 'lg' }>(), {
  size: 'md',
})

const text = computed(() => initials(props.name))
const sizeClass = computed(
  () => ({ sm: 'h-7 w-7 text-[10px]', md: 'h-9 w-9 text-xs', lg: 'h-12 w-12 text-base' })[props.size],
)
const bg = computed(() => {
  const h = avatarHue(props.name)
  return { backgroundColor: `hsl(${h} 65% 92%)`, color: `hsl(${h} 45% 32%)` }
})
</script>

<template>
  <span
    class="inline-grid shrink-0 place-items-center rounded-full font-bold uppercase"
    :class="sizeClass"
    :style="bg"
    :title="name"
  >
    {{ text }}
  </span>
</template>
