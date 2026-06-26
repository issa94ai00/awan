<template>
    <div class="reports-index">
        <el-row gutter="20">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('business_reporting_dashboard') }}</span>
                    </template>
                    <p>{{ $t('choose_a_report_to_see') }}</p>
                    <router-link :to="{ name: 'admin.reports.sales' }">
                        <el-button type="primary">{{ $t('sales_report') }}</el-button>
                    </router-link>
                    <router-link :to="{ name: 'admin.reports.inventory' }">
                        <el-button type="success">{{ $t('inventory_report') }}</el-button>
                    </router-link>
                    <router-link :to="{ name: 'admin.reports.financial' }">
                        <el-button type="warning">{{ $t('financial_report') }}</el-button>
                    </router-link>
                    <router-link :to="{ name: 'admin.reports.payroll' }">
                        <el-button type="info">{{ $t('payroll_report') }}</el-button>
                    </router-link>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <span>{{ $t('quick_overview') }}</span>
                    </template>
                    <div style="padding: 1rem 0;">
                        <p>{{ $t('pages_sales_inventory_finance_payroll') }}</p>
                        <p>{{ $t('existing_dashboard_and_billing_summary') }}</p>
                        <p>{{ $t('you_can_integrate_with_more') }}</p>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
</script>

<style scoped>
.reports-index {
    padding: 0;
}
</style>
