<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<{
  class?: HTMLAttributes["class"]
  scope?: "col" | "row" | "colgroup" | "rowgroup"
}>(), {
  scope: "col",
})
</script>

<template>
  <th
    data-slot="table-head"
    :scope="scope"
    :class="cn('text-foreground h-10 px-2 py-4 text-start align-middle font-bold whitespace-nowrap [&:has([role=checkbox])]:pe-0 [&>[role=checkbox]]:translate-y-[2px]', props.class)"
  >
    <slot />
  </th>
</template>
