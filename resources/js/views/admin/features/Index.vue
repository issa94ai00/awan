<template>
    <div class="features-page">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-info">
                <div class="header-breadcrumbs">
                    <span class="eyebrow">{{ $t('system_management') }}</span>
                    <el-tag size="small" effect="plain" type="info" class="version-tag">ERP 2.0</el-tag>
                </div>
                <h2>{{ $t('features_title') }}</h2>
                <p class="subtitle">{{ $t('features_subtitle') }}</p>
            </div>

            <div class="header-actions">
                <el-radio-group v-model="viewMode" size="default" class="view-switcher">
                    <el-radio-button label="cards">
                        <el-icon class="mr-1"><Grid /></el-icon>
                        <span>{{ $t('fm_view_cards') }}</span>
                    </el-radio-button>
                    <el-radio-button label="table">
                        <el-icon class="mr-1"><Document /></el-icon>
                        <span>{{ $t('fm_view_table') }}</span>
                    </el-radio-button>
                    <el-radio-button label="matrix">
                        <el-icon class="mr-1"><Collection /></el-icon>
                        <span>{{ $t('fm_view_matrix') }}</span>
                    </el-radio-button>
                </el-radio-group>

                <el-button :icon="Refresh" class="refresh-btn" :loading="loading" @click="fetchFeatures">
                    {{ $t('refresh') }}
                </el-button>
            </div>
        </div>

        <!-- Interactive KPI Stat Cards (Click to filter) -->
        <div class="stats-grid">
            <div
                class="stat-card total"
                :class="{ active: statusFilter === 'all' && !groupFilter && !roleFilter }"
                @click="resetFilters"
            >
                <div class="icon-wrap total-icon"><el-icon><Grid /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t('fm_stat_total') }}</span>
                    <strong>{{ stats.total }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>

            <div
                class="stat-card success"
                :class="{ active: statusFilter === 'enabled' }"
                @click="setStatusFilter('enabled')"
            >
                <div class="icon-wrap success"><el-icon><CircleCheck /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t('fm_stat_enabled') }}</span>
                    <strong>{{ stats.enabled }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>

            <div
                class="stat-card danger"
                :class="{ active: statusFilter === 'disabled' }"
                @click="setStatusFilter('disabled')"
            >
                <div class="icon-wrap danger"><el-icon><CircleClose /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t('fm_stat_disabled') }}</span>
                    <strong>{{ stats.disabled }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>

            <div
                class="stat-card warning"
                :class="{ active: statusFilter === 'core' }"
                @click="setStatusFilter('core')"
            >
                <div class="icon-wrap warning"><el-icon><Lock /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t('fm_stat_core') }}</span>
                    <strong>{{ stats.core }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>

            <div class="stat-card muted" @click="groupFilter = null">
                <div class="icon-wrap muted"><el-icon><Collection /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t('fm_stat_groups') }}</span>
                    <strong>{{ stats.groups }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>
        </div>

        <!-- Toolbar & Filter Bar -->
        <el-card shadow="never" class="toolbar-card">
            <div class="toolbar">
                <el-input
                    v-model="search"
                    :placeholder="$t('fm_search_routes')"
                    clearable
                    :prefix-icon="Search"
                    class="search-input"
                />

                <el-radio-group v-model="statusFilter" size="default" class="filter-radio-group">
                    <el-radio-button label="all">{{ $t('fm_filter_all') }}</el-radio-button>
                    <el-radio-button label="enabled">
                        <span class="flex items-center gap-1">
                            <span class="status-dot on"></span>
                            {{ $t('fm_filter_enabled') }}
                        </span>
                    </el-radio-button>
                    <el-radio-button label="disabled">
                        <span class="flex items-center gap-1">
                            <span class="status-dot off"></span>
                            {{ $t('fm_filter_disabled') }}
                        </span>
                    </el-radio-button>
                    <el-radio-button label="core">{{ $t('fm_filter_core') }}</el-radio-button>
                </el-radio-group>

                <el-select
                    v-model="groupFilter"
                    :placeholder="$t('fm_all_groups')"
                    clearable
                    class="group-select"
                >
                    <el-option
                        v-for="group in groupOrder"
                        :key="group"
                        :label="$t('fm_group_' + group)"
                        :value="group"
                    >
                        <div class="flex items-center justify-between w-full">
                            <span>{{ $t('fm_group_' + group) }}</span>
                            <el-badge
                                :value="features.filter(f => f.group === group).length"
                                type="info"
                                class="item-count-badge"
                            />
                        </div>
                    </el-option>
                </el-select>

                <el-select
                    v-model="roleFilter"
                    :placeholder="$t('fm_filter_role')"
                    clearable
                    class="role-select"
                >
                    <el-option
                        v-for="role in roles"
                        :key="role.id"
                        :label="role.display_name || role.name"
                        :value="role.id"
                    >
                        <div class="flex items-center justify-between w-full">
                            <span>{{ role.display_name || role.name }}</span>
                            <small class="text-slate-400" v-if="role.is_super">({{ $t('fm_super_always') }})</small>
                            <small class="text-slate-400" v-else>({{ role.users_count }} {{ $t('fm_role_users') }})</small>
                        </div>
                    </el-option>
                </el-select>

                <el-button
                    v-if="search || statusFilter !== 'all' || groupFilter || roleFilter"
                    text
                    type="primary"
                    :icon="Close"
                    @click="resetFilters"
                >
                    {{ $t('fm_reset_filters') }}
                </el-button>

                <!-- Bulk Selection Floating / Inline Bar -->
                <transition name="fade">
                    <div v-if="selectedKeys.length && viewMode !== 'matrix'" class="bulk-actions">
                        <el-tag type="info" effect="dark" round>
                            {{ $t('fm_selected', { count: selectedKeys.length }) }}
                        </el-tag>
                        <el-button
                            size="small"
                            type="success"
                            plain
                            :icon="CircleCheck"
                            :loading="bulkSaving"
                            @click="bulkToggle(true)"
                        >
                            {{ $t('fm_bulk_enable') }}
                        </el-button>
                        <el-button
                            size="small"
                            type="danger"
                            plain
                            :icon="CircleClose"
                            :loading="bulkSaving"
                            @click="bulkToggle(false)"
                        >
                            {{ $t('fm_bulk_disable') }}
                        </el-button>
                    </div>
                </transition>
            </div>
        </el-card>

        <!-- VIEW MODE 1: CARDS & GROUPS VIEW (Default) -->
        <div v-if="viewMode === 'cards'" v-loading="loading" class="cards-view">
            <template v-for="group in activeGroups" :key="group">
                <div v-if="groupedFeatures[group]?.length" class="group-section">
                    <div class="group-header">
                        <div class="group-title-wrap">
                            <div class="group-icon-avatar">
                                <el-icon><component :is="groupIcon(group)" /></el-icon>
                            </div>
                            <div>
                                <h3 class="group-title">{{ $t('fm_group_' + group) }}</h3>
                                <span class="group-subtitle">
                                    {{ $t('fm_group_active_ratio', {
                                        enabled: groupedFeatures[group].filter(f => f.is_enabled).length,
                                        total: groupedFeatures[group].length
                                    }) }}
                                </span>
                            </div>
                        </div>

                        <div class="group-actions">
                            <el-button
                                size="small"
                                text
                                type="success"
                                :icon="Check"
                                @click="toggleGroup(group, true)"
                            >
                                {{ $t('fm_enable_group') }}
                            </el-button>
                            <el-button
                                size="small"
                                text
                                type="danger"
                                :icon="Close"
                                @click="toggleGroup(group, false)"
                            >
                                {{ $t('fm_disable_group') }}
                            </el-button>
                        </div>
                    </div>

                    <div class="features-card-grid">
                        <div
                            v-for="feature in groupedFeatures[group]"
                            :key="feature.key"
                            class="feature-card"
                            :class="{
                                'is-enabled': feature.is_enabled,
                                'is-disabled': !feature.is_enabled,
                                'is-core': feature.is_core
                            }"
                        >
                            <!-- Card Header -->
                            <div class="card-top">
                                <div class="card-icon-title">
                                    <div class="module-icon" :class="feature.is_enabled ? 'active' : 'inactive'">
                                        <el-icon><component :is="iconFor(feature)" /></el-icon>
                                    </div>
                                    <div class="module-meta">
                                        <div class="module-name-row">
                                            <h4 class="module-name">{{ featureName(feature) }}</h4>
                                            <el-tag
                                                v-if="feature.is_core"
                                                size="small"
                                                type="warning"
                                                effect="light"
                                                class="core-tag"
                                            >
                                                <el-icon class="mr-0.5"><Lock /></el-icon>
                                                {{ $t('fm_core_badge') }}
                                            </el-tag>
                                        </div>
                                        <div class="module-code-wrap">
                                            <span
                                                class="module-key-badge"
                                                @click="copyKey(feature.key)"
                                                :title="$t('fm_key_copied')"
                                            >
                                                {{ feature.key }}
                                                <el-icon class="copy-icon"><CopyDocument /></el-icon>
                                            </span>
                                            <span class="module-en-alt" v-if="locale === 'ar' && feature.name_en">
                                                {{ feature.name_en }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-switch-wrap">
                                    <el-tooltip
                                        v-if="feature.is_core"
                                        :content="$t('fm_core_locked_tip')"
                                        placement="top"
                                    >
                                        <span>
                                            <el-switch
                                                :model-value="true"
                                                disabled
                                            />
                                        </span>
                                    </el-tooltip>
                                    <el-switch
                                        v-else
                                        :model-value="feature.is_enabled"
                                        :loading="savingKeys.includes(feature.key)"
                                        active-color="#10b981"
                                        @change="(val) => toggleFeature(feature, val)"
                                    />
                                </div>
                            </div>

                            <!-- Card Body: Description -->
                            <p class="module-description">
                                {{ featureDescription(feature) || $t('fm_description_placeholder') }}
                            </p>

                            <!-- Affected Routes Preview -->
                            <div v-if="feature.paths && feature.paths.length" class="routes-preview-box">
                                <div class="routes-header" @click="openRoutes(feature)">
                                    <div class="flex items-center gap-1 text-slate-500">
                                        <el-icon class="text-xs"><Link /></el-icon>
                                        <span class="text-xs font-semibold">{{ $t('fm_affected_routes') }} ({{ feature.paths.length }})</span>
                                    </div>
                                    <el-button text size="small" type="primary" class="p-0 text-xs">
                                        {{ $t('details') }}
                                    </el-button>
                                </div>
                                <div class="routes-chips">
                                    <code
                                        v-for="path in feature.paths.slice(0, 3)"
                                        :key="path"
                                        class="route-pill"
                                        :title="path"
                                    >
                                        {{ path }}
                                    </code>
                                    <span
                                        v-if="feature.paths.length > 3"
                                        class="route-more-pill"
                                        @click="openRoutes(feature)"
                                    >
                                        +{{ feature.paths.length - 3 }}
                                    </span>
                                </div>
                            </div>

                            <!-- Roles Section -->
                            <div class="card-roles-section">
                                <span class="roles-label">{{ $t('fm_all_roles') }}:</span>
                                <div class="card-role-chips">
                                    <el-tag
                                        v-for="role in grantedRoles(feature)"
                                        :key="role.id"
                                        size="small"
                                        effect="light"
                                        :type="role.is_super ? 'success' : 'primary'"
                                        class="role-badge"
                                    >
                                        {{ role.display_name || role.name }}
                                        <span v-if="role.users_count > 0" class="role-badge-count">
                                            {{ role.users_count }}
                                        </span>
                                    </el-tag>
                                    <span v-if="!grantedRoles(feature).length" class="no-roles-text">
                                        {{ $t('fm_super_always') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="card-footer">
                                <el-button
                                    size="small"
                                    type="primary"
                                    plain
                                    :icon="Key"
                                    class="action-btn"
                                    @click="openRoles(feature)"
                                >
                                    {{ $t('fm_manage_roles') }}
                                </el-button>

                                <div class="card-footer-secondary">
                                    <el-tooltip :content="$t('fm_edit_feature')" placement="top">
                                        <el-button
                                            size="small"
                                            circle
                                            :icon="Edit"
                                            @click="openEdit(feature)"
                                        />
                                    </el-tooltip>
                                    <el-tooltip :content="$t('fm_affected_routes')" placement="top">
                                        <el-button
                                            size="small"
                                            circle
                                            :icon="Link"
                                            @click="openRoutes(feature)"
                                        />
                                    </el-tooltip>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <el-empty
                v-if="!loading && !filteredFeatures.length"
                :description="$t('fm_empty')"
                class="empty-card"
            >
                <el-button type="primary" plain @click="resetFilters">
                    {{ $t('fm_reset_filters') }}
                </el-button>
            </el-empty>
        </div>

        <!-- VIEW MODE 2: TABLE VIEW -->
        <el-card v-else-if="viewMode === 'table'" shadow="never" class="table-card">
            <el-table
                v-loading="loading"
                :data="filteredFeatures"
                row-key="key"
                border
                stripe
                style="width: 100%"
                @selection-change="onSelectionChange"
            >
                <el-table-column type="selection" width="46" :selectable="(row) => !row.is_core" />

                <el-table-column :label="$t('fm_feature_column')" min-width="260">
                    <template #default="{ row }">
                        <div class="feature-cell">
                            <div class="feature-icon" :class="row.is_enabled ? 'on' : 'off'">
                                <el-icon><component :is="iconFor(row)" /></el-icon>
                            </div>
                            <div class="feature-cell-content">
                                <div class="feature-name">
                                    <span>{{ featureName(row) }}</span>
                                    <el-tag v-if="row.is_core" size="small" type="warning" effect="light">
                                        {{ $t('fm_core_badge') }}
                                    </el-tag>
                                </div>
                                <div class="feature-sub">
                                    <span class="feature-key" @click="copyKey(row.key)" :title="$t('fm_key_copied')">
                                        {{ row.key }}
                                        <el-icon class="copy-icon-sm"><CopyDocument /></el-icon>
                                    </span>
                                    <span v-if="locale === 'ar' && row.name_en" class="feature-alt-name">
                                        {{ row.name_en }}
                                    </span>
                                </div>
                                <p class="table-description-text" v-if="featureDescription(row)">
                                    {{ featureDescription(row) }}
                                </p>
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('fm_stat_groups')" width="140" align="center">
                    <template #default="{ row }">
                        <el-tag effect="plain" type="info" class="font-medium">
                            {{ $t('fm_group_' + row.group) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('fm_affected_routes')" min-width="200">
                    <template #default="{ row }">
                        <div v-if="row.paths && row.paths.length" class="table-routes-wrap">
                            <code v-for="path in row.paths.slice(0, 2)" :key="path" class="table-route-tag">
                                {{ path }}
                            </code>
                            <el-button
                                v-if="row.paths.length > 2"
                                text
                                size="small"
                                type="primary"
                                @click="openRoutes(row)"
                            >
                                +{{ row.paths.length - 2 }}
                            </el-button>
                        </div>
                        <span v-else class="muted-text text-xs">{{ $t('fm_no_routes') }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('fm_all_roles')" min-width="220">
                    <template #default="{ row }">
                        <div class="role-chips">
                            <el-tag
                                v-for="role in grantedRoles(row)"
                                :key="role.id"
                                size="small"
                                effect="light"
                                :type="role.is_super ? 'success' : 'primary'"
                            >
                                {{ role.display_name || role.name }}
                            </el-tag>
                            <span v-if="!grantedRoles(row).length" class="muted-text text-xs">—</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('fm_status_column')" width="130" align="center">
                    <template #default="{ row }">
                        <el-tooltip
                            v-if="row.is_core"
                            :content="$t('fm_core_locked_tip')"
                            placement="top"
                        >
                            <span><el-switch :model-value="true" disabled /></span>
                        </el-tooltip>
                        <el-switch
                            v-else
                            :model-value="row.is_enabled"
                            :loading="savingKeys.includes(row.key)"
                            active-color="#10b981"
                            @change="(val) => toggleFeature(row, val)"
                        />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('actions')" width="160" align="center" fixed="right">
                    <template #default="{ row }">
                        <div class="flex items-center justify-center gap-1">
                            <el-button
                                size="small"
                                type="primary"
                                plain
                                :icon="Key"
                                @click="openRoles(row)"
                            >
                                {{ $t('fm_manage_roles') }}
                            </el-button>
                            <el-tooltip :content="$t('fm_edit_feature')" placement="top">
                                <el-button
                                    size="small"
                                    circle
                                    :icon="Edit"
                                    @click="openEdit(row)"
                                />
                            </el-tooltip>
                        </div>
                    </template>
                </el-table-column>
            </el-table>

            <el-empty v-if="!loading && !filteredFeatures.length" :description="$t('fm_empty')" />
        </el-card>

        <!-- VIEW MODE 3: FULL PERMISSIONS MATRIX VIEW -->
        <el-card v-else-if="viewMode === 'matrix'" shadow="never" class="matrix-card">
            <div class="matrix-banner">
                <div class="flex items-center gap-2">
                    <el-icon class="text-primary text-xl"><Collection /></el-icon>
                    <div>
                        <h4 class="matrix-title">{{ $t('fm_matrix_title') }}</h4>
                        <p class="matrix-desc">{{ $t('fm_matrix_desc') }}</p>
                    </div>
                </div>
            </div>

            <div class="matrix-table-container">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th class="feature-col-th">{{ $t('fm_feature_column') }}</th>
                            <th class="status-col-th">{{ $t('fm_status_column') }}</th>
                            <th
                                v-for="role in roles"
                                :key="role.id"
                                class="role-col-th"
                                :class="{ 'is-super': role.is_super }"
                            >
                                <div class="role-th-wrap">
                                    <span class="role-th-name">{{ role.display_name || role.name }}</span>
                                    <small v-if="role.is_super" class="role-th-super">{{ $t('fm_super_always') }}</small>
                                    <small v-else class="role-th-users">{{ role.users_count }} {{ $t('fm_role_users') }}</small>
                                    <div v-if="!role.is_super" class="role-col-actions">
                                        <el-button
                                            link
                                            type="primary"
                                            size="small"
                                            @click="bulkGrantRoleInMatrix(role, true)"
                                        >
                                            {{ $t('fm_select_all') }}
                                        </el-button>
                                        <span class="text-slate-300">/</span>
                                        <el-button
                                            link
                                            type="danger"
                                            size="small"
                                            @click="bulkGrantRoleInMatrix(role, false)"
                                        >
                                            {{ $t('fm_clear') }}
                                        </el-button>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="group in activeGroups" :key="group">
                            <tr v-if="groupedFeatures[group]?.length" class="group-separator-row">
                                <td :colspan="roles.length + 2">
                                    <div class="group-sep-content">
                                        <el-icon><component :is="groupIcon(group)" /></el-icon>
                                        <strong>{{ $t('fm_group_' + group) }}</strong>
                                        <span class="text-xs text-slate-500 font-normal">
                                            ({{ groupedFeatures[group].length }} {{ $t('fm_stat_total') }})
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-for="feature in groupedFeatures[group]"
                                :key="feature.key"
                                class="matrix-row-item"
                                :class="{ 'disabled-row': !feature.is_enabled }"
                            >
                                <td class="feature-col-td">
                                    <div class="matrix-feature-info">
                                        <div class="module-icon-sm" :class="feature.is_enabled ? 'active' : 'inactive'">
                                            <el-icon><component :is="iconFor(feature)" /></el-icon>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm">
                                                {{ featureName(feature) }}
                                                <el-tag v-if="feature.is_core" size="small" type="warning" effect="light" class="ml-1">
                                                    {{ $t('fm_core_badge') }}
                                                </el-tag>
                                            </div>
                                            <span class="text-xs text-slate-400 font-mono">{{ feature.key }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="status-col-td text-center">
                                    <el-switch
                                        v-if="!feature.is_core"
                                        :model-value="feature.is_enabled"
                                        size="small"
                                        :loading="savingKeys.includes(feature.key)"
                                        active-color="#10b981"
                                        @change="(val) => toggleFeature(feature, val)"
                                    />
                                    <el-tag v-else size="small" type="warning" effect="light">
                                        {{ $t('fm_core_badge') }}
                                    </el-tag>
                                </td>

                                <td
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="role-col-td text-center"
                                >
                                    <div v-if="role.is_super" class="matrix-super-cell">
                                        <el-icon class="text-success text-base"><Check /></el-icon>
                                    </div>
                                    <div v-else class="matrix-checkbox-cell">
                                        <el-switch
                                            :model-value="feature.role_ids.includes(role.id)"
                                            size="small"
                                            :loading="isMatrixCellLoading(feature.id, role.id)"
                                            :disabled="!feature.is_enabled"
                                            @change="(val) => toggleMatrixRole(feature, role, val)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </el-card>

        <!-- DIALOG 1: Role Permissions Matrix Dialog (Feature specific) -->
        <el-dialog
            v-model="rolesDialogVisible"
            width="680px"
            :title="roleDialogTitle"
            append-to-body
            destroy-on-close
            class="custom-dialog"
        >
            <div v-if="activeFeature" class="roles-dialog">
                <div class="dialog-banner">
                    <div class="dialog-feature-badge">
                        <div class="module-icon active">
                            <el-icon><component :is="iconFor(activeFeature)" /></el-icon>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800">{{ featureName(activeFeature) }}</h4>
                            <span class="text-xs text-slate-400 font-mono">{{ activeFeature.key }}</span>
                        </div>
                    </div>
                    <div class="dialog-actions-quick">
                        <el-button size="small" text type="primary" @click="selectAllRoles">
                            {{ $t('fm_select_all') }}
                        </el-button>
                        <el-button size="small" text type="danger" @click="clearAllRoles">
                            {{ $t('fm_clear') }}
                        </el-button>
                    </div>
                </div>

                <p class="matrix-hint">{{ $t('fm_matrix_hint') }}</p>

                <el-input
                    v-model="roleSearchQuery"
                    :placeholder="$t('fm_role_search')"
                    clearable
                    :prefix-icon="Search"
                    class="mb-3"
                />

                <div class="matrix-list">
                    <div
                        v-for="role in filteredDialogRoles"
                        :key="role.id"
                        class="matrix-row"
                        :class="{ 'is-selected': roleSelection.includes(role.id) || role.is_super }"
                    >
                        <div class="matrix-role">
                            <div class="flex items-center gap-2">
                                <span class="matrix-name">{{ role.display_name || role.name }}</span>
                                <el-tag v-if="role.is_super" type="success" size="small" effect="light">
                                    {{ $t('fm_super_always') }}
                                </el-tag>
                            </div>
                            <small class="muted-text">
                                {{ role.description || (role.users_count + ' ' + $t('fm_role_users')) }}
                            </small>
                        </div>

                        <el-tag v-if="role.is_super" type="success" effect="plain" class="font-medium">
                            <el-icon class="mr-1"><Check /></el-icon>
                            {{ $t('fm_super_always') }}
                        </el-tag>
                        <el-switch
                            v-else
                            :model-value="roleSelection.includes(role.id)"
                            active-color="#10b981"
                            @change="(val) => toggleRoleSelection(role.id, val)"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="rolesDialogVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="savingRoles" @click="saveRoles">
                        {{ $t('save') }}
                    </el-button>
                </span>
            </template>
        </el-dialog>

        <!-- DIALOG 2: Edit Feature Information -->
        <el-dialog
            v-model="editDialogVisible"
            width="600px"
            :title="$t('fm_edit_dialog_title')"
            append-to-body
            destroy-on-close
        >
            <el-form
                v-if="editForm"
                label-position="top"
                class="edit-feature-form"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <el-form-item :label="$t('fm_name_ar')" required>
                        <el-input v-model="editForm.name_ar" :placeholder="$t('fm_name_ar')" />
                    </el-form-item>
                    <el-form-item :label="$t('fm_name_en')" required>
                        <el-input v-model="editForm.name_en" :placeholder="$t('fm_name_en')" />
                    </el-form-item>
                </div>

                <el-form-item :label="$t('fm_desc_ar')">
                    <el-input
                        v-model="editForm.description_ar"
                        type="textarea"
                        :rows="3"
                        :placeholder="$t('fm_description_placeholder')"
                    />
                </el-form-item>

                <el-form-item :label="$t('fm_desc_en')">
                    <el-input
                        v-model="editForm.description_en"
                        type="textarea"
                        :rows="3"
                        :placeholder="$t('fm_description_placeholder')"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="editDialogVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="savingEdit" @click="saveEdit">
                        {{ $t('save') }}
                    </el-button>
                </span>
            </template>
        </el-dialog>

        <!-- DIALOG 3: Routes & Affected Screens Inspector -->
        <el-dialog
            v-model="routesDialogVisible"
            width="600px"
            :title="$t('fm_routes_dialog_title')"
            append-to-body
        >
            <div v-if="inspectFeature" class="routes-dialog-content">
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl mb-4 border border-slate-200">
                    <div class="module-icon-sm" :class="inspectFeature.is_enabled ? 'active' : 'inactive'">
                        <el-icon><component :is="iconFor(inspectFeature)" /></el-icon>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800">{{ featureName(inspectFeature) }}</h4>
                        <span class="text-xs text-slate-500">{{ $t('fm_routes_dialog_subtitle') }}</span>
                    </div>
                </div>

                <div class="routes-list">
                    <div
                        v-for="path in inspectFeature.paths || []"
                        :key="path"
                        class="route-item"
                    >
                        <el-icon class="text-slate-400"><Link /></el-icon>
                        <code class="route-code">{{ path }}</code>
                        <el-button
                            text
                            size="small"
                            :icon="CopyDocument"
                            class="copy-route-btn"
                            @click="copyKey(path)"
                        />
                    </div>
                    <div v-if="!inspectFeature.paths || !inspectFeature.paths.length" class="text-center py-6 text-slate-400">
                        {{ $t('fm_no_routes') }}
                    </div>
                </div>
            </div>
            <template #footer>
                <el-button @click="routesDialogVisible = false">{{ $t('close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { matchesSearch } from '@/utils/search';
import { featuresApi } from '@/api/features';
import {
    Refresh, Search, Grid, CircleCheck, CircleClose, Collection, Key,
    Odometer, Box, ShoppingCart, Refresh as RmaIcon, ShoppingBag, Coin,
    UserFilled, ChatDotRound, Tools, DataAnalysis, Cpu, Bell, View, Monitor,
    ChatLineRound, Setting, Location, TrendCharts, Edit, Link, Check, Close,
    Document, CopyDocument, Lock,
} from '@element-plus/icons-vue';

const { t, locale } = useI18n();

const ICONS = {
    Odometer, Box, ShoppingCart, Refresh: RmaIcon, ShoppingBag, Coin,
    UserFilled, ChatDotRound, Tools, DataAnalysis, Cpu, Bell, View, Monitor,
    ChatLineRound, Setting, Location, TrendCharts, Grid, Collection,
};

const GROUP_ICONS = {
    general: Odometer,
    content: Box,
    commerce: ShoppingCart,
    inventory: Location,
    hr: UserFilled,
    reports: TrendCharts,
    system: Setting,
};

const groupOrder = ['general', 'content', 'commerce', 'inventory', 'hr', 'reports', 'system'];

const loading = ref(false);
const savingRoles = ref(false);
const bulkSaving = ref(false);
const savingEdit = ref(false);

const features = ref([]);
const roles = ref([]);
const stats = ref({ total: 0, enabled: 0, disabled: 0, groups: 0, core: 0 });

const viewMode = ref('cards'); // 'cards' | 'table' | 'matrix'
const search = ref('');
const statusFilter = ref('all');
const groupFilter = ref(null);
const roleFilter = ref(null);
const selectedKeys = ref([]);
const savingKeys = ref([]);
const matrixCellLoading = ref({}); // 'featureId_roleId' => boolean

// Dialogs state
const rolesDialogVisible = ref(false);
const activeFeature = ref(null);
const roleSelection = ref([]);
const roleSearchQuery = ref('');

const editDialogVisible = ref(false);
const editFeature = ref(null);
const editForm = ref({ name_ar: '', name_en: '', description_ar: '', description_en: '' });

const routesDialogVisible = ref(false);
const inspectFeature = ref(null);

const iconFor = (feature) => ICONS[feature.icon] || Grid;
const groupIcon = (group) => GROUP_ICONS[group] || Collection;

const featureName = (feature) => (
    locale.value === 'ar' ? (feature.name_ar || feature.key) : (feature.name_en || feature.name_ar || feature.key)
);

const featureDescription = (feature) => (
    locale.value === 'ar' ? (feature.description_ar || feature.description_en || '') : (feature.description_en || feature.description_ar || '')
);

const grantedRoles = (feature) => roles.value.filter((role) => feature.role_ids && feature.role_ids.includes(role.id));

const filteredDialogRoles = computed(() => {
    if (!roleSearchQuery.value) return roles.value;
    const q = roleSearchQuery.value.toLowerCase();
    return roles.value.filter((r) =>
        (r.display_name && r.display_name.toLowerCase().includes(q)) ||
        (r.name && r.name.toLowerCase().includes(q))
    );
});

const filteredFeatures = computed(() => features.value.filter((feature) => {
    const searchTargets = [
        feature.name_ar,
        feature.name_en,
        feature.key,
        feature.description_ar,
        feature.description_en,
        ...(feature.paths || []),
    ];
    if (!matchesSearch(searchTargets, search.value)) return false;
    if (groupFilter.value && feature.group !== groupFilter.value) return false;
    if (statusFilter.value === 'enabled' && !feature.is_enabled) return false;
    if (statusFilter.value === 'disabled' && feature.is_enabled) return false;
    if (statusFilter.value === 'core' && !feature.is_core) return false;

    if (roleFilter.value) {
        const selectedRole = roles.value.find((r) => r.id === roleFilter.value);
        if (selectedRole && !selectedRole.is_super) {
            if (!feature.role_ids || !feature.role_ids.includes(roleFilter.value)) return false;
        }
    }

    return true;
}));

const groupedFeatures = computed(() => {
    const groups = {};
    groupOrder.forEach((g) => { groups[g] = []; });
    filteredFeatures.value.forEach((feature) => {
        if (!groups[feature.group]) groups[feature.group] = [];
        groups[feature.group].push(feature);
    });
    return groups;
});

const activeGroups = computed(() => {
    if (groupFilter.value) {
        return groupOrder.filter((g) => g === groupFilter.value);
    }
    return groupOrder;
});

const roleDialogTitle = computed(() => (
    activeFeature.value ? `${t('fm_matrix_title')} — ${featureName(activeFeature.value)}` : t('fm_matrix_title')
));

function setStatusFilter(status) {
    statusFilter.value = status;
}

function resetFilters() {
    search.value = '';
    statusFilter.value = 'all';
    groupFilter.value = null;
    roleFilter.value = null;
}

function copyKey(text) {
    if (!text) return;
    if (navigator?.clipboard?.writeText) {
        navigator.clipboard.writeText(text);
        ElMessage.success(t('fm_key_copied'));
    }
}

async function fetchFeatures() {
    loading.value = true;
    try {
        const res = await featuresApi.getAll();
        const data = res.data?.data || {};
        features.value = data.features || [];
        roles.value = data.roles || [];
        recalcStats();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('fm_no_permission'));
    } finally {
        loading.value = false;
    }
}

function onSelectionChange(rows) {
    selectedKeys.value = rows.map((row) => row.key);
}

async function toggleFeature(feature, value) {
    if (feature.is_core && !value) {
        ElMessage.warning(t('fm_core_locked_tip'));
        return;
    }

    if (!value) {
        try {
            await ElMessageBox.confirm(
                t('fm_confirm_disable_text'),
                t('fm_confirm_disable_title'),
                {
                    confirmButtonText: t('fm_confirm'),
                    cancelButtonText: t('fm_cancel'),
                    type: 'warning',
                }
            );
        } catch {
            return;
        }
    }

    savingKeys.value = [...savingKeys.value, feature.key];
    try {
        await featuresApi.update(feature.id, { is_enabled: value });
        feature.is_enabled = value;
        recalcStats();
        ElMessage.success(t('fm_update_success'));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('fm_error'));
    } finally {
        savingKeys.value = savingKeys.value.filter((key) => key !== feature.key);
    }
}

async function toggleGroup(group, isEnabled) {
    if (!isEnabled) {
        try {
            await ElMessageBox.confirm(
                t('fm_confirm_disable_group'),
                t('fm_disable_group'),
                {
                    confirmButtonText: t('fm_confirm'),
                    cancelButtonText: t('fm_cancel'),
                    type: 'warning',
                }
            );
        } catch {
            return;
        }
    }

    loading.value = true;
    try {
        await featuresApi.toggleGroup(group, isEnabled);
        features.value.forEach((feature) => {
            if (feature.group === group && (!feature.is_core || isEnabled)) {
                feature.is_enabled = isEnabled;
            }
        });
        recalcStats();
        ElMessage.success(t('fm_update_success'));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('fm_error'));
    } finally {
        loading.value = false;
    }
}

async function bulkToggle(isEnabled) {
    if (selectedKeys.value.length === 0) return;

    if (!isEnabled) {
        try {
            await ElMessageBox.confirm(
                t('fm_confirm_disable_text'),
                t('fm_confirm_disable_title'),
                {
                    confirmButtonText: t('fm_confirm'),
                    cancelButtonText: t('fm_cancel'),
                    type: 'warning',
                }
            );
        } catch {
            return;
        }
    }

    bulkSaving.value = true;
    try {
        await featuresApi.bulkToggle(selectedKeys.value, isEnabled);
        features.value.forEach((feature) => {
            if (selectedKeys.value.includes(feature.key) && (!feature.is_core || isEnabled)) {
                feature.is_enabled = isEnabled;
            }
        });
        selectedKeys.value = [];
        recalcStats();
        ElMessage.success(t('fm_update_success'));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('fm_error'));
    } finally {
        bulkSaving.value = false;
    }
}

function isMatrixCellLoading(featureId, roleId) {
    return !!matrixCellLoading.value[`${featureId}_${roleId}`];
}

async function toggleMatrixRole(feature, role, granted) {
    const key = `${feature.id}_${role.id}`;
    matrixCellLoading.value = { ...matrixCellLoading.value, [key]: true };

    try {
        await featuresApi.toggleRole(feature.id, role.id, granted);
        if (granted) {
            if (!feature.role_ids.includes(role.id)) {
                feature.role_ids = [...feature.role_ids, role.id];
            }
        } else {
            feature.role_ids = feature.role_ids.filter((id) => id !== role.id);
        }
        ElMessage.success(t('fm_roles_success'));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('fm_error'));
    } finally {
        const next = { ...matrixCellLoading.value };
        delete next[key];
        matrixCellLoading.value = next;
    }
}

async function bulkGrantRoleInMatrix(role, grant) {
    loading.value = true;
    try {
        const promises = features.value.map(async (feature) => {
            const hasRole = feature.role_ids.includes(role.id);
            if (grant && !hasRole) {
                await featuresApi.toggleRole(feature.id, role.id, true);
                feature.role_ids = [...feature.role_ids, role.id];
            } else if (!grant && hasRole) {
                await featuresApi.toggleRole(feature.id, role.id, false);
                feature.role_ids = feature.role_ids.filter((id) => id !== role.id);
            }
        });
        await Promise.all(promises);
        ElMessage.success(t('fm_roles_success'));
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('fm_error'));
    } finally {
        loading.value = false;
    }
}

function openRoles(feature) {
    activeFeature.value = feature;
    roleSearchQuery.value = '';
    roleSelection.value = (feature.role_ids || []).filter(
        (id) => !roles.value.find((role) => role.id === id)?.is_super
    );
    rolesDialogVisible.value = true;
}

function toggleRoleSelection(roleId, enabled) {
    if (enabled) {
        if (!roleSelection.value.includes(roleId)) {
            roleSelection.value = [...roleSelection.value, roleId];
        }
    } else {
        roleSelection.value = roleSelection.value.filter((id) => id !== roleId);
    }
}

function selectAllRoles() {
    roleSelection.value = roles.value.filter((r) => !r.is_super).map((r) => r.id);
}

function clearAllRoles() {
    roleSelection.value = [];
}

async function saveRoles() {
    if (!activeFeature.value) return;
    savingRoles.value = true;
    try {
        await featuresApi.updateRoles(activeFeature.value.id, roleSelection.value);
        activeFeature.value.role_ids = [...roleSelection.value];
        ElMessage.success(t('fm_roles_success'));
        rolesDialogVisible.value = false;
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('fm_error'));
    } finally {
        savingRoles.value = false;
    }
}

function openEdit(feature) {
    editFeature.value = feature;
    editForm.value = {
        name_ar: feature.name_ar || '',
        name_en: feature.name_en || '',
        description_ar: feature.description_ar || '',
        description_en: feature.description_en || '',
    };
    editDialogVisible.value = true;
}

async function saveEdit() {
    if (!editFeature.value) return;
    savingEdit.value = true;
    try {
        const res = await featuresApi.update(editFeature.value.id, editForm.value);
        const updated = res.data?.data?.feature;
        if (updated) {
            editFeature.value.name_ar = updated.name_ar;
            editFeature.value.name_en = updated.name_en;
            editFeature.value.description_ar = updated.description_ar;
            editFeature.value.description_en = updated.description_en;
        }
        ElMessage.success(t('fm_update_success'));
        editDialogVisible.value = false;
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('fm_error'));
    } finally {
        savingEdit.value = false;
    }
}

function openRoutes(feature) {
    inspectFeature.value = feature;
    routesDialogVisible.value = true;
}

function recalcStats() {
    stats.value = {
        total: features.value.length,
        enabled: features.value.filter((f) => f.is_enabled).length,
        disabled: features.value.filter((f) => !f.is_enabled).length,
        core: features.value.filter((f) => f.is_core).length,
        groups: new Set(features.value.map((f) => f.group)).size,
    };
}

onMounted(fetchFeatures);
</script>

<style scoped>
.features-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 0;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    background: #ffffff;
    padding: 24px 28px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.03);
}

