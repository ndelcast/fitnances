<script setup>
import { ref } from 'vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const props = defineProps({
    modelValue: { type: [Number, null], default: null },
    options: { type: Array, required: true }, // [{ id, name, type? }]
    type: { type: String, default: null },    // 'income' | 'expense' (utilisé pour créer une nouvelle catégorie)
    placeholder: { type: String, default: 'Selecciona categoría' },
});

const emit = defineEmits(['update:modelValue', 'created']);

const selectRef = ref();
const inputRef = ref();
const newName = ref('');
const saving = ref(false);
const error = ref(null);

const addCategory = async () => {
    const name = newName.value.trim();
    if (!name || saving.value) {
        inputRef.value?.$el?.focus();
        return;
    }
    saving.value = true;
    error.value = null;

    try {
        const res = await fetch('/categories', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name, type: props.type ?? 'expense' }),
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(body?.errors?.name?.[0] || body?.message || 'Error al crear la categoría.');
        }

        const created = await res.json();
        emit('created', created);
        emit('update:modelValue', created.id);
        newName.value = '';
        selectRef.value?.hide();
    } catch (err) {
        error.value = err.message;
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Select
        ref="selectRef"
        :modelValue="modelValue"
        :options="options"
        optionLabel="name"
        optionValue="id"
        :placeholder="placeholder"
        fluid
        @update:modelValue="(v) => emit('update:modelValue', v)"
    >
        <template #footer>
            <div class="border-t border-surface-200 bg-surface-50 p-2">
                <div class="flex gap-2">
                    <InputText
                        ref="inputRef"
                        v-model="newName"
                        placeholder="Nueva categoría"
                        size="small"
                        class="flex-1"
                        :disabled="saving"
                        @click.stop
                        @keydown.enter.prevent="addCategory"
                        @keydown.stop
                    />
                    <Button
                        type="button"
                        icon="pi pi-plus"
                        size="small"
                        severity="success"
                        :loading="saving"
                        v-tooltip="'Añadir'"
                        @click.stop="addCategory"
                    />
                </div>
                <p v-if="error" class="mt-1 px-1 text-xs text-red-600">{{ error }}</p>
            </div>
        </template>
    </Select>
</template>
