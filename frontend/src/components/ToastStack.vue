<template>
  <div class="toast-stack" aria-live="polite">
    <transition-group name="toast">
      <div
        v-for="t in toast.items"
        :key="t.id"
        :class="['toast', t.type]"
        role="status"
      >
        {{ t.message }}
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { useToastStore } from '../stores/toast'

const toast = useToastStore()
</script>

<style scoped>
.toast-stack {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-width: min(360px, calc(100vw - 48px));
  pointer-events: none;
}
.toast {
  pointer-events: auto;
  padding: 14px 18px;
  border-radius: 14px;
  font-weight: 600;
  font-size: 0.92rem;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
}
.toast.success {
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: #fff;
}
.toast.error {
  background: linear-gradient(135deg, #ef4444, #b91c1c);
  color: #fff;
}
.toast.info {
  background: #1a233d;
  color: #f8fafc;
}
.toast-enter-active,
.toast-leave-active {
  transition: all 0.28s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(12px);
}
</style>
