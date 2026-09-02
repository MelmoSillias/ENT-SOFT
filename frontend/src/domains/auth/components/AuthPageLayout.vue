<template>
  <div class="auth-page">
    <section class="auth-page__section">
      <div class="auth-page__container">
        <div class="auth-page__brand">
          <div class="auth-page__brand-visual">
            <img
              v-if="brand.logoUrl"
              :src="brand.logoUrl"
              :alt="brand.name"
              class="auth-page__brand-logo"
            />
            <span v-else class="auth-page__brand-mark">{{ brand.shortName }}</span>
          </div>
          <div class="auth-page__brand-text">
            <span class="auth-page__brand-name">{{ brand.name }}</span>
            <span v-if="brand.tagline" class="auth-page__brand-tagline">{{ brand.tagline }}</span>
          </div>
        </div>

        <div v-if="title || subtitle" class="auth-page__header">
          <h1 v-if="title">{{ title }}</h1>
          <p v-if="subtitle">{{ subtitle }}</p>
        </div>

        <div class="auth-page__content">
          <slot />
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { appConfig } from '@/config/app'
import { useLayoutTheme } from '@/domains/layout/composables/useLayoutTheme'

defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
})

useLayoutTheme()

const brand = appConfig.branding
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  background: var(--layout-auth-bg);
  padding: 1.5rem;
  font-family: var(--layout-font-family);
  color: var(--layout-text-color);
}

.auth-page__container {
  width: min(28rem, 100%);
  display: grid;
  gap: 1.5rem;
}

.auth-page__brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 1rem;
}

.auth-page__brand-visual {
  display: grid;
  place-items: center;
  width: 9rem;
  height: 9rem;
  border-radius: 999px;
  background: var(--layout-logo-bg, #ffffff);
  border: 1px solid var(--layout-logo-border, rgba(26, 48, 102, 0.12));
  box-shadow:
    0 16px 40px rgba(26, 48, 102, 0.12),
    0 0 0 6px color-mix(in srgb, var(--layout-accent-soft, rgba(26, 48, 102, 0.12)) 65%, transparent);
  padding: 0.55rem;
}

.auth-page__brand-logo {
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: center;
  flex-shrink: 0;
  transform: scale(1.08);
}

.auth-page__brand-text {
  display: grid;
  gap: 0.25rem;
}

.auth-page__brand-mark {
  display: grid;
  place-items: center;
  width: 100%;
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--layout-accent), var(--layout-accent-strong));
  color: #fff;
  font-weight: 700;
  font-size: 1.1rem;
}

.auth-page__brand-name {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--layout-auth-brand-color, var(--layout-text-color));
  line-height: 1.2;
}

.auth-page__brand-tagline {
  font-size: 0.9rem;
  color: var(--layout-text-muted);
}

.auth-page__header {
  text-align: center;
}

.auth-page__header h1 {
  margin: 0 0 0.35rem;
  font-size: 1.5rem;
  color: var(--layout-auth-brand-color, var(--layout-text-color));
}

.auth-page__header p {
  margin: 0;
  color: var(--layout-text-muted);
  font-size: 0.95rem;
}

.auth-page__content {
  width: 100%;
}
</style>
