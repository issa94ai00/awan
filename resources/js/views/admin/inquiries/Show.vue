<template>
    <div class="inquiry-show">
        <el-page-header @back="goBack" :title="$t('return')" style="margin-bottom:1.5rem">
            <template #content>
                <span class="text-large font-600 mr-3">{{ $t('inquiry_details') }}{{ inquiry?.id }}</span>
            </template>
        </el-page-header>

        <el-card v-loading="loading" shadow="hover">
            <template v-if="inquiry">
                <div style="margin-bottom:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                    <el-tag :type="getStatusType(inquiry.status)" size="large">{{ inquiry.status_label }}</el-tag>
                    <el-tag :type="getPriorityType(inquiry.priority)" size="large">{{ inquiry.priority_label }}</el-tag>
                </div>

                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="$t('name')">{{ inquiry.name }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('email')">
                        <a v-if="inquiry.email" :href="`mailto:${inquiry.email}`">{{ inquiry.email }}</a>
                        <span v-else>-</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('phone')">
                        <a v-if="inquiry.phone" :href="`tel:${inquiry.phone}`">{{ inquiry.phone }}</a>
                        <span v-else>-</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('inquiry_type')">{{ inquiry.subject_label }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('product')" v-if="inquiry.product">
                        {{ inquiry.product.name_ar }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('responsible')">
                        {{ inquiry.assigned_to ? inquiry.assigned_to.name : $t('not_specified') }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('creation_date')">{{ inquiry.created_at_formatted }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('closing_date')">
                        {{ inquiry.closed_at_formatted || '-' }}
                    </el-descriptions-item>
                </el-descriptions>

                <div style="margin-top:1.5rem;">
                    <h3 style="margin-bottom:0.75rem;">{{ $t('message') }}</h3>
                    <div style="padding:1rem; background:#f9fafb; border-radius:8px; white-space:pre-wrap; line-height:1.6;">
                        {{ inquiry.message }}
                    </div>
                </div>

                <div style="margin-top:1.5rem;">
                    <h3 style="margin-bottom:0.75rem;">{{ $t('replies') }}</h3>
                    <div v-if="inquiry.replies && inquiry.replies.length">
                        <el-timeline>
                            <el-timeline-item 
                                v-for="reply in inquiry.replies" 
                                :key="reply.id" 
                                :timestamp="reply.created_at_human"
                                placement="top"
                            >
                                <el-card>
                                    <div style="margin-bottom:0.5rem;">
                                        <strong>{{ reply.admin_name || $t('supervisor') }}</strong>
                                    </div>
                                    <div style="white-space:pre-wrap; line-height:1.6;">{{ reply.message }}</div>
                                </el-card>
                            </el-timeline-item>
                        </el-timeline>
                    </div>
                    <el-empty v-else description="لا توجد ردود بعد"/>
                </div>

                <div style="margin-top:1.5rem; padding:1.5rem; background:#f3f4f6; border-radius:8px;">
                    <h3 style="margin-bottom:1rem;">{{ $t('add_a_reply') }}</h3>
                    <el-input
                        v-model="replyForm.message"
                        type="textarea"
                        :rows="4"
                        :placeholder="$t('write_your_response_here')"
                    />
                    <div style="display:flex; gap:0.5rem; margin-top:1rem; flex-wrap:wrap;">
                        <el-button type="primary" @click="sendReply" :loading="sending">
                            {{ $t('send_reply') }}
                        </el-button>
                        <el-button @click="updateStatus('replied')">{{ $t('mark_as_respondent') }}</el-button>
                        <el-button type="success" @click="closeInquiry" v-if="inquiry.status !== 'closed'">
                            {{ $t('closing') }}
                        </el-button>
                        <el-button type="warning" @click="reopenInquiry" v-if="inquiry.status === 'closed'">
                            {{ $t('reopen') }}
                        </el-button>
                    </div>
                </div>

                <div style="margin-top:1.5rem; padding:1.5rem; background:#f3f4f6; border-radius:8px;">
                    <h3 style="margin-bottom:1rem;">{{ $t('assigned_to_an_employee') }}</h3>
                    <el-select 
                        v-model="assignTo" 
                        :placeholder="$t('select_employee')" 
                        filterable 
                        style="width:100%"
                    >
                        <el-option 
                            v-for="user in users" 
                            :key="user.id" 
                            :label="user.name" 
                            :value="user.id"
                        />
                    </el-select>
                    <el-button 
                        type="primary" 
                        @click="assignInquiry" 
                        style="margin-top:0.5rem" 
                        :disabled="!assignTo"
                        :loading="assigning"
                    >
                        {{ $t('hiring') }}
                    </el-button>
                </div>
            </template>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useInquiriesStore } from '@/stores/inquiries';
import { ElMessage } from 'element-plus';

const route = useRoute();
const router = useRouter();
const store = useInquiriesStore();

const loading = ref(false);
const sending = ref(false);
const assigning = ref(false);
const inquiry = ref(null);
const replyForm = ref({ message: '' });
const assignTo = ref('');
const users = ref([
    { id: 1, name: window.t('administrator_1')},
    { id: 2, name: window.t('administrator_2')},
]);

function getStatusType(status) {
    const types = {
        new: 'danger',
        read: 'warning',
        replied: 'success',
        closed: 'info'
    };
    return types[status] || '';
}

function getPriorityType(priority) {
    const types = {
        low: 'info',
        medium: '',
        high: 'warning',
        urgent: 'danger'
    };
    return types[priority] || '';
}

function goBack() {
    router.push({ name: 'admin.inquiries.index' });
}

async function loadInquiry() {
    loading.value = true;
    try {
        const res = await store.show(route.params.id);
        inquiry.value = res;
    } catch (error) {
        ElMessage.error(window.t('failed_to_load_query_data'));
    } finally {
        loading.value = false;
    }
}

async function sendReply() {
    if (!replyForm.value.message.trim()) {
        ElMessage.warning(window.t('please_write_a_reply'));
        return;
    }
    
    sending.value = true;
    try {
        await store.reply(inquiry.value.id, replyForm.value.message);
        ElMessage.success(window.t('reply_sent_successfully'));
        replyForm.value.message = '';
        await loadInquiry();
    } catch (error) {
        ElMessage.error(window.t('failed_to_send_response'));
    } finally {
        sending.value = false;
    }
}

async function updateStatus(status) {
    try {
        await store.update(inquiry.value.id, { status });
        ElMessage.success(window.t('status_updated'));
        await loadInquiry();
    } catch (error) {
        ElMessage.error(window.t('failed_to_update_status'));
    }
}

async function closeInquiry() {
    try {
        await store.close(inquiry.value.id);
        ElMessage.success(window.t('the_inquiry_has_been_closed'));
        await loadInquiry();
    } catch (error) {
        ElMessage.error(window.t('failed_to_close_inquiry'));
    }
}

async function reopenInquiry() {
    try {
        await store.reopen(inquiry.value.id);
        ElMessage.success(window.t('the_inquiry_has_been_reopened'));
        await loadInquiry();
    } catch (error) {
        ElMessage.error(window.t('failed_to_reopen_inquiry'));
    }
}

async function assignInquiry() {
    if (!assignTo.value) return;
    
    assigning.value = true;
    try {
        await store.assign(inquiry.value.id, assignTo.value);
        ElMessage.success(window.t('the_inquiry_has_been_set'));
        assignTo.value = '';
        await loadInquiry();
    } catch (error) {
        ElMessage.error(window.t('failed_to_set_query'));
    } finally {
        assigning.value = false;
    }
}

onMounted(() => loadInquiry());
</script>

<style scoped>
.inquiry-show { padding: 0; }
.text-large { font-size: 1.2rem; }
.font-600 { font-weight: 600; }
.mr-3 { margin-right: 0.75rem; }
</style>