.header-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}

.eyebrow {
    font-size: 12px;
    color: #6366f1;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.version-tag {
    font-weight: 700;
    font-size: 11px;
    border-radius: 6px;
}

.page-header h2 {
    margin: 0;
    font-size: 26px;
    color: #0f172a;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.subtitle {
    margin: 6px 0 0;
    color: #64748b;
    max-width: 650px;
    font-size: 14px;
    line-height: 1.6;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.view-switcher :deep(.el-radio-button__inner) {
    display: inline-flex;
    align-items: center;
    font-weight: 600;
    padding: 10px 18px;
}

.refresh-btn {
    border-radius: 10px;
    height: 40px;
    font-weight: 700;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.stat-card {
    position: relative;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.03);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.08);
    border-color: #cbd5e1;
}

.stat-card.active {
    border-color: #4f46e5;
    background: linear-gradient(135deg, #ffffff, #f8faff);
    box-shadow: 0 10px 25px -4px rgba(79, 70, 229, 0.15);
}

.stat-card.active .stat-indicator {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #4f46e5;
}

.icon-wrap {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 22px;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.stat-card:hover .icon-wrap {
    transform: scale(1.08);
}

.icon-wrap.total-icon { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.icon-wrap.success { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.icon-wrap.danger { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
.icon-wrap.warning { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.icon-wrap.muted { background: rgba(100, 116, 139, 0.12); color: #64748b; }

.stat-body {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-card strong {
    font-size: 28px;
    color: #0f172a;
    font-weight: 800;
    line-height: 1.1;
}

/* Toolbar */
.toolbar-card {
    border-radius: 18px;
    border: 1px solid #e2e8f0;
}

.toolbar {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    width: 300px;
}

.group-select,
.role-select {
    width: 190px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.status-dot.on { background: #10b981; }
.status-dot.off { background: #ef4444; }

.bulk-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-inline-start: auto;
    padding: 6px 12px;
    background: #f1f5f9;
    border-radius: 12px;
}

/* Grouped Cards View */
.cards-view {
    display: flex;
    flex-direction: column;
    gap: 28px;
}

.group-section {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 6px;
}

.group-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}

.group-icon-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #e0e7ff;
    color: #4338ca;
    display: grid;
    place-items: center;
    font-size: 18px;
}

.group-title {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: #1e293b;
}

.group-subtitle {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
}

.features-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 18px;
}

.feature-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.02);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

.feature-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px -4px rgba(15, 23, 42, 0.07);
    border-color: #cbd5e1;
}

.feature-card.is-disabled {
    opacity: 0.78;
    background: #fafafa;
}

.feature-card.is-core {
    border-color: #fde68a;
}

.card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.card-icon-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.module-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 20px;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.module-icon.active {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
    color: #059669;
}

.module-icon.inactive {
    background: #f1f5f9;
    color: #94a3b8;
}

.module-meta {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.module-name-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.module-name {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
}

.core-tag {
    font-weight: 700;
    font-size: 10px;
    padding: 0 6px;
    height: 20px;
    line-height: 20px;
}

.module-code-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}

.module-key-badge {
    color: #64748b;
    font-family: monospace;
    font-size: 11px;
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: background 0.15s;
}

.module-key-badge:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.module-en-alt {
    color: #94a3b8;
    font-size: 11px;
}

.module-description {
    margin: 0;
    font-size: 13px;
    color: #475569;
    line-height: 1.6;
    min-height: 42px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.routes-preview-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 8px 12px;
}

.routes-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    cursor: pointer;
}

.routes-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.route-pill {
    font-size: 11px;
    font-family: monospace;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 1px 6px;
    border-radius: 4px;
    color: #0284c7;
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.route-more-pill {
    font-size: 11px;
    font-weight: 700;
    background: #e0f2fe;
    color: #0369a1;
    padding: 1px 6px;
    border-radius: 4px;
    cursor: pointer;
}

.card-roles-section {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.roles-label {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.card-role-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.role-badge {
    border-radius: 8px;
    font-weight: 600;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.role-badge-count {
    background: rgba(0, 0, 0, 0.08);
    border-radius: 4px;
    padding: 0 4px;
    font-size: 10px;
}

.no-roles-text {
    font-size: 12px;
    color: #10b981;
    font-weight: 600;
}

.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
    margin-top: auto;
}

.card-footer-secondary {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Table View */
.table-card {
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.feature-cell {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 6px 0;
}

.feature-cell-content {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.feature-name {
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}

.feature-sub {
    display: flex;
    align-items: center;
    gap: 8px;
}

.feature-key {
    color: #64748b;
    font-family: monospace;
    font-size: 11px;
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.feature-alt-name {
    color: #94a3b8;
    font-size: 11px;
}

.table-description-text {
    margin: 4px 0 0;
    font-size: 12px;
    color: #64748b;
    line-height: 1.5;
}

.table-routes-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
}

.table-route-tag {
    font-size: 11px;
    font-family: monospace;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 2px 6px;
    border-radius: 4px;
    color: #0284c7;
}

.role-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

/* Matrix View */
.matrix-card {
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.matrix-banner {
    padding: 16px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.matrix-title {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
}

.matrix-desc {
    margin: 2px 0 0;
    font-size: 13px;
    color: #64748b;
}

.matrix-table-container {
    overflow-x: auto;
}

.matrix-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.matrix-table th,
.matrix-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.matrix-table thead th {
    background: #f8fafc;
    color: #0f172a;
    font-weight: 800;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 2;
}

.matrix-table thead th.feature-col-th {
    text-align: start;
    min-width: 240px;
}

.matrix-table thead th.status-col-th {
    min-width: 100px;
}

.role-th-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.role-th-name {
    font-weight: 800;
    color: #0f172a;
}

.role-th-super {
    font-size: 10px;
    color: #10b981;
    font-weight: 700;
}

.role-th-users {
    font-size: 10px;
    color: #64748b;
}

.role-col-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    font-size: 10px;
}

.group-separator-row td {
    background: #f1f5f9;
    padding: 8px 16px;
}

.group-sep-content {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #334155;
    font-size: 13px;
}

.matrix-row-item:hover td {
    background: #f8fafc;
}

.matrix-row-item.disabled-row {
    opacity: 0.6;
}

.matrix-feature-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.module-icon-sm {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 16px;
    flex-shrink: 0;
}

.module-icon-sm.active {
    background: #ecfdf5;
    color: #059669;
}

.module-icon-sm.inactive {
    background: #f1f5f9;
    color: #94a3b8;
}

.matrix-super-cell {
    display: grid;
    place-items: center;
}

.matrix-checkbox-cell {
    display: grid;
    place-items: center;
}

/* Dialogs */
.dialog-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 14px;
}

.dialog-feature-badge {
    display: flex;
    align-items: center;
    gap: 10px;
}

.matrix-hint {
    margin: 0 0 14px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
}

.matrix-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 360px;
    overflow-y: auto;
    padding-right: 4px;
}

.matrix-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    transition: all 0.15s ease;
}

.matrix-row.is-selected {
    border-color: #c7d2fe;
    background: #f8faff;
}

.matrix-role {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.matrix-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 14px;
}

.routes-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 340px;
    overflow-y: auto;
}

.route-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.route-code {
    font-family: monospace;
    font-size: 13px;
    color: #0369a1;
    flex: 1;
}

.copy-route-btn {
    padding: 2px;
}

/* Animations */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Responsive */
@media (max-width: 992px) {
    .features-card-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }

    .header-actions {
        justify-content: space-between;
    }

    .search-input {
        width: 100%;
    }

    .group-select,
    .role-select {
        width: 100%;
    }

    .features-card-grid {
        grid-template-columns: 1fr;
    }

    .bulk-actions {
        margin-inline-start: 0;
        width: 100%;
        justify-content: space-between;
    }
}
</style>
