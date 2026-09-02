<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  group: {
    type: Object,
    required: true
  },
  collapsed: {
    type: Boolean,
    default: false
  },
  activeKey: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['parent-click'])

const router = useRouter()

const expanded = ref(false)

const isParentActive = computed(() => {
  return props.activeKey === props.group.key
})

const activeChild = computed(() => {
  return props.group.items?.find(item => item.key === props.activeKey)
})

const hasActiveChild = computed(() => {
  return !!activeChild.value
})

const shouldExpand = computed(() => {
  if (props.collapsed) return false
  return expanded.value || hasActiveChild.value
})

const displayLabel = computed(() => {
  if (props.collapsed && props.group.shortLabel) {
    return props.group.shortLabel
  }
  return props.group.label
})

const labelTitle = computed(() => {
  if (props.collapsed && props.group.shortLabel && props.group.shortLabel !== props.group.label) {
    return props.group.label
  }
  return undefined
})

const handleParentClick = () => {
  if (props.group.routeName && !props.group.items?.length) {
    router.push({ name: props.group.routeName })
  } else if (props.group.items?.length) {
    if (props.collapsed) {
      emit('parent-click', props.group)
    } else {
      expanded.value = !expanded.value
    }
  }
}

const handleChildClick = (child) => {
  if (child.routeName) {
    router.push({ name: child.routeName })
  }
}

const isChildActive = (child) => {
  return child.key === props.activeKey
}
</script>

<template>
  <div class="app-nav-group" :class="{ 'app-nav-group--collapsed': collapsed }">
    <button
      type="button"
      class="app-nav-group__parent"
      :class="{
        'app-nav-group__parent--active': isParentActive || hasActiveChild,
        'app-nav-group__parent--collapsed': collapsed,
        'app-nav-group__parent--expanded': shouldExpand
      }"
      :title="labelTitle"
      :aria-label="group.label"
      @click="handleParentClick"
    >
      <i v-if="group.icon" :class="['app-nav-group__icon', group.icon]"></i>
      <span class="app-nav-group__label">{{ displayLabel }}</span>
      <i
        v-if="group.items && group.items.length && !collapsed"
        class="app-nav-group__chevron pi"
        :class="shouldExpand ? 'pi-chevron-down' : 'pi-chevron-right'"
      ></i>
      <span v-if="isParentActive || hasActiveChild" class="app-nav-group__active-bar"></span>
    </button>

    <Transition name="nav-children">
      <div v-if="shouldExpand && group.items && group.items.length" class="app-nav-group__children">
        <div class="app-nav-group__tree-line"></div>
        <button
          v-for="child in group.items"
          :key="child.key"
          type="button"
          class="app-nav-group__child"
          :class="{ 'app-nav-group__child--active': isChildActive(child) }"
          @click="handleChildClick(child)"
        >
          <span class="app-nav-group__child-label">{{ child.label }}</span>
          <span v-if="isChildActive(child)" class="app-nav-group__active-bar"></span>
        </button>
      </div>
    </Transition>
  </div>
</template>
