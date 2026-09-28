<template>
    <div class="accounting-page fixed-assets">
        <AdminPageHeader
            icon="fas fa-building-columns text-primary"
            :title="$t('fixed_assets')"
            :subtitle="$t('fixed_assets_subtitle')"
        >
            <template #actions>
                <el-tag size="small" effect="plain" round class="currency-tag">
                    {{ $t('amounts_in_base_currency', { currency: baseCode }) }}
                </el-tag>
                <el-button :loading="loading" @click="reload">
                    <i class="fas fa-sync-alt mr-1"></i> {{ $t('refresh') }}
                </el-button>
                <el-button type="primary" plain @click="openRun()">
                    <i class="fas fa-calendar-check mr-1"></i> {{ $t('fa_run_depreciation') }}
                </el-button>
                <el-button type="primary" @click="openCreate">
                    <i class="fas fa-plus mr-1"></i> {{ $t('register_asset') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-alert v-if="error" type="error" show-icon :closable="false" class="mb-3" :title="error">
            <el-button size="small" type="danger" plain @click="reload">{{ $t('dash_try_again') }}</el-button>
        </el-alert>

        <!-- ── Where depreciation stands ─────────────────────────────────── -->
        <!-- The run is scheduled for the first of each month; this says whether
             it has kept up, and lets the months it missed be posted from here. -->
        <section v-if="summary" class="dep-status" :class="depState">
            <span class="dep-icon">
                <i class="fas" :class="depState === 'is-behind' ? 'fa-clock-rotate-left' : depState === 'is-ok' ? 'fa-circle-check' : 'fa-circle-info'"></i>
            </span>
            <div class="dep-text">
                <strong v-if="depState === 'is-behind'">
                    {{ $t('fa_behind_title', { count: summary.depreciation.behind_count, months: summary.depreciation.behind_months }) }}
                </strong>
                <strong v-else-if="depState === 'is-ok'">
                    {{ $t('fa_up_to_date', { month: monthLabel(summary.depreciation.through) }) }}
                </strong>
                <strong v-else>{{ $t('fa_nothing_to_depreciate') }}</strong>
                <span>
                    <template v-if="depState === 'is-behind'">
                        {{ $t('fa_behind_hint', { amount: money(summary.depreciation.behind_amount), month: monthLabel(summary.depreciation.through) }) }}
                    </template>
                    <template v-else>{{ $t('fa_schedule_hint') }}</template>
                </span>
            </div>
            <el-button v-if="depState === 'is-behind'" type="warning" @click="openRun()">
                {{ $t('fa_review_and_post') }}
            </el-button>
        </section>

        <!-- ── The register in four figures ─────────────────────────────── -->
        <div v-if="summary" class="kpis">
            <section class="fa-card kpi">
                <span class="kpi-label"><i class="fas fa-tag"></i> {{ $t('assets_at_cost') }}</span>
                <strong class="kpi-value">{{ money(summary.cost) }}</strong>
                <span class="kpi-sub">{{ $t('fa_active_count', { count: formatNumber(summary.active_count) }) }}</span>
            </section>
            <section class="fa-card kpi">
                <span class="kpi-label"><i class="fas fa-hourglass-half"></i> {{ $t('accumulated_depreciation') }}</span>
                <strong class="kpi-value is-used">{{ money(summary.accumulated_depreciation) }}</strong>
                <span class="meter" :title="usedPercent + '%'"><span :style="{ width: usedPercent + '%' }"></span></span>
                <span class="kpi-sub">{{ $t('fa_used_share', { pct: formatPct(usedPercent) }) }}</span>
            </section>
            <section class="fa-card kpi is-accent">
                <span class="kpi-label"><i class="fas fa-scale-balanced"></i> {{ $t('net_book_value') }}</span>
                <strong class="kpi-value">{{ money(summary.net_book_value) }}</strong>
                <span class="kpi-sub">{{ $t('fa_nbv_hint') }}</span>
            </section>
            <section class="fa-card kpi">
                <span class="kpi-label"><i class="fas fa-calendar-days"></i> {{ $t('fa_monthly_expense') }}</span>
                <strong class="kpi-value">{{ money(summary.monthly_charge) }}</strong>
                <span class="kpi-sub">
                    {{ $t('fa_yearly', { amount: money(summary.monthly_charge * 12) }) }}
                    <template v-if="summary.fully_depreciated_count"> · {{ $t('fa_fully_count', { count: formatNumber(summary.fully_depreciated_count) }) }}</template>
                </span>
            </section>
        </div>

        <!-- ── Register ─────────────────────────────────────────────────── -->
        <section class="fa-card">
            <header class="fa-card-head">
                <h2><i class="fas fa-list"></i> {{ $t('asset_register') }}</h2>
                <div class="toolbar">
                    <el-segmented v-model="filters.status" :options="statusOptions" @change="applyFilters" />
                    <el-input
                        v-model="filters.search"
                        clearable
                        :placeholder="$t('fa_search_placeholder')"
                        class="t-search"
                        @input="onSearch"
                        @clear="applyFilters"
                    >
                        <template #prefix><i class="fas fa-search"></i></template>
                    </el-input>
                    <el-select
                        v-if="categories.length"
                        v-model="filters.category"
                        clearable
                        :placeholder="$t('fa_all_categories')"
                        class="t-category"
                        @change="applyFilters"
                    >
                        <el-option v-for="c in categories" :key="c" :label="c" :value="c" />
                    </el-select>
                </div>
            </header>

            <div v-if="loading && !assets.length" class="py-2"><el-skeleton :rows="5" animated /></div>

            <template v-else>
                <el-table
                    v-if="assets.length"
                    v-loading="loading"
                    :data="assets"
                    class="register"
                    row-class-name="clickable"
                    style="width:100%"
                    @row-click="openDetail"
                >
                    <el-table-column :label="$t('fa_asset')" min-width="230">
                        <template #default="{ row }">
                            <div class="asset-cell">
                                <span class="code-badge">{{ row.asset_number }}</span>
                                <div class="asset-name">
                                    <strong>{{ row.name }}</strong>
                                    <small>
                                        <span v-if="row.category" class="chip">{{ row.category }}</span>
                                        <span v-if="row.warehouse"><i class="fas fa-location-dot"></i> {{ row.warehouse.name }}</span>
                                    </small>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('acquired_on')" width="130">
                        <template #default="{ row }">
                            <span class="amt">{{ dateOnly(row.acquired_on) }}</span>
                            <small class="sub">{{ lifeLabel(row.useful_life_months) }}</small>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('cost')" width="130" align="right">
                        <template #default="{ row }"><span class="amt">{{ plain(row.cost) }}</span></template>
                    </el-table-column>
                    <el-table-column :label="$t('fa_used_up')" min-width="190">
                        <template #default="{ row }">
                            <template v-if="row.status === 'disposed'">
                                <span class="tag is-muted"><i class="fas fa-box-archive"></i> {{ $t('fa_disposed_on', { date: dateOnly(row.disposed_on) }) }}</span>
                                <small class="sub" :class="resultClass(row.disposal_result)">
                                    {{ resultLabel(row.disposal_result) }}
                                </small>
                            </template>
                            <template v-else>
                                <div class="progress-line">
                                    <span class="meter" :class="{ 'is-full': row.is_fully_depreciated }">
                                        <span :style="{ width: row.depreciated_percent + '%' }"></span>
                                    </span>
                                    <span class="pct">{{ formatPct(row.depreciated_percent) }}</span>
                                </div>
                                <small class="sub">
                                    <template v-if="row.is_fully_depreciated">{{ $t('fa_fully_depreciated') }}</template>
                                    <template v-else>{{ plain(row.accumulated_depreciation) }} · {{ $t('fa_months_left', { n: row.remaining_months }) }}</template>
                                </small>
                            </template>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('net_book_value')" width="140" align="right">
                        <template #default="{ row }"><strong class="amt">{{ plain(row.net_book_value) }}</strong></template>
                    </el-table-column>
                    <el-table-column :label="$t('monthly_charge')" width="120" align="right">
                        <template #default="{ row }">
                            <span v-if="row.status === 'active' && !row.is_fully_depreciated" class="amt">{{ plain(row.monthly_charge) }}</span>
                            <span v-else class="sub">—</span>
                        </template>
                    </el-table-column>
                    <el-table-column width="120" align="center">
                        <template #default="{ row }">
                            <div class="row-actions" @click.stop>
                                <el-tooltip :content="$t('edit')" placement="top">
                                    <el-button size="small" text circle @click="openEdit(row)"><i class="fas fa-pen"></i></el-button>
                                </el-tooltip>
                                <el-tooltip v-if="row.status === 'active'" :content="$t('dispose_asset')" placement="top">
                                    <el-button size="small" text circle type="warning" @click="openDispose(row)"><i class="fas fa-box-archive"></i></el-button>
                                </el-tooltip>
                                <el-button size="small" text circle @click="openDetail(row)"><i class="fas fa-chevron-right dir-arrow"></i></el-button>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-else class="empty">
                    <i class="fas fa-building-columns"></i>
                    <strong>{{ hasFilters ? $t('fa_no_match') : $t('no_assets_registered') }}</strong>
                    <p v-if="!hasFilters">{{ $t('fa_empty_hint') }}</p>
                    <el-button v-if="hasFilters" @click="clearFilters">{{ $t('fa_clear_filters') }}</el-button>
                    <el-button v-else type="primary" @click="openCreate"><i class="fas fa-plus mr-1"></i> {{ $t('register_asset') }}</el-button>
                </div>

                <div v-if="pagination.total > pagination.per_page" class="pager">
                    <el-pagination
                        v-model:current-page="page"
                        v-model:page-size="perPage"
                        :page-sizes="[20, 50, 100]"
                        :total="pagination.total"
                        layout="total, sizes, prev, pager, next"
                        background
                        @current-change="reload"
                        @size-change="applyFilters"
                    />
                </div>
            </template>
        </section>

        <!-- ── Detail ───────────────────────────────────────────────────── -->
        <el-drawer v-model="detailVisible" :size="drawerSize" :title="detail ? `${detail.asset_number} · ${detail.name}` : ''" destroy-on-close>
            <div v-if="detailLoading && !detail" class="p-2"><el-skeleton :rows="8" animated /></div>
            <div v-else-if="detail" v-loading="detailLoading" class="detail">
                <div class="detail-head">
                    <span class="tag" :class="detail.status === 'active' ? (detail.is_fully_depreciated ? 'is-info' : 'is-ok') : 'is-muted'">
                        {{ detail.status === 'active' ? (detail.is_fully_depreciated ? $t('fa_fully_depreciated') : $t('in_use')) : $t('disposed') }}
                    </span>
                    <span v-if="detail.category" class="chip">{{ detail.category }}</span>
                    <span class="spacer"></span>
                    <el-button size="small" @click="openEdit(detail)"><i class="fas fa-pen mr-1"></i> {{ $t('edit') }}</el-button>
                    <el-button v-if="detail.status === 'active'" size="small" type="warning" plain @click="openDispose(detail)">
                        <i class="fas fa-box-archive mr-1"></i> {{ $t('dispose_asset') }}
                    </el-button>
                </div>

                <!-- Cost split into what is used, what is left to charge, and
                     what is never charged. -->
                <div class="split">
                    <div class="split-bar">
                        <span class="s-used" :style="{ width: share(detail.accumulated_depreciation) }"></span>
                        <span class="s-left" :style="{ width: share(Math.max(0, detail.depreciable_amount - detail.accumulated_depreciation)) }"></span>
                        <span class="s-salvage" :style="{ width: share(detail.salvage_value) }"></span>
                    </div>
                    <div class="split-legend">
                        <span><i class="dot s-used"></i> {{ $t('accumulated_depreciation') }} <b>{{ plain(detail.accumulated_depreciation) }}</b></span>
                        <span><i class="dot s-left"></i> {{ $t('fa_left_to_charge') }} <b>{{ plain(Math.max(0, detail.depreciable_amount - detail.accumulated_depreciation)) }}</b></span>
                        <span v-if="Number(detail.salvage_value)"><i class="dot s-salvage"></i> {{ $t('salvage_value') }} <b>{{ plain(detail.salvage_value) }}</b></span>
                    </div>
                </div>

                <dl class="facts two">
                    <div><dt>{{ $t('cost') }}</dt><dd>{{ money(detail.cost) }}</dd></div>
                    <div><dt>{{ $t('net_book_value') }}</dt><dd><strong>{{ money(detail.net_book_value) }}</strong></dd></div>
                    <div><dt>{{ $t('acquired_on') }}</dt><dd>{{ dateOnly(detail.acquired_on) }}</dd></div>
                    <div><dt>{{ $t('fa_useful_life') }}</dt><dd>{{ lifeLabel(detail.useful_life_months) }}</dd></div>
                    <div><dt>{{ $t('monthly_charge') }}</dt><dd>{{ money(detail.monthly_charge) }}</dd></div>
                    <div v-if="detail.status === 'active'">
                        <dt>{{ $t('fa_last_charge') }}</dt>
                        <dd>{{ detail.last_charge_month ? monthLabel(detail.last_charge_month) : '—' }}</dd>
                    </div>
                    <div v-if="detail.depreciated_through"><dt>{{ $t('fa_charged_through') }}</dt><dd>{{ monthLabel(dateOnly(detail.depreciated_through).slice(0, 7)) }}</dd></div>
                    <div v-if="detail.supplier"><dt>{{ $t('supplier') }}</dt><dd>{{ detail.supplier.name }}</dd></div>
                    <div v-if="detail.warehouse"><dt>{{ $t('fa_location') }}</dt><dd>{{ detail.warehouse.name }}</dd></div>
                    <div v-if="detail.creator"><dt>{{ $t('fa_registered_by') }}</dt><dd>{{ detail.creator.name }}</dd></div>
                </dl>

                <p v-if="detail.notes" class="notes"><i class="fas fa-note-sticky"></i> {{ detail.notes }}</p>

                <template v-if="detail.status === 'disposed'">
                    <h3 class="sub-head">{{ $t('fa_disposal') }}</h3>
                    <dl class="facts two">
                        <div><dt>{{ $t('disposed_on') }}</dt><dd>{{ dateOnly(detail.disposed_on) }}</dd></div>
                        <div><dt>{{ $t('disposal_proceeds') }}</dt><dd>{{ money(detail.disposal_proceeds) }}</dd></div>
                        <div><dt>{{ $t('fa_result') }}</dt><dd :class="resultClass(detail.disposal_result)">{{ resultLabel(detail.disposal_result) }}</dd></div>
                    </dl>
                </template>

                <h3 class="sub-head">{{ $t('fa_entries') }}</h3>
                <div class="entries">
                    <router-link v-if="detail.acquisition_entry" :to="journalLink(detail.acquisition_entry)" class="entry-link">
                        <span class="code-badge">{{ detail.acquisition_entry.entry_number }}</span>
                        <span>{{ $t('fa_acquisition_entry') }} · {{ detail.acquisition_entry.entry_date }}</span>
                        <b class="amt">{{ plain(detail.acquisition_entry.amount) }}</b>
                    </router-link>
                    <router-link v-if="detail.disposal_entry" :to="journalLink(detail.disposal_entry)" class="entry-link">
                        <span class="code-badge">{{ detail.disposal_entry.entry_number }}</span>
                        <span>{{ $t('fa_disposal_entry') }} · {{ detail.disposal_entry.entry_date }}</span>
                        <b class="amt">{{ plain(detail.disposal_entry.amount) }}</b>
                    </router-link>
                </div>

                <h3 class="sub-head">
                    {{ $t('fa_schedule') }}
                    <small v-if="scheduleCounts.due" class="due-note">{{ $t('fa_due_count', { n: scheduleCounts.due }) }}</small>
                </h3>
                <div v-if="detail.schedule.length" class="schedule">
                    <div class="s-row is-head">
                        <span>{{ $t('vr_month') }}</span>
                        <span class="ta-end">{{ $t('fa_charge') }}</span>
                        <span class="ta-end">{{ $t('fa_nbv_after') }}</span>
                        <span></span>
                    </div>
                    <template v-for="group in scheduleByYear" :key="group.year">
                        <button type="button" class="s-row is-year" @click="toggleYear(group.year)">
                            <span><i class="fas fa-chevron-down caret" :class="{ 'is-closed': !openYears.has(group.year) }"></i> {{ group.year }}</span>
                            <span class="ta-end amt">{{ plain(group.total) }}</span>
                            <span class="ta-end amt">{{ plain(group.nbvEnd) }}</span>
                            <span class="ta-end"><small class="sub">{{ $t('fa_n_months', { n: group.rows.length }) }}</small></span>
                        </button>
                        <template v-if="openYears.has(group.year)">
                            <div v-for="row in group.rows" :key="row.month" class="s-row" :class="'is-' + row.state">
                                <span>{{ monthLabel(row.month) }}</span>
                                <span class="ta-end amt">{{ plain(row.charge) }}</span>
                                <span class="ta-end amt">{{ plain(row.net_book_value_after) }}</span>
                                <span class="ta-end">
                                    <router-link v-if="row.entry_number" :to="journalLink(row)" class="state is-posted">{{ row.entry_number }}</router-link>
                                    <span v-else class="state" :class="'is-' + row.state">{{ $t('fa_state_' + row.state) }}</span>
                                </span>
                            </div>
                        </template>
                    </template>
                </div>
                <p v-else class="muted">{{ $t('fa_no_schedule') }}</p>
            </div>
        </el-drawer>

        <!-- ── Register ─────────────────────────────────────────────────── -->
        <el-dialog v-model="createVisible" :title="$t('register_asset')" :width="dialogWidth(760)" destroy-on-close class="fa-dialog">
            <div class="create-grid">
                <el-form :model="form" label-position="top" class="create-form" @submit.prevent>
                    <h4 class="form-section">{{ $t('fa_section_what') }}</h4>
                    <el-row :gutter="12">
                        <el-col :xs="24" :sm="14">
                            <el-form-item :label="$t('name')" required :error="fieldError('name')">
                                <el-input v-model="form.name" maxlength="255" />
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="10">
                            <el-form-item :label="$t('category')" :error="fieldError('category')">
                                <el-autocomplete
                                    v-model="form.category"
                                    :fetch-suggestions="suggestCategories"
                                    :placeholder="$t('asset_category_example')"
                                    clearable
                                    style="width:100%"
                                />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item v-if="warehouses.length" :label="$t('fa_location')">
                        <el-select v-model="form.warehouse_id" clearable filterable style="width:100%">
                            <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                        </el-select>
                    </el-form-item>

                    <h4 class="form-section">{{ $t('fa_section_cost') }}</h4>
                    <el-row :gutter="12">
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('cost')" required :error="fieldError('cost')">
                                <el-input-number v-model="form.cost" :min="0" :precision="2" :controls="false" style="width:100%" />
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('salvage_value')" :error="fieldError('salvage_value') || salvageError">
                                <el-input-number v-model="form.salvage_value" :min="0" :precision="2" :controls="false" style="width:100%" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-row :gutter="12">
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('useful_life_months')" required :error="fieldError('useful_life_months')">
                                <el-input-number v-model="form.useful_life_months" :min="1" :max="600" :step="12" style="width:100%" />
                                <div class="life-presets">
                                    <button
                                        v-for="years in [1, 3, 5, 10]"
                                        :key="years"
                                        type="button"
                                        :class="{ 'is-on': form.useful_life_months === years * 12 }"
                                        @click="form.useful_life_months = years * 12"
                                    >{{ $t('fa_years', { n: years }) }}</button>
                                </div>
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('acquired_on')" required :error="fieldError('acquired_on')">
                                <el-date-picker
                                    v-model="form.acquired_on"
                                    type="date"
                                    format="YYYY-MM-DD"
                                    value-format="YYYY-MM-DD"
                                    :disabled-date="isFuture"
                                    :clearable="false"
                                    style="width:100%"
                                />
                            </el-form-item>
                        </el-col>
                    </el-row>

                    <h4 class="form-section">{{ $t('fa_section_payment') }}</h4>
                    <el-form-item :label="$t('settlement')">
                        <el-segmented v-model="form.settlement" :options="settlementOptions" />
                    </el-form-item>
                    <el-form-item v-if="form.settlement === 'credit'" :label="$t('supplier')" required :error="fieldError('supplier_id')">
                        <el-select v-model="form.supplier_id" filterable clearable :loading="suppliersLoading" style="width:100%">
                            <el-option v-for="supplier in suppliers" :key="supplier.id" :label="supplier.name" :value="supplier.id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="$t('notes')">
                        <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" />
                    </el-form-item>
                </el-form>

                <!-- The figures the whole schedule is derived from, and the entry
                     it will post, shown before they are committed. -->
                <aside class="preview">
                    <h4>{{ $t('fa_preview') }}</h4>
                    <template v-if="plan">
                        <dl class="facts">
                            <div><dt>{{ $t('fa_depreciable') }}</dt><dd>{{ plain(plan.depreciable) }}</dd></div>
                            <div><dt>{{ $t('monthly_charge') }}</dt><dd><strong>{{ plain(plan.monthly) }}</strong></dd></div>
                            <div><dt>{{ $t('fa_per_year') }}</dt><dd>{{ plain(plan.monthly * 12) }}</dd></div>
                            <div><dt>{{ $t('fa_first_charge') }}</dt><dd>{{ monthLabel(plan.first) }}</dd></div>
                            <div><dt>{{ $t('fa_last_charge') }}</dt><dd>{{ monthLabel(plan.last) }}</dd></div>
                        </dl>
                        <p v-if="plan.behind > 0" class="callout is-warn">
                            <i class="fas fa-clock-rotate-left"></i>
                            {{ $t('fa_backdated_hint', { n: plan.behind, amount: plain(plan.behind * plan.monthly) }) }}
                        </p>
                        <div class="entry-preview">
                            <small>{{ $t('fa_entry_preview') }}</small>
                            <div class="je"><span>{{ $t('fa_dr') }}</span><span>{{ $t('fixed_assets') }}</span><b>{{ plain(form.cost) }}</b></div>
                            <div class="je is-cr"><span>{{ $t('fa_cr') }}</span><span>{{ creditAccountLabel }}</span><b>{{ plain(form.cost) }}</b></div>
                        </div>
                    </template>
                    <p v-else class="muted">{{ $t('fa_preview_empty') }}</p>
                </aside>
            </div>

            <template #footer>
                <el-button @click="createVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" :disabled="!canSubmit" @click="submit">
                    {{ $t('fa_register_and_post') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- ── Edit ─────────────────────────────────────────────────────── -->
        <el-dialog v-model="editVisible" :title="$t('fa_edit_asset')" :width="dialogWidth(480)" destroy-on-close>
            <el-form label-position="top" @submit.prevent>
                <el-form-item :label="$t('name')" required :error="fieldError('name')">
                    <el-input v-model="editForm.name" maxlength="255" />
                </el-form-item>
                <el-form-item :label="$t('category')">
                    <el-autocomplete v-model="editForm.category" :fetch-suggestions="suggestCategories" clearable style="width:100%" />
                </el-form-item>
                <el-form-item v-if="warehouses.length" :label="$t('fa_location')">
                    <el-select v-model="editForm.warehouse_id" clearable filterable style="width:100%">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('notes')">
                    <el-input v-model="editForm.notes" type="textarea" :rows="2" maxlength="1000" />
                </el-form-item>
            </el-form>
            <p class="callout"><i class="fas fa-lock"></i> {{ $t('fa_figures_locked') }}</p>

            <template #footer>
                <el-button @click="editVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" :disabled="!editForm.name.trim()" @click="saveEdit">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>

        <!-- ── Dispose ──────────────────────────────────────────────────── -->
        <el-dialog v-model="disposeVisible" :title="disposing ? `${$t('dispose_asset')} · ${disposing.name}` : $t('dispose_asset')" :width="dialogWidth(520)" destroy-on-close>
            <div v-if="!disposing" class="py-2"><el-skeleton :rows="4" animated /></div>
            <template v-else>
                <p class="muted mb-3">{{ $t('dispose_asset_hint') }}</p>

                <el-form label-position="top" @submit.prevent>
                    <el-row :gutter="12">
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('disposed_on')" :error="fieldError('disposed_on')">
                                <el-date-picker
                                    v-model="disposeForm.disposed_on"
                                    type="date"
                                    format="YYYY-MM-DD"
                                    value-format="YYYY-MM-DD"
                                    :disabled-date="disposalDateDisabled"
                                    :clearable="false"
                                    style="width:100%"
                                />
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('disposal_proceeds')" :error="fieldError('proceeds')">
                                <el-input-number v-model="disposeForm.proceeds" :min="0" :precision="2" :controls="false" style="width:100%" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item v-if="Number(disposeForm.proceeds) > 0" :label="$t('fa_received_into')">
                        <el-segmented v-model="disposeForm.settlement" :options="receiptOptions" />
                    </el-form-item>

                    <div v-if="catchUp.months" class="catch-up">
                        <el-switch v-model="disposeForm.charge_to_date" />
                        <span>{{ $t('fa_charge_before_disposal', { n: catchUp.months, amount: plain(catchUp.amount) }) }}</span>
                    </div>
                </el-form>

                <div class="dispose-sum">
                    <div><span>{{ $t('cost') }}</span><b>{{ plain(disposing.cost) }}</b></div>
                    <div><span>{{ $t('accumulated_depreciation') }}</span><b>− {{ plain(disposalAccumulated) }}</b></div>
                    <div class="is-sub"><span>{{ $t('fa_carried_at') }}</span><b>{{ plain(disposalCarrying) }}</b></div>
                    <div><span>{{ $t('disposal_proceeds') }}</span><b>{{ plain(disposeForm.proceeds) }}</b></div>
                    <div class="is-result" :class="resultClass(disposalResult)">
                        <span>{{ disposalResult >= 0 ? $t('fa_gain') : $t('fa_loss') }}</span>
                        <b>{{ plain(Math.abs(disposalResult)) }}</b>
                    </div>
                </div>
            </template>

            <template #footer>
                <el-button @click="disposeVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="warning" :loading="saving" :disabled="!disposing" @click="confirmDispose">
                    {{ $t('fa_dispose_and_post') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- ── Depreciation run ─────────────────────────────────────────── -->
        <el-dialog v-model="runVisible" :title="$t('fa_run_depreciation')" :width="dialogWidth(680)" destroy-on-close>
            <div class="run-head">
                <span>{{ $t('fa_charge_through') }}</span>
                <el-date-picker
                    v-model="runMonth"
                    type="month"
                    format="YYYY-MM"
                    value-format="YYYY-MM"
                    :disabled-date="monthNotEnded"
                    :clearable="false"
                    @change="loadRunPreview"
                />
            </div>
            <p class="muted">{{ $t('fa_run_hint') }}</p>

            <div v-if="runLoading" class="py-2"><el-skeleton :rows="4" animated /></div>
            <template v-else-if="runResult">
                <p class="callout" :class="runResult.blocked.length ? 'is-warn' : 'is-ok'">
                    <i class="fas" :class="runResult.blocked.length ? 'fa-triangle-exclamation' : 'fa-circle-check'"></i>
                    {{ $t('fa_run_done', { n: runResult.entries, amount: money(runResult.total) }) }}
                </p>
                <div v-for="b in runResult.blocked" :key="b.asset_number" class="blocked">
                    <span class="code-badge">{{ b.asset_number }}</span> <b>{{ b.name }}</b>
                    <p>{{ b.reason }}</p>
                </div>
            </template>
            <template v-else-if="runPreview">
                <div v-if="runPreview.assets.length" class="run-list">
                    <div v-for="row in runPreview.assets" :key="row.id" class="run-row">
                        <div class="asset-cell">
                            <span class="code-badge">{{ row.asset_number }}</span>
                            <div class="asset-name">
                                <strong>{{ row.name }}</strong>
                                <small>{{ monthsLabel(row.months) }}</small>
                            </div>
                        </div>
                        <div class="ta-end">
                            <b class="amt">{{ plain(row.amount) }}</b>
                            <small class="sub">{{ $t('fa_nbv_after') }} {{ plain(row.net_book_value_after) }}</small>
                        </div>
                    </div>
                    <div class="run-row is-total">
                        <span>{{ $t('fa_entries_count', { n: runPreview.entries }) }}</span>
                        <b class="amt">{{ money(runPreview.total) }}</b>
                    </div>
                </div>
                <div v-else class="empty is-small">
                    <i class="fas fa-circle-check"></i>
                    <strong>{{ $t('fa_up_to_date', { month: monthLabel(runPreview.month) }) }}</strong>
                </div>
            </template>

            <template #footer>
                <el-button @click="runVisible = false">{{ runResult ? $t('close') : $t('cancel') }}</el-button>
                <el-button
                    v-if="!runResult"
                    type="primary"
                    :loading="saving"
                    :disabled="!runPreview || !runPreview.assets.length"
                    @click="confirmRun"
                >
                    {{ $t('fa_post_entries', { n: runPreview?.entries || 0 }) }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { fixedAssetsApi } from '@/api/accountingReports';
import { suppliersApi } from '@/api/suppliers';
import { formatMoney, formatNumber, baseCurrencyCode, numberLocale } from '@/utils/currency';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

const baseCode = baseCurrencyCode();
const money = (value) => formatMoney(value || 0);
const plain = (value) => new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
const formatPct = (value) => `${new Intl.NumberFormat(numberLocale(), { maximumFractionDigits: 1 }).format(Number(value || 0))}%`;

const localDate = (d) => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const todayIso = localDate(new Date());
const dateOnly = (value) => String(value || '').slice(0, 10);
const ym = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
const addMonths = (month, n) => {
    const [y, m] = month.split('-').map(Number);
    return ym(new Date(y, m - 1 + n, 1));
};
const monthsBetween = (from, to) => {
    const [fy, fm] = from.split('-').map(Number);
    const [ty, tm] = to.split('-').map(Number);
    return (ty - fy) * 12 + (tm - fm);
};
const lastCompletedMonth = addMonths(ym(new Date()), -1);

const monthLabel = (month) => {
    if (!month) return '—';
    const [y, m] = month.split('-').map(Number);
    return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'ar-SY', { month: 'short', year: 'numeric' }).format(new Date(y, m - 1, 1));
};

const monthsLabel = (months) => (months.length === 1
    ? monthLabel(months[0])
    : `${monthLabel(months[0])} → ${monthLabel(months[months.length - 1])} · ${t('fa_n_months', { n: months.length })}`);

const lifeLabel = (months) => {
    const n = Number(months || 0);
    return n % 12 === 0 ? t('fa_years', { n: n / 12 }) : t('fa_n_months', { n });
};

const isFuture = (date) => localDate(date) > todayIso;
const monthNotEnded = (date) => ym(date) > lastCompletedMonth;

const dialogWidth = (px) => (window.innerWidth < px + 32 ? '94%' : `${px}px`);
const drawerSize = window.innerWidth < 720 ? '100%' : '620px';

const resultClass = (value) => (Number(value) > 0.004 ? 'is-gain' : Number(value) < -0.004 ? 'is-loss' : '');
const resultLabel = (value) => {
    const v = Number(value || 0);
    if (Math.abs(v) < 0.005) return t('fa_no_gain_or_loss');
    return `${v > 0 ? t('fa_gain') : t('fa_loss')} ${plain(Math.abs(v))}`;
};

const journalLink = (entry) => ({ path: '/admin/accounting/journal', query: { search: entry.entry_number } });

/* ------------------------------------------------------------------ *
 * Register
 * ------------------------------------------------------------------ */

const assets = ref([]);
const summary = ref(null);
const categories = ref([]);
const warehouses = ref([]);
const pagination = ref({ total: 0, per_page: 20 });
const page = ref(1);
const perPage = ref(20);
const loading = ref(false);
const error = ref('');

const filters = reactive({ status: 'active', search: '', category: '' });

const statusOptions = computed(() => [
    { label: `${t('in_use')}${summary.value ? ` (${formatNumber(summary.value.active_count)})` : ''}`, value: 'active' },
    { label: `${t('disposed')}${summary.value ? ` (${formatNumber(summary.value.disposed_count)})` : ''}`, value: 'disposed' },
    { label: t('fa_all'), value: 'all' },
]);

const hasFilters = computed(() => Boolean(filters.search || filters.category) || filters.status !== 'active');

const usedPercent = computed(() => {
    const cost = Number(summary.value?.cost || 0);
    return cost > 0 ? Math.round((Number(summary.value.accumulated_depreciation) / cost) * 1000) / 10 : 0;
});

const depState = computed(() => {
    if (!summary.value?.active_count) return 'is-empty';
    return summary.value.depreciation.behind_count > 0 ? 'is-behind' : 'is-ok';
});

const syncUrl = () => {
    const query = {};
    if (filters.status !== 'active') query.status = filters.status;
    if (filters.search) query.search = filters.search;
    if (filters.category) query.category = filters.category;
    if (page.value > 1) query.page = page.value;
    if (detailVisible.value && detail.value) query.asset = detail.value.id;
    router.replace({ query });
};

const reload = async () => {
    loading.value = true;
    error.value = '';
    syncUrl();
    try {
        const res = await fixedAssetsApi.getAll({
            status: filters.status,
            search: filters.search || undefined,
            category: filters.category || undefined,
            page: page.value,
            per_page: perPage.value,
        });
        const data = res.data?.data || {};
        assets.value = data.assets || [];
        summary.value = data.summary || null;
        categories.value = data.categories || [];
        warehouses.value = data.warehouses || [];
        pagination.value = data.pagination || { total: 0, per_page: perPage.value };
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const applyFilters = () => {
    page.value = 1;
    reload();
};

let searchTimer = null;
const onSearch = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
};

const clearFilters = () => {
    filters.status = 'active';
    filters.search = '';
    filters.category = '';
    applyFilters();
};

const suggestCategories = (query, cb) => {
    const q = String(query || '').toLowerCase();
    cb(categories.value.filter((c) => c.toLowerCase().includes(q)).map((value) => ({ value })));
};

/* ------------------------------------------------------------------ *
 * Validation errors from the server, shown on their fields
 * ------------------------------------------------------------------ */

const errors = ref({});
const fieldError = (field) => errors.value[field]?.[0] || '';

const failWith = (e) => {
    errors.value = e.response?.status === 422 ? e.response.data?.errors || {} : {};
    ElMessage.error(e.response?.data?.message || t('failed_to_save_asset'));
};

/* ------------------------------------------------------------------ *
 * Detail
 * ------------------------------------------------------------------ */

const detailVisible = ref(false);
const detailLoading = ref(false);
const detail = ref(null);
const openYears = ref(new Set());

const fetchDetail = async (id) => {
    const res = await fixedAssetsApi.get(id);
    return res.data?.data || null;
};

const openDetail = async (row) => {
    detailVisible.value = true;
    detailLoading.value = true;
    if (detail.value?.id !== row.id) detail.value = null;
    try {
        detail.value = await fetchDetail(row.id);
        // Open the year that is happening now, or the first one.
        const years = scheduleByYear.value.map((g) => g.year);
        const current = String(new Date().getFullYear());
        openYears.value = new Set([years.includes(current) ? current : years[0]]);
        syncUrl();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_load_report'));
        detailVisible.value = false;
    } finally {
        detailLoading.value = false;
    }
};

const share = (value) => {
    const cost = Number(detail.value?.cost || 0);
    return cost > 0 ? `${Math.max(0, (Number(value || 0) / cost) * 100)}%` : '0%';
};

const scheduleByYear = computed(() => {
    const groups = [];
    for (const row of detail.value?.schedule || []) {
        const year = row.month.slice(0, 4);
        let group = groups[groups.length - 1];
        if (!group || group.year !== year) {
            group = { year, rows: [], total: 0, nbvEnd: 0 };
            groups.push(group);
        }
        group.rows.push(row);
        group.total += Number(row.charge);
        group.nbvEnd = row.net_book_value_after;
    }
    return groups;
});

const scheduleCounts = computed(() => ({
    due: (detail.value?.schedule || []).filter((r) => r.state === 'due').length,
}));

const toggleYear = (year) => {
    const next = new Set(openYears.value);
    next.has(year) ? next.delete(year) : next.add(year);
    openYears.value = next;
};

const refreshDetail = async () => {
    if (detailVisible.value && detail.value) {
        detail.value = await fetchDetail(detail.value.id).catch(() => detail.value);
    }
};

/* ------------------------------------------------------------------ *
 * Register an asset
 * ------------------------------------------------------------------ */

const createVisible = ref(false);
const saving = ref(false);
const suppliers = ref([]);
const suppliersLoading = ref(false);

const blankForm = () => ({
    name: '',
    category: '',
    warehouse_id: null,
    cost: undefined,
    salvage_value: 0,
    useful_life_months: 60,
    acquired_on: todayIso,
    settlement: 'credit',
    supplier_id: null,
    notes: '',
});

const form = reactive(blankForm());

const settlementOptions = computed(() => [
    { label: t('on_account'), value: 'credit' },
    { label: t('cash'), value: 'cash' },
    { label: t('bank_transfer'), value: 'bank' },
]);

const receiptOptions = computed(() => [
    { label: t('cash'), value: 'cash' },
    { label: t('bank_transfer'), value: 'bank' },
]);

const creditAccountLabel = computed(() => {
    if (form.settlement === 'cash') return t('cash');
    if (form.settlement === 'bank') return t('bank_transfer');
    const supplier = suppliers.value.find((s) => s.id === form.supplier_id);
    return supplier ? `${t('fa_payables')} · ${supplier.name}` : t('fa_payables');
});

const salvageError = computed(() => (
    Number(form.cost) > 0 && Number(form.salvage_value) >= Number(form.cost) ? t('fa_salvage_too_high') : ''
));

/** Straight-line, rounded per month as the server posts it. */
const plan = computed(() => {
    const cost = Number(form.cost) || 0;
    const salvage = Number(form.salvage_value) || 0;
    const months = Number(form.useful_life_months) || 0;
    if (cost <= 0 || cost <= salvage || months <= 0 || !form.acquired_on) return null;

    const depreciable = cost - salvage;
    const first = form.acquired_on.slice(0, 7);
    const monthly = Math.round((depreciable / months) * 100) / 100;
    const count = monthly > 0 ? Math.ceil(Math.round((depreciable / monthly) * 1e6) / 1e6) : months;

    return {
        depreciable,
        monthly,
        first,
        last: addMonths(first, count - 1),
        // Months already ended that the next run will charge at once.
        behind: Math.max(0, Math.min(count, monthsBetween(first, lastCompletedMonth) + 1)),
    };
});

const canSubmit = computed(() => (
    form.name.trim()
    && Number(form.cost) > 0
    && Number(form.useful_life_months) > 0
    && form.acquired_on
    && !salvageError.value
    && (form.settlement !== 'credit' || form.supplier_id)
));

const loadSuppliers = async () => {
    if (suppliers.value.length) return;
    suppliersLoading.value = true;
    try {
        const res = await suppliersApi.getAll({ per_page: 500 });
        suppliers.value = res.data?.data?.suppliers || [];
    } catch {
        // A missing supplier list does not stop a cash purchase.
    } finally {
        suppliersLoading.value = false;
    }
};

const openCreate = () => {
    Object.assign(form, blankForm());
    errors.value = {};
    createVisible.value = true;
    loadSuppliers();
};

const submit = async () => {
    saving.value = true;
    errors.value = {};
    try {
        await fixedAssetsApi.create({
            name: form.name.trim(),
            category: form.category?.trim() || null,
            warehouse_id: form.warehouse_id || null,
            cost: Number(form.cost),
            salvage_value: Number(form.salvage_value) || 0,
            useful_life_months: Number(form.useful_life_months),
            acquired_on: form.acquired_on,
            settlement: form.settlement,
            supplier_id: form.settlement === 'credit' ? form.supplier_id : null,
            notes: form.notes?.trim() || null,
        });

        createVisible.value = false;
        ElMessage.success(t('asset_registered'));
        await reload();
    } catch (e) {
        failWith(e);
    } finally {
        saving.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Edit
 * ------------------------------------------------------------------ */

const editVisible = ref(false);
const editingId = ref(null);
const editForm = reactive({ name: '', category: '', warehouse_id: null, notes: '' });

const openEdit = (asset) => {
    editingId.value = asset.id;
    Object.assign(editForm, {
        name: asset.name || '',
        category: asset.category || '',
        warehouse_id: asset.warehouse_id || null,
        notes: asset.notes || '',
    });
    errors.value = {};
    editVisible.value = true;
};

const saveEdit = async () => {
    saving.value = true;
    errors.value = {};
    try {
        await fixedAssetsApi.update(editingId.value, {
            name: editForm.name.trim(),
            category: editForm.category?.trim() || null,
            warehouse_id: editForm.warehouse_id || null,
            notes: editForm.notes?.trim() || null,
        });
        editVisible.value = false;
        ElMessage.success(t('fa_asset_updated'));
        await Promise.all([reload(), refreshDetail()]);
    } catch (e) {
        failWith(e);
    } finally {
        saving.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Dispose
 * ------------------------------------------------------------------ */

const disposeVisible = ref(false);
const disposing = ref(null);
const disposeForm = reactive({ disposed_on: todayIso, proceeds: 0, settlement: 'cash', charge_to_date: true });

const openDispose = async (asset) => {
    disposing.value = null;
    Object.assign(disposeForm, { disposed_on: todayIso, proceeds: 0, settlement: 'cash', charge_to_date: true });
    errors.value = {};
    disposeVisible.value = true;
    try {
        // The schedule says which months are still owed before it goes.
        disposing.value = detail.value?.id === asset.id ? detail.value : await fetchDetail(asset.id);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_load_report'));
        disposeVisible.value = false;
    }
};

const disposalDateDisabled = (date) => {
    const d = localDate(date);
    if (d > todayIso || !disposing.value) return true;
    if (d < dateOnly(disposing.value.acquired_on)) return true;
    const through = dateOnly(disposing.value.depreciated_through);
    return Boolean(through) && d.slice(0, 7) < through.slice(0, 7);
};

/** The months used before the disposal month that have not been charged yet. */
const catchUp = computed(() => {
    const month = String(disposeForm.disposed_on || '').slice(0, 7);
    const rows = (disposing.value?.schedule || []).filter((r) => r.state !== 'posted' && r.month < month && r.month <= lastCompletedMonth);
    return { months: rows.length, amount: rows.reduce((sum, r) => sum + Number(r.charge), 0) };
});

const disposalAccumulated = computed(() => (
    Number(disposing.value?.accumulated_depreciation || 0) + (disposeForm.charge_to_date ? catchUp.value.amount : 0)
));
const disposalCarrying = computed(() => Number(disposing.value?.cost || 0) - disposalAccumulated.value);
const disposalResult = computed(() => Math.round((Number(disposeForm.proceeds || 0) - disposalCarrying.value) * 100) / 100);

const confirmDispose = async () => {
    saving.value = true;
    errors.value = {};
    try {
        await fixedAssetsApi.dispose(disposing.value.id, {
            disposed_on: disposeForm.disposed_on,
            proceeds: Number(disposeForm.proceeds) || 0,
            settlement: disposeForm.settlement,
            charge_to_date: disposeForm.charge_to_date,
        });

        disposeVisible.value = false;
        ElMessage.success(t('asset_disposed'));
        await Promise.all([reload(), refreshDetail()]);
    } catch (e) {
        failWith(e);
    } finally {
        saving.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Depreciation run
 * ------------------------------------------------------------------ */

const runVisible = ref(false);
const runMonth = ref(lastCompletedMonth);
const runPreview = ref(null);
const runResult = ref(null);
const runLoading = ref(false);

const loadRunPreview = async () => {
    runLoading.value = true;
    runResult.value = null;
    try {
        const res = await fixedAssetsApi.depreciationPreview({ month: runMonth.value });
        runPreview.value = res.data?.data || null;
    } catch (e) {
        runPreview.value = null;
        ElMessage.error(e.response?.data?.message || t('failed_to_load_report'));
    } finally {
        runLoading.value = false;
    }
};

const openRun = () => {
    runMonth.value = lastCompletedMonth;
    runPreview.value = null;
    runResult.value = null;
    runVisible.value = true;
    loadRunPreview();
};

const confirmRun = async () => {
    saving.value = true;
    try {
        const res = await fixedAssetsApi.depreciate({ month: runMonth.value });
        runResult.value = res.data?.data || null;
        await Promise.all([reload(), refreshDetail()]);
    } catch (e) {
        if (e.response?.data?.data?.blocked) {
            runResult.value = e.response.data.data;
        } else {
            ElMessage.error(e.response?.data?.message || t('failed_to_save_asset'));
        }
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    const q = route.query;
    filters.status = ['active', 'disposed', 'all'].includes(q.status) ? q.status : 'active';
    filters.search = q.search ? String(q.search) : '';
    filters.category = q.category ? String(q.category) : '';
    page.value = Math.max(1, Number(q.page) || 1);
    const assetId = Number(q.asset) || null;
    await reload();
    if (assetId) openDetail({ id: assetId });
});
</script>

<style scoped>
/* Same palette as the other accounting screens: every text colour reaches at
   least 4.5:1 on white. What has been used up is amber, what is left is blue. */
.accounting-page {
    font-family: 'Cairo', sans-serif;
    --ov-radius: 14px;
    --ov-surface: #ffffff;
    --ov-soft: #f8fafc;
    --ov-border: #e2e8f0;
    --ov-text: #0f172a;
    --ov-body: #334155;
    --ov-muted: #475569;
    --ov-subtle: #64748b;
    --c-used: #b45309;
    --c-used-bar: #f59e0b;
    --c-left: #1d4ed8;
    --c-left-bar: #93c5fd;
    color: var(--ov-body);
}

.currency-tag { align-self: center; color: var(--ov-muted); border-color: var(--ov-border); font-weight: 600; }
.mr-1 { margin-inline-end: 0.35rem; }
.muted { color: var(--ov-muted); font-size: 0.85rem; }
.ta-end { text-align: end; }
.amt { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--ov-text); unicode-bidi: isolate; }
.sub { display: block; font-size: 0.75rem; color: var(--ov-subtle); font-weight: 600; margin-top: 0.1rem; }
.is-gain { color: #047857 !important; }
.is-loss { color: #b91c1c !important; }
.dir-arrow { font-size: 0.8em; }
[dir="rtl"] .dir-arrow { transform: scaleX(-1); }

.fa-card {
    background: var(--ov-surface);
    border: 1px solid var(--ov-border);
    border-radius: var(--ov-radius);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    padding: 1rem 1.1rem;
    margin-bottom: 1rem;
    min-width: 0;
}

.fa-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin: 0 0 0.9rem;
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--ov-border);
}

.fa-card-head h2 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.6rem; }
.fa-card-head h2 i { width: 30px; height: 30px; display: inline-grid; place-items: center; border-radius: 9px; font-size: 0.85rem; color: #1d4ed8; background: #dbeafe; }

.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
.t-search { width: 220px; }
.t-category { width: 170px; }
@media (max-width: 640px) { .t-search, .t-category { width: 100%; } }

/* ── Depreciation status ──────────────────────────────────────────────── */
.dep-status {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.8rem 1rem;
    padding: 0.85rem 1.1rem;
    margin-bottom: 1rem;
    border: 1px solid;
    border-inline-start-width: 4px;
    border-radius: var(--ov-radius);
}
.dep-status.is-ok { background: #f0fdf4; border-color: #bbf7d0; border-inline-start-color: #047857; --d: #065f46; }
.dep-status.is-behind { background: #fffbeb; border-color: #fde68a; border-inline-start-color: #d97706; --d: #92400e; }
.dep-status.is-empty { background: var(--ov-soft); border-color: var(--ov-border); border-inline-start-color: var(--ov-subtle); --d: var(--ov-muted); }
.dep-icon { width: 36px; height: 36px; flex-shrink: 0; display: grid; place-items: center; border-radius: 10px; background: #fff; color: var(--d); }
.dep-text { display: flex; flex-direction: column; flex: 1; min-width: 220px; }
.dep-text strong { color: var(--d); font-size: 0.92rem; }
.dep-text span { font-size: 0.8rem; color: var(--ov-body); line-height: 1.6; }

/* ── KPIs ─────────────────────────────────────────────────────────────── */
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
.kpis .fa-card { margin-bottom: 0; }
.kpi { display: flex; flex-direction: column; gap: 0.3rem; }
.kpi.is-accent { border-top: 3px solid var(--c-left); }
.kpi-label { font-size: 0.8rem; font-weight: 700; color: var(--ov-muted); display: flex; align-items: center; gap: 0.4rem; }
.kpi-label i { color: var(--ov-subtle); }
.kpi-value { font-size: 1.45rem; font-weight: 800; color: var(--ov-text); font-variant-numeric: tabular-nums; unicode-bidi: isolate; line-height: 1.25; }
.kpi-value.is-used { color: var(--c-used); }
.kpi.is-accent .kpi-value { color: var(--c-left); }
.kpi-sub { font-size: 0.76rem; font-weight: 600; color: var(--ov-subtle); }

.meter { display: block; height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; flex: 1; min-width: 60px; }
.meter > span { display: block; height: 100%; border-radius: 999px; background: var(--c-used-bar); }
.meter.is-full > span { background: #94a3b8; }

/* ── Register ─────────────────────────────────────────────────────────── */
.register :deep(.clickable) { cursor: pointer; }
.register :deep(th .cell) { font-size: 0.76rem; font-weight: 700; color: var(--ov-subtle); }
.asset-cell { display: flex; align-items: flex-start; gap: 0.6rem; min-width: 0; }
.asset-name { display: flex; flex-direction: column; min-width: 0; }
.asset-name strong { color: var(--ov-text); font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.asset-name small { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; font-size: 0.74rem; color: var(--ov-subtle); font-weight: 600; }
.chip { display: inline-block; padding: 0 0.45rem; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 0.72rem; font-weight: 700; }
.progress-line { display: flex; align-items: center; gap: 0.5rem; }
.pct { font-size: 0.76rem; font-weight: 700; color: var(--ov-text); font-variant-numeric: tabular-nums; min-width: 42px; text-align: end; }
.row-actions { display: inline-flex; gap: 0.1rem; }

.tag { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.1rem 0.6rem; border-radius: 999px; font-size: 0.74rem; font-weight: 700; }
.tag.is-ok { color: #065f46; background: #d1fae5; }
.tag.is-info { color: #1e40af; background: #dbeafe; }
.tag.is-muted { color: var(--ov-muted); background: #f1f5f9; }

.code-badge {
    display: inline-block;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: var(--ov-text);
    padding: 0 0.4rem;
    border-radius: 6px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.74rem;
    direction: ltr;
    white-space: nowrap;
}

.empty { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 2.5rem 1rem; text-align: center; }
.empty > i { font-size: 2rem; color: #cbd5e1; }
.empty strong { color: var(--ov-text); }
.empty p { margin: 0; max-width: 460px; font-size: 0.84rem; color: var(--ov-muted); line-height: 1.7; }
.empty.is-small { padding: 1.5rem 1rem; }
.empty.is-small > i { color: #047857; font-size: 1.5rem; }

.pager { display: flex; justify-content: flex-end; margin-top: 0.9rem; overflow-x: auto; }

/* ── Detail ───────────────────────────────────────────────────────────── */
.detail { display: flex; flex-direction: column; gap: 0.9rem; }
.detail-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
.spacer { flex: 1; }

.split-bar { display: flex; height: 12px; border-radius: 999px; overflow: hidden; background: #e2e8f0; }
.split-bar span { display: block; height: 100%; }
.s-used { background: var(--c-used-bar); }
.s-left { background: var(--c-left-bar); }
.s-salvage { background: #cbd5e1; }
.split-legend { display: flex; flex-wrap: wrap; gap: 0.4rem 1rem; margin-top: 0.5rem; font-size: 0.78rem; color: var(--ov-muted); }
.split-legend b { color: var(--ov-text); font-variant-numeric: tabular-nums; margin-inline-start: 0.2rem; }
.dot { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-inline-end: 0.3rem; }

.facts { margin: 0; display: flex; flex-direction: column; }
.facts.two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 1.2rem; }
@media (max-width: 520px) { .facts.two { grid-template-columns: 1fr; } }
.facts div { display: flex; justify-content: space-between; gap: 1rem; padding: 0.4rem 0; border-bottom: 1px dashed var(--ov-border); font-size: 0.84rem; }
.facts dt { color: var(--ov-muted); font-weight: 600; }
.facts dd { margin: 0; color: var(--ov-text); font-weight: 700; font-variant-numeric: tabular-nums; unicode-bidi: isolate; text-align: end; }

.notes { margin: 0; padding: 0.6rem 0.8rem; border-radius: 10px; background: #fefce8; font-size: 0.84rem; color: var(--ov-body); }
.notes i { color: #a16207; margin-inline-end: 0.35rem; }

.sub-head { margin: 0.3rem 0 0; font-size: 0.9rem; font-weight: 800; color: var(--ov-text); display: flex; align-items: center; gap: 0.5rem; }
.due-note { font-size: 0.74rem; font-weight: 700; color: #92400e; background: #fef3c7; border-radius: 999px; padding: 0 0.5rem; }

.entries { display: flex; flex-direction: column; gap: 0.4rem; }
.entry-link { display: flex; align-items: center; gap: 0.6rem; padding: 0.5rem 0.7rem; border: 1px solid var(--ov-border); border-radius: 10px; text-decoration: none; color: var(--ov-body); font-size: 0.82rem; }
.entry-link:hover { border-color: #93c5fd; background: #eff6ff; }
.entry-link b { margin-inline-start: auto; }

.schedule { display: flex; flex-direction: column; border: 1px solid var(--ov-border); border-radius: 10px; overflow: hidden; }
.s-row { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 0.9fr); gap: 0.5rem; align-items: center; padding: 0.4rem 0.7rem; font-size: 0.8rem; border-bottom: 1px dashed var(--ov-border); }
.s-row:last-child { border-bottom: 0; }
.s-row.is-head { font-size: 0.72rem; font-weight: 700; color: var(--ov-subtle); background: var(--ov-soft); border-bottom-style: solid; }
.s-row.is-year { width: 100%; border: 0; border-bottom: 1px solid var(--ov-border); background: #f1f5f9; font: inherit; font-size: 0.82rem; font-weight: 800; color: var(--ov-text); cursor: pointer; text-align: start; }
.s-row.is-planned { color: var(--ov-subtle); }
.s-row.is-planned .amt { color: var(--ov-muted); }
.s-row.is-due { background: #fffbeb; }
.caret { font-size: 0.7em; transition: transform 0.15s; margin-inline-end: 0.3rem; }
.caret.is-closed { transform: rotate(-90deg); }
[dir="rtl"] .caret.is-closed { transform: rotate(90deg); }
.state { font-size: 0.72rem; font-weight: 700; border-radius: 999px; padding: 0 0.5rem; white-space: nowrap; text-decoration: none; }
.state.is-posted { color: #065f46; background: #d1fae5; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; direction: ltr; display: inline-block; }
.state.is-due { color: #92400e; background: #fef3c7; }
.state.is-planned { color: var(--ov-muted); background: #f1f5f9; }

/* ── Dialogs ──────────────────────────────────────────────────────────── */
.create-grid { display: grid; grid-template-columns: minmax(0, 1fr) 250px; gap: 1.2rem; }
@media (max-width: 760px) { .create-grid { grid-template-columns: 1fr; } }
.form-section { margin: 0 0 0.6rem; font-size: 0.8rem; font-weight: 800; color: var(--ov-subtle); text-transform: uppercase; letter-spacing: 0.02em; }
.form-section:not(:first-child) { margin-top: 0.4rem; padding-top: 0.8rem; border-top: 1px solid var(--ov-border); }

.life-presets { display: flex; gap: 0.3rem; margin-top: 0.35rem; flex-wrap: wrap; }
.life-presets button { border: 1px solid var(--ov-border); background: #fff; border-radius: 999px; padding: 0 0.6rem; font: inherit; font-size: 0.74rem; font-weight: 700; color: var(--ov-muted); cursor: pointer; line-height: 1.8; }
.life-presets button.is-on, .life-presets button:hover { border-color: #93c5fd; color: #1d4ed8; background: #eff6ff; }

.preview { background: var(--ov-soft); border: 1px solid var(--ov-border); border-radius: 12px; padding: 0.9rem; align-self: start; display: flex; flex-direction: column; gap: 0.7rem; }
.preview h4 { margin: 0; font-size: 0.86rem; font-weight: 800; color: var(--ov-text); }
.entry-preview { display: flex; flex-direction: column; gap: 0.25rem; }
.entry-preview small { font-size: 0.72rem; font-weight: 700; color: var(--ov-subtle); }
.je { display: grid; grid-template-columns: 26px minmax(0, 1fr) auto; gap: 0.4rem; font-size: 0.78rem; padding: 0.3rem 0.45rem; border-radius: 8px; background: #fff; border: 1px solid var(--ov-border); }
.je span:first-child { font-weight: 800; color: #1d4ed8; }
.je.is-cr span:first-child { color: #0f766e; }
.je.is-cr span:nth-child(2) { padding-inline-start: 0.6rem; }
.je b { font-variant-numeric: tabular-nums; color: var(--ov-text); }

.callout { display: flex; gap: 0.5rem; align-items: baseline; margin: 0.4rem 0 0; padding: 0.55rem 0.75rem; border-radius: 10px; background: var(--ov-soft); font-size: 0.8rem; line-height: 1.6; color: var(--ov-body); }
.callout.is-warn { background: #fffbeb; color: #78350f; }
.callout.is-ok { background: #f0fdf4; color: #065f46; }

.catch-up { display: flex; align-items: center; gap: 0.6rem; padding: 0.55rem 0.75rem; border-radius: 10px; background: #fffbeb; font-size: 0.82rem; color: #78350f; margin-bottom: 0.8rem; }

.dispose-sum { display: flex; flex-direction: column; border: 1px solid var(--ov-border); border-radius: 12px; overflow: hidden; }
.dispose-sum div { display: flex; justify-content: space-between; padding: 0.45rem 0.8rem; font-size: 0.84rem; border-bottom: 1px dashed var(--ov-border); }
.dispose-sum div:last-child { border-bottom: 0; }
.dispose-sum span { color: var(--ov-muted); font-weight: 600; }
.dispose-sum b { font-variant-numeric: tabular-nums; color: var(--ov-text); unicode-bidi: isolate; }
.dispose-sum .is-sub { background: var(--ov-soft); }
.dispose-sum .is-result { background: #f1f5f9; font-size: 0.95rem; }
.dispose-sum .is-result span, .dispose-sum .is-result b { color: inherit; font-weight: 800; }

.run-head { display: flex; align-items: center; gap: 0.7rem; flex-wrap: wrap; margin-bottom: 0.4rem; font-weight: 700; color: var(--ov-text); }
.run-list { display: flex; flex-direction: column; border: 1px solid var(--ov-border); border-radius: 12px; overflow: hidden; max-height: 360px; overflow-y: auto; }
.run-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.55rem 0.8rem; border-bottom: 1px dashed var(--ov-border); }
.run-row.is-total { position: sticky; bottom: 0; background: #f1f5f9; border-bottom: 0; font-weight: 800; color: var(--ov-text); }
.blocked { padding: 0.55rem 0.75rem; border-radius: 10px; background: #fef2f2; margin-top: 0.4rem; font-size: 0.82rem; }
.blocked p { margin: 0.2rem 0 0; color: #991b1b; }
</style>
