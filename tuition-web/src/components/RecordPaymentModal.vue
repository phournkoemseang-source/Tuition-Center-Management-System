<script setup lang="ts">
import QRCode from 'qrcode'
import { computed, ref, watch } from 'vue'
import { errorMessage } from '../services/api'
import { paymentService } from '../services/tcms'
import type { Payment, PaymentMethod } from '../types'

const props = defineProps<{ payment: Payment | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const methods: { value: PaymentMethod; label: string; icon: string; hint: string; needsRef: boolean }[] = [
  { value: 'cash', label: 'Cash', icon: '💵', hint: 'Money received in hand', needsRef: false },
  { value: 'khqr', label: 'KHQR', icon: '📱', hint: 'Scan to pay with any bank app', needsRef: true },
  { value: 'aba', label: 'ABA Mobile', icon: '🏦', hint: 'ABA bank transfer', needsRef: true },
  { value: 'wing', label: 'Wing', icon: '👐', hint: 'Wing money transfer', needsRef: true },
  { value: 'bank', label: 'Other bank', icon: '🏛️', hint: 'Any other bank app', needsRef: true },
]

const selected = ref<PaymentMethod>('cash')
const reference = ref('')
const saving = ref(false)
const error = ref<string | null>(null)
const qrDataUrl = ref<string | null>(null)

const needsReference = computed(
  () => methods.find((m) => m.value === selected.value)?.needsRef ?? false,
)

const amount = computed(() => (props.payment ? Number(props.payment.amount).toFixed(2) : '0.00'))

// EMV-coherent KHQR-style payload: merchant + amount + student ref.
// A real deployment would use the center's registered KHQR merchant string.
const qrPayload = computed(() => {
  if (!props.payment) return ''
  return [
    '00020101021126', // KHQR-style header (dynamic QR)
    `0014TUITION_CENTER`,
    '52045814', // currency USD
    `54${String(amount.value.length).padStart(2, '0')}${amount.value}`,
    '5303840',
    `62${String(
      `08${String(props.payment.id).length.toString().padStart(2, '0')}${props.payment.id}`,
    ).length.toString().padStart(2, '0')}${`08${String(props.payment.id).length.toString().padStart(2, '0')}${props.payment.id}`}`,
    '6304', // CRC placeholder — real KHQR needs CRC16-CCITT
  ].join('')
})

watch(selected, async (m) => {
  qrDataUrl.value = null
  if (m === 'khqr' && props.payment) {
    try {
      qrDataUrl.value = await QRCode.toDataURL(qrPayload.value, {
        width: 220,
        margin: 1,
        color: { dark: '#1e1b4b', light: '#ffffff' },
      })
    } catch {
      qrDataUrl.value = null
    }
  }
})

watch(
  () => props.payment,
  (p) => {
    if (p) {
      selected.value = 'cash'
      reference.value = ''
      error.value = null
      qrDataUrl.value = null
    }
  },
)

async function save() {
  if (!props.payment) return
  saving.value = true
  error.value = null
  try {
    await paymentService.markPaid(props.payment.id, selected.value, reference.value || undefined)
    emit('saved')
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="payment"
      class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-900/40 p-4"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-1 flex items-start justify-between">
          <div>
            <h2 class="text-lg font-bold text-slate-900">Record payment</h2>
            <p class="text-sm text-slate-500">
              {{ payment.student.full_name }} · {{ payment.classRoom.name }}
            </p>
          </div>
          <button class="rounded-lg p-1 text-slate-400 hover:bg-slate-100" @click="emit('close')">✕</button>
        </div>

        <div class="my-4 rounded-xl bg-indigo-50 px-4 py-3 text-center">
          <span class="text-2xl font-bold text-indigo-700">${{ amount }}</span>
          <span class="ml-2 text-sm text-indigo-500">due {{ new Date(payment.due_date).toLocaleDateString() }}</span>
        </div>

        <p class="mb-2 text-sm font-medium text-slate-700">How did they pay?</p>
        <div class="mb-4 grid grid-cols-2 gap-2">
          <button
            v-for="m in methods"
            :key="m.value"
            class="rounded-xl border-2 px-3 py-2.5 text-left transition"
            :class="
              selected === m.value
                ? 'border-indigo-600 bg-indigo-50'
                : 'border-slate-200 hover:border-slate-300'
            "
            @click="selected = m.value"
          >
            <span class="text-lg">{{ m.icon }}</span>
            <p class="text-sm font-semibold text-slate-800">{{ m.label }}</p>
            <p class="text-[11px] leading-tight text-slate-500">{{ m.hint }}</p>
          </button>
        </div>

        <!-- KHQR QR preview -->
        <div v-if="selected === 'khqr'" class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
          <img
            v-if="qrDataUrl"
            :src="qrDataUrl"
            alt="KHQR payment code"
            class="mx-auto rounded-lg bg-white p-2"
            width="180"
            height="180"
          />
          <p class="mt-2 text-xs text-slate-500">
            📷 Show this to the payer — they scan it with any banking app. Then enter the app's
            transaction ID below to confirm.
          </p>
        </div>

        <div v-if="needsReference">
          <label class="mb-1 block text-sm font-medium text-slate-700">
            Transaction reference <span class="text-red-500">*</span>
          </label>
          <input
            v-model="reference"
            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500"
            placeholder="e.g. transaction ID from the app, or last 4 digits"
          />
          <p class="mt-1 text-xs text-slate-400">
            This is your proof if there's ever a question about the payment.
          </p>
        </div>

        <p v-if="error" class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

        <div class="mt-5 flex justify-end gap-2">
          <button class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100" @click="emit('close')">
            Cancel
          </button>
          <button
            :disabled="saving || (needsReference && reference.trim() === '')"
            class="rounded-xl bg-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60"
            @click="save"
          >
            {{ saving ? 'Saving…' : `✓ Mark paid $${amount}` }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
