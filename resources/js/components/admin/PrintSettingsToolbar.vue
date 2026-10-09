<template>
    <div class="print-settings-toolbar inv-screen-only" :class="{ 'is-collapsed': isCollapsed }">
        <!-- Floating Expansion Tab when Collapsed -->
        <div v-if="isCollapsed" class="toolbar-collapsed-bar">
            <el-button
                size="small"
                type="primary"
                plain
                class="btn-expand-toolbar"
                @click="isCollapsed = false"
            >
                <i class="fas fa-sliders"></i>
                <span>{{ $t('show_print_options') || 'إظهار خيارات وتخصيص الطباعة' }}</span>
                <i class="fas fa-chevron-down"></i>
            </el-button>

            <!-- Quick Action Cluster when collapsed -->
            <div class="collapsed-quick-cluster">
                <el-button-group size="small">
                    <el-button :disabled="zoomLevel <= 60" @click="$emit('zoom-out')" :title="$t('zoom_out') || 'تصغير'">
                        <i class="fas fa-minus"></i>
                    </el-button>
                    <el-button @click="$emit('reset-zoom')" class="zoom-badge-btn">
                        {{ zoomLevel }}%
                    </el-button>
                    <el-button :disabled="zoomLevel >= 150" @click="$emit('zoom-in')" :title="$t('zoom_in') || 'تكبير'">
                        <i class="fas fa-plus"></i>
                    </el-button>
                </el-button-group>

                <el-button
                    size="small"
                    type="warning"
                    plain
                    :loading="isExportingPdf"
                    @click="$emit('export-pdf')"
                >
                    <i class="fas fa-file-pdf"></i> PDF
                </el-button>

                <el-button
                    size="small"
                    type="primary"
                    class="btn-print-action"
                    @click="$emit('print-now')"
                >
                    <i class="fas fa-print"></i> {{ $t('print_now') || 'طباعة' }}
                </el-button>
            </div>
        </div>

        <!-- Full Toolbar -->
        <div v-else class="toolbar-inner">
            <!-- Row 1: Presets, Theme, Density & Advanced Customizer -->
            <div class="toolbar-main-strip">
                <!-- Group A: Document Presets -->
                <div class="tb-group tb-group-presets">
                    <span class="tb-group-title">
                        <i class="fas fa-wand-magic-sparkles text-primary"></i>
                        {{ $t('doc_preset') || 'النمط الجاهز' }}:
                    </span>
                    <el-radio-group
                        v-model="modelValue.preset"
                        size="small"
                        class="segmented-presets"
                        @change="onPresetChange"
                    >
                        <el-radio-button value="tax">
                            <i class="fas fa-file-invoice-dollar"></i> {{ $t('preset_tax_invoice') || 'ضريبية رسمية' }}
                        </el-radio-button>
                        <el-radio-button value="receipt">
                            <i class="fas fa-receipt"></i> {{ $t('preset_receipt') || 'إيصال عميل' }}
                        </el-radio-button>
                        <el-radio-button value="commercial">
                            <i class="fas fa-briefcase"></i> {{ $t('preset_commercial') || 'كشف تجاري' }}
                        </el-radio-button>
                        <el-radio-button value="warehouse">
                            <i class="fas fa-dolly"></i> {{ $t('preset_warehouse') || 'إذن تسليم' }}
                        </el-radio-button>
                    </el-radio-group>
                </div>

                <!-- Group B: Theme Palette Dots -->
                <div class="tb-group tb-group-theme">
                    <span class="tb-group-title">
                        <i class="fas fa-palette"></i>
                        {{ $t('theme') || 'اللون' }}:
                    </span>
                    <div class="theme-selector-chips">
                        <button
                            v-for="th in themes"
                            :key="th.key"
                            type="button"
                            class="theme-chip"
                            :class="{ active: modelValue.theme === th.key }"
                            :title="th.label"
                            @click="modelValue.theme = th.key"
                        >
                            <span class="theme-color-disc" :style="{ background: th.color }"></span>
                            <span class="theme-chip-label">{{ th.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- Group C: Density Switch -->
                <div class="tb-group tb-group-density">
                    <span class="tb-group-title">
                        <i class="fas fa-table-cells"></i>
                        {{ $t('density') || 'الكثافة' }}:
                    </span>
                    <el-radio-group v-model="modelValue.density" size="small">
                        <el-radio-button value="standard">
                            <i class="fas fa-expand"></i> {{ $t('density_standard') || 'قياسي' }}
                        </el-radio-button>
                        <el-radio-button value="compact">
                            <i class="fas fa-compress"></i> {{ $t('density_compact') || 'مدمج' }}
                        </el-radio-button>
                    </el-radio-group>
                </div>

                <!-- Group D: Advanced Customization Popover -->
                <div class="tb-group tb-group-customizer">
                    <el-popover
                        placement="bottom-end"
                        :width="420"
                        trigger="click"
                        popper-class="print-customizer-popover"
                    >
                        <template #reference>
                            <el-button size="small" class="btn-customizer-trigger">
                                <i class="fas fa-sliders"></i>
                                <span>{{ $t('customize_elements') || 'تخصيص العناصر المطبوعة' }}</span>
                                <span v-if="activeTogglesCount" class="active-toggles-badge">{{ activeTogglesCount }}</span>
                                <i class="fas fa-chevron-down"></i>
                            </el-button>
                        </template>

                        <!-- Popover Contents with organized categories -->
                        <div class="customizer-panel">
                            <div class="customizer-panel-header">
                                <div class="cp-title">
                                    <i class="fas fa-sliders text-primary"></i>
                                    <strong>{{ $t('customize_document_elements') || 'تخصيص مكونات المستند' }}</strong>
                                </div>
                                <span class="cp-sub">{{ $t('choose_what_to_show_on_print') || 'اختر العناصر التي تريد ظهورها في الورقة المطبوعة' }}</span>
                            </div>

                            <!-- Category 1: Header & Identity -->
                            <div class="cp-section">
                                <span class="cp-sec-title"><i class="fas fa-heading"></i> {{ $t('header_and_identity') || 'الترويسة والهوية' }}</span>
                                <div class="cp-sec-body">
                                    <div class="cp-unit">
                                        <span class="cp-label">{{ $t('header_style') || 'نمط الترويسة' }}:</span>
                                        <el-radio-group v-model="modelValue.headerStyle" size="small">
                                            <el-radio-button value="official">{{ $t('header_official') || 'رسمية' }}</el-radio-button>
                                            <el-radio-button value="banner">{{ $t('header_banner') || 'بانر' }}</el-radio-button>
                                            <el-radio-button value="compact">{{ $t('header_compact') || 'مدمجة' }}</el-radio-button>
                                        </el-radio-group>
                                    </div>
                                    <div class="cp-unit">
                                        <span class="cp-label">{{ $t('watermark') || 'العلامة المائية' }}:</span>
                                        <el-select v-model="modelValue.watermark" size="small" style="width: 150px;">
                                            <el-option value="" :label="$t('watermark_none') || 'بدون علامة'" />
                                            <el-option value="draft" :label="$t('watermark_draft') || 'مسودة DRAFT'" />
                                            <el-option value="approved" :label="$t('watermark_approved') || 'معتمد APPROVED'" />
                                            <el-option value="paid" :label="$t('watermark_paid') || 'مدفوع PAID'" />
                                            <el-option value="official" :label="$t('watermark_official') || 'رسمية OFFICIAL'" />
                                        </el-select>
                                    </div>
                                    <div class="cp-switches-grid">
                                        <el-checkbox v-model="modelValue.showLogo">{{ $t('show_logo') || 'الشعار' }}</el-checkbox>
                                        <el-checkbox v-model="modelValue.showQrCode">{{ $t('show_qr') || 'رمز التحقق QR' }}</el-checkbox>
                                        <el-checkbox v-model="modelValue.showContacts">{{ $t('show_contacts') || 'بيانات التواصل' }}</el-checkbox>
                                    </div>
                                </div>
                            </div>

                            <!-- Category 2: Line Items & Details -->
                            <div class="cp-section">
                                <span class="cp-sec-title"><i class="fas fa-boxes-stacked"></i> {{ $t('line_items_and_meta') || 'البنود والبيانات' }}</span>
                                <div class="cp-switches-grid">
                                    <el-checkbox v-model="modelValue.showImages">{{ $t('show_images') || 'صور المنتجات' }}</el-checkbox>
                                    <el-checkbox v-model="modelValue.showSku">{{ $t('show_sku') || 'الأكواد والباركود' }}</el-checkbox>
                                    <el-checkbox v-model="modelValue.showCustomerInfo">{{ $t('customer_info') || 'بيانات العميل' }}</el-checkbox>
                                    <el-checkbox v-model="modelValue.showPaymentDetails">{{ $t('payment_details') || 'بيانات وطرق الدفع' }}</el-checkbox>
                                </div>
                            </div>

                            <!-- Category 3: Cover, Closing & Legal -->
                            <div class="cp-section">
                                <span class="cp-sec-title"><i class="fas fa-file-shield"></i> {{ $t('cover_and_signatures') || 'الغلاف والاعتماد' }}</span>
                                <div class="cp-switches-grid">
                                    <el-checkbox v-model="modelValue.showCover">{{ $t('include_cover_page') || 'صفحة غلاف رسمية A4' }}</el-checkbox>
                                    <el-checkbox v-model="modelValue.showSignatures">{{ $t('show_signatures') || 'التوقيعات والختم الرسمي' }}</el-checkbox>
                                    <el-checkbox v-model="modelValue.showNotes">{{ $t('notes') || 'الملاحظات والشروط' }}</el-checkbox>
                                    <el-checkbox v-model="modelValue.showFooter">{{ $t('show_footer') || 'تذييل المستند' }}</el-checkbox>
                                </div>
                            </div>
                        </div>
                    </el-popover>
                </div>

                <!-- Group E: Preferences Management (Save / Reset) -->
                <div class="tb-group tb-group-prefs">
                    <el-button
                        size="small"
                        plain
                        class="btn-save-pref"
                        :title="$t('save_as_my_default') || 'حفظ هذه الخيارات كإعداد افتراضي لك'"
                        @click="$emit('save-default')"
                    >
                        <i class="fas fa-bookmark text-warning"></i>
                        <span>{{ $t('save_as_default') || 'حفظ كافتراضي' }}</span>
                    </el-button>
                    <el-button
                        size="small"
                        text
                        class="btn-reset-pref"
                        :title="$t('reset_to_system_defaults') || 'استعادة الإعدادات الافتراضية للنظام'"
                        @click="$emit('reset-default')"
                    >
                        <i class="fas fa-rotate-left"></i>
                    </el-button>
                </div>

                <!-- Spacer -->
                <div class="tb-flex-spacer"></div>

                <!-- Group F: Zoom & Collapse -->
                <div class="tb-group tb-group-viewport">
                    <el-button-group size="small">
                        <el-button :disabled="zoomLevel <= 60" @click="$emit('zoom-out')" :title="$t('zoom_out') || 'تصغير'">
                            <i class="fas fa-minus"></i>
                        </el-button>
                        <el-button @click="$emit('reset-zoom')" class="zoom-badge-btn" :title="$t('reset_zoom') || 'إعادة ضبط 100%'">
                            {{ zoomLevel }}%
                        </el-button>
                        <el-button :disabled="zoomLevel >= 150" @click="$emit('zoom-in')" :title="$t('zoom_in') || 'تكبير'">
                            <i class="fas fa-plus"></i>
                        </el-button>
                    </el-button-group>

                    <el-button
                        size="small"
                        text
                        class="btn-collapse-toggle"
                        :title="$t('collapse_toolbar') || 'تصغير شريط الخيارات لتوسيع المعاينة'"
                        @click="isCollapsed = true"
                    >
                        <i class="fas fa-chevron-up"></i>
                    </el-button>
                </div>

                <!-- Group G: Prominent Execution Buttons -->
                <div class="tb-group tb-group-actions">
                    <el-button
                        type="warning"
                        plain
                        size="default"
                        :loading="isExportingPdf"
                        class="btn-export-pdf"
                        @click="$emit('export-pdf')"
                    >
                        <i class="fas fa-file-pdf"></i>
                        <span>{{ $t('download_pdf') || 'تحميل PDF' }}</span>
                    </el-button>

                    <el-button
                        type="primary"
                        size="default"
                        class="btn-print-prominent"
                        @click="$emit('print-now')"
                    >
                        <i class="fas fa-print"></i>
                        <span>{{ $t('print_now') || 'طباعة الآن' }}</span>
                        <kbd class="shortcut-pill">Ctrl+P</kbd>
                    </el-button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    zoomLevel: {
        type: Number,
        default: 100,
    },
    isExportingPdf: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:modelValue',
    'apply-preset',
    'save-default',
    'reset-default',
    'zoom-in',
    'zoom-out',
    'reset-zoom',
    'export-pdf',
    'print-now',
]);

