<template>
    <div
        class="dropzone"
        :class="[`shape-${shape}`, { dragging, 'has-image': !!preview, pending: !!pendingName }]"
        role="button"
        tabindex="0"
        :aria-label="label"
        @click="pick"
        @keydown.enter.prevent="pick"
        @keydown.space.prevent="pick"
        @dragenter.prevent="dragging = true"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
    >
        <input ref="inputRef" type="file" accept="image/*" class="dropzone-input" @change="onChange" />

        <div v-if="preview" class="dropzone-preview" :class="{ checker: transparentBg }">
            <img :src="preview" :alt="label" />
        </div>
        <div v-else class="dropzone-empty">
            <el-icon><Picture /></el-icon>
        </div>

        <div class="dropzone-text">
            <strong>{{ preview ? t('settings_replace_image') : t('settings_upload_image') }}</strong>
            <small v-if="pendingName" class="pending-name">
                <span class="pending-dot"></span>{{ t('settings_pending_upload', { name: pendingName }) }}
            </small>
            <small v-else>{{ hint || t('settings_drop_hint', { size: maxSizeMb }) }}</small>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Picture } from '@element-plus/icons-vue';

const props = defineProps({
    preview: { type: String, default: '' },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    // 'wide' for banners and share images, 'square' for logos and icons.
    shape: { type: String, default: 'wide' },
    transparentBg: { type: Boolean, default: false },
    maxSizeMb: { type: Number, default: 3 },
    pendingName: { type: String, default: '' },
});

const emit = defineEmits(['select']);
const { t } = useI18n();
const inputRef = ref(null);
const dragging = ref(false);

const pick = () => inputRef.value?.click();

const accept = (file) => {
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        ElMessage.error(t('only_photos_can_be_uploaded'));
        return;
    }
    if (file.size / 1024 / 1024 > props.maxSizeMb) {
        ElMessage.error(t('image_size_limit', { maxSize: props.maxSizeMb }));
        return;
    }
    emit('select', file);
};

const onChange = (event) => {
    accept(event.target.files?.[0]);
    // Lets the same file be picked again after a discard.
    event.target.value = '';
};

const onDrop = (event) => {
    dragging.value = false;
    accept(event.dataTransfer?.files?.[0]);
};
</script>

<style scoped>
.dropzone {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 0.75rem;
    border: 1.5px dashed #cbd5e1;
    border-radius: 14px;
    background: #f8fafc;
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease;
}

.dropzone:hover,
.dropzone:focus-visible,
.dropzone.dragging {
    border-color: #0d9488;
    background: rgba(13, 148, 136, 0.05);
    outline: none;
}

.dropzone.pending {
    border-style: solid;
    border-color: #f59e0b;
    background: rgba(245, 158, 11, 0.05);
}

.dropzone-input {
    display: none;
}

.dropzone-preview,
.dropzone-empty {
    flex-shrink: 0;
    width: 132px;
    height: 74px;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 1px solid #e2e8f0;
}

.shape-square .dropzone-preview,
.shape-square .dropzone-empty {
    width: 74px;
}

.dropzone-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.shape-wide .dropzone-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dropzone-preview.checker {
    background-color: #fff;
    background-image:
        linear-gradient(45deg, #eef2f7 25%, transparent 25%),
        linear-gradient(-45deg, #eef2f7 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, #eef2f7 75%),
        linear-gradient(-45deg, transparent 75%, #eef2f7 75%);
    background-size: 12px 12px;
    background-position: 0 0, 0 6px, 6px -6px, -6px 0;
}

.dropzone-empty .el-icon {
    font-size: 1.6rem;
    color: #94a3b8;
}

.dropzone-text {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.dropzone-text strong {
    font-size: 0.86rem;
    color: #0f766e;
}

.dropzone-text small {
    font-size: 0.75rem;
    color: #64748b;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pending-name {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #b45309 !important;
}

.pending-dot {
    flex-shrink: 0;
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #f59e0b;
}
</style>
