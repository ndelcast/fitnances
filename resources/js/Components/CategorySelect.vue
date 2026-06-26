<script setup>
import { ref } from 'vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const props = defineProps({
    modelValue: { type: [String, null], default: null },
    options: { type: Array, required: true },
    placeholder: { type: String, default: 'Selecciona categoría' },
});

const emit = defineEmits(['update:modelValue', 'add']);

const selectRef = ref();
const newName = ref('');
const inputRef = ref();

const addCategory = () => {
    const name = newName.value.trim();
    if (!name) {
        inputRef.value?.$el?.focus();
        return;
    }
    if (!props.options.includes(name)) {
        emit('add', name);
    }
    emit('update:modelValue', name);
    newName.value = '';
    selectRef.value?.hide();
};
</script>

<template>
    <Select
        ref="selectRef"
        :modelValue="modelValue"
        :options="options"
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
                        @click.stop
                        @keydown.enter.prevent="addCategory"
                        @keydown.stop
                    />
                    <Button
                        type="button"
                        icon="pi pi-plus"
                        size="small"
                        severity="success"
                        v-tooltip="'Añadir'"
                        @click.stop="addCategory"
                    />
                </div>
            </div>
        </template>
    </Select>
</template>