const isCollapsed = ref(false);

const themes = [
    { key: 'navy', label: 'كحلي ملكي', color: '#1e3a8a' },
    { key: 'emerald', label: 'زمردي رسمي', color: '#047857' },
    { key: 'charcoal', label: 'رمادي فحمي', color: '#334155' },
    { key: 'indigo', label: 'نيلي معتمد', color: '#4338ca' },
];

const onPresetChange = (val) => {
    emit('apply-preset', val);
};

const activeTogglesCount = computed(() => {
    let count = 0;
    const keys = [
        'showCover', 'showQrCode', 'showLogo', 'showContacts',
        'showImages', 'showSku', 'showCustomerInfo',
        'showPaymentDetails', 'showNotes', 'showSignatures', 'showFooter'
    ];
    keys.forEach((k) => {
        if (props.modelValue[k]) count++;
    });
    return count;
});
</script>

<style scoped>
.print-settings-toolbar {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 18px;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.05);
    direction: rtl;
    user-select: none;
    transition: all 0.2s ease-in-out;
}

.toolbar-collapsed-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 2px 0;
}

.btn-expand-toolbar {
    font-weight: 600;
    gap: 6px;
    font-size: 11.5px;
}

.collapsed-quick-cluster {
    display: flex;
    align-items: center;
    gap: 10px;
}

.toolbar-main-strip {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.tb-group {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.tb-group-title {
    font-size: 11.5px;
    font-weight: 700;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.theme-selector-chips {
    display: flex;
    align-items: center;
    gap: 6px;
}

.theme-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    color: #334155;
    transition: all 0.15s ease;
}

.theme-chip:hover {
    border-color: #cbd5e1;
    background: #ffffff;
}

.theme-chip.active {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #1d4ed8;
    box-shadow: 0 0 0 1px #3b82f6;
}

.theme-color-disc {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
}

.btn-customizer-trigger {
    font-weight: 600;
    font-size: 11.5px;
    gap: 6px;
    border-color: #cbd5e1;
    color: #1e293b;
}

.active-toggles-badge {
    background: #3b82f6;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 10px;
}

.btn-save-pref {
    font-size: 11.5px;
    font-weight: 600;
    gap: 5px;
    border-color: #cbd5e1;
}

.btn-reset-pref {
    color: #64748b;
    padding: 4px 6px;
}
.btn-reset-pref:hover {
    color: #0f172a;
}

.tb-flex-spacer {
    flex: 1;
    min-width: 8px;
}

.zoom-badge-btn {
    font-weight: 700;
    min-width: 52px;
    font-size: 11px;
    color: #1e293b;
}

.btn-collapse-toggle {
    color: #64748b;
    padding: 4px 8px;
}

.btn-export-pdf {
    font-weight: 700;
    font-size: 12px;
    gap: 6px;
}

.btn-print-prominent {
    font-weight: 800;
    font-size: 12.5px;
    gap: 8px;
    padding: 8px 18px;
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%) !important;
    border: none !important;
    box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.35);
}

.btn-print-prominent:hover {
    background: linear-gradient(135deg, #172554 0%, #1d4ed8 100%) !important;
}

.shortcut-pill {
    background: rgba(255, 255, 255, 0.22);
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 10px;
    font-family: inherit;
    letter-spacing: 0.5px;
}

/* Customizer Panel Popover styling */
.customizer-panel {
    direction: rtl;
    padding: 4px;
}

.customizer-panel-header {
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 8px;
    margin-bottom: 10px;
}

.cp-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #0f172a;
}

.cp-sub {
    font-size: 11px;
    color: #64748b;
    display: block;
    margin-top: 2px;
}

.cp-section {
    margin-bottom: 12px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    padding: 8px 12px;
}

.cp-sec-title {
    font-size: 11px;
    font-weight: 800;
    color: #334155;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
}

.cp-unit {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
}

.cp-label {
    font-size: 11px;
    color: #64748b;
    font-weight: 600;
}

.cp-switches-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 4px 8px;
}

@media (max-width: 900px) {
    .toolbar-main-strip {
        gap: 8px;
    }
    .tb-group-title {
        display: none;
    }
}
</style>
